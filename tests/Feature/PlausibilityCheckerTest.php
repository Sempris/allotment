<?php

namespace Tests\Feature;

use App\Enums\ReadingSource;
use App\Models\Meter;
use App\Services\PlausibilityChecker;
use App\Services\ReadingRecorder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class PlausibilityCheckerTest extends TestCase
{
    use RefreshDatabase;

    private PlausibilityChecker $checker;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-12');
        $this->checker = app(PlausibilityChecker::class);
    }

    private function meterWithHistory(): Meter
    {
        // 40 m³ per year since April 2023.
        $meter = Meter::factory()->create(['installed_on' => '2023-04-01', 'initial_value_milli' => 0]);
        $recorder = app(ReadingRecorder::class);
        $recorder->record($meter, Carbon::parse('2024-04-01'), 40_000, ReadingSource::Manual);
        $recorder->record($meter, Carbon::parse('2025-04-01'), 80_000, ReadingSource::Manual);

        return $meter;
    }

    public function test_a_normal_reading_passes_without_warning(): void
    {
        $result = $this->checker->check($this->meterWithHistory(), Carbon::parse('2026-04-01'), 121_000);

        $this->assertFalse($result->isRejected());
        $this->assertNull($result->warning);
    }

    public function test_a_value_lower_than_the_previous_reading_is_rejected(): void
    {
        $result = $this->checker->check($this->meterWithHistory(), Carbon::parse('2026-04-01'), 79_999);

        $this->assertTrue($result->isRejected());
        $this->assertStringContainsString('kleiner als der vorherige', $result->errors[0]);
    }

    public function test_a_value_higher_than_a_later_reading_is_rejected(): void
    {
        // Back-dated entry between the two existing readings.
        $result = $this->checker->check($this->meterWithHistory(), Carbon::parse('2024-10-01'), 85_000);

        $this->assertTrue($result->isRejected());
        $this->assertStringContainsString('größer als die spätere Ablesung', $result->errors[0]);
    }

    public function test_future_dates_and_dates_before_installation_are_rejected(): void
    {
        $meter = $this->meterWithHistory();

        $this->assertTrue($this->checker->check($meter, Carbon::parse('2026-12-01'), 120_000)->isRejected());
        $this->assertTrue($this->checker->check($meter, Carbon::parse('2023-01-01'), 0)->isRejected());
    }

    public function test_a_second_reading_on_the_same_day_is_rejected(): void
    {
        $result = $this->checker->check($this->meterWithHistory(), Carbon::parse('2025-04-01'), 80_000);

        $this->assertTrue($result->isRejected());
        $this->assertStringContainsString('bereits eine Ablesung', $result->errors[0]);
    }

    public function test_consumption_far_above_the_meters_own_average_is_flagged(): void
    {
        // 40 m³ per year so far; 130 m³ in one year is more than three times that.
        $result = $this->checker->check($this->meterWithHistory(), Carbon::parse('2026-04-01'), 210_000);

        $this->assertFalse($result->isRejected());
        $this->assertStringContainsString('mehr als 3-mal so hoch', (string) $result->warning);
    }

    public function test_without_history_a_generous_absolute_limit_applies(): void
    {
        $meter = Meter::factory()->create(['installed_on' => '2026-04-01']);

        $this->assertNull($this->checker->check($meter, Carbon::parse('2026-10-01'), 60_000)->warning);
        $this->assertStringContainsString('Höchstwert', (string) $this->checker->check($meter, Carbon::parse('2026-10-01'), 400_000)->warning);
    }
}
