<?php

namespace App\Filament\Pages;

use App\Models\CurvePoint;
use App\Models\DataSource;
use App\Models\IndicatorValue;
use App\Models\MarketIndicator;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class Dashboard extends BaseDashboard
{
    protected static ?string $title = 'Command Center';

    private const string USD_PHP_CODE = 'usdphp_spot';

    private const string BVAL_CODE = 'php_bval_curve';

    private const string INFLATION_CODE = 'inflation_rate';

    private const string GDP_CODE = 'gdp_growth_rate';

    /**
     * Every indicator code the view currently reads dynamically, for the
     * BSP Key Rates strip.
     *
     * @var list<string>
     */
    private const KEY_RATE_CODES = [
        'inflation_rate',
        'on_lending_rate',
        'target_rrp_rate',
        'on_deposit_rate',
        'on_rrp_rate',
        'bsp_securities_28d',
        'bsp_securities_56d',
        'on_reference_rate',
        'tdf_7d',
        'tdf_14d',
        'tdf_28d',
    ];

    /**
     * The 12 BAP FX Summary indicators, in the order the "BAP Spot
     * USD/PHP" table displays its rows. All 12 are always fetched
     * together for the same `as_of_date` - see BapFxSummaryFetcher.
     *
     * @var list<string>
     */
    private const BAP_FX_CODES = [
        'bap_usdphp_open_time',
        'bap_usdphp_open',
        'bap_usdphp_high',
        'bap_usdphp_low',
        'bap_usdphp_close',
        'bap_usdphp_am_wa',
        'bap_usdphp_am_volume',
        'bap_usdphp_pm_wa',
        'bap_usdphp_pm_volume',
        'bap_usdphp_wa',
        'bap_usdphp_total_volume',
        'bap_usdphp_settlement',
    ];

    /**
     * Every PSE indicator the PSEi hero card and "Philippine Stock
     * Market Overview" read - see PseIndexSummaryFetcher. Each index
     * has a level (`psei`) and a signed point change (`psei_chg`).
     *
     * @var list<string>
     */
    private const PSE_CODES = [
        'psei', 'psei_chg',
        'pse_all_shares', 'pse_all_shares_chg',
        'pse_financials', 'pse_financials_chg',
        'pse_industrial', 'pse_industrial_chg',
        'pse_holding_firms', 'pse_holding_firms_chg',
        'pse_property', 'pse_property_chg',
        'pse_services', 'pse_services_chg',
        'pse_mining_oil', 'pse_mining_oil_chg',
        'pse_total_volume', 'pse_total_trades', 'pse_total_value', 'pse_advances', 'pse_declines', 'pse_unchanged', 'pse_market_status',
    ];

    /**
     * The four Key Treasury Commodities cards. Gold, wheat and Brent
     * carry their own previous close (`<code>_prev`, from Markets
     * Insider); LME Lead's previous value is simply its prior trading
     * day in the stored history.
     *
     * @var list<string>
     */
    private const COMMODITY_CODES = [
        'gold_spot', 'gold_spot_prev',
        'wheat_price', 'wheat_price_prev',
        'brent_crude', 'brent_crude_prev',
        'lme_lead_cash',
    ];

    public function getHeading(): string
    {
        return '';
    }

    public function getMaxContentWidth(): Width|string|null
    {
        return Width::Full;
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                View::make('filament.pages.dashboard-overview')
                    ->viewData($this->getDashboardViewData()),
            ]);
    }

    /**
     * Cards wired to real database values so far: the USD/PHP hero card
     * (Module 1's proof point), the BSP Key Rates strip, the "BAP Spot
     * USD/PHP" table, and the "PHP BVAL Reference Rates" table. Every
     * other card on the page is still placeholder markup until its own
     * source module lands.
     *
     * All indicators are fetched in one query with their latest value
     * eager-loaded (`->with('latestValue')`), instead of one query per
     * card - avoids the N+1 that a naive per-code lookup would cause.
     *
     * @return array<string, mixed>
     */
    protected function getDashboardViewData(): array
    {
        $codes = [self::USD_PHP_CODE, ...self::KEY_RATE_CODES, ...self::BAP_FX_CODES, ...self::PSE_CODES];

        $indicators = MarketIndicator::query()
            ->whereIn('code', $codes)
            ->with('latestValue')
            ->get()
            ->keyBy('code');

        $usdPhpIndicator = $indicators->get(self::USD_PHP_CODE);
        $usdPhpLatest = $usdPhpIndicator?->latestValue;
        $usdPhpPrevious = $usdPhpIndicator?->previousValue($usdPhpLatest);

        return [
            'usdPhp' => $this->buildSnapshot($usdPhpLatest, $usdPhpPrevious),
            'keyRates' => collect(self::KEY_RATE_CODES)
                ->mapWithKeys(fn (string $code): array => [
                    $code => $this->buildSnapshot($indicators->get($code)?->latestValue),
                ])
                ->all(),
            'bapFx' => $this->buildBapFxSnapshot($indicators),
            'pse' => collect(self::PSE_CODES)
                ->mapWithKeys(fn (string $code): array => [
                    $code => $this->buildSnapshot($indicators->get($code)?->latestValue),
                ])
                ->all(),
            'bval' => $this->buildBvalCurve(),
            'inflationHistory' => $this->buildInflationHistory(),
            'inflationRecent' => $this->buildInflationRecent(),
            'gdpQuarters' => $this->buildGdpGrowthQuarters(),
            'gdpHistory' => $this->buildGdpGrowthHistory(),
            'gdpTrend' => $this->buildGdpGrowthTrend(),
            'commodities' => $this->buildCommodityCards(),
            'lmeSummary' => $this->buildLmeTradingSummary(),
            'lmeSeries' => $this->buildLmeLeadSeries(),
            'tour' => $this->buildTour(),
        ];
    }

    /**
     * @return array{value: float|null, valueText: string|null, previousValue: float|null, previousValueText: string|null, asOfDate: Carbon|null, previousAsOfDate: Carbon|null, asOfTime: string|null, periodLabel: string|null, sourceNote: string|null, percentChange: float|null}
     */
    private function buildSnapshot(?IndicatorValue $latest, ?IndicatorValue $previous = null): array
    {
        return [
            'value' => $latest?->value !== null ? (float) $latest->value : null,
            'valueText' => $latest?->value_text,
            'previousValue' => $previous?->value !== null ? (float) $previous->value : null,
            'previousValueText' => $previous?->value_text,
            'asOfDate' => $latest?->as_of_date,
            'previousAsOfDate' => $previous?->as_of_date,
            'asOfTime' => $latest?->as_of_time,
            'periodLabel' => $latest?->period_label,
            'sourceNote' => $latest?->source_note,
            'percentChange' => $latest?->percentChangeFrom($previous),
        ];
    }

    /**
     * All 12 BAP FX indicators are upserted together with the same
     * `as_of_date` every run (see BapFxSummaryFetcher), so "previous"
     * for all of them is simply the one prior date shared across them -
     * fetched as a single batch instead of calling `previousValue()`
     * (one query each) 12 times over.
     *
     * @param  Collection<string, MarketIndicator>  $indicators
     * @return array<string, array{value: float|null, valueText: string|null, previousValue: float|null, asOfDate: Carbon|null, periodLabel: string|null, sourceNote: string|null, percentChange: float|null}>
     */
    private function buildBapFxSnapshot(Collection $indicators): array
    {
        $bapIndicators = collect(self::BAP_FX_CODES)
            ->map(fn (string $code): ?MarketIndicator => $indicators->get($code))
            ->filter();

        $latestDate = $bapIndicators->first()?->latestValue?->as_of_date;
        $bapIds = $bapIndicators->pluck('id')->values();

        $previousValues = collect();

        if ($latestDate && $bapIds->isNotEmpty()) {
            $previousDate = IndicatorValue::query()
                ->whereIn('market_indicator_id', $bapIds)
                ->where('as_of_date', '<', $latestDate->toDateString())
                ->max('as_of_date');

            if ($previousDate) {
                $previousValues = IndicatorValue::query()
                    ->whereIn('market_indicator_id', $bapIds)
                    ->where('as_of_date', $previousDate)
                    ->get()
                    ->keyBy('market_indicator_id');
            }
        }

        return collect(self::BAP_FX_CODES)
            ->mapWithKeys(function (string $code) use ($indicators, $previousValues): array {
                $indicator = $indicators->get($code);

                return [
                    $code => $this->buildSnapshot(
                        $indicator?->latestValue,
                        $indicator ? $previousValues->get($indicator->id) : null,
                    ),
                ];
            })
            ->all();
    }

    /**
     * The last two dates stored for the BVAL curve, grouped by tenor -
     * two small queries regardless of how many tenors there are, rather
     * than one `previousValue`-style query per tenor.
     *
     * @return list<array{tenor: string, value: float, previousValue: float|null, sortOrder: int}>
     */
    private function buildBvalCurve(): array
    {
        $indicator = MarketIndicator::query()->where('code', self::BVAL_CODE)->first();

        if (! $indicator) {
            return [];
        }

        $recentDates = $indicator->curvePoints()
            ->toBase()
            ->select('as_of_date')
            ->distinct()
            ->orderByDesc('as_of_date')
            ->limit(2)
            ->pluck('as_of_date');

        if ($recentDates->isEmpty()) {
            return [];
        }

        return $indicator->curvePoints()
            ->whereIn('as_of_date', $recentDates)
            ->orderBy('sort_order')
            ->get()
            ->groupBy('point_key')
            ->map(function (Collection $points): array {
                $sorted = $points->sortByDesc('as_of_date')->values();
                /** @var CurvePoint $today */
                $today = $sorted->first();
                $previous = $sorted->get(1);

                return [
                    'tenor' => $today->point_key,
                    'value' => (float) $today->value,
                    'previousValue' => $previous ? (float) $previous->value : null,
                    'sortOrder' => $today->sort_order,
                ];
            })
            ->sortBy('sortOrder')
            ->values()
            ->all();
    }

    /**
     * The "Inflation Update" table - PSA's own "Table B. Year-on-Year
     * Inflation Rates" layout (months as rows, years as columns, plus
     * an average-for-the-year row), built from every value
     * `market:backfill-inflation` has stored for this indicator. One
     * query for the whole table, grouped in PHP rather than queried
     * year by year.
     *
     * @return array{years: list<int>, months: list<array{label: string, values: array<int, float|null>}>, averages: array<int, float|null>}
     */
    private function buildInflationHistory(): array
    {
        $indicator = MarketIndicator::query()->where('code', self::INFLATION_CODE)->first();

        if (! $indicator) {
            return ['years' => [], 'months' => [], 'averages' => []];
        }

        $byYearMonth = [];

        $indicator->values()
            ->whereNotNull('value')
            ->orderBy('as_of_date')
            ->get(['as_of_date', 'value'])
            ->each(function (IndicatorValue $indicatorValue) use (&$byYearMonth): void {
                $byYearMonth[$indicatorValue->as_of_date->year][$indicatorValue->as_of_date->month] = (float) $indicatorValue->value;
            });

        $years = array_keys($byYearMonth);
        sort($years);

        $months = [];

        for ($month = 1; $month <= 12; $month++) {
            $months[] = [
                'label' => Carbon::create(2000, $month, 1)->format('M'),
                'values' => collect($years)->mapWithKeys(fn (int $year): array => [$year => $byYearMonth[$year][$month] ?? null])->all(),
            ];
        }

        $averages = collect($years)->mapWithKeys(function (int $year) use ($byYearMonth): array {
            $monthValues = array_values($byYearMonth[$year]);

            return [$year => $monthValues === [] ? null : array_sum($monthValues) / count($monthValues)];
        })->all();

        return ['years' => $years, 'months' => $months, 'averages' => $averages];
    }

    /**
     * The last 4 real, published quarters for the "GDP Growth, YoY %"
     * card - the mockup's own 2 "forecast" bars are dropped, since we
     * have no forecast source; every bar shown here is an actual PSA
     * print. Bar heights are scaled relative to the largest value in
     * this set, not a fixed axis, since quarterly GDP growth has swung
     * from double-digit negative to double-digit positive historically
     * (see the COVID-era prints Module 3's full backfill also captured).
     *
     * @return list<array{label: string, value: float, asOfDate: Carbon, heightPercent: float}>
     */
    private function buildGdpGrowthQuarters(): array
    {
        $indicator = MarketIndicator::query()->where('code', self::GDP_CODE)->first();

        if (! $indicator) {
            return [];
        }

        $quarters = $indicator->values()
            ->orderByDesc('as_of_date')
            ->limit(4)
            ->get()
            ->sortBy('as_of_date')
            ->values()
            ->map(fn (IndicatorValue $value): array => [
                'label' => 'Q'.$value->as_of_date->quarter." '".$value->as_of_date->format('y'),
                'value' => (float) $value->value,
                'asOfDate' => $value->as_of_date,
            ]);

        $maxValue = $quarters->max('value') ?: 1.0;

        return $quarters
            ->map(fn (array $quarter): array => [
                ...$quarter,
                'heightPercent' => max(8.0, min(100.0, ($quarter['value'] / $maxValue) * 100)),
            ])
            ->all();
    }

    /**
     * The full "GDP Growth History" table - quarters as rows, years as
     * columns, plus an average-for-the-year row - the same layout as
     * {@see buildInflationHistory()}, just quarterly instead of
     * monthly. Built from every quarter `market:backfill-gdp` has
     * stored, one query for the whole table.
     *
     * @return array{years: list<int>, quarters: list<array{label: string, values: array<int, float|null>}>, averages: array<int, float|null>}
     */
    private function buildGdpGrowthHistory(): array
    {
        $indicator = MarketIndicator::query()->where('code', self::GDP_CODE)->first();

        if (! $indicator) {
            return ['years' => [], 'quarters' => [], 'averages' => []];
        }

        $byYearQuarter = [];

        $indicator->values()
            ->whereNotNull('value')
            ->orderBy('as_of_date')
            ->get(['as_of_date', 'value'])
            ->each(function (IndicatorValue $indicatorValue) use (&$byYearQuarter): void {
                $byYearQuarter[$indicatorValue->as_of_date->year][$indicatorValue->as_of_date->quarter] = (float) $indicatorValue->value;
            });

        $years = array_keys($byYearQuarter);
        sort($years);

        $quarters = [];

        for ($quarter = 1; $quarter <= 4; $quarter++) {
            $quarters[] = [
                'label' => "Q{$quarter}",
                'values' => collect($years)->mapWithKeys(fn (int $year): array => [$year => $byYearQuarter[$year][$quarter] ?? null])->all(),
            ];
        }

        $averages = collect($years)->mapWithKeys(function (int $year) use ($byYearQuarter): array {
            $quarterValues = array_values($byYearQuarter[$year]);

            return [$year => $quarterValues === [] ? null : array_sum($quarterValues) / count($quarterValues)];
        })->all();

        return ['years' => $years, 'quarters' => $quarters, 'averages' => $averages];
    }

    /**
     * The last 28 real, published quarters (7 years - the same window
     * PSA's own "Figure 1" line chart covers) for a proper trend line,
     * matching the reference figure the user shared. Flags the latest
     * point and the two extremes (min/max) so the view can label those
     * directly instead of every point - labeling every single value on
     * a 28-point line is cluttered; the rest stay reachable via a
     * hover tooltip and the "GDP Growth History" table below it.
     *
     * @return list<array{label: string, value: float, asOfDate: Carbon, isLatest: bool, isMax: bool, isMin: bool}>
     */
    private function buildGdpGrowthTrend(): array
    {
        $indicator = MarketIndicator::query()->where('code', self::GDP_CODE)->first();

        if (! $indicator) {
            return [];
        }

        $points = $indicator->values()
            ->orderByDesc('as_of_date')
            ->limit(28)
            ->get()
            ->sortBy('as_of_date')
            ->values()
            ->map(fn (IndicatorValue $value): array => [
                'label' => 'Q'.$value->as_of_date->quarter.' '.$value->as_of_date->format('y'),
                'value' => (float) $value->value,
                'asOfDate' => $value->as_of_date,
            ]);

        if ($points->isEmpty()) {
            return [];
        }

        $maxValue = $points->max('value');
        $minValue = $points->min('value');
        $lastIndex = $points->count() - 1;

        return $points
            ->map(fn (array $point, int $index): array => [
                ...$point,
                'isLatest' => $index === $lastIndex,
                'isMax' => $point['value'] === $maxValue,
                'isMin' => $point['value'] === $minValue,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<string, array{value: float|null, previous: float|null, percentChange: float|null, asOfDate: Carbon|null, note: string|null, history: list<float>}>
     */
    private function buildCommodityCards(): array
    {
        $indicators = MarketIndicator::query()
            ->whereIn('code', self::COMMODITY_CODES)
            ->with('latestValue')
            ->get()
            ->keyBy('code');

        $mainCodes = ['gold_spot', 'wheat_price', 'brent_crude', 'lme_lead_cash'];
        $idToCode = $indicators->filter(fn (MarketIndicator $indicator): bool => in_array($indicator->code, $mainCodes, true))
            ->mapWithKeys(fn (MarketIndicator $indicator): array => [$indicator->id => $indicator->code]);

        // One bounded query for every card's sparkline, grouped in PHP.
        $history = IndicatorValue::query()
            ->whereIn('market_indicator_id', $idToCode->keys())
            ->whereNotNull('value')
            ->where('as_of_date', '>=', now()->subDays(120)->toDateString())
            ->orderBy('as_of_date')
            ->get(['market_indicator_id', 'as_of_date', 'value'])
            ->groupBy(fn (IndicatorValue $value): string => $idToCode[$value->market_indicator_id])
            ->map(fn (Collection $values): array => $values->pluck('value')->map(fn ($value): float => (float) $value)->slice(-60)->values()->all());

        $cards = [];

        foreach ($mainCodes as $code) {
            $latest = $indicators->get($code)?->latestValue;
            $value = $latest?->value !== null ? (float) $latest->value : null;
            $series = $history->get($code, []);

            $previous = $code === 'lme_lead_cash'
                ? (count($series) >= 2 ? $series[count($series) - 2] : null)
                : ($indicators->get($code.'_prev')?->latestValue?->value !== null ? (float) $indicators->get($code.'_prev')->latestValue->value : null);

            $cards[$code] = [
                'value' => $value,
                'previous' => $previous,
                'percentChange' => ($value !== null && $previous) ? ($value - $previous) / $previous * 100 : null,
                'asOfDate' => $latest?->as_of_date,
                'note' => $latest?->source_note,
                'history' => $series,
            ];
        }

        return $cards;
    }

    /**
     * The LME Lead "Trading Summary" (Contract / Bid / Offer) - the latest
     * date's manually-entered curve points.
     *
     * @return array{asOfDate: Carbon|null, points: list<array{label: string, bid: float|null, offer: float|null}>}
     */
    private function buildLmeTradingSummary(): array
    {
        $indicator = MarketIndicator::query()->where('code', 'lme_lead_curve')->first();
        $latestDate = $indicator?->curvePoints()->toBase()->max('as_of_date');

        if (! $latestDate) {
            return ['asOfDate' => null, 'points' => []];
        }

        $points = $indicator->curvePoints()
            ->where('as_of_date', $latestDate)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return [
            'asOfDate' => $points->first()->as_of_date,
            'points' => $points->map(fn (CurvePoint $point): array => [
                'label' => $point->point_label ?? $point->point_key,
                'bid' => $point->bid !== null ? (float) $point->bid : null,
                'offer' => $point->offer !== null ? (float) $point->offer : null,
            ])->all(),
        ];
    }

    /**
     * The last 20 trading days of LME Lead Cash-Settlement and 3-month
     * prices, oldest first, for the price chart.
     *
     * @return list<array{date: Carbon, cash: float, threeMonth: float}>
     */
    private function buildLmeLeadSeries(): array
    {
        $ids = MarketIndicator::query()->whereIn('code', ['lme_lead_cash', 'lme_lead_3m'])->pluck('id', 'code');

        if ($ids->count() < 2) {
            return [];
        }

        $byDate = IndicatorValue::query()
            ->whereIn('market_indicator_id', $ids->values())
            ->whereNotNull('value')
            ->orderByDesc('as_of_date')
            ->limit(40)
            ->get(['market_indicator_id', 'as_of_date', 'value'])
            ->groupBy(fn (IndicatorValue $value): string => $value->as_of_date->toDateString());

        return $byDate
            ->filter(fn (Collection $rows): bool => $rows->count() === 2)
            ->map(fn (Collection $rows, string $date): array => [
                'date' => Carbon::parse($date),
                'cash' => (float) $rows->firstWhere('market_indicator_id', $ids['lme_lead_cash'])->value,
                'threeMonth' => (float) $rows->firstWhere('market_indicator_id', $ids['lme_lead_3m'])->value,
            ])
            ->sortBy('date')
            ->values()
            ->all();
    }

    /**
     * Each entry is one stop on the page. `target` is the data-tour key the
     * Blade view puts on that section. `purpose` is the plain-English Guide
     * text; `how` is the Technical text; `slugs` are the data_sources rows
     * that feed it (empty = static, no source yet).
     *
     * @return list<array{target: string, title: string, purpose: string, how: string, slugs: list<string>, note: string|null}>
     */
    private function tourDefinitions(): array
    {
        return [
            [
                'target' => 'hero-usdphp',
                'title' => 'USD/PHP Spot',
                'purpose' => 'The latest official BSP peso-dollar reference rate. The badge shows the change from the previous stored day. The icon in the corner opens a larger view.',
                'how' => 'Read from the BSP Daily Reference Exchange Rate Bulletin, RERB.xlsx (a daily Excel file at a fixed URL), by BspUsdPhpFetcher.',
                'slugs' => ['bsp-usdphp'],
                'note' => null,
            ],
            [
                'target' => 'hero-pse',
                'title' => 'PSEi Composite',
                'purpose' => 'The level of the Philippine Stock Exchange main index, its change for the day, and the total value traded.',
                'how' => 'Read from the PSE Edge Index Summary page, by PseIndexSummaryFetcher.',
                'slugs' => ['pse-index-summary'],
                'note' => 'Source is PSE Edge, which states its materials are for personal, non-commercial use.',
            ],
            [
                'target' => 'hero-inflation',
                'title' => 'Headline Inflation',
                'purpose' => 'Year-on-year consumer inflation from PSA, for the latest month. It is compared with the BSP target band of 2.0% to 4.0%, and the bars show the last six months.',
                'how' => 'Read from the PSA OpenSTAT API by PsaInflationFetcher, the same stored values as the Inflation Rate card in the BSP Policy strip.',
                'slugs' => ['psa-inflation'],
                'note' => null,
            ],
            [
                'target' => 'key-rates',
                'title' => 'BSP Policy & System Benchmarks',
                'purpose' => 'Central bank policy rates and related benchmarks. Each card has its own date, because BSP updates them at different times. Not dynamic: 7 of the 11 cards. Enter their values by hand in Market Indicators (http://treasury-market-updates.test/rpanel/market-indicators, Enter Value). The other 4 are automatic.',
                'how' => 'Three rates are read automatically from BSP\'s sefi.pdf (BspKeyRatesFetcher), and Inflation Rate is read from the PSA OpenSTAT API. The other rates are typed in by hand in the admin.',
                'slugs' => ['bsp-key-rates', 'psa-inflation'],
                'note' => '3 of 11 rates are automatic, the rest are manual entry.',
            ],
            [
                'target' => 'bap-table',
                'title' => 'BAP Spot USD/PHP',
                'purpose' => 'Detailed daily peso-dollar fixing from the Bankers Association of the Philippines: open, high, low, close, weighted averages, volumes and the FX settlement rate. "Previous" is the prior banking day.',
                'how' => 'Read from the last row of BAP\'s FX historical HTML table (fxhistorical.html), by BapFxSummaryFetcher. Each column is stored as its own indicator.',
                'slugs' => ['bap-fx-summary'],
                'note' => null,
            ],
            [
                'target' => 'bval-table',
                'title' => 'PHP BVAL Reference Rates',
                'purpose' => 'Philippine government bond yields from 1 month to 25 years, today and the previous day. Change is in basis points (bps): 1 bps = 0.01%.',
                'how' => 'Read from PDS\'s public GraphQL API (the same one behind the PDS widget), by PdsBvalFetcher. The subscriber-only MarketPage system is not used.',
                'slugs' => ['pds-bval'],
                'note' => 'Uses a public API key found through a web search, not issued to Ramcar directly.',
            ],
            [
                'target' => 'inflation-update',
                'title' => 'Inflation Update',
                'purpose' => 'Year-on-year inflation for every month since 2019. The last row is the average for each year.',
                'how' => 'Read from PSA\'s OpenSTAT PxWeb API by PsaInflationFetcher. The history was loaded once with market:backfill-inflation.',
                'slugs' => ['psa-inflation'],
                'note' => null,
            ],
            [
                'target' => 'gdp-history',
                'title' => 'GDP Growth',
                'purpose' => 'Real GDP growth year on year, quarter by quarter, from 2001. Red dots mark contraction quarters. The table below has the exact figures.',
                'how' => 'Read from PSA\'s quarterly National Accounts table (PxWeb) by PsaGdpGrowthFetcher. The history was loaded once with market:backfill-gdp.',
                'slugs' => ['psa-gdp'],
                'note' => null,
            ],
            [
                'target' => 'pse-overview',
                'title' => 'Philippine Stock Market Overview',
                'purpose' => 'Sector indices, total volume and value, and advancing, declining and unchanged stocks for the day. Static / Not dynamic: the Foreign Flow card. It has no source and no manual entry yet, so it stays as placeholder until one is added.',
                'how' => 'Read from the PSE Edge Index Summary page by PseIndexSummaryFetcher. Foreign Flow has no source and is tagged "Not dynamic".',
                'slugs' => ['pse-index-summary'],
                'note' => 'Foreign Flow is static. PSE Edge is for personal, non-commercial use.',
            ],
            [
                'target' => 'commodities',
                'title' => 'Key Treasury Commodities',
                'purpose' => 'Brent crude, spot gold, wheat and LME lead prices. Each sparkline stays a grey Static / Not dynamic placeholder until that price has at least 5 stored readings. Below them, the LME lead trading summary (bid/offer) is Manual entry. Add its rows in Market Indicators (http://treasury-market-updates.test/rpanel/market-indicators, LME Lead Official Prices, Curve points). The price curve is automatic.',
                'how' => 'Gold, wheat and Brent are read from Markets Insider pages (MarketsInsiderPriceFetcher). LME lead cash and 3-month prices are read from Westmetall (WestmetallLeadFetcher). The bid/offer table is typed in by hand.',
                'slugs' => ['mi-gold', 'mi-wheat', 'mi-brent', 'westmetall-lme-lead', 'lme-lead-official-prices'],
                'note' => 'Markets Insider prices are indicative, not official fixings. The LME bid/offer table is manual.',
            ],
            [
                'target' => 'macro',
                'title' => 'Macroeconomic Overview',
                'purpose' => 'A collapsible section with the inflation trend and GDP growth cards. Click the header to expand or collapse it. Static / Not dynamic: the inflation card, which still shows sample figures.',
                'how' => 'The GDP card is read from the PSA GDP source. The inflation card is static, since it still has sample figures.',
                'slugs' => ['psa-gdp'],
                'note' => 'Inflation card is static.',
            ],
        ];
    }

    /**
     * @return array{guide: list<array{target: string, title: string, body: string}>, technical: list<array{target: string, title: string, body: string, static: bool, note: string|null, sources: list<array<string, mixed>>}>}
     */
    private function buildTour(): array
    {
        $definitions = $this->tourDefinitions();

        $sources = DataSource::query()
            ->whereIn('slug', collect($definitions)->pluck('slugs')->flatten()->unique()->values())
            ->with('latestRun')
            ->get()
            ->keyBy('slug');

        return [
            'guide' => collect($definitions)
                ->map(fn (array $step): array => ['target' => $step['target'], 'title' => $step['title'], 'body' => $step['purpose']])
                ->values()
                ->all(),
            'technical' => collect($definitions)
                ->map(fn (array $step): array => [
                    'target' => $step['target'],
                    'title' => $step['title'],
                    'body' => $step['how'],
                    'static' => $step['slugs'] === [],
                    'note' => $step['note'],
                    'sources' => collect($step['slugs'])
                        ->map(fn (string $slug): ?DataSource => $sources->get($slug))
                        ->filter()
                        ->map(fn (DataSource $source): array => [
                            'name' => $source->name,
                            'method' => $source->method->getLabel(),
                            'url' => $source->url,
                            'description' => $source->description,
                            'lastRun' => $source->latestRun
                                ? $source->latestRun->status->getLabel().' · '.$source->latestRun->started_at?->format('M j, Y g:i A')
                                : 'Never run',
                        ])
                        ->values()
                        ->all(),
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * The last six stored months of headline inflation, oldest first, for
     * the Headline Inflation card's mini bar chart.
     *
     * @return list<array{label: string, value: float, asOfDate: Carbon, heightPercent: float}>
     */
    private function buildInflationRecent(): array
    {
        $indicator = MarketIndicator::query()->where('code', self::INFLATION_CODE)->first();

        if (! $indicator) {
            return [];
        }

        $months = $indicator->values()
            ->whereNotNull('value')
            ->orderByDesc('as_of_date')
            ->limit(6)
            ->get()
            ->sortBy('as_of_date')
            ->values()
            ->map(fn (IndicatorValue $value): array => [
                'label' => $value->as_of_date->format('M'),
                'value' => (float) $value->value,
                'asOfDate' => $value->as_of_date,
            ]);

        $maxValue = $months->max('value') ?: 1.0;

        return $months
            ->map(fn (array $month): array => [
                ...$month,
                'heightPercent' => max(8.0, min(100.0, ($month['value'] / $maxValue) * 100)),
            ])
            ->all();
    }
}
