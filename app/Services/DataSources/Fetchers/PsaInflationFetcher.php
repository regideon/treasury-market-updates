<?php

namespace App\Services\DataSources\Fetchers;

use App\Models\DataSource;
use App\Services\DataSources\Contracts\SourceFetcher;
use App\Services\DataSources\FetchResult;
use Carbon\Carbon;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Reads headline (all-items) year-on-year inflation for the Philippines
 * from PSA's OpenSTAT PxWeb API - a genuine, unauthenticated JSON REST
 * API, not a scrape or a file download. Unlike every BSP source so far,
 * this one is directly reachable from this dev sandbox: no Akamai block.
 *
 * PxWeb tables are queried by GET (metadata: the variables, their codes,
 * and human-readable labels) and POST (actual data, keyed by those
 * codes) against the *same* URL.
 *
 * One non-obvious trap: this table's `"response":{"format":"json-stat2"}`
 * silently returns a single, wrong value regardless of the query -
 * confirmed by comparing it against known-correct figures. The plain
 * `"format":"json"` response works correctly and is what this fetcher
 * uses.
 */
class PsaInflationFetcher implements SourceFetcher
{
    private const string INDICATOR_CODE = 'inflation_rate';

    private const string GEOLOCATION_PHILIPPINES = '0';

    private const string COMMODITY_ALL_ITEMS = '0';

    public function fetch(DataSource $dataSource): FetchResult
    {
        // The last two years covers a January-of-a-new-year edge case
        // where the newest year might not have any published data yet.
        [$rows, $yearVariable, $periodVariable, $response] = $this->queryYears($dataSource, sliceToLastTwo: true);

        $months = $this->mapPublishedMonths($rows, $yearVariable, $periodVariable);

        if ($months === []) {
            throw new RuntimeException('No published inflation figure found in the requested period window.');
        }

        $latest = $months[array_key_last($months)];

        return new FetchResult(
            values: [$this->toValueRow($latest)],
            rawSnapshot: json_encode($rows),
            httpStatus: $response->status(),
        );
    }

    /**
     * Every published month PSA has ever released for this table, not
     * just the latest one - for a one-off historical backfill (see
     * `market:backfill-inflation`), not the daily pipeline run.
     */
    public function fetchHistory(DataSource $dataSource): FetchResult
    {
        [$rows, $yearVariable, $periodVariable, $response] = $this->queryYears($dataSource, sliceToLastTwo: false);

        $months = $this->mapPublishedMonths($rows, $yearVariable, $periodVariable);

        return new FetchResult(
            values: array_map($this->toValueRow(...), $months),
            rawSnapshot: json_encode($rows),
            httpStatus: $response->status(),
        );
    }

    /**
     * @return array{0: array<int, array{key: array<int, string>, values: array<int, string>}>, 1: array{values: array<int, string>, valueTexts: array<int, string>}, 2: array{values: array<int, string>, valueTexts: array<int, string>}, 3: Response}
     */
    private function queryYears(DataSource $dataSource, bool $sliceToLastTwo): array
    {
        if (! $dataSource->url) {
            throw new RuntimeException("Data source [{$dataSource->slug}] has no URL configured.");
        }

        $metadata = Http::withHeaders(['User-Agent' => 'Mozilla/5.0'])
            ->get($dataSource->url)
            ->throw()
            ->json();

        $yearVariable = $this->findVariable($metadata, 'Year');
        $periodVariable = $this->findVariable($metadata, 'Period');

        $yearCodes = $sliceToLastTwo ? array_slice($yearVariable['values'], -2) : $yearVariable['values'];
        // Drop the last period - it's always the "Ave" (annual average)
        // pseudo-period, not a real month.
        $monthPeriodCodes = array_slice($periodVariable['values'], 0, -1);

        $response = Http::withHeaders(['User-Agent' => 'Mozilla/5.0'])
            ->post($dataSource->url, [
                'query' => [
                    ['code' => 'Geolocation', 'selection' => ['filter' => 'item', 'values' => [self::GEOLOCATION_PHILIPPINES]]],
                    ['code' => 'Commodity Description', 'selection' => ['filter' => 'item', 'values' => [self::COMMODITY_ALL_ITEMS]]],
                    ['code' => 'Year', 'selection' => ['filter' => 'item', 'values' => $yearCodes]],
                    ['code' => 'Period', 'selection' => ['filter' => 'item', 'values' => $monthPeriodCodes]],
                ],
                'response' => ['format' => 'json'],
            ])
            ->throw();

        // PSA's data-query response (unlike its metadata response) is
        // prefixed with a UTF-8 BOM, which makes plain json_decode()
        // fail outright unless it's stripped first.
        $body = ltrim($response->body(), "\xEF\xBB\xBF");
        $rows = json_decode($body, true)['data'] ?? [];

        return [$rows, $yearVariable, $periodVariable, $response];
    }

    /**
     * @param  array{date: Carbon, value: float}  $month
     * @return array{code: string, as_of_date: string, value: float, period_label: string}
     */
    private function toValueRow(array $month): array
    {
        return [
            'code' => self::INDICATOR_CODE,
            'as_of_date' => $month['date']->endOfMonth()->toDateString(),
            'value' => $month['value'],
            'period_label' => $month['date']->format('F Y'),
        ];
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @return array{code: string, values: array<int, string>, valueTexts: array<int, string>}
     */
    private function findVariable(array $metadata, string $code): array
    {
        foreach ($metadata['variables'] ?? [] as $variable) {
            if ($variable['code'] === $code) {
                return $variable;
            }
        }

        throw new RuntimeException("Could not find the [{$code}] variable in the PSA table metadata.");
    }

    /**
     * Every row PSA actually published a number for (it prints ".." for
     * a month it hasn't released yet), resolved to a real date and
     * sorted oldest to newest.
     *
     * @param  array<int, array{key: array<int, string>, values: array<int, string>}>  $rows
     * @param  array{values: array<int, string>, valueTexts: array<int, string>}  $yearVariable
     * @param  array{values: array<int, string>, valueTexts: array<int, string>}  $periodVariable
     * @return list<array{date: Carbon, value: float}>
     */
    private function mapPublishedMonths(array $rows, array $yearVariable, array $periodVariable): array
    {
        $months = [];

        foreach ($rows as $row) {
            $rawValue = $row['values'][0] ?? null;

            if ($rawValue === null || ! is_numeric($rawValue)) {
                continue;
            }

            [, , $yearCode, $periodCode] = $row['key'];
            $yearIndex = array_search($yearCode, $yearVariable['values'], true);
            $periodIndex = array_search($periodCode, $periodVariable['values'], true);

            if ($yearIndex === false || $periodIndex === false) {
                continue;
            }

            $year = (int) $yearVariable['valueTexts'][$yearIndex];
            $monthName = $periodVariable['valueTexts'][$periodIndex];
            $rank = ($year * 12) + $periodIndex;

            $months[$rank] = [
                'date' => Carbon::parse("{$monthName} {$year}"),
                'value' => (float) $rawValue,
            ];
        }

        ksort($months);

        return array_values($months);
    }
}
