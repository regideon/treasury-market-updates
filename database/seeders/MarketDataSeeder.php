<?php

namespace Database\Seeders;

use App\Enums\DashboardCategory;
use App\Enums\DataSourceMethod;
use App\Models\CurvePoint;
use App\Models\DataSource;
use App\Models\IndicatorValue;
use App\Models\MarketIndicator;
use App\Services\DataSources\Fetchers\BapFxSummaryFetcher;
use App\Services\DataSources\Fetchers\BspKeyRatesFetcher;
use App\Services\DataSources\Fetchers\BspUsdPhpFetcher;
use App\Services\DataSources\Fetchers\MarketsInsiderPriceFetcher;
use App\Services\DataSources\Fetchers\PdsBvalFetcher;
use App\Services\DataSources\Fetchers\PsaGdpGrowthFetcher;
use App\Services\DataSources\Fetchers\PsaInflationFetcher;
use App\Services\DataSources\Fetchers\PseIndexSummaryFetcher;
use App\Services\DataSources\Fetchers\WestmetallLeadFetcher;
use Illuminate\Database\Seeder;

/**
 * Module 1's proof point (USD/PHP) plus the BSP Key Rates widget. USD/PHP,
 * 3 of the 11 BSP Key Rates indicators, Inflation Rate, the BAP FX
 * Summary table, and the PHP BVAL curve now have a real Module 2 fetcher
 * each - see each fetcher's own docblock for what it covers and why.
 * Every indicator keeps its own `as_of_date` on purpose - these sources
 * don't republish everything together, so some values are days old and
 * some (the ON Reference Rate, the 28-day TDF) haven't changed in years.
 */
class MarketDataSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedUsdPhp();
        $this->seedBspKeyRates();
        $this->seedPsaInflation();
        $this->seedBapFxSummary();
        $this->seedPdsBval();
        $this->seedPsaGdp();
        $this->seedPseIndexSummary();
        $this->seedMarketsInsiderCommodities();
        $this->seedLmeLead();
    }

    private function seedUsdPhp(): void
    {
        $dataSource = DataSource::updateOrCreate(
            ['slug' => 'bsp-usdphp'],
            [
                'name' => 'BSP - USD/PHP Reference Rate',
                'category' => DashboardCategory::Fx,
                'method' => DataSourceMethod::File,
                // BSP's Daily Reference Exchange Rate Bulletin - a small
                // same-day file at a fixed URL, republished every banking
                // day. NOT pesodollar.xlsx, which is a bulk historical
                // archive that lags real time; that one is a Module 3
                // backfill source instead.
                'url' => 'https://www.bsp.gov.ph/Statistics/RERB/RERB.xlsx',
                'description' => 'Small Excel file (~25 KB) BSP republishes every banking day - the "Daily Reference Exchange Rate Bulletin." The closest thing to a live USD/PHP rate. Not the same as pesodollar.xlsx, a bulk 1978-present archive that lags real time.',
                'is_active' => true,
                'config' => ['fetcher' => BspUsdPhpFetcher::class],
            ],
        );

        $indicator = MarketIndicator::updateOrCreate(
            ['code' => 'usdphp_spot'],
            [
                'data_source_id' => $dataSource->id,
                'label' => 'USD/PHP Spot Fixing',
                'unit' => 'PHP',
                'category' => DashboardCategory::Fx,
                'display_order' => 1,
            ],
        );

        IndicatorValue::updateOrCreate(
            ['market_indicator_id' => $indicator->id, 'as_of_date' => now()->subDay()->toDateString()],
            ['value' => 61.7450],
        );

        IndicatorValue::updateOrCreate(
            ['market_indicator_id' => $indicator->id, 'as_of_date' => now()->toDateString()],
            ['value' => 61.7350],
        );
    }

    private function seedBspKeyRates(): void
    {
        $dataSource = DataSource::updateOrCreate(
            ['slug' => 'bsp-key-rates'],
            [
                'name' => 'BSP Key Rates',
                'category' => DashboardCategory::Rates,
                'method' => DataSourceMethod::File,
                // Covers only on_rrp_rate, tdf_7d and bsp_securities_28d -
                // see BspKeyRatesFetcher's docblock. The other 8
                // indicators below stay on their manually-seeded values;
                // the pipeline simply won't touch codes a fetch result
                // doesn't include.
                'url' => 'https://www.bsp.gov.ph/statistics/keystat/sefi.pdf',
                'description' => 'BSP\'s "Selected Economic and Financial Indicators" PDF - a big multi-topic statistics document (GDP, employment, interest rates, and more), not a dedicated rates file. Only 3 of these 11 Key Rates cards are reliably parseable from it; the rest are blank, stale, or footnote-only in this document.',
                'is_active' => true,
                'config' => ['fetcher' => BspKeyRatesFetcher::class],
            ],
        );

        // code => [label, unit, display_order, value|null, value_text|null, as_of_date, period_label|null, source_note|null]
        // Note: inflation_rate is NOT here - it's actually a PSA figure,
        // not a BSP one (BSP's Key Rates widget just displays it
        // alongside its own rates). See seedPsaInflation() below.
        $rates = [
            'on_lending_rate' => ['ON Lending Rate', '%', 2, 4.75, null, '2026-04-07', null, null],
            'target_rrp_rate' => ['Target RRP Rate', '%', 3, 4.25, null, '2026-04-07', null, null],
            'on_deposit_rate' => ['ON Deposit Rate', '%', 4, 3.75, null, '2026-04-07', null, null],
            'on_rrp_rate' => ['ON RRP Rate', '%', 5, 4.2441, null, '2026-04-07', null, 'Accepted: 4.2300%-4.2800%'],
            'bsp_securities_28d' => ['28-day BSP Securities (WAIR)', '%', 6, 4.5265, null, '2026-04-06', null, null],
            'bsp_securities_56d' => ['56-day BSP Securities (WAIR)', '%', 7, 5.1156, null, '2025-10-24', null, null],
            'on_reference_rate' => ['ON Reference Rate', '%', 8, null, '****', '2023-09-08', null, 'Not currently in use'],
            'tdf_7d' => ['7-day TDF (WAIR)', '%', 9, 4.2078, null, '2026-04-01', null, null],
            'tdf_14d' => ['14-day TDF (WAIR)', '%', 10, 4.8216, null, '2025-10-29', null, null],
            'tdf_28d' => ['28-day TDF (WAIR)', '%', 11, 1.84, null, '2020-10-07', null, null],
        ];

        foreach ($rates as $code => [$label, $unit, $order, $value, $valueText, $asOfDate, $periodLabel, $sourceNote]) {
            $indicator = MarketIndicator::updateOrCreate(
                ['code' => $code],
                [
                    'data_source_id' => $dataSource->id,
                    'label' => $label,
                    'unit' => $unit,
                    'category' => DashboardCategory::Rates,
                    'display_order' => $order,
                ],
            );

            IndicatorValue::updateOrCreate(
                ['market_indicator_id' => $indicator->id, 'as_of_date' => $asOfDate],
                [
                    'value' => $value,
                    'value_text' => $valueText,
                    'period_label' => $periodLabel,
                    'source_note' => $sourceNote,
                ],
            );
        }
    }

    private function seedPsaInflation(): void
    {
        $dataSource = DataSource::updateOrCreate(
            ['slug' => 'psa-inflation'],
            [
                'name' => 'PSA - Headline Inflation (CPI, 2018=100)',
                'category' => DashboardCategory::Macro,
                'method' => DataSourceMethod::Api,
                // PSA OpenSTAT PxWeb table: Year-on-Year Changes of the
                // CPI by Commodity Group. A real JSON REST API - GET for
                // metadata, POST for data - and, unlike every BSP source
                // so far, not blocked from this dev sandbox.
                'url' => 'https://openstat.psa.gov.ph/PXWeb/api/v1/en/DB/2M/PI/CPI/2018NEW/0012M4ACP23.px',
                'description' => "Not a file at all - PSA's OpenSTAT PxWeb API, a real JSON API updated monthly. The most reliable source we have so far: unlike every BSP source, it isn't blocked from our dev network.",
                'is_active' => true,
                'config' => ['fetcher' => PsaInflationFetcher::class],
            ],
        );

        $indicator = MarketIndicator::updateOrCreate(
            ['code' => 'inflation_rate'],
            [
                'data_source_id' => $dataSource->id,
                'label' => 'Inflation Rate (2018=100)',
                'unit' => '%',
                'category' => DashboardCategory::Macro,
                'display_order' => 1,
            ],
        );

        IndicatorValue::updateOrCreate(
            ['market_indicator_id' => $indicator->id, 'as_of_date' => '2026-03-31'],
            ['value' => 4.1, 'period_label' => 'March 2026'],
        );
    }

    /**
     * The "BAP Spot USD/PHP" table. All 12 rows come from the *same*
     * banking day, one MarketIndicator per row (BspKeyRatesFetcher-style),
     * since indicator_values only stores one number per (indicator, date).
     * Seed values below are real numbers read from bap.org.ph's own
     * fxhistorical.html for Sep 15-16, 2026, not placeholders.
     */
    private function seedBapFxSummary(): void
    {
        $dataSource = DataSource::updateOrCreate(
            ['slug' => 'bap-fx-summary'],
            [
                'name' => 'BAP - Spot USD/PHP FX Summary',
                'category' => DashboardCategory::Fx,
                'method' => DataSourceMethod::File,
                // A stable, parameterized HTML table
                // (fxhistorical.html?base=USD&quote=PHP&year=Y&month=M),
                // one row per banking day - not an arbitrary page we're
                // scraping blind, and not blocked from this dev sandbox.
                'url' => 'https://www.bap.org.ph/fxhistorical.html',
                'description' => "Bankers Association of the Philippines' FX historical page - a fixed-URL, well-formed HTML table (one row per banking day: Open/High/Low/Close, AM/PM weighted averages and volumes, and the FX Settlement Rate). More detailed than BSP's own reference rate, and not blocked from our dev network.",
                'is_active' => true,
                'config' => ['fetcher' => BapFxSummaryFetcher::class],
            ],
        );

        // code => [label, unit, decimals(informational only), display_order, today, previous]
        $rows = [
            'bap_usdphp_open_time' => ['Open Time', null, 20, null, null],
            'bap_usdphp_open' => ['Open', 'PHP', 21, 62.80, 62.90],
            'bap_usdphp_high' => ['High', 'PHP', 22, 62.80, 62.925],
            'bap_usdphp_low' => ['Low', 'PHP', 23, 62.68, 62.777],
            'bap_usdphp_close' => ['Close', 'PHP', 24, 62.739, 62.835],
            'bap_usdphp_am_wa' => ['BAP AM Weighted Average', 'PHP', 25, 62.737, 62.833],
            'bap_usdphp_am_volume' => ['AM Volume', 'USD M', 26, 709.5, 796.09],
            'bap_usdphp_pm_wa' => ['BAP PM Weighted Average', 'PHP', 27, 62.727, 62.862],
            'bap_usdphp_pm_volume' => ['PM Volume', 'USD M', 28, 639.45, 1102.52],
            'bap_usdphp_wa' => ['BAP Weighted Average', 'PHP', 29, 62.732, 62.850],
            'bap_usdphp_total_volume' => ['Total Volume', 'USD M', 30, 1348.95, 1898.61],
            'bap_usdphp_settlement' => ['FX Settlement Rate', 'PHP', 31, 62.737, 62.833],
        ];

        foreach ($rows as $code => [$label, $unit, $order, $today, $previous]) {
            $indicator = MarketIndicator::updateOrCreate(
                ['code' => $code],
                [
                    'data_source_id' => $dataSource->id,
                    'label' => $label,
                    'unit' => $unit,
                    'category' => DashboardCategory::Fx,
                    'display_order' => $order,
                ],
            );

            if ($code === 'bap_usdphp_open_time') {
                IndicatorValue::updateOrCreate(
                    ['market_indicator_id' => $indicator->id, 'as_of_date' => '2026-09-15'],
                    ['value_text' => '09:00:03'],
                );
                IndicatorValue::updateOrCreate(
                    ['market_indicator_id' => $indicator->id, 'as_of_date' => '2026-09-16'],
                    ['value_text' => '09:00:01'],
                );

                continue;
            }

            IndicatorValue::updateOrCreate(
                ['market_indicator_id' => $indicator->id, 'as_of_date' => '2026-09-15'],
                ['value' => $previous],
            );
            IndicatorValue::updateOrCreate(
                ['market_indicator_id' => $indicator->id, 'as_of_date' => '2026-09-16'],
                ['value' => $today],
            );
        }
    }

    /**
     * The "PHP BVAL Reference Rates" table - one MarketIndicator
     * (`php_bval_curve`) with a CurvePoint per tenor per date. Seed
     * values below are real numbers read from PDS's own public GraphQL
     * widget API (the `bvalRates` query, `requestPage: "public"`), not
     * placeholders - see PdsBvalFetcher's docblock for the endpoint and
     * the important distinction from PDS's separate, subscriber-only
     * MarketPage system.
     */
    private function seedPdsBval(): void
    {
        $dataSource = DataSource::updateOrCreate(
            ['slug' => 'pds-bval'],
            [
                'name' => 'PDS - PHP BVAL Reference Rates',
                'category' => DashboardCategory::Rates,
                'method' => DataSourceMethod::Api,
                'url' => 'https://www.pds.com.ph/api-mp/graphql/',
                'description' => 'A real, public GraphQL API - the same one behind the embeddable "PHP BVAL Reference Rates" widget PDS licenses to third-party sites. Not to be confused with marketpage.pdex.net.ph, PDS\'s separate subscriber-only MarketPage, which requires a client certificate and a paid subscription. The API key in use here was found via a public search result rather than issued directly to Ramcar - see docs/MODULES.md.',
                'is_active' => true,
                'config' => ['fetcher' => PdsBvalFetcher::class],
            ],
        );

        $indicator = MarketIndicator::updateOrCreate(
            ['code' => 'php_bval_curve'],
            [
                'data_source_id' => $dataSource->id,
                'label' => 'PHP BVAL Benchmark Yield Curve',
                'unit' => '%',
                'category' => DashboardCategory::Rates,
                'display_order' => 40,
            ],
        );

        // tenor => [sort_order, today, previous]
        $tenors = [
            '1M' => [0, 5.0558, 5.0687],
            '3M' => [1, 5.3006, 5.2993],
            '6M' => [2, 5.6577, 5.6430],
            '1Y' => [3, 5.8894, 5.8928],
            '2Y' => [4, 6.6057, 6.6300],
            '3Y' => [5, 6.9664, 6.9892],
            '4Y' => [6, 7.2210, 7.2322],
            '5Y' => [7, 7.3784, 7.3849],
            '7Y' => [8, 7.5760, 7.5831],
            '10Y' => [9, 7.6147, 7.6476],
            '20Y' => [10, 7.7098, 7.8248],
            '25Y' => [11, 7.7123, 7.8412],
        ];

        foreach ($tenors as $tenor => [$sortOrder, $today, $previous]) {
            CurvePoint::updateOrCreate(
                ['market_indicator_id' => $indicator->id, 'as_of_date' => '2026-09-15', 'point_key' => $tenor],
                ['point_label' => $tenor, 'value' => $previous, 'sort_order' => $sortOrder],
            );
            CurvePoint::updateOrCreate(
                ['market_indicator_id' => $indicator->id, 'as_of_date' => '2026-09-16', 'point_key' => $tenor],
                ['point_label' => $tenor, 'value' => $today, 'sort_order' => $sortOrder],
            );
        }
    }

    /**
     * The "GDP Growth, YoY %" card in the Macro section. Seed values
     * below are real numbers read from PSA's own OpenSTAT PxWeb table
     * (2B/NA/QT/1SUM/0022B5BEXQ2.px), not placeholders - see
     * PsaGdpGrowthFetcher's docblock for the full source detail. Full
     * quarterly history back to 2001 is backfilled separately via
     * `php artisan market:backfill-gdp`.
     */
    private function seedPsaGdp(): void
    {
        $dataSource = DataSource::updateOrCreate(
            ['slug' => 'psa-gdp'],
            [
                'name' => 'PSA - Real GDP Growth (YoY, Constant 2018 Prices)',
                'category' => DashboardCategory::Macro,
                'method' => DataSourceMethod::Api,
                'url' => 'https://openstat.psa.gov.ph/PXWeb/api/v1/en/DB/2B/NA/QT/1SUM/0022B5BEXQ2.px',
                'description' => 'PSA\'s OpenSTAT PxWeb API for quarterly National Accounts - the same kind of real JSON API as Inflation Rate\'s, just a different table ("Gross National Income and Gross Domestic Product by Expenditure Shares, Growth Rates"). Reports real (constant 2018 prices) GDP growth, year-on-year, one figure per quarter.',
                'is_active' => true,
                'config' => ['fetcher' => PsaGdpGrowthFetcher::class],
            ],
        );

        $indicator = MarketIndicator::updateOrCreate(
            ['code' => 'gdp_growth_rate'],
            [
                'data_source_id' => $dataSource->id,
                'label' => 'GDP Growth (YoY, Constant 2018 Prices)',
                'unit' => '%',
                'category' => DashboardCategory::Macro,
                'display_order' => 2,
            ],
        );

        IndicatorValue::updateOrCreate(
            ['market_indicator_id' => $indicator->id, 'as_of_date' => '2026-03-31'],
            ['value' => 2.8, 'period_label' => 'Q1 2026'],
        );
        IndicatorValue::updateOrCreate(
            ['market_indicator_id' => $indicator->id, 'as_of_date' => '2026-06-30'],
            ['value' => 2.3, 'period_label' => 'Q2 2026'],
        );
    }

    /**
     * The "Philippine Stock Market Overview" section and the PSEi hero
     * card. Seed values are a real mid-session snapshot read from PSE
     * Edge on Sep 21, 2026 (market in recess), not placeholders - see
     * PseIndexSummaryFetcher's docblock, including PSE's "personal,
     * non-commercial use" restriction on this source.
     */
    private function seedPseIndexSummary(): void
    {
        $dataSource = DataSource::updateOrCreate(
            ['slug' => 'pse-index-summary'],
            [
                'name' => 'PSE Edge - Index Summary',
                'category' => DashboardCategory::Equities,
                'method' => DataSourceMethod::File,
                'url' => 'https://edge.pse.com.ph/index/form.do',
                'description' => 'The Philippine Stock Exchange\'s own "Index Summary" page (PSE Edge) - a small fixed-URL HTML page with the PSEi, All Shares and six sector indices, plus turnover and advances/declines. Not blocked from our dev network. Caution: PSE\'s footer limits these materials to "personal, non-commercial use", so treat this as interim until the PSE Data Products licence question is settled. Foreign flow is NOT on this page.',
                'is_active' => true,
                'config' => ['fetcher' => PseIndexSummaryFetcher::class],
            ],
        );

        // code => [label, unit, level, change]
        $indices = [
            'psei' => ['PSEi', 5850.82, -5.09],
            'pse_all_shares' => ['All Shares', 3269.01, -8.79],
            'pse_financials' => ['Financials', 1821.31, 0.68],
            'pse_industrial' => ['Industrial', 7692.10, -110.83],
            'pse_holding_firms' => ['Holding Firms', 4180.62, 43.48],
            'pse_services' => ['Services', 3162.15, -1.82],
            'pse_mining_oil' => ['Mining and Oil', 21715.33, -144.86],
            'pse_property' => ['Property', 1787.82, -10.18],
        ];

        $rows = [];
        $order = 50;

        foreach ($indices as $code => [$label, $level, $change]) {
            $rows[$code] = [$label, null, $order++, $level, null];
            $rows[$code.'_chg'] = ["{$label} - Change", null, $order++, $change, null];
        }

        $rows['pse_total_volume'] = ['Total Volume', 'shares', $order++, 1303496769.0, null];
        $rows['pse_total_trades'] = ['Total Trades', null, $order++, 57118.0, null];
        $rows['pse_total_value'] = ['Total Value Traded', 'PHP', $order++, 3610613363.0, null];
        $rows['pse_advances'] = ['Advances', null, $order++, 71.0, null];
        $rows['pse_declines'] = ['Declines', null, $order++, 106.0, null];
        $rows['pse_unchanged'] = ['Unchanged', null, $order++, 61.0, null];
        $rows['pse_market_status'] = ['Market Status', null, $order++, null, 'RECESS'];

        foreach ($rows as $code => [$label, $unit, $displayOrder, $value, $valueText]) {
            $indicator = MarketIndicator::updateOrCreate(
                ['code' => $code],
                [
                    'data_source_id' => $dataSource->id,
                    'label' => $label,
                    'unit' => $unit,
                    'category' => DashboardCategory::Equities,
                    'display_order' => $displayOrder,
                ],
            );

            IndicatorValue::updateOrCreate(
                ['market_indicator_id' => $indicator->id, 'as_of_date' => '2026-09-21'],
                ['value' => $value, 'value_text' => $valueText, 'as_of_time' => '12:55:00'],
            );
        }
    }

    /**
     * Gold, wheat and Brent - each its own Markets Insider page, the same
     * pages the Treasury team's Excel sheet links to. Seed values are real
     * readings captured on Sep 21, 2026. Markets Insider offers no API, so
     * these are page-scrapes and carry the "indicative price" caveat - see
     * MarketsInsiderPriceFetcher's docblock. Wheat's unit is not stated on
     * the page (its title says "per Ounce" for every commodity), so no unit
     * is claimed for it.
     */
    private function seedMarketsInsiderCommodities(): void
    {
        // slug => [name, url, code, label, unit, price, previous, as_of_date, note, display_order]
        $sources = [
            'mi-gold' => ['Markets Insider - Gold', 'https://markets.businessinsider.com/commodities/gold-price', 'gold_spot', 'Spot Gold', 'USD/oz', 4354.45, 4380.05, '2026-09-21', 'MI Indication, 03:19:00 AM', 60],
            'mi-wheat' => ['Markets Insider - Wheat', 'https://markets.businessinsider.com/commodities/wheat-price', 'wheat_price', 'Wheat', 'USD', 277.3356, 280.1852, '2026-09-18', 'MI Indication, 02:15:00 PM EDT 9/18/2026', 62],
            'mi-brent' => ['Markets Insider - Brent Crude', 'https://markets.businessinsider.com/commodities/oil-price?type=brent', 'brent_crude', 'Brent Crude', 'USD/bbl', 101.68, 103.87, '2026-09-21', 'MI Indication, 03:10:00 AM', 64],
        ];

        foreach ($sources as $slug => [$name, $url, $code, $label, $unit, $price, $previous, $date, $note, $order]) {
            $dataSource = DataSource::updateOrCreate(
                ['slug' => $slug],
                [
                    'name' => $name,
                    'category' => DashboardCategory::Commodities,
                    'method' => DataSourceMethod::Scrape,
                    'url' => $url,
                    'description' => "The commodity's Markets Insider page (the same one linked from the Treasury Excel sheet). No API - the price is read from the page, so it is an indicative \"MI Indication\" price, fragile to page changes, and subject to the site's terms. Not an official fixing.",
                    'is_active' => true,
                    'config' => ['fetcher' => MarketsInsiderPriceFetcher::class, 'indicator_code' => $code],
                ],
            );

            foreach ([$code => [$label, $price, $order], $code.'_prev' => ["{$label} - Previous Close", $previous, $order + 1]] as $indicatorCode => [$indicatorLabel, $value, $displayOrder]) {
                $indicator = MarketIndicator::updateOrCreate(
                    ['code' => $indicatorCode],
                    [
                        'data_source_id' => $dataSource->id,
                        'label' => $indicatorLabel,
                        'unit' => $unit,
                        'category' => DashboardCategory::Commodities,
                        'display_order' => $displayOrder,
                    ],
                );

                IndicatorValue::updateOrCreate(
                    ['market_indicator_id' => $indicator->id, 'as_of_date' => $date],
                    ['value' => $value, 'source_note' => $note],
                );
            }
        }
    }

    /**
     * LME Lead, in two parts with very different sourcing:
     * - Cash-Settlement / 3-month / stock: Westmetall's free daily table
     *   (WestmetallLeadFetcher), with real history - backfilled by
     *   `market:backfill-lme-lead`.
     * - The "Official Prices" bid/offer curve (Cash, 3-month, Dec-27...):
     *   no automated source (the LME site 403s and its data is licensed),
     *   so it is a manual-entry curve, seeded from the reference
     *   screenshot dated 02 Apr 2026. It will look stale until updated.
     */
    private function seedLmeLead(): void
    {
        $dataSource = DataSource::updateOrCreate(
            ['slug' => 'westmetall-lme-lead'],
            [
                'name' => 'Westmetall - LME Lead (Cash / 3-month / Stock)',
                'category' => DashboardCategory::Commodities,
                'method' => DataSourceMethod::File,
                'url' => 'https://www.westmetall.com/en/markdaten.php?action=table&field=LME_Pb_cash',
                'description' => 'Westmetall\'s public LME Lead table: daily Cash-Settlement price, 3-month price and warehouse stock, newest first. NOT the LME Official Prices bid/offer curve (the LME site is blocked from here and its data is licensed). Whether republishing LME-sourced prices this way is acceptable for Ramcar is a licensing question.',
                'is_active' => true,
                'config' => ['fetcher' => WestmetallLeadFetcher::class],
            ],
        );

        $series = [
            'lme_lead_cash' => ['LME Lead Cash-Settlement', 'USD/t', 66, 1868.00, '2026-09-18'],
            'lme_lead_3m' => ['LME Lead 3-month', 'USD/t', 67, 1911.00, '2026-09-18'],
            'lme_lead_stock' => ['LME Lead Stock', 't', 68, 373625.0, '2026-09-18'],
        ];

        foreach ($series as $code => [$label, $unit, $order, $value, $date]) {
            $indicator = MarketIndicator::updateOrCreate(
                ['code' => $code],
                [
                    'data_source_id' => $dataSource->id,
                    'label' => $label,
                    'unit' => $unit,
                    'category' => DashboardCategory::Commodities,
                    'display_order' => $order,
                ],
            );

            IndicatorValue::updateOrCreate(
                ['market_indicator_id' => $indicator->id, 'as_of_date' => $date],
                ['value' => $value],
            );
        }

        $manual = DataSource::updateOrCreate(
            ['slug' => 'lme-lead-official-prices'],
            [
                'name' => 'LME Lead - Official Prices (manual entry)',
                'category' => DashboardCategory::Commodities,
                'method' => DataSourceMethod::Manual,
                'description' => 'The LME "Trading Summary" bid/offer table for Lead (Cash, 3-month, Dec-27, Dec-28, Dec-29). No automated source - lme.com is blocked from our network and the data is licensed - so it is typed in by hand under Market Indicators -> LME Lead Official Prices -> Curve points.',
                'is_active' => true,
            ],
        );

        $curve = MarketIndicator::updateOrCreate(
            ['code' => 'lme_lead_curve'],
            [
                'data_source_id' => $manual->id,
                'label' => 'LME Lead Official Prices (bid/offer)',
                'unit' => 'USD/t',
                'category' => DashboardCategory::Commodities,
                'display_order' => 69,
            ],
        );

        // point_key => [bid, offer, sort_order]
        foreach (['Cash' => [1892.50, 1893.00], '3-month' => [1916.00, 1918.00], 'Dec-27' => [2078.00, 2083.00], 'Dec-28' => [2158.00, 2163.00], 'Dec-29' => [2208.00, 2213.00]] as $key => [$bid, $offer]) {
            CurvePoint::updateOrCreate(
                ['market_indicator_id' => $curve->id, 'as_of_date' => '2026-04-02', 'point_key' => $key],
                ['point_label' => $key, 'bid' => $bid, 'offer' => $offer, 'sort_order' => array_search($key, ['Cash', '3-month', 'Dec-27', 'Dec-28', 'Dec-29'], true)],
            );
        }
    }
}
