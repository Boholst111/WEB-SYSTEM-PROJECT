<?php

namespace Database\Factories;

use App\Models\AdminUser;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\AdminUser>
 */
class AdminUserFactory extends Factory
{
    protected $model = AdminUser::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'username' => $this->faker->unique()->userName(),
            'email' => $this->faker->unique()->safeEmail(),
            'password_hash' => Hash::make('password'),
            'first_name' => $this->faker->firstName(),
            'last_name' => $this->faker->lastName(),
            'role' => $this->faker->randomElement(['super_admin', 'admin', 'manager', 'staff']),
            'permissions' => null,
            'status' => $this->faker->randomElement(['active', 'inactive', 'suspended']),
            'last_login_at' => $this->faker->optional()->dateTimeBetween('-1 month', 'now'),
            'last_login_ip' => $this->faker->optional()->ipv4(),
            'require_password_change' => false,
            'password_changed_at' => $this->faker->optional()->dateTimeBetween('-6 months', 'now'),
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Indicate that the admin is a super admin.
     */
    public function superAdmin(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'super_admin',
            'status' => 'active',
        ]);
    }

    /**
     * Indicate that the admin is an admin.
     */
    public function admin(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'admin',
            'status' => 'active',
        ]);
    }

    /**
     * Indicate that the admin is active.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'active',
        ]);
    }

    /**
     * Indicate that the admin is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'inactive',
        ]);
    }
}