<?php

use App\Enums\DashboardCategory;
use App\Enums\DataSourceMethod;
use App\Models\DataSource;
use App\Models\IndicatorValue;
use App\Models\MarketIndicator;
use App\Services\DataSources\Fetchers\WestmetallLeadFetcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

function seedLmeLeadSourceWithIndicators(): array
{
    $dataSource = DataSource::factory()->create([
        'slug' => 'westmetall-lme-lead',
        'method' => DataSourceMethod::File,
        'url' => 'https://www.westmetall.com/en/markdaten.php?action=table&field=LME_Pb_cash',
        'config' => ['fetcher' => WestmetallLeadFetcher::class],
    ]);

    return collect(['lme_lead_cash', 'lme_lead_3m', 'lme_lead_stock'])
        ->mapWithKeys(fn (string $code): array => [$code => MarketIndicator::factory()->for($dataSource, 'dataSource')->create(['code' => $code, 'category' => DashboardCategory::Commodities])])
        ->all();
}

test('it upserts every trading day of cash, 3-month and stock', function () {
    Http::fake(['www.westmetall.com/*' => Http::response(file_get_contents(__DIR__.'/../Fixtures/westmetall-lme-lead.html'), 200)]);
    $indicators = seedLmeLeadSourceWithIndicators();

    $this->artisan('market:backfill-lme-lead')->assertSuccessful();

    $cashRows = IndicatorValue::where('market_indicator_id', $indicators['lme_lead_cash']->id);
    expect($cashRows->count())->toBe(181);
    expect((float) $cashRows->orderByDesc('as_of_date')->first()->value)->toBe(1868.0);
    expect(IndicatorValue::where('market_indicator_id', $indicators['lme_lead_3m']->id)->count())->toBe(181);
    expect(IndicatorValue::where('market_indicator_id', $indicators['lme_lead_stock']->id)->count())->toBe(181);
});

test('running it twice does not duplicate rows', function () {
    Http::fake(['www.westmetall.com/*' => Http::response(file_get_contents(__DIR__.'/../Fixtures/westmetall-lme-lead.html'), 200)]);
    $indicators = seedLmeLeadSourceWithIndicators();

    $this->artisan('market:backfill-lme-lead')->assertSuccessful();
    $this->artisan('market:backfill-lme-lead')->assertSuccessful();

    expect(IndicatorValue::where('market_indicator_id', $indicators['lme_lead_cash']->id)->count())->toBe(181);
});

test('it fails clearly when the data source is missing', function () {
    $this->artisan('market:backfill-lme-lead')->assertFailed();
});
