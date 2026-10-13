{{--
    Treasury Market Updates - static dashboard overview.

    This is the "static" build of the approved Google Stitch mockup: layout,
    styling and interactions (drawer, accordion) are real, but every figure
    below is placeholder data. Each section will be wired to a live
    market-data source module by module.
--}}
<div
    x-data="{
        drawerOpen: false,
        drawerTitle: '',
        drawerValue: '',
        drawerDelta: '',
        drawerMeta: '',
        macroOpen: true,
        open(title, value, delta, meta) {
            this.drawerTitle = title;
            this.drawerValue = value;
            this.drawerDelta = delta;
            this.drawerMeta = meta;
            this.drawerOpen = true;
        },
    }"
    class="flex flex-col w-full max-w-7xl mx-auto space-y-space-xl py-space-md"
    style="font-family: 'Inter', ui-sans-serif, system-ui, sans-serif;"
>
    @php
        $usdPhpHasValue = ($usdPhp['value'] ?? null) !== null;
        $usdPhpChange = $usdPhp['percentChange'] ?? null;
        $usdPhpIsUp = $usdPhpChange !== null && $usdPhpChange >= 0;
        $usdPhpBadgeClasses = $usdPhpChange === null
            ? 'bg-surface-container-high text-on-surface-variant'
            : ($usdPhpIsUp ? 'bg-tertiary-fixed text-tertiary-container' : 'bg-error-container text-on-error-container');
        $usdPhpBadgeText = $usdPhpChange === null
            ? 'No data yet'
            : number_format(abs($usdPhpChange), 2).'% '.($usdPhpIsUp ? '&#9650;' : '&#9660;');
        $usdPhpDisplayValue = $usdPhpHasValue ? number_format($usdPhp['value'], 4) : '—';
        $usdPhpPreviousDisplay = ($usdPhp['previousValue'] ?? null) !== null ? number_format($usdPhp['previousValue'], 4) : null;
        $usdPhpAsOfDisplay = $usdPhp['asOfDate']?->format('M j, Y');
    @endphp
    @php
        // One PSE index: its level plus PSE's own signed change, with the
        // % change worked out against the implied previous close.
        $pseIndex = function (string $code) use ($pse): array {
            $value = $pse[$code]['value'] ?? null;
            $change = $pse[$code.'_chg']['value'] ?? null;
            $previous = ($value !== null && $change !== null) ? $value - $change : null;
            $percent = ($previous !== null && $previous != 0) ? $change / $previous * 100 : null;

            return [
                'value' => $value,
                'change' => $change,
                'percent' => $percent,
                'isUp' => $change !== null && $change >= 0,
                'valueText' => $value !== null ? number_format($value, 2) : '—',
                'percentText' => $percent !== null ? ($percent >= 0 ? '+' : '-').number_format(abs($percent), 2).'% '.($percent >= 0 ? '&#9650;' : '&#9660;') : 'No data yet',
                'changeText' => $change !== null ? ($change >= 0 ? '+' : '-').number_format(abs($change), 2) : '—',
            ];
        };

        $pseiCard = $pseIndex('psei');
        $pseStatusRaw = $pse['pse_market_status']['valueText'] ?? null;
        $pseStatusLabel = $pseStatusRaw ? 'Market '.strtolower($pseStatusRaw) : 'No data yet';
        $pseAsOfDate = $pse['pse_market_status']['asOfDate'] ?? null;
        $pseAsOfTime = $pse['pse_market_status']['asOfTime'] ?? null;
        $pseAsOfText = $pseAsOfDate
            ? $pseAsOfDate->format('M j').($pseAsOfTime ? ', '.\Illuminate\Support\Carbon::parse($pseAsOfTime)->format('g:i A') : '')
            : 'No data yet';
    @endphp
    {{-- SECTION 1: HERO METRIC ROW (3 CARDS) --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-gutter-desktop">
        {{-- Card 1: USD/PHP - wired to real data (Module 1 proof point) --}}
        <div data-tour="hero-usdphp" class="bg-surface-container-lowest rounded-2xl p-space-lg shadow-sm hover:shadow-md transition-all duration-200 flex flex-col justify-between group relative overflow-hidden">
            <div class="flex items-center justify-between gap-space-xs mb-space-sm">
                <span class="font-label-micro text-label-micro uppercase tracking-wider text-secondary">SPOT FX &middot; USD/PHP</span>
                <button
                    type="button"
                    title="Expand Details"
                    class="p-1 rounded-md hover:bg-surface-container-high text-on-surface-variant transition-colors"
                    x-on:click="open('USD / PHP Spot Fixing', '{{ $usdPhpDisplayValue }}', '{!! $usdPhpBadgeText !!}', 'Bangko Sentral ng Pilipinas Official Ref. Rate')"
                >
                    <span class="material-symbols-outlined text-[18px]">open_in_new</span>
                </button>
            </div>
            <div class="flex items-baseline justify-between mb-space-xs">
                <span class="font-metric-xl text-metric-xl tracking-tight text-primary font-bold">{{ $usdPhpDisplayValue }}</span>
                <span class="inline-flex items-center gap-0.5 px-2.5 py-0.5 rounded-full {{ $usdPhpBadgeClasses }} font-label-md text-label-md font-semibold">
                    {!! $usdPhpBadgeText !!}
                </span>
            </div>
            
            <div class="flex items-center justify-between text-on-surface-variant font-label-md text-label-md mt-space-xs pt-space-xs">
                <span>{{ $usdPhpPreviousDisplay ? "vs {$usdPhpPreviousDisplay} yesterday" : 'No prior value yet' }}</span>
                <span class="font-semibold text-primary">{{ $usdPhpAsOfDisplay ?? 'No data yet' }}</span>
            </div>
        </div>

        {{-- Card 2: PSEi Equities - wired to PSE Edge (PseIndexSummaryFetcher) --}}
        <div data-tour="hero-pse" class="bg-surface-container-lowest rounded-2xl p-space-lg shadow-sm hover:shadow-md transition-all duration-200 flex flex-col justify-between group relative overflow-hidden">
            <div class="flex items-center justify-between gap-space-xs mb-space-sm">
                <span class="font-label-micro text-label-micro uppercase tracking-wider text-secondary">EQUITIES &middot; PHILIPPINE STOCK EXCHANGE</span>
                <button
                    type="button"
                    title="Expand Details"
                    class="p-1 rounded-md hover:bg-surface-container-high text-on-surface-variant transition-colors"
                    x-on:click="open('PSEi Composite Index', '{{ $pseiCard['valueText'] }}', '{!! $pseiCard['percentText'] !!}', 'Philippine Stock Exchange Main Benchmark')"
                >
                    <span class="material-symbols-outlined text-[18px]">open_in_new</span>
                </button>
            </div>
            <div class="flex items-baseline justify-between mb-space-xs">
                <span class="font-metric-xl text-metric-xl tracking-tight text-primary font-bold">{{ $pseiCard['valueText'] }}</span>
                <span class="inline-flex items-center gap-0.5 px-2.5 py-0.5 rounded-full {{ $pseiCard['percent'] === null ? 'bg-surface-container-high text-on-surface-variant' : ($pseiCard['isUp'] ? 'bg-tertiary-fixed text-tertiary-container' : 'bg-error-container text-on-error-container') }} font-label-md text-label-md font-semibold">
                    {!! $pseiCard['percentText'] !!}
                </span>
            </div>
            
            <div class="flex items-center justify-between text-on-surface-variant font-label-md text-label-md mt-space-xs pt-space-xs">
                <span class="flex items-center gap-1"><span class="w-1.5 h-1.5 rounded-full bg-secondary"></span> {{ $pseStatusLabel }}</span>
                <span class="font-semibold text-primary">{{ $pseAsOfText }}</span>
            </div>
        </div>

        {{-- Card 3: Headline Inflation --}}
        @php
            $inflationNow = $keyRates['inflation_rate'] ?? [];
            $inflationValue = $inflationNow['value'] ?? null;
            $inflationValueText = $inflationValue !== null ? number_format($inflationValue, 1).'%' : '—';
            $inflationPeriodText = $inflationNow['periodLabel'] ?? ($inflationNow['asOfDate']?->format('M Y'));
            $inflationBadge = match (true) {
                $inflationValue === null => 'No data yet',
                $inflationValue > 4.0 => 'Above target',
                $inflationValue < 2.0 => 'Below target',
                default => 'Within target',
            };
        @endphp
        <div data-tour="hero-inflation" class="bg-surface-container-lowest rounded-2xl p-space-lg shadow-sm hover:shadow-md transition-all duration-200 flex flex-col justify-between group relative overflow-hidden">
            <div class="flex items-center justify-between gap-space-xs mb-space-sm">
                <span class="font-label-micro text-label-micro uppercase tracking-wider text-secondary">MACRO &middot; HEADLINE INFLATION (YOY)</span>
                <button
                    type="button"
                    title="Expand Details"
                    class="p-1 rounded-md hover:bg-surface-container-high text-on-surface-variant transition-colors"
                    x-on:click="open('Philippine Headline Inflation', '{{ $inflationValueText }}', '{{ $inflationBadge }}', 'Philippine Statistics Authority, PSA OpenSTAT')"
                >
                    <span class="material-symbols-outlined text-[18px]">open_in_new</span>
                </button>
            </div>
            <div class="flex items-baseline justify-between mb-space-xs">
                <span class="font-metric-xl text-metric-xl tracking-tight text-primary font-bold">{{ $inflationValueText }}</span>
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full bg-secondary-container text-on-secondary-container font-label-md text-label-md font-semibold">
                    {{ $inflationPeriodText ? $inflationPeriodText.' · PSA' : 'No data yet' }}
                </span>
            </div>
            <div class="w-full flex items-end gap-2 h-12 my-space-xs pt-1">
                @forelse ($inflationRecent as $index => $month)
                    @php $isLatest = $index === count($inflationRecent) - 1; @endphp
                    <div class="flex-1 flex flex-col items-center gap-1 h-full justify-end">
                        <div class="w-full {{ $isLatest ? 'bg-primary' : 'bg-surface-container-high' }} rounded-t-sm" style="height: {{ $month['heightPercent'] }}%;" title="{{ $month['label'] }}: {{ number_format($month['value'], 1) }}%"></div>
                        <span class="font-label-micro text-label-micro {{ $isLatest ? 'text-primary font-bold' : 'text-secondary' }} text-[9px]">{{ substr($month['label'], 0, 1) }}</span>
                    </div>
                @empty
                    <div class="w-full h-full flex items-center justify-center">
                        <span class="font-label-md text-label-md text-secondary">No data yet</span>
                    </div>
                @endforelse
            </div>
            <div class="flex items-center justify-between text-on-surface-variant font-label-md text-label-md mt-space-xs pt-space-xs">
                <span>BSP target band 2.0% - 4.0%</span>
                <span class="font-semibold text-primary">{{ $inflationBadge }}</span>
            </div>
        </div>
    </div>

    {{-- SECTION 2: KEY RATES STRIP --}}
    <div data-tour="key-rates" class="rounded-2xl bg-surface-container-low p-space-md shadow-sm">
        <div class="flex flex-wrap items-center justify-between gap-space-xs mb-space-sm px-1">
            <div class="flex items-center gap-2">
                <span class="material-symbols-outlined text-primary text-[18px]">account_balance</span>
                <span class="font-label-micro text-label-micro uppercase tracking-wider text-primary font-bold">BSP POLICY & SYSTEM BENCHMARKS</span>
            </div>
            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-surface-container-lowest font-label-micro text-label-micro text-on-tertiary-container font-semibold">
                <span class="w-1.5 h-1.5 rounded-full bg-on-tertiary-container"></span> Fixing Complete
            </span>
        </div>
        {{--
            Each card below is one independent BSP indicator - and each one
            carries its OWN "as of" date, not a shared one. That's not a bug:
            BSP updates these at different cadences (some daily, some only
            when a new auction/review happens), so e.g. the 28-day TDF here
            hasn't changed since 2020 and the ON Reference Rate since 2023.
            Every card must therefore store and display its own as_of_date.

            Wired to the database via Dashboard::getDashboardViewData() -
            $keyRates is keyed by market_indicators.code. Still seeded
            manually (database/seeders/MarketDataSeeder.php) until Module 2
            gives this source a real fetcher.
        --}}
        @php
            $formatRate = function (?float $value): ?string {
                if ($value === null) {
                    return null;
                }

                return rtrim(rtrim(number_format($value, 4, '.', ''), '0'), '.').'%';
            };

            $keyRateCards = [
                ['code' => 'inflation_rate', 'label' => 'Inflation Rate (2018=100)', 'icon' => 'trending_up'],
                ['code' => 'on_lending_rate', 'label' => 'ON Lending Rate', 'icon' => 'real_estate_agent'],
                ['code' => 'target_rrp_rate', 'label' => 'Target RRP Rate', 'icon' => 'ads_click', 'highlight' => true],
                ['code' => 'on_deposit_rate', 'label' => 'ON Deposit Rate', 'icon' => 'savings'],
                ['code' => 'on_rrp_rate', 'label' => 'ON RRP Rate', 'icon' => 'sync_alt'],
                ['code' => 'bsp_securities_28d', 'label' => '28-day BSP Securities (WAIR)', 'icon' => 'calendar_month'],
                ['code' => 'bsp_securities_56d', 'label' => '56-day BSP Securities (WAIR)', 'icon' => 'event_repeat'],
                ['code' => 'on_reference_rate', 'label' => 'ON Reference Rate', 'icon' => 'history'],
                ['code' => 'tdf_7d', 'label' => '7-day TDF (WAIR)', 'icon' => 'history_toggle_off'],
                ['code' => 'tdf_14d', 'label' => '14-day TDF (WAIR)', 'icon' => 'date_range'],
                ['code' => 'tdf_28d', 'label' => '28-day TDF (WAIR)', 'icon' => 'event_busy'],
            ];
        @endphp
        <div class="grid grid-cols-2 md:grid-cols-4 gap-space-sm">
            @foreach ($keyRateCards as $card)
                @php
                    $snapshot = $keyRates[$card['code']] ?? [];
                    $rateDisplay = $snapshot['valueText'] ?? $formatRate($snapshot['value'] ?? null) ?? '—';
                    $dateDisplay = $snapshot['periodLabel']
                        ?? optional($snapshot['asOfDate'] ?? null)->format('M j, Y')
                        ?? 'No data yet';
                    $highlight = $card['highlight'] ?? false;
                @endphp
                <div class="{{ $highlight ? 'bg-primary text-on-primary' : 'bg-surface-container-lowest hover:bg-surface-container-high/40 transition-colors cursor-pointer' }} rounded-xl p-space-sm shadow-xs flex items-center gap-space-sm">
                    <div class="w-9 h-9 rounded-lg {{ $highlight ? 'bg-surface-container-high/20' : 'bg-surface-container-high' }} flex items-center justify-center flex-shrink-0 {{ $highlight ? 'text-primary-fixed' : 'text-primary' }}">
                        <span class="material-symbols-outlined text-[18px]">{{ $card['icon'] }}</span>
                    </div>
                    <div class="min-w-0">
                        <div class="font-label-micro text-label-micro {{ $highlight ? 'text-primary-fixed-dim uppercase' : 'text-secondary' }} truncate">{{ $card['label'] }}</div>
                        <div class="font-headline-sm text-headline-sm font-bold {{ $highlight ? 'text-on-primary' : 'text-primary' }}">{{ $rateDisplay }}</div>
                        @if (! empty($snapshot['sourceNote']))
                            <div class="font-label-micro text-label-micro {{ $highlight ? 'text-primary-fixed-dim' : 'text-secondary' }}">{{ $snapshot['sourceNote'] }}</div>
                        @endif
                        <div class="font-label-micro text-label-micro {{ $highlight ? 'text-primary-fixed-dim' : 'text-secondary' }}">{{ $dateDisplay }}</div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    

    {{-- SECTION 3: TWO-COLUMN FX + YIELD CURVE --}}
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-gutter-desktop">
        {{-- Left Column: FX Summary USD/PHP --}}
        <div data-tour="bap-table" class="lg:col-span-5 bg-surface-container-lowest rounded-2xl p-space-lg shadow-sm flex flex-col justify-between">
            @php
                $bapOpenTime = $bapFx['bap_usdphp_open_time'] ?? [];
                $bapReference = $bapFx['bap_usdphp_open'] ?? [];
                $bapTodayDate = optional($bapReference['asOfDate'] ?? null)->format('M j, Y');
                $bapPreviousDate = optional($bapReference['previousAsOfDate'] ?? null)->format('M j, Y');

                $bapRows = [
                    ['label' => 'Open', 'code' => 'bap_usdphp_open', 'decimals' => 4],
                    ['label' => 'High', 'code' => 'bap_usdphp_high', 'decimals' => 4, 'class' => 'text-error'],
                    ['label' => 'Low', 'code' => 'bap_usdphp_low', 'decimals' => 4, 'class' => 'text-on-tertiary-container'],
                    ['label' => 'Close', 'code' => 'bap_usdphp_close', 'decimals' => 4],
                    ['label' => 'BAP AM Weighted Average', 'code' => 'bap_usdphp_am_wa', 'decimals' => 3],
                    ['label' => 'AM Volume', 'code' => 'bap_usdphp_am_volume', 'decimals' => 2],
                    ['label' => 'BAP PM Weighted Average', 'code' => 'bap_usdphp_pm_wa', 'decimals' => 3],
                    ['label' => 'PM Volume', 'code' => 'bap_usdphp_pm_volume', 'decimals' => 2],
                    ['label' => 'BAP Weighted Average', 'code' => 'bap_usdphp_wa', 'decimals' => 3],
                    ['label' => 'Total Volume', 'code' => 'bap_usdphp_total_volume', 'decimals' => 2],
                    ['label' => 'FX Settlement Rate', 'code' => 'bap_usdphp_settlement', 'decimals' => 3, 'highlight' => true],
                ];

                $bapTurnover = $bapFx['bap_usdphp_total_volume']['value'] ?? null;
            @endphp
            <div>
                <div class="flex items-center justify-between gap-space-sm mb-space-md">
                    <div>
                        <h2 class="font-headline-md text-headline-md text-primary font-bold tracking-tight">BAP Spot USD/PHP</h2>
                        <p class="font-label-md text-label-md text-secondary">Bankers Association of the Philippines fixing summary</p>
                    </div>
                </div>
                <div class="overflow-x-auto rounded-xl border border-outline-variant/50">
                    <table class="w-full border-collapse">
                        <thead>
                            <tr class="bg-primary">
                                <th class="px-space-sm py-2 text-left font-label-md text-label-md font-semibold text-on-primary">Metric</th>
                                <th class="px-space-sm py-2 text-right font-label-md text-label-md font-semibold text-on-primary">Today</th>
                                <th class="px-space-sm py-2 text-right font-label-md text-label-md font-semibold text-on-primary">Previous</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-outline-variant/40">
                            <tr class="odd:bg-surface-container-lowest even:bg-surface-container-low">
                                <th scope="row" class="px-space-sm py-1.5 text-left font-label-md text-label-md font-medium text-secondary">Date</th>
                                <td class="px-space-sm py-1.5 text-right font-label-md text-label-md font-semibold text-primary">{{ $bapTodayDate ?? 'No data yet' }}</td>
                                <td class="px-space-sm py-1.5 text-right font-label-md text-label-md text-on-surface-variant">{{ $bapPreviousDate ?? '—' }}</td>
                            </tr>
                            <tr class="odd:bg-surface-container-lowest even:bg-surface-container-low">
                                <th scope="row" class="px-space-sm py-1.5 text-left font-label-md text-label-md font-medium text-secondary">Open Time</th>
                                <td class="px-space-sm py-1.5 text-right font-label-md text-label-md font-semibold text-primary">{{ $bapOpenTime['valueText'] ?? '—' }}</td>
                                <td class="px-space-sm py-1.5 text-right font-label-md text-label-md text-on-surface-variant">{{ $bapOpenTime['previousValueText'] ?? '—' }}</td>
                            </tr>
                            @foreach ($bapRows as $row)
                                @php
                                    $snapshot = $bapFx[$row['code']] ?? [];
                                    $todayValue = $snapshot['value'] ?? null;
                                    $previousValue = $snapshot['previousValue'] ?? null;
                                    $highlight = $row['highlight'] ?? false;
                                @endphp
                                <tr class="{{ $highlight ? 'bg-secondary-container/60' : 'odd:bg-surface-container-lowest even:bg-surface-container-low' }}">
                                    <th scope="row" class="px-space-sm py-1.5 text-left font-label-md text-label-md font-medium {{ $highlight ? 'font-semibold text-on-secondary-container' : 'text-secondary' }}">{{ $row['label'] }}</th>
                                    <td class="px-space-sm py-1.5 text-right font-label-md text-label-md font-semibold {{ $highlight ? 'text-on-secondary-container font-bold' : ($row['class'] ?? 'text-primary') }}">
                                        {{ $todayValue !== null ? number_format($todayValue, $row['decimals']) : '—' }}
                                    </td>
                                    <td class="px-space-sm py-1.5 text-right font-label-md text-label-md {{ $highlight ? 'text-on-secondary-container font-semibold' : 'text-on-surface-variant' }}">
                                        {{ $previousValue !== null ? number_format($previousValue, $row['decimals']) : '—' }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="pt-space-sm mt-space-sm bg-surface-container-low rounded-xl p-space-sm flex items-center justify-between">
                <div class="flex items-center gap-space-xs">
                    <span class="material-symbols-outlined text-primary text-[20px]">currency_exchange</span>
                    <div>
                        <div class="font-label-micro text-label-micro text-secondary uppercase">Daily Turnover</div>
                        <div class="font-label-md text-label-md font-bold text-primary">{{ $bapTurnover !== null ? '$'.number_format($bapTurnover, 2).'M' : 'No data yet' }}</div>
                    </div>
                </div>
                <span class="font-label-micro text-label-micro text-secondary">Source: bap.org.ph FX Historical</span>
            </div>
        </div>

        {{-- Right Column: PHP BVAL Yield Curve --}}
        <div data-tour="bval-table" class="lg:col-span-7 bg-surface-container-lowest rounded-2xl p-space-lg shadow-sm flex flex-col justify-between">
            @php
                $bval2y = collect($bval)->firstWhere('tenor', '2Y');
                $bval10y = collect($bval)->firstWhere('tenor', '10Y');
                $bvalSpreadBps = ($bval2y && $bval10y) ? ($bval10y['value'] - $bval2y['value']) * 100 : null;
            @endphp
            <div>
                <div class="flex flex-wrap items-center justify-between gap-space-sm mb-space-md">
                    <div>
                        <div class="flex items-center gap-space-xs">
                            <h2 class="font-headline-md text-headline-md text-primary font-bold tracking-tight">PHP BVAL Reference Rates</h2>
                            <span class="font-label-micro text-label-micro px-2 py-0.5 rounded bg-surface-container-high text-primary font-semibold">Benchmark Tenors, 1M to 25Y</span>
                        </div>
                        <p class="font-label-md text-label-md text-secondary">The Philippine Dealing System Holdings Corp. — PDS</p>
                    </div>
                </div>
                <div class="overflow-x-auto rounded-xl border border-outline-variant/50">
                    <table class="w-full border-collapse">
                        <thead>
                            <tr class="bg-primary">
                                <th class="px-space-sm py-2 text-left font-label-md text-label-md font-semibold text-on-primary">Tenor</th>
                                <th class="px-space-sm py-2 text-right font-label-md text-label-md font-semibold text-on-primary">BVAL Rate Today</th>
                                <th class="px-space-sm py-2 text-right font-label-md text-label-md font-semibold text-on-primary">BVAL Rate Previous Day</th>
                                <th class="px-space-sm py-2 text-right font-label-md text-label-md font-semibold text-on-primary">Change (bps)</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-outline-variant/40">
                            @forelse ($bval as $point)
                                @php
                                    $changeBps = $point['previousValue'] !== null ? ($point['value'] - $point['previousValue']) * 100 : null;
                                @endphp
                                <tr class="odd:bg-surface-container-lowest even:bg-surface-container-low {{ $point['tenor'] === '10Y' ? 'ring-1 ring-inset ring-primary-container/60' : '' }}">
                                    <th scope="row" class="px-space-sm py-1.5 text-left font-label-md text-label-md font-semibold text-primary">{{ $point['tenor'] }}</th>
                                    <td class="px-space-sm py-1.5 text-right font-label-md text-label-md font-semibold text-primary">{{ number_format($point['value'], 4) }}</td>
                                    <td class="px-space-sm py-1.5 text-right font-label-md text-label-md text-on-surface-variant">{{ $point['previousValue'] !== null ? number_format($point['previousValue'], 4) : '—' }}</td>
                                    <td class="px-space-sm py-1.5 text-right font-label-md text-label-md font-semibold {{ $changeBps === null ? 'text-on-surface-variant' : ($changeBps >= 0 ? 'text-error' : 'text-on-tertiary-container') }}">
                                        {{ $changeBps === null ? '—' : (($changeBps >= 0 ? '+' : '').number_format($changeBps, 1)) }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="px-space-sm py-4 text-center font-label-md text-label-md text-secondary">No data yet</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="flex flex-wrap items-center justify-between text-on-surface-variant font-label-md text-label-md pt-space-xs bg-surface-container-low rounded-xl p-space-sm mt-space-sm">
                <span class="flex items-center gap-1 font-semibold text-primary">
                    <span class="material-symbols-outlined text-[16px]">show_chart</span>
                    2s/10s Spread: {{ $bvalSpreadBps !== null ? (($bvalSpreadBps >= 0 ? '+' : '').number_format($bvalSpreadBps, 1).' bps') : 'No data yet' }}
                </span>
                <span class="text-secondary">Source: pds.com.ph — PHP BVAL Reference Rates widget</span>
            </div>
        </div>
    </div>


    {{-- SECTION 2B: INFLATION UPDATE (Year-on-Year, PSA) --}}
    <div data-tour="inflation-update" class="bg-surface-container-lowest rounded-2xl p-space-lg shadow-sm">
        <div class="flex flex-wrap items-center justify-between gap-space-sm mb-space-md">
            <div>
                <h2 class="font-headline-md text-headline-md text-primary font-bold tracking-tight">Inflation Update</h2>
                <p class="font-label-md text-label-md text-secondary">Year-on-Year Inflation Rates in the Philippines, All Items (2018=100)</p>
            </div>
        </div>
        <div class="overflow-x-auto rounded-xl border border-outline-variant/50">
            <table class="w-full border-collapse">
                <thead>
                    <tr class="bg-primary">
                        <th class="px-space-sm py-2 text-left font-label-md text-label-md font-semibold text-on-primary">Month</th>
                        @foreach ($inflationHistory['years'] as $year)
                            <th class="px-space-sm py-2 text-right font-label-md text-label-md font-semibold text-on-primary">{{ $year }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody class="divide-y divide-outline-variant/40">
                    @forelse ($inflationHistory['months'] as $month)
                        <tr class="odd:bg-surface-container-lowest even:bg-surface-container-low">
                            <th scope="row" class="px-space-sm py-1.5 text-left font-label-md text-label-md font-medium text-secondary">{{ $month['label'] }}</th>
                            @foreach ($inflationHistory['years'] as $year)
                                <td class="px-space-sm py-1.5 text-right font-label-md text-label-md text-primary">
                                    {{ $month['values'][$year] !== null ? number_format($month['values'][$year], 1) : '—' }}
                                </td>
                            @endforeach
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ count($inflationHistory['years']) + 1 }}" class="px-space-sm py-4 text-center font-label-md text-label-md text-secondary">No data yet</td>
                        </tr>
                    @endforelse
                    @if ($inflationHistory['years'] !== [])
                        <tr class="bg-secondary-container/60">
                            <th scope="row" class="px-space-sm py-1.5 text-left font-label-md text-label-md font-semibold text-on-secondary-container">Average</th>
                            @foreach ($inflationHistory['years'] as $year)
                                <td class="px-space-sm py-1.5 text-right font-label-md text-label-md font-bold text-on-secondary-container">
                                    {{ $inflationHistory['averages'][$year] !== null ? number_format($inflationHistory['averages'][$year], 1) : '—' }}
                                </td>
                            @endforeach
                        </tr>
                    @endif
                </tbody>
            </table>
        </div>
        <p class="font-label-micro text-label-micro text-secondary mt-space-sm">Source: Philippine Statistics Authority, Retail Price Survey of Selected Commodities for the Generation of Consumer Price Index.</p>
    </div>

    {{-- SECTION 2C: GDP GROWTH HISTORY (Year-on-Year, PSA) --}}
    <div data-tour="gdp-history" class="bg-surface-container-lowest rounded-2xl p-space-lg shadow-sm">
        <div class="flex flex-wrap items-center justify-between gap-space-sm mb-space-md">
            <div>
                <span class="font-label-micro text-label-micro px-2 py-0.5 rounded bg-surface-container-high text-primary font-semibold uppercase tracking-wider">Figure</span>
                <h2 class="font-headline-md text-headline-md text-primary font-bold tracking-tight mt-1">GDP Growth History</h2>
                <p class="font-label-md text-label-md text-secondary">Gross Domestic Product (At Constant 2018 Prices), Year-on-Year Growth Rates</p>
            </div>
        </div>

        @php
            $trendCount = count($gdpTrend);
        @endphp
        @if ($trendCount >= 2)
            @php
                $chartWidth = 1000;
                $chartHeight = 300;
                $padLeft = 46;
                $padRight = 16;
                $padTop = 30;
                $padBottom = 36;
                $innerWidth = $chartWidth - $padLeft - $padRight;
                $innerHeight = $chartHeight - $padTop - $padBottom;
                $xStep = $innerWidth / ($trendCount - 1);

                $trendValues = collect($gdpTrend)->pluck('value');
                $rawMin = min($trendValues->min(), 0);
                $rawMax = max($trendValues->max(), 0);
                $range = ($rawMax - $rawMin) ?: 1.0;
                $padFraction = 0.15;
                $scaleMin = $rawMin - $range * $padFraction;
                $scaleMax = $rawMax + $range * $padFraction;
                $scaleRange = ($scaleMax - $scaleMin) ?: 1.0;

                $toX = fn (int $index): float => round($padLeft + $index * $xStep, 2);
                $toY = fn (float $value): float => round($padTop + $innerHeight * (1 - (($value - $scaleMin) / $scaleRange)), 2);
                $zeroY = $toY(0);

                $plotted = collect($gdpTrend)->values()->map(fn (array $point, int $index): array => [
                    ...$point,
                    'x' => $toX($index),
                    'y' => $toY($point['value']),
                ]);

                $linePath = $plotted->map(fn (array $p, int $i): string => ($i === 0 ? 'M' : 'L')." {$p['x']},{$p['y']}")->implode(' ');
                $areaPath = $linePath." L {$plotted->last()['x']},{$zeroY} L {$plotted->first()['x']},{$zeroY} Z";
                $lastIndex = $trendCount - 1;
            @endphp
            <div class="relative w-full h-64 mb-space-md">
                <svg class="w-full h-full" fill="none" viewBox="0 0 {{ $chartWidth }} {{ $chartHeight }}" preserveAspectRatio="none" role="img" aria-label="GDP year-on-year growth rate trend, {{ $plotted->first()['label'] }} to {{ $plotted->last()['label'] }}">
                    <line x1="{{ $padLeft }}" x2="{{ $chartWidth - $padRight }}" y1="{{ $zeroY }}" y2="{{ $zeroY }}" stroke="#c6c5d0" stroke-width="1"></line>
                    <text x="{{ $padLeft - 6 }}" y="{{ $zeroY + 3 }}" fill="#565f68" font-family="Inter" font-size="10" text-anchor="end">0%</text>

                    <path d="{{ $areaPath }}" fill="#021446" fill-opacity="0.08"></path>
                    <path d="{{ $linePath }}" stroke="#021446" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path>

                    @foreach ($plotted as $index => $p)
                        @php
                            $showXTick = ($lastIndex - $index) % 4 === 0;
                            $showValueLabel = $p['isMax'] || $p['isMin'] || $p['isLatest'];
                            $labelAbove = $p['value'] >= 0;
                        @endphp
                        <circle cx="{{ $p['x'] }}" cy="{{ $p['y'] }}" r="4" fill="{{ $p['value'] < 0 ? '#ba1a1a' : '#021446' }}" stroke="#ffffff" stroke-width="2">
                            <title>{{ $p['label'] }}: {{ number_format($p['value'], 1) }}%</title>
                        </circle>
                        <circle cx="{{ $p['x'] }}" cy="{{ $p['y'] }}" r="12" fill="transparent">
                            <title>{{ $p['label'] }}: {{ number_format($p['value'], 1) }}%</title>
                        </circle>

                        @if ($showValueLabel)
                            <text x="{{ $p['x'] }}" y="{{ $labelAbove ? $p['y'] - 10 : $p['y'] + 18 }}" fill="{{ $p['value'] < 0 ? '#ba1a1a' : '#021446' }}" font-family="Inter" font-size="11" font-weight="700" text-anchor="middle">{{ number_format($p['value'], 1) }}</text>
                        @endif

                        @if ($showXTick)
                            <text x="{{ $p['x'] }}" y="{{ $chartHeight - 12 }}" fill="#565f68" font-family="Inter" font-size="9" text-anchor="middle">{{ $p['label'] }}</text>
                        @endif
                    @endforeach
                </svg>
            </div>
        @else
            <div class="w-full h-32 flex items-center justify-center mb-space-md">
                <span class="font-label-md text-label-md text-secondary">Not enough data yet for a trend line</span>
            </div>
        @endif

        <div class="overflow-x-auto rounded-xl border border-outline-variant/50">
            <table class="w-full border-collapse">
                <thead>
                    <tr class="bg-primary">
                        <th class="px-space-sm py-2 text-left font-label-md text-label-md font-semibold text-on-primary">Quarter</th>
                        @foreach ($gdpHistory['years'] as $year)
                            <th class="px-space-sm py-2 text-right font-label-md text-label-md font-semibold text-on-primary">{{ $year }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody class="divide-y divide-outline-variant/40">
                    @forelse ($gdpHistory['quarters'] as $quarter)
                        <tr class="odd:bg-surface-container-lowest even:bg-surface-container-low">
                            <th scope="row" class="px-space-sm py-1.5 text-left font-label-md text-label-md font-medium text-secondary">{{ $quarter['label'] }}</th>
                            @foreach ($gdpHistory['years'] as $year)
                                @php $value = $quarter['values'][$year] ?? null; @endphp
                                <td class="px-space-sm py-1.5 text-right font-label-md text-label-md {{ $value !== null && $value < 0 ? 'text-error' : 'text-primary' }}">
                                    {{ $value !== null ? number_format($value, 1) : '—' }}
                                </td>
                            @endforeach
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ count($gdpHistory['years']) + 1 }}" class="px-space-sm py-4 text-center font-label-md text-label-md text-secondary">No data yet</td>
                        </tr>
                    @endforelse
                    @if ($gdpHistory['years'] !== [])
                        <tr class="bg-secondary-container/60">
                            <th scope="row" class="px-space-sm py-1.5 text-left font-label-md text-label-md font-semibold text-on-secondary-container">Average</th>
                            @foreach ($gdpHistory['years'] as $year)
                                <td class="px-space-sm py-1.5 text-right font-label-md text-label-md font-bold text-on-secondary-container">
                                    {{ $gdpHistory['averages'][$year] !== null ? number_format($gdpHistory['averages'][$year], 1) : '—' }}
                                </td>
                            @endforeach
                        </tr>
                    @endif
                </tbody>
            </table>
        </div>
        <p class="font-label-micro text-label-micro text-secondary mt-space-sm">Source: Philippine Statistics Authority, National Accounts of the Philippines (Quarterly).</p>
    </div>

    {{-- SECTION 4: EQUITIES COMPREHENSIVE OVERVIEW - wired to PSE Edge (PseIndexSummaryFetcher), except Foreign Flow --}}
    @php
        $pseSectorCards = [
            ['label' => 'All Shares', 'code' => 'pse_all_shares'],
            ['label' => 'Financials', 'code' => 'pse_financials'],
            ['label' => 'Industrial', 'code' => 'pse_industrial'],
            ['label' => 'Holding Firms', 'code' => 'pse_holding_firms'],
            ['label' => 'Property', 'code' => 'pse_property'],
            ['label' => 'Services', 'code' => 'pse_services'],
            ['label' => 'Mining & Oil', 'code' => 'pse_mining_oil'],
        ];

        $pseTurnover = $pse['pse_total_value']['value'] ?? null;
        $pseTurnoverText = match (true) {
            $pseTurnover === null => 'No data yet',
            $pseTurnover >= 1e9 => 'PHP '.number_format($pseTurnover / 1e9, 2).'B',
            default => 'PHP '.number_format($pseTurnover / 1e6, 1).'M',
        };

        $pseAdvances = (int) ($pse['pse_advances']['value'] ?? 0);
        $pseDeclines = (int) ($pse['pse_declines']['value'] ?? 0);
        $pseUnchanged = (int) ($pse['pse_unchanged']['value'] ?? 0);
        $pseBreadthTotal = $pseAdvances + $pseDeclines + $pseUnchanged;
        $pseBreadthPercent = fn (int $count): float => $pseBreadthTotal > 0 ? $count / $pseBreadthTotal * 100 : 0.0;
        $pseiChangeColor = $pseiCard['change'] === null ? 'text-primary' : ($pseiCard['isUp'] ? 'text-on-tertiary-container' : 'text-error');
    @endphp
    <div data-tour="pse-overview" class="bg-surface-container-lowest rounded-2xl p-space-lg shadow-sm">
        <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-space-md mb-space-lg">
            <div>
                <div class="flex items-center gap-space-sm">
                    <h2 class="font-headline-md text-headline-md text-primary font-bold tracking-tight">Philippine Stock Market Overview</h2>
                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full bg-secondary-container text-on-secondary-container font-label-micro text-label-micro font-semibold">
                        <span class="material-symbols-outlined text-[14px]">schedule</span> {{ $pseStatusLabel }}
                    </span>
                </div>
                <p class="font-label-md text-label-md text-secondary">PSEi Composite & Broad Sub-Sector Telemetry &middot; As of {{ $pseAsOfText }}</p>
            </div>
            <div class="flex items-baseline gap-space-md">
                <div>
                    <div class="font-label-micro text-label-micro text-secondary uppercase">PSEi Level</div>
                    <div class="font-metric-md text-metric-md font-bold text-primary">{{ $pseiCard['valueText'] }}</div>
                </div>
                <div>
                    <div class="font-label-micro text-label-micro text-secondary uppercase">Day Change</div>
                    <div class="font-headline-sm text-headline-sm font-bold {{ $pseiChangeColor }}">
                        {{ $pseiCard['change'] !== null ? $pseiCard['changeText'].' ('.($pseiCard['percent'] >= 0 ? '+' : '-').number_format(abs($pseiCard['percent']), 2).'%)' : '—' }}
                    </div>
                </div>
                <div class="hidden sm:block">
                    <div class="font-label-micro text-label-micro text-secondary uppercase">Total Turnover</div>
                    <div class="font-headline-sm text-headline-sm font-bold text-primary">{{ $pseTurnoverText }}</div>
                </div>
            </div>
        </div>
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-space-md mb-space-lg">
            @foreach ($pseSectorCards as $card)
                @php $index = $pseIndex($card['code']); @endphp
                <div class="bg-surface-container-low rounded-xl p-space-sm flex flex-col justify-between">
                    <span class="font-label-micro text-label-micro text-secondary uppercase">{{ $card['label'] }}</span>
                    <div class="flex items-baseline justify-between mt-1">
                        <span class="font-headline-sm text-headline-sm font-bold text-primary">{{ $index['valueText'] }}</span>
                        <span class="font-label-md text-label-md font-semibold {{ $index['percent'] === null ? 'text-secondary' : ($index['isUp'] ? 'text-on-tertiary-container' : 'text-error') }}">{!! $index['percentText'] !!}</span>
                    </div>
                </div>
            @endforeach
            <div class="bg-surface-container-high/60 rounded-xl p-space-sm flex flex-col justify-between">
                <div class="flex items-center justify-between gap-1">
                    <span class="font-label-micro text-label-micro text-primary uppercase font-bold">Foreign Flow</span>
                    <span class="font-label-micro text-label-micro text-outline uppercase tracking-wider">Not dynamic</span>
                </div>
                <div class="flex items-baseline justify-between mt-1">
                    <span class="font-headline-sm text-headline-sm font-bold text-error">-$14.80M</span>
                    <span class="font-label-md text-label-md font-semibold text-secondary">Net Outflow</span>
                </div>
            </div>
        </div>
        <div class="space-y-space-xs pt-space-xs">
            <div class="flex items-center justify-between font-label-micro text-label-micro">
                <span class="text-on-tertiary-container font-bold flex items-center gap-1">
                    <span class="w-2 h-2 rounded-full bg-on-tertiary-container"></span> Advances: {{ $pseAdvances }} ({{ number_format($pseBreadthPercent($pseAdvances), 0) }}%)
                </span>
                <span class="text-secondary font-bold flex items-center gap-1">
                    <span class="w-2 h-2 rounded-full bg-secondary"></span> Unchanged: {{ $pseUnchanged }} ({{ number_format($pseBreadthPercent($pseUnchanged), 0) }}%)
                </span>
                <span class="text-error font-bold flex items-center gap-1">
                    <span class="w-2 h-2 rounded-full bg-error"></span> Declines: {{ $pseDeclines }} ({{ number_format($pseBreadthPercent($pseDeclines), 0) }}%)
                </span>
            </div>
            <div class="w-full h-2.5 rounded-full overflow-hidden flex bg-surface-container-high">
                <div class="bg-on-tertiary-container h-full" style="width: {{ round($pseBreadthPercent($pseAdvances), 2) }}%;" title="Advances: {{ $pseAdvances }}"></div>
                <div class="bg-secondary-fixed-dim h-full" style="width: {{ round($pseBreadthPercent($pseUnchanged), 2) }}%;" title="Unchanged: {{ $pseUnchanged }}"></div>
                <div class="bg-error h-full" style="width: {{ round($pseBreadthPercent($pseDeclines), 2) }}%;" title="Declines: {{ $pseDeclines }}"></div>
            </div>
        </div>

        @php
            $pseSummaryRows = [
                ['label' => 'Total Volume', 'value' => $pse['pse_total_volume']['value'] ?? null],
                ['label' => 'Total Trades', 'value' => $pse['pse_total_trades']['value'] ?? null],
                ['label' => 'Total Value (in PHP)', 'value' => $pseTurnover],
                ['label' => 'Advances', 'value' => $pse['pse_advances']['value'] ?? null, 'class' => 'text-on-tertiary-container'],
                ['label' => 'Declines', 'value' => $pse['pse_declines']['value'] ?? null, 'class' => 'text-error'],
                ['label' => 'Unchanged', 'value' => $pse['pse_unchanged']['value'] ?? null],
            ];
        @endphp
        <div class="mt-space-lg">
            <h3 class="font-headline-sm text-headline-sm text-primary font-bold mb-space-sm">Market Summary</h3>
            <div class="overflow-x-auto rounded-xl border border-outline-variant/50">
                <table class="w-full border-collapse">
                    <tbody class="divide-y divide-outline-variant/40">
                        @foreach ($pseSummaryRows as $row)
                            <tr class="odd:bg-surface-container-low even:bg-surface-container-lowest">
                                <th scope="row" class="px-space-sm py-2 text-left font-label-md text-label-md font-semibold text-primary">{{ $row['label'] }}</th>
                                <td class="px-space-sm py-2 text-right font-label-md text-label-md font-bold {{ $row['class'] ?? 'text-primary' }}">{{ $row['value'] !== null ? number_format($row['value']) : '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <p class="font-label-micro text-label-micro text-secondary mt-space-sm">Source: PSE Edge (as of {{ $pseAsOfText }}). PSE notes that its published market data is delayed by 15 minutes and is for market education only, not intended for trading purposes.</p>
        </div>
    </div>

    {{-- SECTION 5: COMMODITIES STRIP - Markets Insider (gold/wheat/Brent) + Westmetall (LME Lead) --}}
    @php
        $commodityDefs = [
            ['code' => 'brent_crude', 'label' => 'Brent Crude', 'icon' => 'oil_barrel', 'unit' => '/bbl', 'source' => 'Markets Insider'],
            ['code' => 'gold_spot', 'label' => 'Spot Gold', 'icon' => 'monetization_on', 'unit' => '/oz', 'source' => 'Markets Insider'],
            ['code' => 'wheat_price', 'label' => 'Wheat', 'icon' => 'grain', 'unit' => '', 'source' => 'Markets Insider'],
            ['code' => 'lme_lead_cash', 'label' => 'LME Lead', 'icon' => 'precision_manufacturing', 'unit' => '/t', 'source' => 'Westmetall · LME Cash-Settlement'],
        ];

        // Sparkline path in a 160x40 box from the stored history; needs a
        // handful of points to mean anything, otherwise the card falls
        // back to a decorative placeholder line tagged "Not dynamic".
        $sparkline = function (array $history): ?array {
            if (count($history) < 5) {
                return null;
            }

            $min = min($history);
            $max = max($history);
            $range = ($max - $min) ?: 1.0;
            $step = 160 / (count($history) - 1);
            $points = [];

            foreach ($history as $i => $value) {
                $points[] = round($i * $step, 2).','.round(36 - (($value - $min) / $range) * 32, 2);
            }

            return ['line' => 'M'.implode(' L', $points), 'area' => 'M'.implode(' L', $points).' L160,40 L0,40 Z'];
        };
    @endphp
    <div data-tour="commodities" class="space-y-space-md">
        <div class="flex flex-wrap items-center justify-between gap-space-sm">
            <div class="flex items-center gap-space-sm">
                <h2 class="font-headline-md text-headline-md text-primary font-bold tracking-tight">Key Treasury Commodities</h2>
                <span class="font-label-micro text-label-micro uppercase text-secondary">Energy &middot; Metals &middot; Agriculture</span>
            </div>
            <span class="font-label-micro text-label-micro text-secondary">Sparklines show the readings stored so far</span>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-gutter-desktop">
            @foreach ($commodityDefs as $def)
                @php
                    $card = $commodities[$def['code']] ?? ['value' => null, 'percentChange' => null, 'asOfDate' => null, 'note' => null, 'history' => []];
                    $pct = $card['percentChange'];
                    $isUp = $pct !== null && $pct >= 0;
                    $spark = $sparkline($card['history']);
                    $sparkColor = $spark && count($card['history']) > 1 && end($card['history']) < $card['history'][0] ? '#ba1a1a' : '#24ab51';
                    $daysOld = $card['asOfDate'] ? (int) $card['asOfDate']->diffInDays(now()->startOfDay()) : null;
                @endphp
                <div class="bg-surface-container-lowest rounded-2xl p-space-md shadow-sm flex flex-col justify-between hover:shadow-md transition-all">
                    <div>
                        <div class="flex items-center justify-between gap-space-xs mb-space-xs">
                            <div class="flex items-center gap-1.5">
                                <span class="material-symbols-outlined text-primary text-[18px]">{{ $def['icon'] }}</span>
                                <span class="font-label-micro text-label-micro uppercase text-secondary font-semibold">{{ $def['label'] }}</span>
                            </div>
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full {{ $pct === null ? 'bg-surface-container-high text-on-surface-variant' : ($isUp ? 'bg-tertiary-fixed text-tertiary-container' : 'bg-error-container text-on-error-container') }} font-label-micro text-label-micro font-bold">
                                {{ $pct === null ? 'No data yet' : ($isUp ? '+' : '-').number_format(abs($pct), 2).'%' }}
                            </span>
                        </div>
                        <div class="font-metric-md text-metric-md font-bold text-primary">
                            {{ $card['value'] !== null ? '$'.number_format($card['value'], 2) : '—' }}
                            @if ($def['unit'] !== '')
                                <span class="font-body-sm text-body-sm font-normal text-secondary">{{ $def['unit'] }}</span>
                            @endif
                        </div>
                    </div>
                    <div class="w-full h-12 my-2 relative">
                        @if ($spark)
                            <svg class="w-full h-full" fill="none" preserveAspectRatio="none" viewBox="0 0 160 40" role="img" aria-label="{{ $def['label'] }} recent trend">
                                <path d="{{ $spark['area'] }}" fill="{{ $sparkColor }}" fill-opacity="0.12"></path>
                                <path d="{{ $spark['line'] }}" stroke="{{ $sparkColor }}" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"></path>
                            </svg>
                        @else
                            <span class="absolute right-0 top-0 font-label-micro text-label-micro text-outline uppercase tracking-wider">Not dynamic</span>
                            <svg class="w-full h-full" fill="none" preserveAspectRatio="none" viewBox="0 0 160 40">
                                <path d="M0 32 L30 28 L60 30 L90 20 L120 15 L160 10 L160 40 L0 40 Z" fill="#767680" fill-opacity="0.12"></path>
                                <path d="M0 32 L30 28 L60 30 L90 20 L120 15 L160 10" stroke="#767680" stroke-width="1.8"></path>
                            </svg>
                        @endif
                    </div>
                    <div class="flex items-center justify-between gap-2 font-label-micro text-label-micro text-secondary">
                        <span class="truncate" title="{{ $card['note'] }}">{{ $def['source'] }}</span>
                        <span class="flex items-center gap-1 shrink-0">
                            @if ($daysOld !== null && $daysOld > 3)
                                <span class="material-symbols-outlined text-[14px] text-outline">history</span> {{ $daysOld }} days delayed
                            @else
                                {{ $card['asOfDate']?->format('M j, Y') ?? 'No data yet' }}
                            @endif
                        </span>
                    </div>
                </div>
            @endforeach
        </div>

        {{-- LME Lead detail: trading summary (manual) + cash / 3-month price chart (Westmetall) --}}
        @php
            $lmeRows = $lmeSummary['points'];
            $lmeCount = count($lmeSeries);
        @endphp
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-gutter-desktop">
            <div class="lg:col-span-5 bg-surface-container-lowest rounded-2xl p-space-lg shadow-sm">
                <div class="flex flex-wrap items-center justify-between gap-space-xs mb-space-sm">
                    <div>
                        <h3 class="font-headline-sm text-headline-sm text-primary font-bold">LME Lead Official Prices</h3>
                        <p class="font-label-md text-label-md text-secondary">Trading Summary &middot; Prices in US$/t &middot; Data valid for {{ $lmeSummary['asOfDate']?->format('d M Y') ?? 'No data yet' }}</p>
                    </div>
                    <span class="font-label-micro text-label-micro text-outline uppercase tracking-wider" title="No automated source: lme.com is blocked from our network and its data is licensed. Enter under Market Indicators, LME Lead Official Prices, Curve points.">Not dynamic &middot; manual entry</span>
                </div>
                <div class="overflow-x-auto rounded-xl border border-outline-variant/50">
                    <table class="w-full border-collapse">
                        <thead>
                            <tr class="bg-primary">
                                <th class="px-space-sm py-2 text-left font-label-md text-label-md font-semibold text-on-primary">Contract</th>
                                <th class="px-space-sm py-2 text-right font-label-md text-label-md font-semibold text-on-primary">Bid</th>
                                <th class="px-space-sm py-2 text-right font-label-md text-label-md font-semibold text-on-primary">Offer</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-outline-variant/40">
                            @forelse ($lmeRows as $row)
                                <tr class="odd:bg-surface-container-lowest even:bg-surface-container-low">
                                    <th scope="row" class="px-space-sm py-1.5 text-left font-label-md text-label-md font-semibold text-primary">{{ $row['label'] }}</th>
                                    <td class="px-space-sm py-1.5 text-right font-label-md text-label-md text-primary">{{ $row['bid'] !== null ? number_format($row['bid'], 2) : '—' }}</td>
                                    <td class="px-space-sm py-1.5 text-right font-label-md text-label-md text-primary">{{ $row['offer'] !== null ? number_format($row['offer'], 2) : '—' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="px-space-sm py-4 text-center font-label-md text-label-md text-secondary">No data yet</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="lg:col-span-7 bg-surface-container-lowest rounded-2xl p-space-lg shadow-sm">
                <div class="flex flex-wrap items-center justify-between gap-space-xs mb-space-sm">
                    <div>
                        <h3 class="font-headline-sm text-headline-sm text-primary font-bold">LME Lead Price Curve</h3>
                        <p class="font-label-md text-label-md text-secondary">Cash-Settlement vs 3-month &middot; US$ per tonne &middot; last {{ $lmeCount }} trading days</p>
                    </div>
                    <div class="flex items-center gap-space-md font-label-micro text-label-micro text-primary">
                        <span class="flex items-center gap-1.5"><span class="w-4 h-0.5 rounded-full" style="background: #2a78d6;"></span> Cash-Settlement</span>
                        <span class="flex items-center gap-1.5"><span class="w-4 h-0.5 rounded-full" style="background: #eb6834;"></span> 3-month</span>
                    </div>
                </div>
                @if ($lmeCount >= 2)
                    @php
                        $cw = 700; $ch = 260; $pl = 52; $pr = 16; $pt = 16; $pb = 46;
                        $iw = $cw - $pl - $pr; $ih = $ch - $pt - $pb;
                        $all = collect($lmeSeries)->flatMap(fn (array $p): array => [$p['cash'], $p['threeMonth']]);
                        $step = 20;
                        $yMin = floor(($all->min() - 10) / $step) * $step;
                        $yMax = ceil(($all->max() + 10) / $step) * $step;
                        $yRange = ($yMax - $yMin) ?: 1;
                        $lx = fn (int $i): float => round($pl + $i * ($iw / ($lmeCount - 1)), 2);
                        $ly = fn (float $v): float => round($pt + $ih * (1 - (($v - $yMin) / $yRange)), 2);
                        $cashPath = collect($lmeSeries)->map(fn (array $p, int $i): string => ($i === 0 ? 'M' : 'L').' '.$lx($i).','.$ly($p['cash']))->implode(' ');
                        $threePath = collect($lmeSeries)->map(fn (array $p, int $i): string => ($i === 0 ? 'M' : 'L').' '.$lx($i).','.$ly($p['threeMonth']))->implode(' ');
                        $ticks = 4;
                    @endphp
                    <div class="relative w-full h-64">
                        <svg class="w-full h-full" fill="none" viewBox="0 0 {{ $cw }} {{ $ch }}" preserveAspectRatio="none" role="img" aria-label="LME Lead cash-settlement and 3-month price, last {{ $lmeCount }} trading days">
                            @for ($t = 0; $t <= $ticks; $t++)
                                @php $tv = $yMin + ($yRange / $ticks) * $t; @endphp
                                <line x1="{{ $pl }}" x2="{{ $cw - $pr }}" y1="{{ $ly($tv) }}" y2="{{ $ly($tv) }}" stroke="#e5eeff" stroke-width="1"></line>
                                <text x="{{ $pl - 6 }}" y="{{ $ly($tv) + 3 }}" fill="#565f68" font-family="Inter" font-size="10" text-anchor="end">{{ number_format($tv) }}</text>
                            @endfor
                            <path d="{{ $threePath }}" stroke="#eb6834" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path>
                            <path d="{{ $cashPath }}" stroke="#2a78d6" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path>
                            @foreach ($lmeSeries as $i => $p)
                                @if (($lmeCount - 1 - $i) % 4 === 0)
                                    <text x="{{ $lx($i) }}" y="{{ $ch - 8 }}" fill="#565f68" font-family="Inter" font-size="9" text-anchor="end" transform="rotate(-35 {{ $lx($i) }} {{ $ch - 8 }})">{{ $p['date']->format('d/m/Y') }}</text>
                                @endif
                                <circle cx="{{ $lx($i) }}" cy="{{ $ly($p['cash']) }}" r="3" fill="#2a78d6" stroke="#ffffff" stroke-width="1.5"><title>{{ $p['date']->format('d M Y') }} · Cash-Settlement: {{ number_format($p['cash'], 2) }}</title></circle>
                                <circle cx="{{ $lx($i) }}" cy="{{ $ly($p['threeMonth']) }}" r="3" fill="#eb6834" stroke="#ffffff" stroke-width="1.5"><title>{{ $p['date']->format('d M Y') }} · 3-month: {{ number_format($p['threeMonth'], 2) }}</title></circle>
                            @endforeach
                        </svg>
                    </div>
                    <p class="font-label-micro text-label-micro text-secondary mt-space-xs">Source: Westmetall (LME Cash-Settlement and 3-month prices). Not the LME bid/offer curve.</p>
                @else
                    <div class="w-full h-32 flex items-center justify-center">
                        <span class="font-label-md text-label-md text-secondary">Not enough data yet for a price curve</span>
                    </div>
                @endif
            </div>
        </div>
    </div>

    {{-- SECTION 6: MACRO SECTION (collapsible) --}}
    <div data-tour="macro" class="bg-surface-container-low rounded-2xl shadow-sm transition-all overflow-hidden">
        <div class="p-space-lg flex items-center justify-between cursor-pointer select-none" x-on:click="macroOpen = !macroOpen">
            <div class="flex items-center gap-space-sm">
                <span
                    class="material-symbols-outlined text-primary text-[22px] transition-transform duration-200"
                    :class="{ '-rotate-90': ! macroOpen }"
                >expand_more</span>
                <div>
                    <div class="flex items-center gap-space-xs">
                        <h2 class="font-headline-md text-headline-md text-primary font-bold tracking-tight">Macroeconomic Overview</h2>
                        <span class="inline-flex items-center px-2 py-0.5 rounded bg-surface-container text-primary font-label-micro text-label-micro font-semibold">PSA / BSP Data</span>
                    </div>
                    <p class="font-label-md text-label-md text-secondary">Inflation path targets and real GDP expansion trajectories</p>
                </div>
            </div>
            
        </div>
        <div class="px-space-lg pb-space-lg grid grid-cols-1 md:grid-cols-2 gap-gutter-desktop" x-show="macroOpen" x-collapse>
            {{-- Inflation Band --}}
            <div class="bg-surface-container-lowest rounded-xl p-space-md shadow-xs">
                <div class="flex items-center justify-between mb-space-sm">
                    <div>
                        <div class="font-headline-sm text-headline-sm font-bold text-primary">Inflation YoY % & Target Band</div>
                        <div class="font-label-micro text-label-micro text-secondary">2.0% - 4.0% Government Corridor</div>
                    </div>
                    <span class="font-label-md text-label-md text-error font-bold">Above Target (6.2%)</span>
                </div>
                <div class="w-full h-36 relative">
                    <svg class="w-full h-full" fill="none" viewBox="0 0 320 120">
                        <rect fill="#7ffc97" fill-opacity="0.25" height="35" width="280" x="30" y="55"></rect>
                        <text fill="#24ab51" font-family="Inter" font-size="9" text-anchor="end" x="25" y="75">3.0%</text>
                        <line stroke="#eff4ff" stroke-width="1" x1="30" x2="310" y1="20" y2="20"></line>
                        <text fill="#565f68" font-family="Inter" font-size="9" text-anchor="end" x="25" y="24">7.0%</text>
                        <line stroke="#eff4ff" stroke-width="1" x1="30" x2="310" y1="100" y2="100"></line>
                        <text fill="#565f68" font-family="Inter" font-size="9" text-anchor="end" x="25" y="104">1.0%</text>
                        <path d="M40 85 L90 80 L140 68 L190 48 L240 38 L290 32" fill="none" stroke="#1b2a5b" stroke-width="2.5"></path>
                        <circle cx="290" cy="32" fill="#1b2a5b" r="3.5"></circle>
                        <text fill="#565f68" font-family="Inter" font-size="9" text-anchor="middle" x="40" y="115">2023</text>
                        <text fill="#565f68" font-family="Inter" font-size="9" text-anchor="middle" x="140" y="115">2024</text>
                        <text fill="#565f68" font-family="Inter" font-size="9" text-anchor="middle" x="240" y="115">2025</text>
                        <text fill="#021446" font-family="Inter" font-size="9" font-weight="700" text-anchor="middle" x="290" y="115">2026</text>
                    </svg>
                </div>
            </div>
            {{-- GDP Growth --}}
            @php
                $latestGdpQuarter = collect($gdpQuarters)->last();
            @endphp
            <div class="bg-surface-container-lowest rounded-xl p-space-md shadow-xs">
                <div class="flex items-center justify-between mb-space-sm">
                    <div>
                        <div class="font-headline-sm text-headline-sm font-bold text-primary">GDP Growth, YoY %</div>
                        <div class="font-label-micro text-label-micro text-secondary">Real GDP, Constant 2018 Prices</div>
                    </div>
                    <span class="font-label-md text-label-md text-on-tertiary-container font-bold">
                        {{ $latestGdpQuarter ? number_format($latestGdpQuarter['value'], 1).'% ('.$latestGdpQuarter['label'].')' : 'No data yet' }}
                    </span>
                </div>
                @if ($gdpQuarters !== [])
                    <div class="w-full h-36 flex items-end justify-around gap-space-md pt-2 px-space-md">
                        @foreach ($gdpQuarters as $quarter)
                            @php $isLatest = $quarter === $latestGdpQuarter; @endphp
                            <div class="flex-1 flex flex-col items-center gap-1 h-full justify-end">
                                <span class="font-label-micro text-label-micro {{ $isLatest ? 'text-on-tertiary-container' : 'text-primary' }} font-bold">{{ number_format($quarter['value'], 1) }}%</span>
                                <div class="w-full max-w-[44px] {{ $isLatest ? 'bg-primary' : 'bg-surface-container-high' }} rounded-t-md" style="height: {{ $quarter['heightPercent'] }}%;"></div>
                                <span class="font-label-micro text-label-micro {{ $isLatest ? 'text-primary font-bold' : 'text-secondary' }}">{{ $quarter['label'] }}</span>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="w-full h-36 flex items-center justify-center">
                        <span class="font-label-md text-label-md text-secondary">No data yet</span>
                    </div>
                @endif
            </div>
        </div>
    </div>

    {{-- Slide-over drawer --}}
    <div
        x-show="drawerOpen"
        x-transition.opacity
        x-on:click="drawerOpen = false"
        x-on:keydown.escape.window="drawerOpen = false"
        class="fixed inset-0 bg-primary/40 backdrop-blur-xs z-50"
        style="display: none;"
    >
        <div
            x-on:click.stop
            x-show="drawerOpen"
            x-transition:enter="transition ease-out duration-300"
            x-transition:enter-start="translate-x-full"
            x-transition:enter-end="translate-x-0"
            x-transition:leave="transition ease-in duration-200"
            x-transition:leave-start="translate-x-0"
            x-transition:leave-end="translate-x-full"
            class="fixed right-0 top-0 bottom-0 w-full max-w-md bg-surface-container-lowest shadow-2xl p-space-xl flex flex-col justify-between"
        >
            <div>
                <div class="flex items-center justify-between mb-space-lg">
                    <div>
                        <div class="font-label-micro text-label-micro uppercase tracking-wider text-secondary">Historical Fixing Breakdown</div>
                        <h3 class="font-headline-lg text-headline-lg font-bold text-primary" x-text="drawerTitle"></h3>
                    </div>
                    <button type="button" class="p-1 rounded-lg hover:bg-surface-container-high text-on-surface-variant transition-colors" x-on:click="drawerOpen = false">
                        <span class="material-symbols-outlined text-[24px]">close</span>
                    </button>
                </div>
                <div class="bg-surface-container-low rounded-xl p-space-md mb-space-lg">
                    <div class="font-label-micro text-label-micro text-secondary uppercase">Current Market Reference</div>
                    <div class="flex items-baseline justify-between mt-1">
                        <div class="font-metric-xl text-metric-xl font-bold text-primary" x-text="drawerValue"></div>
                        <div class="font-label-md text-label-md font-bold text-on-tertiary-container" x-text="drawerDelta"></div>
                    </div>
                    <div class="font-label-md text-label-md text-secondary mt-1" x-text="drawerMeta"></div>
                </div>
                <h4 class="font-label-micro text-label-micro uppercase tracking-wider text-secondary font-bold mb-space-sm">Last 5 Session Fixings</h4>
                <div class="space-y-space-xs mb-space-lg">
                    <div class="flex items-center justify-between p-2 rounded-lg bg-surface-container-low font-label-md text-label-md">
                        <span class="font-semibold text-primary">Aug 25, 2026</span>
                        <span class="font-bold text-primary">61.7350</span>
                        <span class="text-on-tertiary-container font-semibold">+0.01%</span>
                    </div>
                    <div class="flex items-center justify-between p-2 rounded-lg bg-surface-container-lowest font-label-md text-label-md">
                        <span class="font-semibold text-primary">Aug 22, 2026</span>
                        <span class="font-bold text-primary">61.7450</span>
                        <span class="text-error font-semibold">-0.12%</span>
                    </div>
                    <div class="flex items-center justify-between p-2 rounded-lg bg-surface-container-lowest font-label-md text-label-md">
                        <span class="font-semibold text-primary">Aug 21, 2026</span>
                        <span class="font-bold text-primary">61.8200</span>
                        <span class="text-on-tertiary-container font-semibold">+0.08%</span>
                    </div>
                    <div class="flex items-center justify-between p-2 rounded-lg bg-surface-container-lowest font-label-md text-label-md">
                        <span class="font-semibold text-primary">Aug 20, 2026</span>
                        <span class="font-bold text-primary">61.7700</span>
                        <span class="text-error font-semibold">-0.05%</span>
                    </div>
                    <div class="flex items-center justify-between p-2 rounded-lg bg-surface-container-lowest font-label-md text-label-md">
                        <span class="font-semibold text-primary">Aug 19, 2026</span>
                        <span class="font-bold text-primary">61.8010</span>
                        <span class="text-on-tertiary-container font-semibold">+0.14%</span>
                    </div>
                </div>
                <div class="bg-surface-container-high/40 rounded-xl p-space-md">
                    <div class="font-label-micro text-label-micro text-primary font-bold uppercase mb-1">30-Day Metric Spread</div>
                    <div class="grid grid-cols-2 gap-2 text-label-md font-label-md">
                        <div>
                            <span class="text-secondary">Std Deviation:</span>
                            <div class="font-bold text-primary">0.142 pts</div>
                        </div>
                        <div>
                            <span class="text-secondary">30D Rolling Vol:</span>
                            <div class="font-bold text-primary">3.85%</div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="pt-space-md space-y-2">
                <button type="button" class="w-full flex items-center justify-center gap-space-xs py-2.5 rounded-lg bg-primary text-on-primary font-label-md text-label-md font-semibold hover:opacity-95 transition-opacity shadow-sm">
                    <span class="material-symbols-outlined text-[18px]">file_download</span>
                    Download CSV Series (.csv)
                </button>
                <button type="button" class="w-full py-2 rounded-lg bg-surface-container text-on-surface-variant font-label-md text-label-md hover:bg-surface-container-high transition-colors" x-on:click="drawerOpen = false">
                    Dismiss
                </button>
            </div>
        </div>
    </div>

    {{-- Walkthroughs: Guide (what each part means) and Technical (where each number comes from) --}}
    <div x-data="dashboardTour(@js($tour['guide']), @js($tour['technical']))" x-cloak>
        <template x-teleport="body">
            <div>
                <div x-show="!active" class="fixed bottom-6 right-6 z-[70] flex items-center gap-3">
                    <button type="button" x-on:click="start('technical')" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-full bg-surface-container-lowest text-primary font-label-md text-label-md font-semibold shadow-lg hover:shadow-xl transition-shadow">
                        <span class="material-symbols-outlined text-[18px]">code</span> Technical
                    </button>
                    <button type="button" x-on:click="start('guide')" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-full bg-primary text-on-primary font-label-md text-label-md font-semibold shadow-lg hover:shadow-xl transition-shadow">
                        <span class="material-symbols-outlined text-[18px]">lightbulb</span> Guide
                    </button>
                </div>

                <div x-show="active" class="fixed inset-0 z-[80]" x-on:keydown.escape.window="stop()" x-on:keydown.arrow-right.window="next()" x-on:keydown.arrow-left.window="back()" x-on:resize.window="reposition()" x-on:scroll.window="reposition()">
                    <div x-show="!rect" class="fixed inset-0 bg-white/70 pointer-events-none"></div>
                    <div class="fixed rounded-2xl pointer-events-none transition-all duration-200" :style="highlightStyle"></div>

                    <div x-ref="pop" class="fixed w-[min(22rem,calc(100vw-2rem))] max-h-[70vh] overflow-y-auto bg-surface-container-lowest rounded-2xl shadow-xl p-space-lg" :style="popStyle">
                        <div class="font-label-micro text-label-micro font-bold uppercase tracking-wider text-primary" x-text="(mode === 'technical' ? 'Technical' : 'Guide') + ' · ' + (index + 1) + ' OF ' + steps.length"></div>
                        <h3 class="font-headline-sm text-headline-sm font-bold text-primary mt-1" x-text="step?.title"></h3>
                        <p class="font-label-md text-label-md text-on-surface-variant mt-space-xs" x-text="step?.body"></p>

                        <template x-if="mode === 'technical' && step">
                            <div class="mt-space-sm space-y-space-xs">
                                <template x-if="step.static">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded bg-surface-container-high font-label-micro text-label-micro font-bold uppercase text-outline">Static · no data source yet</span>
                                </template>
                                <template x-for="src in step.sources" :key="src.url ?? src.name">
                                    <div class="rounded-xl bg-surface-container-low p-space-sm">
                                        <div class="font-label-md text-label-md font-semibold text-primary" x-text="src.name"></div>
                                        <div class="font-label-micro text-label-micro text-secondary" x-text="src.method + ' · last run: ' + src.lastRun"></div>
                                        <template x-if="src.url">
                                            <a :href="src.url" target="_blank" rel="noopener" class="font-label-micro text-label-micro text-primary underline break-all" x-text="src.url"></a>
                                        </template>
                                    </div>
                                </template>
                                <p x-show="step.note" class="font-label-micro text-label-micro text-on-surface-variant" x-text="step.note"></p>
                            </div>
                        </template>

                        <div class="flex items-center justify-between gap-space-sm mt-space-lg">
                            <button type="button" x-on:click="stop()" class="font-label-md text-label-md text-secondary underline">End tour</button>
                            <div class="flex items-center gap-2">
                                <button type="button" x-on:click="back()" x-bind:disabled="index === 0" x-bind:class="index === 0 ? 'opacity-40 cursor-not-allowed' : ''" class="px-4 py-2 rounded-lg bg-surface-container-high text-primary font-label-md text-label-md font-semibold">Back</button>
                                <button type="button" x-on:click="next()" class="px-4 py-2 rounded-lg bg-primary text-on-primary font-label-md text-label-md font-semibold" x-text="index >= steps.length - 1 ? 'Finish' : 'Next'"></button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </template>
    </div>
</div>

<script>
    window.dashboardTour ??= (guide, technical) => ({
        active: false,
        mode: 'guide',
        index: 0,
        el: null,
        rect: null,
        popStyle: '',
        get steps() {
            return this.mode === 'technical' ? technical : guide;
        },
        get step() {
            return this.steps[this.index] ?? null;
        },
        get highlightStyle() {
            if (!this.rect) {
                return 'display: none;';
            }
            return `top: ${this.rect.top}px; left: ${this.rect.left}px; width: ${this.rect.width}px; height: ${this.rect.height}px; box-shadow: 0 0 0 9999px rgba(255, 255, 255, 0.7); outline: 2px solid #1b2a5b; outline-offset: 2px;`;
        },
        start(mode) {
            this.mode = mode;
            this.index = 0;
            this.active = true;
            this.$nextTick(() => this.focusStep());
        },
        stop() {
            if (!this.active) {
                return;
            }
            this.active = false;
            this.el = null;
            this.rect = null;
        },
        next() {
            if (!this.active) {
                return;
            }
            if (this.index >= this.steps.length - 1) {
                this.stop();
                return;
            }
            this.index++;
            this.focusStep();
        },
        back() {
            if (!this.active || this.index === 0) {
                return;
            }
            this.index--;
            this.focusStep();
        },
        target(step) {
            return step ? document.querySelector(`[data-tour="${step.target}"]`) : null;
        },
        focusStep() {
            while (this.index < this.steps.length && !this.target(this.steps[this.index])) {
                this.index++;
            }
            if (this.index >= this.steps.length) {
                this.stop();
                return;
            }
            this.el = this.target(this.step);
            this.el.scrollIntoView({ block: 'center', behavior: 'smooth' });
            setTimeout(() => this.reposition(), 350);
        },
        reposition() {
            if (!this.active || !this.el) {
                return;
            }
            const r = this.el.getBoundingClientRect();
            this.rect = { top: r.top - 8, left: r.left - 8, width: r.width + 16, height: r.height + 16 };

            const pop = this.$refs.pop;
            const popHeight = Math.min(pop ? pop.offsetHeight : 260, window.innerHeight * 0.7);
            const popWidth = Math.min(352, window.innerWidth - 32);
            let top = r.bottom + 20;
            if (top + popHeight > window.innerHeight - 16) {
                top = Math.max(16, r.top - popHeight - 20);
            }
            const left = Math.min(Math.max(16, r.left), window.innerWidth - popWidth - 16);
            this.popStyle = `top: ${top}px; left: ${left}px;`;
        },
    });
</script>
