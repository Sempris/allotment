<?php

namespace Tests\Feature;

use App\Enums\ReadingSource;
use App\Models\Meter;
use App\Models\Reading;
use App\Models\User;
use App\Services\ReadingRecorder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ReadingWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-12');
    }

    public function test_guests_are_sent_to_the_login_page(): void
    {
        $this->get('/')->assertRedirect(route('login'));
        $this->get(route('plots.index'))->assertRedirect(route('login'));
    }

    public function test_a_board_member_can_log_in(): void
    {
        $user = User::factory()->create();

        $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_a_reading_is_recorded_with_its_source_and_recorder(): void
    {
        $user = User::factory()->create();
        $meter = Meter::factory()->create();

        $this->actingAs($user)
            ->post(route('readings.store', $meter), ['read_on' => '2026-10-17', 'value' => '38,250'])
            ->assertRedirect(route('plots.show', $meter->plot_id))
            ->assertSessionHasNoErrors();

        $reading = $meter->readings()->sole();
        $this->assertSame(38_250, $reading->value_milli);
        $this->assertSame(ReadingSource::Manual, $reading->source);
        $this->assertSame($user->id, $reading->recorded_by);
    }

    public function test_an_impossible_reading_is_rejected_with_a_clear_message(): void
    {
        $meter = Meter::factory()->create(['initial_value_milli' => 50_000]);

        $this->actingAs(User::factory()->create())
            ->from(route('readings.create', $meter))
            ->post(route('readings.store', $meter), ['read_on' => '2026-10-17', 'value' => '49'])
            ->assertRedirect(route('readings.create', $meter))
            ->assertSessionHasErrors('value');

        $this->assertSame(0, Reading::count());
    }

    public function test_a_correction_keeps_the_original_reading_in_the_history(): void
    {
        $user = User::factory()->create();
        $meter = Meter::factory()->create();
        $original = app(ReadingRecorder::class)->record($meter, Carbon::parse('2026-10-17'), 83_000, ReadingSource::Import);

        $this->actingAs($user)
            ->post(route('readings.correct.store', $original), [
                'read_on' => '2026-10-17', 'value' => '38', 'note' => 'Zahlendreher auf dem Ablesezettel',
            ])
            ->assertSessionHasNoErrors();

        $original->refresh();
        $correction = $original->supersededBy;

        $this->assertNotNull($correction);
        $this->assertSame(38_000, $correction->value_milli);
        $this->assertSame(ReadingSource::Correction, $correction->source);
        $this->assertSame('Zahlendreher auf dem Ablesezettel', $correction->note);
        $this->assertSame([$correction->id], $meter->effectiveReadings()->pluck('id')->all());
        $this->assertSame(2, $meter->readings()->count());
    }

    public function test_a_correction_needs_a_reason(): void
    {
        $meter = Meter::factory()->create();
        $original = app(ReadingRecorder::class)->record($meter, Carbon::parse('2026-10-17'), 83_000, ReadingSource::Import);

        $this->actingAs(User::factory()->create())
            ->post(route('readings.correct.store', $original), ['read_on' => '2026-10-17', 'value' => '38'])
            ->assertSessionHasErrors('note');
    }

    public function test_a_superseded_reading_cannot_be_corrected_again(): void
    {
        $meter = Meter::factory()->create();
        $recorder = app(ReadingRecorder::class);
        $original = $recorder->record($meter, Carbon::parse('2026-10-17'), 83_000, ReadingSource::Import);
        $recorder->correct($original, Carbon::parse('2026-10-17'), 38_000, 'Zahlendreher');

        $this->actingAs(User::factory()->create())
            ->get(route('readings.correct', $original))
            ->assertNotFound();
    }

    public function test_a_flagged_reading_can_be_confirmed_by_the_board(): void
    {
        $user = User::factory()->create();
        $meter = Meter::factory()->create(['installed_on' => '2026-04-01']);
        $reading = app(ReadingRecorder::class)->record($meter, Carbon::parse('2026-10-17'), 400_000, ReadingSource::Manual);

        $this->assertTrue($reading->needsReview());

        $this->actingAs($user)->get('/')->assertSee($reading->flag_reason);
        $this->actingAs($user)->post(route('readings.review', $reading))->assertSessionHasNoErrors();

        $this->assertFalse($reading->fresh()->needsReview());
        $this->assertSame($user->id, $reading->fresh()->flag_reviewed_by);
    }

    public function test_a_meter_exchange_retires_the_old_meter(): void
    {
        $old = Meter::factory()->create();

        $this->actingAs(User::factory()->create())
            ->post(route('meters.store', $old->plot_id), [
                'type' => 'water', 'serial_number' => 'W-NEU-1', 'installed_on' => '2026-06-01',
                'initial_value' => '0', 'replaces_meter_id' => $old->id,
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('2026-06-01', $old->fresh()->removed_on->toDateString());
        $this->assertTrue(app(\App\Services\PlausibilityChecker::class)
            ->check($old->fresh(), Carbon::parse('2026-07-01'), 99_000)->isRejected());
    }
}
