<?php

namespace App\Filament\Resources\AssetItems\Tables;

use App\Filament\Exports\AssetItemExporter;
use App\Filament\Imports\AssetItemImporter;
use App\Models\AssetItem;
use App\Models\Location;
use App\Services\ActivityLogger;
use App\Services\AssetInstallationService;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ExportAction;
use Filament\Actions\ImportAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class AssetItemsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with([
                'activeLoan',
                'asset.category',
                'location',
                'parentItem.asset',
                'procurement',
            ]))
            ->defaultSort('id', 'desc')
            ->defaultGroup(Group::make('asset.nama_alat')->label(__('units.group_asset'))->collapsible())
            ->columns([
                TextColumn::make('asset.nama_alat')
                    ->label(__('units.name_label'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('asset.kode_aset')
                    ->label(__('units.code_label'))
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('nomor_seri_atau_qr')
                    ->label(__('units.serial_label'))
                    ->searchable()
                    ->copyable()
                    ->description(fn (AssetItem $record): ?string => $record->asset?->spesifikasi),

                TextColumn::make('serial_number_pabrik')
                    ->label('S/N Pabrik')
                    ->placeholder('—')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

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
                    ->formatStateUsing(fn (?string $state): string => AssetItem::statusOptions()[$state] ?? (string) $state)
                    ->description(function (AssetItem $record): ?string {
                        if ($record->status === AssetItem::STATUS_INSTALLED && $record->parentItem) {
                            return 'Di: '.$record->parentItem->nomor_seri_atau_qr;
                        }

                        if ($record->status === AssetItem::STATUS_INSTALLED && filled($record->installed_note)) {
                            return $record->installed_note;
                        }

                        return null;
                    }),

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

                TextColumn::make('activeLoan.borrower_name')
                    ->label(__('units.borrowed_by_label'))
                    ->placeholder('—')
                    ->description(fn (AssetItem $record): ?string => $record->activeLoan?->group),

                TextColumn::make('location.label')
                    ->label(__('units.location_label'))
                    ->badge()
                    ->color('info')
                    ->description(fn (AssetItem $record): ?string => $record->storage_spot),

                TextColumn::make('qr_printed_at')
                    ->label(__('units.print_status_label'))
                    ->badge()
                    ->state(fn (AssetItem $record): string => $record->isQrPrinted()
                        ? __('units.print_status_printed')
                        : __('units.print_status_unprinted'))
                    ->color(fn (AssetItem $record): string => $record->isQrPrinted() ? 'success' : 'warning')
                    ->description(fn (AssetItem $record): ?string => $record->isQrPrinted()
                        ? $record->qr_printed_at->format('d M Y H:i').' · '.max(1, (int) $record->qr_print_count).'×'
                        : ('Masuk '.($record->created_at?->format('d M Y') ?? '-')))
                    ->sortable(),

                TextColumn::make('procurement.batch_code')
                    ->label('Batch Masuk')
                    ->placeholder('—')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->label(__('units.created_at_label'))
                    ->dateTime('d M Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('purchase_price')
                    ->label('Harga Perolehan')
                    ->money('IDR', locale: 'id')
                    ->placeholder('—')
                    ->visible(fn (): bool => (bool) auth()->user()?->canViewFinancials())
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('qr_print_status')
                    ->label(__('units.print_status_label'))
                    ->options([
                        'unprinted' => __('units.print_status_unprinted'),
                        'printed' => __('units.print_status_printed'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return match ($data['value'] ?? null) {
                            'unprinted' => $query->whereNull('qr_printed_at'),
                            'printed' => $query->whereNotNull('qr_printed_at'),
                            default => $query,
                        };
                    }),

                SelectFilter::make('procurement_id')
                    ->label('Batch Barang Masuk')
                    ->relationship('procurement', 'batch_code')
                    ->searchable()
                    ->preload(),

                SelectFilter::make('status')
                    ->label(__('units.status_label'))
                    ->options(AssetItem::statusOptions()),

                SelectFilter::make('kondisi')
                    ->label(__('units.condition_label'))
                    ->options([
                        'baik' => __('units.condition_good'),
                        'rusak' => __('units.condition_damaged'),
                        'rusak_total' => __('units.condition_total_loss'),
                    ]),

                SelectFilter::make('location_id')
                    ->label(__('units.location_label'))
                    ->relationship('location', 'label')
                    ->preload(),
            ])
            ->headerActions([
                Action::make('printUnprintedQr')
                    ->label(__('units.print_unprinted_qr'))
                    ->icon('heroicon-o-printer')
                    ->color('primary')
                    ->badge(fn (): int => AssetItem::whereNull('qr_printed_at')->count())
                    ->badgeColor('warning')
                    ->visible(fn (): bool => Gate::allows('print', AssetItem::class))
                    ->url(fn (): string => route('print.qr', ['ids' => 'unprinted']))
                    ->openUrlInNewTab(),
                Action::make('printAllQr')
                    ->label(__('units.print_all_qr'))
                    ->icon('heroicon-o-qr-code')
                    ->color('gray')
                    ->visible(fn (): bool => Gate::allows('print', AssetItem::class))
                    ->url(fn (): string => route('print.qr', ['ids' => 'all']))
                    ->openUrlInNewTab(),
                ImportAction::make()
                    ->label(__('common.import'))
                    ->importer(AssetItemImporter::class),
                Action::make('downloadTemplateCsv')
                    ->label(__('common.template_csv'))
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('gray')
                    ->action(function () {
                        $headers = [
                            'nama_alat', 'kode_aset', 'jenis', 'spesifikasi', 'kegunaan',
                            'nomor_seri_atau_qr', 'kondisi', 'lokasi', 'status', 'jumlah',
                        ];

                        $rows = [
                            ['Mini PC', 'TJKT-PC-001', 'Komputer & Laptop', 'Intel N100, RAM 8GB', 'Praktik Siswa', 'TJKT-PC-0001', 'baik', 'lab_tjkt', 'terpasang', ''],
                            ['Kabel HDMI', 'TJKT-KBL-001', 'Kabel & Konektor Siap Pakai', '2 meter', 'Praktik Siswa', '', 'baik', 'gudang', 'tersedia', '10'],
                        ];

                        return response()->streamDownload(function () use ($headers, $rows) {
                            $out = fopen('php://output', 'w');
                            fwrite($out, "\xEF\xBB\xBF");
                            fputcsv($out, $headers, ';');

                            foreach ($rows as $row) {
                                fputcsv($out, $row, ';');
                            }

                            fclose($out);
                        }, 'template-import-unit.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
                    }),
                ExportAction::make()
                    ->label(__('units.download_excel'))
                    ->exporter(AssetItemExporter::class),
            ])
            ->recordActions([
                ActionGroup::make([
                    Action::make('installFixed')
                        ->label('Pasang Tetap')
                        ->icon('heroicon-o-wrench-screwdriver')
                        ->color('info')
                        ->visible(fn (AssetItem $record): bool => $record->status === AssetItem::STATUS_AVAILABLE && Gate::allows('update', $record))
                        ->modalHeading(fn (AssetItem $record): string => "Pasang Tetap — {$record->nomor_seri_atau_qr}")
                        ->form([
                            Select::make('location_id')
                                ->label('Lokasi Pemasangan')
                                ->options(fn () => Location::orderBy('label')->pluck('label', 'id'))
                                ->default(fn (AssetItem $record) => $record->location_id)
                                ->searchable()
                                ->required(),

                            Select::make('parent_asset_item_id')
                                ->label('Perangkat Induk (Opsional — misal pasang RAM/SSD ke PC)')
                                ->searchable()
                                ->options(fn (AssetItem $record): array => AssetItem::query()
                                    ->with('asset')
                                    ->whereKeyNot($record->getKey())
                                    ->where('status', '!=', AssetItem::STATUS_RETIRED)
                                    ->orderBy('nomor_seri_atau_qr')
                                    ->limit(250)
                                    ->get()
                                    ->mapWithKeys(fn (AssetItem $item): array => [$item->getKey() => $item->displayLabel()])
                                    ->all()),

                            TextInput::make('storage_spot')
                                ->label('Titik Pemasangan / Meja / Rak')
                                ->placeholder('cth: Meja 04 Lab KKPI / Rak Server U2')
                                ->default(fn (AssetItem $record) => $record->storage_spot)
                                ->maxLength(120),

                            Textarea::make('note')
                                ->label('Catatan Pemasangan')
                                ->rows(2)
                                ->maxLength(255),
                        ])
                        ->action(function (AssetItem $record, array $data): void {
                            try {
                                AssetInstallationService::install(
                                    $record,
                                    (int) $data['location_id'],
                                    filled($data['parent_asset_item_id'] ?? null) ? (int) $data['parent_asset_item_id'] : null,
                                    $data['storage_spot'] ?? null,
                                    $data['note'] ?? null,
                                );

                                Notification::make()
                                    ->title('Unit berhasil ditandai Terpasang Tetap')
                                    ->body("Unit {$record->nomor_seri_atau_qr} kini terkunci dari peminjaman.")
                                    ->success()
                                    ->send();
                            } catch (ValidationException $e) {
                                Notification::make()
                                    ->title('Gagal memasang unit')
                                    ->body(collect($e->errors())->flatten()->first() ?? $e->getMessage())
                                    ->danger()
                                    ->send();
                            }
                        }),

                    Action::make('unmountToStock')
                        ->label('Copot ke Stok')
                        ->icon('heroicon-o-arrow-uturn-left')
                        ->color('warning')
                        ->visible(fn (AssetItem $record): bool => $record->status === AssetItem::STATUS_INSTALLED && Gate::allows('update', $record))
                        ->modalHeading(fn (AssetItem $record): string => "Copot dari Pemasangan — {$record->nomor_seri_atau_qr}")
                        ->form([
                            Select::make('location_id')
                                ->label('Lokasi Stok Tujuan')
                                ->options(fn () => Location::orderBy('label')->pluck('label', 'id'))
                                ->default(fn () => Location::where('key', 'gudang')->value('id'))
                                ->searchable()
                                ->required(),

                            TextInput::make('storage_spot')
                                ->label('Titik Simpan di Gudang / Rak')
                                ->placeholder('cth: Almari 2 · Rak C')
                                ->maxLength(120),

                            Textarea::make('note')
                                ->label('Alasan Pencopotan (Opsional)')
                                ->rows(2)
                                ->maxLength(255),
                        ])
                        ->action(function (AssetItem $record, array $data): void {
                            try {
                                AssetInstallationService::unmountToStock(
                                    $record,
                                    (int) $data['location_id'],
                                    $data['storage_spot'] ?? null,
                                    $data['note'] ?? null,
                                );

                                Notification::make()
                                    ->title('Unit dikembalikan ke stok tersedia')
                                    ->body("Unit {$record->nomor_seri_atau_qr} siap dipinjam / dipasang ulang.")
                                    ->success()
                                    ->send();
                            } catch (ValidationException $e) {
                                Notification::make()
                                    ->title('Gagal mencopot unit')
                                    ->body(collect($e->errors())->flatten()->first() ?? $e->getMessage())
                                    ->danger()
                                    ->send();
                            }
                        }),

                    Action::make('retireUnit')
                        ->label('Tandai Afkir')
                        ->icon('heroicon-o-archive-box-x-mark')
                        ->color('danger')
                        ->visible(function (AssetItem $record): bool {
                            $user = auth()->user();

                            return $user !== null
                                && ($user->isSuperadmin() || $user->isKkk())
                                && ! in_array($record->status, [AssetItem::STATUS_BORROWED, AssetItem::STATUS_RETIRED], true);
                        })
                        ->modalHeading(fn (AssetItem $record): string => "Penghapusan / Afkir — {$record->nomor_seri_atau_qr}")
                        ->form([
                            Textarea::make('reason')
                                ->label('Alasan Afkir / Penghapusan Aset')
                                ->required()
                                ->rows(2)
                                ->placeholder('cth: Mati total, biaya perbaikan melebihi harga unit baru.')
                                ->maxLength(255),
                        ])
                        ->action(function (AssetItem $record, array $data): void {
                            try {
                                AssetInstallationService::retire($record, (string) $data['reason']);

                                Notification::make()
                                    ->title('Unit ditandai Afkir')
                                    ->body("Unit {$record->nomor_seri_atau_qr} telah diarsipkan sebagai Afkir.")
                                    ->warning()
                                    ->send();
                            } catch (ValidationException $e) {
                                Notification::make()
                                    ->title('Gagal mengafkirkan unit')
                                    ->body(collect($e->errors())->flatten()->first() ?? $e->getMessage())
                                    ->danger()
                                    ->send();
                            }
                        }),

                    EditAction::make()
                        ->label(__('common.edit')),
                    DeleteAction::make()
                        ->label(__('common.delete')),
                    Action::make('printQr')
                        ->label(__('units.print_qr'))
                        ->icon('heroicon-o-qr-code')
                        ->visible(fn (): bool => Gate::allows('print', AssetItem::class))
                        ->url(fn (AssetItem $record): string => route('print.qr', ['ids' => $record->getKey()]))
                        ->openUrlInNewTab(),
                    Action::make('toggleQrPrinted')
                        ->label(fn (AssetItem $record): string => $record->isQrPrinted()
                            ? __('units.mark_unprinted')
                            : __('units.mark_printed'))
                        ->icon(fn (AssetItem $record): string => $record->isQrPrinted()
                            ? 'heroicon-o-arrow-path'
                            : 'heroicon-o-check-badge')
                        ->color(fn (AssetItem $record): string => $record->isQrPrinted() ? 'warning' : 'success')
                        ->visible(fn (): bool => Gate::allows('print', AssetItem::class))
                        ->action(function (AssetItem $record): void {
                            if ($record->isQrPrinted()) {
                                $record->update(['qr_printed_at' => null]);
                                Notification::make()->title('Status cetak direset ke Belum Dicetak')->info()->send();
                            } else {
                                $record->update([
                                    'qr_printed_at' => now(),
                                    'qr_print_count' => max(1, (int) $record->qr_print_count + 1),
                                ]);
                                Notification::make()->title('Unit ditandai Sudah Dicetak')->success()->send();
                            }
                        }),
                ])
                    ->label(__('units.actions_label'))
                    ->icon('heroicon-o-ellipsis-horizontal'),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->label(__('common.delete')),
                    BulkAction::make('printBulkQr')
                        ->label(__('units.print_selected_qr'))
                        ->icon('heroicon-o-qr-code')
                        ->visible(fn (): bool => Gate::allows('print', AssetItem::class))
                        ->action(fn (Collection $records) => redirect()->away(
                            route('print.qr', ['ids' => $records->pluck('id')->implode(',')])
                        ))
                        ->deselectRecordsAfterCompletion(),
                    BulkAction::make('markBulkPrinted')
                        ->label(__('units.mark_printed'))
                        ->icon('heroicon-o-check-badge')
                        ->color('success')
                        ->visible(fn (): bool => Gate::allows('print', AssetItem::class))
                        ->action(function (Collection $records): void {
                            $ids = $records->pluck('id')->all();
                            $updated = AssetItem::whereIn('id', $ids)->update([
                                'qr_printed_at' => now(),
                                'qr_print_count' => DB::raw('CASE WHEN qr_print_count = 0 THEN 1 ELSE qr_print_count END'),
                            ]);

                            ActivityLogger::log(
                                'asset_item.qr_printed',
                                "Menandai {$updated} unit terpilih sebagai Sudah Dicetak QR",
                                null,
                                ['count' => $updated],
                            );

                            Notification::make()
                                ->title("{$updated} unit ditandai Sudah Dicetak")
                                ->success()
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                    BulkAction::make('markBulkUnprinted')
                        ->label(__('units.mark_unprinted'))
                        ->icon('heroicon-o-arrow-path')
                        ->color('warning')
                        ->visible(fn (): bool => Gate::allows('print', AssetItem::class))
                        ->action(function (Collection $records): void {
                            $ids = $records->pluck('id')->all();
                            $updated = AssetItem::whereIn('id', $ids)->update(['qr_printed_at' => null]);

                            Notification::make()
                                ->title("{$updated} unit direset ke Belum Dicetak")
                                ->info()
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                    BulkAction::make('pindahRuanganMassal')
                        ->label(__('units.move_room'))
                        ->icon('heroicon-o-map-pin')
                        ->form([
                            Select::make('location_id')
                                ->label(__('units.target_location_label'))
                                ->relationship('location', 'label')
                                ->required(),
                            TextInput::make('storage_spot')
                                ->label('Titik Simpan Baru (Opsional)')
                                ->maxLength(120),
                        ])
                        ->action(function (Collection $records, array $data): void {
                            $update = ['location_id' => $data['location_id']];
                            if (filled($data['storage_spot'] ?? null)) {
                                $update['storage_spot'] = trim((string) $data['storage_spot']);
                            }
                            $records->each->update($update);
                        })
                        ->deselectRecordsAfterCompletion(),
                ]),
            ])
            ->paginated([10, 25, 50, 100, 'all'])
            ->defaultPaginationPageOption(25);
    }
}
