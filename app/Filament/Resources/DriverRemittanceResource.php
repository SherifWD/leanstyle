<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DriverRemittanceResource\Pages;
use App\Models\DriverRemittance;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class DriverRemittanceResource extends Resource
{
    protected static ?string $model = DriverRemittance::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                // Driver FK
                Forms\Components\Select::make('driver_id')
                    ->label('Driver')
                    ->relationship('driver', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),

                // Received by (could be an admin/user)
                Forms\Components\Select::make('received_by')
                    ->label('Received By')
                    ->relationship('receivedBy', 'name') // assumes relation is named `receiver`
                    ->searchable()
                    ->preload()
                    ->required(),

                Forms\Components\TextInput::make('amount')
                    ->required()
                    ->numeric(),

                Forms\Components\TextInput::make('reference')
                    ->maxLength(255)
                    ->default(null),

                Forms\Components\DateTimePicker::make('received_at'),

                // Forms\Components\Textarea::make('details')
                //     ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                // Show related names instead of raw IDs
                Tables\Columns\TextColumn::make('driver.name')
                    ->label('Driver')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('receivedBy.name') // relation for received_by
                    ->label('Received By')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('amount')
                    ->numeric()
                    ->sortable(),

                Tables\Columns\TextColumn::make('reference')
                    ->searchable(),

                Tables\Columns\TextColumn::make('received_at')
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
            'index' => Pages\ListDriverRemittances::route('/'),
            'create' => Pages\CreateDriverRemittance::route('/create'),
            'edit' => Pages\EditDriverRemittance::route('/{record}/edit'),
        ];
    }
}
