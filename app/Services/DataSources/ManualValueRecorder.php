<?php

namespace App\Services\DataSources;

use App\Models\IndicatorValue;
use App\Models\MarketIndicator;
use App\Services\DataSources\Contracts\SourceFetcher;

/**
 * Records a value a person typed in directly, as opposed to one a
 * {@see SourceFetcher} pulled
 * automatically. Used by the "Enter Value" action on
 * MarketIndicatorResource - the one thing every indicator can always
 * fall back to, whether it has no automated source at all (e.g. the
 * ON Reference Rate, LME Lead) or its usual source is temporarily down.
 *
 * Kept as its own class (rather than inline in the Filament action) so
 * it's testable without touching Livewire/Filament at all, and so the
 * upsert rule - same (indicator, as_of_date) key every fetcher uses -
 * lives in exactly one place.
 */
class ManualValueRecorder
{
    /**
     * @param  array{value?: float|string|null, value_text?: string|null, as_of_date: string, as_of_time?: string|null, period_label?: string|null, source_note?: string|null}  $attributes
     */
    public function record(MarketIndicator $indicator, array $attributes): IndicatorValue
    {
        return IndicatorValue::updateOrCreate(
            [
                'market_indicator_id' => $indicator->id,
                'as_of_date' => $attributes['as_of_date'],
            ],
            [
                // A manual entry supersedes whatever run (if any)
                // previously produced this date's value - it's no
                // longer attributable to that fetch.
                'scraper_run_id' => null,
                'value' => $attributes['value'] ?? null,
                'value_text' => $attributes['value_text'] ?? null,
                'as_of_time' => $attributes['as_of_time'] ?? null,
                'period_label' => $attributes['period_label'] ?? null,
                'source_note' => $attributes['source_note'] ?? null,
            ],
        );
    }
}
