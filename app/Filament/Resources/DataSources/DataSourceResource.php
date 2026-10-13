<?php

namespace App\Filament\Resources\DataSources;

use App\Enums\DashboardCategory;
use App\Enums\DataSourceMethod;
use App\Filament\Resources\DataSources\Pages\ManageDataSources;
use App\Models\DataSource;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use UnitEnum;

class DataSourceResource extends Resource
{
    protected static ?string $model = DataSource::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedServerStack;

    protected static string|UnitEnum|null $navigationGroup = 'Master Data';

    protected static ?string $navigationLabel = 'Data Sources';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Grid::make(2)->schema([
                    TextInput::make('name')
                        ->required()
                        ->live(onBlur: true)
                        ->afterStateUpdated(fn (Set $set, ?string $state) => $set('slug', Str::slug($state ?? ''))),
                    TextInput::make('slug')
                        ->required()
                        ->unique(ignoreRecord: true)
                        ->helperText('Used internally, e.g. "bsp-usdphp".'),
                    Select::make('category')
                        ->options(DashboardCategory::class)
                        ->required(),
                    Select::make('method')
                        ->options(DataSourceMethod::class)
                        ->required()
                        ->live()
                        ->helperText('Prefer API or File over Scrape wherever one exists.'),
                    TextInput::make('url')
                        ->label('Source URL')
                        ->url()
                        ->columnSpanFull()
                        ->visible(fn (Get $get): bool => $get('method') !== DataSourceMethod::Manual->value),
                    Textarea::make('description')
                        ->label('What is this, in plain language?')
                        ->rows(2)
                        ->columnSpanFull()
                        ->helperText('E.g. "Small Excel file BSP republishes every banking day." Shown as a tooltip on the Market Indicators page.'),
                    TextInput::make('schedule_cron')
                        ->label('Schedule (cron)')
                        ->default('30 18 * * 1-5')
                        ->helperText('Reference only for now - the EOD command currently runs every active, non-manual source together.')
                        ->visible(fn (Get $get): bool => $get('method') !== DataSourceMethod::Manual->value),
                    Toggle::make('is_active')
                        ->label('Active')
                        ->default(true),
                ]),
                KeyValue::make('config')
                    ->label('Config')
                    ->keyLabel('Key')
                    ->valueLabel('Value')
                    ->helperText('E.g. "fetcher" => the SourceFetcher class for this source.')
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('category')
                    ->badge(),
                TextColumn::make('method')
                    ->badge()
                    ->color(fn (DataSourceMethod $state): string => match ($state) {
                        DataSourceMethod::Api, DataSourceMethod::File => 'success',
                        DataSourceMethod::Scrape => 'warning',
                        DataSourceMethod::Manual => 'gray',
                    }),
                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
                TextColumn::make('indicators_count')
                    ->counts('indicators')
                    ->label('Indicators'),
                TextColumn::make('latestRun.status')
                    ->label('Last run')
                    ->badge()
                    ->placeholder('Never run'),
                TextColumn::make('latestRun.started_at')
                    ->label('Last run at')
                    ->dateTime()
                    ->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('category')->options(DashboardCategory::class),
                SelectFilter::make('method')->options(DataSourceMethod::class),
                TernaryFilter::make('is_active')->label('Active'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageDataSources::route('/'),
        ];
    }
}
