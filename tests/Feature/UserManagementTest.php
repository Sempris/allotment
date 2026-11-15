<?php

namespace Tests\Feature;

use App\Enums\ReadingSource;
use App\Enums\Role;
use App\Models\Meter;
use App\Models\Plot;
use App\Models\User;
use App\Services\ReadingRecorder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $board;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-12');

        $this->board = User::factory()->create(['name' => 'Erika Gärtner']);
        $this->actingAs($this->board);
    }

    /** @return array<string, mixed> */
    private function input(array $overrides = []): array
    {
        return $overrides + ['name' => 'Olga Petrenko', 'email' => 'olga@example.org', 'role' => 'tenant', 'plot_id' => null, 'active' => '1', 'password' => 'sonnenblume-3'];
    }

    public function test_the_user_pages_show_roles_and_status(): void
    {
        $locked = User::factory()->treasurer()->inactive()->create(['name' => 'Karl Kasse']);

        $this->get(route('users.index'))->assertOk()->assertSee('Erika Gärtner')->assertSee('Karl Kasse')->assertSee('gesperrt');
        $this->get(route('users.create'))->assertOk()->assertSee('Startpasswort');
        $this->get(route('users.edit', $locked))->assertOk()->assertSee('Zugang von Karl Kasse bearbeiten');
    }

    public function test_the_board_creates_a_tenant_login_for_a_plot(): void
    {
        $plot = Plot::factory()->create(['number' => '3']);

        $this->post(route('users.store'), $this->input(['plot_id' => $plot->id]))
            ->assertRedirect(route('users.index'))
            ->assertSessionHasNoErrors();

        $tenant = User::where('email', 'olga@example.org')->sole();
        $this->assertSame(Role::Tenant, $tenant->role);
        $this->assertSame($plot->id, $tenant->plot_id);
        $this->assertTrue(Hash::check('sonnenblume-3', $tenant->password));
    }

    public function test_a_tenant_needs_a_plot(): void
    {
        $this->post(route('users.store'), $this->input())->assertSessionHasErrors('plot_id');

        $this->assertSame(1, User::count());
    }

    public function test_only_tenants_are_bound_to_a_plot(): void
    {
        $plot = Plot::factory()->create();

        $this->post(route('users.store'), $this->input(['role' => 'treasurer', 'plot_id' => $plot->id]))->assertSessionHasNoErrors();

        $this->assertNull(User::where('email', 'olga@example.org')->sole()->plot_id);
    }

    public function test_a_short_start_password_is_rejected(): void
    {
        $plot = Plot::factory()->create();

        $this->post(route('users.store'), $this->input(['plot_id' => $plot->id, 'password' => 'kurz']))->assertSessionHasErrors('password');
    }

    public function test_an_empty_password_field_keeps_the_current_password(): void
    {
        $treasurer = User::factory()->treasurer()->create();
        $before = $treasurer->password;

        $this->put(route('users.update', $treasurer), $this->input(['role' => 'treasurer', 'password' => '', 'name' => 'Neuer Name']))
            ->assertSessionHasNoErrors();

        $this->assertSame('Neuer Name', $treasurer->fresh()->name);
        $this->assertSame($before, $treasurer->fresh()->password);
    }

    public function test_a_user_with_reading_history_is_locked_not_deleted(): void
    {
        $colleague = User::factory()->create();
        app(ReadingRecorder::class)->record(Meter::factory()->create(), Carbon::parse('2026-10-17'), 10_000, ReadingSource::Manual, $colleague);

        $this->delete(route('users.destroy', $colleague))->assertSessionHasErrors('user');
        $this->assertNotNull($colleague->fresh());

        $this->put(route('users.update', $colleague), ['name' => $colleague->name, 'email' => $colleague->email, 'role' => 'board', 'active' => '0'])
            ->assertSessionHasNoErrors();
        $this->assertFalse($colleague->fresh()->active);
    }

    public function test_a_user_without_history_can_be_deleted(): void
    {
        $treasurer = User::factory()->treasurer()->create();

        $this->delete(route('users.destroy', $treasurer))->assertRedirect(route('users.index'));

        $this->assertNull($treasurer->fresh());
    }

    public function test_the_last_active_board_member_cannot_be_demoted_locked_or_deleted(): void
    {
        $data = ['name' => $this->board->name, 'email' => $this->board->email];

        $this->put(route('users.update', $this->board), $data + ['role' => 'treasurer', 'active' => '1'])->assertSessionHasErrors('role');
        $this->put(route('users.update', $this->board), $data + ['role' => 'board', 'active' => '0'])->assertSessionHasErrors('active');
        $this->delete(route('users.destroy', $this->board))->assertSessionHasErrors('user');

        $this->assertSame(Role::Board, $this->board->fresh()->role);
        $this->assertTrue($this->board->fresh()->active);
    }

    public function test_a_locked_board_member_does_not_count_as_a_second_board(): void
    {
        User::factory()->inactive()->create();

        $this->put(route('users.update', $this->board), ['name' => 'x', 'email' => $this->board->email, 'role' => 'treasurer', 'active' => '1'])
            ->assertSessionHasErrors('role');
    }

    public function test_with_a_second_board_member_one_can_step_down(): void
    {
        User::factory()->create();

        $this->put(route('users.update', $this->board), ['name' => $this->board->name, 'email' => $this->board->email, 'role' => 'treasurer', 'active' => '1'])
            ->assertSessionHasNoErrors();

        $this->assertSame(Role::Treasurer, $this->board->fresh()->role);
    }

    public function test_nobody_locks_or_deletes_the_own_login(): void
    {
        User::factory()->create(); // a second board member exists

        $this->put(route('users.update', $this->board), ['name' => 'x', 'email' => $this->board->email, 'role' => 'board', 'active' => '0'])
            ->assertSessionHasErrors('active');
        $this->delete(route('users.destroy', $this->board))->assertSessionHasErrors('user');
    }

    public function test_only_the_board_manages_users(): void
    {
        $this->actingAs(User::factory()->treasurer()->create());

        $this->get(route('users.index'))->assertForbidden();
        $this->post(route('users.store'), $this->input(['role' => 'board']))->assertForbidden();
        $this->delete(route('users.destroy', $this->board))->assertForbidden();
    }
}
