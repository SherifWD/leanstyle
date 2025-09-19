<?php

namespace App\Filament\Resources\ProductResource\RelationManagers;

use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Forms\Form;
use Filament\Tables;
use Filament\Tables\Table;

class VariantsRelationManager extends RelationManager
{
    protected static string $relationship = 'variants';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('color_id')
                ->label('Color')
                ->relationship('color', 'name')
                ->searchable()
                ->preload()
                ->nullable(),

            Forms\Components\Select::make('size_id')
                ->label('Size')
                ->relationship('size', 'name')
                ->searchable()
                ->preload()
                ->nullable(),

            Forms\Components\TextInput::make('price')
                ->numeric()
                ->nullable(),

            Forms\Components\TextInput::make('discount_price')
                ->numeric()
                ->nullable(),

            Forms\Components\TextInput::make('stock')
                ->numeric()
                ->default(0),

            Forms\Components\Toggle::make('is_active')
                ->default(true),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('color.name')->label('Color'),
                Tables\Columns\TextColumn::make('size.name')->label('Size'),
                Tables\Columns\TextColumn::make('price')->money(),
                Tables\Columns\TextColumn::make('discount_price')->numeric(),
                Tables\Columns\TextColumn::make('stock')->numeric(),
                Tables\Columns\IconColumn::make('is_active')->boolean(),
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
