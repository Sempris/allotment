<?php

namespace Tests\Feature;

use App\Enums\MeterType;
use App\Enums\ReadingSource;
use App\Models\BillingPeriod;
use App\Models\Meter;
use App\Models\Plot;
use App\Models\Tariff;
use App\Services\BillingService;
use App\Services\MeterMaintenance;
use App\Services\ReadingRecorder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class KeyDateBillingTest extends TestCase
{
    use RefreshDatabase;

    private Plot $plot;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-12');

        $this->plot = Plot::factory()->create(['number' => '4']);
        Tariff::create(['year' => 2026, 'meter_type' => MeterType::Water, 'price_per_unit_cents' => 280, 'base_fee_cents' => 1200]);
    }

    /** @param array<string, int> $readings date => milli; the meter runs at 0.100 m³ per day */
    private function meter(array $readings, string $installedOn = '2024-04-01'): Meter
    {
        $meter = Meter::factory()->for($this->plot)->create(['installed_on' => $installedOn]);

        foreach ($readings as $date => $milli) {
            app(ReadingRecorder::class)->record($meter, Carbon::parse($date), $milli, ReadingSource::Manual);
        }

        return $meter;
    }

    public function test_costs_are_based_on_the_interpolated_consumption(): void
    {
        BillingPeriod::create(['year' => 2025, 'key_date' => '2025-09-30']);
        BillingPeriod::create(['year' => 2026, 'key_date' => '2026-09-30']);
        $this->meter(['2024-10-17' => 20_000, '2025-12-20' => 62_900, '2026-10-17' => 93_000]);

        $line = app(BillingService::class)->forYear(2026)->sole()['lines'][0];

        // 36.500 m³ × 2.80 € = 102.20 €, plus 12.00 € base fee.
        $this->assertSame(36_500, $line->consumptionMilli);
        $this->assertSame(11_420, $line->costCents);
        $this->assertTrue($line->isInterpolated());
        $this->assertSame(54_800, $line->period->start->milli);
        $this->assertSame(91_300, $line->period->end->milli);
    }

    public function test_without_a_key_date_lines_are_not_marked_as_interpolated(): void
    {
        $this->meter(['2025-10-17' => 56_500, '2026-10-17' => 93_000]);

        $line = app(BillingService::class)->forYear(2026)->sole()['lines'][0];

        $this->assertSame(36_500, $line->consumptionMilli);
        $this->assertFalse($line->isInterpolated());
    }

    public function test_a_meter_installed_after_the_key_date_is_billed_in_the_next_year(): void
    {
        BillingPeriod::create(['year' => 2026, 'key_date' => '2026-09-30']);
        $this->meter(['2026-11-01' => 1_700], installedOn: '2026-10-15');

        $this->assertSame([], app(BillingService::class)->forYear(2026)->sole()['lines']);
        $this->assertCount(1, app(BillingService::class)->forYear(2027)->sole()['lines']);
    }

    public function test_a_meter_removed_after_the_key_date_is_billed_up_to_its_final_value_in_the_next_year(): void
    {
        BillingPeriod::create(['year' => 2025, 'key_date' => '2025-09-30']);
        $meter = $this->meter(['2024-10-17' => 20_000]);
        // 394 days after the first reading: 20.000 + 39.400.
        Carbon::setTestNow('2025-11-20');
        app(MeterMaintenance::class)->retire($meter, Carbon::parse('2025-11-15'), 59_400, ReadingSource::Removal);
        Carbon::setTestNow('2026-11-12');

        $billing = app(BillingService::class);

        $this->assertSame(34_800, $billing->forYear(2025)->sole()['lines'][0]->consumptionMilli); // up to 30.09.2025: 54.800
        $this->assertSame(4_600, $billing->forYear(2026)->sole()['lines'][0]->consumptionMilli);  // 46 days up to the removal
        $this->assertSame([], $billing->forYear(2027)->sole()['lines']);
    }

    public function test_plots_are_listed_in_natural_order(): void
    {
        Plot::factory()->create(['number' => '10']);
        Plot::factory()->create(['number' => '2']);

        $this->assertSame(['2', '4', '10'], app(BillingService::class)->forYear(2026)->map(fn ($row) => $row['plot']->number)->all());
    }
}
