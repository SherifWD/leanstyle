<?php

namespace App\Filament\Resources;

use App\Filament\Resources\OrderResource\Pages;
use App\Models\Order;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class OrderResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                // Store relation
                Forms\Components\Select::make('store_id')
                    ->label('Store')
                    ->relationship('store', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),

                // Customer relation
                Forms\Components\Select::make('customer_id')
                    ->label('Customer')
                    ->relationship('customer', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),

                Forms\Components\TextInput::make('status')
                    ->required(),

                Forms\Components\TextInput::make('order_code')
                    ->required()
                    ->maxLength(255),

                Forms\Components\TextInput::make('subtotal')
                    ->required()
                    ->numeric()
                    ->default(0.00),

                Forms\Components\TextInput::make('discount_total')
                    ->required()
                    ->numeric()
                    ->default(0.00),

                Forms\Components\TextInput::make('tax_total')
                    ->required()
                    ->numeric()
                    ->default(0.00),

                Forms\Components\TextInput::make('delivery_fee')
                    ->required()
                    ->numeric()
                    ->default(0.00),

                Forms\Components\TextInput::make('grand_total')
                    ->required()
                    ->numeric()
                    ->default(0.00),

                Forms\Components\TextInput::make('payment_method')
                    ->required()
                    ->maxLength(255)
                    ->default('cod'),

                Forms\Components\Toggle::make('is_paid')
                    ->required(),

                Forms\Components\Textarea::make('notes')
                    ->columnSpanFull(),

                Forms\Components\TextInput::make('ship_address')
                    ->maxLength(255)
                    ->default(null),

                Forms\Components\TextInput::make('ship_lat')
                    ->numeric()
                    ->default(null),

                Forms\Components\TextInput::make('ship_lng')
                    ->numeric()
                    ->default(null),

                Forms\Components\DateTimePicker::make('accepted_at'),
                Forms\Components\DateTimePicker::make('ready_at'),
                Forms\Components\DateTimePicker::make('delivered_at'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('store.name')
                    ->label('Store')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('customer.name')
                    ->label('Customer')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('status'),

                Tables\Columns\TextColumn::make('order_code')
                    ->searchable(),

                Tables\Columns\TextColumn::make('subtotal')
                    ->numeric()
                    ->sortable(),

                Tables\Columns\TextColumn::make('discount_total')
                    ->numeric()
                    ->sortable(),

                Tables\Columns\TextColumn::make('tax_total')
                    ->numeric()
                    ->sortable(),

                Tables\Columns\TextColumn::make('delivery_fee')
                    ->numeric()
                    ->sortable(),

                Tables\Columns\TextColumn::make('grand_total')
                    ->numeric()
                    ->sortable(),

                Tables\Columns\TextColumn::make('payment_method')
                    ->searchable(),

                Tables\Columns\IconColumn::make('is_paid')
                    ->boolean(),

                Tables\Columns\TextColumn::make('ship_address')
                    ->searchable(),

                Tables\Columns\TextColumn::make('ship_lat')
                    ->numeric()
                    ->sortable(),

                Tables\Columns\TextColumn::make('ship_lng')
                    ->numeric()
                    ->sortable(),

                Tables\Columns\TextColumn::make('accepted_at')
                    ->dateTime()
                    ->sortable(),

                Tables\Columns\TextColumn::make('ready_at')
                    ->dateTime()
                    ->sortable(),

                Tables\Columns\TextColumn::make('delivered_at')
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

                Tables\Columns\TextColumn::make('deleted_at')
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
            OrderResource\RelationManagers\ItemsRelationManager::class,
            OrderResource\RelationManagers\AssignmentsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOrders::route('/'),
            'create' => Pages\CreateOrder::route('/create'),
            'edit' => Pages\EditOrder::route('/{record}/edit'),
        ];
    }
}
