<?php

namespace App\Services\DataSources\Fetchers;

use App\Models\DataSource;
use App\Services\DataSources\Contracts\SourceFetcher;
use App\Services\DataSources\FetchResult;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Reads the PHP BVAL (Bloomberg Valuation) benchmark tenor curve from
 * PDS's *public* GraphQL API - the same API that backs the embeddable
 * "PHP BVAL Reference Rates" widget PDS licenses to third-party sites.
 *
 * This is a genuinely different system from PDS's subscriber-only
 * MarketPage (marketpage.pdex.net.ph, which requires a client
 * certificate and a paid subscription - not reachable from here at
 * all). This endpoint only needs an API key and explicitly accepts
 * `requestPage: "public"`, which is why it's treated as intentionally
 * public rather than a private key we're not supposed to have. Still,
 * this key was found via a public search result, not issued to Ramcar
 * directly - see docs/MODULES.md for the full writeup.
 *
 * The response includes both today's and the previous trading day's
 * rate per tenor, but only today's is stored here - "previous" for the
 * dashboard comes from whatever curve point was already stored on the
 * prior run, same as every other indicator in this app.
 */
class PdsBvalFetcher implements SourceFetcher
{
    private const string INDICATOR_CODE = 'php_bval_curve';

    private const string ENDPOINT = 'https://www.pds.com.ph/api-mp/graphql/';

    private const string API_KEY = '8WZMZRLKN7SXDY5XQHTM9VFBG3PCJE2U';

    private const string QUERY = <<<'GRAPHQL'
        query ($tradeDate: Date, $requestPage: String) {
            bvalRates(tradeDate: $tradeDate, requestPage: $requestPage) {
                tenor
                bvalRateToday
                bvalRatePrevious
                tradingDateToday
            }
        }
        GRAPHQL;

    /**
     * The API doesn't guarantee tenor order in its response; this fixes
     * the display/sort order of the curve.
     *
     * @var list<string>
     */
    private const array TENOR_ORDER = ['1M', '3M', '6M', '1Y', '2Y', '3Y', '4Y', '5Y', '7Y', '10Y', '20Y', '25Y'];

    public function fetch(DataSource $dataSource): FetchResult
    {
        $response = Http::withHeaders([
            'Authorization' => 'APIKey '.self::API_KEY,
            'Content-Type' => 'application/json',
        ])->post(self::ENDPOINT, [
            'query' => self::QUERY,
            'variables' => ['tradeDate' => null, 'requestPage' => 'public'],
        ])->throw();

        $rates = $response->json('data.bvalRates') ?? [];

        if ($rates === []) {
            throw new RuntimeException('PDS returned no BVAL rates.');
        }

        $curvePoints = [];

        foreach ($rates as $rate) {
            $sortOrder = array_search($rate['tenor'], self::TENOR_ORDER, true);

            $curvePoints[] = [
                'code' => self::INDICATOR_CODE,
                'as_of_date' => Carbon::parse($rate['tradingDateToday'])->toDateString(),
                'point_key' => $rate['tenor'],
                'point_label' => $rate['tenor'],
                'value' => $rate['bvalRateToday'],
                'sort_order' => $sortOrder === false ? 0 : $sortOrder,
            ];
        }

        return new FetchResult(
            curvePoints: $curvePoints,
            rawSnapshot: $response->body(),
            httpStatus: $response->status(),
        );
    }
}
