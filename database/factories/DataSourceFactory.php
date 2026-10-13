<?php

namespace Database\Factories;

use App\Enums\DashboardCategory;
use App\Enums\DataSourceMethod;
use App\Models\DataSource;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<DataSource>
 */
class DataSourceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = $this->faker->unique()->company().' '.$this->faker->randomElement(['Rates', 'Prices', 'Index', 'Feed']);

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'category' => $this->faker->randomElement(DashboardCategory::cases()),
            'method' => $this->faker->randomElement(DataSourceMethod::cases()),
            'url' => $this->faker->url(),
            'schedule_cron' => '30 18 * * 1-5',
            'is_active' => true,
            'config' => [],
        ];
    }

    public function manual(): static
    {
        return $this->state(fn (): array => ['method' => DataSourceMethod::Manual]);
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['is_active' => false]);
    }
}
