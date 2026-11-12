<?php

namespace Database\Factories;

use App\Models\Plot;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Plot>
 */
class PlotFactory extends Factory
{
    private static int $number = 0;

    public function definition(): array
    {
        $number = ++self::$number;

        return [
            'number' => (string) $number,
            'tenant_name' => "Pächter {$number}",
            'tenant_email' => null,
        ];
    }
}
