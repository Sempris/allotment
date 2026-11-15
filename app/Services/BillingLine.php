<?php

namespace App\Services;

use App\Models\Meter;
use App\Models\Tariff;

final readonly class BillingLine
{
    /**
     * @param  MeterPeriod|null  $period  Start and end value the consumption is based on; null if the year's value is missing.
     * @param  Tariff|null  $tariff  The tariff the cost was calculated with; null if the year has none for this meter type.
     */
    public function __construct(
        public Meter $meter,
        public ?int $consumptionMilli,
        public ?int $costCents,
        public ?MeterPeriod $period = null,
        public ?Tariff $tariff = null,
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

    /** Consumption times price, without the base fee. */
    public function consumptionCostCents(): ?int
    {
        return $this->costCents === null ? null : $this->costCents - $this->tariff->base_fee_cents;
    }
}
