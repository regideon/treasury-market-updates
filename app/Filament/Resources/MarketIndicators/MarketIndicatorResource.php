<?php

namespace App\Filament\Resources\MarketIndicators;

use App\Filament\Resources\MarketIndicators\Pages\CreateMarketIndicator;
use App\Filament\Resources\MarketIndicators\Pages\EditMarketIndicator;
use App\Filament\Resources\MarketIndicators\Pages\ListMarketIndicators;
use App\Filament\Resources\MarketIndicators\RelationManagers\CurvePointsRelationManager;
use App\Filament\Resources\MarketIndicators\RelationManagers\ValuesRelationManager;
use App\Filament\Resources\MarketIndicators\Schemas\MarketIndicatorForm;
use App\Filament\Resources\MarketIndicators\Tables\MarketIndicatorsTable;
use App\Models\MarketIndicator;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class MarketIndicatorResource extends Resource
{
    protected static ?string $model = MarketIndicator::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static string|UnitEnum|null $navigationGroup = 'Master Data';

    protected static ?string $navigationLabel = 'Market Indicators';

    protected static ?string $recordTitleAttribute = 'label';

    public static function form(Schema $schema): Schema
    {
        return MarketIndicatorForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return MarketIndicatorsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            ValuesRelationManager::class,
            CurvePointsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMarketIndicators::route('/'),
            'create' => CreateMarketIndicator::route('/create'),
            'edit' => EditMarketIndicator::route('/{record}/edit'),
        ];
    }
}
