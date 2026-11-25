<?php

namespace Database\Factories;

use App\Enums\UserRole;
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

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nama_lengkap' => fake()->name(),
            'username' => fake()->unique()->userName(),
            'email' => fake()->unique()->safeEmail(),
            'password_hash' => static::$password ??= Hash::make('password'),
            'role' => UserRole::Kasir,
            'is_active' => true,
            'remember_token' => Str::random(10),
        ];
    }

    public function admin(): static
    {
        return $this->state(fn (): array => [
            'role' => UserRole::Admin,
        ]);
    }

    public function kasir(): static
    {
        return $this->state(fn (): array => [
            'role' => UserRole::Kasir,
        ]);
    }

    public function owner(): static
    {
        return $this->state(fn (): array => [
            'role' => UserRole::Owner,
        ]);
    }

    public function pelanggan(): static
    {
        return $this->state(fn (): array => [
            'role' => UserRole::Pelanggan,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => [
            'is_active' => false,
        ]);
    }
}
