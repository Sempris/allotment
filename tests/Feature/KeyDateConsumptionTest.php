<?php

namespace Tests\Feature;

use App\Enums\ReadingSource;
use App\Models\BillingPeriod;
use App\Models\Meter;
use App\Services\ConsumptionCalculator;
use App\Services\MeterMaintenance;
use App\Services\MeterValue;
use App\Services\ReadingRecorder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * All examples use a meter that runs at exactly 0.100 m³ per day,
 * so every interpolated value can be checked by hand.
 */
class KeyDateConsumptionTest extends TestCase
{
    use RefreshDatabase;

    private ConsumptionCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-12');
        $this->calculator = app(ConsumptionCalculator::class);
    }

    /** @param array<string, int> $readings date => milli */
    private function meter(array $readings, string $installedOn = '2024-04-01'): Meter
    {
        $meter = Meter::factory()->create(['installed_on' => $installedOn, 'initial_value_milli' => 0]);

        foreach ($readings as $date => $milli) {
            app(ReadingRecorder::class)->record($meter, Carbon::parse($date), $milli, ReadingSource::Manual);
        }

        return $meter;
    }

    private function keyDate(int $year, string $date): void
    {
        BillingPeriod::create(['year' => $year, 'key_date' => $date]);
    }

    private function regularMeter(): Meter
    {
        return $this->meter(['2024-10-17' => 20_000, '2025-10-17' => 56_500, '2026-10-17' => 93_000]);
    }

    private function meterReadLateIn2025(): Meter
    {
        return $this->meter(['2024-10-17' => 20_000, '2025-12-20' => 62_900, '2026-10-17' => 93_000]);
    }

    public function test_values_are_interpolated_to_the_key_date(): void
    {
        $this->keyDate(2025, '2025-09-30');
        $this->keyDate(2026, '2026-09-30');

        $period = $this->calculator->periodForYear($this->regularMeter(), 2026);

        // 17.10.2024 + 348 days at 0.1 m³ = 20.000 + 34.800
        $this->assertSame(54_800, $period->start->milli);
        $this->assertSame('2025-09-30', $period->start->date->toDateString());
        $this->assertSame(MeterValue::INTERPOLATED, $period->start->origin);

        // 17.10.2025 + 348 days at 0.1 m³ = 56.500 + 34.800
        $this->assertSame(91_300, $period->end->milli);
        $this->assertSame(36_500, $period->consumptionMilli());
        $this->assertTrue($period->isInterpolated());
    }

    public function test_a_key_date_year_continues_where_the_previous_year_ended(): void
    {
        // 2025 was billed up to its reading, only 2026 gets a key date.
        $this->keyDate(2026, '2026-09-30');
        $meter = $this->regularMeter();

        $this->assertSame(36_500, $this->calculator->forYear($meter, 2025)); // 56.500 − 20.000, as before
        $period = $this->calculator->periodForYear($meter, 2026);

        $this->assertSame(56_500, $period->start->milli);
        $this->assertSame(MeterValue::READING, $period->start->origin);
        $this->assertSame(34_800, $period->consumptionMilli()); // 91.300 − 56.500, nothing billed twice
    }

    public function test_without_key_dates_a_late_reading_shifts_consumption_between_years(): void
    {
        $meter = $this->meterReadLateIn2025();

        $this->assertSame(42_900, $this->calculator->forYear($meter, 2025)); // 14 months
        $this->assertSame(30_100, $this->calculator->forYear($meter, 2026)); // 10 months
        $this->assertFalse($this->calculator->periodForYear($meter, 2026)->isInterpolated());
    }

    public function test_with_key_dates_every_year_covers_the_same_time_span(): void
    {
        $this->keyDate(2025, '2025-09-30');
        $this->keyDate(2026, '2026-09-30');
        $meter = $this->meterReadLateIn2025();

        // 30.09.2025: 20.000 + 348 days × 0.1 = 54.800; 30.09.2026: 62.900 + 284 days × 0.1 = 91.300
        $this->assertSame(34_800, $this->calculator->forYear($meter, 2025)); // 54.800 − 20.000
        $this->assertSame(36_500, $this->calculator->forYear($meter, 2026)); // 365 days
    }

    public function test_a_reading_on_the_key_date_is_used_as_it_is(): void
    {
        $this->keyDate(2026, '2026-10-17');

        $period = $this->calculator->periodForYear($this->regularMeter(), 2026);

        $this->assertSame(93_000, $period->end->milli);
        $this->assertSame(MeterValue::READING, $period->end->origin);
        $this->assertFalse($period->isInterpolated());
    }

    public function test_nothing_is_projected_beyond_the_last_reading(): void
    {
        $this->keyDate(2026, '2026-10-31'); // two weeks after the last reading

        $this->assertNull($this->calculator->periodForYear($this->regularMeter(), 2026));
    }

    public function test_a_removed_meter_stays_at_its_final_value(): void
    {
        $this->keyDate(2026, '2026-09-30');
        $meter = $this->meter(['2025-10-17' => 56_500]);
        app(MeterMaintenance::class)->retire($meter, Carbon::parse('2026-06-01'), 80_000, ReadingSource::Removal);

        $period = $this->calculator->periodForYear($meter->fresh(), 2026);

        $this->assertSame(80_000, $period->end->milli);
        $this->assertSame('2026-06-01', $period->end->date->toDateString());
        $this->assertSame(MeterValue::READING, $period->end->origin);
        $this->assertSame(23_500, $period->consumptionMilli());
    }

    public function test_the_first_year_is_interpolated_from_the_installation(): void
    {
        $this->keyDate(2024, '2024-09-30');
        $meter = $this->meter(['2024-10-18' => 20_000]); // 200 days after 01.04.2024

        $period = $this->calculator->periodForYear($meter, 2024);

        $this->assertSame(MeterValue::INITIAL, $period->start->origin);
        $this->assertSame(18_200, $period->end->milli); // 182 days × 0.1
    }

    public function test_a_meter_installed_after_the_key_date_has_no_period_in_that_year(): void
    {
        $this->keyDate(2026, '2026-09-30');
        $meter = $this->meter(['2026-11-01' => 1_700], installedOn: '2026-10-15');

        $this->assertNull($this->calculator->periodForYear($meter, 2026));
    }

    public function test_interpolated_values_are_rounded_half_up_in_whole_thousandths(): void
    {
        $this->keyDate(2026, '2026-01-02');

        // 0.001 m³ in two days: the value after one day is 0.0005 -> 0.001.
        $half = $this->meter(['2026-01-03' => 1], installedOn: '2026-01-01');
        $this->assertSame(1, $this->calculator->periodForYear($half, 2026)->end->milli);

        // 0.001 m³ in three days: the value after one day is 0.00033 -> 0.000.
        $third = $this->meter(['2026-01-04' => 1], installedOn: '2026-01-01');
        $this->assertSame(0, $this->calculator->periodForYear($third, 2026)->end->milli);
    }
}
