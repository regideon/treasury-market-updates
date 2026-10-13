<?php

namespace Database\Factories;

use App\Models\IndicatorValue;
use App\Models\MarketIndicator;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<IndicatorValue>
 */
class IndicatorValueFactory extends Factory
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
            'value' => $this->faker->randomFloat(4, 1, 10000),
            'value_text' => null,
            'as_of_date' => $this->faker->dateTimeBetween('-1 year', 'now')->format('Y-m-d'),
            'as_of_time' => null,
            'period_label' => null,
            'source_note' => null,
        ];
    }

    public function forDate(string $date): static
    {
        return $this->state(fn (): array => ['as_of_date' => $date]);
    }
}
