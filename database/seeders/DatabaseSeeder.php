<?php

namespace Database\Seeders;

use App\Enums\MeterType;
use App\Enums\ReadingSource;
use App\Models\Plot;
use App\Models\Tariff;
use App\Models\User;
use App\Services\ReadingRecorder;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * A small allotment club with eight plots and three years of yearly readings.
     * Readings go through the ReadingRecorder, so the seed data passes the same checks as real input.
     */
    public function run(ReadingRecorder $recorder): void
    {
        $board = User::create([
            'name' => 'Erika Gärtner',
            'email' => 'vorstand@example.org',
            'password' => Hash::make('gartenzwerg'),
        ]);

        $tenants = ['Familie Yilmaz', 'Hans Becker', 'Olga Petrenko', 'Familie Schulte', 'Mia Krüger', 'Jonas Weber', 'Familie Nowak', 'Sabine Lange'];
        // Yearly consumption per plot: water in m³, electricity in kWh.
        $profiles = [[38, 210], [22, 95], [51, 340], [30, 160], [18, 60], [44, 280], [27, 120], [35, 190]];

        foreach ($tenants as $i => $tenant) {
            $plot = Plot::create(['number' => (string) ($i + 1), 'tenant_name' => $tenant]);
            [$water, $power] = $profiles[$i];

            $meters = [
                [$plot->meters()->create(['type' => MeterType::Water, 'serial_number' => sprintf('W-%04d', 1001 + $i), 'installed_on' => '2023-04-01', 'initial_value_milli' => 0]), $water],
                [$plot->meters()->create(['type' => MeterType::Electricity, 'serial_number' => sprintf('S-%04d', 2001 + $i), 'installed_on' => '2023-04-01', 'initial_value_milli' => 0]), $power],
            ];

            foreach ($meters as [$meter, $perYear]) {
                $value = 0;

                foreach ([2023, 2024, 2025, 2026] as $year) {
                    // Plots 5 and 8 have not been read in 2026 yet.
                    if ($year === 2026 && in_array($plot->number, ['5', '8'], true)) {
                        continue;
                    }

                    $factor = $year === 2023 ? 0.6 : 1.0; // first season started in April
                    $increase = (int) round($perYear * $factor * (0.9 + 0.05 * (($i + $year) % 4)) * 1000);

                    // Plot 7: a leaking water pipe in 2026, so the reading gets flagged.
                    if ($year === 2026 && $plot->number === '7' && $meter->type === MeterType::Water) {
                        $increase *= 5;
                    }

                    $value += $increase;
                    $recorder->record($meter, Carbon::create($year, 10, 17), $value, ReadingSource::Import, $board);
                }
            }
        }

        foreach ([2024 => [250, 34], 2025 => [265, 36], 2026 => [280, 37]] as $year => [$waterCents, $powerCents]) {
            Tariff::create(['year' => $year, 'meter_type' => MeterType::Water, 'price_per_unit_cents' => $waterCents, 'base_fee_cents' => 1200]);
            Tariff::create(['year' => $year, 'meter_type' => MeterType::Electricity, 'price_per_unit_cents' => $powerCents, 'base_fee_cents' => 1800]);
        }
    }
}
