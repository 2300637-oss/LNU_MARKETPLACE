<?php

namespace Database\Factories;

use App\Models\StudentIdPrefix;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\StudentIdPrefix>
 */
class StudentIdPrefixFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<\App\Models\StudentIdPrefix>
     */
    protected $model = StudentIdPrefix::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $enrollmentYear = fake()->unique()->numberBetween(2019, 2099);
        $prefix = substr((string) $enrollmentYear, -2).'0';

        return [
            'prefix' => $prefix,
            'enrollment_year' => $enrollmentYear,
            'is_active' => true,
            'notes' => null,
        ];
    }

    public function byYear(int $year): static
    {
        $prefix = substr((string) $year, -2).'0';

        return $this->state(fn (array $attributes) => [
            'prefix' => $prefix,
            'enrollment_year' => $year,
        ]);
    }
}

