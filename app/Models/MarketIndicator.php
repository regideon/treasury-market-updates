<?php

namespace App\Models;

use App\Enums\DashboardCategory;
use Database\Factories\MarketIndicatorFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * A single tracked metric (e.g. "USD/PHP Spot", "PSEi Index",
 * "ON Lending Rate") belonging to a data source.
 */
#[Fillable(['data_source_id', 'code', 'label', 'unit', 'category', 'display_order', 'metadata'])]
class MarketIndicator extends Model
{
    /** @use HasFactory<MarketIndicatorFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'category' => DashboardCategory::class,
            'display_order' => 'integer',
            'metadata' => 'array',
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
    public function values(): HasMany
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

    /**
     * A real Eloquent relation (not just a query on demand) so it can be
     * eager-loaded with `->with('latestValue')` - fetching the latest
     * reading for many indicators in one query instead of one query per
     * indicator. Used by the dashboard page to avoid an N+1.
     *
     * @return HasOne<IndicatorValue, $this>
     */
    public function latestValue(): HasOne
    {
        return $this->hasOne(IndicatorValue::class)->latestOfMany('as_of_date');
    }

    public function previousValue(?IndicatorValue $before = null): ?IndicatorValue
    {
        $query = $this->values()->latest('as_of_date');

        if ($before) {
            // Compare as a plain date string, not the Carbon instance
            // itself - binding a DateTimeInterface object directly does
            // not reliably translate to a correct date comparison across
            // database drivers.
            $query->where('as_of_date', '<', $before->as_of_date->toDateString());
        } else {
            $query->skip(1);
        }

        return $query->first();
    }
}
