<?php

namespace App\Filament\Resources\MarketIndicators\RelationManagers;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rules\Unique;

/**
 * Manual entry for curve-shaped indicators that have no automated
 * source - today, the LME Lead "Official Prices" bid/offer table
 * (Cash, 3-month, Dec-27...). One row per contract per date; the
 * dashboard shows the latest date's rows, ordered by "Order".
 *
 * Only shown for indicators that already have curve points, or the
 * LME Lead curve itself before its first row is entered - every other
 * indicator is a single number per date and has no use for this tab.
 */
class CurvePointsRelationManager extends RelationManager
{
    protected static string $relationship = 'curvePoints';

    protected static ?string $title = 'Curve points';

    public static function canViewForRecord(Model $ownerRecord, string $pageName): bool
    {
        return $ownerRecord->code === 'lme_lead_curve' || $ownerRecord->curvePoints()->exists();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                DatePicker::make('as_of_date')
                    ->label('Data valid for')
                    ->required()
                    ->default(now())
                    ->native(false),
                TextInput::make('point_key')
                    ->label('Contract')
                    ->required()
                    ->helperText('E.g. "Cash", "3-month", "Dec-27".')
                    ->unique(
                        ignoreRecord: true,
                        modifyRuleUsing: fn (Unique $rule, Get $get): Unique => $rule
                            ->where('market_indicator_id', $this->getOwnerRecord()->getKey())
                            ->where('as_of_date', $get('as_of_date')),
                    ),
                TextInput::make('point_label')
                    ->label('Label (optional)')
                    ->helperText('Shown instead of the contract name if set.'),
                TextInput::make('bid')
                    ->numeric()
                    ->step('any'),
                TextInput::make('offer')
                    ->numeric()
                    ->step('any'),
                TextInput::make('value')
                    ->label('Single value (optional)')
                    ->numeric()
                    ->step('any')
                    ->helperText('Only for curves that have one number per point instead of bid/offer.'),
                TextInput::make('sort_order')
                    ->label('Order')
                    ->numeric()
                    ->integer()
                    ->default(0)
                    ->helperText('Lowest first: Cash = 0, 3-month = 1, Dec-27 = 2...'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('point_key')
            ->defaultSort('as_of_date', 'desc')
            ->columns([
                TextColumn::make('as_of_date')->label('Data valid for')->date('M j, Y')->sortable(),
                TextColumn::make('point_key')->label('Contract')->searchable(),
                TextColumn::make('bid')->placeholder('—'),
                TextColumn::make('offer')->placeholder('—'),
                TextColumn::make('value')->placeholder('—')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('sort_order')->label('Order')->sortable(),
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
