<?php

namespace App\Providers;

use App\Enums\Role;
use App\Models\Plot;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->defineAbilities();
    }

    /**
     * Who may do what. Routes and views ask for these abilities, never for a
     * role, so this is the one place that maps roles to rights.
     */
    private function defineAbilities(): void
    {
        $board = fn (User $user) => $user->role === Role::Board;
        $boardOrTreasurer = fn (User $user) => in_array($user->role, [Role::Board, Role::Treasurer], true);

        // Overview, all plots, billing and tariffs (read).
        Gate::define('view-club', $boardOrTreasurer);

        // One plot with its meters and reading history. Tenants see only their own.
        Gate::define('view-plot', fn (User $user, Plot $plot) => $boardOrTreasurer($user)
            || ($user->role === Role::Tenant && $user->plot_id === $plot->getKey()));

        // Record, correct, review and import readings.
        Gate::define('record-readings', $board);

        // Create, edit, remove plots and meters.
        Gate::define('manage-master-data', $board);

        // Tariffs and invoices. The treasurer bills, but does not change meter values.
        Gate::define('manage-billing', $boardOrTreasurer);

        Gate::define('manage-users', $board);
    }
}
