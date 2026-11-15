<?php

namespace Tests\Feature;

use App\Enums\ReadingSource;
use App\Models\Meter;
use App\Models\User;
use App\Services\ReadingRecorder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class MeterManagementTest extends TestCase
{
    use RefreshDatabase;

    private Meter $meter;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-12');

        $this->meter = Meter::factory()->create(['serial_number' => 'W-1001', 'installed_on' => '2023-04-01']);
        app(ReadingRecorder::class)->record($this->meter, Carbon::parse('2025-10-17'), 120_000, ReadingSource::Manual);

        $this->actingAs(User::factory()->create());
    }

    public function test_the_serial_number_can_be_corrected(): void
    {
        $this->put(route('meters.update', $this->meter), ['serial_number' => 'W-1001-A', 'installed_on' => '2023-04-01', 'initial_value' => '0'])
            ->assertRedirect(route('plots.show', $this->meter->plot_id))
            ->assertSessionHasNoErrors();

        $this->assertSame('W-1001-A', $this->meter->fresh()->serial_number);
    }

    public function test_a_serial_number_of_another_meter_is_rejected(): void
    {
        Meter::factory()->create(['serial_number' => 'W-1002']);

        $this->put(route('meters.update', $this->meter), ['serial_number' => 'W-1002', 'installed_on' => '2023-04-01', 'initial_value' => '0'])
            ->assertSessionHasErrors('serial_number');

        $this->assertSame('W-1001', $this->meter->fresh()->serial_number);
    }

    public function test_an_initial_value_above_the_first_reading_is_shown_at_the_field(): void
    {
        $this->put(route('meters.update', $this->meter), ['serial_number' => 'W-1001', 'installed_on' => '2023-04-01', 'initial_value' => '120,001'])
            ->assertSessionHasErrors('initial_value');
    }

    public function test_the_edit_page_explains_why_a_meter_with_readings_cannot_be_deleted(): void
    {
        $this->get(route('meters.edit', $this->meter))->assertOk()->assertSee('kann nicht gelöscht werden')->assertDontSee('endgültig löschen');

        $this->delete(route('meters.destroy', $this->meter))->assertSessionHasErrors('meter');
        $this->assertNotNull($this->meter->fresh());
    }

    public function test_an_unused_meter_can_be_deleted(): void
    {
        $unused = Meter::factory()->for($this->meter->plot)->create();

        $this->delete(route('meters.destroy', $unused))->assertRedirect(route('plots.show', $unused->plot_id));

        $this->assertNull(Meter::find($unused->id));
    }

    public function test_a_meter_can_be_removed_without_replacement(): void
    {
        $this->get('/')->assertSee('Wasser W-1001'); // not read in 2026 yet

        $this->from(route('meters.edit', $this->meter))
            ->post(route('meters.retire', $this->meter), ['removed_on' => '2026-09-30'])
            ->assertRedirect(route('meters.edit', $this->meter))
            ->assertSessionHasErrors('final_value');

        $this->post(route('meters.retire', $this->meter), ['removed_on' => '2026-09-30', 'final_value' => '130'])
            ->assertSessionHasNoErrors();

        $this->assertSame('2026-09-30', $this->meter->fresh()->removed_on->toDateString());
        $this->assertSame(130_000, $this->meter->effectiveReadings()->get()->last()->value_milli);

        // A removed meter is no longer expected to be read this year.
        $this->get('/')->assertDontSee('Wasser W-1001');
    }

    public function test_a_removal_date_before_the_last_reading_is_shown_at_the_date_field(): void
    {
        $this->post(route('meters.retire', $this->meter), ['removed_on' => '2025-06-01', 'final_value' => '100'])
            ->assertSessionHasErrors('removed_on');
    }

    public function test_a_removal_can_be_undone(): void
    {
        $this->post(route('meters.retire', $this->meter), ['removed_on' => '2026-09-30', 'final_value' => '130']);

        $this->post(route('meters.reactivate', $this->meter))->assertSessionHasNoErrors();

        $this->assertNull($this->meter->fresh()->removed_on);
        $this->get(route('meters.edit', $this->meter))->assertOk()->assertSee('Ausbauen ohne Ersatz');
    }
}
