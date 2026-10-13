<?php

namespace Database\Factories;

use App\Models\CurvePoint;
use App\Models\MarketIndicator;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CurvePoint>
 */
class CurvePointFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'market_indicator_id' => MarketIndicator::factory(),
            'scraper_run_id' => null,
            'as_of_date' => $this->faker->dateTimeBetween('-1 year', 'now')->format('Y-m-d'),
            'point_key' => $this->faker->randomElement(['1m', '3m', '6m', '1y', '5y', '10y']),
            'point_label' => null,
            'value' => $this->faker->randomFloat(4, 1, 10),
            'bid' => null,
            'offer' => null,
            'sort_order' => 0,
        ];
    }
}
