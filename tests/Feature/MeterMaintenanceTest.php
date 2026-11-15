<?php

namespace Tests\Feature;

use App\Enums\ReadingSource;
use App\Exceptions\MeterChangeRejected;
use App\Models\Meter;
use App\Services\ConsumptionCalculator;
use App\Services\MeterMaintenance;
use App\Services\ReadingRecorder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class MeterMaintenanceTest extends TestCase
{
    use RefreshDatabase;

    private Meter $meter;

    private MeterMaintenance $maintenance;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-12');

        // Installed on 01.04.2023 at 0, first read on 17.10.2023 at 22.800 m³.
        $this->meter = Meter::factory()->create(['serial_number' => 'W-1001', 'installed_on' => '2023-04-01']);
        app(ReadingRecorder::class)->record($this->meter, Carbon::parse('2023-10-17'), 22_800, ReadingSource::Manual);

        $this->maintenance = app(MeterMaintenance::class);
    }

    private function assertRejected(string $field, callable $change): void
    {
        try {
            $change();
            $this->fail('The change should have been rejected.');
        } catch (MeterChangeRejected $e) {
            $this->assertSame($field, $e->field);
        }
    }

    public function test_a_corrected_initial_value_changes_the_first_years_consumption(): void
    {
        $this->maintenance->update($this->meter, 'W-1001', Carbon::parse('2023-04-01'), 2_500);

        // 22.800 − 2.500 instead of 22.800 − 0.
        $this->assertSame(20_300, app(ConsumptionCalculator::class)->forYear($this->meter->fresh(), 2023));
    }

    public function test_the_initial_value_must_not_exceed_the_first_reading(): void
    {
        $this->assertRejected('initial_value', fn () => $this->maintenance->update($this->meter, 'W-1001', Carbon::parse('2023-04-01'), 23_000));

        $this->assertSame(0, $this->meter->fresh()->initial_value_milli);
    }

    public function test_the_installation_date_must_not_lie_after_the_first_reading(): void
    {
        $this->assertRejected('installed_on', fn () => $this->maintenance->update($this->meter, 'W-1001', Carbon::parse('2023-11-01'), 0));

        $this->assertSame('2023-04-01', $this->meter->fresh()->installed_on->toDateString());
    }

    public function test_a_meter_with_readings_cannot_be_deleted(): void
    {
        $this->assertRejected('meter', fn () => $this->maintenance->delete($this->meter));

        $this->assertTrue($this->meter->exists());
    }

    public function test_a_corrected_reading_still_counts_as_history(): void
    {
        // Even if no reading were effective, the superseded original must survive.
        $original = $this->meter->readings()->sole();
        app(ReadingRecorder::class)->correct($original, Carbon::parse('2023-10-17'), 22_900, 'Ablesefehler');

        $this->assertRejected('meter', fn () => $this->maintenance->delete($this->meter));
        $this->assertSame(2, $this->meter->readings()->count());
    }

    public function test_a_meter_without_readings_can_be_deleted(): void
    {
        $unused = Meter::factory()->for($this->meter->plot)->create();

        $this->maintenance->delete($unused);

        $this->assertNull(Meter::find($unused->id));
    }

    public function test_removing_a_meter_needs_its_final_value(): void
    {
        $this->assertRejected('final_value', fn () => $this->maintenance->retire($this->meter, Carbon::parse('2026-09-30'), null, ReadingSource::Removal));

        $this->assertNull($this->meter->fresh()->removed_on);
    }

    public function test_removing_a_meter_stores_the_final_value_as_reading(): void
    {
        $this->maintenance->retire($this->meter, Carbon::parse('2026-09-30'), 130_000, ReadingSource::Removal);

        $final = $this->meter->effectiveReadings()->get()->last();
        $this->assertSame(130_000, $final->value_milli);
        $this->assertSame(ReadingSource::Removal, $final->source);
        $this->assertSame('2026-09-30', $this->meter->fresh()->removed_on->toDateString());
        $this->assertSame(107_200, app(ConsumptionCalculator::class)->forYear($this->meter->fresh(), 2026)); // 130.000 − 22.800
    }

    public function test_a_removal_can_be_undone_and_keeps_the_final_reading(): void
    {
        $this->maintenance->retire($this->meter, Carbon::parse('2026-09-30'), 130_000, ReadingSource::Removal);

        $this->maintenance->reactivate($this->meter);

        $this->assertNull($this->meter->fresh()->removed_on);
        $this->assertSame(2, $this->meter->effectiveReadings()->count());
    }

    public function test_only_a_removed_meter_can_be_reactivated(): void
    {
        $this->assertRejected('meter', fn () => $this->maintenance->reactivate($this->meter));
    }
}
