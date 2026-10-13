<?php

use App\Enums\DataSourceMethod;
use App\Models\DataSource;
use App\Services\DataSources\Fetchers\WestmetallLeadFetcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

/**
 * Uses a real Westmetall LME Lead table captured live on Sep 21, 2026
 * (tests/Fixtures/westmetall-lme-lead.html): the 2026 trading days up
 * to Fri 18 Sep, newest first.
 */
function fakeWestmetallPage(): void
{
    Http::fake(['www.westmetall.com/*' => Http::response(file_get_contents(__DIR__.'/../Fixtures/westmetall-lme-lead.html'), 200)]);
}

function makeWestmetallSource(): DataSource
{
    return DataSource::factory()->create([
        'method' => DataSourceMethod::File,
        'url' => 'https://www.westmetall.com/en/markdaten.php?action=table&field=LME_Pb_cash',
    ]);
}

test('fetch returns only the latest trading day: cash, 3-month and stock', function () {
    fakeWestmetallPage();

    $result = (new WestmetallLeadFetcher)->fetch(makeWestmetallSource());
    $byCode = collect($result->values)->keyBy('code');

    expect($result->values)->toHaveCount(3);
    expect($byCode['lme_lead_cash'])->toMatchArray(['as_of_date' => '2026-09-18', 'value' => 1868.0]);
    expect($byCode['lme_lead_3m']['value'])->toBe(1911.0);
    expect($byCode['lme_lead_stock']['value'])->toBe(373625.0);
    expect($result->httpStatus)->toBe(200);
});

test('fetchHistory returns every trading day on the page, three values each', function () {
    fakeWestmetallPage();

    $result = (new WestmetallLeadFetcher)->fetchHistory(makeWestmetallSource());
    $cash = collect($result->values)->where('code', 'lme_lead_cash');

    expect($cash->count())->toBeGreaterThan(100);
    expect($result->values)->toHaveCount($cash->count() * 3);
    expect($cash->first())->toMatchArray(['as_of_date' => '2026-09-18', 'value' => 1868.0]);
    expect($cash->last())->toMatchArray(['as_of_date' => '2026-01-02', 'value' => 1968.0]);
});

test('it throws when the HTTP request fails', function () {
    Http::fake(['www.westmetall.com/*' => Http::response('Nope', 503)]);

    (new WestmetallLeadFetcher)->fetch(makeWestmetallSource());
})->throws(RequestException::class);

test('it throws when no price rows are on the page', function () {
    Http::fake(['www.westmetall.com/*' => Http::response('<html><body><table><tr><td>nothing</td></tr></table></body></html>', 200)]);

    (new WestmetallLeadFetcher)->fetch(makeWestmetallSource());
})->throws(RuntimeException::class, 'Could not find any LME Lead rows');

test('it throws when the data source has no URL configured', function () {
    $dataSource = DataSource::factory()->create(['method' => DataSourceMethod::File, 'url' => null]);

    (new WestmetallLeadFetcher)->fetch($dataSource);
})->throws(RuntimeException::class, 'has no URL configured');
