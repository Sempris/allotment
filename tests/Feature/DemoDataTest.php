<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Plot;
use App\Models\Reading;
use App\Models\User;
use App\Services\BillingService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * The README describes what the demo data shows. This keeps the two in sync.
 */
class DemoDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_demo_data_tells_the_story_from_the_readme(): void
    {
        Carbon::setTestNow('2026-11-12');
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(8, Plot::count());
        $this->assertEqualsCanonicalizing([Role::Board, Role::Treasurer, Role::Tenant], User::all()->map->role->all());
        $this->assertSame('3', User::where('role', Role::Tenant)->sole()->plot->number);

        $bills = app(BillingService::class)->forYear(2026);
        $numbers = fn ($bills) => $bills->map(fn ($bill) => $bill['plot']->number)->values()->all();

        // Plots 5 and 8 have not been read in 2026 yet.
        $this->assertSame(['5', '8'], $numbers($bills->reject(fn ($bill) => $bill['complete'])));

        // Plot 7: the burst pipe is flagged, so its bill cannot go out before someone looked at it.
        $this->assertSame(['7'], $numbers($bills->filter(fn ($bill) => $bill['open_reviews'] > 0)));
        $this->assertSame(1, Reading::query()->awaitingReview()->count());

        // Plots 2 and 6 get their bill as a printout.
        $this->assertSame(['2', '6'], Plot::whereNull('tenant_email')->orderBy('id')->pluck('number')->all());
    }
}
