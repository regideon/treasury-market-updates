<?php

use App\Enums\DataSourceMethod;
use App\Models\DataSource;
use App\Services\DataSources\Fetchers\PsaGdpGrowthFetcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

/**
 * Uses real PSA OpenSTAT responses (tests/Fixtures/psa-gdp-metadata.json,
 * psa-gdp-data-latest.json) captured live: as of this fixture, Q2 2026
 * is the latest published quarter (2.3%), with Q3-Q4 2026 still "."
 * (not yet released).
 */
function fakePsaGdpLatestResponses(): void
{
    $metadata = file_get_contents(__DIR__.'/../Fixtures/psa-gdp-metadata.json');
    $data = file_get_contents(__DIR__.'/../Fixtures/psa-gdp-data-latest.json');

    Http::fake(function (Request $request) use ($metadata, $data) {
        return $request->method() === 'GET'
            ? Http::response($metadata, 200)
            : Http::response($data, 200);
    });
}

/**
 * Uses a real full-history PSA response (tests/Fixtures/psa-gdp-data-full-history.json):
 * 104 (year-pair x quarter) cells from 2001 through Q2 2026, of which
 * 102 are published.
 */
function fakePsaGdpFullHistoryResponses(): void
{
    $metadata = file_get_contents(__DIR__.'/../Fixtures/psa-gdp-metadata.json');
    $data = file_get_contents(__DIR__.'/../Fixtures/psa-gdp-data-full-history.json');

    Http::fake(function (Request $request) use ($metadata, $data) {
        return $request->method() === 'GET'
            ? Http::response($metadata, 200)
            : Http::response($data, 200);
    });
}

function makePsaGdpDataSource(): DataSource
{
    return DataSource::factory()->create([
        'method' => DataSourceMethod::Api,
        'url' => 'https://openstat.psa.gov.ph/PXWeb/api/v1/en/DB/2B/NA/QT/1SUM/0022B5BEXQ2.px',
    ]);
}

test('it finds the most recently published quarter, skipping unpublished "." quarters', function () {
    fakePsaGdpLatestResponses();

    $result = (new PsaGdpGrowthFetcher)->fetch(makePsaGdpDataSource());

    expect($result->values)->toHaveCount(1);
    expect($result->values[0])->toMatchArray([
        'code' => 'gdp_growth_rate',
        'as_of_date' => '2026-06-30',
        'value' => 2.3,
        'period_label' => 'Q2 2026',
    ]);
    expect($result->httpStatus)->toBe(200);
});

test('it queries both the metadata (GET) and the data (POST) endpoints', function () {
    fakePsaGdpLatestResponses();

    (new PsaGdpGrowthFetcher)->fetch(makePsaGdpDataSource());

    Http::assertSentCount(2);
    Http::assertSent(fn (Request $request): bool => $request->method() === 'GET');
    Http::assertSent(fn (Request $request): bool => $request->method() === 'POST');
});

test('it throws when the HTTP request fails', function () {
    Http::fake([
        'openstat.psa.gov.ph/*' => Http::response('Service Unavailable', 503),
    ]);

    (new PsaGdpGrowthFetcher)->fetch(makePsaGdpDataSource());
})->throws(RequestException::class);

test('it throws when every requested quarter is unpublished', function () {
    $metadata = file_get_contents(__DIR__.'/../Fixtures/psa-gdp-metadata.json');

    Http::fake(function (Request $request) use ($metadata) {
        if ($request->method() === 'GET') {
            return Http::response($metadata, 200);
        }

        return Http::response(json_encode(['data' => [
            ['key' => ['16', '1', '25', '2'], 'values' => ['.']],
        ]]), 200);
    });

    (new PsaGdpGrowthFetcher)->fetch(makePsaGdpDataSource());
})->throws(RuntimeException::class, 'No published GDP growth figure found');

test('fetchHistory returns every published quarter, oldest to newest', function () {
    fakePsaGdpFullHistoryResponses();

    $result = (new PsaGdpGrowthFetcher)->fetchHistory(makePsaGdpDataSource());

    expect($result->values)->toHaveCount(102);
    expect($result->values[0])->toMatchArray([
        'code' => 'gdp_growth_rate',
        'as_of_date' => '2001-03-31',
        'value' => 2.4,
        'period_label' => 'Q1 2001',
    ]);
    expect($result->values[array_key_last($result->values)])->toMatchArray([
        'code' => 'gdp_growth_rate',
        'as_of_date' => '2026-06-30',
        'value' => 2.3,
        'period_label' => 'Q2 2026',
    ]);
});

test('fetchHistory requests every year-pair, unlike fetch which only requests the last two', function () {
    fakePsaGdpFullHistoryResponses();

    (new PsaGdpGrowthFetcher)->fetchHistory(makePsaGdpDataSource());

    Http::assertSent(function (Request $request): bool {
        if ($request->method() !== 'POST') {
            return true;
        }

        $yearQuery = collect($request->data()['query'])->firstWhere('code', 'Year');

        return count($yearQuery['selection']['values']) === 26;
    });
});
