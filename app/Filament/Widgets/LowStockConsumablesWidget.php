<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Consumables\ConsumableResource;
use App\Models\Consumable;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class LowStockConsumablesWidget extends TableWidget
{
    protected static ?int $sort = 8;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        $user = auth()->user();

        if (! $user || ! $user->is_active || $user->isBorrower()) {
            return false;
        }

        return Consumable::query()
            ->where('is_active', true)
            ->whereColumn('stock', '<=', 'min_stock')
            ->exists();
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading(__('dashboard.low_stock_heading'))
            ->query(
                Consumable::query()
                    ->with('location')
                    ->where('is_active', true)
                    ->whereColumn('stock', '<=', 'min_stock')
                    ->orderBy('stock')
            )
            ->columns([
                TextColumn::make('name')
                    ->label(__('dashboard.col_consumable'))
                    ->description(fn (Consumable $record): ?string => $record->specification),

                TextColumn::make('category_label')
                    ->label('Kategori')
                    ->badge()
                    ->color('info'),

                TextColumn::make('stock')
                    ->label(__('dashboard.col_remaining'))
                    ->badge()
                    ->color('danger')
                    ->formatStateUsing(fn (Consumable $record): string => number_format($record->stock).' '.$record->unit),

                TextColumn::make('min_stock')
                    ->label(__('dashboard.col_min_stock'))
                    ->formatStateUsing(fn (Consumable $record): string => number_format($record->min_stock).' '.$record->unit),

                TextColumn::make('location.label')
                    ->label(__('dashboard.col_location'))
                    ->badge()
                    ->color('gray')
                    ->placeholder('—'),
            ])
            ->headerActions([
                Action::make('manageConsumables')
                    ->label(__('dashboard.manage_consumables'))
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(fn (): string => ConsumableResource::getUrl('index')),
            ])
            ->paginated(false);
    }
}
