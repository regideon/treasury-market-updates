<?php

namespace App\Console\Commands;

use App\Enums\ScraperRunStatus;
use App\Models\DataSource;
use App\Services\DataSources\MarketDataPipeline;
use Illuminate\Console\Command;

class PullEndOfDayMarketData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'market:pull-eod {--source= : Only run the data source with this slug}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = "Fetch today's end-of-day values for every active data source and store them";

    public function handle(MarketDataPipeline $pipeline): int
    {
        $query = DataSource::query()->where('is_active', true);

        if ($slug = $this->option('source')) {
            $query->where('slug', $slug);
        }

        $sources = $query->orderBy('category')->get();

        if ($sources->isEmpty()) {
            $this->warn('No active data sources to run.');

            return self::SUCCESS;
        }

        foreach ($sources as $source) {
            $run = $pipeline->runForSource($source);

            if (! $run) {
                $this->line("- {$source->name}: skipped (manual source, no fetch to run)");

                continue;
            }

            if ($run->status === ScraperRunStatus::Success) {
                $this->info("- {$source->name}: OK ({$run->records_upserted} value(s))");
            } else {
                $this->error("- {$source->name}: {$run->status->getLabel()} - {$run->error}");
            }
        }

        return self::SUCCESS;
    }
}
