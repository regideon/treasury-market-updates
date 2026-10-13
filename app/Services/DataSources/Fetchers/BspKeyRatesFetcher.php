<?php

namespace App\Services\DataSources\Fetchers;

use App\Models\DataSource;
use App\Services\DataSources\Contracts\SourceFetcher;
use App\Services\DataSources\FetchResult;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Smalot\PdfParser\Parser;

/**
 * Reads BSP's "Selected Economic and Financial Indicators" PDF
 * (bsp.gov.ph/statistics/keystat/sefi.pdf) for the three BSP Key Rates
 * indicators that document actually publishes as a clean, current,
 * single number:
 *
 *  - ON RRP Rate
 *  - 7-day TDF (WAIR)
 *  - 28-day BSP Securities (WAIR)
 *
 * The other 8 cards on the strip are deliberately NOT sourced here:
 * 56-day BSP Securities / 14-day TDF / 28-day TDF are the same table
 * shape but frequently blank (no recent auction); ON Lending Rate and
 * ON Deposit Rate only have their true current value in a footnote
 * sentence (the printed table column is a monthly average that lags
 * a rate change - e.g. it still read 5.29%/4.29% after BSP's Aug 28
 * move to 5.50%/4.50%); Target RRP Rate and ON Reference Rate aren't
 * in this document in any parseable form. See docs/MODULES.md for the
 * full per-card breakdown.
 *
 * Parsing approach: `getText()` returns the document as plain lines in
 * reading order, each row's label and its numbers usually landing on
 * separate lines. We walk the lines once, tracking which subsection
 * we're in (TDF vs BSP Securities - both contain a same-named
 * "28-day WAIR"/"7-day WAIR" row, so the label text alone is
 * ambiguous), and stop entirely once we reach "Real Interest Rates"
 * (the same three labels repeat there, inflation-adjusted - not what
 * we want).
 */
class BspKeyRatesFetcher implements SourceFetcher
{
    private const string SECTION_TDF = 'tdf';

    private const string SECTION_BSP_SECURITIES = 'bsp_securities';

    /**
     * @var array<int, array{code: string, label: string, section: ?string}>
     */
    private const array TARGETS = [
        ['code' => 'on_rrp_rate', 'label' => 'Overnight Reverse Repurchase (ON RRP)', 'section' => null],
        ['code' => 'tdf_7d', 'label' => '7-day WAIR', 'section' => self::SECTION_TDF],
        ['code' => 'bsp_securities_28d', 'label' => '28-day WAIR', 'section' => self::SECTION_BSP_SECURITIES],
    ];

    public function fetch(DataSource $dataSource): FetchResult
    {
        if (! $dataSource->url) {
            throw new RuntimeException("Data source [{$dataSource->slug}] has no URL configured.");
        }

        $response = Http::withHeaders(['User-Agent' => 'Mozilla/5.0'])
            ->get($dataSource->url)
            ->throw();

        $body = $response->body();
        $text = (new Parser)->parseContent($body)->getText();

        $asOfDate = $this->readDocumentDate($text);
        $lines = explode("\n", $text);

        $values = [];

        foreach ($this->extractRates($lines) as $code => $rate) {
            $values[] = [
                'code' => $code,
                'as_of_date' => $asOfDate,
                'value' => $rate,
            ];
        }

        if ($values === []) {
            throw new RuntimeException('None of the expected BSP Key Rates rows were found - the document layout may have changed.');
        }

        return new FetchResult(
            values: $values,
            rawSnapshot: $body,
            httpStatus: $response->status(),
        );
    }

    private function readDocumentDate(string $text): string
    {
        if (! preg_match('/\b(\d{1,2}-[A-Za-z]{3}-\d{2})\b/', $text, $matches)) {
            throw new RuntimeException('Could not find the document date in the SEFI file.');
        }

        return Carbon::createFromFormat('d-M-y', $matches[1])->toDateString();
    }

    /**
     * @param  list<string>  $lines
     * @return array<string, float>
     */
    private function extractRates(array $lines): array
    {
        $section = null;
        $found = [];

        foreach ($lines as $index => $line) {
            $trimmed = trim($line);

            if (str_contains($trimmed, 'Real Interest Rates')) {
                // Everything from here on is inflation-adjusted - the
                // labels repeat, so we must not keep matching past this.
                break;
            }

            if (str_starts_with($trimmed, 'Term Deposit Facility')) {
                $section = self::SECTION_TDF;
            } elseif (str_starts_with($trimmed, 'BSP Securities')) {
                $section = self::SECTION_BSP_SECURITIES;
            }

            foreach (self::TARGETS as $target) {
                if (isset($found[$target['code']])) {
                    continue;
                }

                if ($target['section'] !== null && $section !== $target['section']) {
                    continue;
                }

                if (! str_contains($trimmed, $target['label'])) {
                    continue;
                }

                $rate = $this->findLatestValueNear($lines, $index);

                if ($rate !== null) {
                    $found[$target['code']] = $rate;
                }
            }
        }

        return $found;
    }

    /**
     * The label and its row of numbers are separate lines (sometimes
     * with a footnote-marker-only line like "^^" in between), so look
     * a few lines ahead for the first one that's a row of numbers, and
     * take the rightmost real number in it - that's the "Latest
     * Available Data" column.
     */
    private function findLatestValueNear(array $lines, int $labelIndex): ?float
    {
        for ($offset = 0; $offset <= 4; $offset++) {
            $line = $lines[$labelIndex + $offset] ?? null;

            if ($line === null) {
                break;
            }

            $tokens = preg_split('/\s+/', trim($line), -1, PREG_SPLIT_NO_EMPTY);
            $numericTokens = array_values(array_filter(
                $tokens,
                fn (string $token): bool => (bool) preg_match('/^-?\d+\.\d+$/', $token),
            ));

            // The rows we target always print 8 columns (7 historical
            // periods + the latest). Anything short of that means the
            // latest figure isn't actually there yet for this row.
            if (count($numericTokens) === 8) {
                return (float) end($numericTokens);
            }
        }

        return null;
    }
}
