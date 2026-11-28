<?php

namespace App\Filament\Resources\Assets\Tables;

use App\Models\Asset;
use App\Models\AssetItem;
use App\Models\Location;
use App\Services\AssetItemService;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;

class AssetsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query
                ->with(['category'])
                ->withCount([
                    'assetItems',
                    'assetItems as unit_baik' => fn (Builder $q) => $q->where('kondisi', 'baik'),
                    'assetItems as unit_rusak' => fn (Builder $q) => $q->where('kondisi', 'rusak'),
                    'assetItems as unit_rusak_total' => fn (Builder $q) => $q->where('kondisi', 'rusak_total'),
                    'assetItems as unit_terpasang' => fn (Builder $q) => $q->where('status', AssetItem::STATUS_INSTALLED),
                ])
                ->withSum('bulkStocks as bulk_total_qty', 'total_qty')
                ->withSum('bulkStocks as bulk_available_qty', 'available_qty')
            )
            ->columns([
                TextColumn::make('kode_aset')
                    ->label(__('assets.code_label'))
                    ->searchable()
                    ->sortable()
                    ->copyable(),

                TextColumn::make('nama_alat')
                    ->label(__('assets.name_label'))
                    ->searchable()
                    ->sortable()
                    ->description(fn (Asset $record): ?string => $record->spesifikasi),

                TextColumn::make('category.code')
                    ->label('Kategori')
                    ->badge()
                    ->color('info')
                    ->placeholder(fn (Asset $record): string => $record->jenis ?: '-')
                    ->tooltip(fn (Asset $record): ?string => $record->category?->name ?? $record->jenis)
                    ->sortable(),

                TextColumn::make('tracking_mode')
                    ->label('Mode')
                    ->badge()
                    ->color(fn (?string $state): string => $state === Asset::MODE_BULK_RETURNABLE ? 'warning' : 'gray')
                    ->formatStateUsing(fn (?string $state): string => $state === Asset::MODE_BULK_RETURNABLE ? 'Massal' : 'Satuan'),

                IconColumn::make('requires_approval')
                    ->label('Kritis')
                    ->boolean()
                    ->trueIcon('heroicon-o-shield-exclamation')
                    ->falseIcon('heroicon-o-minus')
                    ->trueColor('danger')
                    ->falseColor('gray')
                    ->tooltip(fn (Asset $record): string => $record->requires_approval ? 'Wajib ACC KKK / Toolman' : 'Peminjaman normal'),

                TextColumn::make('kegunaan')
                    ->label(__('assets.usage_label'))
                    ->badge()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'praktik' => '🛠️ '.(__('assets.usage_option_practice')),
                        'non_praktik' => '📋 '.(__('assets.usage_option_non_practice')),
                        'Praktik Siswa' => '🎓 '.(__('assets.usage_option_student')),
                        'Praktik Guru' => '🧑‍🏫 '.(__('assets.usage_option_teacher')),
                        'Ujian/CBT' => '💻 '.(__('assets.usage_option_exam')),
                        'lainnya' => '✨ '.(__('assets.usage_option_other')),
                        default => (string) $state,
                    }),

                TextColumn::make('status_kondisi')
                    ->label(__('assets.condition_status_label'))
                    ->html()
                    ->getStateUsing(function (Asset $record): string {
                        if ($record->isBulkReturnable()) {
                            $avail = (int) ($record->bulk_available_qty ?? 0);
                            $total = (int) ($record->bulk_total_qty ?? 0);

                            return '<span class="fi-badge">Tersedia: '.$avail.' / '.$total.'</span>';
                        }

                        $baik = (int) ($record->unit_baik ?? 0);
                        $rusak = (int) ($record->unit_rusak ?? 0);
                        $terpasang = (int) ($record->unit_terpasang ?? 0);

                        $parts = [
                            '<span class="fi-badge">'.__('assets.condition_good').": {$baik}</span>",
                            '<span class="fi-badge">'.__('assets.condition_damaged').": {$rusak}</span>",
                        ];

                        if ($terpasang > 0) {
                            $parts[] = '<span class="fi-badge">Terpasang: '.$terpasang.'</span>';
                        }

                        return implode(' ', $parts);
                    }),

                TextColumn::make('total_terdaftar')
                    ->label(__('assets.unit_count_label'))
                    ->badge()
                    ->color('primary')
                    ->getStateUsing(fn (Asset $record): int => $record->isBulkReturnable()
                        ? (int) ($record->bulk_total_qty ?? 0)
                        : (int) ($record->asset_items_count ?? 0)
                    ),
            ])
            ->filters([
                SelectFilter::make('category_id')
                    ->label('Kategori')
                    ->relationship('category', 'name')
                    ->preload(),

                SelectFilter::make('tracking_mode')
                    ->label('Mode Pelacakan')
                    ->options([
                        Asset::MODE_SERIALIZED => 'Satuan (Per Unit)',
                        Asset::MODE_BULK_RETURNABLE => 'Pinjam Massal (1 QR/Wadah)',
                    ]),

                TernaryFilter::make('requires_approval')
                    ->label('Alat Kritis (Wajib ACC)'),
            ])
            ->recordActions([
                Action::make('tambahUnit')
                    ->label(__('assets.add_unit'))
                    ->icon('heroicon-o-plus-circle')
                    ->color('info')
                    ->visible(fn (Asset $record): bool => ! $record->isBulkReturnable() && Gate::allows('create', AssetItem::class))
                    ->modalHeading(fn (Asset $record) => __('assets.add_unit_heading', ['name' => $record->nama_alat]))
                    ->form([
                        TextInput::make('jumlah')
                            ->label(__('assets.qty_label'))
                            ->numeric()
                            ->default(1)
                            ->minValue(1)
                            ->maxValue(500)
                            ->required(),

                        Select::make('location_id')
                            ->label(__('assets.placement_label'))
                            ->options(fn () => Location::orderBy('label')->pluck('label', 'id'))
                            ->default(fn () => Location::where('key', 'gudang')->value('id'))
                            ->required(),

                        TextInput::make('storage_spot')
                            ->label('Titik Simpan / Almari / Rak (Opsional)')
                            ->placeholder('cth: Almari 1 · Rak B')
                            ->maxLength(120),

                        Select::make('kondisi')
                            ->label(__('assets.initial_condition_label'))
                            ->options([
                                'baik' => __('assets.condition_good'),
                                'rusak' => __('assets.condition_damaged'),
                                'rusak_total' => __('assets.condition_total_loss'),
                            ])
                            ->default('baik')
                            ->required(),

                        Select::make('status')
                            ->label('Status Awal')
                            ->options([
                                AssetItem::STATUS_AVAILABLE => __('units.status_available'),
                                AssetItem::STATUS_INSTALLED => __('units.status_installed'),
                            ])
                            ->default(AssetItem::STATUS_AVAILABLE)
                            ->required(),

                        Textarea::make('sn_pabrik')
                            ->label('Nomor Seri Pabrik (Opsional, 1 per baris)')
                            ->rows(3)
                            ->placeholder("SN-PABRIK-001\nSN-PABRIK-002")
                            ->helperText('Kode stiker QR tetap digenerate pendek otomatis (TJKT-KAT-####).'),
                    ])
                    ->action(function (Asset $record, array $data): void {
                        $factorySerials = collect(preg_split('/\r\n|\r|\n/', (string) ($data['sn_pabrik'] ?? '')))
                            ->map(fn ($s) => trim((string) $s))
                            ->filter()
                            ->values()
                            ->all();

                        $qty = max((int) $data['jumlah'], count($factorySerials), 1);

                        $units = AssetItemService::bulkCreate(
                            asset: $record,
                            qty: $qty,
                            locationId: (int) $data['location_id'],
                            kondisi: (string) $data['kondisi'],
                            status: (string) ($data['status'] ?? AssetItem::STATUS_AVAILABLE),
                            serials: [],
                            storageSpot: $data['storage_spot'] ?? null,
                            factorySerials: $factorySerials,
                        );

                        Notification::make()
                            ->title(__('assets.units_added_title', ['count' => $units->count(), 'name' => $record->nama_alat]))
                            ->body(__('assets.units_added_body', ['first' => $units->first()->nomor_seri_atau_qr, 'last' => $units->last()->nomor_seri_atau_qr]))
                            ->success()
                            ->send();
                    }),
                EditAction::make()
                    ->label(__('common.edit')),
                DeleteAction::make()
                    ->label(__('common.delete')),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->label(__('common.delete')),
                ]),
            ])
            ->paginated([10, 25, 50, 100, 'all'])
            ->defaultPaginationPageOption(25);
    }
}
