<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DriverCashLedgerResource\Pages;
use App\Models\DriverCashLedger;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class DriverCashLedgerResource extends Resource
{
    protected static ?string $model = DriverCashLedger::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                // Handle FK as relationship
                Forms\Components\Select::make('driver_id')
                    ->label('Driver')
                    ->relationship('driver', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),

                Forms\Components\Select::make('order_id')
                    ->label('Order')
                    ->relationship('order', 'id') // or maybe 'order_number' if you have it
                    ->searchable()
                    ->preload()
                    ->default(null),

                Forms\Components\TextInput::make('type')
                    ->required(),

                Forms\Components\TextInput::make('amount')
                    ->required()
                    ->numeric(),

                Forms\Components\Textarea::make('note')
                    ->columnSpanFull(),

                Forms\Components\DateTimePicker::make('effective_at'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('driver.name')
                    ->label('Driver')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('order.id') // or order.order_number
                    ->label('Order')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('type'),

                Tables\Columns\TextColumn::make('amount')
                    ->numeric()
                    ->sortable(),

                Tables\Columns\TextColumn::make('effective_at')
                    ->dateTime()
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDriverCashLedgers::route('/'),
            'create' => Pages\CreateDriverCashLedger::route('/create'),
            'edit' => Pages\EditDriverCashLedger::route('/{record}/edit'),
        ];
    }
}
