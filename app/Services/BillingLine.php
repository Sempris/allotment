<?php

namespace App\Services;

use App\Models\Meter;

final readonly class BillingLine
{
    /**
     * @param  MeterPeriod|null  $period  Start and end value the consumption is based on; null if the year's value is missing.
     */
    public function __construct(
        public Meter $meter,
        public ?int $consumptionMilli,
        public ?int $costCents,
        public ?MeterPeriod $period = null,
    ) {}

    public function isComplete(): bool
    {
        return $this->costCents !== null;
    }

    /** The consumption rests on a value calculated for a key date instead of a reading. */
    public function isInterpolated(): bool
    {
        return $this->period?->isInterpolated() ?? false;
    }
}
