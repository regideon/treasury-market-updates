<?php

use App\Enums\DashboardCategory;
use App\Enums\DataSourceMethod;
use App\Models\DataSource;
use App\Models\IndicatorValue;
use App\Models\MarketIndicator;
use App\Services\DataSources\Fetchers\PsaGdpGrowthFetcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

function fakePsaGdpFullHistoryResponsesForBackfill(): void
{
    $metadata = file_get_contents(__DIR__.'/../Fixtures/psa-gdp-metadata.json');
    $data = file_get_contents(__DIR__.'/../Fixtures/psa-gdp-data-full-history.json');

    Http::fake(function (Request $request) use ($metadata, $data) {
        return $request->method() === 'GET'
            ? Http::response($metadata, 200)
            : Http::response($data, 200);
    });
}

function makeGdpDataSourceAndIndicator(): MarketIndicator
{
    $dataSource = DataSource::factory()->create([
        'slug' => 'psa-gdp',
        'method' => DataSourceMethod::Api,
        'url' => 'https://openstat.psa.gov.ph/PXWeb/api/v1/en/DB/2B/NA/QT/1SUM/0022B5BEXQ2.px',
        'config' => ['fetcher' => PsaGdpGrowthFetcher::class],
    ]);

    return MarketIndicator::factory()->for($dataSource, 'dataSource')->create([
        'code' => 'gdp_growth_rate',
        'category' => DashboardCategory::Macro,
    ]);
}

test('it upserts every published quarter into indicator_values', function () {
    fakePsaGdpFullHistoryResponsesForBackfill();
    $indicator = makeGdpDataSourceAndIndicator();

    $this->artisan('market:backfill-gdp')->assertSuccessful();

    expect(IndicatorValue::where('market_indicator_id', $indicator->id)->count())->toBe(102);

    $oldest = IndicatorValue::where('market_indicator_id', $indicator->id)->orderBy('as_of_date')->first();
    $newest = IndicatorValue::where('market_indicator_id', $indicator->id)->orderByDesc('as_of_date')->first();

    expect($oldest->as_of_date->toDateString())->toBe('2001-03-31');
    expect((float) $oldest->value)->toBe(2.4);
    expect($newest->as_of_date->toDateString())->toBe('2026-06-30');
    expect((float) $newest->value)->toBe(2.3);
});

test('running it twice does not duplicate rows', function () {
    fakePsaGdpFullHistoryResponsesForBackfill();
    $indicator = makeGdpDataSourceAndIndicator();

    $this->artisan('market:backfill-gdp')->assertSuccessful();
    $this->artisan('market:backfill-gdp')->assertSuccessful();

    expect(IndicatorValue::where('market_indicator_id', $indicator->id)->count())->toBe(102);
});

test('it fails clearly when the psa-gdp data source is missing', function () {
    $this->artisan('market:backfill-gdp')->assertFailed();
});
