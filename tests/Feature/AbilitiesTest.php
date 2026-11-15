<?php

namespace Tests\Feature;

use App\Models\Plot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AbilitiesTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, array{string, bool, bool, bool}> */
    public static function matrix(): array
    {
        return [
            //                      ability                board  treasurer tenant
            'view-club' => ['view-club', true, true, false],
            'record-readings' => ['record-readings', true, false, false],
            'manage-master-data' => ['manage-master-data', true, false, false],
            'manage-billing' => ['manage-billing', true, true, false],
            'manage-users' => ['manage-users', true, false, false],
        ];
    }

    #[DataProvider('matrix')]
    public function test_each_role_has_exactly_the_documented_rights(string $ability, bool $board, bool $treasurer, bool $tenant): void
    {
        $plot = Plot::factory()->create();

        $this->assertSame($board, User::factory()->create()->can($ability));
        $this->assertSame($treasurer, User::factory()->treasurer()->create()->can($ability));
        $this->assertSame($tenant, User::factory()->tenantOf($plot)->create()->can($ability));
    }

    public function test_a_tenant_sees_only_the_own_plot(): void
    {
        $own = Plot::factory()->create();
        $other = Plot::factory()->create();
        $tenant = User::factory()->tenantOf($own)->create();

        $this->assertTrue($tenant->can('view-plot', $own));
        $this->assertFalse($tenant->can('view-plot', $other));
    }

    public function test_board_and_treasurer_see_every_plot(): void
    {
        $plot = Plot::factory()->create();

        $this->assertTrue(User::factory()->create()->can('view-plot', $plot));
        $this->assertTrue(User::factory()->treasurer()->create()->can('view-plot', $plot));
    }

    public function test_existing_users_become_board_members(): void
    {
        // The column default covers users created before roles existed.
        $id = \DB::table('users')->insertGetId(['name' => 'Alt', 'email' => 'alt@example.org', 'password' => 'x']);

        $this->assertTrue(User::findOrFail($id)->can('manage-users'));
    }
}
