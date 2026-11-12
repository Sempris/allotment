<?php

namespace Tests\Feature;

use App\Enums\MeterType;
use App\Enums\ReadingSource;
use App\Models\Meter;
use App\Models\Plot;
use App\Models\Tariff;
use App\Models\User;
use App\Services\BillingService;
use App\Services\ConsumptionCalculator;
use App\Services\ReadingRecorder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class BillingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-12');
    }

    public function test_yearly_consumption_is_the_difference_to_the_previous_years_reading(): void
    {
        $meter = Meter::factory()->create(['installed_on' => '2024-04-01', 'initial_value_milli' => 5_000]);
        $recorder = app(ReadingRecorder::class);
        $recorder->record($meter, Carbon::parse('2024-10-15'), 25_000, ReadingSource::Manual);
        $recorder->record($meter, Carbon::parse('2025-10-15'), 62_500, ReadingSource::Manual);

        $calculator = app(ConsumptionCalculator::class);

        $this->assertSame(20_000, $calculator->forYear($meter, 2024)); // from the initial value
        $this->assertSame(37_500, $calculator->forYear($meter, 2025));
        $this->assertNull($calculator->forYear($meter, 2026));         // not read yet
    }

    public function test_corrected_readings_do_not_count(): void
    {
        $meter = Meter::factory()->create(['installed_on' => '2025-04-01']);
        $recorder = app(ReadingRecorder::class);
        $original = $recorder->record($meter, Carbon::parse('2025-10-15'), 83_000, ReadingSource::Manual);
        $recorder->correct($original, Carbon::parse('2025-10-15'), 38_000, 'Zahlendreher');

        $this->assertSame(38_000, app(ConsumptionCalculator::class)->forYear($meter, 2025));
    }

    public function test_costs_are_calculated_in_whole_cents(): void
    {
        $plot = Plot::factory()->create(['number' => '4']);
        $water = Meter::factory()->for($plot)->create(['installed_on' => '2026-04-01']);
        $power = Meter::factory()->for($plot)->electricity()->create(['installed_on' => '2026-04-01']);
        Tariff::create(['year' => 2026, 'meter_type' => MeterType::Water, 'price_per_unit_cents' => 280, 'base_fee_cents' => 1200]);
        Tariff::create(['year' => 2026, 'meter_type' => MeterType::Electricity, 'price_per_unit_cents' => 37, 'base_fee_cents' => 1800]);

        $recorder = app(ReadingRecorder::class);
        $recorder->record($water, Carbon::parse('2026-10-15'), 38_255, ReadingSource::Manual); // 38.255 m³
        $recorder->record($power, Carbon::parse('2026-10-15'), 152_700, ReadingSource::Manual); // 152.7 kWh

        $row = app(BillingService::class)->forYear(2026)->sole();

        // Water: 38.255 × 2.80 € = 107.114 € -> 107.11 €, plus 12.00 € base fee.
        $this->assertSame(10_711 + 1_200, $row['lines'][0]->costCents);
        // Electricity: 152.7 × 0.37 € = 56.499 € -> 56.50 €, plus 18.00 € base fee.
        $this->assertSame(5_650 + 1_800, $row['lines'][1]->costCents);
        $this->assertSame(11_911 + 7_450, $row['total_cents']);
        $this->assertTrue($row['complete']);
    }

    public function test_missing_readings_and_tariffs_are_shown_instead_of_guessed(): void
    {
        $meter = Meter::factory()->create(['installed_on' => '2026-04-01']);

        $this->actingAs(User::factory()->create())
            ->get(route('billing.index', ['year' => 2026]))
            ->assertOk()
            ->assertSee('Ablesung fehlt')
            ->assertSee('fehlt der Tarif');

        $row = app(BillingService::class)->forYear(2026)->sole();
        $this->assertFalse($row['complete']);
        $this->assertNull($row['lines'][0]->costCents);
    }

    public function test_the_billing_can_be_downloaded_as_csv(): void
    {
        $plot = Plot::factory()->create(['number' => '9', 'tenant_name' => 'Olga Petrenko']);
        $meter = Meter::factory()->for($plot)->create(['serial_number' => 'W-1009', 'installed_on' => '2026-04-01']);
        Tariff::create(['year' => 2026, 'meter_type' => MeterType::Water, 'price_per_unit_cents' => 280, 'base_fee_cents' => 0]);
        app(ReadingRecorder::class)->record($meter, Carbon::parse('2026-10-15'), 10_000, ReadingSource::Manual);

        $response = $this->actingAs(User::factory()->create())->get(route('billing.csv', ['year' => 2026]));

        $response->assertOk();
        $this->assertStringContainsString('9;"Olga Petrenko";Wasser;W-1009;10,000;m³;28,00', $response->streamedContent());
    }
}
