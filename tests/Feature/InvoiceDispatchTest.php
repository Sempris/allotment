<?php

namespace Tests\Feature;

use App\Enums\MeterType;
use App\Enums\ReadingSource;
use App\Exceptions\InvoiceNotAvailable;
use App\Mail\InvoiceMail;
use App\Models\InvoiceDispatch;
use App\Models\Meter;
use App\Models\Plot;
use App\Models\Tariff;
use App\Models\User;
use App\Services\InvoiceDispatcher;
use App\Services\ReadingRecorder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class InvoiceDispatchTest extends TestCase
{
    use RefreshDatabase;

    private InvoiceDispatcher $dispatcher;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-12');
        Mail::fake();

        Tariff::create(['year' => 2026, 'meter_type' => MeterType::Water, 'price_per_unit_cents' => 280, 'base_fee_cents' => 1200]);
        $this->dispatcher = app(InvoiceDispatcher::class);
    }

    /** A plot whose 2026 bill is 36.500 m³ × 2.80 € + 12.00 € = 114.20 €. */
    private function plotWithBill(string $number, ?string $email): Plot
    {
        $plot = Plot::factory()->create(['number' => $number, 'tenant_email' => $email]);
        $meter = Meter::factory()->for($plot)->create(['installed_on' => '2024-04-01']);
        $recorder = app(ReadingRecorder::class);
        $recorder->record($meter, Carbon::parse('2025-10-17'), 56_500, ReadingSource::Manual);
        $recorder->record($meter, Carbon::parse('2026-10-17'), 93_000, ReadingSource::Manual);

        return $plot;
    }

    public function test_the_bill_is_mailed_as_pdf_and_logged_with_its_amount(): void
    {
        $board = User::factory()->create();
        $plot = $this->plotWithBill('4', 'schulte@example.org');

        $dispatch = $this->dispatcher->sendByEmail($plot, 2026, $board);

        Mail::assertSent(InvoiceMail::class, function (InvoiceMail $mail) {
            $mail->assertTo('schulte@example.org');
            $mail->assertHasSubject('Jahresabrechnung 2026 – Parzelle 4');
            $mail->assertSeeInText('114,20 €');
            $this->assertCount(1, $mail->attachments());

            return true;
        });
        Mail::assertSentCount(1);

        $this->assertSame(11_420, $dispatch->total_cents);
        $this->assertSame('schulte@example.org', $dispatch->recipient_email);
        $this->assertSame($board->id, $dispatch->sent_by);
        $this->assertFalse($dispatch->wasHandedOver());
    }

    public function test_without_email_address_the_bill_is_handed_over_as_printout(): void
    {
        $plot = $this->plotWithBill('5', null);

        try {
            $this->dispatcher->sendByEmail($plot, 2026);
            $this->fail('Sending without an address should be rejected.');
        } catch (InvoiceNotAvailable $e) {
            $this->assertStringContainsString('keine E-Mail-Adresse', $e->getMessage());
        }

        $dispatch = $this->dispatcher->markHandedOver($plot, 2026);

        Mail::assertNothingSent();
        $this->assertTrue($dispatch->wasHandedOver());
        $this->assertSame(11_420, $dispatch->total_cents);
    }

    public function test_an_incomplete_bill_does_not_go_out(): void
    {
        $plot = $this->plotWithBill('4', 'schulte@example.org');
        Tariff::query()->delete();

        $this->expectException(InvoiceNotAvailable::class);

        try {
            $this->dispatcher->sendByEmail($plot, 2026);
        } finally {
            Mail::assertNothingSent();
            $this->assertSame(0, InvoiceDispatch::count());
        }
    }

    public function test_a_bill_with_a_reading_awaiting_review_does_not_go_out(): void
    {
        // A burst pipe or a typo: five times the usual consumption gets flagged.
        $plot = Plot::factory()->create(['number' => '7', 'tenant_email' => 'nowak@example.org']);
        $meter = Meter::factory()->for($plot)->create(['installed_on' => '2024-04-01']);
        $recorder = app(ReadingRecorder::class);
        $recorder->record($meter, Carbon::parse('2025-10-17'), 56_500, ReadingSource::Manual);
        $flagged = $recorder->record($meter, Carbon::parse('2026-10-17'), 256_500, ReadingSource::Manual);
        $this->assertTrue($flagged->needsReview());

        try {
            $this->dispatcher->sendByEmail($plot, 2026);
            $this->fail('A bill with an unreviewed reading should not go out.');
        } catch (InvoiceNotAvailable $e) {
            $this->assertStringContainsString('zur Prüfung markiert', $e->getMessage());
        }
        Mail::assertNothingSent();

        // The board confirms the reading (it really was a burst pipe): now the bill can go out.
        $flagged->forceFill(['flag_reviewed_at' => now()])->save();
        $this->dispatcher->sendByEmail($plot->fresh(), 2026);

        Mail::assertSentCount(1);
    }

    public function test_sending_all_pending_bills_skips_issued_ones_and_reports_the_rest(): void
    {
        $alreadySent = $this->plotWithBill('1', 'eins@example.org');
        $this->dispatcher->sendByEmail($alreadySent, 2026);
        $this->plotWithBill('2', null);                   // no address
        $this->plotWithBill('3', 'drei@example.org');     // pending
        Meter::factory()->for(Plot::factory()->create(['number' => '4', 'tenant_email' => 'vier@example.org']))->create(); // not read
        Plot::factory()->create(['number' => '5']);        // no meter at all
        Mail::fake(); // forget the first mail

        $result = $this->dispatcher->sendAllPending(2026);

        $this->assertSame(['sent' => ['3'], 'no_email' => ['2'], 'not_ready' => ['4'], 'failed' => []], $result);
        Mail::assertSentCount(1);
        Mail::assertSent(InvoiceMail::class, fn (InvoiceMail $mail) => $mail->hasTo('drei@example.org'));
        $this->assertSame(2, InvoiceDispatch::count());
    }

    public function test_a_user_who_issued_bills_is_part_of_the_history(): void
    {
        $treasurer = User::factory()->treasurer()->create();
        $this->assertFalse($treasurer->hasHistory());

        $this->dispatcher->sendByEmail($this->plotWithBill('4', 'schulte@example.org'), 2026, $treasurer);

        $this->assertTrue($treasurer->hasHistory());
    }
}
