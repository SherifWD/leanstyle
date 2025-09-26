<?php

namespace App\Filament\Resources\OrderResource\RelationManagers;

use App\Models\OrderStatusHistory;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Actions\CreateAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class AssignmentsRelationManager extends RelationManager
{
    protected static string $relationship = 'assignment';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('driver_id')
                ->relationship('driver', 'name', fn (Builder $query) => $query->where('role', 'delivery_boy'))
                ->searchable()
                ->preload()
                ->required(),
            Forms\Components\Hidden::make('assigned_by')
                ->default(fn () => $this->getCurrentUserId())
                ->dehydrated(true),
            Forms\Components\DateTimePicker::make('assigned_at')
                ->default(fn () => now()),
            Forms\Components\DateTimePicker::make('accepted_at'),
            Forms\Components\DateTimePicker::make('started_at'),
            Forms\Components\DateTimePicker::make('picked_at'),
            Forms\Components\DateTimePicker::make('out_for_delivery_at'),
            Forms\Components\DateTimePicker::make('completed_at'),
            Forms\Components\DateTimePicker::make('rejected_at'),
            Forms\Components\Textarea::make('notes')->columnSpanFull(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('driver.name')->label('Driver'),
                Tables\Columns\TextColumn::make('assignedBy.name')->label('Assigned By'),
                Tables\Columns\TextColumn::make('assigned_at')->dateTime(),
                Tables\Columns\TextColumn::make('accepted_at')->dateTime(),
                Tables\Columns\TextColumn::make('completed_at')->dateTime(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->mutateFormDataUsing(fn (array $data): array => $this->prepareAssignmentPayload($data))
                    ->after(function (Model $record): void {
                        $this->ensureOrderIsAssigned($record);
                    })
                    ->visible(fn (): bool => ! $this->hasExistingAssignment()),
            ])
            ->actions([
                EditAction::make()
                    ->mutateFormDataUsing(fn (array $data): array => $this->prepareAssignmentPayload($data))
                    ->after(function (Model $record): void {
                        $this->ensureOrderIsAssigned($record);
                    }),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    protected function prepareAssignmentPayload(array $data): array
    {
        if (empty($data['assigned_by'])) {
            $data['assigned_by'] = $this->getCurrentUserId();
        }

        if (empty($data['assigned_at'])) {
            $data['assigned_at'] = now();
        }

        return $data;
    }

    protected function ensureOrderIsAssigned(Model $assignment): void
    {
        $order = $this->getOwnerRecord();

        if (! $order) {
            return;
        }

        $lockedStatuses = ['picked', 'out_for_delivery', 'delivered', 'cancelled', 'rejected'];

        if (in_array($order->status, $lockedStatuses, true)) {
            return;
        }

        if ($order->status === 'assigned') {
            return;
        }

        $fromStatus = $order->status;

        $order->forceFill(['status' => 'assigned'])->save();

        $changedBy = $this->getCurrentUserId() ?? $order->store?->owner_id;

        if (! $changedBy) {
            return;
        }

        OrderStatusHistory::create([
            'order_id'    => $order->getKey(),
            'from_status' => $fromStatus,
            'to_status'   => 'assigned',
            'changed_by'  => $changedBy,
            'reason'      => 'Driver assigned via admin panel',
        ]);
    }

    protected function getCurrentUserId(): ?int
    {
        return Filament::auth()->id() ?? auth()->id();
    }

    protected function hasExistingAssignment(): bool
    {
        $order = $this->getOwnerRecord();

        if (! $order) {
            return false;
        }

        return $order->assignment()->exists();
    }
}
