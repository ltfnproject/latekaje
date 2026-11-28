<?php

namespace App\Filament\Resources\Procurements\Pages;

use App\Filament\Resources\Procurements\ProcurementResource;
use App\Models\AssetItem;
use App\Services\ProcurementService;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class ViewProcurement extends ViewRecord
{
    protected static string $resource = ProcurementResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('printBatchQr')
                ->label('Cetak QR Batch Ini')
                ->icon('heroicon-o-printer')
                ->color('primary')
                ->visible(fn (): bool => $this->record->isGenerated()
                    && $this->record->assetItems()->exists()
                    && Gate::allows('print', AssetItem::class))
                ->url(fn (): string => route('print.qr', ['procurement_id' => $this->record->getKey()]))
                ->openUrlInNewTab(),

            EditAction::make()
                ->label(__('common.edit'))
                ->visible(fn (): bool => Gate::allows('update', $this->record)),

            Action::make('rollbackGeneration')
                ->label('Batalkan Generate (Edit Ulang)')
                ->icon('heroicon-o-arrow-uturn-left')
                ->color('warning')
                ->visible(fn (): bool => $this->record->isGenerated() && Gate::allows('delete', $this->record))
                ->requiresConfirmation()
                ->modalHeading(fn (): string => "Batalkan Generate Batch {$this->record->batch_code}?")
                ->modalDescription('Seluruh unit satuan, tambahan stok wadah massal, dan tambahan stok BHP yang dibuat oleh batch ini akan dihapus/dikembalikan, lalu batch dibuka kembali agar dapat diubah.')
                ->action(function (): void {
                    try {
                        $res = ProcurementService::rollbackGeneration($this->record);
                        Notification::make()
                            ->title('Generate batch berhasil dibatalkan')
                            ->body("{$res['units_deleted']} unit dihapus, {$res['bulk_reverted']} stok massal & {$res['consumables_reverted']} stok BHP dikembalikan. Silakan klik Ubah untuk memperbaiki batch.")
                            ->success()
                            ->send();

                        $this->redirect(ProcurementResource::getUrl('edit', ['record' => $this->record]));
                    } catch (ValidationException $e) {
                        Notification::make()
                            ->title('Tidak dapat membatalkan batch')
                            ->body(collect($e->errors())->flatten()->first() ?? $e->getMessage())
                            ->danger()
                            ->persistent()
                            ->send();
                    }
                }),

            Action::make('cancelAndDelete')
                ->label('Batalkan & Hapus Batch')
                ->icon('heroicon-o-trash')
                ->color('danger')
                ->visible(fn (): bool => Gate::allows('delete', $this->record))
                ->requiresConfirmation()
                ->modalHeading(fn (): string => "Batalkan & Hapus Batch {$this->record->batch_code}?")
                ->modalDescription(function (): string {
                    $unitCount = $this->record->assetItems()->count();
                    $consumableQty = (int) $this->record->consumableTransactions()->sum('quantity');

                    return "Batch ini akan dihapus beserta {$unitCount} unit fisik satuan, tambahan stok wadah massal, {$consumableQty} stok Bahan Habis Pakai, dan foto kedatangannya. Tindakan ini tidak dapat dibatalkan.";
                })
                ->action(function (): void {
                    try {
                        $res = ProcurementService::cancelAndDelete($this->record);
                        Notification::make()
                            ->title('Batch barang masuk dibatalkan & dihapus')
                            ->body("{$res['units_deleted']} unit satuan dihapus, {$res['bulk_reverted']} stok massal & {$res['consumables_reverted']} stok BHP dikembalikan.")
                            ->success()
                            ->send();

                        $this->redirect(ProcurementResource::getUrl('index'));
                    } catch (ValidationException $e) {
                        Notification::make()
                            ->title('Tidak dapat membatalkan batch')
                            ->body(collect($e->errors())->flatten()->first() ?? $e->getMessage())
                            ->danger()
                            ->persistent()
                            ->send();
                    }
                }),
        ];
    }
}
