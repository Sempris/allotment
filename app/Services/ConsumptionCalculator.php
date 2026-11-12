<?php

namespace App\Services;

use App\Models\Meter;
use App\Models\Reading;
use Illuminate\Support\Carbon;

/**
 * Allotment clubs usually read all meters once a year around a fixed date.
 * Consumption for a year is therefore: the last reading in that year minus the
 * last reading before that year (or the value at installation).
 */
class ConsumptionCalculator
{
    /**
     * @return int|null Consumption in milli-units, or null if the year's reading is missing.
     */
    public function forYear(Meter $meter, int $year): ?int
    {
        $yearStart = Carbon::create($year, 1, 1)->startOfDay();
        $yearEnd = Carbon::create($year, 12, 31)->endOfDay();

        if ($meter->installed_on->gt($yearEnd)) {
            return null;
        }

        $readings = $meter->relationLoaded('effectiveReadings')
            ? $meter->effectiveReadings
            : $meter->effectiveReadings()->get();

        /** @var Reading|null $end */
        $end = $readings->filter(fn (Reading $r) => $r->read_on->between($yearStart, $yearEnd))->last();

        if ($end === null) {
            return null;
        }

        /** @var Reading|null $start */
        $start = $readings->filter(fn (Reading $r) => $r->read_on->lt($yearStart))->last();

        return $end->value_milli - ($start?->value_milli ?? $meter->initial_value_milli);
    }
}
