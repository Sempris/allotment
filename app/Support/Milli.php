<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * Meter values and prices are stored as integers to avoid floating-point
 * rounding: meter values in thousandths of a unit (1 m³ = 1000), money in cents.
 */
final class Milli
{
    /**
     * Parse user input like "123,456" or "123.456" into milli-units.
     */
    public static function parse(string $input): int
    {
        $normalized = str_replace([' ', "\u{00A0}"], '', trim($input));

        // German input: "1.234,5" -> thousands separator dot, decimal comma.
        if (str_contains($normalized, ',')) {
            $normalized = str_replace('.', '', $normalized);
            $normalized = str_replace(',', '.', $normalized);
        }

        if (! preg_match('/^\d+(\.\d{1,3})?$/', $normalized)) {
            throw new InvalidArgumentException("Ungültiger Zählerstand: {$input}");
        }

        [$whole, $fraction] = array_pad(explode('.', $normalized, 2), 2, '');

        return (int) $whole * 1000 + (int) str_pad($fraction, 3, '0');
    }

    public static function format(int $milli, int $decimals = 3): string
    {
        return number_format($milli / 1000, $decimals, ',', '.');
    }

    public static function formatCents(int $cents): string
    {
        return number_format($cents / 100, 2, ',', '.').' €';
    }
}
