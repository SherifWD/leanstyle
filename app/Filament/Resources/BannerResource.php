<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BannerResource\Pages;
use App\Models\Banner;
use Filament\Forms;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Illuminate\Support\Str;

class BannerResource extends Resource
{
    protected static ?string $model = Banner::class;
    protected static ?string $navigationGroup = 'Store';
    protected static ?string $navigationIcon = 'heroicon-o-photo';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('title')->maxLength(255),

            Forms\Components\FileUpload::make('image_path')
                ->label('Image')
                ->image()
                ->disk('local')
                ->directory('banners')
                ->visibility('public')
                ->imageEditor()
                ->afterStateHydrated(function (FileUpload $component, $state, $record) {
                    if (! $record) {
                        return;
                    }

                    $storedPath = $record->getRawOriginal('image_path');

                    if (blank($storedPath)) {
                        return;
                    }

                    $component->state([Str::uuid()->toString() => $storedPath]);
                })
                ->getUploadedFileNameForStorageUsing(function ($file) {
                    $ext = strtolower($file->getClientOriginalExtension() ?: $file->extension());
                    return Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)) . '-' . Str::random(8) . '.' . $ext;
                })
                ->required(),

            Forms\Components\TextInput::make('link_url')->maxLength(255)->nullable(),

            Forms\Components\Select::make('category_id')
                ->relationship('category','name')
                ->searchable()->preload()->nullable(),

            Forms\Components\Select::make('position')
                ->options([
                    'home' => 'Home',
                    'category' => 'Category',
                    'app_top' => 'App Top',
                ])->nullable(),

            Forms\Components\TextInput::make('sort')->numeric()->default(0),
            Forms\Components\Toggle::make('is_active')->default(true),
            Forms\Components\DateTimePicker::make('starts_at')->nullable(),
            Forms\Components\DateTimePicker::make('ends_at')->nullable(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\ImageColumn::make('image_path')->label('Image')->getStateUsing(fn($record) => $record->image_path)->square(),
            Tables\Columns\TextColumn::make('title')->searchable(),
            Tables\Columns\TextColumn::make('category.name')->label('Category'),
            Tables\Columns\TextColumn::make('position'),
            Tables\Columns\IconColumn::make('is_active')->boolean(),
            Tables\Columns\TextColumn::make('sort')->numeric()->sortable(),
        ])->filters([
            SelectFilter::make('position')
                ->options([
                    'home' => 'Home',
                    'category' => 'Category',
                    'app_top' => 'App Top',
                ]),
            SelectFilter::make('category_id')
                ->relationship('category', 'name')
                ->label('Category'),
            TernaryFilter::make('is_active')
                ->label('Active'),
        ])
          ->actions([
            Tables\Actions\EditAction::make(),
          ])->bulkActions([
            Tables\Actions\BulkActionGroup::make([
                Tables\Actions\DeleteBulkAction::make(),
            ]),
          ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBanners::route('/'),
            'create' => Pages\CreateBanner::route('/create'),
            'edit' => Pages\EditBanner::route('/{record}/edit'),
        ];
    }
}
