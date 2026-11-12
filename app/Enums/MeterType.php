<?php

namespace App\Enums;

enum MeterType: string
{
    case Water = 'water';
    case Electricity = 'electricity';

    public function label(): string
    {
        return match ($this) {
            self::Water => 'Wasser',
            self::Electricity => 'Strom',
        };
    }

    /** Water is listed before electricity everywhere. */
    public function sortOrder(): int
    {
        return match ($this) {
            self::Water => 1,
            self::Electricity => 2,
        };
    }

    public function unit(): string
    {
        return match ($this) {
            self::Water => 'm³',
            self::Electricity => 'kWh',
        };
    }

    /**
     * Upper bound for a plausible yearly consumption of one allotment plot,
     * in milli-units. Used when a meter has too little history for a
     * meter-specific comparison.
     */
    public function plausibleYearlyMaximumMilli(): int
    {
        return match ($this) {
            self::Water => 150_000,        // 150 m³
            self::Electricity => 2_000_000, // 2,000 kWh
        };
    }
}
