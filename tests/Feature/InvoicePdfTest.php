<?php

namespace Tests\Feature;

use App\Enums\MeterType;
use App\Enums\ReadingSource;
use App\Models\BillingPeriod;
use App\Models\Meter;
use App\Models\Plot;
use App\Models\Tariff;
use App\Models\User;
use App\Services\InvoiceRenderer;
use App\Services\ReadingRecorder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Plot 4, 2026: water 36.500 m³ × 2.80 € + 12.00 € = 114.20 €,
 * electricity 152.7 kWh × 0.37 € + 18.00 € = 74.50 €, total 188.70 €.
 */
class InvoicePdfTest extends TestCase
{
    use RefreshDatabase;

    private Plot $plot;

    private Meter $water;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-12');

        $this->plot = Plot::factory()->create(['number' => '4', 'tenant_name' => 'Familie Schulte']);
        $this->water = Meter::factory()->for($this->plot)->create(['serial_number' => 'W-1004', 'installed_on' => '2024-04-01']);
        $power = Meter::factory()->for($this->plot)->electricity()->create(['serial_number' => 'S-2004', 'installed_on' => '2025-10-17']);
        Tariff::create(['year' => 2026, 'meter_type' => MeterType::Water, 'price_per_unit_cents' => 280, 'base_fee_cents' => 1200]);
        Tariff::create(['year' => 2026, 'meter_type' => MeterType::Electricity, 'price_per_unit_cents' => 37, 'base_fee_cents' => 1800]);

        $recorder = app(ReadingRecorder::class);
        $recorder->record($this->water, Carbon::parse('2025-10-17'), 56_500, ReadingSource::Manual);
        $recorder->record($this->water, Carbon::parse('2026-10-17'), 93_000, ReadingSource::Manual);
        $recorder->record($power, Carbon::parse('2026-10-17'), 152_700, ReadingSource::Manual);
    }

    public function test_the_bill_shows_how_the_amount_is_made_up(): void
    {
        $this->view('invoices.pdf', app(InvoiceRenderer::class)->data($this->plot, 2026))
            ->assertSee('Jahresabrechnung 2026')
            ->assertSee('Familie Schulte')
            ->assertSee('56,500 m³')->assertSee('17.10.2025')
            ->assertSee('93,000 m³')->assertSee('17.10.2026')
            ->assertSee('36,500 m³')->assertSee('2,80 €')->assertSee('12,00 €')->assertSee('114,20 €')
            ->assertSee('152,700 kWh')->assertSee('0,37 €')->assertSee('18,00 €')->assertSee('74,50 €')
            ->assertSee('188,70 €')
            ->assertSee('26.11.2026') // due 14 days after 12.11.2026
            ->assertSee('Abrechnung 2026 Parzelle 4')
            ->assertSee('IBAN DE00')
            ->assertDontSee('≈');
    }

    public function test_interpolated_values_are_marked_and_explained_on_the_bill(): void
    {
        BillingPeriod::create(['year' => 2026, 'key_date' => '2026-09-30']);
        // The electricity meter has no reading before the key date, so it is interpolated from its installation.

        $this->view('invoices.pdf', app(InvoiceRenderer::class)->data($this->plot, 2026))
            ->assertSee('≈ 91,300 m³')
            ->assertSee('auf Stichtag umgerechnet')
            ->assertSee('Der Zählerstand wurde auf den Abrechnungsstichtag umgerechnet');
    }

    public function test_the_bill_can_be_downloaded_as_pdf(): void
    {
        $response = $this->actingAs(User::factory()->treasurer()->create())
            ->get(route('invoices.show', ['year' => 2026, 'plot' => $this->plot]));

        $response->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $response->getContent());
        $this->assertStringContainsString('jahresabrechnung-2026-parzelle-4.pdf', $response->headers->get('Content-Disposition'));
    }

    public function test_an_incomplete_bill_is_not_available_as_pdf(): void
    {
        Tariff::query()->where('meter_type', MeterType::Electricity)->delete();

        $this->actingAs(User::factory()->create())
            ->get(route('invoices.show', ['year' => 2026, 'plot' => $this->plot]))
            ->assertNotFound()
            ->assertSee('unvollständig');
    }

    public function test_a_year_without_meter_has_no_bill(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('invoices.show', ['year' => 2020, 'plot' => $this->plot]))
            ->assertNotFound();
    }

    public function test_a_tenant_cannot_download_the_bill_of_another_plot(): void
    {
        $other = Plot::factory()->create();

        $this->actingAs(User::factory()->tenantOf($other)->create())
            ->get(route('invoices.show', ['year' => 2026, 'plot' => $this->plot]))
            ->assertForbidden();
    }
}
