<?php

namespace App\Services;

use App\Models\Meter;
use App\Models\Reading;
use App\Support\Milli;
use Illuminate\Support\Carbon;

/**
 * Decides whether a new meter value can be right.
 *
 * Hard rules reject the value (a mechanical meter never runs backwards).
 * A soft rule flags unusually high consumption: such values are often typos,
 * but can be real (a leaking pipe), so the board decides.
 */
class PlausibilityChecker
{
    /** A consumption rate this many times above the meter's own average is flagged. */
    public const SPIKE_FACTOR = 3;

    /** The meter's own average is only trusted after this many days of history. */
    public const MIN_HISTORY_DAYS = 180;

    public function check(Meter $meter, Carbon $readOn, int $valueMilli, ?int $ignoreReadingId = null): PlausibilityResult
    {
        $errors = [];
        $unit = $meter->type->unit();

        if ($readOn->isAfter(today())) {
            $errors[] = 'Das Ablesedatum liegt in der Zukunft.';
        }

        if (! $meter->isActiveOn($readOn)) {
            $errors[] = 'Der Zähler war an diesem Datum nicht eingebaut.';
        }

        $readings = $meter->effectiveReadings()
            ->when($ignoreReadingId, fn ($q) => $q->whereKeyNot($ignoreReadingId))
            ->get();

        if ($readings->contains(fn (Reading $r) => $r->read_on->isSameDay($readOn))) {
            $errors[] = 'Für diesen Zähler gibt es an diesem Datum bereits eine Ablesung. Bitte die vorhandene korrigieren.';
        }

        /** @var Reading|null $previous */
        $previous = $readings->filter(fn (Reading $r) => $r->read_on->lt($readOn))->last();
        /** @var Reading|null $next */
        $next = $readings->first(fn (Reading $r) => $r->read_on->gt($readOn));

        $floorMilli = $previous?->value_milli ?? $meter->initial_value_milli;
        $floorDate = $previous?->read_on ?? $meter->installed_on;

        if ($valueMilli < $floorMilli) {
            $errors[] = sprintf(
                'Der Stand ist kleiner als der vorherige (%s %s am %s). Ein Zähler läuft nicht rückwärts; wurde er getauscht, bitte einen neuen Zähler anlegen.',
                Milli::format($floorMilli), $unit, $floorDate->format('d.m.Y'),
            );
        }

        if ($next !== null && $valueMilli > $next->value_milli) {
            $errors[] = sprintf(
                'Der Stand ist größer als die spätere Ablesung (%s %s am %s).',
                Milli::format($next->value_milli), $unit, $next->read_on->format('d.m.Y'),
            );
        }

        if ($errors !== []) {
            return new PlausibilityResult($errors);
        }

        return new PlausibilityResult(
            warning: $this->spikeWarning($meter, $floorMilli, $floorDate, $readOn, $valueMilli),
        );
    }

    private function spikeWarning(Meter $meter, int $floorMilli, Carbon $floorDate, Carbon $readOn, int $valueMilli): ?string
    {
        $consumption = $valueMilli - $floorMilli;
        $days = max(1, (int) $floorDate->diffInDays($readOn));
        $unit = $meter->type->unit();

        $historyDays = (int) $meter->installed_on->diffInDays($floorDate);
        $historyConsumption = $floorMilli - $meter->initial_value_milli;

        if ($historyDays >= self::MIN_HISTORY_DAYS && $historyConsumption > 0) {
            // Compare rates per day, so intervals of different length stay comparable.
            $expected = $historyConsumption / $historyDays * $days;

            if ($consumption > self::SPIKE_FACTOR * $expected) {
                return sprintf(
                    'Verbrauch von %s %s in %d Tagen ist mehr als %d-mal so hoch wie der bisherige Durchschnitt dieses Zählers (ca. %s %s).',
                    Milli::format($consumption, 1), $unit, $days, self::SPIKE_FACTOR, Milli::format((int) round($expected), 1), $unit,
                );
            }

            return null;
        }

        // Not enough history: fall back to a generous limit for one allotment plot.
        $limit = (int) round($meter->type->plausibleYearlyMaximumMilli() * max(1, $days / 365));

        if ($consumption > $limit) {
            return sprintf(
                'Verbrauch von %s %s liegt über dem für eine Parzelle üblichen Höchstwert (%s %s pro Jahr).',
                Milli::format($consumption, 1), $unit, Milli::format($meter->type->plausibleYearlyMaximumMilli(), 0), $unit,
            );
        }

        return null;
    }
}
