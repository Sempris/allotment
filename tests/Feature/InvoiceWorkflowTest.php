<?php

namespace Tests\Feature;

use App\Enums\MeterType;
use App\Enums\ReadingSource;
use App\Mail\InvoiceMail;
use App\Models\InvoiceDispatch;
use App\Models\Meter;
use App\Models\Plot;
use App\Models\Tariff;
use App\Models\User;
use App\Services\ReadingRecorder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class InvoiceWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private Plot $plot;

    private Meter $meter;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-12');
        Mail::fake();

        // 2026: 36.500 m³ × 2.80 € + 12.00 € = 114.20 €
        Tariff::create(['year' => 2026, 'meter_type' => MeterType::Water, 'price_per_unit_cents' => 280, 'base_fee_cents' => 1200]);
        $this->plot = Plot::factory()->create(['number' => '4', 'tenant_name' => 'Familie Schulte', 'tenant_email' => 'schulte@example.org']);
        $this->meter = Meter::factory()->for($this->plot)->create(['installed_on' => '2024-04-01']);
        $recorder = app(ReadingRecorder::class);
        $recorder->record($this->meter, Carbon::parse('2025-10-17'), 56_500, ReadingSource::Manual);
        $recorder->record($this->meter, Carbon::parse('2026-10-17'), 93_000, ReadingSource::Manual);
    }

    /** @return array{year: int, plot: Plot} */
    private function invoice(?Plot $plot = null): array
    {
        return ['year' => 2026, 'plot' => $plot ?? $this->plot];
    }

    public function test_the_treasurer_sends_a_bill_and_the_page_shows_it_as_sent(): void
    {
        $this->actingAs(User::factory()->treasurer()->create());

        $this->get(route('billing.index', ['year' => 2026]))->assertSee('Per E-Mail senden')->assertDontSee('versendet an');

        $this->from(route('billing.index', ['year' => 2026]))
            ->post(route('invoices.send', $this->invoice()))
            ->assertRedirect(route('billing.index', ['year' => 2026]))
            ->assertSessionHasNoErrors();

        Mail::assertSent(InvoiceMail::class, fn (InvoiceMail $mail) => $mail->hasTo('schulte@example.org'));

        $this->get(route('billing.index', ['year' => 2026]))
            ->assertSee('versendet an schulte@example.org am 12.11.2026')
            ->assertDontSee('Per E-Mail senden')
            ->assertDontSee('Erneut senden');
    }

    public function test_a_plot_without_email_is_marked_as_handed_over(): void
    {
        $this->plot->update(['tenant_email' => null]);
        $this->actingAs(User::factory()->create());

        $this->get(route('billing.index', ['year' => 2026]))->assertSee('Ausgedruckt übergeben')->assertDontSee('Per E-Mail senden');
        $this->post(route('invoices.send', $this->invoice()))->assertSessionHasErrors('invoice');

        $this->post(route('invoices.hand-over', $this->invoice()))->assertSessionHasNoErrors();

        Mail::assertNothingSent();
        $this->get(route('billing.index', ['year' => 2026]))->assertSee('übergeben am 12.11.2026');
    }

    public function test_a_correction_after_sending_is_shown_as_changed_amount(): void
    {
        $this->actingAs(User::factory()->create());
        $this->post(route('invoices.send', $this->invoice()));

        // The 2026 reading was 93.000 by mistake; 92.000 is right: 35.500 m³ -> 99.40 € + 12.00 €.
        app(ReadingRecorder::class)->correct($this->meter->effectiveReadings()->get()->last(), Carbon::parse('2026-10-17'), 92_000, 'Ablesefehler');

        $this->get(route('billing.index', ['year' => 2026]))
            ->assertSee('111,40 €')
            ->assertSee('Betrag seit der Herausgabe geändert (war 114,20 €)')
            ->assertSee('Erneut senden');
    }

    public function test_a_flagged_reading_blocks_sending_until_it_is_reviewed(): void
    {
        $board = User::factory()->create();
        $flagged = app(ReadingRecorder::class)->correct(
            $this->meter->effectiveReadings()->get()->last(), Carbon::parse('2026-10-17'), 256_500, 'Rohrbruch laut Pächter',
        );
        $this->assertTrue($flagged->needsReview());

        $this->actingAs($board)->get(route('billing.index', ['year' => 2026]))
            ->assertSee('Ablesung zur Prüfung markiert')
            ->assertDontSee('Per E-Mail senden');
        $this->post(route('invoices.send', $this->invoice()))->assertSessionHasErrors('invoice');
        Mail::assertNothingSent();

        $this->post(route('readings.review', $flagged));
        $this->post(route('invoices.send', $this->invoice()))->assertSessionHasNoErrors();
        Mail::assertSentCount(1);
    }

    public function test_all_pending_bills_are_sent_with_a_summary(): void
    {
        Plot::factory()->create(['number' => '9']); // no meter: not mentioned at all
        $withoutEmail = Plot::factory()->create(['number' => '5']);
        $meter = Meter::factory()->for($withoutEmail)->create(['installed_on' => '2026-04-01']);
        app(ReadingRecorder::class)->record($meter, Carbon::parse('2026-10-17'), 10_000, ReadingSource::Manual);

        $this->actingAs(User::factory()->treasurer()->create())
            ->from(route('billing.index', ['year' => 2026]))
            ->post(route('invoices.send-all', ['year' => 2026]))
            ->assertRedirect(route('billing.index', ['year' => 2026]))
            ->assertSessionHas('status', 'Abrechnungen 2026: 1 versendet · ohne E-Mail-Adresse (bitte ausdrucken): Parzelle 5.');

        Mail::assertSentCount(1);
        $this->assertSame(1, InvoiceDispatch::count());
    }

    public function test_a_tenant_gets_the_bill_only_after_it_was_issued(): void
    {
        $tenant = User::factory()->tenantOf($this->plot)->create();

        $this->actingAs($tenant)->get(route('invoices.show', $this->invoice()))->assertForbidden()->assertSee('noch nicht herausgegeben');
        $this->get(route('plots.show', $this->plot))->assertDontSee('Jahresabrechnungen');

        $this->actingAs(User::factory()->create())->post(route('invoices.send', $this->invoice()));

        $this->actingAs($tenant)->get(route('plots.show', $this->plot))
            ->assertSee('Abrechnung 2026 (PDF)')
            ->assertSee('114,20 €');
        $response = $this->get(route('invoices.show', $this->invoice()));
        $response->assertOk()->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_tenants_cannot_issue_bills(): void
    {
        $this->actingAs(User::factory()->tenantOf($this->plot)->create());

        $this->post(route('invoices.send', $this->invoice()))->assertForbidden();
        $this->post(route('invoices.hand-over', $this->invoice()))->assertForbidden();
        $this->post(route('invoices.send-all', ['year' => 2026]))->assertForbidden();

        Mail::assertNothingSent();
    }

    public function test_a_plot_with_issued_bills_cannot_be_deleted(): void
    {
        InvoiceDispatch::create(['plot_id' => $this->plot->id, 'year' => 2026, 'total_cents' => 11_420]);

        $this->actingAs(User::factory()->create())->delete(route('plots.destroy', $this->plot))->assertSessionHasErrors('plot');

        $this->assertNotNull($this->plot->fresh());
    }
}
