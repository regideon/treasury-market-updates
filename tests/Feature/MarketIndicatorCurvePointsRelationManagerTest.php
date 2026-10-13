<?php

use App\Filament\Resources\MarketIndicators\Pages\EditMarketIndicator;
use App\Filament\Resources\MarketIndicators\RelationManagers\CurvePointsRelationManager;
use App\Models\CurvePoint;
use App\Models\DataSource;
use App\Models\MarketIndicator;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Livewire\livewire;

uses(RefreshDatabase::class);

/**
 * Relation managers are lazy-loaded through a separate Livewire request,
 * so - as with the value-history manager - only a direct Livewire test
 * actually exercises them.
 */
function lmeCurveIndicator(): MarketIndicator
{
    return MarketIndicator::factory()->for(DataSource::factory()->manual(), 'dataSource')->create(['code' => 'lme_lead_curve']);
}

test('the curve points tab lists existing bid/offer rows', function () {
    $this->actingAs(User::factory()->create());
    $indicator = lmeCurveIndicator();
    CurvePoint::factory()->for($indicator, 'indicator')->create(['as_of_date' => '2026-04-02', 'point_key' => 'Dec-27', 'bid' => 2078, 'offer' => 2083]);

    livewire(CurvePointsRelationManager::class, ['ownerRecord' => $indicator, 'pageClass' => EditMarketIndicator::class])
        ->assertSuccessful()
        ->assertSee('Dec-27')
        ->assertSee('2078')
        ->assertSee('2083');
});

test('a row can be added by hand with bid, offer and order', function () {
    $this->actingAs(User::factory()->create());
    $indicator = lmeCurveIndicator();

    livewire(CurvePointsRelationManager::class, ['ownerRecord' => $indicator, 'pageClass' => EditMarketIndicator::class])
        ->callAction(TestAction::make('create')->table(), [
            'as_of_date' => '2026-09-21',
            'point_key' => 'Cash',
            'bid' => 1868.5,
            'offer' => 1869,
            'sort_order' => 0,
        ])
        ->assertHasNoFormErrors();

    expect(CurvePoint::where('market_indicator_id', $indicator->id)->firstWhere('point_key', 'Cash'))
        ->bid->toEqual('1868.500000')
        ->as_of_date->toDateString()->toBe('2026-09-21');
});

test('the same contract cannot be entered twice for the same date', function () {
    $this->actingAs(User::factory()->create());
    $indicator = lmeCurveIndicator();
    CurvePoint::factory()->for($indicator, 'indicator')->create(['as_of_date' => '2026-09-21', 'point_key' => 'Cash']);

    livewire(CurvePointsRelationManager::class, ['ownerRecord' => $indicator, 'pageClass' => EditMarketIndicator::class])
        ->callAction(TestAction::make('create')->table(), ['as_of_date' => '2026-09-21', 'point_key' => 'Cash', 'bid' => 1, 'offer' => 2, 'sort_order' => 0])
        ->assertHasFormErrors(['point_key']);

    expect(CurvePoint::where('market_indicator_id', $indicator->id)->count())->toBe(1);
});

test('the tab only appears for the LME curve or indicators that already have curve points', function () {
    $plain = MarketIndicator::factory()->for(DataSource::factory(), 'dataSource')->create(['code' => 'usdphp_spot']);
    $lme = lmeCurveIndicator();
    $withPoints = MarketIndicator::factory()->for(DataSource::factory(), 'dataSource')->create(['code' => 'php_bval_curve']);
    CurvePoint::factory()->for($withPoints, 'indicator')->create();

    expect(CurvePointsRelationManager::canViewForRecord($plain, EditMarketIndicator::class))->toBeFalse();
    expect(CurvePointsRelationManager::canViewForRecord($lme, EditMarketIndicator::class))->toBeTrue();
    expect(CurvePointsRelationManager::canViewForRecord($withPoints, EditMarketIndicator::class))->toBeTrue();
});
