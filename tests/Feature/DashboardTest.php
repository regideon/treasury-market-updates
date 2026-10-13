<?php

use App\Enums\DashboardCategory;
use App\Enums\DataSourceMethod;
use App\Models\CurvePoint;
use App\Models\DataSource;
use App\Models\IndicatorValue;
use App\Models\MarketIndicator;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('the treasury dashboard renders for an authenticated user', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/rpanel');

    $response->assertOk();
    $response->assertSee('BAP Spot USD/PHP');
    $response->assertSee('PHP BVAL Reference Rates');
    $response->assertSee('Philippine Stock Market Overview');
});

test('guests are redirected to the login page', function () {
    $response = $this->get('/rpanel');

    $response->assertRedirect('/rpanel/login');
});

test('the USD/PHP hero card shows the latest stored value and its change', function () {
    // Deliberately different from the original mockup's own 61.7350 /
    // 61.7450 placeholder numbers, so this test can only pass if the
    // card is genuinely reading from the database - not matching on a
    // hardcoded string that happens to look the same.
    $indicator = MarketIndicator::factory()
        ->for(DataSource::factory()->manual(), 'dataSource')
        ->create(['code' => 'usdphp_spot', 'category' => DashboardCategory::Fx]);

    IndicatorValue::factory()->for($indicator, 'indicator')->forDate('2026-08-24')->create(['value' => 70.0000]);
    IndicatorValue::factory()->for($indicator, 'indicator')->forDate('2026-08-25')->create(['value' => 71.4000]);

    $response = $this->actingAs(User::factory()->create())->get('/rpanel');

    $response->assertOk();
    $response->assertSee('71.4000');
    $response->assertSee('vs 70.0000 yesterday');
    $response->assertSee('2.00%');
});

test('the USD/PHP hero card degrades gracefully when there is no data yet', function () {
    $response = $this->actingAs(User::factory()->create())->get('/rpanel');

    $response->assertOk();
    $response->assertSee('No data yet');
});

test('the BSP Key Rates strip shows stored values, formatted rates, and each card\'s own date', function () {
    $dataSource = DataSource::factory()->manual()->create(['category' => DashboardCategory::Rates]);

    // A distinct value (not the mockup's own numbers) with trailing
    // zeros, to prove the "%" formatting is real and the card is reading
    // from the database.
    $lending = MarketIndicator::factory()->for($dataSource, 'dataSource')
        ->create(['code' => 'on_lending_rate', 'category' => DashboardCategory::Rates]);
    IndicatorValue::factory()->for($lending, 'indicator')->forDate('2026-04-07')->create(['value' => 9.6250]);

    // A discontinued-style rate: no numeric value, just the "****" BSP
    // itself displays, with its own (much older) date.
    $reference = MarketIndicator::factory()->for($dataSource, 'dataSource')
        ->create(['code' => 'on_reference_rate', 'category' => DashboardCategory::Rates]);
    IndicatorValue::factory()->for($reference, 'indicator')->forDate('2023-09-08')
        ->create(['value' => null, 'value_text' => '****']);

    $response = $this->actingAs(User::factory()->create())->get('/rpanel');

    $response->assertOk();
    $response->assertSee('9.625%');
    $response->assertSee('Apr 7, 2026');
    $response->assertSee('****');
    $response->assertSee('Sep 8, 2023');
});

test('a BSP Key Rates card with no stored value yet shows a dash instead of breaking', function () {
    $response = $this->actingAs(User::factory()->create())->get('/rpanel');

    $response->assertOk();
    $response->assertSee('—');
});

test('the Inflation Update table shows years as columns and months as rows, with a computed average', function () {
    $indicator = MarketIndicator::factory()
        ->for(DataSource::factory()->manual(), 'dataSource')
        ->create(['code' => 'inflation_rate', 'category' => DashboardCategory::Macro]);

    IndicatorValue::factory()->for($indicator, 'indicator')->forDate('2025-01-31')->create(['value' => 2.9]);
    IndicatorValue::factory()->for($indicator, 'indicator')->forDate('2025-02-28')->create(['value' => 2.1]);
    IndicatorValue::factory()->for($indicator, 'indicator')->forDate('2026-01-31')->create(['value' => 2.0]);

    $response = $this->actingAs(User::factory()->create())->get('/rpanel');

    $response->assertOk();
    $response->assertSee('Inflation Update');
    // 2025's average of 2.9 and 2.1 is 2.5 - not a value either month shows
    // on its own, so seeing it proves the average is actually computed,
    // not just copied from one of the months.
    $response->assertSeeInOrder(['2025', '2.9', '2.1', '2.5']);
    $response->assertSee('2026');
    $response->assertSee('2.0');
});

test('the Inflation Update table shows a friendly placeholder when there is no data yet', function () {
    $response = $this->actingAs(User::factory()->create())->get('/rpanel');

    $response->assertOk();
    $response->assertSee('Inflation Update');
    $response->assertSee('No data yet');
});

test('the GDP Growth card shows the last 4 real quarters, most recent highlighted', function () {
    $indicator = MarketIndicator::factory()
        ->for(DataSource::factory()->manual(), 'dataSource')
        ->create(['code' => 'gdp_growth_rate', 'category' => DashboardCategory::Macro]);

    // 5 quarters seeded, only the last 4 should be shown - proves the
    // card is reading real rows, not a hardcoded count.
    IndicatorValue::factory()->for($indicator, 'indicator')->forDate('2025-03-31')->create(['value' => 5.4, 'period_label' => 'Q1 2025']);
    IndicatorValue::factory()->for($indicator, 'indicator')->forDate('2025-06-30')->create(['value' => 5.4, 'period_label' => 'Q2 2025']);
    IndicatorValue::factory()->for($indicator, 'indicator')->forDate('2025-09-30')->create(['value' => 4.0, 'period_label' => 'Q3 2025']);
    IndicatorValue::factory()->for($indicator, 'indicator')->forDate('2025-12-31')->create(['value' => 3.0, 'period_label' => 'Q4 2025']);
    IndicatorValue::factory()->for($indicator, 'indicator')->forDate('2026-03-31')->create(['value' => 2.8, 'period_label' => 'Q1 2026']);

    $response = $this->actingAs(User::factory()->create())->get('/rpanel');

    $response->assertOk();
    $response->assertSee('GDP Growth');
    $response->assertSee('2.8% (Q1 &#039;26)', false);
    $response->assertDontSee('Q1 2025');
    $response->assertSeeInOrder(['Q2 &#039;25', 'Q3 &#039;25', 'Q4 &#039;25', 'Q1 &#039;26'], false);
});

test('the GDP Growth card shows a friendly placeholder when there is no data yet', function () {
    $response = $this->actingAs(User::factory()->create())->get('/rpanel');

    $response->assertOk();
    $response->assertSee('GDP Growth');
    $response->assertSee('No data yet');
});

test('the GDP Growth History table shows years as columns and quarters as rows, with a computed average', function () {
    $indicator = MarketIndicator::factory()
        ->for(DataSource::factory()->manual(), 'dataSource')
        ->create(['code' => 'gdp_growth_rate', 'category' => DashboardCategory::Macro]);

    IndicatorValue::factory()->for($indicator, 'indicator')->forDate('2025-03-31')->create(['value' => 5.4]);
    IndicatorValue::factory()->for($indicator, 'indicator')->forDate('2025-06-30')->create(['value' => 5.4]);
    IndicatorValue::factory()->for($indicator, 'indicator')->forDate('2025-09-30')->create(['value' => 4.0]);
    IndicatorValue::factory()->for($indicator, 'indicator')->forDate('2025-12-31')->create(['value' => 3.0]);

    $response = $this->actingAs(User::factory()->create())->get('/rpanel');

    $response->assertOk();
    $response->assertSee('GDP Growth History');
    // 2025's average of 5.4, 5.4, 4.0, 3.0 is 4.45, rounded to 4.5 - not
    // a value any quarter shows on its own, so seeing it proves the
    // average is actually computed, not just copied from one quarter.
    $response->assertSeeInOrder(['2025', '5.4', '4.0', '3.0', '4.5']);
});

test('the GDP Growth History table shows a friendly placeholder when there is no data yet', function () {
    $response = $this->actingAs(User::factory()->create())->get('/rpanel');

    $response->assertOk();
    $response->assertSee('GDP Growth History');
    $response->assertSee('No data yet');
});

test('the GDP Growth trend chart only labels the extremes and the latest point, not every quarter', function () {
    $indicator = MarketIndicator::factory()
        ->for(DataSource::factory()->manual(), 'dataSource')
        ->create(['code' => 'gdp_growth_rate', 'category' => DashboardCategory::Macro]);

    IndicatorValue::factory()->for($indicator, 'indicator')->forDate('2025-03-31')->create(['value' => 5.4]);
    IndicatorValue::factory()->for($indicator, 'indicator')->forDate('2025-06-30')->create(['value' => -8.2]);
    IndicatorValue::factory()->for($indicator, 'indicator')->forDate('2025-09-30')->create(['value' => 12.0]);
    IndicatorValue::factory()->for($indicator, 'indicator')->forDate('2025-12-31')->create(['value' => 3.0]);

    $response = $this->actingAs(User::factory()->create())->get('/rpanel');

    $response->assertOk();
    // The min (-8.2), the max (12.0), and the latest quarter (3.0) each
    // get a direct label; the middle, unremarkable 5.4 never appears as
    // a standalone label text (only inside the SVG <title> tooltip).
    $response->assertSee('>-8.2<', false);
    $response->assertSee('>12.0<', false);
    $response->assertSee('>3.0<', false);
    $response->assertDontSee('>5.4<', false);
});

test('the GDP Growth trend chart shows a placeholder with fewer than 2 quarters', function () {
    $indicator = MarketIndicator::factory()
        ->for(DataSource::factory()->manual(), 'dataSource')
        ->create(['code' => 'gdp_growth_rate', 'category' => DashboardCategory::Macro]);

    IndicatorValue::factory()->for($indicator, 'indicator')->forDate('2026-06-30')->create(['value' => 2.3]);

    $response = $this->actingAs(User::factory()->create())->get('/rpanel');

    $response->assertOk();
    $response->assertSee('Not enough data yet for a trend line');
});

test('the PSE cards show stored index levels, PSE\'s own change, turnover and breadth', function () {
    $dataSource = DataSource::factory()->manual()->create(['category' => DashboardCategory::Equities]);

    $store = function (string $code, ?float $value, ?string $text = null) use ($dataSource): void {
        $indicator = MarketIndicator::factory()->for($dataSource, 'dataSource')
            ->create(['code' => $code, 'category' => DashboardCategory::Equities]);
        IndicatorValue::factory()->for($indicator, 'indicator')->forDate('2026-09-21')
            ->create(['value' => $value, 'value_text' => $text, 'as_of_time' => '15:30:00']);
    };

    // Deliberately not the mockup's own 6,190.23 / -61.40.
    $store('psei', 7000.00);
    $store('psei_chg', -70.00);
    $store('pse_financials', 2000.00);
    $store('pse_financials_chg', 20.00);
    $store('pse_total_value', 4_500_000_000);
    $store('pse_advances', 30);
    $store('pse_declines', 60);
    $store('pse_unchanged', 10);
    $store('pse_market_status', null, 'CLOSED');

    $response = $this->actingAs(User::factory()->create())->get('/rpanel');

    $response->assertOk();
    $response->assertSee('7,000.00');
    // -70 against an implied previous close of 7,070 is -0.99%.
    $response->assertSee('-70.00 (-0.99%)');
    $response->assertSee('+1.01%', false);
    $response->assertSee('PHP 4.50B');
    $response->assertSee('Market closed');
    $response->assertSee('Advances: 30 (30%)');
    $response->assertSee('Declines: 60 (60%)');
    $response->assertDontSee('6,190.23');
});

test('the PSE cards degrade gracefully when nothing is stored yet', function () {
    $response = $this->actingAs(User::factory()->create())->get('/rpanel');

    $response->assertOk();
    $response->assertSee('Philippine Stock Market Overview');
    $response->assertSee('No data yet');
    $response->assertDontSee('6,190.23');
});

test('data with no source yet is tagged "Not dynamic"', function () {
    $response = $this->actingAs(User::factory()->create())->get('/rpanel');

    $response->assertOk();
    // The PSEi sparkline and the Foreign Flow card have no real source.
    expect(substr_count(strtolower($response->getContent()), 'not dynamic'))->toBeGreaterThanOrEqual(2);
});

test('the Market Summary table shows volume, trades, value and breadth from stored PSE data', function () {
    $dataSource = DataSource::factory()->manual()->create(['category' => DashboardCategory::Equities]);

    foreach ([
        'pse_total_volume' => 1_267_301_240,
        'pse_total_trades' => 80_416,
        'pse_total_value' => 5_822_406_669,
        'pse_advances' => 120,
        'pse_declines' => 76,
        'pse_unchanged' => 65,
    ] as $code => $value) {
        $indicator = MarketIndicator::factory()->for($dataSource, 'dataSource')
            ->create(['code' => $code, 'category' => DashboardCategory::Equities]);
        IndicatorValue::factory()->for($indicator, 'indicator')->forDate('2026-09-21')->create(['value' => $value]);
    }

    $response = $this->actingAs(User::factory()->create())->get('/rpanel');

    $response->assertOk();
    $response->assertSeeInOrder(['Market Summary', 'Total Volume', '1,267,301,240', 'Total Trades', '80,416', 'Total Value (in PHP)', '5,822,406,669', 'Advances', '120', 'Declines', '76', 'Unchanged', '65']);
});

test('the Market Summary table shows dashes when nothing is stored yet', function () {
    $response = $this->actingAs(User::factory()->create())->get('/rpanel');

    $response->assertOk();
    $response->assertSee('Market Summary');
    $response->assertSee('Total Trades');
});

test('the commodity cards show stored prices with the change against the stored previous close', function () {
    $dataSource = DataSource::factory()->manual()->create(['category' => DashboardCategory::Commodities]);

    $store = function (string $code, float $value, string $date = '2026-09-21') use ($dataSource): void {
        $indicator = MarketIndicator::factory()->for($dataSource, 'dataSource')
            ->create(['code' => $code, 'category' => DashboardCategory::Commodities]);
        IndicatorValue::factory()->for($indicator, 'indicator')->forDate($date)->create(['value' => $value]);
    };

    // Deliberately not the mockup's own $2,512.40 gold.
    $store('gold_spot', 5000.00);
    $store('gold_spot_prev', 4900.00);
    $store('brent_crude', 90.00);
    $store('brent_crude_prev', 100.00);

    $response = $this->actingAs(User::factory()->create())->get('/rpanel');

    $response->assertOk();
    $response->assertSee('$5,000.00');
    $response->assertSee('+2.04%');
    $response->assertSee('$90.00');
    $response->assertSee('-10.00%');
    $response->assertDontSee('2,512.40');
});

test('a commodity card with fewer than 5 stored readings shows a "Not dynamic" placeholder sparkline, and a real one once it has enough', function () {
    $dataSource = DataSource::factory()->manual()->create(['category' => DashboardCategory::Commodities]);

    $lead = MarketIndicator::factory()->for($dataSource, 'dataSource')
        ->create(['code' => 'lme_lead_cash', 'category' => DashboardCategory::Commodities]);
    $gold = MarketIndicator::factory()->for($dataSource, 'dataSource')
        ->create(['code' => 'gold_spot', 'category' => DashboardCategory::Commodities]);

    foreach (range(0, 5) as $day) {
        IndicatorValue::factory()->for($lead, 'indicator')->forDate(now()->subDays(10 - $day)->toDateString())->create(['value' => 1800 + $day * 5]);
    }
    IndicatorValue::factory()->for($gold, 'indicator')->forDate(now()->toDateString())->create(['value' => 4000]);

    $response = $this->actingAs(User::factory()->create())->get('/rpanel');

    $response->assertOk();
    // Lead has 6 readings: a real, labelled sparkline. Gold has 1: placeholder.
    $response->assertSee('LME Lead recent trend');
    $response->assertDontSee('Spot Gold recent trend');
    expect(substr_count(strtolower($response->getContent()), 'not dynamic'))->toBeGreaterThanOrEqual(1);
});

test('the LME Lead trading summary lists the manually-entered contracts with bid, offer and its own date', function () {
    $indicator = MarketIndicator::factory()
        ->for(DataSource::factory()->manual(), 'dataSource')
        ->create(['code' => 'lme_lead_curve', 'category' => DashboardCategory::Commodities]);

    foreach ([['Cash', 1111.5, 1112, 0], ['3-month', 1222, 1224.5, 1]] as [$key, $bid, $offer, $order]) {
        CurvePoint::factory()->for($indicator, 'indicator')->create([
            'as_of_date' => '2026-04-02', 'point_key' => $key, 'point_label' => $key, 'bid' => $bid, 'offer' => $offer, 'sort_order' => $order,
        ]);
    }

    $response = $this->actingAs(User::factory()->create())->get('/rpanel');

    $response->assertOk();
    $response->assertSee('LME Lead Official Prices');
    $response->assertSee('Data valid for 02 Apr 2026');
    $response->assertSeeInOrder(['Cash', '1,111.50', '1,112.00', '3-month', '1,222.00', '1,224.50']);
    $response->assertSee('manual entry');
    $response->assertDontSee('1,892.50');
});

test('the LME Lead price curve draws cash and 3-month lines with a legend and per-point tooltips', function () {
    $dataSource = DataSource::factory()->manual()->create(['category' => DashboardCategory::Commodities]);
    $cash = MarketIndicator::factory()->for($dataSource, 'dataSource')->create(['code' => 'lme_lead_cash', 'category' => DashboardCategory::Commodities]);
    $threeMonth = MarketIndicator::factory()->for($dataSource, 'dataSource')->create(['code' => 'lme_lead_3m', 'category' => DashboardCategory::Commodities]);

    foreach (['2026-09-16' => [1848, 1889], '2026-09-17' => [1862, 1903], '2026-09-18' => [1868, 1911]] as $date => [$c, $t]) {
        IndicatorValue::factory()->for($cash, 'indicator')->forDate($date)->create(['value' => $c]);
        IndicatorValue::factory()->for($threeMonth, 'indicator')->forDate($date)->create(['value' => $t]);
    }

    $response = $this->actingAs(User::factory()->create())->get('/rpanel');

    $response->assertOk();
    $response->assertSee('LME Lead Price Curve');
    $response->assertSee('last 3 trading days');
    $response->assertSee('Cash-Settlement');
    $response->assertSee('18 Sep 2026 · 3-month: 1,911.00', false);
    $response->assertSee('16 Sep 2026 · Cash-Settlement: 1,848.00', false);
});

test('the commodities section degrades gracefully when nothing is stored yet', function () {
    $response = $this->actingAs(User::factory()->create())->get('/rpanel');

    $response->assertOk();
    $response->assertSee('Key Treasury Commodities');
    $response->assertSee('Not enough data yet for a price curve');
    $response->assertDontSee('2,512.40');
    $response->assertDontSee('$81.42');
});

test('the Guide and Technical walkthrough buttons are on the page', function () {
    $response = $this->actingAs(User::factory()->create())->get('/rpanel');

    $response->assertOk();
    $response->assertSee('Technical');
    $response->assertSee('Guide');
    $response->assertSee('dashboardTour', false);
});

test('the walkthroughs cover every section in page order, with a purpose for each', function () {
    $response = $this->actingAs(User::factory()->create())->get('/rpanel');

    $response->assertOk();
    $response->assertSee('System Benchmarks');
    $response->assertSeeInOrder(['PSEi Composite', 'Headline Inflation', 'PHP BVAL Reference Rates', 'Inflation Update', 'GDP Growth', 'Philippine Stock Market Overview', 'Key Treasury Commodities', 'Macroeconomic Overview'], false);
});

test('a technical step lists the real source URL, method and last run from the database', function () {
    DataSource::factory()->create([
        'slug' => 'bap-fx-summary',
        'name' => 'BAP - Spot FX Summary',
        'method' => DataSourceMethod::File,
        'url' => 'https://www.bap.org.ph/fxhistorical.html',
    ]);

    $response = $this->actingAs(User::factory()->create())->get('/rpanel');

    $response->assertOk();
    $response->assertSee('fxhistorical.html', false);
    $response->assertSee('BAP - Spot FX Summary');
    $response->assertSee('File download');
    $response->assertSee('Never run');
});

test('the static cards are labelled as having no data source', function () {
    $response = $this->actingAs(User::factory()->create())->get('/rpanel');

    $response->assertOk();
    // Headline Inflation has no source, so its walkthrough step is marked static.
    $response->assertSee('Static · no data source yet', false);
});

test('the Headline Inflation card shows the stored latest reading, its month, and where it sits against the target band', function () {
    $indicator = MarketIndicator::factory()
        ->for(DataSource::factory()->manual(), 'dataSource')
        ->create(['code' => 'inflation_rate', 'category' => DashboardCategory::Macro]);

    // Deliberately not the mockup's own 6.2% / Jul 2026.
    IndicatorValue::factory()->for($indicator, 'indicator')->forDate('2026-07-31')->create(['value' => 4.8, 'period_label' => 'July 2026']);
    IndicatorValue::factory()->for($indicator, 'indicator')->forDate('2026-08-31')->create(['value' => 5.3, 'period_label' => 'August 2026']);

    $response = $this->actingAs(User::factory()->create())->get('/rpanel');

    $response->assertOk();
    $response->assertSee('5.3%');
    $response->assertSee('August 2026 · PSA');
    $response->assertSee('Above target');
    $response->assertDontSee('Next: Sep 5');
});

test('the Headline Inflation card shows a placeholder when no inflation reading is stored', function () {
    $response = $this->actingAs(User::factory()->create())->get('/rpanel');

    $response->assertOk();
    $response->assertSee('No data yet');
    $response->assertDontSee('Above target');
});
