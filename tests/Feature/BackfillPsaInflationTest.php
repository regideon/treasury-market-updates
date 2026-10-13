<?php

use App\Enums\DashboardCategory;
use App\Enums\DataSourceMethod;
use App\Models\DataSource;
use App\Models\IndicatorValue;
use App\Models\MarketIndicator;
use App\Services\DataSources\Fetchers\PsaInflationFetcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

function fakePsaFullHistoryResponsesForBackfill(): void
{
    $metadata = file_get_contents(__DIR__.'/../Fixtures/psa-cpi-metadata.json');
    $data = file_get_contents(__DIR__.'/../Fixtures/psa-cpi-full-history-data.json');

    Http::fake(function (Request $request) use ($metadata, $data) {
        return $request->method() === 'GET'
            ? Http::response($metadata, 200)
            : Http::response($data, 200);
    });
}

function makeInflationDataSourceAndIndicator(): MarketIndicator
{
    $dataSource = DataSource::factory()->create([
        'slug' => 'psa-inflation',
        'method' => DataSourceMethod::Api,
        'url' => 'https://openstat.psa.gov.ph/PXWeb/api/v1/en/DB/2M/PI/CPI/2018NEW/0012M4ACP23.px',
        'config' => ['fetcher' => PsaInflationFetcher::class],
    ]);

    return MarketIndicator::factory()->for($dataSource, 'dataSource')->create([
        'code' => 'inflation_rate',
        'category' => DashboardCategory::Macro,
    ]);
}

test('it upserts every published month into indicator_values', function () {
    fakePsaFullHistoryResponsesForBackfill();
    $indicator = makeInflationDataSourceAndIndicator();

    $this->artisan('market:backfill-inflation')->assertSuccessful();

    expect(IndicatorValue::where('market_indicator_id', $indicator->id)->count())->toBe(92);

    $oldest = IndicatorValue::where('market_indicator_id', $indicator->id)->orderBy('as_of_date')->first();
    $newest = IndicatorValue::where('market_indicator_id', $indicator->id)->orderByDesc('as_of_date')->first();

    expect($oldest->as_of_date->toDateString())->toBe('2019-01-31');
    expect((float) $oldest->value)->toBe(4.4);
    expect($newest->as_of_date->toDateString())->toBe('2026-08-31');
    expect((float) $newest->value)->toBe(6.1);
});

test('running it twice does not duplicate rows', function () {
    fakePsaFullHistoryResponsesForBackfill();
    $indicator = makeInflationDataSourceAndIndicator();

    $this->artisan('market:backfill-inflation')->assertSuccessful();
    $this->artisan('market:backfill-inflation')->assertSuccessful();

    expect(IndicatorValue::where('market_indicator_id', $indicator->id)->count())->toBe(92);
});

test('it fails clearly when the psa-inflation data source is missing', function () {
    $this->artisan('market:backfill-inflation')->assertFailed();
});
