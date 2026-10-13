<?php

namespace App\Models;

use App\Enums\ScraperRunStatus;
use Database\Factories\ScraperRunFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A single attempt to pull fresh values for one data source - one row
 * per run of `market:pull-eod` per source, regardless of outcome.
 */
#[Fillable([
    'data_source_id',
    'started_at',
    'finished_at',
    'status',
    'http_status',
    'records_upserted',
    'error',
    'raw_snapshot_path',
])]
class ScraperRun extends Model
{
    /** @use HasFactory<ScraperRunFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'status' => ScraperRunStatus::class,
            'records_upserted' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<DataSource, $this>
     */
    public function dataSource(): BelongsTo
    {
        return $this->belongsTo(DataSource::class);
    }

    /**
     * @return HasMany<IndicatorValue, $this>
     */
    public function indicatorValues(): HasMany
    {
        return $this->hasMany(IndicatorValue::class);
    }

    /**
     * @return HasMany<CurvePoint, $this>
     */
    public function curvePoints(): HasMany
    {
        return $this->hasMany(CurvePoint::class);
    }
}
