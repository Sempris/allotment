<?php

namespace App\Services;

use Illuminate\Support\Carbon;

/**
 * A meter value on a date, together with where it comes from.
 */
final readonly class MeterValue
{
    public const READING = 'reading';

    public const INITIAL = 'initial';

    public const INTERPOLATED = 'interpolated';

    public function __construct(
        public int $milli,
        public Carbon $date,
        public string $origin,
    ) {}

    /** Calculated between two readings instead of read from the meter. */
    public function isInterpolated(): bool
    {
        return $this->origin === self::INTERPOLATED;
    }

    public function originLabel(): string
    {
        return match ($this->origin) {
            self::READING => 'Ablesung',
            self::INITIAL => 'Anfangsstand',
            default => 'auf Stichtag umgerechnet',
        };
    }
}
