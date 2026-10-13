<?php

namespace App\Services\DataSources\Contracts;

use App\Models\DataSource;
use App\Models\ScraperRun;
use App\Services\DataSources\FetchResult;

/**
 * The contract every source integration implements, whatever method it
 * uses under the hood (API call, file download, HTML scrape). One class
 * per data source; the pipeline doesn't need to know which.
 */
interface SourceFetcher
{
    /**
     * Fetch the latest values for this data source. Should throw on
     * failure (network error, unexpected shape, etc.) rather than
     * return a partial/empty result - the pipeline records the
     * exception on the {@see ScraperRun} for you.
     */
    public function fetch(DataSource $dataSource): FetchResult;
}
