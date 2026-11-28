<?php

namespace App\Filament\Resources\Procurements\RelationManagers;

use App\Filament\Resources\AssetItems\AssetItemResource;
use App\Models\AssetItem;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;

class AssetItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'assetItems';

    protected static ?string $title = 'Daftar Detail Data Unit (Satuan)';

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['asset', 'location', 'parentItem']))
            ->defaultSort('id', 'asc')
            ->columns([
                TextColumn::make('nomor_seri_atau_qr')
                    ->label(__('units.serial_label'))
                    ->badge()
                    ->color('primary')
                    ->copyable()
                    ->searchable()
                    ->sortable(),

                TextColumn::make('asset.nama_alat')
                    ->label(__('units.name_label'))
                    ->searchable()
                    ->sortable()
                    ->description(fn (AssetItem $record): ?string => $record->asset?->spesifikasi),

                TextColumn::make('serial_number_pabrik')
                    ->label('S/N Pabrik')
                    ->placeholder('—')
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('location.label')
                    ->label(__('units.location_label'))
                    ->badge()
                    ->color('info')
                    ->description(fn (AssetItem $record): ?string => $record->storage_spot),

                TextColumn::make('status')
                    ->label(__('units.status_label'))
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        AssetItem::STATUS_AVAILABLE => 'success',
                        AssetItem::STATUS_BORROWED => 'warning',
                        AssetItem::STATUS_INSTALLED => 'info',
                        AssetItem::STATUS_RETIRED => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (?string $state): string => AssetItem::statusOptions()[$state] ?? (string) $state),

                TextColumn::make('kondisi')
                    ->label(__('units.condition_label'))
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'baik' => 'success',
                        'rusak' => 'warning',
                        'rusak_total' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'baik' => __('units.condition_good'),
                        'rusak' => __('units.condition_damaged'),
                        'rusak_total' => __('units.condition_total_loss'),
                        default => (string) $state,
                    }),

                TextColumn::make('qr_printed_at')
                    ->label(__('units.print_status_label'))
                    ->badge()
                    ->state(fn (AssetItem $record): string => $record->isQrPrinted()
                        ? __('units.print_status_printed')
                        : __('units.print_status_unprinted'))
                    ->color(fn (AssetItem $record): string => $record->isQrPrinted() ? 'success' : 'warning')
                    ->description(fn (AssetItem $record): ?string => $record->isQrPrinted()
                        ? $record->qr_printed_at->format('d M Y H:i').' · '.max(1, (int) $record->qr_print_count).'×'
                        : null),

                TextColumn::make('purchase_price')
                    ->label('Harga Perolehan')
                    ->money('IDR', locale: 'id')
                    ->placeholder('—')
                    ->visible(fn (): bool => (bool) auth()->user()?->canViewFinancials())
                    ->toggleable(),
            ])
            ->headerActions([
                Action::make('printBatchQr')
                    ->label('Cetak Semua QR Batch Ini')
                    ->icon('heroicon-o-printer')
                    ->color('primary')
                    ->visible(fn (): bool => Gate::allows('print', AssetItem::class)
                        && $this->getOwnerRecord()->assetItems()->exists())
                    ->url(fn (): string => route('print.qr', ['procurement_id' => $this->getOwnerRecord()->getKey()]))
                    ->openUrlInNewTab(),
            ])
            ->recordActions([
                Action::make('printUnitQr')
                    ->label(__('units.print_qr'))
                    ->icon('heroicon-o-qr-code')
                    ->color('gray')
                    ->visible(fn (): bool => Gate::allows('print', AssetItem::class))
                    ->url(fn (AssetItem $record): string => route('print.qr', ['ids' => $record->getKey()]))
                    ->openUrlInNewTab(),
                Action::make('editUnit')
                    ->label(__('common.edit'))
                    ->icon('heroicon-o-pencil-square')
                    ->visible(fn (AssetItem $record): bool => Gate::allows('update', $record))
                    ->url(fn (AssetItem $record): string => AssetItemResource::getUrl('edit', ['record' => $record])),
            ])
            ->emptyStateHeading('Tidak ada unit satuan pada batch ini')
            ->emptyStateDescription('Batch ini mungkin hanya berisi Wadah Pinjam Massal / Bahan Habis Pakai, atau belum digenerate.');
    }
}
