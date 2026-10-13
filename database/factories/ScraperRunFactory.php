<?php

namespace Database\Factories;

use App\Enums\ScraperRunStatus;
use App\Models\DataSource;
use App\Models\ScraperRun;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ScraperRun>
 */
class ScraperRunFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startedAt = $this->faker->dateTimeBetween('-1 month', 'now');

        return [
            'data_source_id' => DataSource::factory(),
            'started_at' => $startedAt,
            'finished_at' => (clone $startedAt)->modify('+5 seconds'),
            'status' => ScraperRunStatus::Success,
            'http_status' => 200,
            'records_upserted' => $this->faker->numberBetween(1, 5),
            'error' => null,
            'raw_snapshot_path' => null,
        ];
    }

    public function failed(): static
    {
        return $this->state(fn (): array => [
            'status' => ScraperRunStatus::Failed,
            'http_status' => $this->faker->randomElement([404, 500, 503]),
            'records_upserted' => 0,
            'error' => 'Connection timed out.',
        ]);
    }
}
