<?php

namespace App\Filament\Resources\MarketIndicators\Schemas;

use App\Enums\DashboardCategory;
use App\Models\DataSource;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;

class MarketIndicatorForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Grid::make(2)->schema([
                    Select::make('data_source_id')
                        ->label('Data source')
                        ->options(fn () => DataSource::query()->orderBy('name')->pluck('name', 'id'))
                        ->searchable()
                        ->required(),
                    Select::make('category')
                        ->options(DashboardCategory::class)
                        ->required(),
                    TextInput::make('code')
                        ->helperText('Used internally, e.g. "on_lending_rate". Must be unique.')
                        ->required()
                        ->unique(ignoreRecord: true),
                    TextInput::make('label')
                        ->helperText('Shown on the dashboard card, e.g. "ON Lending Rate".')
                        ->required(),
                    TextInput::make('unit')
                        ->helperText('E.g. "%", "PHP", "USD/bbl".'),
                    TextInput::make('display_order')
                        ->numeric()
                        ->default(0),
                ]),
            ]);
    }
}
