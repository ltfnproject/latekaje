<?php

namespace App\Filament\Resources\Procurements\Pages;

use App\Filament\Resources\Procurements\ProcurementResource;
use App\Models\AssetItem;
use App\Services\ProcurementService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class EditProcurement extends EditRecord
{
    protected static string $resource = ProcurementResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['photo_paths'] = ProcurementService::sanitizePhotos($data['photo_paths'] ?? []);

        return $data;
    }

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

            Action::make('generateInventory')
                ->label('Generate Unit & Stok Sekarang')
                ->icon('heroicon-o-bolt')
                ->color('success')
                ->visible(fn (): bool => ! $this->record->isGenerated())
                ->requiresConfirmation()
                ->action(function (): void {
                    try {
                        $result = ProcurementService::generateInventory($this->record);
                        Notification::make()
                            ->title('Inventaris berhasil digenerate')
                            ->body("Dibuat {$result['units_created']} unit satuan, +{$result['bulk_added']} stok massal, & +{$result['consumables_added']} stok BHP.")
                            ->success()
                            ->send();
                        $this->redirect(ProcurementResource::getUrl('view', ['record' => $this->record]));
                    } catch (ValidationException $e) {
                        Notification::make()
                            ->title('Gagal generate inventaris')
                            ->body(collect($e->errors())->flatten()->first() ?? $e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),

            Action::make('rollbackGeneration')
                ->label('Batalkan Generate (Edit Ulang)')
                ->icon('heroicon-o-arrow-uturn-left')
                ->color('warning')
                ->visible(fn (): bool => $this->record->isGenerated() && Gate::allows('delete', $this->record))
                ->requiresConfirmation()
                ->modalHeading(fn (): string => "Batalkan Generate Batch {$this->record->batch_code}?")
                ->modalDescription('Seluruh unit satuan, tambahan stok wadah massal, dan tambahan stok BHP yang dibuat oleh batch ini akan dihapus/dikembalikan, lalu form rincian barang dibuka kembali untuk diedit ulang.')
                ->action(function (): void {
                    try {
                        $res = ProcurementService::rollbackGeneration($this->record);
                        Notification::make()
                            ->title('Generate batch dibatalkan')
                            ->body("{$res['units_deleted']} unit dihapus, {$res['bulk_reverted']} stok massal & {$res['consumables_reverted']} stok BHP dikembalikan. Silakan ubah rincian lalu klik Generate lagi.")
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

                    return "Membatalkan batch ini akan menghapus {$unitCount} unit fisik satuan, mengurangi tambahan stok wadah massal, mengembalikan {$consumableQty} stok Bahan Habis Pakai, dan menghapus foto kedatangan. Lanjutkan?";
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

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->record]);
    }
}
