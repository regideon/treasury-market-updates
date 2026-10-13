<?php

use App\Enums\DataSourceMethod;
use App\Models\DataSource;
use App\Services\DataSources\Fetchers\BspKeyRatesFetcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

/**
 * Uses a real sefi.pdf captured from BSP (tests/Fixtures/bsp-sefi-sample.pdf,
 * dated 11 Sep 2026) so the parser is proven against BSP's actual layout,
 * including the exact ambiguity that makes this fetcher only cover 3 of
 * the 11 BSP Key Rates cards - see the class docblock and
 * docs/MODULES.md for why the other 8 aren't sourced from this file.
 */
function fakeBspSefiResponse(): void
{
    Http::fake([
        'bsp.gov.ph/*' => Http::response(file_get_contents(__DIR__.'/../Fixtures/bsp-sefi-sample.pdf'), 200),
    ]);
}

function makeBspSefiDataSource(): DataSource
{
    return DataSource::factory()->create([
        'method' => DataSourceMethod::File,
        'url' => 'https://www.bsp.gov.ph/statistics/keystat/sefi.pdf',
    ]);
}

test('it parses the three clean BSP Key Rates from the real SEFI document', function () {
    fakeBspSefiResponse();

    $result = (new BspKeyRatesFetcher)->fetch(makeBspSefiDataSource());

    $byCode = collect($result->values)->keyBy('code');

    expect($byCode)->toHaveCount(3);

    expect($byCode['on_rrp_rate'])->toMatchArray(['as_of_date' => '2026-09-11', 'value' => 5.0]);
    expect($byCode['tdf_7d'])->toMatchArray(['as_of_date' => '2026-09-11', 'value' => 4.98]);
    expect($byCode['bsp_securities_28d'])->toMatchArray(['as_of_date' => '2026-09-11', 'value' => 5.07]);

    // Deliberately not covered by this fetcher - see the class docblock.
    expect($byCode->has('on_lending_rate'))->toBeFalse();
    expect($byCode->has('on_deposit_rate'))->toBeFalse();
    expect($byCode->has('bsp_securities_56d'))->toBeFalse();
    expect($byCode->has('tdf_14d'))->toBeFalse();
    expect($byCode->has('tdf_28d'))->toBeFalse();
});

test('it does not confuse the TDF and BSP Securities rows that share the same WAIR labels', function () {
    fakeBspSefiResponse();

    $result = (new BspKeyRatesFetcher)->fetch(makeBspSefiDataSource());
    $byCode = collect($result->values)->keyBy('code');

    // 28-day WAIR appears twice in the document (once under Term Deposit
    // Facility, which is blank/dormant, and once under BSP Securities,
    // which has a real value) - make sure we picked the latter.
    expect($byCode['bsp_securities_28d']['value'])->toBe(5.07);
});

test('it throws when the HTTP request fails', function () {
    Http::fake([
        'bsp.gov.ph/*' => Http::response('Access Denied', 403),
    ]);

    (new BspKeyRatesFetcher)->fetch(makeBspSefiDataSource());
})->throws(RequestException::class);

test('it throws when the document does not look like the expected bulletin', function () {
    Http::fake([
        'bsp.gov.ph/*' => Http::response('not a pdf at all', 200),
    ]);

    (new BspKeyRatesFetcher)->fetch(makeBspSefiDataSource());
})->throws(Exception::class);
