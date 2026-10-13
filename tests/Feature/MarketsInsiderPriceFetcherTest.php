<?php

use App\Enums\DataSourceMethod;
use App\Models\DataSource;
use App\Services\DataSources\Fetchers\MarketsInsiderPriceFetcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

/**
 * Uses real Markets Insider pages captured live on Sep 21, 2026
 * (tests/Fixtures/mi-*-price.html). The wheat page carried a full
 * "02:15:00 PM EDT 9/18/2026" timestamp; gold and Brent carried only a
 * time, no date - both shapes matter.
 */
function fakeMarketsInsiderPage(string $fixture): void
{
    Http::fake(['markets.businessinsider.com/*' => Http::response(file_get_contents(__DIR__.'/../Fixtures/'.$fixture), 200)]);
}

function makeMarketsInsiderSource(string $code, string $path): DataSource
{
    return DataSource::factory()->create([
        'method' => DataSourceMethod::Scrape,
        'url' => "https://markets.businessinsider.com/commodities/{$path}",
        'config' => ['indicator_code' => $code],
    ]);
}

test('it reads the price and derives the exact previous close from the stated change', function () {
    fakeMarketsInsiderPage('mi-gold-price.html');

    $result = (new MarketsInsiderPriceFetcher)->fetch(makeMarketsInsiderSource('gold_spot', 'gold-price'));
    $byCode = collect($result->values)->keyBy('code');

    expect($byCode['gold_spot']['value'])->toBe(4354.45);
    // 4354.45 - (-25.6) = 4380.05, matching the page's own "Prev. Close".
    expect($byCode['gold_spot_prev']['value'])->toBe(4380.05);
    expect($result->httpStatus)->toBe(200);
});

test('a dated timestamp is used as-is, and kept in the note', function () {
    fakeMarketsInsiderPage('mi-wheat-price.html');

    $result = (new MarketsInsiderPriceFetcher)->fetch(makeMarketsInsiderSource('wheat_price', 'wheat-price'));
    $byCode = collect($result->values)->keyBy('code');

    expect($byCode['wheat_price'])->toMatchArray([
        'as_of_date' => '2026-09-18',
        'value' => 277.3356,
    ]);
    expect($byCode['wheat_price']['source_note'])->toContain('MI Indication')->toContain('02:15:00 PM EDT 9/18/2026');
    expect($byCode['wheat_price_prev']['value'])->toBe(280.1852);
});

test('a time-only timestamp is treated as today', function () {
    Carbon::setTestNow('2026-09-16 10:00:00'); // a Wednesday
    fakeMarketsInsiderPage('mi-brent-price.html');

    $result = (new MarketsInsiderPriceFetcher)->fetch(makeMarketsInsiderSource('brent_crude', 'oil-price'));

    expect(collect($result->values)->firstWhere('code', 'brent_crude'))->toMatchArray(['as_of_date' => '2026-09-16', 'value' => 101.68]);
    expect(collect($result->values)->firstWhere('code', 'brent_crude_prev')['value'])->toBe(103.87);

    Carbon::setTestNow();
});

test('a time-only timestamp on a weekend is rolled back to Friday', function () {
    Carbon::setTestNow('2026-09-20 10:00:00'); // a Sunday
    fakeMarketsInsiderPage('mi-brent-price.html');

    $result = (new MarketsInsiderPriceFetcher)->fetch(makeMarketsInsiderSource('brent_crude', 'oil-price'));

    expect(collect($result->values)->firstWhere('code', 'brent_crude')['as_of_date'])->toBe('2026-09-18');

    Carbon::setTestNow();
});

test('it throws when the HTTP request fails', function () {
    Http::fake(['markets.businessinsider.com/*' => Http::response('Forbidden', 403)]);

    (new MarketsInsiderPriceFetcher)->fetch(makeMarketsInsiderSource('gold_spot', 'gold-price'));
})->throws(RequestException::class);

test('it throws when the data source has no URL or no indicator code', function () {
    $noUrl = DataSource::factory()->create(['method' => DataSourceMethod::Scrape, 'url' => null, 'config' => ['indicator_code' => 'x']]);
    expect(fn () => (new MarketsInsiderPriceFetcher)->fetch($noUrl))->toThrow(RuntimeException::class, 'has no URL configured');

    $noCode = DataSource::factory()->create(['method' => DataSourceMethod::Scrape, 'url' => 'https://markets.businessinsider.com/x', 'config' => []]);
    expect(fn () => (new MarketsInsiderPriceFetcher)->fetch($noCode))->toThrow(RuntimeException::class, 'indicator_code');
});

test('it throws when the price is missing from the page', function () {
    Http::fake(['markets.businessinsider.com/*' => Http::response('<html><body>Not the page we expected</body></html>', 200)]);

    (new MarketsInsiderPriceFetcher)->fetch(makeMarketsInsiderSource('gold_spot', 'gold-price'));
})->throws(RuntimeException::class, 'Could not read [price-section__current-value]');
