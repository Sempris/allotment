<?php

namespace Tests\Feature;

use App\Enums\MeterType;
use App\Enums\ReadingSource;
use App\Exceptions\MeterExchangeRejected;
use App\Models\Meter;
use App\Models\Reading;
use App\Services\ConsumptionCalculator;
use App\Services\MeterExchange;
use App\Services\ReadingRecorder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class MeterExchangeTest extends TestCase
{
    use RefreshDatabase;

    private Meter $old;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-12');

        // Water meter, last read on 17.10.2025 at 120.000 m³.
        $this->old = Meter::factory()->create(['serial_number' => 'W-ALT', 'installed_on' => '2023-04-01']);
        app(ReadingRecorder::class)->record($this->old, Carbon::parse('2025-10-17'), 120_000, ReadingSource::Manual);
    }

    private function exchange(?int $finalValueMilli, string $date = '2026-06-01', MeterType $type = MeterType::Water): Meter
    {
        return app(MeterExchange::class)->exchange($this->old, Carbon::parse($date), $finalValueMilli, $type, 'W-NEU', 0);
    }

    private function assertRejected(string $field, callable $exchange): void
    {
        try {
            $exchange();
            $this->fail('The exchange should have been rejected.');
        } catch (MeterExchangeRejected $e) {
            $this->assertSame($field, $e->field);
        }

        // Nothing is stored half-way.
        $this->assertNull($this->old->fresh()->removed_on);
        $this->assertSame(1, Meter::count());
        $this->assertSame(1, Reading::count());
    }

    public function test_the_final_value_is_stored_as_a_reading_of_the_old_meter(): void
    {
        $new = $this->exchange(131_400);

        $final = $this->old->effectiveReadings()->get()->last();
        $this->assertSame('2026-06-01', $final->read_on->toDateString());
        $this->assertSame(131_400, $final->value_milli);
        $this->assertSame(ReadingSource::Exchange, $final->source);

        $this->assertSame('2026-06-01', $this->old->fresh()->removed_on->toDateString());
        $this->assertSame('2026-06-01', $new->installed_on->toDateString());
        $this->assertSame($this->old->plot_id, $new->plot_id);
    }

    public function test_consumption_up_to_the_exchange_is_billed_on_the_old_meter(): void
    {
        $new = $this->exchange(131_400);
        app(ReadingRecorder::class)->record($new, Carbon::parse('2026-10-17'), 14_250, ReadingSource::Manual);

        $calculator = app(ConsumptionCalculator::class);

        $this->assertSame(11_400, $calculator->forYear($this->old->fresh(), 2026)); // 131.400 − 120.000
        $this->assertSame(14_250, $calculator->forYear($new, 2026));
    }

    public function test_an_exchange_without_final_value_is_rejected(): void
    {
        $this->assertRejected('final_value', fn () => $this->exchange(null));
    }

    public function test_a_final_value_below_the_last_reading_is_rejected(): void
    {
        $this->assertRejected('final_value', fn () => $this->exchange(119_000));
    }

    public function test_a_reading_on_the_exchange_date_counts_as_final_value(): void
    {
        app(ReadingRecorder::class)->record($this->old, Carbon::parse('2026-06-01'), 131_400, ReadingSource::Manual);

        $this->exchange(null);

        $this->assertSame(2, $this->old->readings()->count()); // no second reading on that day
        $this->assertSame('2026-06-01', $this->old->fresh()->removed_on->toDateString());
    }

    public function test_a_final_value_contradicting_the_reading_of_that_day_is_rejected(): void
    {
        app(ReadingRecorder::class)->record($this->old, Carbon::parse('2026-06-01'), 131_400, ReadingSource::Manual);

        try {
            $this->exchange(131_500);
            $this->fail('The exchange should have been rejected.');
        } catch (MeterExchangeRejected $e) {
            $this->assertSame('final_value', $e->field);
        }

        $this->assertNull($this->old->fresh()->removed_on);
    }

    public function test_the_exchange_date_must_not_lie_before_the_last_reading(): void
    {
        $this->assertRejected('installed_on', fn () => $this->exchange(110_000, '2025-10-01'));
    }

    public function test_the_new_meter_must_have_the_same_type(): void
    {
        $this->assertRejected('type', fn () => $this->exchange(131_400, type: MeterType::Electricity));
    }

    public function test_a_removed_meter_cannot_be_exchanged_again(): void
    {
        $this->exchange(131_400);

        $this->expectException(MeterExchangeRejected::class);
        app(MeterExchange::class)->exchange($this->old, Carbon::parse('2026-07-01'), 140_000, MeterType::Water, 'W-NEU-2', 0);
    }
}
