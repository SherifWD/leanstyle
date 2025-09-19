<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BusinessHourResource\Pages;
use App\Filament\Resources\BusinessHourResource\RelationManagers;
use App\Models\BusinessHour;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class BusinessHourResource extends Resource
{
    protected static ?string $model = BusinessHour::class;

    protected static bool $shouldRegisterNavigation = false; // managed under Store
    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                // Forms\Components\TextInput::make('store_id')
                //     ->required()
                //     ->numeric(),
                    Forms\Components\Select::make('store_id')
                ->relationship('store','name')
                    ->required(),
                Forms\Components\Select::make('weekday')
                ->options(['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'])
                    ->required(),
                Forms\Components\TimePicker::make('open_at'),
                Forms\Components\TimePicker::make('close_at'),
                Forms\Components\Toggle::make('is_closed')
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('store.name')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('weekday')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('open_at')->time(),
                Tables\Columns\TextColumn::make('close_at')->time(),
                Tables\Columns\IconColumn::make('is_closed')
                    ->boolean(),
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
            'index' => Pages\ListBusinessHours::route('/'),
            'create' => Pages\CreateBusinessHour::route('/create'),
            'edit' => Pages\EditBusinessHour::route('/{record}/edit'),
        ];
    }
}
