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
 * Reads LME Lead's daily Cash-Settlement price, 3-month price and
 * warehouse stock from Westmetall's public market-data table
 * (`westmetall.com/en/markdaten.php?action=table&field=LME_Pb_cash`) -
 * one row per trading day, newest first, for the current year.
 *
 * This is NOT the LME's own "Official Prices" bid/offer curve (Cash,
 * 3-month, Dec-27, Dec-28, Dec-29...): the LME site itself 403s from
 * here and its data is licensed. Westmetall only republishes the Cash
 * and 3-month settlement prices, so the forward-curve table stays a
 * manual-entry item. Whether redistributing LME-sourced prices this
 * way is acceptable for Ramcar is a licensing question for the business
 * - see docs/MODULES.md.
 *
 * `fetch()` returns the latest trading day only (the daily run);
 * `fetchHistory()` returns every row on the page, for the price chart
 * and a one-off backfill (`market:backfill-lme-lead`).
 */
class WestmetallLeadFetcher implements SourceFetcher
{
    public function fetch(DataSource $dataSource): FetchResult
    {
        $rows = $this->readRows($dataSource);

        return $this->toResult([$rows['rows'][0]], $rows['body'], $rows['status']);
    }

    public function fetchHistory(DataSource $dataSource): FetchResult
    {
        $rows = $this->readRows($dataSource);

        return $this->toResult($rows['rows'], $rows['body'], $rows['status']);
    }

    /**
     * @param  list<array{date: Carbon, cash: float, three_month: float, stock: float}>  $rows
     */
    private function toResult(array $rows, string $body, int $status): FetchResult
    {
        $values = [];

        foreach ($rows as $row) {
            $date = $row['date']->toDateString();
            $values[] = ['code' => 'lme_lead_cash', 'as_of_date' => $date, 'value' => $row['cash']];
            $values[] = ['code' => 'lme_lead_3m', 'as_of_date' => $date, 'value' => $row['three_month']];
            $values[] = ['code' => 'lme_lead_stock', 'as_of_date' => $date, 'value' => $row['stock']];
        }

        return new FetchResult(values: $values, rawSnapshot: $body, httpStatus: $status);
    }

    /**
     * @return array{rows: non-empty-list<array{date: Carbon, cash: float, three_month: float, stock: float}>, body: string, status: int}
     */
    private function readRows(DataSource $dataSource): array
    {
        if (! $dataSource->url) {
            throw new RuntimeException("Data source [{$dataSource->slug}] has no URL configured.");
        }

        $response = Http::withHeaders(['User-Agent' => 'Mozilla/5.0'])
            ->get($dataSource->url)
            ->throw();

        $document = new DOMDocument;
        libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="UTF-8">'.$response->body());
        libxml_clear_errors();

        $rows = [];

        foreach ((new DOMXPath($document))->query('//table//tr[td]') as $tr) {
            $cells = [];

            foreach ($tr->getElementsByTagName('td') as $td) {
                $cells[] = trim($td->textContent);
            }

            // e.g. "18. September 2026" - skips any non-data row.
            if (count($cells) < 4 || ! preg_match('/^\d{1,2}\.\s+[A-Za-z]+\s+\d{4}$/', $cells[0])) {
                continue;
            }

            $rows[] = [
                'date' => Carbon::createFromFormat('j. F Y', $cells[0])->startOfDay(),
                'cash' => $this->toFloat($cells[1]),
                'three_month' => $this->toFloat($cells[2]),
                'stock' => $this->toFloat($cells[3]),
            ];
        }

        if ($rows === []) {
            throw new RuntimeException('Could not find any LME Lead rows in the Westmetall page.');
        }

        return ['rows' => $rows, 'body' => $response->body(), 'status' => $response->status()];
    }

    private function toFloat(string $text): float
    {
        return (float) str_replace(',', '', $text);
    }
}
