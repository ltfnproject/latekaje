<?php

namespace App\Filament\Resources\Procurements\RelationManagers;

use App\Models\ConsumableTransaction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ConsumableTransactionsRelationManager extends RelationManager
{
    protected static string $relationship = 'consumableTransactions';

    protected static ?string $title = 'Rincian Masuk Bahan Habis Pakai';

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['consumable.location', 'recorder']))
            ->defaultSort('id', 'asc')
            ->columns([
                TextColumn::make('consumable.name')
                    ->label('Nama Bahan Habis Pakai')
                    ->searchable()
                    ->sortable()
                    ->description(fn (ConsumableTransaction $record): ?string => $record->consumable?->specification),

                TextColumn::make('consumable.category_label')
                    ->label('Kategori')
                    ->badge()
                    ->color('info'),

                TextColumn::make('quantity')
                    ->label('Jumlah Masuk')
                    ->badge()
                    ->color('success')
                    ->formatStateUsing(fn (ConsumableTransaction $record): string => '+'.number_format($record->quantity).' '.($record->consumable?->unit ?? '')),

                TextColumn::make('stock_after')
                    ->label('Perubahan Stok')
                    ->formatStateUsing(fn (ConsumableTransaction $record): string => number_format($record->stock_before).' → '.number_format($record->stock_after).' '.($record->consumable?->unit ?? '')),

                TextColumn::make('unit_price')
                    ->label('Harga Satuan (Rp)')
                    ->money('IDR', locale: 'id')
                    ->placeholder('—')
                    ->visible(fn (): bool => (bool) auth()->user()?->canViewFinancials()),

                TextColumn::make('purpose')
                    ->label('Keterangan')
                    ->limit(50)
                    ->tooltip(fn (ConsumableTransaction $record): ?string => $record->purpose),

                TextColumn::make('transacted_at')
                    ->label('Waktu Masuk')
                    ->dateTime('d M Y H:i'),
            ])
            ->emptyStateHeading('Tidak ada Bahan Habis Pakai pada batch ini')
            ->emptyStateDescription('Tambahkan baris Bahan Habis Pakai saat mencatat Barang Masuk untuk melihat rinciannya di sini.');
    }
}
