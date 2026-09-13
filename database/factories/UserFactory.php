<?php

namespace Database\Factories;

use App\Enums\AuthProvider;
use App\Enums\UserRole;
use App\Models\Business;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected $model = User::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'business_id' => Business::factory(),
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'password' => 'password',
            'role' => UserRole::Owner,
            'provider' => AuthProvider::Email,
            'avatar_url' => null,
        ];
    }

    public function accountant(): static
    {
        return $this->state(fn () => ['role' => UserRole::Accountant]);
    }

    /**
     * Read-only: can see the book, cannot change it.
     */
    public function viewer(): static
    {
        return $this->state(fn () => ['role' => UserRole::Viewer]);
    }

    /**
     * An account that can only be signed into with Google.
     */
    public function google(): static
    {
        return $this->state(fn () => [
            'password' => null,
            'provider' => AuthProvider::Google,
            'google_id' => (string) Str::random(20),
        ]);
    }
}
