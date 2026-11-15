<?php

namespace Tests\Feature;

use App\Models\Meter;
use App\Models\Plot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlotManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
    }

    public function test_a_change_of_tenant_keeps_meters_and_number(): void
    {
        $plot = Plot::factory()->create(['number' => '7', 'tenant_name' => 'Familie Nowak']);
        $meter = Meter::factory()->for($plot)->create();

        $this->put(route('plots.update', $plot), ['number' => '7', 'tenant_name' => 'Tarik Demir', 'tenant_email' => 'tarik@example.org'])
            ->assertRedirect(route('plots.show', $plot))
            ->assertSessionHasNoErrors();

        $plot->refresh();
        $this->assertSame('Tarik Demir', $plot->tenant_name);
        $this->assertSame('tarik@example.org', $plot->tenant_email);
        $this->assertSame([$meter->id], $plot->meters()->pluck('id')->all());
    }

    public function test_a_plot_cannot_take_the_number_of_another_plot(): void
    {
        Plot::factory()->create(['number' => '8']);
        $plot = Plot::factory()->create(['number' => '7']);

        $this->put(route('plots.update', $plot), ['number' => '8', 'tenant_name' => 'Familie Nowak'])
            ->assertSessionHasErrors('number');

        $this->assertSame('7', $plot->fresh()->number);
    }

    public function test_a_plot_with_meters_cannot_be_deleted(): void
    {
        $plot = Meter::factory()->create()->plot;

        $this->get(route('plots.edit', $plot))->assertOk()->assertSee('kann nicht gelöscht werden');
        $this->delete(route('plots.destroy', $plot))->assertSessionHasErrors('plot');

        $this->assertNotNull($plot->fresh());
    }

    public function test_an_empty_plot_can_be_deleted(): void
    {
        $plot = Plot::factory()->create();

        $this->delete(route('plots.destroy', $plot))->assertRedirect(route('plots.index'));

        $this->assertNull($plot->fresh());
    }
}
