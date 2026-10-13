<?php

namespace App\Filament\Resources\MarketIndicators\Tables;

use App\Enums\DashboardCategory;
use App\Enums\DataSourceMethod;
use App\Models\DataSource;
use App\Models\MarketIndicator;
use App\Services\DataSources\ManualValueRecorder;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class MarketIndicatorsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['dataSource', 'latestValue']))
            ->defaultSort('display_order')
            ->columns([
                TextColumn::make('label')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('code')
                    ->fontFamily('mono')
                    ->color('gray')
                    ->searchable(),
                TextColumn::make('category')
                    ->badge(),
                TextColumn::make('dataSource.name')
                    ->label('Data source')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('dataSource.method')
                    ->label('Method')
                    ->badge()
                    ->color(fn (DataSourceMethod $state): string => match ($state) {
                        DataSourceMethod::Api, DataSourceMethod::File => 'success',
                        DataSourceMethod::Scrape => 'warning',
                        DataSourceMethod::Manual => 'gray',
                    })
                    ->tooltip(fn (MarketIndicator $record): string => static::formatMethodTooltip($record)),
                TextColumn::make('latest_value')
                    ->label('Latest value')
                    ->state(fn (MarketIndicator $record): ?string => static::formatLatestValue($record))
                    ->placeholder('No data yet')
                    ->weight('bold'),
                TextColumn::make('latestValue.as_of_date')
                    ->label('As of')
                    ->date('M j, Y')
                    ->placeholder('—')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('category')
                    ->options(DashboardCategory::class),
                SelectFilter::make('data_source_id')
                    ->label('Data source')
                    ->options(fn () => DataSource::query()->orderBy('name')->pluck('name', 'id')),
            ])
            ->recordActions([
                Action::make('enterValue')
                    ->label('Enter Value')
                    ->icon(Heroicon::OutlinedPencilSquare)
                    ->color('primary')
                    ->modalHeading(fn (MarketIndicator $record): string => "Enter a value for \"{$record->label}\"")
                    ->schema([
                        TextInput::make('value')
                            ->numeric()
                            ->step('any')
                            ->helperText('Leave blank if this reading is text-only (e.g. "****").'),
                        TextInput::make('value_text')
                            ->label('Text value')
                            ->helperText('Only for non-numeric readings, e.g. "****".'),
                        DatePicker::make('as_of_date')
                            ->label('As of date')
                            ->required()
                            ->default(now())
                            ->native(false),
                        TimePicker::make('as_of_time')
                            ->label('Time (optional)')
                            ->seconds(false)
                            ->native(false)
                            ->helperText('Set this when the date alone would leave two entries looking identical.'),
                        TextInput::make('period_label')
                            ->label('Period label (optional)')
                            ->helperText('E.g. "March 2026" for a monthly figure.'),
                        TextInput::make('source_note')
                            ->label('Note (optional)'),
                    ])
                    ->fillForm(fn (MarketIndicator $record): array => [
                        'value' => $record->latestValue?->value,
                        'value_text' => $record->latestValue?->value_text,
                        'as_of_date' => $record->latestValue?->as_of_date ?? now(),
                        'as_of_time' => $record->latestValue?->as_of_time,
                        'period_label' => $record->latestValue?->period_label,
                        'source_note' => $record->latestValue?->source_note,
                    ])
                    ->action(function (array $data, MarketIndicator $record): void {
                        app(ManualValueRecorder::class)->record($record, $data);
                    })
                    ->successNotificationTitle('Value saved'),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    /**
     * Plain-language "what is this source" (data_sources.description)
     * plus its URL, so hovering "Method" tells you more than just
     * "File" or "API" - which file, from where, and what kind of thing
     * it actually is (a live daily bulletin vs. a bulk archive vs. a
     * real API, etc).
     */
    private static function formatMethodTooltip(MarketIndicator $record): string
    {
        $dataSource = $record->dataSource;

        if (! $dataSource) {
            return 'No data source configured.';
        }

        $parts = array_filter([
            $dataSource->description,
            $dataSource->url ? "Source: {$dataSource->url}" : null,
        ]);

        return $parts !== [] ? implode(' — ', $parts) : 'No URL configured - entered manually.';
    }

    private static function formatLatestValue(MarketIndicator $record): ?string
    {
        $latest = $record->latestValue;

        if (! $latest) {
            return null;
        }

        if ($latest->value_text !== null) {
            return $latest->value_text;
        }

        if ($latest->value === null) {
            return null;
        }

        $formatted = rtrim(rtrim(number_format((float) $latest->value, 4, '.', ''), '0'), '.');

        return $record->unit === '%' ? "{$formatted}%" : $formatted;
    }
}
