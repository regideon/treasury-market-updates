# Treasury Market Dashboard (`/rpanel`) — Gabay at Buong Paliwanag

## Purpose

The **Treasury Market Dashboard** is a live screen for Ramcar's Treasury team. It replaces the Excel file that used to be emailed every day with the market rates. Now everyone checks one page, and it is always up to date.

What it shows:
- **FX:** the USD/PHP reference rate and the BAP FX fixing (open, high, low, close, volumes)
- **Rates:** BSP policy rates, the PHP BVAL yield curve (1M to 25Y), and headline inflation
- **Macro:** inflation history and quarterly GDP growth
- **Equities:** the PSEi, sector indices, turnover and market breadth (PSE)
- **Commodities:** Brent, gold, wheat and LME lead

Not all data is automatic. Some parts are manual entry, and some are still placeholders. Each one is labelled in the Guide and in this document.

**Why "Treasury Market Dashboard"?** It is more precise than "financial dashboard". The Treasury team uses it, and the content is market data (FX, rates, bonds, stocks, commodities). "Financial dashboard" is true but generic; it could just as well describe an accounting or sales dashboard.

For the module-by-module story (decisions, bugs and timeline), see [`MODULES.md`](MODULES.md). For a quick project overview, see [`README.md`](../README.md).

This is the complete guide to everything shown on **http://treasury-market-updates.test/rpanel**, from top to bottom, including the technical details. It was written for demos and for colleagues who want to understand where each number comes from.

> **Mabilis na tip sa demo:** may dalawang button sa kanang-ibaba ng screen.
> - **Guide** — step-by-step na paliwanag ng bawat section (ano ang ipinapakita at bakit).
> - **Technical** — parehong tour, pero ipinapakita kung saan galing ang data (URL, uri ng source, huling run, at kung static pa).

---

## Legend

| Tag | Ibig sabihin |
|---|---|
| ✅ **Dynamic** | Totoong numero mula sa database. Galing sa totoong source (file, API, scrape) o manual entry. |
| ⚠️ **Static** / **Not dynamic** | Placeholder o sample lang. Hindi pa konektado sa anumang source. |
| ✍️ **Manual entry** | Tina-type ng tao sa admin (`/rpanel/market-indicators`). Walang automated na pinagkukunan. |

---

## Mga Terms na Kailangan Mong Maintindihan

- **BSP** — Bangko Sentral ng Pilipinas. Sila ang nagtatakda ng policy rates.
- **BAP** — Bankers Association of the Philippines. Sila ang nagpapatakbo ng FX fixing sa interbank.
- **PDS** — Philippine Dealing System. Sila ang nagbibigay ng bond benchmark (BVAL).
- **PSE** — Philippine Stock Exchange. Sila ang nagpapatakbo ng stock market.
- **PSA** — Philippine Statistics Authority. Sila ang gumagawa ng inflation at GDP.
- **WAIR** — *Weighted Average Interest Rate*: average ng mga na-accept na bids sa BSP auction, "weighted" ayon sa laki ng bid.
- **TDF** — *Term Deposit Facility*: paraan ng BSP para sipsipin ang sobrang pera ng mga bangko.
- **ON RRP** — *Overnight Reverse Repurchase*: pangunahing policy rate ng BSP.
- **BVAL** — *Bloomberg Valuation*: estimated na fair-market yield ng government bonds, hindi direktang presyo ng isang transaction.
- **bps** — *basis point*. 1 bps = 0.01%.
- **MI Indication** — indicative na presyo ng Markets Insider. Hindi ito official fixing.
- **Trend line / sparkline** — maliit na linya sa card para ipakita ang direksyon ng galaw.

---

## 1. Tatlong Hero Card 

*The three headline cards at the top: the USD/PHP spot rate, the PSEi equity index, and headline inflation.*

### 1.1 "SPOT FX · USD/PHP" — ✅ Dynamic

*The latest official BSP peso-dollar reference rate, with its change from the previous stored day.*

- **Ano ang ipinapakita:** Ang pinakabagong opisyal na USD/PHP reference rate ng BSP. May % badge na nagsasabi ng pagbabago laban sa nakaraang araw na naka-save.
- **Saan galing:** BSP Daily Reference Exchange Rate Bulletin, `RERB.xlsx`, isang maliit na Excel file na binabago ni BSP araw-araw sa parehong URL: `https://www.bsp.gov.ph/Statistics/RERB/RERB.xlsx`.
- **Paano kinukuha:** `BspUsdPhpFetcher` — binabasa ang column na "PHIL.PESO EQUIVALENT" sa row ng USD.
- **Formula ng % badge:**
  ```
  % change = ((ngayon − kahapon) / kahapon) × 100
  ```
  Nasa `IndicatorValue::percentChangeFrom()`.
- **"vs X yesterday":** ang naunang naka-save na value (`previousValue`), hindi ang aktwal na kahapon. Kung walang nakaraang araw pa, "No prior value yet" ang lalabas.
- **Expand icon (↗):** nagbubukas ng side drawer na may parehong info. Client-side lang ito (Alpine.js), walang bagong request sa server.
- **Indicator code:** `usdphp_spot`. Source: `bsp-usdphp`.

### 1.2 "EQUITIES · PHILIPPINE STOCK EXCHANGE" — ✅ Dynamic

*The PSEi index level, its change for the day, and the total value traded on the Philippine Stock Exchange.*

- **Ano ang ipinapakita:** Level ng PSEi, ang pagbabago nito ngayong araw, at ang kabuuang halaga ng na-trade (turnover).
- **Saan galing:** PSE Edge Index Summary page: `https://edge.pse.com.ph/index/form.do`.
- **Paano kinukuha:** `PseIndexSummaryFetcher`.
- **Pagbabago (% at points):** galing mismo sa PSE. Ang % ay kinukuwenta namin:
  ```
  dating close = PSEi ngayon − Chg
  % change = (Chg / dating close) × 100
  ```
  Halimbawa: 5,850.82 na bumaba ng 5.09 → dating close 5,855.91 → −0.09%.
- **Market status at oras** (hal. "Market recess", "As of Sep 21, 12:55 PM"): galing din sa page. Live ang page na ito, kaya partial pa ang numbers habang bukas ang market. Ang 18:30 na EOD run ang kumukuha ng final na numbers.
- **Indicator codes:** `psei` (level), `psei_chg` (change). Source: `pse-index-summary`.

### 1.3 "MACRO · HEADLINE INFLATION (YOY)" — ✅ Dynamic

*The latest year-on-year inflation rate from PSA, compared with the BSP target band of 2.0% to 4.0%, with the last six months.*

- **Ano ang ipinapakita:** ang pinakabagong buwanang year-on-year inflation mula sa PSA, ang buwan at source nito, ang BSP target band (2.0% – 4.0%), at mini bar chart ng huling 6 na buwan (pinakabago ang naka-highlight).
- **Badge sa ibaba:** kinukumpara ang value sa target band:
  - "Above target" kapag higit sa 4.0%
  - "Below target" kapag mas mababa sa 2.0%
  - "Within target" kapag nasa pagitan
- **Saan galing:** PSA OpenSTAT API, ang parehong stored values ng "Inflation Rate" card sa BSP Policy strip.
- **Technical:** `Dashboard::buildInflationRecent()` (huling 6 na buwan, may `heightPercent` para sa bars). Ang headline value ay galing sa `keyRates['inflation_rate']`. Source: `psa-inflation`.
- **Wala na:** ang dating "Next: Sep 5" na footer ay tinanggal dahil walang source para dito.

> **Paano mag-enter ng manual values:** pumunta sa **http://treasury-market-updates.test/rpanel/market-indicators** at pindutin ang **Enter Value** sa row ng indicator. Para sa LME Lead bid/offer, gamitin ang tab na **Curve points** sa loob ng "LME Lead Official Prices (bid/offer)". Ang **http://treasury-market-updates.test/rpanel/data-sources** ay para sa pag-review ng source (URL, method, huling run), hindi para sa mismong values.

## 2. "BSP Policy & System Benchmarks" — ✅ Dynamic (halo: automatic + manual)

*Eleven central bank policy rates and system benchmarks. Each card keeps its own date, because BSP updates them at different times.*

Strip na may 11 maliit na card. Bawat card ay may **sariling petsa**, dahil magkakaiba ang schedule ng pag-update ni BSP (may araw-araw, may buwanan, may hindi na nagbabago mula 2020 o 2023).

| Card | Indicator code | Paano nakukuha |
|---|---|---|
| Inflation Rate (2018=100) | `inflation_rate` | ✅ Automatic — PSA OpenSTAT API (`PsaInflationFetcher`) |
| ON RRP Rate | `on_rrp_rate` | ✅ Automatic — BSP `sefi.pdf` (`BspKeyRatesFetcher`) |
| 7-day TDF (WAIR) | `tdf_7d` | ✅ Automatic — BSP `sefi.pdf` (`BspKeyRatesFetcher`) |
| 28-day BSP Securities (WAIR) | `bsp_securities_28d` | ✅ Automatic — BSP `sefi.pdf` (`BspKeyRatesFetcher`) |
| ON Lending Rate | `on_lending_rate` | ✍️ Manual entry |
| Target RRP Rate | `target_rrp_rate` | ✍️ Manual entry (nasa footnote lang sa PDF, hindi clean na cell) |
| ON Deposit Rate | `on_deposit_rate` | ✍️ Manual entry (may stale na monthly average sa PDF) |
| 56-day BSP Securities (WAIR) | `bsp_securities_56d` | ✍️ Manual entry (blangko sa PDF kapag walang auction) |
| ON Reference Rate | `on_reference_rate` | ✍️ Manual entry — **discontinued** (`****`) mula Sep 2023 |
| 14-day TDF (WAIR) | `tdf_14d` | ✍️ Manual entry (blangko sa PDF) |
| 28-day TDF (WAIR) | `tdf_28d` | ✍️ Manual entry — hindi na ginagamit mula 2020 |

**Paano ito ipinapakita:**
- Trailing zeros inaalis (`4.2500` → `4.25%`).
- Kung may `value_text` (hal. `****`), ito ang lalabas sa halip na numero.
- Kung walang value, "—" ang lalabas.

**Technical:** Lahat ng card ay nasa `bsp-key-rates` source maliban sa Inflation Rate (`psa-inflation`). Ang pipeline ay hindi nagsusulat sa mga code na hindi kasama sa fetch result, kaya safe ang manual na values.

**Terms:** tignan ang "Mga Terms" sa itaas.

---

## 3. Dalawang Table sa Gitna

*Two tables side by side: the BAP daily USD/PHP fixing (left) and the PHP BVAL government bond yield curve (right).*

### 3.1 "BAP Spot USD/PHP" (kaliwa) — ✅ Dynamic

*The detailed daily USD/PHP fixing from the Bankers Association of the Philippines: open, high, low, close, weighted averages, volumes and the settlement rate.*

Detalyadong daily fixing ng peso-dollar mula sa BAP.

| Row | Ibig sabihin | Formula / Paano |
|---|---|---|
| Date | Petsa ng huling banking day | Galing sa `as_of_date` |
| Open Time | Oras nag-open ang FX market | Text lang (hal. "09:00:01") |
| Open / High / Low / Close | Presyo sa simula, pinakamataas, pinakamababa, at pagsara | Direktang binigay ni BAP. Hindi kami nagkukwenta. |
| BAP AM / PM Weighted Average | Average ng trades sa umaga / hapon, weighted ng volume | `Σ(presyo × volume) / Σ(volume)`, ginawa ni BAP |
| AM / PM Volume | Kabuuang na-trade sa session, sa milyong USD | Sum ng trades |
| BAP Weighted Average | Parehong weighted average, buong araw | AM + PM |
| Total Volume | AM + PM volume | Simpleng addition |
| FX Settlement Rate | Opisyal na rate para sa settlement ng mga kontratang naka-refer sa BAP | Kadalasan kapareho ng BAP Weighted Average |

- **"Previous" column:** ang naunang naka-save na banking day (hindi ang row sa parehong request).
- **Daily Turnover footer:** Total Volume ng pinakabagong araw.
- **Saan galing:** `bap.org.ph/fxhistorical.html?base=USD&quote=PHP&year=YYYY&month=MM`. Kinukuha ang huling row ng kasalukuyang buwan.
- **Technical:** `BapFxSummaryFetcher`. Source: `bap-fx-summary` (File). 12 indicator codes `bap_usdphp_*`, lahat parehong `as_of_date`.

### 3.2 "PHP BVAL Reference Rates" (kanan) — ✅ Dynamic

*The government bond yield curve from 1 month to 25 years, today and the previous day, with the change in basis points.*

Yield curve ng government bonds, 1M hanggang 25Y.

| Column | Ibig sabihin |
|---|---|
| Tenor | Gaano katagal bago mag-mature ang bond |
| BVAL Rate Today / Previous Day | Yield (%) sa tenor na 'yon. Mas mataas na yield = mas mataas na interest na kailangang ibayad ng gobyerno |
| Change (bps) | `(ngayon − kahapon) × 100` |

- **10Y row** ang naka-highlight, dahil ito ang pinaka-tinitignang tenor sa bond market.
- **Footer 2s/10s Spread:** `(10Y − 2Y) × 100` bps. Kapag negative ito (inverted curve), madalas itong senyales ng papalapit na recession.
- **Saan galing:** PDS public GraphQL widget API: `https://www.pds.com.ph/api-mp/graphql/`, `bvalRates` query, `requestPage: "public"`.
- **Technical:** `PdsBvalFetcher` → `curve_points` table (12 rows bawat araw, `point_key` = tenor). Indicator `php_bval_curve`. Source `pds-bval` (API).
- **Paalala:** ang API key ay nahanap sa public search result, hindi pormal na ibinigay sa Ramcar. Hindi ito ang subscriber-only na MarketPage (`marketpage.pdex.net.ph`) na nangangailangan ng client certificate.

---

## 4. "Inflation Update" — ✅ Dynamic (2019–2026)

*Year-on-year inflation for every month since 2019, with the yearly average in the last row.*

Year-on-year inflation bawat buwan. Buwan bilang rows, taon bilang columns, at "Average" sa ilalim.

- **Average formula:** simpleng average ng lahat ng buwan na may data sa taon:
  ```
  average = Σ(buwanang rate) / (bilang ng buwan na may data)
  ```
  Na-verify namin na tumutugma ito sa "Average" row ng PSA (2021 = 3.9%, 2023 = 6.0%).
- **Blangko na cell "—":** buwan na hindi pa na-publish ng PSA. Hindi ito kasama sa average.
- **Saan galing:** PSA OpenSTAT PxWeb table `DB/2M/PI/CPI/2018NEW/0012M4ACP23.px`, "Year-on-Year Changes of the CPI by Commodity Group", Philippines, All Items.
- **Technical:**
  - Daily run: `PsaInflationFetcher::fetch()` — huling dalawang taon lang.
  - Backfill: `PsaInflationFetcher::fetchHistory()` + `php artisan market:backfill-inflation` — lahat ng taon, 92 buwan na published (hanggang Agosto 2026).
  - `as_of_date` = huling araw ng buwan. `period_label` = "August 2026".
  - Ang PSA response ay may UTF-8 BOM. Tinatanggal ito bago mag-`json_decode`.
- **Dashboard builder:** `Dashboard::buildInflationHistory()`, isang query para sa buong table.

---

## 5. "GDP Growth History" — ✅ Dynamic (2001–2026)

*Real GDP growth year on year, by quarter, from 2001: a trend chart for the last seven years and a full table below it.*

### 5.1 Trend line chart

*The last 28 quarters of GDP growth as a line chart. The minimum, maximum and latest quarters are labelled, and the rest show their value on hover.*

- Huling 28 quarters (7 taon). Kapareho ng window ng PSA's own "Figure 1".
- **Labels:** min, max, at pinakabagong quarter lang ang may direct na number. Ang iba ay nasa hover tooltip (`<title>` element).
- **Kulay:** navy para sa positibo, pula para sa negatibo.
- **0% line:** guide para makita kung kailan naging contraction ang GDP.
- **X-axis:** isang label bawat 4 quarters, laging kasama ang pinakabagong quarter.
- **Technical:** `Dashboard::buildGdpGrowthTrend()` (pumipili ng data at nag-fla-flag ng extremes). Ang SVG coordinates ay ginagawa sa Blade (presentation math).

### 5.2 Table

*Every quarter from 2001 to the latest, laid out with quarters as rows and years as columns, plus each year's average.*

- Quarters bilang rows (Q1–Q4), taon bilang columns (2001–2026), at "Average" row.
- Pula ang negatibong value.
- **Average formula:** mean ng available na quarters sa taon. Ang 2026 ay partial pa (Q1–Q2 lang).

### 5.3 Technical

*Where the GDP data comes from and how it is loaded.*

- Source: PSA OpenSTAT PxWeb table `DB/2B/NA/QT/1SUM/0022B5BEXQ2.px` ("Gross National Income and Gross Domestic Product by Expenditure Shares, Growth Rates").
- Filter: `Type of Expenditure = 16` (Gross Domestic Product mismo, hindi components), `Type of Valuation = 1` (At Constant 2018 Prices).
- **Mahalagang trick:** ang "Year" dimension ay comparison pair (hal. "2024-2025"). Ang quarter na inuulat ay ang **pangalawang taon** ng pair: `calendar year = 2001 + position`. Na-verify ito sa +12.0% rebound ng Q2 2021.
- Fetcher: `PsaGdpGrowthFetcher`. Backfill: `php artisan market:backfill-gdp` (102 published quarters).
- Indicator: `gdp_growth_rate`. Source: `psa-gdp`.

---

## 6. "Philippine Stock Market Overview" — ✅ Dynamic (maliban sa Foreign Flow)

*Sector indices, turnover, market breadth and the day's market summary for the Philippine Stock Exchange.*

- **Header:** PSEi level, Day Change (points at %), at Total Turnover (PHP).
- **Sector cards:** All Shares, Financials, Industrial, Holding Firms, Property, Services, Mining & Oil. Bawat isa ay level at %change.
- **Foreign Flow card:** ⚠️ **Not dynamic.** Hindi ito nasa PSE Edge page, at naka-403 block ang `pse.com.ph` market pages mula sa dev network.
- **Advances / Unchanged / Declines bar:** porsyento ng stocks na tumaas, walang galaw, at bumaba.
- **Market Summary table** (sa ilalim): Total Volume, Total Trades, Total Value (PHP), Advances, Declines, Unchanged. Galing sa parehong PSE page.
- **Footnote:** "PSE data is delayed 15 minutes, for market education only" — ito ang disclaimer ng PSE.

**Technical:**
- `PseIndexSummaryFetcher`. Source: `pse-index-summary` (File, `edge.pse.com.ph/index/form.do`).
- 23 indicators: 8 index levels (`psei`, `pse_all_shares`, `pse_financials`, `pse_industrial`, `pse_holding_firms`, `pse_services`, `pse_mining_oil`, `pse_property`), kasama ang `_chg` bawat isa, plus `pse_total_volume`, `pse_total_trades`, `pse_total_value`, `pse_advances`, `pse_declines`, `pse_unchanged`, at `pse_market_status`.
- **Bitag:** walang sign ang "Chg" column ng PSE. Ang direction ay nasa ▲/▼ sa %Chg cell, kaya binabasa ng fetcher ang arrow.
- **Lisensya:** ang PSE footer ay nagsasabing "personal, non-commercial use" lang. Pansamantalang source ito hanggang may sagot ang PSE tungkol sa Data Products.

---

## 7. "Key Treasury Commodities" — ✅ Dynamic (halo)

*Brent crude, spot gold, wheat and LME lead prices, followed by the LME lead trading summary and price curve.*

### 7.1 Apat na cards

*Four price cards, each with its change from the previous close and a short trend line once enough readings are stored.*

| Card | Source | Ano ang previous | Technical |
|---|---|---|---|
| **Brent Crude** (/bbl) | Markets Insider (`mi-brent`) | `brent_crude_prev` (price − stated change) | `MarketsInsiderPriceFetcher` |
| **Spot Gold** (/oz) | Markets Insider (`mi-gold`) | `gold_spot_prev` | `MarketsInsiderPriceFetcher` |
| **Wheat** ($ lang, walang nakasulat na unit) | Markets Insider (`mi-wheat`) | `wheat_price_prev` | `MarketsInsiderPriceFetcher` |
| **LME Lead** (/t) | Westmetall (`westmetall-lme-lead`) — Cash-Settlement | Naunang trading day sa history | `WestmetallLeadFetcher` |

- **% badge:** `(ngayon − previous) / previous × 100`.
- **Sparkline:** totoong linya kapag **≥ 5 readings** na naka-save. Kung wala pa, grey placeholder na may tag na **"Not dynamic"**.
- **Footer ng card:** source name at petsa. Kung higit 3 araw na ang lumipas, "X days delayed" ang lalabas.

### 7.2 LME Lead Official Prices (Trading Summary)

*The LME lead bid and offer table for the cash and forward contracts. It is entered by hand.*

- Table: Contract (Cash, 3-month, Dec-27, Dec-28, Dec-29), Bid, at Offer.
- **Status:** ✍️ **Manual entry.** Tag: "Not dynamic · manual entry".
- **Bakit manual:** naka-403 ang `lme.com` mula sa dev network, at lisensyado ang data.
- **Saan i-type:** `/rpanel/market-indicators` → **LME Lead Official Prices (bid/offer)** → tab na **"Curve points"**. Kailangan ang Contract, Bid, Offer, at "Data valid for".
- **Technical:** indicator `lme_lead_curve`, `curve_points` table (`bid` at `offer`). Ang relation manager ay `CurvePointsRelationManager`. Pinipigilan nito ang duplicate na contract sa parehong petsa.
- **Seed:** galing sa screenshot na may petsang 02 Apr 2026, kaya luma pa ang itsura hanggang may mag-update.

### 7.3 LME Lead Price Curve

*Cash-settlement and 3-month LME lead prices over the last 20 trading days.*

- Line chart: Cash-Settlement (asul) at 3-month (orange), huling 20 trading days.
- Bawat point ay may tooltip na may petsa at presyo.
- **Source:** Westmetall. Hindi ito bid/offer curve.
- **Backfill:** `php artisan market:backfill-lme-lead` (buong taon, 181 araw).

### 7.4 Mahalagang paalala sa Markets Insider

*Notes on the limits of the Markets Insider prices used for gold, wheat and Brent.*

- **Scraping** ito. Walang API. Naka-anti-forgery token ang chart endpoint nila.
- **Indicative** ang presyo ("MI Indication"), hindi official fixing.
- Ang timestamp ay minsan may date, minsan oras lang. Kapag oras lang, ituturing na ngayong araw (at Biyernes kung weekend).

---

## 8. "Macroeconomic Overview" (collapsible) — Halo

*A collapsible section that groups the inflation and GDP cards. Click the header to show or hide it.*

Pindutin ang header para mag-expand o mag-collapse.

- **Inflation YoY % & Target Band** — ⚠️ **Static.** Sample chart lang na may "Above Target (6.2%)". Ang totoong inflation ay nasa Sections 2 at 4.
- **GDP Growth, YoY %** — ✅ **Dynamic.** Huling 4 na totoong quarters (hindi na kasama ang dating dalawang "forecast" bar). Ang badge sa taas ay ang pinakabagong quarter.

---

## Technicalities (para sa developer at sa mga tanong tungkol sa system)

### Architecture

```
Source (file / API / scrape / manual)
   └─► SourceFetcher (isang class bawat source)
         └─► FetchResult (values + curve points)
               └─► MarketDataPipeline  ── ScraperRun (log: Success / Failed)
                     └─► indicator_values / curve_points (upsert)
                           └─► Dashboard::getDashboardViewData() ─► dashboard-overview.blade.php
```

- Kapag nag-fail ang source, `Failed` ang `ScraperRun` at buo pa rin ang lumang data. Hindi nagka-crash ang page.
- Ang "Previous" value ay galing sa naka-save na history, hindi sa previous na field ng API.

### Database

| Table | Ginagamit para sa |
|---|---|
| `data_sources` | Listahan ng sources: name, slug, method (API/File/Scrape/Manual), URL, description, config (fetcher class at indicator code) |
| `market_indicators` | Bawat metric: code, label, unit, category, display order |
| `indicator_values` | Isang value bawat indicator bawat `as_of_date` (may optional na `as_of_time`) |
| `curve_points` | Mga curve: BVAL tenors, LME bid/offer |
| `scraper_runs` | Log ng bawat run: status, oras, records, error, snapshot path |

- **Unique key:** `(indicator, as_of_date)` para sa values; `(indicator, as_of_date, point_key)` para sa curve points. Dahil dito, safe i-re-run ang anumang fetch.

### Schedule at commands

| Command | Ano ang ginagawa | Kailan |
|---|---|---|
| `php artisan market:pull-eod` | Tumatakbo ang lahat ng active na source | Automatic: **weekdays 18:30**, `withoutOverlapping()` |
| `php artisan market:pull-eod --source=<slug>` | Isang source lang | Manual |
| `php artisan market:backfill-inflation` | Buong history ng inflation (2019+) | Isang beses, safe i-re-run |
| `php artisan market:backfill-gdp` | Buong history ng GDP (2001+) | Isang beses, safe i-re-run |
| `php artisan market:backfill-lme-lead` | Buong taon ng LME Lead (Westmetall) | Isang beses, safe i-re-run |

- **Timezone:** itakda ang `APP_TIMEZONE=Asia/Manila` sa `.env` para tumakbo ang 18:30 pagkatapos ng market close.

### Admin (`/rpanel`)

- **Market Indicators** (`/rpanel/market-indicators`): listahan ng lahat ng indicator.
  - **Enter Value:** quick action para mag-type ng value. Ito ang paraan para sa manual entry.
  - **Value history:** lahat ng nakaraang values, pwedeng itama.
  - **Curve points:** para sa LME Lead bid/offer (lumalabas lang sa mga curve indicator).
- **Navigation:** Data Sources at Market Indicators ay nasa **Master Data** group sa sidebar. Magkaiba na ang icon ng bawat isa (Data Sources = server stack, Market Indicators = chart bar).
- **Data Sources** (`/rpanel/data-sources`): CRUD ng sources. Dito makikita ang method, URL, description, at config.

### Mga source at ang uri nila

| Source slug | Uri | Endpoint / file | Fetcher |
|---|---|---|---|
| `bsp-usdphp` | File | `bsp.gov.ph/Statistics/RERB/RERB.xlsx` | `BspUsdPhpFetcher` |
| `bsp-key-rates` | File (PDF) | `bsp.gov.ph/statistics/keystat/sefi.pdf` | `BspKeyRatesFetcher` |
| `psa-inflation` | API | `openstat.psa.gov.ph/PXWeb/api/v1/en/DB/2M/PI/CPI/2018NEW/0012M4ACP23.px` | `PsaInflationFetcher` |
| `psa-gdp` | API | `openstat.psa.gov.ph/PXWeb/api/v1/en/DB/2B/NA/QT/1SUM/0022B5BEXQ2.px` | `PsaGdpGrowthFetcher` |
| `bap-fx-summary` | File (HTML table) | `bap.org.ph/fxhistorical.html` | `BapFxSummaryFetcher` |
| `pds-bval` | API (GraphQL) | `pds.com.ph/api-mp/graphql/` | `PdsBvalFetcher` |
| `pse-index-summary` | File (HTML table) | `edge.pse.com.ph/index/form.do` | `PseIndexSummaryFetcher` |
| `mi-gold`, `mi-wheat`, `mi-brent` | Scrape | `markets.businessinsider.com/commodities/...` | `MarketsInsiderPriceFetcher` |
| `westmetall-lme-lead` | File (HTML table) | `westmetall.com/en/markdaten.php?action=table&field=LME_Pb_cash` | `WestmetallLeadFetcher` |
| `lme-lead-official-prices` | Manual | — | — |

### Mga paalala sa sources

- **Naka-block mula sa dev network:** BSP (Akamai), PSA main site (Cloudflare), `www.pse.com.ph` market pages (403), `lme.com` (403). Ang mga API at file na nasa listahan ay tested na gumagana.
- **Hindi pa tested mula sa production server:** lahat ng source. Maaaring magka-block pagdating ng deployment.
- **Lisensya:** PSE (personal, non-commercial), LME (lisensyado, via Westmetall), Markets Insider (scraping, may sariling terms). Desisyon ng Ramcar ang final na pag-apruba.

### Mga pattern na sinusunod

- **Previous value:** naka-save na history, hindi kinukuha sa API. Kaya sa unang run, "No prior value" muna.
- **Batch queries:** isang query para sa buong table (inflation, GDP, BAP, BVAL). Walang N+1.
- **Sparkline threshold:** ≥ 5 readings bago gumuhit ng linya.
- **Walkthrough:** ang `data-tour` attributes sa Blade at ang `Dashboard::tourDefinitions()` ang naghahatid ng Guide at Technical. Kapag may bagong card, dagdagan ang dalawa.

### Testing

- 113 automated tests. Lahat ng fetcher ay sinubukan laban sa **totoong nakuhang responses** sa `tests/Fixtures/`.
- Para patakbuhin: `php artisan test --compact`.
- Para sa frontend: `npm run build` pagkatapos ng Blade o CSS changes.

---

## Mga Bagay na Static Pa (buod para sa demo)

| Bahagi | Status | Bakit |
|---|---|---|
| Foreign Flow | ⚠️ Not dynamic | Walang source sa PSE Edge |
| Inflation band sa Macro section | ⚠️ Static | Sample chart |
| 7 sa 11 BSP Key Rates | ✍️ Manual entry | Walang automated source |
| LME Lead bid/offer table | ✍️ Manual entry | Walang automated source (lisensyado) |
| Sparklines ng commodities | ⚠️ Not dynamic hanggang 5 readings | Kulang pa ang history |
