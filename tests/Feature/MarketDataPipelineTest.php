<?php

use App\Enums\DataSourceMethod;
use App\Enums\ScraperRunStatus;
use App\Models\DataSource;
use App\Models\IndicatorValue;
use App\Models\MarketIndicator;
use App\Models\ScraperRun;
use App\Services\DataSources\Contracts\SourceFetcher;
use App\Services\DataSources\FetchResult;
use App\Services\DataSources\MarketDataPipeline;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * A fetcher stub that always returns one fixed reading, for exercising
 * the pipeline without touching any real HTTP/file source.
 */
class FakeFixedRateFetcher implements SourceFetcher
{
    public function fetch(DataSource $dataSource): FetchResult
    {
        return new FetchResult(
            values: [
                ['code' => 'test_usdphp', 'as_of_date' => '2026-08-25', 'value' => 61.735],
            ],
            rawSnapshot: 'raw response body',
        );
    }
}

class FakeThrowingFetcher implements SourceFetcher
{
    public function fetch(DataSource $dataSource): FetchResult
    {
        throw new RuntimeException('The upstream site is down.');
    }
}

test('a manual data source is skipped without creating a run', function () {
    $source = DataSource::factory()->manual()->create();

    $run = app(MarketDataPipeline::class)->runForSource($source);

    expect($run)->toBeNull();
    expect(ScraperRun::count())->toBe(0);
});

test('a source with no registered fetcher fails clearly', function () {
    $source = DataSource::factory()->create([
        'method' => DataSourceMethod::File,
        'config' => [],
    ]);

    $run = app(MarketDataPipeline::class)->runForSource($source);

    expect($run->status)->toBe(ScraperRunStatus::Failed);
    expect($run->error)->toContain('No fetcher registered');
});

test('a working fetcher upserts indicator values and logs a successful run', function () {
    $source = DataSource::factory()->create([
        'method' => DataSourceMethod::File,
        'config' => ['fetcher' => FakeFixedRateFetcher::class],
    ]);
    $indicator = MarketIndicator::factory()->for($source, 'dataSource')->create(['code' => 'test_usdphp']);

    $run = app(MarketDataPipeline::class)->runForSource($source);

    expect($run->status)->toBe(ScraperRunStatus::Success);
    expect($run->records_upserted)->toBe(1);
    expect($run->raw_snapshot_path)->not->toBeNull();

    $value = IndicatorValue::where('market_indicator_id', $indicator->id)->first();
    expect((float) $value->value)->toBe(61.735);
    expect($value->scraper_run_id)->toBe($run->id);
    expect($source->fresh()->latestRun->id)->toBe($run->id);
});

test('running the same source again for the same date updates instead of duplicating', function () {
    $source = DataSource::factory()->create([
        'method' => DataSourceMethod::File,
        'config' => ['fetcher' => FakeFixedRateFetcher::class],
    ]);
    MarketIndicator::factory()->for($source, 'dataSource')->create(['code' => 'test_usdphp']);

    $pipeline = app(MarketDataPipeline::class);
    $firstRun = $pipeline->runForSource($source);
    $secondRun = $pipeline->runForSource($source);

    // Assert both runs actually succeeded, not just that the row count
    // stayed at 1 - a second run that silently failed (e.g. an
    // as_of_date match bug) would also leave the count at 1, for the
    // wrong reason.
    expect($firstRun->status)->toBe(ScraperRunStatus::Success);
    expect($secondRun->status)->toBe(ScraperRunStatus::Success);
    expect(IndicatorValue::count())->toBe(1);
});

test('a fetcher that throws is recorded as a failed run', function () {
    $source = DataSource::factory()->create([
        'method' => DataSourceMethod::Scrape,
        'config' => ['fetcher' => FakeThrowingFetcher::class],
    ]);

    $run = app(MarketDataPipeline::class)->runForSource($source);

    expect($run->status)->toBe(ScraperRunStatus::Failed);
    expect($run->error)->toBe('The upstream site is down.');
});
