<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum ScraperRunStatus: string implements HasColor, HasLabel
{
    case Pending = 'pending';
    case Success = 'success';
    case Failed = 'failed';
    case Stale = 'stale';

    public function getLabel(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Success => 'Success',
            self::Failed => 'Failed',
            self::Stale => 'Stale',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Pending => 'gray',
            self::Success => 'success',
            self::Failed => 'danger',
            self::Stale => 'warning',
        };
    }
}
