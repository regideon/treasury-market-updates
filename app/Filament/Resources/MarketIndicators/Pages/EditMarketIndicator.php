<?php

namespace App\Filament\Resources\MarketIndicators\Pages;

use App\Filament\Resources\MarketIndicators\MarketIndicatorResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditMarketIndicator extends EditRecord
{
    protected static string $resource = MarketIndicatorResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
