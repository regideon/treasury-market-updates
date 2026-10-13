<?php

use App\Filament\Resources\MarketIndicators\Pages\EditMarketIndicator;
use App\Filament\Resources\MarketIndicators\RelationManagers\ValuesRelationManager;
use App\Models\DataSource;
use App\Models\IndicatorValue;
use App\Models\MarketIndicator;
use App\Models\ScraperRun;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Livewire\livewire;

uses(RefreshDatabase::class);

/**
 * Regression test for a real fatal error: MarketIndicatorResource's Edit
 * page loaded fine, but visiting it crashed the browser with "Allowed
 * memory size exhausted" - because Filament relation managers are
 * lazy-loaded via a *separate* follow-up Livewire request, which a
 * plain HTTP GET test never triggers. Only a direct Livewire component
 * test (this one) actually exercises that code path.
 *
 * Root cause: a ->state() column callback had its parameter named
 * $state. Filament's closure injection treats a parameter named $state
 * as "give me the already-resolved state", which for the ->state()
 * callback itself means calling getState() again - infinite recursion.
 * Fixed by naming it $record and reading the attribute directly.
 */
test('the value history relation manager renders without recursing infinitely', function () {
    $this->actingAs(User::factory()->create());

    $dataSource = DataSource::factory()->create();
    $indicator = MarketIndicator::factory()->for($dataSource, 'dataSource')->create();
    $run = ScraperRun::factory()->for($dataSource, 'dataSource')->create();

    // One manual entry and one fetched entry, so both branches of the
    // "Source" column's state callback actually run.
    IndicatorValue::factory()->for($indicator, 'indicator')->forDate('2026-09-16')->create([
        'scraper_run_id' => null,
    ]);
    IndicatorValue::factory()->for($indicator, 'indicator')->forDate('2026-09-17')->create([
        'scraper_run_id' => $run->id,
    ]);

    livewire(ValuesRelationManager::class, [
        'ownerRecord' => $indicator,
        'pageClass' => EditMarketIndicator::class,
    ])
        ->assertSuccessful()
        ->assertSee('Manual')
        ->assertSee("Run #{$run->id}");
});

test('the Period column always shows the exact date, and the time whenever one was recorded', function () {
    $this->actingAs(User::factory()->create());

    $indicator = MarketIndicator::factory()->for(DataSource::factory(), 'dataSource')->create();

    // No period_label at all (the common case) - must fall back to the
    // date, not the "—" placeholder every such row used to share.
    IndicatorValue::factory()->for($indicator, 'indicator')->forDate('2026-09-16')->create([
        'period_label' => null,
        'as_of_time' => null,
    ]);

    // A period_label plus a recorded time - both must show, so two
    // entries that otherwise look alike stay distinguishable.
    IndicatorValue::factory()->for($indicator, 'indicator')->forDate('2026-09-17')->create([
        'period_label' => 'March 2026',
        'as_of_time' => '14:30:00',
    ]);

    livewire(ValuesRelationManager::class, [
        'ownerRecord' => $indicator,
        'pageClass' => EditMarketIndicator::class,
    ])
        ->assertSuccessful()
        ->assertSee('Sep 16, 2026')
        ->assertSee('March 2026 (Sep 17, 2026, 2:30 PM)');
});
