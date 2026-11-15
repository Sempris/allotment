<?php

namespace App\Services;

/**
 * What one meter is billed for in one year: from the start value to the end value.
 */
final readonly class MeterPeriod
{
    public function __construct(
        public MeterValue $start,
        public MeterValue $end,
    ) {}

    public function consumptionMilli(): int
    {
        return $this->end->milli - $this->start->milli;
    }

    /** At least one of the two values was calculated for a key date. */
    public function isInterpolated(): bool
    {
        return $this->start->isInterpolated() || $this->end->isInterpolated();
    }
}
