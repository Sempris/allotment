<?php

namespace Database\Factories;

use App\Enums\Role;
use App\Models\Plot;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        return [
            'name' => 'Vorstand '.Str::random(4),
            'email' => Str::lower(Str::random(10)).'@example.org',
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'role' => Role::Board,
            'plot_id' => null,
            'active' => true,
        ];
    }

    public function treasurer(): static
    {
        return $this->state(fn () => ['role' => Role::Treasurer]);
    }

    public function tenantOf(Plot $plot): static
    {
        return $this->state(fn () => ['role' => Role::Tenant, 'plot_id' => $plot->getKey()]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['active' => false]);
    }
}
