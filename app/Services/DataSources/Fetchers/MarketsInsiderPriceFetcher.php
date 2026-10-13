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
 * Reads one commodity's current price and change from its Markets
 * Insider page (e.g. `markets.businessinsider.com/commodities/gold-price`)
 * - the same pages the Treasury team's Excel sheet already links to.
 * One class serves several data sources (gold, wheat, Brent): the page
 * is the data source's `url`, and `config['indicator_code']` says which
 * indicator it feeds. Each run writes `<code>` (the price) and
 * `<code>_prev` (the previous close).
 *
 * Non-obvious details:
 * - The page states its own change (`price-section__absolute-value`), so
 *   the previous close is `price - change` - exact, whereas the
 *   "Prev. Close" snapshot box is rounded to 2 decimals.
 * - The timestamp is sometimes "02:15:00 PM EDT 9/18/2026" and
 *   sometimes only "03:19:00 AM" (no date). With no date the price is
 *   treated as today's, rolled back to Friday on a weekend so a Saturday
 *   run doesn't invent a Saturday reading.
 * - The page says "MI Indication", i.e. an indicative price, not an
 *   official fixing; that label is kept as the value's note.
 * - Markets Insider has no public API for this and its chart endpoint
 *   is guarded by anti-forgery tokens, so this reads the page markup.
 *   That is scraping in the plain sense (fragile, and subject to the
 *   site's terms) - see docs/MODULES.md.
 */
class MarketsInsiderPriceFetcher implements SourceFetcher
{
    public function fetch(DataSource $dataSource): FetchResult
    {
        if (! $dataSource->url) {
            throw new RuntimeException("Data source [{$dataSource->slug}] has no URL configured.");
        }

        $code = $dataSource->config['indicator_code'] ?? null;

        if (! $code) {
            throw new RuntimeException("Data source [{$dataSource->slug}] has no config->indicator_code.");
        }

        $response = Http::withHeaders(['User-Agent' => 'Mozilla/5.0'])
            ->get($dataSource->url)
            ->throw();

        $xpath = $this->loadXPath($response->body());

        $price = $this->number($xpath, 'price-section__current-value');
        $change = $this->number($xpath, 'price-section__absolute-value');
        [$asOf, $note] = $this->readTimestamp($xpath);

        $date = $asOf->toDateString();

        return new FetchResult(
            values: [
                ['code' => $code, 'as_of_date' => $date, 'value' => $price, 'source_note' => $note],
                ['code' => $code.'_prev', 'as_of_date' => $date, 'value' => round($price - $change, 4), 'source_note' => $note],
            ],
            rawSnapshot: $response->body(),
            httpStatus: $response->status(),
        );
    }

    private function loadXPath(string $html): DOMXPath
    {
        $document = new DOMDocument;
        libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="UTF-8">'.$html);
        libxml_clear_errors();

        return new DOMXPath($document);
    }

    private function number(DOMXPath $xpath, string $class): float
    {
        $node = $xpath->query("//span[contains(@class, '{$class}')]")->item(0);
        $text = str_replace(',', '', trim((string) $node?->textContent));

        if (! is_numeric($text)) {
            throw new RuntimeException("Could not read [{$class}] from the Markets Insider page.");
        }

        return (float) $text;
    }

    /**
     * @return array{0: Carbon, 1: string}
     */
    private function readTimestamp(DOMXPath $xpath): array
    {
        $spans = $xpath->query("//div[contains(@class, 'price-section__additionals')]/span");
        $text = '';

        foreach ($spans as $span) {
            if (preg_match('/\d{1,2}:\d{2}:\d{2}\s*[AP]M/i', $span->textContent)) {
                $text = trim(preg_replace('/\s+/', ' ', $span->textContent));

                break;
            }
        }

        if ($text === '') {
            throw new RuntimeException('Could not read the price timestamp from the Markets Insider page.');
        }

        if (preg_match('/(\d{1,2}\/\d{1,2}\/\d{4})/', $text, $dateMatch)) {
            $date = Carbon::createFromFormat('n/j/Y', $dateMatch[1])->startOfDay();
        } else {
            $date = Carbon::today();

            if ($date->isWeekend()) {
                $date = $date->previousWeekday();
            }
        }

        return [$date, "MI Indication, {$text}"];
    }
}
