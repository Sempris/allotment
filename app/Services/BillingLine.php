<?php

namespace App\Services;

use App\Models\Meter;

final readonly class BillingLine
{
    public function __construct(
        public Meter $meter,
        public ?int $consumptionMilli,
        public ?int $costCents,
    ) {}

    public function isComplete(): bool
    {
        return $this->costCents !== null;
    }
}
