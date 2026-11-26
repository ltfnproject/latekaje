<?php

namespace App\Filament\Resources\Consumables\RelationManagers;

use App\Models\ConsumableTransaction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class TransactionsRelationManager extends RelationManager
{
    protected static string $relationship = 'transactions';

    protected static ?string $title = 'Riwayat Keluar / Masuk Stok';

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['fundingSource', 'recorder']))
            ->defaultSort('transacted_at', 'desc')
            ->columns([
                TextColumn::make('transacted_at')
                    ->label('Waktu')
                    ->dateTime('d M Y H:i')
                    ->sortable(),

                TextColumn::make('type')
                    ->label('Jenis')
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        ConsumableTransaction::TYPE_IN => 'success',
                        ConsumableTransaction::TYPE_OUT => 'warning',
                        default => 'info',
                    })
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        ConsumableTransaction::TYPE_IN => 'Masuk (Restock)',
                        ConsumableTransaction::TYPE_OUT => 'Keluar (Pakai)',
                        default => 'Koreksi Opname',
                    }),

                TextColumn::make('quantity')
                    ->label('Jumlah')
                    ->formatStateUsing(fn (ConsumableTransaction $record): string => match ($record->type) {
                        ConsumableTransaction::TYPE_IN => '+'.$record->quantity,
                        ConsumableTransaction::TYPE_OUT => '-'.$record->quantity,
                        default => '='.$record->quantity,
                    }),

                TextColumn::make('stock_after')
                    ->label('Stok')
                    ->formatStateUsing(fn (ConsumableTransaction $record): string => "{$record->stock_before} → {$record->stock_after}"),

                TextColumn::make('recipient_name')
                    ->label('Penerima / Kelas')
                    ->placeholder('—')
                    ->description(fn (ConsumableTransaction $record): ?string => $record->recipient_group),

                TextColumn::make('purpose')
                    ->label('Keperluan / Catatan')
                    ->limit(50)
                    ->tooltip(fn (ConsumableTransaction $record): ?string => $record->purpose),

                TextColumn::make('fundingSource.name')
                    ->label('Sumber Dana')
                    ->placeholder('—')
                    ->toggleable(),

                TextColumn::make('unit_price')
                    ->label('Harga Satuan')
                    ->money('IDR', locale: 'id')
                    ->placeholder('—')
                    ->visible(fn (): bool => (bool) auth()->user()?->canViewFinancials())
                    ->toggleable(),

                TextColumn::make('recorder.name')
                    ->label('Dicatat Oleh')
                    ->placeholder('Sistem'),
            ]);
    }
}
