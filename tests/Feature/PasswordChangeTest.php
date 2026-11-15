<?php

namespace Tests\Feature;

use App\Models\Plot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PasswordChangeTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_tenant_replaces_the_start_password(): void
    {
        $tenant = User::factory()->tenantOf(Plot::factory()->create())->create(); // factory password: "password"

        $this->actingAs($tenant)->get(route('password.edit'))->assertOk();

        $this->put(route('password.update'), [
            'current_password' => 'password', 'password' => 'mein-neues-passwort', 'password_confirmation' => 'mein-neues-passwort',
        ])->assertRedirect(route('dashboard'))->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('mein-neues-passwort', $tenant->fresh()->password));
    }

    public function test_the_current_password_is_required(): void
    {
        $user = User::factory()->create();
        $before = $user->password;

        $this->actingAs($user)->put(route('password.update'), [
            'current_password' => 'falsch', 'password' => 'mein-neues-passwort', 'password_confirmation' => 'mein-neues-passwort',
        ])->assertSessionHasErrors('current_password');

        $this->assertSame($before, $user->fresh()->password);
    }

    public function test_the_new_password_must_be_repeated_and_long_enough(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->put(route('password.update'), [
            'current_password' => 'password', 'password' => 'mein-neues-passwort', 'password_confirmation' => 'anders',
        ])->assertSessionHasErrors('password');

        $this->actingAs($user)->put(route('password.update'), [
            'current_password' => 'password', 'password' => 'kurz', 'password_confirmation' => 'kurz',
        ])->assertSessionHasErrors('password');
    }
}
