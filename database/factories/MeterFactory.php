<?php

namespace Database\Factories;

use App\Enums\MeterType;
use App\Models\Meter;
use App\Models\Plot;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Meter>
 */
class MeterFactory extends Factory
{
    public function definition(): array
    {
        return [
            'plot_id' => Plot::factory(),
            'type' => MeterType::Water,
            'serial_number' => 'W-'.Str::upper(Str::random(8)),
            'installed_on' => '2023-04-01',
            'initial_value_milli' => 0,
        ];
    }

    public function electricity(): static
    {
        return $this->state(fn () => ['type' => MeterType::Electricity, 'serial_number' => 'S-'.Str::upper(Str::random(8))]);
    }
}
