<?php

namespace App\Filament\Resources\MarketIndicators\Pages;

use App\Filament\Resources\MarketIndicators\MarketIndicatorResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListMarketIndicators extends ListRecords
{
    protected static string $resource = MarketIndicatorResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
