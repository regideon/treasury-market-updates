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
 * Reads real (constant 2018 prices) quarterly GDP year-on-year growth
 * for the Philippines from the same PSA OpenSTAT PxWeb system as
 * {@see PsaInflationFetcher} - table `2B/NA/QT/1SUM/0022B5BEXQ2.px`
 * ("Gross National Income and Gross Domestic Product by Expenditure
 * Shares, Growth Rates").
 *
 * Two non-obvious things about this particular table:
 * - Its "Year" dimension is a comparison pair like "2024-2025", not a
 *   single calendar year - the quarter it actually reports on is the
 *   *second* year of the pair (year-variable position N means calendar
 *   year 2001 + N). Confirmed against a well-known real print: the
 *   post-lockdown rebound this table reports as +12.0% lands on
 *   position 20 ("2020-2021"), i.e. Q2 2021 - matching the actual,
 *   widely-reported figure.
 * - "Type of Expenditure" code 16 is "Gross Domestic Product" itself;
 *   the same table also carries its components (household spending,
 *   government spending, exports, etc.) as separate codes we don't
 *   want here.
 */
class PsaGdpGrowthFetcher implements SourceFetcher
{
    private const string INDICATOR_CODE = 'gdp_growth_rate';

    private const string EXPENDITURE_GDP = '16';

    private const string VALUATION_CONSTANT_2018_PRICES = '1';

    /**
     * The calendar year the *first* year-variable position ("0",
     * "2000-2001") actually reports on.
     */
    private const int FIRST_POSITION_CALENDAR_YEAR = 2001;

    public function fetch(DataSource $dataSource): FetchResult
    {
        // The last two year-pairs covers a similar year-boundary edge
        // case to PsaInflationFetcher: the newest pair might not have
        // every quarter published yet.
        [$rows, $yearVariable, $periodVariable, $response] = $this->queryYears($dataSource, sliceToLastTwo: true);

        $quarters = $this->mapPublishedQuarters($rows, $yearVariable, $periodVariable);

        if ($quarters === []) {
            throw new RuntimeException('No published GDP growth figure found in the requested period window.');
        }

        $latest = $quarters[array_key_last($quarters)];

        return new FetchResult(
            values: [$this->toValueRow($latest)],
            rawSnapshot: json_encode($rows),
            httpStatus: $response->status(),
        );
    }

    /**
     * Every published quarter PSA has ever released for this table -
     * for a one-off historical backfill (see `market:backfill-gdp`),
     * not the daily pipeline run.
     */
    public function fetchHistory(DataSource $dataSource): FetchResult
    {
        [$rows, $yearVariable, $periodVariable, $response] = $this->queryYears($dataSource, sliceToLastTwo: false);

        $quarters = $this->mapPublishedQuarters($rows, $yearVariable, $periodVariable);

        return new FetchResult(
            values: array_map($this->toValueRow(...), $quarters),
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

        $response = Http::withHeaders(['User-Agent' => 'Mozilla/5.0'])
            ->post($dataSource->url, [
                'query' => [
                    ['code' => 'Type of Expenditure', 'selection' => ['filter' => 'item', 'values' => [self::EXPENDITURE_GDP]]],
                    ['code' => 'Type of Valuation', 'selection' => ['filter' => 'item', 'values' => [self::VALUATION_CONSTANT_2018_PRICES]]],
                    ['code' => 'Year', 'selection' => ['filter' => 'item', 'values' => $yearCodes]],
                    ['code' => 'Period', 'selection' => ['filter' => 'item', 'values' => $periodVariable['values']]],
                ],
                'response' => ['format' => 'json'],
            ])
            ->throw();

        // Same PxWeb quirk as PsaInflationFetcher: the data-query
        // response is prefixed with a UTF-8 BOM.
        $body = ltrim($response->body(), "\xEF\xBB\xBF");
        $rows = json_decode($body, true)['data'] ?? [];

        return [$rows, $yearVariable, $periodVariable, $response];
    }

    /**
     * @param  array{date: Carbon, value: float}  $quarter
     * @return array{code: string, as_of_date: string, value: float, period_label: string}
     */
    private function toValueRow(array $quarter): array
    {
        return [
            'code' => self::INDICATOR_CODE,
            'as_of_date' => $quarter['date']->toDateString(),
            'value' => $quarter['value'],
            'period_label' => 'Q'.$quarter['date']->quarter.' '.$quarter['date']->year,
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
     * Every row PSA actually published a number for (it prints "." for
     * a quarter it hasn't released yet), resolved to a real date and
     * sorted oldest to newest.
     *
     * @param  array<int, array{key: array<int, string>, values: array<int, string>}>  $rows
     * @param  array{values: array<int, string>, valueTexts: array<int, string>}  $yearVariable
     * @param  array{values: array<int, string>, valueTexts: array<int, string>}  $periodVariable
     * @return list<array{date: Carbon, value: float}>
     */
    private function mapPublishedQuarters(array $rows, array $yearVariable, array $periodVariable): array
    {
        $quarters = [];

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

            $calendarYear = self::FIRST_POSITION_CALENDAR_YEAR + $yearIndex;
            $quarterNumber = $periodIndex + 1;
            $rank = ($calendarYear * 4) + $periodIndex;

            $quarters[$rank] = [
                'date' => Carbon::create($calendarYear, $quarterNumber * 3, 1)->endOfMonth()->startOfDay(),
                'value' => (float) $rawValue,
            ];
        }

        ksort($quarters);

        return array_values($quarters);
    }
}
