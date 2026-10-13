<?php

namespace App\Services\DataSources;

use App\Models\MarketIndicator;
use App\Services\DataSources\Contracts\SourceFetcher;

/**
 * What a {@see SourceFetcher} hands
 * back after a single fetch attempt - the raw pieces the pipeline needs
 * to upsert into `indicator_values` / `curve_points`, matched to their
 * {@see MarketIndicator} by `code`.
 *
 * @phpstan-type ValueRow array{code: string, as_of_date: string, value?: float|null, value_text?: string|null, as_of_time?: string|null, period_label?: string|null, source_note?: string|null}
 * @phpstan-type CurvePointRow array{code: string, as_of_date: string, point_key: string, point_label?: string|null, value?: float|null, bid?: float|null, offer?: float|null, sort_order?: int}
 */
final class FetchResult
{
    /**
     * @param  array<int, array{code: string, as_of_date: string, value?: float|null, value_text?: string|null, as_of_time?: string|null, period_label?: string|null, source_note?: string|null}>  $values
     * @param  array<int, array{code: string, as_of_date: string, point_key: string, point_label?: string|null, value?: float|null, bid?: float|null, offer?: float|null, sort_order?: int}>  $curvePoints
     */
    public function __construct(
        public readonly array $values = [],
        public readonly array $curvePoints = [],
        public readonly ?string $rawSnapshot = null,
        public readonly ?int $httpStatus = null,
    ) {}
}
