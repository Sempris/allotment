<?php

namespace App\Services;

use App\Models\BillingPeriod;
use App\Models\Meter;
use App\Models\Reading;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Yearly consumption of a meter.
 *
 * Every billing year ends at a boundary value per meter, and the next year
 * starts there, so no consumption is billed twice or left out:
 *
 *  - Without a key date (the usual case: all meters are read around one fixed
 *    day), the boundary is the last reading in the calendar year.
 *  - With a key date, the boundary is the meter value on that date, linearly
 *    interpolated between the readings around it.
 *
 * Values are only interpolated, never extrapolated: a value projected beyond
 * the last reading would change once the next reading arrives, and with it a
 * bill that was already sent.
 */
class ConsumptionCalculator
{
    /**
     * @param  array<int, Carbon>|null  $keyDates  year => key date; loaded if not given
     * @return int|null Consumption in milli-units, or null if the year's reading is missing.
     */
    public function forYear(Meter $meter, int $year, ?array $keyDates = null): ?int
    {
        return $this->periodForYear($meter, $year, $keyDates)?->consumptionMilli();
    }

    /**
     * @param  array<int, Carbon>|null  $keyDates  year => key date; loaded if not given
     */
    public function periodForYear(Meter $meter, int $year, ?array $keyDates = null): ?MeterPeriod
    {
        $keyDates ??= BillingPeriod::keyDates();
        $readings = $this->readings($meter);

        $end = $this->boundary($meter, $readings, $year, $keyDates);

        if ($end === null) {
            return null;
        }

        // Start where the last year with a known boundary ended.
        for ($previous = $year - 1; $previous >= $meter->installed_on->year; $previous--) {
            $start = $this->boundary($meter, $readings, $previous, $keyDates);

            if ($start !== null) {
                return new MeterPeriod($start, $end);
            }
        }

        return new MeterPeriod($this->initialValue($meter), $end);
    }

    /**
     * The date a billing year ends on: its key date, or the end of the calendar year.
     *
     * @param  array<int, Carbon>  $keyDates
     */
    public function periodEnd(int $year, array $keyDates): Carbon
    {
        return ($keyDates[$year] ?? Carbon::create($year, 12, 31))->copy()->startOfDay();
    }

    /**
     * @param  Collection<int, Reading>  $readings
     * @param  array<int, Carbon>  $keyDates
     */
    private function boundary(Meter $meter, Collection $readings, int $year, array $keyDates): ?MeterValue
    {
        if (isset($keyDates[$year])) {
            return $this->valueOn($meter, $readings, $keyDates[$year]->copy()->startOfDay());
        }

        $yearStart = Carbon::create($year, 1, 1)->startOfDay();
        $yearEnd = Carbon::create($year, 12, 31)->endOfDay();

        /** @var Reading|null $last */
        $last = $readings->filter(fn (Reading $r) => $r->read_on->between($yearStart, $yearEnd))->last();

        return $last === null ? null : new MeterValue($last->value_milli, $last->read_on, MeterValue::READING);
    }

    /**
     * The meter value on a date, or null if it cannot be determined without guessing.
     *
     * @param  Collection<int, Reading>  $readings  effective readings, oldest first
     */
    private function valueOn(Meter $meter, Collection $readings, Carbon $date): ?MeterValue
    {
        if ($date->lt($meter->installed_on)) {
            return null;
        }

        /** @var Reading|null $exact */
        $exact = $readings->first(fn (Reading $r) => $r->read_on->isSameDay($date));

        if ($exact !== null) {
            return new MeterValue($exact->value_milli, $exact->read_on, MeterValue::READING);
        }

        /** @var Reading|null $before */
        $before = $readings->filter(fn (Reading $r) => $r->read_on->lt($date))->last();
        /** @var Reading|null $after */
        $after = $readings->first(fn (Reading $r) => $r->read_on->gt($date));

        $from = $before === null
            ? $this->initialValue($meter)
            : new MeterValue($before->value_milli, $before->read_on, MeterValue::READING);

        if ($after === null) {
            // A removed meter stays at its final value. Otherwise the value on
            // the date is unknown until the meter is read again.
            if ($meter->removed_on !== null && $date->gte($meter->removed_on) && $before !== null) {
                return $from;
            }

            return $date->isSameDay($from->date) ? $from : null;
        }

        if ($date->isSameDay($from->date)) {
            return $from;
        }

        $daysTotal = (int) $from->date->diffInDays($after->read_on);
        $daysToDate = (int) $from->date->diffInDays($date);

        return new MeterValue(
            $from->milli + self::divideRounded(($after->value_milli - $from->milli) * $daysToDate, $daysTotal),
            $date,
            MeterValue::INTERPOLATED,
        );
    }

    private function initialValue(Meter $meter): MeterValue
    {
        return new MeterValue($meter->initial_value_milli, $meter->installed_on, MeterValue::INITIAL);
    }

    /** @return Collection<int, Reading> */
    private function readings(Meter $meter): Collection
    {
        return $meter->relationLoaded('effectiveReadings')
            ? $meter->effectiveReadings
            : $meter->effectiveReadings()->get();
    }

    /** Integer division, rounded half up (both arguments non-negative). */
    private static function divideRounded(int $dividend, int $divisor): int
    {
        return intdiv(2 * $dividend + $divisor, 2 * $divisor);
    }
}
