<?php

namespace App\Models;

use Database\Factories\CurvePointFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One point on a term-structure/curve series for a given date (e.g. the
 * "10Y" point of the PHP BVAL yield curve, or the "3-month" point of the
 * LME Lead price curve).
 */
#[Fillable([
    'market_indicator_id',
    'scraper_run_id',
    'as_of_date',
    'point_key',
    'point_label',
    'value',
    'bid',
    'offer',
    'sort_order',
])]
class CurvePoint extends Model
{
    /** @use HasFactory<CurvePointFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'as_of_date' => 'date:Y-m-d',
            'value' => 'decimal:6',
            'bid' => 'decimal:6',
            'offer' => 'decimal:6',
            'sort_order' => 'integer',
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
}
