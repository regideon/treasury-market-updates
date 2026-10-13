<?php

namespace Database\Factories;

use App\Enums\DashboardCategory;
use App\Models\DataSource;
use App\Models\MarketIndicator;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<MarketIndicator>
 */
class MarketIndicatorFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $label = $this->faker->unique()->words(3, true);

        return [
            'data_source_id' => DataSource::factory(),
            'code' => Str::slug($label, '_'),
            'label' => Str::title($label),
            'unit' => $this->faker->randomElement(['PHP', 'USD', '%', 'index', 'USD/bbl', 'USD/oz']),
            'category' => $this->faker->randomElement(DashboardCategory::cases()),
            'display_order' => $this->faker->numberBetween(0, 20),
            'metadata' => [],
        ];
    }
}
