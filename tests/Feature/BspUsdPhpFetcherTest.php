<?php

use App\Enums\DataSourceMethod;
use App\Models\DataSource;
use App\Services\DataSources\Fetchers\BspUsdPhpFetcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

/**
 * Uses a real RERB.xlsx captured from BSP (tests/Fixtures/bsp-rerb-sample.xlsx,
 * dated 16 September 2026, USD -> PHP 62.8500) so the parser is proven
 * against BSP's actual file layout, not a hand-made stand-in - without
 * making a real network call in the test suite.
 */
test('it parses the USD/PHP rate and bulletin date from the real RERB file', function () {
    $fixture = file_get_contents(__DIR__.'/../Fixtures/bsp-rerb-sample.xlsx');

    Http::fake([
        'bsp.gov.ph/*' => Http::response($fixture, 200),
    ]);

    $dataSource = DataSource::factory()->create([
        'method' => DataSourceMethod::File,
        'url' => 'https://www.bsp.gov.ph/Statistics/RERB/RERB.xlsx',
    ]);

    $result = (new BspUsdPhpFetcher)->fetch($dataSource);

    expect($result->values)->toHaveCount(1);
    expect($result->values[0])->toMatchArray([
        'code' => 'usdphp_spot',
        'as_of_date' => '2026-09-16',
        'value' => 62.85,
    ]);
    expect($result->rawSnapshot)->toBe($fixture);
    expect($result->httpStatus)->toBe(200);
});

test('it sends a request to the configured URL', function () {
    $fixture = file_get_contents(__DIR__.'/../Fixtures/bsp-rerb-sample.xlsx');

    Http::fake([
        'bsp.gov.ph/*' => Http::response($fixture, 200),
    ]);

    $dataSource = DataSource::factory()->create([
        'method' => DataSourceMethod::File,
        'url' => 'https://www.bsp.gov.ph/Statistics/RERB/RERB.xlsx',
    ]);

    (new BspUsdPhpFetcher)->fetch($dataSource);

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://www.bsp.gov.ph/Statistics/RERB/RERB.xlsx');
});

test('it throws when the data source has no URL', function () {
    $dataSource = DataSource::factory()->create([
        'method' => DataSourceMethod::File,
        'url' => null,
    ]);

    (new BspUsdPhpFetcher)->fetch($dataSource);
})->throws(RuntimeException::class, 'has no URL configured');

test('it throws when the response is not a valid bulletin', function () {
    Http::fake([
        'bsp.gov.ph/*' => Http::response('<html>not a spreadsheet</html>', 200),
    ]);

    $dataSource = DataSource::factory()->create([
        'method' => DataSourceMethod::File,
        'url' => 'https://www.bsp.gov.ph/Statistics/RERB/RERB.xlsx',
    ]);

    (new BspUsdPhpFetcher)->fetch($dataSource);
})->throws(RuntimeException::class);

test('it throws when the HTTP request fails', function () {
    Http::fake([
        'bsp.gov.ph/*' => Http::response('Access Denied', 403),
    ]);

    $dataSource = DataSource::factory()->create([
        'method' => DataSourceMethod::File,
        'url' => 'https://www.bsp.gov.ph/Statistics/RERB/RERB.xlsx',
    ]);

    (new BspUsdPhpFetcher)->fetch($dataSource);
})->throws(RequestException::class);
