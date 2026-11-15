<?php

namespace App\Services;

use App\Enums\MeterType;
use App\Enums\ReadingSource;
use App\Exceptions\MeterExchangeRejected;
use App\Exceptions\ReadingRejected;
use App\Models\Meter;
use App\Models\Reading;
use App\Models\User;
use App\Support\Milli;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Replaces a meter by a new one of the same type.
 *
 * The old meter's final value is mandatory: without it, the consumption between
 * its last reading and the removal would silently drop out of the billing.
 * Final reading, removal and the new meter are stored together or not at all.
 */
class MeterExchange
{
    public function __construct(private readonly ReadingRecorder $recorder) {}

    /**
     * @param  int|null  $finalValueMilli  May only be omitted if the old meter already has a reading on the exchange date.
     *
     * @throws MeterExchangeRejected
     */
    public function exchange(
        Meter $old,
        Carbon $date,
        ?int $finalValueMilli,
        MeterType $newType,
        string $newSerialNumber,
        int $newInitialValueMilli,
        ?User $user = null,
    ): Meter {
        return DB::transaction(function () use ($old, $date, $finalValueMilli, $newType, $newSerialNumber, $newInitialValueMilli, $user) {
            $old = Meter::query()->lockForUpdate()->findOrFail($old->getKey());

            if ($old->removed_on !== null) {
                throw new MeterExchangeRejected('replaces_meter_id', ['Dieser Zähler ist bereits ausgebaut.']);
            }

            if ($newType !== $old->type) {
                throw new MeterExchangeRejected('type', [sprintf(
                    'Der neue Zähler muss dieselbe Art haben wie der ersetzte (%s).', $old->type->label(),
                )]);
            }

            if ($date->lt($old->installed_on)) {
                throw new MeterExchangeRejected('installed_on', [sprintf(
                    'Das Tauschdatum liegt vor dem Einbau des alten Zählers (%s).', $old->installed_on->format('d.m.Y'),
                )]);
            }

            $this->recordFinalReading($old, $date, $finalValueMilli, $user);

            $old->update(['removed_on' => $date]);

            return $old->plot->meters()->create([
                'type' => $newType,
                'serial_number' => $newSerialNumber,
                'installed_on' => $date,
                'initial_value_milli' => $newInitialValueMilli,
            ]);
        });
    }

    private function recordFinalReading(Meter $old, Carbon $date, ?int $finalValueMilli, ?User $user): void
    {
        $unit = $old->type->unit();
        $readings = $old->effectiveReadings()->get();

        /** @var Reading|null $later */
        $later = $readings->first(fn (Reading $r) => $r->read_on->gt($date));

        if ($later !== null) {
            throw new MeterExchangeRejected('installed_on', [sprintf(
                'Der alte Zähler wurde am %s noch abgelesen. Das Tauschdatum darf nicht davor liegen.',
                $later->read_on->format('d.m.Y'),
            )]);
        }

        /** @var Reading|null $sameDay */
        $sameDay = $readings->first(fn (Reading $r) => $r->read_on->isSameDay($date));

        if ($sameDay !== null) {
            // The reading of that day is the final value. A second, different one is not created.
            if ($finalValueMilli !== null && $finalValueMilli !== $sameDay->value_milli) {
                throw new MeterExchangeRejected('final_value', [sprintf(
                    'Für den alten Zähler gibt es am Tauschtag bereits eine Ablesung mit %s %s. Bitte diese korrigieren oder das Feld leer lassen.',
                    Milli::format($sameDay->value_milli), $unit,
                )]);
            }

            return;
        }

        if ($finalValueMilli === null) {
            throw new MeterExchangeRejected('final_value', [
                'Bitte den Endstand des alten Zählers am Tauschtag angeben. Ohne ihn fehlt der Verbrauch bis zum Ausbau in der Abrechnung.',
            ]);
        }

        try {
            $this->recorder->record($old, $date, $finalValueMilli, ReadingSource::Exchange, $user, 'Endstand bei Zählertausch');
        } catch (ReadingRejected $e) {
            throw new MeterExchangeRejected('final_value', $e->reasons);
        }
    }
}
