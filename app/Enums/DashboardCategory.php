<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Mirrors the dashboard's section tabs (see dashboard-overview.blade.php),
 * so a data source or indicator's category tells you exactly which part
 * of the dashboard it feeds.
 */
enum DashboardCategory: string implements HasLabel
{
    case Fx = 'fx';
    case Rates = 'rates';
    case Equities = 'equities';
    case Commodities = 'commodities';
    case Macro = 'macro';

    public function getLabel(): string
    {
        return match ($this) {
            self::Fx => 'FX',
            self::Rates => 'Rates',
            self::Equities => 'Equities',
            self::Commodities => 'Commodities',
            self::Macro => 'Macro',
        };
    }
}
