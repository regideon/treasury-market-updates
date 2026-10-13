<?php

namespace App\Models;

use Database\Factories\IndicatorValueFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * One daily (end-of-day) reading of a market indicator.
 */
#[Fillable([
    'market_indicator_id',
    'scraper_run_id',
    'value',
    'value_text',
    'as_of_date',
    'as_of_time',
    'period_label',
    'source_note',
])]
class IndicatorValue extends Model
{
    /** @use HasFactory<IndicatorValueFactory> */
    use HasFactory, LogsActivity;

    /**
     * Who changed which reading, and when - covers both automated
     * fetches (no causer) and manual entries via the admin (causer is
     * the acting user), so a wrong value can always be traced back.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['value', 'value_text', 'as_of_date', 'period_label', 'source_note'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'value' => 'decimal:6',
            'as_of_date' => 'date:Y-m-d',
        ];
    }

    /**
     * @return BelongsTo<MarketIndicator, $this>
     */
    public function indicator(): BelongsTo
    {
        return $this->belongsTo(MarketIndicator::class, 'market_indicator_id');
    }

    /**
     * @return BelongsTo<ScraperRun, $this>
     */
    public function scraperRun(): BelongsTo
    {
        return $this->belongsTo(ScraperRun::class);
    }

    /**
     * Percentage change versus a previous reading, or null if there's
     * nothing to compare against (e.g. the first-ever value).
     */
    public function percentChangeFrom(?self $previous): ?float
    {
        if (! $previous || $previous->value === null || (float) $previous->value === 0.0 || $this->value === null) {
            return null;
        }

        return ((float) $this->value - (float) $previous->value) / (float) $previous->value * 100;
    }
}
