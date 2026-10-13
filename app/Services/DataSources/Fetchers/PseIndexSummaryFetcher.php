<?php

namespace App\Services\DataSources\Fetchers;

use App\Models\DataSource;
use App\Services\DataSources\Contracts\SourceFetcher;
use App\Services\DataSources\FetchResult;
use Carbon\Carbon;
use DOMDocument;
use DOMXPath;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Reads the PSEi, All Shares and six sector indices, plus the day's
 * Market Summary (volume, trades, value, advances/declines/unchanged), from PSE Edge's "Index
 * Summary" page (`edge.pse.com.ph/index/form.do`) - a small, fixed-URL,
 * server-rendered page with two plain tables, not blocked from this dev
 * network (unlike www.pse.com.ph's own market pages, which 403).
 *
 * Non-obvious details:
 * - The change columns carry no sign of their own - direction is only
 *   the colour and a trailing ▲/▼ in the %Chg cell, so the sign is read
 *   from that arrow.
 * - Each index gets two indicators, `<code>` (the level) and
 *   `<code>_chg` (PSE's own signed point change), because indicator
 *   values hold one number per (indicator, date) and PSE's change is
 *   authoritative from day one - unlike a "vs yesterday" derived from
 *   our own history, which would be empty until a second day is stored.
 * - The page is live: mid-session it reads "MARKET : RECESS" or "OPEN"
 *   with a partial day's figures. The EOD schedule (18:30) reads the
 *   final "CLOSED" numbers; any earlier run for the same date is simply
 *   overwritten by the upsert.
 * - PSE's footer restricts these materials to "personal, non-commercial
 *   use". Treat this as an interim source until PSE's Data Products
 *   licence question is settled - see docs/MODULES.md.
 */
class PseIndexSummaryFetcher implements SourceFetcher
{
    private const string TIME_FORMAT = 'H:i:s';

    /**
     * PSE's row label => indicator code prefix.
     *
     * @var array<string, string>
     */
    private const array INDEX_CODES = [
        'PSEi' => 'psei',
        'All Shares' => 'pse_all_shares',
        'Financials' => 'pse_financials',
        'Industrial' => 'pse_industrial',
        'Holding Firms' => 'pse_holding_firms',
        'Services' => 'pse_services',
        'Mining and Oil' => 'pse_mining_oil',
        'Property' => 'pse_property',
    ];

    public function fetch(DataSource $dataSource): FetchResult
    {
        if (! $dataSource->url) {
            throw new RuntimeException("Data source [{$dataSource->slug}] has no URL configured.");
        }

        $response = Http::withHeaders(['User-Agent' => 'Mozilla/5.0'])
            ->get($dataSource->url)
            ->throw();

        $xpath = $this->loadXPath($response->body());

        $indexRows = $this->parseIndexRows($xpath);
        [$status, $asOf] = $this->parseMarketHeader($xpath);
        $stats = $this->parseMarketStats($xpath);

        $date = $asOf->toDateString();
        $time = $asOf->format(self::TIME_FORMAT);
        $values = [];

        foreach ($indexRows as $code => $row) {
            $values[] = ['code' => $code, 'as_of_date' => $date, 'as_of_time' => $time, 'value' => $row['value']];
            $values[] = ['code' => $code.'_chg', 'as_of_date' => $date, 'as_of_time' => $time, 'value' => $row['change']];
        }

        $values[] = ['code' => 'pse_total_volume', 'as_of_date' => $date, 'as_of_time' => $time, 'value' => $stats['Total Volume']];
        $values[] = ['code' => 'pse_total_trades', 'as_of_date' => $date, 'as_of_time' => $time, 'value' => $stats['Total Trades']];
        $values[] = ['code' => 'pse_total_value', 'as_of_date' => $date, 'as_of_time' => $time, 'value' => $stats['Total Value']];
        $values[] = ['code' => 'pse_advances', 'as_of_date' => $date, 'as_of_time' => $time, 'value' => $stats['Advances']];
        $values[] = ['code' => 'pse_declines', 'as_of_date' => $date, 'as_of_time' => $time, 'value' => $stats['Declines']];
        $values[] = ['code' => 'pse_unchanged', 'as_of_date' => $date, 'as_of_time' => $time, 'value' => $stats['Unchanged']];
        $values[] = ['code' => 'pse_market_status', 'as_of_date' => $date, 'as_of_time' => $time, 'value_text' => $status];

        return new FetchResult(
            values: $values,
            rawSnapshot: $response->body(),
            httpStatus: $response->status(),
        );
    }

    private function loadXPath(string $html): DOMXPath
    {
        $document = new DOMDocument;
        libxml_use_internal_errors(true);
        // The XML prefix makes libxml read the page as UTF-8, so the
        // ▲/▼ arrows survive as real characters.
        $document->loadHTML('<?xml encoding="UTF-8">'.$html);
        libxml_clear_errors();

        return new DOMXPath($document);
    }

    /**
     * @return array<string, array{value: float, change: float}>
     */
    private function parseIndexRows(DOMXPath $xpath): array
    {
        $rows = $xpath->query("//div[@id='index']//tbody/tr");

        if ($rows === false || $rows->length === 0) {
            throw new RuntimeException('Could not find the PSE Edge index summary table in the fetched page.');
        }

        $parsed = [];

        foreach ($rows as $row) {
            $cells = [];

            foreach ($xpath->query('td', $row) as $cell) {
                $cells[] = trim($cell->textContent);
            }

            if (count($cells) < 4 || ! isset(self::INDEX_CODES[$cells[0]])) {
                continue;
            }

            $direction = match (true) {
                str_contains($cells[3], '▼') => -1,
                str_contains($cells[3], '▲') => 1,
                default => 0,
            };

            $parsed[self::INDEX_CODES[$cells[0]]] = [
                'value' => $this->toFloat($cells[1]),
                'change' => $direction * $this->toFloat($cells[2]),
            ];
        }

        $missing = array_diff(array_values(self::INDEX_CODES), array_keys($parsed));

        if ($missing !== []) {
            throw new RuntimeException('PSE Edge index summary is missing: '.implode(', ', $missing));
        }

        return $parsed;
    }

    /**
     * @return array{0: string, 1: Carbon}
     */
    private function parseMarketHeader(DOMXPath $xpath): array
    {
        $headers = $xpath->query("//div[@id='market']//thead//th");

        $statusText = trim((string) $headers?->item(0)?->textContent);
        $asOfText = trim((string) $headers?->item(1)?->textContent);

        if (! preg_match('/MARKET\s*:\s*(.+)$/i', $statusText, $status) || ! preg_match('/As of\s+(.+)$/i', $asOfText, $asOf)) {
            throw new RuntimeException('Could not read the market status / "As of" header from PSE Edge.');
        }

        return [strtoupper(trim($status[1])), Carbon::parse(trim($asOf[1]))];
    }

    /**
     * @return array<string, float>
     */
    private function parseMarketStats(DOMXPath $xpath): array
    {
        $stats = [];

        foreach ($xpath->query("//div[@id='market']//tbody/tr") as $row) {
            $cells = $xpath->query('td', $row);

            if ($cells->length >= 2) {
                $stats[trim($cells->item(0)->textContent)] = $this->toFloat($cells->item(1)->textContent);
            }
        }

        foreach (['Total Volume', 'Total Trades', 'Total Value', 'Advances', 'Declines', 'Unchanged'] as $required) {
            if (! isset($stats[$required])) {
                throw new RuntimeException("PSE Edge market summary is missing [{$required}].");
            }
        }

        return $stats;
    }

    private function toFloat(string $text): float
    {
        return (float) str_replace(',', '', trim($text));
    }
}
