<?php

namespace App\Console\Commands;

use App\Enums\ScraperRunStatus;
use App\Models\DataSource;
use App\Services\DataSources\Fetchers\PsaGdpGrowthFetcher;
use App\Services\DataSources\MarketDataPipeline;
use Illuminate\Console\Command;

/**
 * One-off historical backfill for real GDP growth - the same "pull
 * Module 3 forward for one indicator" move as `market:backfill-inflation`.
 * Safe to re-run any time (every write is an upsert).
 */
class BackfillPsaGdp extends Command
{
    /**
     * @var string
     */
    protected $signature = 'market:backfill-gdp';

    /**
     * @var string
     */
    protected $description = 'Backfill every published quarter of PSA real GDP growth into indicator_values';

    public function handle(MarketDataPipeline $pipeline, PsaGdpGrowthFetcher $fetcher): int
    {
        $dataSource = DataSource::query()->where('slug', 'psa-gdp')->first();

        if (! $dataSource) {
            $this->error('No data source with slug [psa-gdp] found. Run the seeder first.');

            return self::FAILURE;
        }

        $result = $fetcher->fetchHistory($dataSource);
        $run = $pipeline->runWithResult($dataSource, $result);

        if ($run->status !== ScraperRunStatus::Success) {
            $this->error("Backfill failed: {$run->error}");

            return self::FAILURE;
        }

        $this->info("Backfilled {$run->records_upserted} quarter(s) of GDP growth data.");

        return self::SUCCESS;
    }
}
