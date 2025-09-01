<?php

namespace App\Filament\Resources;

use App\Filament\Resources\OrderItemResource\Pages;
use App\Models\OrderItem;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class OrderItemResource extends Resource
{
    protected static ?string $model = OrderItem::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                // Order relation
                Forms\Components\Select::make('order_id')
                    ->label('Order')
                    ->relationship('order', 'id') // change to 'order_number' if exists
                    ->searchable()
                    ->preload()
                    ->required(),

                // Product relation
                Forms\Components\Select::make('product_id')
                    ->label('Product')
                    ->relationship('product', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),


                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255),

                Forms\Components\Textarea::make('options')
                    ->columnSpanFull(),

                Forms\Components\TextInput::make('qty')
                    ->required()
                    ->numeric(),

                Forms\Components\TextInput::make('unit_price')
                    ->required()
                    ->numeric(),

                Forms\Components\TextInput::make('discount')
                    ->required()
                    ->numeric()
                    ->default(0.00),

                Forms\Components\TextInput::make('line_total')
                    ->required()
                    ->numeric(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('order.id') // or order.order_number
                    ->label('Order')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('product.name')
                    ->label('Product')
                    ->sortable()
                    ->searchable(),


                Tables\Columns\TextColumn::make('name')
                    ->searchable(),

                Tables\Columns\TextColumn::make('qty')
                    ->numeric()
                    ->sortable(),

                Tables\Columns\TextColumn::make('unit_price')
                    ->numeric()
                    ->sortable(),

                Tables\Columns\TextColumn::make('discount')
                    ->numeric()
                    ->sortable(),

                Tables\Columns\TextColumn::make('line_total')
                    ->numeric()
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
            'index' => Pages\ListOrderItems::route('/'),
            'create' => Pages\CreateOrderItem::route('/create'),
            'edit' => Pages\EditOrderItem::route('/{record}/edit'),
        ];
    }
}
