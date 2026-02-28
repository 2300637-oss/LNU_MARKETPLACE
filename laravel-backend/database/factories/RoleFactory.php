<?php

namespace Database\Factories;

use App\Models\Role;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Role>
 */
class RoleFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<\App\Models\Role>
     */
    protected $model = Role::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $code = fake()->unique()->bothify('role_??##');

        return [
            'code' => $code,
            'name' => Str::title(str_replace('_', ' ', $code)),
            'description' => 'System role',
            'is_system' => true,
        ];
    }

    public function admin(): static
    {
        return $this->state(fn (array $attributes) => [
            'code' => 'admin',
            'name' => 'Admin',
            'description' => 'Platform administrator',
            'is_system' => true,
        ]);
    }

    public function user(): static
    {
        return $this->state(fn (array $attributes) => [
            'code' => 'user',
            'name' => 'User',
            'description' => 'Default student account',
            'is_system' => true,
        ]);
    }
}

