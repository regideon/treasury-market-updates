<?php

use App\Enums\DataSourceMethod;
use App\Models\DataSource;
use App\Services\DataSources\Fetchers\BapFxSummaryFetcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

/**
 * Uses a real bap.org.ph fxhistorical.html response, captured live
 * (tests/Fixtures/bap-fxhistorical-sample.html) for September 2026. Its
 * last row - Sep 16, 2026 - is the one this fetcher should read.
 */
function fakeBapResponse(): void
{
    $html = file_get_contents(__DIR__.'/../Fixtures/bap-fxhistorical-sample.html');

    Http::fake(['bap.org.ph/*' => Http::response($html, 200)]);
}

function makeBapDataSource(): DataSource
{
    return DataSource::factory()->create([
        'method' => DataSourceMethod::File,
        'url' => 'https://www.bap.org.ph/fxhistorical.html',
    ]);
}

test('it reads the last row of the FX history table as the current banking day', function () {
    fakeBapResponse();

    $result = (new BapFxSummaryFetcher)->fetch(makeBapDataSource());

    expect($result->values)->toHaveCount(12);
    expect($result->httpStatus)->toBe(200);

    $byCode = collect($result->values)->keyBy('code');

    expect($byCode['bap_usdphp_open_time'])->toMatchArray([
        'as_of_date' => '2026-09-16',
        'value_text' => '09:00:01',
    ]);
    expect($byCode['bap_usdphp_open']['value'])->toBe(62.8);
    expect($byCode['bap_usdphp_high']['value'])->toBe(62.8);
    expect($byCode['bap_usdphp_low']['value'])->toBe(62.68);
    expect($byCode['bap_usdphp_close']['value'])->toBe(62.739);
    expect($byCode['bap_usdphp_am_wa']['value'])->toBe(62.737);
    expect($byCode['bap_usdphp_am_volume']['value'])->toBe(709.5);
    expect($byCode['bap_usdphp_pm_wa']['value'])->toBe(62.727);
    expect($byCode['bap_usdphp_pm_volume']['value'])->toBe(639.45);
    expect($byCode['bap_usdphp_wa']['value'])->toBe(62.732);
    expect($byCode['bap_usdphp_total_volume']['value'])->toBe(1348.95);
    expect($byCode['bap_usdphp_settlement']['value'])->toBe(62.737);

    foreach ($byCode as $row) {
        expect($row['as_of_date'])->toBe('2026-09-16');
    }
});

test('it requests the current year/month page', function () {
    fakeBapResponse();

    (new BapFxSummaryFetcher)->fetch(makeBapDataSource());

    Http::assertSent(function (Request $request): bool {
        $query = [];
        parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

        return $query['base'] === 'USD' && $query['quote'] === 'PHP';
    });
});

test('it throws when the HTTP request fails', function () {
    Http::fake(['bap.org.ph/*' => Http::response('Access Denied', 403)]);

    (new BapFxSummaryFetcher)->fetch(makeBapDataSource());
})->throws(RequestException::class);

test('it throws when the data source has no URL configured', function () {
    $dataSource = DataSource::factory()->create(['method' => DataSourceMethod::File, 'url' => null]);

    (new BapFxSummaryFetcher)->fetch($dataSource);
})->throws(RuntimeException::class, 'has no URL configured');

test('it throws when the expected table is missing from the page', function () {
    Http::fake(['bap.org.ph/*' => Http::response('<html><body>Not the page we expected</body></html>', 200)]);

    (new BapFxSummaryFetcher)->fetch(makeBapDataSource());
})->throws(RuntimeException::class, 'Could not find the BAP FX history table');
