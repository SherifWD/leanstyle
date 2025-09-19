<?php

namespace App\Filament\Resources\StoreResource\RelationManagers;

use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Forms\Form;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ProductsRelationManager extends RelationManager
{
    protected static string $relationship = 'products';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')->required()->maxLength(255),
            Forms\Components\Textarea::make('description')->columnSpanFull(),

            Forms\Components\Select::make('category_id')
                ->label('Category')
                ->relationship(
                    name: 'category',
                    titleAttribute: 'name',
                    modifyQueryUsing: function (Builder $query) {
                        $ownerId = $this->getOwnerRecord()->id;
                        $query->where('store_id', $ownerId);
                    }
                )
                ->searchable()->preload()->nullable(),

            Forms\Components\Select::make('brand_id')
                ->label('Brand')
                ->relationship(
                    name: 'brand',
                    titleAttribute: 'name',
                    modifyQueryUsing: function (Builder $query) {
                        $ownerId = $this->getOwnerRecord()->id;
                        $query->where('store_id', $ownerId);
                    }
                )
                ->searchable()->preload()->nullable(),

            Forms\Components\TextInput::make('price')->numeric()->required(),
            Forms\Components\TextInput::make('discount_price')->numeric()->nullable(),
            Forms\Components\TextInput::make('stock')->numeric()->default(0),
            Forms\Components\Toggle::make('is_active')->default(true),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('category.name')->label('Category')->sortable(),
                Tables\Columns\TextColumn::make('brand.name')->label('Brand')->sortable(),
                Tables\Columns\TextColumn::make('price')->money()->sortable(),
                Tables\Columns\TextColumn::make('discount_price')->numeric()->sortable(),
                Tables\Columns\TextColumn::make('stock')->numeric()->sortable(),
                Tables\Columns\IconColumn::make('is_active')->boolean(),
                Tables\Columns\TextColumn::make('created_at')->dateTime(),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
                Tables\Actions\Action::make('manage')
                    ->label('Manage')
                    ->url(fn($record) => route('filament.admin.resources.products.edit', ['record' => $record]))
                    ->openUrlInNewTab(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}

