<?php

namespace App\Filament\Resources\ProductResource\RelationManagers;

use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Forms\Form;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class ImagesRelationManager extends RelationManager
{
    protected static string $relationship = 'images';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('product_variant_id')
                ->label('Variant')
                ->relationship('variant', 'id')
                ->getOptionLabelFromRecordUsing(function ($record) {
                    $color = optional($record->color)->name;
                    $size  = optional($record->size)->name;
                    $parts = array_filter([$color, $size]);
                    $label = implode(' / ', $parts);
                    return $label ? $label : ('Variant #' . $record->id);
                })
                ->searchable()
                ->preload()
                ->nullable(),

            Forms\Components\FileUpload::make('path')
                ->label('Image')
                ->image()
                ->disk('local')
                ->directory('products')
                ->visibility('public')
                ->imageEditor()
                ->required()
                ->getUploadedFileNameForStorageUsing(function ($file) {
                    $ext = strtolower($file->getClientOriginalExtension() ?: $file->extension());
                    return Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)) . '-' . Str::random(8) . '.' . $ext;
                }),

            Forms\Components\TextInput::make('sort')
                ->numeric()
                ->default(0),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('path')
                    ->label('Image')
                    ->getStateUsing(fn($record) => $record->path ? asset($record->path) : null)
                    ->square(),
                Tables\Columns\TextColumn::make('variant_id')
                    ->label('Variant')
                    ->getStateUsing(function ($record) {
                        $v = $record->variant;
                        if (!$v) return null;
                        $color = optional($v->color)->name;
                        $size  = optional($v->size)->name;
                        $parts = array_filter([$color, $size]);
                        $label = implode(' / ', $parts);
                        return $label ? $label : ('Variant #' . $v->id);
                    }),
                Tables\Columns\TextColumn::make('sort')->sortable(),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->toggleable(true),
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
