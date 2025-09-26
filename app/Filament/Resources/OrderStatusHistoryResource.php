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
    protected static bool $shouldRegisterNavigation = false;

    public static function form(Form $form): Form
    {
        $statuses = [
            'pending' => 'pending',
            'preparing' => 'preparing',
            'ready' => 'ready',
            'assigned' => 'assigned',
            'picked' => 'picked',
            'out_for_delivery' => 'out_for_delivery',
            'delivered' => 'delivered',
            'rejected' => 'rejected',
            'cancelled' => 'cancelled',
        ];
        return $form
            ->schema([
                // Order relation
                Forms\Components\Select::make('order_id')
                    ->label('Order')
                    ->relationship('order', 'order_code') // change 'order_code' to another column if preferred
                    ->searchable()
                    ->preload()
                    ->required(),

                Forms\Components\Select::make('from_status')
                    ->options($statuses)
                    ->searchable(),

                Forms\Components\Select::make('to_status')
                    ->options($statuses)
                    ->required()
                    ->searchable(),

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

                Tables\Columns\TextColumn::make('to_status')
                    ->label('Status')
                    ->badge(),

                Tables\Columns\TextColumn::make('changer.name') // relation for changed_by
                    ->label('Changed By')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: false),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([])
            ->actions([
                Tables\Actions\Action::make('open_order')
                    ->label('Open Order')
                    ->url(fn($record) => route('filament.admin.resources.orders.edit', ['record' => $record->order]))
                    ->openUrlInNewTab(),
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
