<?php

namespace App\Services\DataSources\Fetchers;

use App\Models\DataSource;
use App\Services\DataSources\Contracts\SourceFetcher;
use App\Services\DataSources\FetchResult;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use RuntimeException;

/**
 * Reads BSP's Daily Reference Exchange Rate Bulletin
 * (bsp.gov.ph/Statistics/RERB/RERB.xlsx) - a small, same-day file BSP
 * republishes under this fixed URL every banking day, as opposed to
 * `pesodollar.xlsx` (a bulk historical archive that lags real time and
 * is a better fit for the Module 3 backfill instead).
 *
 * Layout: the bulletin date sits in cell A4 ("16 September 2026"); each
 * currency is one row with a SYMBOL column and a "PHIL.PESO EQUIVALENT"
 * column further along the same row. We locate both by header text
 * rather than a hardcoded column letter, so a harmless column reorder
 * on BSP's end doesn't silently break this.
 */
class BspUsdPhpFetcher implements SourceFetcher
{
    private const string INDICATOR_CODE = 'usdphp_spot';

    public function fetch(DataSource $dataSource): FetchResult
    {
        if (! $dataSource->url) {
            throw new RuntimeException("Data source [{$dataSource->slug}] has no URL configured.");
        }

        $response = Http::withHeaders(['User-Agent' => 'Mozilla/5.0'])
            ->get($dataSource->url)
            ->throw();

        $body = $response->body();
        $sheet = $this->loadFirstSheet($body);

        $asOfDate = $this->readBulletinDate($sheet);
        $rate = $this->readUsdToPhpRate($sheet);

        return new FetchResult(
            values: [
                [
                    'code' => self::INDICATOR_CODE,
                    'as_of_date' => $asOfDate,
                    'value' => $rate,
                ],
            ],
            rawSnapshot: $body,
            httpStatus: $response->status(),
        );
    }

    private function loadFirstSheet(string $xlsxBytes): Worksheet
    {
        $tempPath = tempnam(sys_get_temp_dir(), 'bsp-rerb-').'.xlsx';

        try {
            file_put_contents($tempPath, $xlsxBytes);

            return IOFactory::load($tempPath)->getActiveSheet();
        } finally {
            @unlink($tempPath);
        }
    }

    private function readBulletinDate(Worksheet $sheet): string
    {
        // The date is the first cell (any row near the top) that looks
        // like a full "D Month YYYY" string rather than a header label.
        foreach ($sheet->getRowIterator(1, 8) as $row) {
            foreach ($row->getCellIterator() as $cell) {
                $text = trim((string) $cell->getFormattedValue());

                if ($text !== '' && preg_match('/^\d{1,2}\s+[A-Za-z]+\s+\d{4}$/', $text)) {
                    return Carbon::parse($text)->toDateString();
                }
            }
        }

        throw new RuntimeException('Could not find the bulletin date in the RERB file.');
    }

    private function readUsdToPhpRate(Worksheet $sheet): float
    {
        $highestRow = $sheet->getHighestDataRow();
        $highestColumn = $sheet->getHighestDataColumn();

        $symbolColumn = null;
        $pesoColumn = null;

        // Header text is spread across a couple of rows ("PHIL.PESO" /
        // "EQUIVALENT" stacked), so scan a small header band for each
        // column's meaning instead of assuming fixed letters.
        foreach ($sheet->getRowIterator(1, 8) as $row) {
            foreach ($row->getCellIterator('A', $highestColumn) as $cell) {
                $text = strtoupper(trim((string) $cell->getFormattedValue()));

                if ($text === 'SYMBOL') {
                    $symbolColumn = $cell->getColumn();
                }

                if (str_contains($text, 'PESO')) {
                    $pesoColumn = $cell->getColumn();
                }
            }
        }

        if (! $symbolColumn || ! $pesoColumn) {
            throw new RuntimeException('Could not find the SYMBOL / PHIL.PESO EQUIVALENT columns in the RERB file.');
        }

        for ($rowNumber = 1; $rowNumber <= $highestRow; $rowNumber++) {
            $symbol = trim((string) $sheet->getCell("{$symbolColumn}{$rowNumber}")->getFormattedValue());

            if (strtoupper($symbol) !== 'USD') {
                continue;
            }

            $rawValue = trim((string) $sheet->getCell("{$pesoColumn}{$rowNumber}")->getFormattedValue());

            if ($rawValue === '' || ! is_numeric($rawValue)) {
                throw new RuntimeException("Found the USD row but its PHIL.PESO EQUIVALENT value ([{$rawValue}]) isn't numeric.");
            }

            return (float) $rawValue;
        }

        throw new RuntimeException('Could not find a USD row in the RERB file.');
    }
}
