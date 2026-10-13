<?php

namespace App\Models;

use App\Enums\DashboardCategory;
use App\Enums\DataSourceMethod;
use Database\Factories\DataSourceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * A registered source of market data (e.g. "BSP USD/PHP file",
 * "PSE Edge", "PDS PHP BVAL") and how we obtain it.
 */
#[Fillable(['name', 'slug', 'category', 'method', 'url', 'description', 'schedule_cron', 'is_active', 'config'])]
class DataSource extends Model
{
    /** @use HasFactory<DataSourceFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'category' => DashboardCategory::class,
            'method' => DataSourceMethod::class,
            'is_active' => 'boolean',
            'config' => 'array',
        ];
    }

    /**
     * @return HasMany<MarketIndicator, $this>
     */
    public function indicators(): HasMany
    {
        return $this->hasMany(MarketIndicator::class);
    }

    /**
     * @return HasMany<ScraperRun, $this>
     */
    public function scraperRuns(): HasMany
    {
        return $this->hasMany(ScraperRun::class);
    }

    /**
     * @return HasOne<ScraperRun, $this>
     */
    public function latestRun(): HasOne
    {
        return $this->hasOne(ScraperRun::class)->latestOfMany('started_at');
    }
}
