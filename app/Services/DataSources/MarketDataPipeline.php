<?php

namespace App\Services\DataSources;

use App\Enums\DataSourceMethod;
use App\Enums\ScraperRunStatus;
use App\Models\CurvePoint;
use App\Models\DataSource;
use App\Models\IndicatorValue;
use App\Models\MarketIndicator;
use App\Models\ScraperRun;
use App\Services\DataSources\Contracts\SourceFetcher;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

/**
 * Runs one data source through its {@see SourceFetcher}, records the
 * attempt as a {@see ScraperRun}, and upserts whatever values/curve
 * points it returned. This is the one place that touches
 * `indicator_values` and `curve_points` on write, so every source -
 * whatever method it uses - goes through the same validation and
 * logging.
 */
class MarketDataPipeline
{
    /**
     * Run a single data source. Returns null for manual sources, since
     * those have nothing to automate - their values are entered
     * directly in the admin instead.
     */
    public function runForSource(DataSource $dataSource): ?ScraperRun
    {
        if ($dataSource->method === DataSourceMethod::Manual) {
            return null;
        }

        return $this->runWithResolver(
            $dataSource,
            fn (): FetchResult => $this->resolveFetcher($dataSource)->fetch($dataSource),
        );
    }

    /**
     * Records and upserts an already-fetched {@see FetchResult}, exactly
     * like {@see runForSource()} does - for callers (e.g. a one-off
     * historical backfill command) that build the result themselves
     * instead of going through a data source's registered fetcher.
     */
    public function runWithResult(DataSource $dataSource, FetchResult $result): ScraperRun
    {
        return $this->runWithResolver($dataSource, fn (): FetchResult => $result);
    }

    /**
     * @param  callable(): FetchResult  $resolveResult
     */
    private function runWithResolver(DataSource $dataSource, callable $resolveResult): ScraperRun
    {
        $run = ScraperRun::create([
            'data_source_id' => $dataSource->id,
            'started_at' => now(),
            'status' => ScraperRunStatus::Pending,
        ]);

        try {
            $result = $resolveResult();
            $recordsUpserted = $this->upsert($dataSource, $run, $result);

            $run->update([
                'finished_at' => now(),
                'status' => ScraperRunStatus::Success,
                'http_status' => $result->httpStatus,
                'records_upserted' => $recordsUpserted,
                'raw_snapshot_path' => $this->storeSnapshot($dataSource, $run, $result),
            ]);
        } catch (Throwable $exception) {
            $run->update([
                'finished_at' => now(),
                'status' => ScraperRunStatus::Failed,
                'error' => $exception->getMessage(),
            ]);
        }

        return $run->fresh();
    }

    private function resolveFetcher(DataSource $dataSource): SourceFetcher
    {
        $fetcherClass = $dataSource->config['fetcher'] ?? null;

        if (! $fetcherClass || ! class_exists($fetcherClass)) {
            throw new RuntimeException("No fetcher registered for data source [{$dataSource->slug}]. Set config->fetcher to a SourceFetcher class.");
        }

        $fetcher = app($fetcherClass);

        if (! $fetcher instanceof SourceFetcher) {
            throw new RuntimeException("[{$fetcherClass}] does not implement ".SourceFetcher::class);
        }

        return $fetcher;
    }

    private function upsert(DataSource $dataSource, ScraperRun $run, FetchResult $result): int
    {
        $indicators = MarketIndicator::query()
            ->where('data_source_id', $dataSource->id)
            ->get()
            ->keyBy('code');

        $count = 0;

        foreach ($result->values as $row) {
            $indicator = $indicators->get($row['code']);

            if (! $indicator) {
                continue;
            }

            IndicatorValue::updateOrCreate(
                [
                    'market_indicator_id' => $indicator->id,
                    'as_of_date' => $row['as_of_date'],
                ],
                [
                    'scraper_run_id' => $run->id,
                    'value' => $row['value'] ?? null,
                    'value_text' => $row['value_text'] ?? null,
                    'as_of_time' => $row['as_of_time'] ?? null,
                    'period_label' => $row['period_label'] ?? null,
                    'source_note' => $row['source_note'] ?? null,
                ],
            );

            $count++;
        }

        foreach ($result->curvePoints as $row) {
            $indicator = $indicators->get($row['code']);

            if (! $indicator) {
                continue;
            }

            CurvePoint::updateOrCreate(
                [
                    'market_indicator_id' => $indicator->id,
                    'as_of_date' => $row['as_of_date'],
                    'point_key' => $row['point_key'],
                ],
                [
                    'scraper_run_id' => $run->id,
                    'point_label' => $row['point_label'] ?? null,
                    'value' => $row['value'] ?? null,
                    'bid' => $row['bid'] ?? null,
                    'offer' => $row['offer'] ?? null,
                    'sort_order' => $row['sort_order'] ?? 0,
                ],
            );

            $count++;
        }

        return $count;
    }

    private function storeSnapshot(DataSource $dataSource, ScraperRun $run, FetchResult $result): ?string
    {
        if ($result->rawSnapshot === null) {
            return null;
        }

        $path = "scraper-snapshots/{$dataSource->slug}/{$run->id}.snapshot";

        Storage::disk('local')->put($path, $result->rawSnapshot);

        return $path;
    }
}
