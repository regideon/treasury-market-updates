<?php

use App\Enums\DataSourceMethod;
use App\Models\DataSource;
use App\Services\DataSources\Fetchers\PsaInflationFetcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

/**
 * Uses real PSA OpenSTAT responses (tests/Fixtures/psa-cpi-metadata.json,
 * psa-cpi-data.json) captured live: as of this fixture, August 2026 is
 * the latest published month (6.1%), with September onward still ".."
 * (not yet released) - exactly the case this fetcher needs to handle.
 */
function fakePsaResponses(): void
{
    $metadata = file_get_contents(__DIR__.'/../Fixtures/psa-cpi-metadata.json');
    $data = file_get_contents(__DIR__.'/../Fixtures/psa-cpi-data.json');

    Http::fake(function (Request $request) use ($metadata, $data) {
        return $request->method() === 'GET'
            ? Http::response($metadata, 200)
            : Http::response($data, 200);
    });
}

function makePsaDataSource(): DataSource
{
    return DataSource::factory()->create([
        'method' => DataSourceMethod::Api,
        'url' => 'https://openstat.psa.gov.ph/PXWeb/api/v1/en/DB/2M/PI/CPI/2018NEW/0012M4ACP23.px',
    ]);
}

/**
 * Uses a real full-history PSA response (tests/Fixtures/psa-cpi-full-history-data.json),
 * captured live: 96 (year x month) cells from 2019 through 2026, of
 * which 92 are published - September 2026 onward is still ".." at the
 * time this fixture was captured.
 */
function fakePsaFullHistoryResponses(): void
{
    $metadata = file_get_contents(__DIR__.'/../Fixtures/psa-cpi-metadata.json');
    $data = file_get_contents(__DIR__.'/../Fixtures/psa-cpi-full-history-data.json');

    Http::fake(function (Request $request) use ($metadata, $data) {
        return $request->method() === 'GET'
            ? Http::response($metadata, 200)
            : Http::response($data, 200);
    });
}

test('it finds the most recently published month, skipping unpublished ".." months', function () {
    fakePsaResponses();

    $result = (new PsaInflationFetcher)->fetch(makePsaDataSource());

    expect($result->values)->toHaveCount(1);
    expect($result->values[0])->toMatchArray([
        'code' => 'inflation_rate',
        'as_of_date' => '2026-08-31',
        'value' => 6.1,
        'period_label' => 'August 2026',
    ]);
    expect($result->httpStatus)->toBe(200);
});

test('it queries both the metadata (GET) and the data (POST) endpoints', function () {
    fakePsaResponses();

    (new PsaInflationFetcher)->fetch(makePsaDataSource());

    Http::assertSentCount(2);
    Http::assertSent(fn (Request $request): bool => $request->method() === 'GET');
    Http::assertSent(fn (Request $request): bool => $request->method() === 'POST');
});

test('it throws when the HTTP request fails', function () {
    Http::fake([
        'openstat.psa.gov.ph/*' => Http::response('Service Unavailable', 503),
    ]);

    (new PsaInflationFetcher)->fetch(makePsaDataSource());
})->throws(RequestException::class);

test('it throws when every requested month is unpublished', function () {
    $metadata = file_get_contents(__DIR__.'/../Fixtures/psa-cpi-metadata.json');

    Http::fake(function (Request $request) use ($metadata) {
        if ($request->method() === 'GET') {
            return Http::response($metadata, 200);
        }

        return Http::response(json_encode(['data' => [
            ['key' => ['0', '0', '7', '8'], 'values' => ['..']],
        ]]), 200);
    });

    (new PsaInflationFetcher)->fetch(makePsaDataSource());
})->throws(RuntimeException::class, 'No published inflation figure found');

test('fetchHistory returns every published month, oldest to newest', function () {
    fakePsaFullHistoryResponses();

    $result = (new PsaInflationFetcher)->fetchHistory(makePsaDataSource());

    // 96 (year x month) cells for 2019-2026, 4 of which (Sep-Dec 2026)
    // are unpublished ".." at the time this fixture was captured.
    expect($result->values)->toHaveCount(92);
    expect($result->values[0])->toMatchArray([
        'code' => 'inflation_rate',
        'as_of_date' => '2019-01-31',
        'value' => 4.4,
        'period_label' => 'January 2019',
    ]);
    expect($result->values[array_key_last($result->values)])->toMatchArray([
        'code' => 'inflation_rate',
        'as_of_date' => '2026-08-31',
        'value' => 6.1,
        'period_label' => 'August 2026',
    ]);
});

test('fetchHistory requests every year, unlike fetch which only requests the last two', function () {
    fakePsaFullHistoryResponses();

    (new PsaInflationFetcher)->fetchHistory(makePsaDataSource());

    Http::assertSent(function (Request $request): bool {
        if ($request->method() !== 'POST') {
            return true;
        }

        $yearQuery = collect($request->data()['query'])->firstWhere('code', 'Year');

        return count($yearQuery['selection']['values']) === 8;
    });
});
