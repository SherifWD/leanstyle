<?php

namespace App\Filament\Resources\StoreResource\RelationManagers;

use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Forms\Form;
use Filament\Tables;
use Filament\Tables\Table;

class BusinessHoursRelationManager extends RelationManager
{
    protected static string $relationship = 'businessHours';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('weekday')
                ->label('Weekday')
                ->options([
                    0 => 'Sunday', 1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday',
                ])
                ->required(),
            Forms\Components\TimePicker::make('open_at')->seconds(false),
            Forms\Components\TimePicker::make('close_at')->seconds(false),
            Forms\Components\Toggle::make('is_closed')->default(false),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('weekday'),
                Tables\Columns\TextColumn::make('open_at')->time(),
                Tables\Columns\TextColumn::make('close_at')->time(),
                Tables\Columns\IconColumn::make('is_closed')->boolean(),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}

