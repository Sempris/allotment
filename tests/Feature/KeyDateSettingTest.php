<?php

namespace Tests\Feature;

use App\Enums\MeterType;
use App\Enums\ReadingSource;
use App\Models\BillingPeriod;
use App\Models\Meter;
use App\Models\Plot;
use App\Models\Tariff;
use App\Models\User;
use App\Services\ReadingRecorder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class KeyDateSettingTest extends TestCase
{
    use RefreshDatabase;

    private Plot $plot;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-12');

        $this->plot = Plot::factory()->create(['number' => '4', 'tenant_name' => 'Familie Schulte']);
        $meter = Meter::factory()->for($this->plot)->create(['serial_number' => 'W-1004', 'installed_on' => '2024-04-01']);
        Tariff::create(['year' => 2026, 'meter_type' => MeterType::Water, 'price_per_unit_cents' => 280, 'base_fee_cents' => 1200]);

        foreach (['2024-10-17' => 20_000, '2025-10-17' => 56_500, '2026-10-17' => 93_000] as $date => $milli) {
            app(ReadingRecorder::class)->record($meter, Carbon::parse($date), $milli, ReadingSource::Manual);
        }
    }

    public function test_the_treasurer_switches_a_year_to_a_key_date(): void
    {
        $this->actingAs(User::factory()->treasurer()->create());

        $this->get(route('billing.index', ['year' => 2026]))
            ->assertOk()->assertSee('letzten Ablesung im Jahr')->assertSee('36,500 m³')->assertDontSee('≈');

        $this->post(route('billing.key-date.store'), ['year' => 2026, 'key_date' => '2026-09-30'])
            ->assertRedirect(route('billing.index', ['year' => 2026]))
            ->assertSessionHasNoErrors();

        // 91.300 (interpolated) − 56.500 (reading 2025) = 34.800 m³ -> 97.44 € + 12.00 €
        $this->get(route('billing.index', ['year' => 2026]))
            ->assertOk()
            ->assertSee('Stichtag 30.09.2026')
            ->assertSee('30.09.2026: ≈ 91,300')
            ->assertSee('≈ 34,800 m³')
            ->assertSee('109,44 €');
    }

    public function test_the_key_date_must_lie_within_the_billing_year(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('billing.key-date.store'), ['year' => 2026, 'key_date' => '2027-01-15'])
            ->assertSessionHasErrors('key_date');

        $this->assertSame(0, BillingPeriod::count());
    }

    public function test_a_key_date_can_be_changed_and_removed(): void
    {
        $this->actingAs(User::factory()->create());
        BillingPeriod::create(['year' => 2026, 'key_date' => '2026-09-30']);

        $this->post(route('billing.key-date.store'), ['year' => 2026, 'key_date' => '2026-10-01'])->assertSessionHasNoErrors();
        $this->assertSame('2026-10-01', BillingPeriod::sole()->key_date->toDateString());

        $this->delete(route('billing.key-date.destroy'), ['year' => 2026])->assertRedirect(route('billing.index', ['year' => 2026]));
        $this->assertSame(0, BillingPeriod::count());
    }

    public function test_a_key_date_after_the_last_reading_shows_the_meter_as_not_read(): void
    {
        BillingPeriod::create(['year' => 2026, 'key_date' => '2026-10-31']);

        $this->actingAs(User::factory()->create())
            ->get(route('billing.index', ['year' => 2026]))
            ->assertSee('Ablesung am oder nach dem Stichtag fehlt')
            ->assertSee('Summe (unvollständig)');
    }

    public function test_the_next_year_suggests_the_same_day(): void
    {
        BillingPeriod::create(['year' => 2026, 'key_date' => '2026-09-30']);

        $this->actingAs(User::factory()->create())
            ->get(route('billing.index', ['year' => 2027]))
            ->assertSee('value="2027-09-30"', false);
    }

    public function test_the_csv_names_start_value_end_value_and_the_conversion(): void
    {
        BillingPeriod::create(['year' => 2026, 'key_date' => '2026-09-30']);

        $csv = $this->actingAs(User::factory()->create())->get(route('billing.csv', ['year' => 2026]))->streamedContent();

        $this->assertStringContainsString('"Betrag (EUR)";Anfangsstand;Anfangsdatum;Endstand;Enddatum;Hinweis', $csv);
        $this->assertStringContainsString('4;"Familie Schulte";Wasser;W-1004;34,800;m³;109,44;56,500;17.10.2025;91,300;30.09.2026;"auf Stichtag umgerechnet"', $csv);
    }

    public function test_only_board_and_treasurer_choose_the_key_date(): void
    {
        $this->actingAs(User::factory()->tenantOf($this->plot)->create());

        $this->post(route('billing.key-date.store'), ['year' => 2026, 'key_date' => '2026-09-30'])->assertForbidden();
        $this->delete(route('billing.key-date.destroy'), ['year' => 2026])->assertForbidden();
    }
}
