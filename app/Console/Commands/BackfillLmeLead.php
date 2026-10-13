<?php

namespace App\Console\Commands;

use App\Enums\ScraperRunStatus;
use App\Models\DataSource;
use App\Services\DataSources\Fetchers\WestmetallLeadFetcher;
use App\Services\DataSources\MarketDataPipeline;
use Illuminate\Console\Command;

/**
 * One-off history load for the LME Lead price chart - every trading day
 * Westmetall's table lists for the current year (Cash-Settlement,
 * 3-month and stock). Safe to re-run: every write is an upsert.
 */
class BackfillLmeLead extends Command
{
    /**
     * @var string
     */
    protected $signature = 'market:backfill-lme-lead';

    /**
     * @var string
     */
    protected $description = 'Backfill the LME Lead cash/3-month/stock history from Westmetall into indicator_values';

    public function handle(MarketDataPipeline $pipeline, WestmetallLeadFetcher $fetcher): int
    {
        $dataSource = DataSource::query()->where('slug', 'westmetall-lme-lead')->first();

        if (! $dataSource) {
            $this->error('No data source with slug [westmetall-lme-lead] found. Run the seeder first.');

            return self::FAILURE;
        }

        $run = $pipeline->runWithResult($dataSource, $fetcher->fetchHistory($dataSource));

        if ($run->status !== ScraperRunStatus::Success) {
            $this->error("Backfill failed: {$run->error}");

            return self::FAILURE;
        }

        $this->info("Backfilled {$run->records_upserted} LME Lead value(s).");

        return self::SUCCESS;
    }
}
