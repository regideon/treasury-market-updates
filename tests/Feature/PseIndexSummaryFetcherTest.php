<?php

use App\Enums\DataSourceMethod;
use App\Models\DataSource;
use App\Services\DataSources\Fetchers\PseIndexSummaryFetcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

/**
 * Uses a real edge.pse.com.ph/index/form.do page, captured live
 * (tests/Fixtures/pse-index-summary-sample.html) mid-session on
 * Sep 21, 2026 - "MARKET : RECESS", PSEi 5,850.82 down 5.09.
 */
function fakePseResponse(?callable $transform = null): void
{
    $html = file_get_contents(__DIR__.'/../Fixtures/pse-index-summary-sample.html');

    Http::fake(['edge.pse.com.ph/*' => Http::response($transform ? $transform($html) : $html, 200)]);
}

function makePseDataSource(): DataSource
{
    return DataSource::factory()->create([
        'method' => DataSourceMethod::File,
        'url' => 'https://edge.pse.com.ph/index/form.do',
    ]);
}

test('it reads every index level and its signed change, using the arrow for direction', function () {
    fakePseResponse();

    $result = (new PseIndexSummaryFetcher)->fetch(makePseDataSource());
    $byCode = collect($result->values)->keyBy('code');

    expect($byCode['psei']['value'])->toBe(5850.82);
    expect($byCode['psei_chg']['value'])->toBe(-5.09);
    expect($byCode['pse_financials']['value'])->toBe(1821.31);
    expect($byCode['pse_financials_chg']['value'])->toBe(0.68);
    expect($byCode['pse_industrial_chg']['value'])->toBe(-110.83);
    expect($byCode['pse_holding_firms_chg']['value'])->toBe(43.48);
    expect($byCode['pse_mining_oil']['value'])->toBe(21715.33);
    expect($byCode['pse_property_chg']['value'])->toBe(-10.18);
    expect($byCode['pse_all_shares']['value'])->toBe(3269.01);
    expect($byCode['pse_services']['value'])->toBe(3162.15);
});

test('it reads turnover, breadth, market status and the as-of date and time', function () {
    fakePseResponse();

    $result = (new PseIndexSummaryFetcher)->fetch(makePseDataSource());
    $byCode = collect($result->values)->keyBy('code');

    expect($byCode['pse_total_volume']['value'])->toBe(1303496769.0);
    expect($byCode['pse_total_trades']['value'])->toBe(57118.0);
    expect($byCode['pse_total_value']['value'])->toBe(3610613363.0);
    expect($byCode['pse_advances']['value'])->toBe(71.0);
    expect($byCode['pse_declines']['value'])->toBe(106.0);
    expect($byCode['pse_unchanged']['value'])->toBe(61.0);
    expect($byCode['pse_market_status'])->toMatchArray(['value_text' => 'RECESS', 'as_of_date' => '2026-09-21', 'as_of_time' => '12:55:00']);
    expect($result->values)->toHaveCount(23);
    expect($result->httpStatus)->toBe(200);
});

test('it reads the final CLOSED status the same way', function () {
    fakePseResponse(fn (string $html): string => str_replace('MARKET : RECESS', 'MARKET : CLOSED', $html));

    $result = (new PseIndexSummaryFetcher)->fetch(makePseDataSource());

    expect(collect($result->values)->firstWhere('code', 'pse_market_status')['value_text'])->toBe('CLOSED');
});

test('an unchanged index gets a zero change', function () {
    fakePseResponse(fn (string $html): string => str_replace('0.04▲', '0.00', $html));

    $result = (new PseIndexSummaryFetcher)->fetch(makePseDataSource());

    expect(collect($result->values)->firstWhere('code', 'pse_financials_chg')['value'])->toBe(0.0);
});

test('it throws when the HTTP request fails', function () {
    Http::fake(['edge.pse.com.ph/*' => Http::response('Forbidden', 403)]);

    (new PseIndexSummaryFetcher)->fetch(makePseDataSource());
})->throws(RequestException::class);

test('it throws when the data source has no URL configured', function () {
    $dataSource = DataSource::factory()->create(['method' => DataSourceMethod::File, 'url' => null]);

    (new PseIndexSummaryFetcher)->fetch($dataSource);
})->throws(RuntimeException::class, 'has no URL configured');

test('it throws when the index table is missing from the page', function () {
    Http::fake(['edge.pse.com.ph/*' => Http::response('<html><body>Not the page we expected</body></html>', 200)]);

    (new PseIndexSummaryFetcher)->fetch(makePseDataSource());
})->throws(RuntimeException::class, 'Could not find the PSE Edge index summary table');

test('it throws when an expected index row is missing', function () {
    fakePseResponse(fn (string $html): string => str_replace('Holding Firms', 'Renamed Sector', $html));

    (new PseIndexSummaryFetcher)->fetch(makePseDataSource());
})->throws(RuntimeException::class, 'missing: pse_holding_firms');
