<?php

use App\Models\DataSource;
use App\Models\IndicatorValue;
use App\Models\MarketIndicator;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('the market indicators admin page renders and lists indicators with their latest value', function () {
    $indicator = MarketIndicator::factory()
        ->for(DataSource::factory(), 'dataSource')
        ->create(['label' => 'ON Lending Rate', 'unit' => '%']);

    IndicatorValue::factory()->for($indicator, 'indicator')->forDate('2026-09-17')->create(['value' => 5.5]);

    $response = $this->actingAs(User::factory()->create())->get('/rpanel/market-indicators');

    $response->assertOk();
    $response->assertSee('ON Lending Rate');
    $response->assertSee('5.5%');
});

test('an indicator with no value yet shows a friendly placeholder instead of breaking', function () {
    MarketIndicator::factory()->for(DataSource::factory(), 'dataSource')->create(['label' => 'Brand New Indicator']);

    $response = $this->actingAs(User::factory()->create())->get('/rpanel/market-indicators');

    $response->assertOk();
    $response->assertSee('Brand New Indicator');
    $response->assertSee('No data yet');
});

test('the Method column shows the data source URL as a tooltip', function () {
    $url = 'https://www.bsp.gov.ph/Statistics/RERB/RERB.xlsx';
    $dataSource = DataSource::factory()->create(['url' => $url]);
    MarketIndicator::factory()->for($dataSource, 'dataSource')->create();

    $response = $this->actingAs(User::factory()->create())->get('/rpanel/market-indicators');

    $response->assertOk();
    // Filament embeds the tooltip text as JSON for Alpine (x-tooltip="{
    // content: '...' }"), which escapes "/" as "\/" - assert against
    // that exact encoded form rather than the raw URL.
    $response->assertSee(str_replace('/', '\/', $url), false);
});

test('the Method column also shows the data source\'s plain-language description, when set', function () {
    $dataSource = DataSource::factory()->create([
        'url' => 'https://www.bsp.gov.ph/Statistics/RERB/RERB.xlsx',
        'description' => 'Small Excel file BSP republishes every banking day.',
    ]);
    MarketIndicator::factory()->for($dataSource, 'dataSource')->create();

    $response = $this->actingAs(User::factory()->create())->get('/rpanel/market-indicators');

    $response->assertOk();
    $response->assertSee('Small Excel file BSP republishes every banking day.', false);
});

test('the Method column falls back to a friendly note when a manual source has no URL', function () {
    $dataSource = DataSource::factory()->manual()->create(['url' => null]);
    MarketIndicator::factory()->for($dataSource, 'dataSource')->create();

    $response = $this->actingAs(User::factory()->create())->get('/rpanel/market-indicators');

    $response->assertOk();
    $response->assertSee('No URL configured - entered manually.', false);
});
