<?php

namespace App\Services;

use App\Enums\ReadingSource;
use App\Exceptions\MeterChangeRejected;
use App\Exceptions\ReadingRejected;
use App\Models\Meter;
use App\Models\Reading;
use App\Models\User;
use App\Support\Milli;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Changes to a meter's master data. Every change is checked against the
 * meter's readings, so billing never loses its basis unnoticed.
 */
class MeterMaintenance
{
    public function __construct(private readonly ReadingRecorder $recorder) {}

    /**
     * Take a meter out of service. This is the only place that sets `removed_on`.
     *
     * The final value is mandatory: without it, the consumption between the last
     * reading and the removal would silently drop out of the billing.
     *
     * @param  int|null  $finalValueMilli  May only be omitted if the meter already has a reading on that date.
     *
     * @throws MeterChangeRejected
     */
    public function retire(Meter $meter, Carbon $date, ?int $finalValueMilli, ReadingSource $source, ?User $user = null): Meter
    {
        return DB::transaction(function () use ($meter, $date, $finalValueMilli, $source, $user) {
            $meter = Meter::findAndLock($meter->getKey());

            if ($meter->removed_on !== null) {
                throw new MeterChangeRejected('meter', ['Dieser Zähler ist bereits ausgebaut.']);
            }

            if ($date->lt($meter->installed_on)) {
                throw new MeterChangeRejected('date', [sprintf(
                    'Das Datum liegt vor dem Einbau des Zählers (%s).', $meter->installed_on->format('d.m.Y'),
                )]);
            }

            $this->recordFinalReading($meter, $date, $finalValueMilli, $source, $user);

            $meter->update(['removed_on' => $date]);

            return $meter;
        });
    }

    /**
     * Undo a removal. The final reading stays: it is a real reading.
     *
     * @throws MeterChangeRejected
     */
    public function reactivate(Meter $meter): Meter
    {
        return DB::transaction(function () use ($meter) {
            $meter = Meter::findAndLock($meter->getKey());

            if ($meter->removed_on === null) {
                throw new MeterChangeRejected('meter', ['Dieser Zähler ist nicht ausgebaut.']);
            }

            $meter->update(['removed_on' => null]);

            return $meter;
        });
    }

    /**
     * Fix a data-entry mistake. Installation date and initial value must not
     * contradict the readings that already exist.
     *
     * @throws MeterChangeRejected
     */
    public function update(Meter $meter, string $serialNumber, Carbon $installedOn, int $initialValueMilli): Meter
    {
        return DB::transaction(function () use ($meter, $serialNumber, $installedOn, $initialValueMilli) {
            $meter = Meter::findAndLock($meter->getKey());

            /** @var Reading|null $first */
            $first = $meter->effectiveReadings()->first();

            if ($first !== null && $installedOn->gt($first->read_on)) {
                throw new MeterChangeRejected('installed_on', [sprintf(
                    'Das Einbaudatum darf nicht nach der ersten Ablesung liegen (%s).', $first->read_on->format('d.m.Y'),
                )]);
            }

            if ($meter->removed_on !== null && $installedOn->gt($meter->removed_on)) {
                throw new MeterChangeRejected('installed_on', [sprintf(
                    'Das Einbaudatum darf nicht nach dem Ausbau liegen (%s).', $meter->removed_on->format('d.m.Y'),
                )]);
            }

            if ($first !== null && $initialValueMilli > $first->value_milli) {
                throw new MeterChangeRejected('initial_value', [sprintf(
                    'Der Anfangsstand darf nicht höher sein als die erste Ablesung (%s %s am %s).',
                    Milli::format($first->value_milli), $meter->type->unit(), $first->read_on->format('d.m.Y'),
                )]);
            }

            $meter->update([
                'serial_number' => $serialNumber,
                'installed_on' => $installedOn,
                'initial_value_milli' => $initialValueMilli,
            ]);

            return $meter;
        });
    }

    /**
     * Only meters without any reading can be deleted; corrected readings count
     * too, because they are part of the history.
     *
     * @throws MeterChangeRejected
     */
    public function delete(Meter $meter): void
    {
        DB::transaction(function () use ($meter) {
            $meter = Meter::findAndLock($meter->getKey());

            if ($meter->readings()->exists()) {
                throw new MeterChangeRejected('meter', [
                    'Der Zähler hat Ablesungen und kann nicht gelöscht werden. Wird er nicht mehr genutzt, bitte ausbauen.',
                ]);
            }

            $meter->delete();
        });
    }

    private function recordFinalReading(Meter $meter, Carbon $date, ?int $finalValueMilli, ReadingSource $source, ?User $user): void
    {
        $readings = $meter->effectiveReadings()->get();

        /** @var Reading|null $later */
        $later = $readings->first(fn (Reading $r) => $r->read_on->gt($date));

        if ($later !== null) {
            throw new MeterChangeRejected('date', [sprintf(
                'Der Zähler wurde am %s noch abgelesen. Das Datum darf nicht davor liegen.', $later->read_on->format('d.m.Y'),
            )]);
        }

        /** @var Reading|null $sameDay */
        $sameDay = $readings->first(fn (Reading $r) => $r->read_on->isSameDay($date));

        if ($sameDay !== null) {
            // The reading of that day is the final value. A second, different one is not created.
            if ($finalValueMilli !== null && $finalValueMilli !== $sameDay->value_milli) {
                throw new MeterChangeRejected('final_value', [sprintf(
                    'Für den Zähler gibt es an diesem Tag bereits eine Ablesung mit %s %s. Bitte diese korrigieren oder das Feld leer lassen.',
                    Milli::format($sameDay->value_milli), $meter->type->unit(),
                )]);
            }

            return;
        }

        if ($finalValueMilli === null) {
            throw new MeterChangeRejected('final_value', [
                'Bitte den Endstand des Zählers am Ausbautag angeben. Ohne ihn fehlt der Verbrauch bis zum Ausbau in der Abrechnung.',
            ]);
        }

        try {
            $this->recorder->record($meter, $date, $finalValueMilli, $source, $user, 'Endstand bei Ausbau');
        } catch (ReadingRejected $e) {
            throw new MeterChangeRejected('final_value', $e->reasons);
        }
    }
}
