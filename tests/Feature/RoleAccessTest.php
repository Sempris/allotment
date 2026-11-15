<?php

namespace Tests\Feature;

use App\Enums\ReadingSource;
use App\Models\Meter;
use App\Models\Plot;
use App\Models\Reading;
use App\Models\Tariff;
use App\Models\User;
use App\Services\ReadingRecorder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class RoleAccessTest extends TestCase
{
    use RefreshDatabase;

    private Plot $plot3;

    private Plot $plot4;

    private Meter $meter;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-12');

        $this->plot3 = Plot::factory()->create(['number' => '3', 'tenant_name' => 'Olga Petrenko']);
        $this->plot4 = Plot::factory()->create(['number' => '4', 'tenant_name' => 'Familie Schulte']);
        $this->meter = Meter::factory()->for($this->plot3)->create(['serial_number' => 'W-1003']);
        app(ReadingRecorder::class)->record($this->meter, Carbon::parse('2025-10-17'), 51_000, ReadingSource::Manual);
    }

    public function test_the_treasurer_reads_everything_but_cannot_change_meter_values(): void
    {
        $this->actingAs(User::factory()->treasurer()->create());

        $this->get('/')->assertOk();
        $this->get(route('plots.index'))->assertOk();
        $this->get(route('plots.show', $this->plot3))->assertOk()->assertDontSee('Korrigieren')->assertDontSee('Ablesung erfassen');
        $this->get(route('billing.index'))->assertOk();

        $this->post(route('readings.store', $this->meter), ['read_on' => '2026-10-17', 'value' => '60'])->assertForbidden();
        $this->post(route('readings.correct.store', $this->meter->readings()->first()), ['read_on' => '2025-10-17', 'value' => '50', 'note' => 'x'])->assertForbidden();
        $this->get(route('import.create'))->assertForbidden();
        $this->get(route('plots.create'))->assertForbidden();
        $this->get(route('meters.edit', $this->meter))->assertForbidden();
        $this->delete(route('plots.destroy', $this->plot4))->assertForbidden();

        $this->assertSame(1, Reading::count());
        $this->assertNotNull($this->plot4->fresh());
    }

    public function test_the_treasurer_maintains_tariffs(): void
    {
        $this->actingAs(User::factory()->treasurer()->create())
            ->post(route('tariffs.store'), ['year' => 2026, 'meter_type' => 'water', 'price_per_unit' => '2,80', 'base_fee' => '12,00'])
            ->assertSessionHasNoErrors();

        $this->assertSame(280, Tariff::sole()->price_per_unit_cents);
    }

    public function test_a_tenant_lands_on_the_own_plot(): void
    {
        $this->actingAs(User::factory()->tenantOf($this->plot3)->create())
            ->get('/')
            ->assertRedirect(route('plots.show', $this->plot3));
    }

    public function test_a_tenant_sees_the_own_readings_without_any_actions(): void
    {
        $this->actingAs(User::factory()->tenantOf($this->plot3)->create())
            ->get(route('plots.show', $this->plot3))
            ->assertOk()
            ->assertSee('51,000')
            ->assertSee('Meine Parzelle')
            ->assertDontSee('Korrigieren')
            ->assertDontSee('Ablesung erfassen')
            ->assertDontSee('Parzelle bearbeiten')
            ->assertDontSee('Jahresabrechnung');
    }

    public function test_a_tenant_cannot_see_anything_else(): void
    {
        $this->actingAs(User::factory()->tenantOf($this->plot3)->create());

        $this->get(route('plots.show', $this->plot4))->assertForbidden();
        $this->get(route('plots.index'))->assertForbidden();
        $this->get(route('billing.index'))->assertForbidden();
        $this->get(route('billing.csv'))->assertForbidden();
        $this->get(route('tariffs.index'))->assertForbidden();
        $this->post(route('readings.store', $this->meter), ['read_on' => '2026-10-17', 'value' => '60'])->assertForbidden();
        $this->post(route('tariffs.store'), ['year' => 2026, 'meter_type' => 'water', 'price_per_unit' => '0,01', 'base_fee' => '0'])->assertForbidden();
    }

    public function test_a_tenant_without_plot_gets_an_explanation(): void
    {
        $tenant = User::factory()->tenantOf($this->plot4)->create();
        $this->plot4->delete(); // plot_id becomes null

        $this->actingAs($tenant->fresh())->get('/')->assertForbidden()->assertSee('keine Parzelle zugeordnet');
    }

    public function test_a_locked_user_cannot_log_in(): void
    {
        $user = User::factory()->inactive()->create();

        $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_locking_a_user_ends_the_running_session(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get('/')->assertOk();

        $user->update(['active' => false]);

        $this->get('/')->assertRedirect(route('login'));
        $this->assertGuest();
    }
}
