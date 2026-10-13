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
 * Reads the latest banking day's USD/PHP fixing summary from BAP's
 * (Bankers Association of the Philippines) FX historical page - a
 * stable, parameterized HTML table
 * (`fxhistorical.html?base=USD&quote=PHP&year=Y&month=M`), not an
 * arbitrary page being scraped blind: the URL is fixed, and the markup
 * is a single well-formed `<table id="ratetypeTbl">` with one row per
 * banking day. This fetcher always takes the last row of the current
 * month's table.
 */
class BapFxSummaryFetcher implements SourceFetcher
{
    private const string CODE_OPEN_TIME = 'bap_usdphp_open_time';

    private const string CODE_OPEN = 'bap_usdphp_open';

    private const string CODE_HIGH = 'bap_usdphp_high';

    private const string CODE_LOW = 'bap_usdphp_low';

    private const string CODE_CLOSE = 'bap_usdphp_close';

    private const string CODE_AM_WEIGHTED_AVERAGE = 'bap_usdphp_am_wa';

    private const string CODE_AM_VOLUME = 'bap_usdphp_am_volume';

    private const string CODE_PM_WEIGHTED_AVERAGE = 'bap_usdphp_pm_wa';

    private const string CODE_PM_VOLUME = 'bap_usdphp_pm_volume';

    private const string CODE_WEIGHTED_AVERAGE = 'bap_usdphp_wa';

    private const string CODE_TOTAL_VOLUME = 'bap_usdphp_total_volume';

    private const string CODE_SETTLEMENT_RATE = 'bap_usdphp_settlement';

    public function fetch(DataSource $dataSource): FetchResult
    {
        if (! $dataSource->url) {
            throw new RuntimeException("Data source [{$dataSource->slug}] has no URL configured.");
        }

        $now = Carbon::now();
        $url = $dataSource->url.'?base=USD&quote=PHP&year='.$now->year.'&month='.$now->month;

        $response = Http::withHeaders(['User-Agent' => 'Mozilla/5.0'])
            ->get($url)
            ->throw();

        $row = $this->latestRow($response->body());

        return new FetchResult(
            values: [
                ['code' => self::CODE_OPEN_TIME, 'as_of_date' => $row['date'], 'value_text' => $row['time']],
                ['code' => self::CODE_OPEN, 'as_of_date' => $row['date'], 'value' => $row['open']],
                ['code' => self::CODE_HIGH, 'as_of_date' => $row['date'], 'value' => $row['high']],
                ['code' => self::CODE_LOW, 'as_of_date' => $row['date'], 'value' => $row['low']],
                ['code' => self::CODE_CLOSE, 'as_of_date' => $row['date'], 'value' => $row['close']],
                ['code' => self::CODE_AM_WEIGHTED_AVERAGE, 'as_of_date' => $row['date'], 'value' => $row['am_wa']],
                ['code' => self::CODE_AM_VOLUME, 'as_of_date' => $row['date'], 'value' => $row['am_volume']],
                ['code' => self::CODE_PM_WEIGHTED_AVERAGE, 'as_of_date' => $row['date'], 'value' => $row['pm_wa']],
                ['code' => self::CODE_PM_VOLUME, 'as_of_date' => $row['date'], 'value' => $row['pm_volume']],
                ['code' => self::CODE_WEIGHTED_AVERAGE, 'as_of_date' => $row['date'], 'value' => $row['wa']],
                ['code' => self::CODE_TOTAL_VOLUME, 'as_of_date' => $row['date'], 'value' => $row['total_volume']],
                ['code' => self::CODE_SETTLEMENT_RATE, 'as_of_date' => $row['date'], 'value' => $row['settlement']],
            ],
            rawSnapshot: $response->body(),
            httpStatus: $response->status(),
        );
    }

    /**
     * @return array{date: string, time: string, open: float, high: float, low: float, close: float, am_wa: float, am_volume: float, pm_wa: float, pm_volume: float, wa: float, total_volume: float, settlement: float}
     */
    private function latestRow(string $html): array
    {
        $document = new DOMDocument;
        libxml_use_internal_errors(true);
        $document->loadHTML($html);
        libxml_clear_errors();

        $xpath = new DOMXPath($document);
        $rows = $xpath->query("//table[@id='ratetypeTbl']/tbody/tr");

        if ($rows === false || $rows->length === 0) {
            throw new RuntimeException('Could not find the BAP FX history table in the fetched page.');
        }

        $lastRow = $rows->item($rows->length - 1);
        $cells = [];

        foreach ($xpath->query('td', $lastRow) as $cell) {
            $cells[] = trim($cell->textContent);
        }

        if (count($cells) < 13) {
            throw new RuntimeException('Unexpected number of columns in the BAP FX history table.');
        }

        return [
            'date' => Carbon::parse($cells[0])->toDateString(),
            'time' => $cells[1],
            'open' => (float) $cells[2],
            'high' => (float) $cells[3],
            'low' => (float) $cells[4],
            'close' => (float) $cells[5],
            'am_wa' => (float) $cells[6],
            'am_volume' => (float) $cells[7],
            'pm_wa' => (float) $cells[8],
            'pm_volume' => (float) $cells[9],
            'wa' => (float) $cells[10],
            'total_volume' => (float) $cells[11],
            'settlement' => (float) $cells[12],
        ];
    }
}
