<?php

use App\Models\DataSource;
use App\Models\IndicatorValue;
use App\Models\MarketIndicator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/**
 * Regression test for the N+1 the query detector caught on the dashboard:
 * MarketIndicator::latestValue() used to run its own query per call, so
 * looping over the 12 dashboard indicators fired ~12 extra queries. It's
 * now a real `latestOfMany` relation, so eager-loading it fetches every
 * indicator's latest value in one additional query, regardless of how
 * many indicators there are.
 */
test('eager-loading latestValue on many indicators does not N+1', function () {
    $dataSource = DataSource::factory()->manual()->create();

    $codes = ['rate_one', 'rate_two', 'rate_three', 'rate_four', 'rate_five'];

    foreach ($codes as $code) {
        $indicator = MarketIndicator::factory()->for($dataSource, 'dataSource')->create(['code' => $code]);
        IndicatorValue::factory()->for($indicator, 'indicator')->forDate('2026-04-07')->create(['value' => 1.2345]);
        IndicatorValue::factory()->for($indicator, 'indicator')->forDate('2026-04-06')->create(['value' => 1.1111]);
    }

    DB::flushQueryLog();
    DB::enableQueryLog();

    $indicators = MarketIndicator::query()
        ->whereIn('code', $codes)
        ->with('latestValue')
        ->get();

    // Access the relation on every indicator, the way the dashboard does -
    // this must not fire any further queries if eager loading worked.
    $values = $indicators->map(fn (MarketIndicator $indicator) => $indicator->latestValue?->value);

    $queryCount = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($values)->each->toEqual(1.2345);
    // 1 query for the indicators themselves, 1 for the eager-loaded
    // latest values - not one per indicator.
    expect($queryCount)->toBe(2);
});
