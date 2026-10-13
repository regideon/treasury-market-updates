<?php

namespace App\Console\Commands;

use App\Enums\ScraperRunStatus;
use App\Models\DataSource;
use App\Services\DataSources\Fetchers\PsaInflationFetcher;
use App\Services\DataSources\MarketDataPipeline;
use Illuminate\Console\Command;

/**
 * One-off historical backfill for headline inflation - pulled forward
 * from Module 3 for this one indicator, so the "Inflation Update" table
 * on the dashboard has real multi-year history to show instead of just
 * the latest month. Safe to re-run any time (every write is an upsert).
 */
class BackfillPsaInflation extends Command
{
    /**
     * @var string
     */
    protected $signature = 'market:backfill-inflation';

    /**
     * @var string
     */
    protected $description = 'Backfill every published month of PSA headline inflation into indicator_values';

    public function handle(MarketDataPipeline $pipeline, PsaInflationFetcher $fetcher): int
    {
        $dataSource = DataSource::query()->where('slug', 'psa-inflation')->first();

        if (! $dataSource) {
            $this->error('No data source with slug [psa-inflation] found. Run the seeder first.');

            return self::FAILURE;
        }

        $result = $fetcher->fetchHistory($dataSource);
        $run = $pipeline->runWithResult($dataSource, $result);

        if ($run->status !== ScraperRunStatus::Success) {
            $this->error("Backfill failed: {$run->error}");

            return self::FAILURE;
        }

        $this->info("Backfilled {$run->records_upserted} month(s) of inflation data.");

        return self::SUCCESS;
    }
}
