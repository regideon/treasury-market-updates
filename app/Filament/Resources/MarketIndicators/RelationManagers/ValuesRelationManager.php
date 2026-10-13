<?php

namespace App\Filament\Resources\MarketIndicators\RelationManagers;

use App\Models\IndicatorValue;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Carbon;

/**
 * The full history of readings for one indicator - lets Treasury see
 * (and, if needed, correct) any past entry, whether it was fetched
 * automatically or typed in by hand. The quick "Enter Value" action on
 * the indicator list covers the common case of just adding today's
 * reading; this is for reviewing history or fixing a mistake.
 */
class ValuesRelationManager extends RelationManager
{
    protected static string $relationship = 'values';

    protected static ?string $title = 'Value history';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('value')
                    ->numeric()
                    ->step('any'),
                TextInput::make('value_text')
                    ->label('Text value')
                    ->helperText('Only for non-numeric readings, e.g. "****".'),
                DatePicker::make('as_of_date')
                    ->required()
                    ->native(false),
                TimePicker::make('as_of_time')
                    ->label('Time (optional)')
                    ->seconds(false)
                    ->native(false)
                    ->helperText('Set this when the date alone would leave two entries looking identical.'),
                TextInput::make('period_label')
                    ->label('Period label (optional)')
                    ->helperText('E.g. "March 2026" for a monthly figure. Leave blank to just show the date.'),
                TextInput::make('source_note')
                    ->label('Note (optional)'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('as_of_date')
            ->defaultSort('as_of_date', 'desc')
            ->columns([
                TextColumn::make('as_of_date')
                    ->date('M j, Y')
                    ->sortable(),
                TextColumn::make('value')
                    ->placeholder('—'),
                TextColumn::make('value_text')
                    ->label('Text value')
                    ->placeholder('—'),
                TextColumn::make('period_label')
                    ->label('Period')
                    // period_label is free text and usually left blank,
                    // which used to make every row's "Period" cell show
                    // the same "—" placeholder - not helpful for telling
                    // rows apart. Always show the exact date instead, and
                    // append the time whenever one's been recorded (which
                    // is exactly when two rows would otherwise look the
                    // same at a glance).
                    ->state(function (IndicatorValue $record): string {
                        $date = $record->as_of_date->format('M j, Y');

                        if ($record->as_of_time) {
                            $date .= ', '.Carbon::parse($record->as_of_time)->format('g:i A');
                        }

                        return $record->period_label
                            ? "{$record->period_label} ({$date})"
                            : $date;
                    })
                    ->toggleable(),
                TextColumn::make('source_note')
                    ->label('Note')
                    ->placeholder('—')
                    ->toggleable()
                    ->limit(40),
                TextColumn::make('scraper_run_id')
                    ->label('Source')
                    ->badge()
                    // Deliberately not named $state here: Filament treats
                    // a parameter literally named $state as "inject the
                    // already-resolved state", which for the ->state()
                    // callback itself means calling getState() again -
                    // infinite recursion. $record is safe.
                    ->state(fn (IndicatorValue $record): string => $record->scraper_run_id === null ? 'Manual' : "Run #{$record->scraper_run_id}")
                    ->color(fn (string $state): string => $state === 'Manual' ? 'gray' : 'success'),
            ])
            ->headerActions([
                CreateAction::make(),
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
}
