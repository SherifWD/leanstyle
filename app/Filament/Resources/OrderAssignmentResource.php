<?php

namespace App\Filament\Resources;

use App\Filament\Resources\OrderAssignmentResource\Pages;
use App\Models\OrderAssignment;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class OrderAssignmentResource extends Resource
{
    protected static ?string $model = OrderAssignment::class;

    protected static bool $shouldRegisterNavigation = false; // managed under Order
    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                // Order relationship
                Forms\Components\Select::make('order_id')
                    ->label('Order')
                    ->relationship('order', 'id') // change to 'order_number' if you have that field
                    ->searchable()
                    ->preload()
                    ->required(),

                // Driver relationship
                Forms\Components\Select::make('driver_id')
                    ->label('Driver')
                    ->relationship('driver', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),

                // Assigned By (likely a user/admin)
                Forms\Components\Select::make('assigned_by')
                    ->label('Assigned By')
                    ->relationship('assignedBy', 'name') // assumes relation is named assigner
                    ->searchable()
                    ->preload()
                    ->required(),

                Forms\Components\DateTimePicker::make('assigned_at'),
                Forms\Components\DateTimePicker::make('accepted_at'),
                Forms\Components\DateTimePicker::make('started_at'),
                Forms\Components\DateTimePicker::make('picked_at'),
                Forms\Components\DateTimePicker::make('out_for_delivery_at'),
                Forms\Components\DateTimePicker::make('completed_at'),
                Forms\Components\DateTimePicker::make('rejected_at'),

                Forms\Components\Textarea::make('notes')
                    ->columnSpanFull(),
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

                Tables\Columns\TextColumn::make('driver.name')
                    ->label('Driver')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('assignedBy.name') // relation for assigned_by
                    ->label('Assigned By')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('assigned_at')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('accepted_at')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('started_at')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('picked_at')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('out_for_delivery_at')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('completed_at')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('rejected_at')
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
            'index' => Pages\ListOrderAssignments::route('/'),
            'create' => Pages\CreateOrderAssignment::route('/create'),
            'edit' => Pages\EditOrderAssignment::route('/{record}/edit'),
        ];
    }
}
