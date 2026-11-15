<?php

namespace Tests\Feature;

use App\Enums\MeterType;
use App\Models\Tariff;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class TariffManagementTest extends TestCase
{
    use RefreshDatabase;

    private Tariff $water2026;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-12');

        $this->water2026 = Tariff::create(['year' => 2026, 'meter_type' => MeterType::Water, 'price_per_unit_cents' => 280, 'base_fee_cents' => 1200]);
        Tariff::create(['year' => 2026, 'meter_type' => MeterType::Electricity, 'price_per_unit_cents' => 37, 'base_fee_cents' => 1800]);

        $this->actingAs(User::factory()->create());
    }

    public function test_editing_prefills_the_form_with_the_current_prices(): void
    {
        $this->get(route('tariffs.index', ['edit' => $this->water2026->id]))
            ->assertOk()
            ->assertSee('Tarif Wasser 2026 bearbeiten')
            ->assertSee('value="2,80"', false)
            ->assertSee('value="12,00"', false);
    }

    public function test_saving_overwrites_the_tariff_of_that_year_and_type(): void
    {
        $this->post(route('tariffs.store'), ['year' => 2026, 'meter_type' => 'water', 'price_per_unit' => '2,95', 'base_fee' => '12,50'])
            ->assertRedirect(route('tariffs.index'))
            ->assertSessionHasNoErrors();

        $this->assertSame(2, Tariff::count());
        $this->assertSame(295, $this->water2026->fresh()->price_per_unit_cents);
        $this->assertSame(1250, $this->water2026->fresh()->base_fee_cents);
    }

    public function test_a_deleted_tariff_shows_up_as_missing_in_the_billing(): void
    {
        $this->get(route('billing.index', ['year' => 2026]))->assertDontSee('fehlt der Tarif');

        $this->delete(route('tariffs.destroy', $this->water2026))->assertRedirect(route('tariffs.index'));

        $this->assertNull($this->water2026->fresh());
        $this->get(route('billing.index', ['year' => 2026]))->assertSee('fehlt der Tarif für: Wasser');
    }
}
