# Treasury Market Updates — Gabay sa Mga Module

> Layunin ng doc na 'to: maintindihan mo kung ano ba talaga ang ginagawa natin dito, kahit hindi ka financial person. Taglish 'to, may examples, at nakalagay kung anong files/features ang kasama sa bawat module.

## Ano ba itong project?

Every day, may nagpapadala sa Treasury team ng Excel file na puno ng mga current rates — presyo ng dollar, interest rates, presyo ng stocks, presyo ng oil/gold/wheat, atbp. Yung Excel na 'yon, kailangan i-open, i-check manually, tapos i-forward pa sa mga kailangan makakita.

**Ang gagawin natin: gagawa tayo ng isang website (dashboard) na "one-stop shop"** — buksan mo lang, andun na lahat, updated araw-araw, walang need i-check ng Excel pa. Parang binibihag natin lahat ng "presyo ngayon" sa isang screen.

Ginagawa natin 'to gamit ang **Laravel** (yung "utak" ng system sa likod) at **Filament** (yung gumagawa ng admin panel / dashboard UI na makikita mo sa browser).

## Mabilis na glossary (para sa hindi financial person)

Bago tayo pumunta sa modules, ito muna yung mga terms na paulit-ulit lalabas:

| Termino | Sa simpleng salita |
|---|---|
| **FX rate / USD-PHP** | Presyo ng dollar kung ipapalit mo sa peso — parang sa money changer, pero 'to yung "official" na presyo na ginagamit ng mga bangko. |
| **Interest rate (RRP, deposit, lending rate)** | Kung magpapahiram o magpapautang ka ng pera, ito yung "renta" o extra na babayaran/matatanggap mo. Si BSP (parang "central bank" ng Pilipinas) ang nagtatakda ng mga rates na 'to para kontrolin presyo ng bilihin sa bansa. |
| **Inflation rate** | Kung gaano kabilis tumataas ang presyo ng mga bagay-bagay (bigas, pamasahe, kuryente) kumpara noong isang taon. Mataas na inflation = mabilis lumaki ang gastos mo kahit pareho lang binibili mo. |
| **GDP growth** | Parang "report card" ng buong ekonomiya ng Pilipinas — kung lumalaki ba ang kabuuang kita/produksyon ng bansa. |
| **PSEi / stock market** | Parang "average grade" ng pinaka-malalaking kumpanya sa Pilipinas na nakalista sa stock exchange. Kung tumaas ang PSEi, generally maganda ang klima ng negosyo; kung bumaba, kabaligtaran. |
| **BVAL yield curve** | Kapag nagpapahiram ka ng pera sa gobyerno (government bonds), iba-iba ang "renta" (interest) depende kung gaano katagal — 1 buwan, 1 taon, hanggang 25 taon. Yung listahan/chart ng mga rates na 'yon per tagal, tinatawag na "yield curve". |
| **Commodities (oil, gold, wheat, lead)** | Presyo ng mga hilaw na bagay na ginagamit ng industriya — gasolina/oil, ginto, trigo (pang-tinapay), at lead/tingga (ginagamit sa baterya, cable, atbp). Kahit di ka nag-tra-trade, apektado tayo dito kasi nakaka-apekto sa presyo ng gasolina, pagkain, at produkto. |
| **Scraping vs API** | "Scraping" = kinukuha natin yung datos mula sa isang website sa pamamagitan ng pag-basa ng HTML nito (parang manual copy-paste, pero automated). Delikado kasi kapag pinalitan ng website yung disenyo nila, pwedeng masira yung "pagbasa" natin. "API/file" = yung website mismo ang nagbibigay ng datos sa isang paraan na designed talaga para kunin ng ibang programa — mas matibay, hindi basta-basta nasisira. |
| **N+1 query** | Isang common na "bug" sa mga database-driven na app: sa halip na isang tanong lang sa database ("bigyan mo ko lahat ng presyo"), 12 beses tinanong nang paisa-isa ("presyo ng #1? presyo ng #2?..."). Gumagana pa rin, pero **mas mabagal**, lalo na kapag dumami pa ang datos. Solusyon: "eager loading" — isang malaking tanong na lang, isang beses. May tool kami (`beyondcode/laravel-query-detector`) na dev-only, awtomatikong nagbabantay nito at sumisigaw kapag may nahuling N+1 — ganito na-detect yung isyu sa BSP Key Rates cards, at na-ayos agad (tignan sa Module 1). |

## Mga totoong files/sources na ginagamit natin (reference)

Sa buong doc na 'to, paulit-ulit babanggitin ang mga file/website na 'to (`RERB.xlsx`, `sefi.pdf`, atbp.) — minsan nakakalito dahil magkakamukha ang pangalan pero magkaiba pala sila. Ito, sa isang lugar na lang, kung ano talaga ang bawat isa:

| Pangalan | Saan galing | Anong klase | Gaano kadalas updated | Ginagamit para saan |
|---|---|---|---|---|
| **`RERB.xlsx`** | BSP — `bsp.gov.ph/Statistics/RERB/RERB.xlsx` | Maliit na Excel file (~25 KB) — literal na "listahan ng palitan ng pera ngayong araw" (USD, EUR, JPY, atbp. laban sa Peso) | **Araw-araw** (banking day) — ito yung "totoong live" na file | USD/PHP hero card sa dashboard — `BspUsdPhpFetcher` |
| **`pesodollar.xlsx`** | BSP — `bsp.gov.ph/statistics/external/pesodollar.xlsx` | MALAKING archive file — buong history ng presyo ng dollar mula **1978** hanggang ngayon | Hindi pala araw-araw — nung tiningnan namin, nag-stop ng ilang linggo (matanda na) | **Hindi** ginagamit sa live update — nakatabi para sa Module 3 (backfill/history) |
| **`sefi.pdf`** | BSP — `bsp.gov.ph/statistics/keystat/sefi.pdf` ("Selected Economic and Financial Indicators") | Mahabang PDF, parang "libro" — may GDP, empleyo, interest rates, at marami pang ibang estadistika ng buong bansa | Regular (mismong araw ng pagbukas namin, "11-Sep-26" ang petsa) | 3 sa 11 BSP Key Rates cards (ON RRP, 7-day TDF, 28-day BSP Securities) — `BspKeyRatesFetcher`. Ang ibang 8 cards, blangko o "stale" ang laman nito, kaya manual na lang muna sila. |
| **PSA OpenSTAT (PxWeb API)** | `openstat.psa.gov.ph/PXWeb/api/v1/...` | **Hindi file** — isang "API," ibig sabihin isang tanungan sa internet na sasagot ng datos (JSON) sa halip na magbigay ng file na i-do-download | Buwanan (updated agad pagkatapos mag-publish ang PSA ng bagong buwan) | Inflation Rate — `PsaInflationFetcher`. Ito yung **pinaka-maaasahan** sa lahat ngayon — hindi kami na-block dito, hindi tulad ng lahat ng BSP files sa taas. |

**Mabilis na paraan para matandaan:** kung "araw-araw" ang datos na kailangan natin (parang "presyo ngayon"), file/API na "live" ang hanapin (`RERB.xlsx`, PSA API) — HINDI yung malaking archive file (`pesodollar.xlsx`). Kung "kasaysayan"/backfill naman ang kailangan, doon na natin gagamitin yung malaking archive.

## Kung saan tayo galing (napagdaanan na)

Tapos na natin: gumawa kami ng **static na dashboard** — parang mannequin sa tindahan. Ganito na ang itsura ng final dashboard. Karamihan sa mga numbers na nakikita mo (6,190.23 para sa PSEi, mga commodity prices, atbp.) ay **sample/fake data pa rin**, naka-type diretso sa design. Pero dalawang parte na — ang USD/PHP hero card at ang buong BSP Key Rates strip (12 cards lahat) — ay **binabasa na mula sa database**, kahit manual pa rin ang laman nito habang wala pang totoong koneksyon sa BSP (tignan yung "proof points" sa Module 1 sa ibaba).

Dagdag pa dito, na-set up na rin natin ang ilang **"foundation" na tooling** — hindi pa direktang bahagi ng disenyo ng dashboard, pero kailangan na bago pa man tayo pumasok sa Module 1:

- **Login / authentication** — kailangan na ngayon mag-login bago makita ang dashboard (dati bukas lang basta-basta, walang login). May "forgot password" flow na rin.
- **Notification bell** — may built-in na "bell" icon na sa panel, may sarili na ring `notifications` table sa database — gagamitin natin 'to mamaya para sa mga alerto (hal. Module 5).
- **Mobile bottom navigation** — may hiwalay na navigation bar sa ibaba ng screen kapag binuksan sa phone, para "thumb-friendly" ang paggamit — plugin na `hammadzafar05/mobile-bottom-nav`.
- **Laravel Pulse** — parang "vital signs monitor" ng application mismo (response times, slow queries, exceptions, atbp.), makikita sa `/pulse`. Groundwork ito para sa Module 4 (Admin & Monitoring Tools), kung saan natin gagamitin talaga 'to para bantayan ang mga scraper/source runs.
- **Activity log** (`spatie/laravel-activitylog`) — "audit trail" na susubaybay kung sino gumawa ng anong pagbabago sa system (hal. sino nag-edit ng manual override) — groundwork din para sa Module 4.
- **N+1 query detector at Laravel Pao** — dev-only tools lang, hindi makikita ng end users, tumutulong maghanap ng slow queries at mag-optimize habang dev pa tayo.

**Mga files dito:**
- `app/Filament/Pages/Dashboard.php` — ito yung "utak" ng dashboard page mismo.
- `resources/views/filament/pages/dashboard-overview.blade.php` — ito yung actual na layout/design (mga cards, charts, drawer na lumalabas kapag click mo yung isang card).
- `resources/css/filament/rpanel/theme.css` — mga kulay, spacing, font sizes na ginamit sa disenyo.
- `app/Providers/Filament/RpanelPanelProvider.php` — configuration ng buong panel: pangalan, fonts, tema, login/password-reset, notification bell, at mobile bottom nav plugin.
- `config/pulse.php` at `resources/views/vendor/pulse/dashboard.blade.php` — configuration at view ng Laravel Pulse monitoring dashboard.
- `database/migrations/*_create_pulse_tables.php`, `*_create_activity_log_table.php`, `*_create_notifications_table.php` — mga bagong "baul" para sa monitoring, audit log, at notifications (hiwalay pa rin sa mga market-data tables — 'yon pa sa Module 1).
- `tests/Feature/DashboardTest.php` — sinisiguradong bumubukas nang maayos ang dashboard para sa naka-login na user, at na-rere-redirect sa login page ang mga guest.

**Mga karagdagang files pagkatapos ng "proof points" (USD/PHP + BSP Key Rates, tignan Module 1 sa ibaba):**
- `database/seeders/MarketDataSeeder.php` — dito nakalagay yung mga manual/sample na values habang wala pang totoong fetcher.
- `app/Models/MarketIndicator.php` — dito nakalagay yung `latestValue()` relation (ginawang "eager-loadable" para maiwasan ang N+1 — tignan yung glossary sa taas).
- `tests/Feature/MarketIndicatorEagerLoadingTest.php` — sinisiguradong hindi na babalik yung N+1 bug kahit may mag-edit pa ng code mamaya.

## Sunod-sunod na modules

### Module 1 — Foundation: Database + "Delivery System" ng datos ✅

**Sa simpleng salita:** bago natin ilagay ang totoong numero sa dashboard, kailangan muna natin ng "filing cabinet" (database) kung saan itatago natin lahat ng presyo, at isang "delivery system" na kukuha ng bagong presyo araw-araw at ilalagay sa filing cabinet na 'yon.

Tapos na 'to — nandito na yung **istraktura** (structure). Parang tapos na gumawa ng mga baul at estante; may laman na rin sa ilang parte, bilang "proof points" (tignan sa ibaba).

**Mga bagong "baul" (database tables):**
- `data_sources` — listahan kung saan galing bawat presyo (halimbawa: "BSP website", "PSE", atbp.) at kung paano natin kinukuha (API, file, scraping, o manual typing). Model: `App\Models\DataSource`.
- `market_indicators` — listahan ng bawat specific na presyo/rate (hal. "USD/PHP", "PSEi", "ON Lending Rate"). Model: `App\Models\MarketIndicator`.
- `indicator_values` — dito na-store ang aktwal na numero kada araw (hal. "Aug 25, 2026: USD/PHP = 61.7350"). Model: `App\Models\IndicatorValue`.
- `curve_points` — para sa mga presyong may "listahan sa loob ng listahan" tulad ng BVAL yield curve (1M, 3M, 1Y... bawat isa may sariling numero) o LME Lead (Cash, 3-month, Dec-27...). Model: `App\Models\CurvePoint`.
- `scraper_runs` — parang "log book" — tinatala kung kailan natin sinubukang kumuha ng bagong datos, kung successful ba o may error. Model: `App\Models\ScraperRun`.

**Bagong "delivery system":**
- Common na "contract" — `App\Services\DataSources\Contracts\SourceFetcher` interface — na susundin ng bawat paraan ng pagkuha ng datos, para pareho lang ang pattern kahit iba-iba ang source. Kasama dito ang `FetchResult` (yung "resibo" na isinasauli ng bawat fetcher) at `MarketDataPipeline` (yung nagre-record ng `ScraperRun` at nag-a-upsert ng values/curve points sa database).
- Command na `market:pull-eod` (`App\Console\Commands\PullEndOfDayMarketData`) na tatakbo tuwing gabi (end-of-day), kukuha ng bagong presyo sa lahat ng active na source, at ilalagay sa database. Pwede rin patakbuhin isang source lang: `php artisan market:pull-eod --source=bsp-usdphp`.
- Unang admin page (`DataSourceResource` sa Filament, `/rpanel/data-sources`) para makita mo kung anong mga sources ang naka-register, kung anong category/method, kung naka-on/off, at kung kailan huling successful yung pagkuha.

**Halimbawa paano ito ginagamit:** kapag may nag-request tayo ng bagong source (hal. "gold price"), hindi na tayo magsusulat ng bagong sistema mula zero — mag-a-add lang tayo ng bagong row sa `data_sources`, gagawa ng isang maliit na klase na sumusunod sa `SourceFetcher` contract, at automatic na siyang sasali sa gabi-gabing "delivery".

**Proof points — 2 parte ng dashboard, live na sa database (pero manual pa ang laman):** para masubukan ang buong "pipeline" mula database hanggang sa dashboard, ginawa na natin ang dalawang totoong halimbawa:

1. **USD/PHP hero card** (yung malaking numero sa itaas) — 1 indicator (`usdphp_spot`).
2. **BSP Key Rates strip** — lahat ng 11 cards (Inflation Rate hanggang 28-day TDF) — 11 indicators, bawat isa may sariling `code` (hal. `on_lending_rate`, `target_rrp_rate`, atbp.) at sariling "as of" date, dahil magkaiba talaga ang petsa ng bawat isa (tignan yung "Interest rate" na entry sa glossary).

Sa ngayon, **manual pa ang entry** ng values (via `database/seeders/MarketDataSeeder.php`, hindi pa totoong koneksyon sa BSP) — pero lahat ng 12 cards na 'to ay **binabasa na mula sa database**, hindi na naka-type diretso sa design. Kung walang laman ang database, may "No data yet" (o "—" sa Key Rates) na lalabas sa halip na mag-crash. Ang totoong koneksyon sa BSP (file download man o scraping) ay gagawin pa sa Module 2 — hintay pa rin ang exact na URL/method.

**Natutunan sa gitna ng paggawa nito — N+1 query bug, na-detect at na-ayos agad:** nung una, para makuha yung latest value ng bawat isa sa 12 indicators, 12+ magkakahiwalay na tanong ang ginagawa sa database (isa-isa) sa halip na isa lang. Awtomatikong na-flag 'to ng dev-only monitoring tool namin (`beyondcode/laravel-query-detector`). Na-ayos sa pamamagitan ng "eager loading" (`MarketIndicator::whereIn(...)->with('latestValue')->get()`) — mula sa ~25 tanong, naging 2-3 na lang. May bagong test pa (`MarketIndicatorEagerLoadingTest.php`) para hindi na 'to mangyari ulit kahit may mag-edit pa ng code mamaya. **Bakit importante 'to malaman:** ganito ang magiging itsura ng bugs habang dumadami ang mga indicators natin (12 ngayon, pupunta ng 50+ pagsapit ng Module 2) — kaya mahalagang may automated na "bantay" na tool bago pa maging problema sa totoong paggamit.

### Sagot: ano bang ibig sabihin ng bawat card sa "BSP Key Rates", at bakit magkaiba ang mga petsa?

Para sa hindi financial person, 11 cards 'to na parang magkakamukha (puro porsyento), pero magkakaiba talaga sila. Ito isa-isa, tapos ipapaliwanag din kung bakit hindi pareho ang petsa ng bawat isa.

**Yung tatlong "bakod" ng interest rate sa bansa (magkakasama, magkakaugnay):**

| Card | Sa simpleng salita |
|---|---|
| **ON Lending Rate** | Kapag kailangan ng isang bangko ng pera kaagad (overnight lang - bukas babayaran na), ito yung pinakamataas na rate na sisingilin ni BSP sa kanila. Parang "pinaka-mahal" na pautang. |
| **Target RRP Rate** | Ito yung **pangunahing "policy rate"** ni BSP - yung headline na balita kapag may sinasabing "nagtaas/nagbaba ng interest rate si BSP." Ginagamit para kontrolin kung gaano kadali o kahirap humiram/mag-ipon ng pera sa buong bansa. |
| **ON Deposit Rate** | Kabaligtaran ng ON Lending - ito yung rate na matatanggap ng isang bangko kapag nag-"ipon" sila ng pera kay BSP overnight. Parang "pinaka-mura," o yung "sahig." |

Ang tatlong 'to magkasama bumubuo ng tinatawag na **"interest rate corridor"** - parang bakod na itinatakda ni BSP kung saan dapat pumapasok ang mga panandaliang interest rate sa bansa. 'Yun din ang dahilan bakit magkakalapit ang mga numero nila (4.75% / 4.25% / 3.75% halimbawa).

**Yung mga "totoong nangyari" sa pinaka-huling benta/hiraman ni BSP:**

| Card | Sa simpleng salita |
|---|---|
| **ON RRP Rate** | Ito yung ACTUAL na rate na natanggap sa pinaka-bagong "lelang"/auction ni BSP - hindi lang "goal" tulad ng Target RRP, kundi yung nangyari talaga. Kaya may extra na "Accepted: 4.2300%-4.2800%" - dahil magkaiba-iba ang inalok ng bawat bangko na sumali. |
| **28-day BSP Securities (WAIR)** | "BSP Securities" ay parang resibo ng utang ni BSP sa mga bangko, babayaran pagkatapos ng 28 araw. **WAIR** = "Weighted Average Interest Rate" - ibig sabihin, average lang ng lahat ng rates na binigay sa lahat ng bumili sa auction na 'yon. |
| **56-day BSP Securities (WAIR)** | Kaparehong konsepto ng nasa itaas, pero 56 araw naman ang tagal bago babayaran. |
| **7-day TDF (WAIR)** | "TDF" = Term Deposit Facility - katulad ng BSP Securities, pero ibang klase ng "ipon" na inaalok ni BSP sa mga bangko. 7 araw lang ang tagal bago mababawi ng bangko ang pera nila plus interest. |
| **14-day TDF (WAIR)** | Kapareho ng 7-day TDF, pero 14 araw ang tagal. |
| **28-day TDF (WAIR)** | Kapareho rin, pero 28 araw ang tagal - at ito mismo yung may pinaka-lumang petsa (Oct 7, 2020) dahil matagal nang hindi ginagamit ni BSP ang tenor na 'to. |

**Yung mga "hiwalay" na hindi talaga galing sa BSP mismo:**

| Card | Sa simpleng salita |
|---|---|
| **Inflation Rate (2018=100)** | Kung gaano kabilis tumataas ang presyo ng mga bilihin, kumpara noong isang taon. "2018=100" ibig sabihin ginamit nila ang presyo noong 2018 bilang "normal" na panukat. **Hindi 'to galing kay BSP** - galing 'to sa PSA (Philippine Statistics Authority), kaya iba ang cadence ng pag-update nito kumpara sa ibang cards. |
| **ON Reference Rate** | Dati itong ginagamit na "reference rate," pero **hindi na ginagamit ni BSP mula Sep 2023**. Kaya kung minsan "****" na lang ang lalabas - ibig sabihin "wala/discontinued na," hindi "may current value na 4 stars."|

**Ngayon, bakit magkaiba ang mga petsa (March 2026, Apr 7 2026, Oct 2025, Sep 2023, Oct 2020)?**

Dahil bawat isa dito **iba ang dalas ng pag-update**, at hindi porket "isang widget" sila lahat, sabay-sabay din silang nagba-bago:

1. **Buwanan lang** (Inflation Rate) - kasi galing 'to sa PSA, at buwan-buwan lang talaga sila naglalabas ng bagong inflation report. Kahit "araw ngayon," ang pinaka-bago pa ring available ay yung datos ng nakaraang buwan (may "lag" talaga bago ma-verify at ma-publish ang buong-bansang datos).
2. **Madalas, pero hindi araw-araw** (ON Lending, Target RRP, ON Deposit, ON RRP) - updated 'to kada meeting/desisyon ng BSP Monetary Board, o kada auction - kaya "kagagawa lang" karaniwan, pero hindi porke't "araw-araw" e nagbabago rin araw-araw.
3. **Depende sa huling auction** (28-day at 56-day BSP Securities, mga TDF) - kung hindi pa nag-a-auction ulit si BSP ng partikular na "tenor" (halimbawa, 56 araw), mananatiling "matanda" ang huling datos nito kahit updated na yung iba. Ganito rin bakit iba ang petsa ng 28-day BSP Securities (Apr 6) kumpara sa 56-day (Oct 24, 2025) - magkaibang schedule sila ng lelang/auction.
4. **Discontinued na, hindi na tinatanggal** (ON Reference Rate, 28-day TDF) - minsan tumitigil gamitin ni BSP ang isang klase ng rate (halimbawa, dahil may mas bagong paraan na ginamit nila), pero itinatago pa rin nila ito sa listahan bilang "huling nalathalang datos" - kahit taon-taon na itong hindi gumagalaw.

**Bakit importante 'to para sa atin bilang developers:** kaya nga sa Module 1, ginawa nating sarili ang "as of date" ng **BAWAT** indicator (hindi isang petsa lang para sa buong widget) - kasi kung isang petsa lang ang gagamitin natin, mali agad ang lalabas sa mga cards na matagal nang hindi nagbabago (parang sasabihin nating "kagagawa lang" ang 28-day TDF kahit 2020 pa 'yon huling na-auction). Tignan yung "Proof points" sa itaas para sa detalye ng schema na sumusuporta dito.

### Module 2 onwards — Ikonekta ang Bawat Totoong Source

Dito na natin ikokonekta yung mga totoong presyo, isa-isa. Bawat isa dito ay hiwalay na module — kaya kahit matagal ang isa, hindi ma-a-apektuhan yung iba.

| Source | Ano ang kinukuha | Paano kukunin | Halimbawa |
|---|---|---|---|
| **BSP USD/PHP file** ✅ | Presyo ng dollar araw-araw | **File download, TAPOS NA** — `RERB.xlsx`, isang maliit na file na "araw-araw" talaga binabago ni BSP sa parehong URL. Matatag ito kasi hindi tayo "nangungupit" ng webpage, binibigay talaga nila ang file. | "62.8500" (Sep 16, 2026) lalabas sa hero card sa itaas ng dashboard, kapag na-run ang `market:pull-eod`. |
| **BSP policy rates file** | ON Lending, Target RRP, ON Deposit, atbp. | **File download** din (PDF na regular nilang nilalabas) | Yung "Key Rates Strip" na cards sa dashboard. |
| **BAP FX weighted average** ✅ | Mas detalyadong presyo ng dollar (Open, High, Low, Close, atbp.) | **May nahanap na kami — hindi na kailangang scraping.** `bap.org.ph` ay may malinis na HTML page (`fxhistorical.html`) na may fixed na URL pattern per buwan — parang "file" din ang gawi, hindi random na webpage na basta binabasa. | Yung "FX Summary" table sa dashboard. |
| **PDS PHP BVAL** ✅ | Yield curve ng government bonds (1M hanggang 25Y) | **May nahanap na kaming totoong public API — hindi na kailangang scraping.** May GraphQL API pala ang PDS na pwedeng gamitin nang libre (walang subscription kailangan). Tingnan yung buong detalye sa ibaba. | Yung yield curve chart. |
| **PSE stock market** | PSEi, mga sector indices, advances/declines | May opisyal silang bayad na "Data Products" — nag-inquire na tayo. Habang wala pang sagot, scraping muna bilang panandalian. | Yung "Philippine Stock Market Overview" section. |
| **PSA Inflation & GDP** | Buwanang inflation rate, quarterly GDP growth | **Real API** — meron palang opisyal na PSA OpenSTAT API, hindi na kailangan i-scrape ang press release | Yung "Macroeconomic Overview" charts. |
| **Brent oil** ✅ | Presyo ng krudong langis | Ginamit namin ang **Markets Insider** (yung link sa Excel niyo), hindi ang EIA — tingnan ang "Commodities" update sa ibaba. May libreng EIA API pa rin na pwedeng fallback. | Yung "Brent Crude" commodity card. |
| **Gold, Wheat** ✅ | Presyo ng ginto at trigo | **Markets Insider page** (scraping — walang API). Yung "bayad na commercial API" ay hindi pa kailangan sa ngayon. | Yung "Spot Gold" at "Wheat" cards. |
| **LME Lead** ✅ (bahagya) | Cash / 3-month price ng lead | **Westmetall** (libreng public table) para sa cash/3-month; yung bid/offer table ay **manual** pa rin. | Yung "LME Lead" card, price curve, at trading summary table. |

**Bakit hiwalay-hiwalay na module bawat isa?** Kasi magkaiba ang "kalikasan" ng bawat source — may file, may API, may scraping. Kung isahin natin lahat sa isang malaking gawain, mahirap i-tsek kung saan nagkamali pag may error. Isa-isa, mas madaling i-tsek at i-ayos.

**Update — unang tunay na fetcher, tapos na (USD/PHP):**

Habang hinahanap namin ang tamang file para sa USD/PHP, dalawang magkaibang file pala ang meron ang BSP, at magkaiba ang gamit:

- **`pesodollar.xlsx`** — malaking archive mula 1978, pero **hindi pala updated araw-araw** (nung ni-tsek namin, tumigil sa Aug 28 kahit Sep 16 na). Mas bagay 'to sa **Module 3 (backfill)** — doon kasi okay lang kahit "matanda" ang datos.
- **`RERB.xlsx`** ("Daily Reference Exchange Rate Bulletin") — mas maliit na file, at TALAGANG araw-araw binabago ni BSP sa parehong URL. Ito na ang ginamit namin.

Ginawa na ang `App\Services\DataSources\Fetchers\BspUsdPhpFetcher` — kinukuha ang file, hinahanap ang petsa at ang USD row, tapos kinukuha ang "PHIL.PESO EQUIVALENT" column bilang presyo. Na-tsek namin gamit ang **totoong file** mula sa BSP (na-save bilang `tests/Fixtures/bsp-rerb-sample.xlsx` para sa mga test) — 62.8500 para sa Sep 16, 2026, malapit sa 62.719 na nakita mo sa Excel niyo para sa Sep 14. Magkatugma!

**May isang problema pa:** kapag sinubukan naming kunin talaga ang file (hindi lang sa pag-tsek), na-block kami ng BSP's website mismo ("Access Denied," parang naka-firewall sila laban sa mga dev/cloud servers). Hindi pa namin alam kung ma-a-apektuhan din ang totoong server na gagamitin pag-launch — kailangan pang subukan doon mismo. Kung na-block din pala doon, kakailanganin namin ng "scraping service" (parang tulay/proxy na kumukuha ng datos para sa amin) — kagaya ng napag-usapan na natin para sa LME.

**Bakit hindi 'to naging problema sa buong system:** dahil dinisenyo namin ang `MarketDataPipeline` (Module 1) para "malambot mahulog" kapag may error — sinubukan namin talaga tumakbo ang bagong fetcher, na-block, at ang nangyari lang: na-record bilang "Failed" sa `scraper_runs` (yung log book), habang yung dati nang naka-save na value sa dashboard ay nanatiling buo, walang na-corrupt o nag-crash.

**Update — sinuri na namin ang `sefi.pdf` para sa 11 cards ng "BSP Key Rates":**

Bago namin gawin ang fetcher para sa buong "BSP Key Rates" strip, binuksan at binasa muna namin ang `sefi.pdf` (yung PDF na natagpuan namin dati) para tignan kung ilan doon sa 11 cards ang may malinaw, kumpletong datos. Ito yung resulta:

| Card | Status | Why |
|---|---|---|
| **ON RRP Rate** | ✅ Solid | Real current value present, matches BSP's own footnote exactly. The "Accepted: X%–Y%" sub-line isn't in this document though — that detail would need a separate BSP auction-results source |
| **28-day BSP Securities (WAIR)** | ✅ Solid | Real current value present |
| **7-day TDF (WAIR)** | ✅ Solid | Real current value present |
| **ON Lending Rate** | ⚠️ Issue *(corrected)* | Looked solid at first glance, but the table cell is a **stale monthly average** — it still read 5.29% after BSP's Aug 28 move to 5.50%. The true current value only exists in a footnote sentence, not a parseable cell. |
| **ON Deposit Rate** | ⚠️ Issue *(corrected)* | Same problem as ON Lending Rate — table shows a stale 4.29% while the footnote says the real current value is 4.50%. |
| **56-day BSP Securities (WAIR)** | ⚠️ Issue | Column exists, but was **blank** when I checked — BSP simply hadn't re-auctioned this tenor recently. Data only shows up when they actually run one. |
| **14-day TDF (WAIR)** | ⚠️ Issue | Same pattern — column exists, blank at last check |
| **28-day TDF (WAIR)** | ⚠️ Issue (structural) | Blank across **every year** in the document — BSP hasn't used this tenor since 2020. There's nothing live to ever fetch here; it's permanently frozen at the value we already have. |
| **Target RRP Rate** | ⚠️ Issue | Not a data cell at all — only mentioned in a prose footnote ("On 27 August 2026, the Monetary Board decided to raise the Target RRP Rate...") when it changes. Not something a fetcher can reliably parse as a number. |
| **ON Reference Rate** | ⚠️ Issue (structural) | Not in this document at all, and separately confirmed discontinued by BSP since Sep 2023. Nothing to fetch, from any source — this one stays a permanent `****`. |
| **Inflation Rate (2018=100)** | ⚠️ Wrong source | It *is* in the PDF, but as a Jan–Jun 2026 **period average** (5.2%), not the single-month figure the card needs (e.g. "March 2026: 4.1%"). Wrong granularity — this one needs PSA's monthly release instead, not `sefi.pdf`. |

**Buod (na-correct):** unang tingin namin, 5 sa 11 ang solid. Pero mas malalim na tingin, **3 lang talaga** ang genuinely solid — ON RRP, 28-day BSP Securities, 7-day TDF. Yung ON Lending at ON Deposit, mukhang malinaw sa unang tingin, pero pag sinuri namin mabuti, "matandang" monthly average pala ang laman ng cell nila (5.29%/4.29%), hindi yung totoong current value (5.50%/4.50% per BSP's sariling footnote). Sa halip na hulaan/i-estimate na lang namin ang totoong value (delikado 'to, pwedeng magkamali nang tahimik), pinili naming manatili itong manual muna hanggang may mas maaasahang source. 3 pa rin ang may datos pero hindi kumpleto (56-day BSP Securities, 14-day TDF, 28-day TDF), 2 talagang walang makukuha kahit saan (Target RRP sa format na 'to, ON Reference Rate), at 1 mali ang pinagkunan (Inflation Rate).

**Ginawa na ang fetcher para sa 3 tunay na solid:** `App\Services\DataSources\Fetchers\BspKeyRatesFetcher` — kinukuha ang `sefi.pdf`, tapos hinahanap ang mismong linya ng ON RRP / 7-day TDF / 28-day BSP Securities. May kalikutan dito: pareho ang label na "28-day WAIR" at "7-day WAIR" sa DALAWANG magkaibang seksyon ng document (Term Deposit Facility vs BSP Securities), kaya kailangan sundan ng fetcher kung nasaan siya sa document bago kumuha ng value — nasubok na 'to gamit ang totoong PDF (`tests/Fixtures/bsp-sefi-sample.pdf`) para masiguradong hindi nagkakamali. Yung 8 natitirang indicators (kasama na ang ON Lending at ON Deposit) ay manatiling manual — hindi sila hahawakan ng pipeline dahil wala silang code sa fetch result.

At tulad ng USD/PHP, **parehong 403 block** ang natanggap namin sa `sefi.pdf` mula sa dev sandbox namin nung sinubukan naming talaga patakbuhin (na-tsek namin gamit ang totoong `market:pull-eod --source=bsp-key-rates`) — kaya kahit yung 3 "solid" ay hindi pa rin namin na-confirm na magagamit talaga kapag naka-deploy na sa totoong server. Ang maganda, na-verify namin na "malambot" talaga humulog ang system: na-record bilang "Failed," at nanatiling buo ang lahat ng dating naka-save na values.

**Update — unang TALAGANG gumaganang live source (Inflation Rate, galing PSA hindi BSP):**

Habang ginagawa namin ang Inflation Rate card, natuklasan namin na **iba pala ang source nito** — hindi ito galing kay BSP kahit nasa "BSP Key Rates" widget ito nakikita — galing 'to sa PSA (Philippine Statistics Authority). Mabuti na lang, may totoong "API" pala ang PSA (tinatawag na "PxWeb" — karaniwang ginagamit ng mga national statistics office sa buong mundo), at **hindi ito na-block** mula sa dev sandbox namin, hindi tulad ng lahat ng BSP sources natin!

Ginawa na ang `App\Services\DataSources\Fetchers\PsaInflationFetcher` — tumatawag muna ito sa PSA para malaman kung anong mga "codes" ang ginagamit nila para sa Pilipinas at "All Items," tapos humihingi ng datos gamit ang mga codes na 'yon. Sinubukan namin talaga itong patakbuhin nang buo (`market:pull-eod --source=psa-inflation`) at **NAGTAGUMPAY** — nakuha namin ang totoong Agosto 2026 inflation rate: **6.1%**, at automatic na na-save sa database. Ito na ang unang source sa buong project na TUNAY na gumagana nang automatic, hindi lang sa test.

May natuklasan din kaming maliit pero importanteng "bug" sa panig ng PSA: yung sagot nila pag humihingi ka ng datos ay may nakatagong "invisible character" sa simula (tinatawag na "BOM") na nagpapasira sa pagbasa ng JSON kung hindi mo alam. Na-ayos na namin 'to, at may test na rin para hindi na 'to maulit.

**Update — malaking balita: nahanap na namin ang tamang source para sa BAP FX Summary AT PDS BVAL, dalawang card na matagal na naming hinahanap:**

Base sa mga link/screenshot na binigay mo (BAP Spot USD/PHP at PHP BVAL Reference Rates), sinuri namin ang parehong website nang direkta.

**1. BAP FX Summary — `bap.org.ph`**

Maganda ang balita: **hindi ito naka-block**, hindi tulad ng BSP. May nahanap kaming page — `fxhistorical.html?base=USD&quote=PHP&year=YYYY&month=MM` — na nagbibigay ng malinis, maayos na HTML table (parang "file" din ang gawi kasi fixed ang URL pattern, hindi tayo basta "nangungupit" ng random na webpage). Kumpleto ang datos hanggang Sep 16, 2026 (fresh, kagabi lang). Ito ang gagamitin para sa "BAP Spot USD/PHP" na table sa dashboard, at pwede rin gamitin para sa Module 3 backfill (baguhin lang ang `year`/`month` sa URL).

**2. PDS PHP BVAL — `pds.com.ph` — DALAWANG magkaibang system pala ito!**

Ito yung pinaka-importanteng natuklasan namin. Akala namin isa lang ang "PDS website," pero pala **dalawa ang magkaibang system**, magkaiba ang accessibility:

- **`marketpage.pdex.net.ph`** (ang "MarketPage" — parang login portal ng mga bangko/dealers) — **hindi ito puwedeng ma-access nang basta-basta**. Sinubukan namin i-curl ito at nakita namin na kailangan ng "client certificate" (parang special na ID card na naka-install sa computer mo, ibinibigay lang sa mga subscriber) bago ka pa makapasok — parang hinihingan ka ng ID sa pintuan bago ka pumasok sa building. **Kailangan talaga ng bayad na subscription** para dito, at hindi ito ma-a-access kahit anong scraping technique gamitin namin.

- **`pds.com.ph/wd-mp/...`** (ang "widget" na nakikita mo sa ibang public websites, gaya nung mga screenshot mo) — **magkaibang system 'to, at PUBLIC pala 'to!** Sinuri namin ang code ng widget na 'to (parang tinignan namin "sa likod ng makina" kung paano kumukuha ng datos ang widget), at natuklasan namin na may tinatawag na "API" ito (`https://www.pds.com.ph/api-mp/graphql/`) na direkta naming pwedeng gamitin — hindi na kailangang "basahin" ang webpage, tatawagan na lang namin mismo ang parehong "pinagkukunan" ng datos ng widget mismo.
  - Sinubukan namin ito nang direkta at **gumana nang perpekto** — nakuha namin ang lahat ng 11 tenors (1M hanggang 25Y) nang malinis.
  - Sinubukan din namin humingi ng **lumang datos** (historical) — hiningi namin yung Aug 25, 2026, at eksaktong tumugma ang mga numero sa unang screenshot mong ipinadala sa amin noong simula pa lang ng project (1M=4.7722, 3M=5.0314, 6M=5.3856, 1Y=5.6685, 2Y=6.3566). Malaking kumpirmasyon 'to na tama ang source na ito — at bonus, solusyon na rin ito sa Module 3 backfill ng BVAL, libre pa.

**Mahalagang paalala tungkol dito:** yung "key" (parang password) na ginamit namin para tumawag sa API na 'to ay nahanap namin sa isang search engine result, hindi 'to opisyal na binigay sa Ramcar ng PDS. Mukhang sinadya naman ng PDS na maging public ito (may parameter silang `request_page: "public"` literal sa loob ng query, ibig sabihin idinisenyo talaga nila 'tong ma-access ng publiko para sa mga widget nila sa ibang website), pero dahil hindi opisyal na inisyu sa amin, may maliit na risk na baguhin nila ito balang araw nang walang paalala. Hindi 'to dapat maging problema (public naman talaga ang datos), pero worth banggitin.

**Sagot sa tanong "magkano ang presyo ng PDS data subscription?":** Wala kaming nahanap na public na listahan ng presyo — hindi 'to nakapublish kahit saan sa website nila (dalawang beses na kaming naghanap online). Kailangan talaga direktang tanungin ang **PDS Client Services** para sa quotation:
- Email: clientservices@pds.com.ph
- Phone: 884-5063 / 884-5017 / 884-4408 / 884-5022

Pero dahil nahanap na namin ang libreng public na paraan (yung widget API sa itaas), hindi na kailangan bayaran ang subscription na 'to para sa BVAL — reserve na lang natin 'tong option kung sakaling ma-block din pala ang widget API sa totoong server namin balang araw.

**Ang natitirang tanong:** hindi pa namin nasusubukan kung ma-a-access din ang `bap.org.ph` at ang PDS widget API mula sa totoong production server (hindi lang sa dev sandbox namin ngayon). Base sa naranasan namin, walang dahilan para asahan na parehong "Akamai block" gaya ng BSP — pareho itong nag-return ng malinis na 200 OK dito sa parehong dev sandbox na binlock ng BSP — pero kailangan pa rin i-verify pagdating ng totoong deployment.

**Update — ginawa na ang fetchers, ngayon ay LIVE (hindi na placeholder) ang "BAP Spot USD/PHP" at "PHP BVAL Reference Rates" tables sa dashboard:**

Dati, redesign lang ang ginawa namin sa dalawang table na ito (mula sa mga chart/tile papuntang proper table, kagaya ng hitsura ng Excel mo) — pero placeholder numbers pa rin ang laman. Ngayon, totoong data source na ang kinukuha nila:

- **`App\Services\DataSources\Fetchers\BapFxSummaryFetcher`** — kinukuha ang huling row ng `bap.org.ph`'s `fxhistorical.html` table (parehong buwan/taon, current month). Isa itong `MarketIndicator` (kaya isa lang na value) para sa bawat column ng table — Open, High, Low, Close, AM/PM Weighted Average + Volume, Weighted Average, Total Volume, at FX Settlement Rate (12 indicators lahat, kasama ang "Open Time" na text lang, hindi numero). Ganito rin ang approach na ginamit namin dati para sa BSP Key Rates' 11 cards.
- **`App\Services\DataSources\Fetchers\PdsBvalFetcher`** — tumatawag sa PDS GraphQL widget API na natuklasan namin, kinukuha ang lahat ng 12 tenors (1M-25Y) bilang `curve_points` (unang gamit ng `curve_points` table mula pa nung ginawa ito sa Module 1!). Isang `MarketIndicator` (`php_bval_curve`) na may 12 curve points bawat araw.
- Parehong na-test gamit ang **totoong response** mula sa aktwal na source (`tests/Fixtures/bap-fxhistorical-sample.html`, `tests/Fixtures/pds-bval-response.json`) — hindi gawa-gawa lang na sample data.

**Paano nakukuha ang "Previous" column nang hindi paulit-ulit magtatanong sa database (N+1):** Dahil sabay-sabay isinusave ang lahat ng 12 BAP indicators bawat run (parehong petsa lagi), isang query lang para sa "ano ang huling petsa bago 'to" tapos isang query lang para makuha lahat ng 12 dati-value nila — 3 query lang, hindi 12+. Ganito rin ang ginawa para sa BVAL curve: 2 query lang para makuha ang huli at pangalawang-huling petsa ng buong 12-tenor curve, hindi isa-isang tanong per tenor. Parehas 'to sa disiplina na natutunan namin mula sa N+1 bug na nasabi na sa itaas.

**Paalala:** hindi kailanman minemorya o hinuhulaan ng system ang "previous" — kinukuha talaga ito mula sa dati nang naka-save na value sa database (yung huling successful run bago ito). Kaya sa **unang** run pa lang ng bagong fetcher, walang "previous" pa (magpapakita ng "—"), maliban kung sineed muna ng 2 araw na datos — ginawa na namin 'to sa `MarketDataSeeder` gamit ang **totoong numero** na nakuha namin habang tino-test (Sep 15-16, 2026 para sa BAP; Sep 15-16, 2026 para sa BVAL), kaya may makikita ka agad sa dashboard.

**Update — bagong "Inflation Update" table sa dashboard, may 8 taon na ng totoong history (2019-2026):**

Nagpakita ka ng screenshot ng sariling "Inflation Update" table ni PSA mismo (`psa.gov.ph/content/summary-inflation-report-...`) — buwan bilang rows, taon bilang columns, may "Average" row sa ilalim. Gusto mong ganito rin ang view natin sa dashboard.

May dalawang natuklasan bago namin ginawa 'to:

1. **Yung PSA page mismo sa screenshot mo ay naka-block** — kapag sinubukan naming kunin, nag-return ng "Just a moment..." challenge page (Cloudflare, parehong klase ng problema sa BSP's Akamai, iba lang vendor). Hindi 'to ang `openstat.psa.gov.ph` (yung PxWeb API domain) na ginagamit na namin sa `PsaInflationFetcher` — magkaiba palang domain, at yung API domain lang ang bukas.
2. **Mabuti na lang, hindi na namin kailangan yung blocked page** — pinag-tsek namin yung sarili naming API, at lumalabas na **2019 hanggang 2026 na** ang saklaw nito (mas malawak pa sa 2021-2026 na nakita mo sa screenshot mo, dahil mas bago yung "ngayon" namin kesa sa petsa ng report mo). Isang POST request lang, buong 8 taon x 12 buwan, lahat nakukuha.

**Ginawa namin:**

- Dinagdagan ang `PsaInflationFetcher` ng bagong method, `fetchHistory()` — kinukuha ang LAHAT ng taon (hindi lang yung huling 2, na ginagamit ng regular na `fetch()` para sa araw-araw na pipeline run).
- Bagong command: `php artisan market:backfill-inflation` — isang beses lang itong pinapatakbo (hindi part ng araw-araw na schedule), kinukuha ang buong history, at "upsert" (safe i-re-run, hindi dumodoble ang datos).
- Bagong table sa dashboard, "Inflation Update," sa ibaba mismo ng "BSP Policy & System Benchmarks" strip — buwan bilang rows, taon bilang columns, "Average" row sa ilalim (simpleng average ng mga buwan na available sa taon na 'yon — na-verify namin na eksaktong tumutugma ang aming resulta sa mismong "Average" row ni PSA, halimbawa 2021 = 3.9%, 2023 = 6.0%).
- Kasama rin dito ang bahagyang pag-refactor ng `MarketDataPipeline` — nagdagdag kami ng `runWithResult()` na paraan para magamit ng backfill command ang parehong "ScraperRun record + upsert" logic ng araw-araw na pipeline, kaso may sarili na siyang inihanda nang FetchResult (buong history) sa halip na tumawag pa sa isang fetcher's `fetch()`.

**Bakit "Module 3 na naunahan"?** Kasi ito na ang unang totoong historical backfill sa buong project — dati ay estimate/plan pa lang ang Module 3, ngayon totoo na ito para sa Inflation Rate. Pareho ang dahilan kung bakit naunahan din namin ang manual-entry admin (Module 4) noon — kapag hinihingi na ng user ang view, mas mabuti gawin agad kesa hintayin ang "tamang pagkakasunod-sunod."

**Update — "GDP Growth" card: hindi 'to nawala, hindi lang pa na-tapos, ngayon LIVE na:**

Nagbigay ka ng screenshot ng PSA's "Figure 1. Gross Domestic Product (At Constant 2018 Prices), Year-on-Year Growth Rates" — buong quarterly history mula Q1 2018-2019 hanggang Q4 2024-2025, may malaking pagbagsak (-16.9%) noong COVID. Tinanong mo kung "nawala" ba yung GDP card namin.

**Sagot: hindi 'to natanggal — nandiyan lang siya sa loob ng "Macroeconomic Overview" section** (ang collapsible section na may dalawang maliit na card: "Inflation YoY %" at "GDP Growth, YoY %"), naka-scroll pa lang pababa. Static/placeholder lang talaga ang laman niya dati (4 quarters, 2 totoo + 2 "forecast" na hulaan lang).

**Natuklasan namin habang hinahanap ang tamang PSA table:** iba't ibang PxWeb "folder" pala ang GDP kumpara sa Inflation — hindi ito nasa "Prices" (`2M`) kundi sa "Economic Accounts → National Accounts → Quarterly" (`2B/NA/QT/1SUM/0022B5BEXQ2.px`, "Gross National Income and Gross Domestic Product by Expenditure Shares, Growth Rates"). Parehong openstat.psa.gov.ph API lang, ibang table lang. Na-verify namin ang datos laban sa totoong kilalang GDP figures (halimbawa, ang malakas na "+12.0%" rebound pagkatapos ng lockdown, na tumutugma sa Q2 2021).

**Ginawa namin:**

- Bagong fetcher: `App\Services\DataSources\Fetchers\PsaGdpGrowthFetcher` — parehong `fetch()`/`fetchHistory()` na pattern gamit ang `PsaInflationFetcher`.
- Bagong command: `php artisan market:backfill-gdp` — kinukuha lahat ng quarters mula 2001 hanggang ngayon (102 totoong quarters, safe i-re-run).
- Ginawang dynamic ang "GDP Growth, YoY %" card — **tinanggal namin ang 2 "forecast" bar** (wala kaming totoong forecast source), sa halip 4 totoong huling quarters na lang, lahat totoong PSA print.

**Update — ginawa na rin ang buong "GDP Growth History" table:**

Sinundan namin ang parehong pattern ng "Inflation Update" — bagong table sa dashboard, sa ibaba mismo ng "Macroeconomic Overview" collapsible section: **Quarter bilang rows (Q1-Q4), taon bilang columns (2001-2026), "Average" row sa ilalim** (average ng 4 quarters na available sa taon na 'yon). Ito ang buong 102-quarter history na na-backfill namin — na-verify namin na eksaktong tumutugma sa screenshot mong PSA figure (halimbawa, 2020-2021 column: -3.8, 12.0, 7.0, 7.9 — eksaktong pareho). `Dashboard::buildGdpGrowthHistory()` ang gumagawa nito, isang query lang para sa buong table.

**Update — ginawa na rin ang linya-chart mismo ("Figure 1" mula sa screenshot mo):**

Nung sinabi mong "let's do this graph first," binuo namin ang totoong trend line, hindi lang table — nasa itaas mismo ng "GDP Growth History" table ngayon, gamit ang huling 28 totoong quarters (7 taon), kapareho ng window na nakita mo sa PSA's Figure 1 (kasama pa rin ang COVID dip, dahil kasya ito sa 7-taong window mula "ngayon").

**Mahalagang desisyon dito:** hindi namin kinopya nang eksakto ang PSA's style na naglalagay ng number sa BAWAT tuldok (28 numbers, siksikan). Sa halip, sumunod kami sa established na "chart design" na disiplina namin (may sariling checklist kami para dito) — dalawang bagay lang ang direktang minarkahan ng number: **ang pinaka-mataas (extreme high), ang pinaka-mababa (extreme low), at ang PINAKABAGO na quarter.** Lahat ng ibang tuldok, makikita mo pa rin ang eksaktong value kapag hinover mo (parang "tooltip") — kaya hindi nawawala ang detalye, mas malinis lang ang tingin.

Ibang detalye:
- **Pulang tuldok** = negative na quarter (halimbawa yung -16.9% at -11.6% noong COVID), **navy blue** = positive.
- May "0%" na guide line para makita agad kung saan tumatawid pababa/pataas ang GDP sa zero.
- Ang x-axis (mga petsa sa ilalim) ay hindi lahat ng 28 quarters ang naka-label — isa lang bawat taon (kada 4 quarters), para hindi masyadong siksikan, pero laging kasama ang pinakabagong quarter.
- `Dashboard::buildGdpGrowthTrend()` ang pumipili kung aling mga quarter ang lalabas (huling 28) at kung sino ang "kailangang markahan" (extremes + latest); ang mismong pag-guhit ng SVG line/coordinates ay ginagawa sa Blade view mismo, dahil "presentation math" lang ito (depende sa laki ng chart), hindi kailangang i-test sa PHP level nang hiwalay.

**Update — "Philippine Stock Market Overview" at yung PSEi hero card: LIVE na (galing sa PSE Edge):**

Tinanong mo kung dynamic na ba ang Stock Market Overview — hindi pa noon, at ang alam namin ay hinihintay pa ang sagot ng PSE tungkol sa bayad na "Data Products." Habang naghihintay, sinuri namin ang **PSE Edge** (`edge.pse.com.ph`, ang sarili nilang disclosure website) — **hindi naka-block**, at may "Index Summary" page (`/index/form.do`) na malinis na HTML table: PSEi, All Shares, at 6 na sector (Financials, Industrial, Holding Firms, Services, Mining & Oil, Property), kasama ang value, change, market status ("RECESS"/"CLOSED"), oras ("As of..."), total value traded (turnover), at advances/declines/unchanged.

- Bagong fetcher: `App\Services\DataSources\Fetchers\PseIndexSummaryFetcher`. Isa itong "File"-type na source na katulad ng BAP (fixed URL, well-formed na table). 23 na indicators ang napupuno (level + change ng bawat isa sa 8 index, kasama ang total volume, total trades, turnover, advances/declines/unchanged, at market status). May sarili na ring **"Market Summary" table** sa ilalim ng Stock Market Overview (Total Volume, Total Trades, Total Value in PHP, Advances, Declines, Unchanged) - hiniling mo, base sa screenshot ng PSE. Yung footnote ng PSE na "delayed by 15 minutes, for market education only" ay nakalagay din doon.
- **Maliit na bitag:** ang "Chg" column ng PSE ay **walang plus/minus** - kulay lang at ▲/▼ sa dulo ng %Chg ang nagsasabi kung taas o baba. Binabasa ng fetcher ang arrow para ilagay ang tamang sign (may test para dito, kasama ang "unchanged" na kaso).
- **Bakit sarili naming "Chg" indicator per index:** dahil ang PSE ang nagbibigay ng authoritative na pagbabago mula pa sa unang araw - kung "vs kahapon" ang gagamitin namin mula sa sarili naming history, walang lalabas hanggang may pangalawang araw na naka-save.
- Sinubukan namin talaga (`market:pull-eod --source=pse-index-summary`): **NAGTAGUMPAY** - 23 values, totoong live PSEi na na-save sa database.

**Mga bagay na HINDI pa dynamic (may maliit na "NOT DYNAMIC" na tag na sa dashboard, ayon sa hiling mo):**
- **Foreign Flow** (net foreign buying/selling) - wala sa PSE Edge page, at naka-403 block ang main na `pse.com.ph` market pages. Kailangan ng ibang source (o manual entry, o yung bayad na PSE product).
- Ang trend line ng PSEi sa hero card ay **tinanggal na** (pati ang sa USD/PHP card) — walang stored history ng PSEi para gumuhit nito.

**⚠️ Mahalagang babala tungkol sa lisensya:** ang footer ng PSE Edge ay nagsasabing ang materials ay para sa **"personal, non-commercial use"** lang. Walang tahasang pagbabawal sa automated access sa disclaimer nila at walang `robots.txt`, pero ang araw-araw na paghila ng isang kumpanya para sa internal treasury dashboard ay pwedeng ituring na commercial. Kaya **pansamantalang source lang 'to** - dapat pa ring makakuha ng sagot sa PSE tungkol sa Data Products licence. Desisyon 'to ng Ramcar, hindi ng dev.

**Update — "Commodities": Gold, Wheat, Brent, at LME Lead — LIVE na (iba-iba ang pinanggalingan ng bawat isa):**

Pinadala mo ang mga screenshot (LME Lead Trading Summary + price curve, at Markets Insider para sa Wheat/Brent/Gold) at sinabi mong ito na ang susunod na module. Sinuri muna namin kung ano talaga ang pwedeng kunin — **walang iisang source para sa lahat**, kaya tatlong magkakaibang paraan:

| Card / bahagi | Source | Paano | Status |
|---|---|---|---|
| **Gold, Wheat, Brent** | Markets Insider (`markets.businessinsider.com/commodities/...`) — yung mismong mga link sa Excel niyo | **Scraping** ng page (walang API; yung chart endpoint nila ay may anti-forgery tokens, hindi namin ginalaw) | ✅ Live, nasubok (`market:pull-eod`) |
| **LME Lead — Cash / 3-month / Stock** | Westmetall (`westmetall.com`) — libreng public table, araw-araw, may 181 araw ng history ngayong taon | Fixed-URL HTML table (`WestmetallLeadFetcher`) | ✅ Live + may history (`market:backfill-lme-lead`) |
| **LME Lead — "Official Prices" bid/offer (Cash, 3-month, Dec-27, Dec-28, Dec-29)** | Wala | **Manual entry** lang — ang mismong `lme.com` ay naka-block (403) at lisensyado ang data | ⚠️ "NOT DYNAMIC" |

**Mga importanteng bagay na dapat mong malaman:**

1. **Hindi ito ang "official" na presyo.** Ang Markets Insider ay nagpapakita ng **"MI Indication"** — indicative na presyo nila, hindi ang opisyal na fixing (halimbawa, hindi ito ang LBMA gold fix). Nakalagay ito sa "note" ng bawat value.
2. **Yung LME Lead price curve sa dashboard ay hindi bid/offer.** Cash-Settlement at 3-month **settlement** prices ang meron sa Westmetall — hindi ang bid/offer na nasa screenshot mo. Kaya nilagyan namin ng malinaw na label ("Cash-Settlement vs 3-month") at footnote na "Not the LME bid/offer curve." Yung tunay na bid/offer table (Cash, 3-month, Dec-27...) ay nasa hiwalay na table at **manual**.
3. **Manual entry na ng bid/offer:** may bago nang **"Curve points" tab** sa `/rpanel/market-indicators` → "LME Lead Official Prices (bid/offer)" — dito nagta-type ng Contract, Bid, Offer, at "Data valid for" date. Naka-seed ito mula sa screenshot mo (02 Apr 2026), kaya **luma pa ang itsura hanggang may mag-update**. Yung ganitong uri ng datos (`bid`/`offer` sa `curve_points`) ay planado na simula pa sa Module 1 — ngayon lang nagamit.
4. **Lisensya (LME):** ang LME data ay lisensyado. Ang Westmetall ay nagpapakita nito nang libre, pero **kung katanggap-tanggap ba sa Ramcar** na gamitin ito para sa internal dashboard ay tanong para sa negosyo, hindi sa dev — parehong klase ng tanong sa PSE. Pareho rin ang paalala sa Markets Insider: scraping ito at may sarili silang terms.
5. **Wheat: walang nakasulat na unit** sa Markets Insider page (ang title nila ay "per Ounce" para sa lahat ng commodity, template lang). Kaya `$` lang ang ipinapakita namin, walang "/bu" o "/t" — hindi kami maghuhula.
6. **Brent — magkaiba ang EIA at Markets Insider:** noong Sep 15 ang EIA spot (`RBRTE`) ay ~$130.80, samantalang ~$101–102 ang Markets Insider makalipas ang ilang araw. Magkaibang instrumento (dated spot vs futures), at hindi lang iisang araw ang paghahambing. Sumunod kami sa Markets Insider dahil iyon ang nasa Excel niyo.
7. **Sparklines:** totoong linya na lang ang lalabas kapag may **hindi bababa sa 5** na naka-save na readings. Ang LME Lead ay may 60 araw na. Ang Gold/Wheat/Brent ay may 1 pa lang, kaya may kulay-abong placeholder na may tag na **"NOT DYNAMIC"** — kusa itong magiging totoo habang tumatakbo ang araw-araw na run. Tinanggal namin yung mga 1W/1M/6M/1Y/All na button dahil wala silang ginagawa at nagmumukhang may range na wala naman.
8. **Kulay ng LME chart:** dalawang linya (Cash = asul, 3-month = orange). Sinuri namin ang pares gamit ang validator para sa color-blind na mambabasa at pumasa; yung brand navy ay hindi pumapasa bilang "series color" kaya hindi ginamit.

**"LIVE FX Rates Reference: OANDA"** — nabanggit mo ang OANDA live rates page bilang reference. Hindi namin ito ginawang source (walang hiniling na bagong card, at may sarili silang terms); tinuturing lang namin itong reference para i-cross-check ang USD/PHP. Kung gusto mong gawing hiwalay na FX source, sabihin lang.

**Mga bagong file:** `MarketsInsiderPriceFetcher`, `WestmetallLeadFetcher`, `BackfillLmeLead` (command), `CurvePointsRelationManager`, at mga test (`MarketsInsiderPriceFetcherTest`, `WestmetallLeadFetcherTest`, `BackfillLmeLeadTest`, `MarketIndicatorCurvePointsRelationManagerTest`) — lahat ay nasubok laban sa **totoong nakuhang pages** (`tests/Fixtures/mi-*-price.html`, `westmetall-lme-lead.html`).

**Update — Guide at Technical na button sa dashboard:**

May dalawang button na ngayon sa kanang-ibaba ng dashboard:

- **Guide** — isang guided tour sa bawat section, mula sa itaas pababa. Sinasabi nito kung ano ang ipinapakita ng bawat card o table at bakit ito nandoon.
- **Technical** — parehong tour, pero para sa mga nag-iimplementa o nagbe-verify: kung saan galing ang bawat number (URL, uri ng source tulad ng API, file, scrape o manual), kailan ito huling tumakbo, at kung static pa ang card.

Ang Technical na bahagi ay kumukuha ng URL, method at huling run mula mismo sa `data_sources` table, kaya laging tama kahit magbago ang source. Ang paliwanag na text ay nasa `Dashboard::tourDefinitions()` — kapag may bagong card, dagdagan ito ng entry at ng `data-tour` attribute sa Blade.

**Update — mga pagbabago sa dashboard (Guide, Technical, at paglilinis):**

- **Guide at Technical buttons** sa kanang-ibaba ng screen. Guide = paliwanag ng bawat section; Technical = saan galing ang data (URL, uri, huling run). Ang text ay nasa `Dashboard::tourDefinitions()`. Ang overlay ay may 70% puting wash, at naka-highlight ang section na pinag-uusapan.
- **Tinanggal:** ang top bar (Institutional Command View, PHT Fix Window, at ang tatlong button na hindi gumagana), ang footer strip (walang source ang claim tungkol sa BSP Circular, at hindi gumagana ang mga button), ang "Toggle View" label, at ang salitang "Forecasts" sa Macro header. Pati ang trend lines ng USD/PHP at PSEi cards.
- **Headline Inflation card** ay LIVE na. Galing sa stored PSA values: pinakabagong reading, buwan, mini bar chart ng huling 6 na buwan, at badge na "Above / Within / Below target" (BSP target 2.0%–4.0%). Tinanggal ang dating "Next: Sep 5" dahil walang source para dito.
- **Naiwan pa:** ang "Inflation YoY % & Target Band" chart sa Macro section ay static pa rin (may "6.2%" na sample).

**Update — Guide: malinaw na kung saan mag-e-enter ng manual values:**

Sa Guide, may nakasulat na na kung aling bahagi ang **Not dynamic** at kailangang i-enter nang manual:
- **BSP Policy strip** (7 sa 11 cards): i-enter sa `http://treasury-market-updates.test/rpanel/market-indicators` (Enter Value).
- **LME Lead bid/offer** table: i-enter sa `/rpanel/market-indicators` → LME Lead Official Prices → **Curve points**.
- **Foreign Flow** ay walang source at walang manual entry pa, kaya static pa rin.

`/rpanel/data-sources` ay para sa pag-review ng source (URL, method, huling run), hindi para sa mismong values.

**Update — navigation:** ang **Data Sources** at **Market Indicators** ay nasa iisang **Master Data** group na sa sidebar, at may kanya-kanyang icon na (server stack at chart bar).

### Module 3 — Pagsasalin ng Nakaraang Datos (Historical Backfill)

**Sa simpleng salita:** kapag bago pa lang tayong nag-a-start mag-record, blangko pa yung mga chart natin (walang "nakaraang linya" para makita ang trend). Sa module na 'to, kukunin natin ang datos ng nakaraang ~1 taon (kung available) at ilalagay sa database, para may makita agad na chart pagka-launch, hindi puro "isang tuldok lang".

**Halimbawa:** yung "1-year" na chart ng gold price — kailangan may 1 taon na datos bago ito mag-mukhang totoong chart, hindi lang isang punto.

### Module 4 — Admin at Monitoring Tools

**Sa simpleng salita:** parang "control room" sa likod ng dashboard — dito makikita/magagawa ng treasury team (hindi lang mga developers):
- Tignan kung anong mga sources ang naka-on/off.
- I-click ang "run ulit ngayon" kung sa tingin nila hindi na-update ng maayos.
- I-type manually ang isang value kung minsan (halimbawa, habang wala pang koneksyon sa LME).
- I-set kung anong "malaking galaw" ng presyo ang dapat mag-trigger ng alerto.

**Files/features:** mga bagong Filament Resources (parang mga listahan/forms sa loob ng `/rpanel` panel) para sa `data_sources`, `scraper_runs` (yung "log book"), at manual override forms.

**✅ Ginawa na, pinauna namin bago pa man matapos ang Module 2 — "Enter Value" na admin page:**

Habang kinukumpleto namin ang Module 2, sinabi mo na kung sakaling hindi talaga posible ang API/scraping para sa isang source, gagawa tayo ng admin page kung saan pwedeng i-type na lang ng treasury team ang value — at sumang-ayon kami dito, kaya ginawa na namin agad, hindi na hinintay ang "Module 4" bilang pormalidad.

**Bakit ito priority, hindi lang "backup plan":** ilan sa mga indicators natin ay **hindi na talaga magkakaroon ng automated source, kahit kailan** — ang ON Reference Rate (tumigil na gamitin ni BSP), ang 28-day TDF (hindi na ginagamit mula 2020), ang Target RRP Rate (footnote lang laging nasusulat, hindi clean na number), at kahit ano pang hihintayin natin sa bandang huli (LME license, PSE data-license negotiation, PDS BVAL feed). Kung ang pagkakumpleto ng buong dashboard ay naka-depende sa lahat ng 'yon matapos, matagal bago "tapos" ang project. Sa halip, ginawa muna namin ito para **magamit na agad ng buong Treasury team ang dashboard ngayon din** — automated man o hindi ang bawat source, may paraan pa ring panatilihing updated ito.

**Ano ang nasa loob:**
- `/rpanel/market-indicators` — listahan ng LAHAT ng indicators (11 BSP Key Rates cards, USD/PHP, Inflation Rate, at lahat pang idadagdag natin mamaya), kasama ang latest value, petsa, at kung saang data source galing.
- Button na **"Enter Value"** sa bawat row — nagbubukas ng maliit na form (value, petsa, at opsyonal na label/note), tapos automatic na ini-“upsert” gamit ang parehong (indicator + petsa) key na ginagamit din ng lahat ng automated fetchers natin — kaya kung mag-type ka ng value sa parehong petsa, ina-update lang, hindi dumadami ang record.
- Isang "Value history" tab kada indicator — makikita mo LAHAT ng nakaraang values (galing man sa fetcher o sa taong nag-type), at pwede mo pang i-edit/tanggal kung may mali.
- **May audit trail na** — gamit ang `spatie/laravel-activitylog` (yung "groundwork" na na-install na dati) — automatic na na-rerecord kung sino at kailan nag-type/nag-edit ng isang value. Kapag automated fetcher ang gumawa, walang "sino" (dahil walang naka-login na tao); kapag tao ang nag-type, naka-record ang eksaktong user.

**Isang natuklasan habang ginagawa ito — bug sa pag-compare ng petsa, na-ayos na:**

Nung sinusubok namin i-update ang parehong petsa nang dalawang beses (para tiyakin na "update lang, hindi dumadagdag"), nag-crash ang system — pero LOCAL/dev database lang namin (SQLite) ang naka-expose nito; hindi nangyari sa totoong database (MySQL) na ginagamit natin. Dahilan: pareho ang code, pero magkaiba ang "estilo" ng dalawang database sa pag-imbak ng petsa — yung MySQL, awtomatiko niyang "tinatanggal" ang oras kung DATE lang talaga ang column (kaya gumagana kahit medyo "maluwag" ang code), pero yung SQLite, literal niyang isinasave kung ano man ang ipinasa dito. Nag-e-emerge ang bug sa unang beses na kailangan i-compare ang petsa nang eksakto.

**Bakit importante 'to malaman:** may isa pang matandang test na "pumapasa" na pala dati, pero mali ang dahilan — na-catch pala ng ating sariling "malambot na pagkakahulog" (yung ginawa nating error-handling sa Module 1) ang crash, kaya "successful" ang itsura kahit hindi naman totoong gumana yung pag-update. Ayos na ito ngayon — na-fix ang paraan ng pag-imbak ng petsa (`date:Y-m-d` sa halip na `date` lang), at pinalakas din namin yung lumang test para masigurong hindi na ito mauulit nang hindi namin mapapansin.

**⚠️ Totoong pag-crash sa browser, na-ayos na — "Enter Value" page:**

Nung binuksan ang `/rpanel/market-indicators/{id}/edit` sa TOTOONG browser, nag-crash ito — "memory exhausted" (parang natigil/nag-loop ang page at umubos ng RAM). Ito ang pinaka-seryosong bug na natagpuan natin sa buong project so far, kaya i-detalye natin.

**Ano talaga ang nangyari:** may isang column sa "Value history" na nagpapakita kung "Manual" o "Run #123" ang pinagmulan ng isang value. Sinulat namin ito gamit ang isang "function" (tinatawag na "closure") na may parameter na pinangalanang `$state`. Lumalabas, sa Filament, kapag `$state` mismo ang pangalan ng parameter sa ganitong klaseng function, awtomatiko nitong ineexpect na "ibigay mo sa akin ang KASALUKUYANG resulta" — pero ito mismo yung function na DAPAT kumakalkula ng resulta! Kaya nag-loop ito nang WALANG KATAPUSAN: kumukuha ng resulta → tatawag ulit sa sarili niya para makuha ang resulta → tatawag ulit... at iba pa, hanggang maubos ang memory.

**Bakit hindi ito nahuli agad ng mga test natin:** ang "Value history" na 'to ay isang uri ng Filament component na "lazy-loaded" — ibig sabihin, hindi ito kasabay na lumo-load kapag binuksan mo ang page; may HIWALAY na "follow-up request" pa na naglo-load nito pagkatapos, katulad ng "second trip" pagkatapos ng unang bukas ng page. Ang mga simpleng test natin dati (basta "buksan mo yung page, tingnan kung 200 OK") ay hindi naman talaga "sumasakay" sa ganitong second trip — kaya "pumapasa" ang test kahit may crash pala kapag totoong ginamit sa browser.

**Ayos na:**
- Pinalitan namin yung parameter pangalan mula `$state` tungo sa `$record` (yung buong "row"/record mismo), at doon na lang kinukuha ang kailangang value — ligtas na ito, walang infinite loop.
- Nagdagdag kami ng bagong tool sa pag-test (`pestphp/pest-plugin-livewire`) na kayang "buksan" talaga ang ganitong "lazy" na parte ng page nang direkta, tapos gumawa kami ng bagong permanenteng test (`MarketIndicatorValuesRelationManagerTest`) na sumasakay talaga sa second trip na 'to — para hindi na ito mauulit nang hindi namin mapapansin.

### Module 5 — Excel Export at mga Alerto

**Sa simpleng salita:** dalawang bagay ang gagawin dito:
1. **"Isang click" na Excel export** — gagawin nating pareho ang porma ng lumalabas na Excel doon sa Excel na dati nang ipinapadala ng Treasury team, pero ngayon isang button na lang ang pinindot, hindi na kailangan i-type/i-compile manually.
2. **Automatic na email alerto** — halimbawa, kung bigla biglang tumaas/bumaba nang malaki ang isang presyo, o kung hindi na-update ang isang source, may makakatanggap agad ng email/notification — hindi na kailangan laging bantayan ang dashboard.

### Module 6 — Testing at Katatagan (Reliability)

**Sa simpleng salita:** parang quality control bago ilabas ang produkto. Gagawa tayo ng mga automated na "pagsusulit" (tests) para masiguradong hindi basta-basta masisira ang system kapag may binago tayong code, at para agad malaman kung may source na tumigil sa pag-gana.

### Module 7 — Dokumentasyon at User Testing (UAT)

**Sa simpleng salita:** yung "test drive" bago talaga gamitin araw-araw ng buong Treasury team. Susuriin nila kung tama ba ang lahat ng presyo, tama ba ang design, at tapos gagawa tayo ng simpleng gabay (paano gamitin) para sa mga bagong gagamit.

## Buod: Saan tayo ngayon?

| Module | Status |
|---|---|
| Module 0 — Static dashboard (design/layout) | ✅ Tapos na |
| Module 1 — Database + delivery system (foundation) | ✅ Tapos na |
| Module 2 — Bawat source (BSP, BAP, PDS, PSE, PSA, EIA, commodities vendor) | 🔧 Malayo na ang narating — LIVE na ang USD/PHP, 3 sa 11 BSP Key Rates, Inflation Rate, BAP FX Summary, PDS BVAL, GDP Growth, PSE (PSEi + Stock Market Overview + Market Summary), Gold/Wheat/Brent (Markets Insider), at LME Lead cash/3-month (Westmetall). Natitira pa: 7 sa 11 BSP Key Rates (manual pa rin), PSE Foreign Flow, at LME Lead bid/offer table (parehong sadyang manual — walang automated source). |
| Module 3 — Historical backfill | 🔧 Sinimulan na — Inflation Rate (2019-2026 buwanan), GDP Growth (2001-2026 quarterly), at LME Lead (buong taon) ay may `market:backfill-*` commands na. Ang iba pang sources ay latest value pa lang. |
| Module 4 — Admin & monitoring tools | 🔧 Naunahan na ang manual entry — LIVE na at ginagamit araw-araw ang "Enter Value" (`ValuesRelationManager`) at ang bagong "Curve points" tab (`CurvePointsRelationManager`, ginamit na para sa LME Lead bid/offer). Naka-install na ang Pulse at Activity Log pero default pa lang — wala pang custom na "monitoring dashboard" para sa kalusugan ng mga scraper/source runs. |
| Module 5 — Excel export & alerts | ⏳ Hintay pa |
| Module 6 — Testing & reliability | 🔧 Malakas na ang foundation — 109 automated tests, lahat gamit ang totoong-nakuhang fixtures (hindi gawa-gawa), pero hindi pa ito "pormal na module" (hal. retry logic, alerting kapag nagfail ang isang source, atbp.) |
| Module 7 — Docs & UAT | 🔧 Patuloy na ina-update ang `MODULES.md`, `DASHBOARD.md`, at `README.md` bawat malaking pagbabago, pero wala pang pormal na UAT (user acceptance testing) na naganap |

Para sa detalyadong mandays/timeline estimate, tignan yung `Treasury-Market-Updates-Timeline.xlsx` sa root ng project.
