<?php

use App\Enums\DataSourceMethod;
use App\Models\DataSource;
use App\Services\DataSources\Fetchers\PdsBvalFetcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

/**
 * Uses a real PDS response, captured live from the public GraphQL widget
 * API (tests/Fixtures/pds-bval-response.json) - all 12 BVAL tenors for
 * Sep 16, 2026.
 */
function fakePdsResponse(): void
{
    $json = file_get_contents(__DIR__.'/../Fixtures/pds-bval-response.json');

    Http::fake(['www.pds.com.ph/api-mp/graphql/*' => Http::response($json, 200)]);
}

function makePdsDataSource(): DataSource
{
    return DataSource::factory()->create([
        'method' => DataSourceMethod::Api,
        'url' => 'https://www.pds.com.ph/api-mp/graphql/',
    ]);
}

test('it reads all 12 BVAL tenors as curve points for the current trading date', function () {
    fakePdsResponse();

    $result = (new PdsBvalFetcher)->fetch(makePdsDataSource());

    expect($result->curvePoints)->toHaveCount(12);
    expect($result->values)->toBe([]);
    expect($result->httpStatus)->toBe(200);

    $byTenor = collect($result->curvePoints)->keyBy('point_key');

    expect($byTenor['1M'])->toMatchArray([
        'code' => 'php_bval_curve',
        'as_of_date' => '2026-09-16',
        'point_key' => '1M',
        'value' => 5.0558,
        'sort_order' => 0,
    ]);
    expect($byTenor['10Y'])->toMatchArray([
        'value' => 7.6147,
        'sort_order' => 9,
    ]);
    expect($byTenor['25Y'])->toMatchArray([
        'value' => 7.7123,
        'sort_order' => 11,
    ]);

    foreach ($byTenor as $point) {
        expect($point['as_of_date'])->toBe('2026-09-16');
    }
});

test('it authenticates with an APIKey header and passes requestPage=public', function () {
    fakePdsResponse();

    (new PdsBvalFetcher)->fetch(makePdsDataSource());

    Http::assertSent(function (Request $request): bool {
        $body = json_decode($request->body(), true);

        return $request->hasHeader('Authorization')
            && str_starts_with($request->header('Authorization')[0], 'APIKey ')
            && ($body['variables']['requestPage'] ?? null) === 'public';
    });
});

test('it throws when the HTTP request fails', function () {
    Http::fake(['www.pds.com.ph/api-mp/graphql/*' => Http::response('Unauthorized', 401)]);

    (new PdsBvalFetcher)->fetch(makePdsDataSource());
})->throws(RequestException::class);

test('it throws when PDS returns no rates', function () {
    Http::fake(['www.pds.com.ph/api-mp/graphql/*' => Http::response(json_encode(['data' => ['bvalRates' => []]]), 200)]);

    (new PdsBvalFetcher)->fetch(makePdsDataSource());
})->throws(RuntimeException::class, 'PDS returned no BVAL rates');
