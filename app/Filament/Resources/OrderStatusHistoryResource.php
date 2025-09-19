<?php

namespace App\Filament\Resources;

use App\Filament\Resources\OrderStatusHistoryResource\Pages;
use App\Models\OrderStatusHistory;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class OrderStatusHistoryResource extends Resource
{
    protected static ?string $model = OrderStatusHistory::class;
    protected static ?string $navigationGroup = 'Orders';
    protected static ?string $navigationIcon = 'heroicon-o-clock';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                // Order relation
                Forms\Components\Select::make('order_id')
                    ->label('Order')
                    ->relationship('order', 'order_code') // change 'order_code' to another column if preferred
                    ->searchable()
                    ->preload()
                    ->required(),

                Forms\Components\TextInput::make('from_status'),

                Forms\Components\TextInput::make('to_status')
                    ->required(),

                // Changed By relation (usually User or Admin)
                Forms\Components\Select::make('changed_by')
                    ->label('Changed By')
                    ->relationship('changer', 'name') // assumes relation is defined as changer()
                    ->searchable()
                    ->preload()
                    ->required(),

                Forms\Components\Textarea::make('reason')
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('order.order_code') // or order.id if no order_code
                    ->label('Order')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('from_status'),

                Tables\Columns\TextColumn::make('to_status'),

                Tables\Columns\TextColumn::make('changer.name') // relation for changed_by
                    ->label('Changed By')
                    ->sortable()
                    ->searchable(),

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
            'index' => Pages\ListOrderStatusHistories::route('/'),
            'create' => Pages\CreateOrderStatusHistory::route('/create'),
            'edit' => Pages\EditOrderStatusHistory::route('/{record}/edit'),
        ];
    }
}
