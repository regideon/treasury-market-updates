<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * How a data source's values are obtained. Cheapest/most durable first:
 * an official API or a stable file download should always be preferred
 * over scraping a rendered page. `Manual` covers sources with no
 * automated feed at all (e.g. LME, until a licensed feed is in place).
 */
enum DataSourceMethod: string implements HasLabel
{
    case Api = 'api';
    case File = 'file';
    case Scrape = 'scrape';
    case Manual = 'manual';

    public function getLabel(): string
    {
        return match ($this) {
            self::Api => 'API',
            self::File => 'File download',
            self::Scrape => 'Web scraping',
            self::Manual => 'Manual entry',
        };
    }
}
