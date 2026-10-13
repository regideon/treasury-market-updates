<?php

use App\Models\DataSource;
use App\Models\IndicatorValue;
use App\Models\MarketIndicator;
use App\Models\ScraperRun;
use App\Models\User;
use App\Services\DataSources\ManualValueRecorder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

test('it creates a value for a date the indicator has never had one for', function () {
    $indicator = MarketIndicator::factory()->for(DataSource::factory(), 'dataSource')->create();

    $value = (new ManualValueRecorder)->record($indicator, [
        'value' => 5.25,
        'as_of_date' => '2026-09-17',
        'period_label' => null,
        'source_note' => null,
    ]);

    expect((float) $value->value)->toBe(5.25);
    expect($value->as_of_date->toDateString())->toBe('2026-09-17');
    expect(IndicatorValue::count())->toBe(1);
});

test('it updates the existing value instead of duplicating it for the same date', function () {
    $indicator = MarketIndicator::factory()->for(DataSource::factory(), 'dataSource')->create();
    IndicatorValue::factory()->for($indicator, 'indicator')->forDate('2026-09-17')->create(['value' => 1.0]);

    (new ManualValueRecorder)->record($indicator, ['value' => 9.99, 'as_of_date' => '2026-09-17']);

    expect(IndicatorValue::count())->toBe(1);
    expect((float) $indicator->values()->first()->value)->toBe(9.99);
});

test('it clears the scraper run link when a person overrides a fetched value', function () {
    $indicator = MarketIndicator::factory()->for(DataSource::factory(), 'dataSource')->create();
    $run = ScraperRun::factory()->for($indicator->dataSource, 'dataSource')->create();
    IndicatorValue::factory()->for($indicator, 'indicator')->forDate('2026-09-17')->create([
        'value' => 1.0,
        'scraper_run_id' => $run->id,
    ]);

    (new ManualValueRecorder)->record($indicator, ['value' => 2.0, 'as_of_date' => '2026-09-17']);

    $value = $indicator->values()->first();
    expect($value->scraper_run_id)->toBeNull();
    expect((float) $value->value)->toBe(2.0);
});

test('it supports a text-only reading with no numeric value', function () {
    $indicator = MarketIndicator::factory()->for(DataSource::factory(), 'dataSource')->create();

    $value = (new ManualValueRecorder)->record($indicator, [
        'value' => null,
        'value_text' => '****',
        'as_of_date' => '2026-09-17',
    ]);

    expect($value->value)->toBeNull();
    expect($value->value_text)->toBe('****');
});

test('a manual entry is logged to the activity log with the acting user as the causer', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $indicator = MarketIndicator::factory()->for(DataSource::factory(), 'dataSource')->create();

    (new ManualValueRecorder)->record($indicator, ['value' => 4.2, 'as_of_date' => '2026-09-17']);

    $activity = Activity::query()->latest('id')->first();

    expect($activity)->not->toBeNull();
    expect($activity->causer_id)->toBe($user->id);
});

test('it stores an optional time alongside the date, for telling apart otherwise-identical entries', function () {
    $indicator = MarketIndicator::factory()->for(DataSource::factory(), 'dataSource')->create();

    $value = (new ManualValueRecorder)->record($indicator, [
        'value' => 61.75,
        'as_of_date' => '2026-09-17',
        'as_of_time' => '14:30:00',
    ]);

    expect($value->as_of_time)->toBe('14:30:00');
});
