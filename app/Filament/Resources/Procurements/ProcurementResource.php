<?php

namespace App\Filament\Resources\Procurements;

use App\Filament\Resources\Procurements\Pages\CreateProcurement;
use App\Filament\Resources\Procurements\Pages\EditProcurement;
use App\Filament\Resources\Procurements\Pages\ListProcurements;
use App\Filament\Resources\Procurements\Pages\ViewProcurement;
use App\Filament\Resources\Procurements\RelationManagers\AssetItemsRelationManager;
use App\Filament\Resources\Procurements\RelationManagers\ConsumableTransactionsRelationManager;
use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\AssetItem;
use App\Models\Consumable;
use App\Models\FundingSource;
use App\Models\Location;
use App\Models\Procurement;
use App\Services\AssetCodeGenerator;
use App\Services\GovernanceService;
use App\Services\ProcurementService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\ValidationException;

class ProcurementResource extends Resource
{
    protected static ?string $model = Procurement::class;

    protected static ?string $recordTitleAttribute = 'batch_code';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInboxArrowDown;

    protected static ?int $navigationSort = 2;

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return __('common.nav_group_transactions');
    }

    public static function getNavigationLabel(): string
    {
        return __('common.nav_procurements');
    }

    public static function getModelLabel(): string
    {
        return 'Barang Masuk';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Barang Masuk';
    }

    public static function form(Schema $schema): Schema
    {
        $strictActive = GovernanceService::isProcurementStrictMode();

        $isStrictForNew = static fn (Get $get): bool => GovernanceService::isProcurementStrictMode()
            && $get('entry_type') === Procurement::TYPE_NEW;

        return $schema->components([
            Section::make('Info Batch Barang Masuk')
                ->description(
                    $strictActive
                        ? 'Mode Ketat Barang Baru AKTIF — Jenis "Pengadaan / Barang Baru" wajib melengkapi Sumber Anggaran, Tanggal Masuk, Foto Fisik, dan Harga Satuan (sesuai kewenangan). Jenis "Inventaris Lama" tetap opsional.'
                        : 'Mode Input Fleksibel AKTIF — Sumber Anggaran, Tanggal Masuk, Foto Fisik, dan Harga bersifat OPSIONAL (aman untuk pendataan barang lama). Ubah ke Mode Ketat di menu Setting saat stok lama selesai didata.'
                )
                ->columns(2)
                ->schema([
                    TextInput::make('batch_code')
                        ->label('Kode Batch')
                        ->default(fn (): string => AssetCodeGenerator::nextProcurementBatchCode())
                        ->required()
                        ->unique(ignoreRecord: true)
                        ->maxLength(50)
                        ->suffixAction(
                            Action::make('regenerateBatchCode')
                                ->icon('heroicon-o-arrow-path')
                                ->action(fn (Set $set) => $set('batch_code', AssetCodeGenerator::nextProcurementBatchCode()))
                        ),

                    Select::make('entry_type')
                        ->label('Jenis Pencatatan')
                        ->options(Procurement::entryTypeOptions())
                        ->default(Procurement::TYPE_NEW)
                        ->required()
                        ->live(),

                    Select::make('funding_source_id')
                        ->label('Sumber Anggaran')
                        ->relationship('fundingSource', 'name', fn (Builder $query) => $query->where('is_active', true)->orderByDesc('fiscal_year')->orderBy('name'))
                        ->searchable()
                        ->preload()
                        ->required($isStrictForNew)
                        ->placeholder('Pilih sumber dana (opsional untuk barang lama)')
                        ->createOptionForm(
                            auth()->user()?->can('create', FundingSource::class)
                                ? [
                                    TextInput::make('name')
                                        ->label('Nama Sumber Anggaran')
                                        ->required()
                                        ->maxLength(150),
                                    Select::make('source_type')
                                        ->label('Jenis Sumber Dana')
                                        ->options(FundingSource::sourceTypeOptions())
                                        ->default(FundingSource::TYPE_PURCHASE)
                                        ->required(),
                                    TextInput::make('fiscal_year')
                                        ->label('Tahun Anggaran')
                                        ->numeric()
                                        ->default((int) date('Y')),
                                    Textarea::make('notes')
                                        ->label('Keterangan')
                                        ->rows(2),
                                ]
                                : null
                        ),

                    DatePicker::make('received_at')
                        ->label('Tanggal Barang Masuk')
                        ->default(fn (): string => today()->toDateString())
                        ->required($isStrictForNew),

                    TextInput::make('vendor')
                        ->label('Toko / Vendor / Pemberi Hibah (Opsional)')
                        ->placeholder('cth: Toko Komputer Jepara / Kemendikbudristek')
                        ->maxLength(150),

                    Textarea::make('notes')
                        ->label('Catatan Batch (Opsional)')
                        ->rows(2)
                        ->placeholder('cth: Pembelanjaan tahap 2 alat praktik jaringan & kabel FO')
                        ->maxLength(500),

                    FileUpload::make('photo_paths')
                        ->label('Foto Fisik Barang Masuk (Bisa Foto Grup 1x untuk 20+ Barang)')
                        ->multiple()
                        ->image()
                        ->disk('local')
                        ->directory('procurements/raw')
                        ->visibility('private')
                        ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                        ->maxSize(6144)
                        ->maxFiles(10)
                        ->required($isStrictForNew)
                        ->helperText('Cukup 1–10 foto fisik kedatangan barang (disimpan privat & disanitasi otomatis). Tidak perlu scan nota.')
                        ->columnSpanFull(),

                    Placeholder::make('existing_photos_preview')
                        ->label('Galeri Foto Tersimpan (Privat)')
                        ->content(fn (?Procurement $record) => $record
                            ? new HtmlString(view('filament.components.procurement-photos', ['procurement' => $record])->render())
                            : '')
                        ->visible(fn (?Procurement $record): bool => $record !== null && ! empty($record->photo_paths))
                        ->columnSpanFull(),
                ]),

            Section::make('Daftar Aset (Satuan & Wadah Pinjam Massal)')
                ->description('Masukkan alat inventaris (satuan maupun massal) dari batch ini. Kosongkan jika batch hanya berisi Bahan Habis Pakai.')
                ->schema([
                    Repeater::make('items')
                        ->label('Rincian Aset')
                        ->relationship()
                        ->defaultItems(1)
                        ->minItems(0)
                        ->addActionLabel('+ Tambah Baris Aset')
                        ->reorderable(false)
                        ->columns(2)
                        ->disabled(fn (?Procurement $record): bool => (bool) $record?->isGenerated())
                        ->helperText(fn (?Procurement $record): ?string => $record?->isGenerated()
                            ? 'Inventaris untuk batch ini sudah digenerate. Lihat detail unit pada tab di bawah, atau klik "Batalkan Generate (Edit Ulang)" di atas jika ingin mengubah rincian.'
                            : null)
                        ->schema([
                            Select::make('asset_id')
                                ->label('Katalog Aset / Spesifikasi')
                                ->relationship('asset', 'nama_alat')
                                ->getOptionLabelFromRecordUsing(fn (Asset $record): string => "[{$record->kode_aset}] {$record->nama_alat} — {$record->spesifikasi}")
                                ->searchable(['kode_aset', 'nama_alat', 'spesifikasi'])
                                ->preload()
                                ->required()
                                ->columnSpanFull()
                                ->createOptionForm([
                                    Select::make('category_id')
                                        ->label('Kategori Aset TJKT')
                                        ->options(fn () => AssetCategory::orderBy('sort_order')->get()->mapWithKeys(fn (AssetCategory $c) => [$c->id => $c->label()]))
                                        ->required()
                                        ->live()
                                        ->afterStateUpdated(function (mixed $state, Set $set): void {
                                            $cat = $state ? AssetCategory::find($state) : null;
                                            if (! $cat) {
                                                return;
                                            }
                                            $set('jenis', $cat->name);
                                            $set('kode_aset', AssetCodeGenerator::nextAssetCode($cat));
                                            $set('tracking_mode', $cat->default_tracking_mode);
                                            $set('default_qr_preset', $cat->default_qr_preset);
                                        }),
                                    TextInput::make('kode_aset')
                                        ->label('Kode Aset')
                                        ->required()
                                        ->unique(Asset::class, 'kode_aset'),
                                    TextInput::make('nama_alat')
                                        ->label('Nama Alat')
                                        ->required()
                                        ->maxLength(255),
                                    TextInput::make('jenis')
                                        ->label('Jenis / Subkategori')
                                        ->required()
                                        ->maxLength(255),
                                    TextInput::make('spesifikasi')
                                        ->label('Spesifikasi Lengkap')
                                        ->required()
                                        ->maxLength(255),
                                    Select::make('kegunaan')
                                        ->label('Kegunaan')
                                        ->options([
                                            'Praktik Siswa' => 'Praktik Siswa',
                                            'Praktik Guru' => 'Praktik Guru',
                                            'Ujian/CBT' => 'Ujian/CBT',
                                            'non_praktik' => 'Non-Praktik / Operasional',
                                        ])
                                        ->default('Praktik Siswa')
                                        ->required(),
                                    Select::make('tracking_mode')
                                        ->label('Mode Pelacakan')
                                        ->options(Asset::trackingModeOptions())
                                        ->default(Asset::MODE_SERIALIZED)
                                        ->required(),
                                    Select::make('default_qr_preset')
                                        ->label('Preset Stiker QR')
                                        ->options(Asset::qrPresetOptions())
                                        ->default('medium_24')
                                        ->required(),
                                    Toggle::make('requires_approval')
                                        ->label('Alat Kritis (Wajib ACC)')
                                        ->default(false),
                                ])
                                ->createOptionUsing(function (array $data): int {
                                    $asset = Asset::create($data);

                                    return (int) $asset->getKey();
                                }),

                            TextInput::make('quantity')
                                ->label('Jumlah Unit / Qty')
                                ->numeric()
                                ->default(1)
                                ->minValue(1)
                                ->maxValue(500)
                                ->required(),

                            Select::make('location_id')
                                ->label('Lokasi Penempatan Awal')
                                ->relationship('location', 'label')
                                ->default(fn () => Location::where('key', 'gudang')->value('id'))
                                ->searchable()
                                ->preload()
                                ->required(),

                            TextInput::make('storage_spot')
                                ->label('Titik Simpan / Almari / Rak (Opsional)')
                                ->placeholder('cth: Almari 1 · Rak B')
                                ->maxLength(120),

                            Select::make('initial_condition')
                                ->label('Kondisi Awal')
                                ->options([
                                    'baik' => __('units.condition_good'),
                                    'rusak' => __('units.condition_damaged'),
                                    'rusak_total' => __('units.condition_total_loss'),
                                ])
                                ->default('baik')
                                ->required(),

                            Select::make('initial_status')
                                ->label('Status Awal (Khusus Satuan)')
                                ->options([
                                    AssetItem::STATUS_AVAILABLE => __('units.status_available'),
                                    AssetItem::STATUS_INSTALLED => __('units.status_installed'),
                                ])
                                ->default(AssetItem::STATUS_AVAILABLE)
                                ->required(),

                            TextInput::make('unit_price')
                                ->label('Harga Satuan (Rp)')
                                ->numeric()
                                ->prefix('Rp')
                                ->minValue(0)
                                ->placeholder('Opsional untuk barang lama')
                                ->visible(fn (): bool => (bool) auth()->user()?->canViewFinancials())
                                ->dehydrated(fn (): bool => (bool) auth()->user()?->canViewFinancials())
                                ->required(fn (Get $get): bool => (bool) auth()->user()?->canViewFinancials()
                                    && GovernanceService::isProcurementStrictMode()
                                    && $get('../../entry_type') === Procurement::TYPE_NEW),

                            Textarea::make('serial_numbers_text')
                                ->label('Nomor Seri Pabrik (Opsional — 1 baris per unit)')
                                ->rows(2)
                                ->placeholder("SN-PABRIK-001\nSN-PABRIK-002")
                                ->helperText('Biarkan kosong jika tidak perlu mencatat SN pabrik. Kode stiker QR tetap digenerate pendek otomatis.')
                                ->columnSpanFull(),
                        ]),
                ]),

            Section::make('Daftar Bahan Habis Pakai (Opsional dalam Batch yang Sama)')
                ->description('Tambahkan restock Kabel UTP/FO roll, Konektor RJ45/SC, Timah, Tinta, dll. bila ikut datang dalam batch ini.')
                ->collapsed(fn (?Procurement $record): bool => $record !== null && ! $record->consumableItems()->exists())
                ->schema([
                    Repeater::make('consumableItems')
                        ->label('Rincian Bahan Habis Pakai')
                        ->relationship()
                        ->defaultItems(0)
                        ->minItems(0)
                        ->addActionLabel('+ Tambah Baris Bahan Habis Pakai')
                        ->reorderable(false)
                        ->columns(2)
                        ->disabled(fn (?Procurement $record): bool => (bool) $record?->isGenerated())
                        ->schema([
                            Select::make('consumable_id')
                                ->label('Bahan Habis Pakai')
                                ->relationship('consumable', 'name', fn (Builder $query) => $query->where('is_active', true)->orderBy('name'))
                                ->getOptionLabelFromRecordUsing(fn (Consumable $record): string => "{$record->name} ({$record->unit})".($record->specification ? " — {$record->specification}" : ''))
                                ->searchable(['name', 'specification'])
                                ->preload()
                                ->required()
                                ->columnSpanFull()
                                ->createOptionForm([
                                    TextInput::make('name')
                                        ->label('Nama Bahan')
                                        ->required()
                                        ->maxLength(150),
                                    Select::make('category_label')
                                        ->label('Kategori BHP')
                                        ->options(Consumable::categoryOptions())
                                        ->default('Umum')
                                        ->required(),
                                    TextInput::make('specification')
                                        ->label('Spesifikasi / Merk (Opsional)')
                                        ->maxLength(255),
                                    Select::make('unit')
                                        ->label('Satuan')
                                        ->options(Consumable::unitOptions())
                                        ->default('pcs')
                                        ->required(),
                                    TextInput::make('min_stock')
                                        ->label('Batas Minimum Peringatan')
                                        ->numeric()
                                        ->default(10)
                                        ->minValue(0)
                                        ->required(),
                                    Select::make('location_id')
                                        ->label('Lokasi Simpan')
                                        ->options(fn () => Location::orderBy('label')->pluck('label', 'id'))
                                        ->default(fn () => Location::where('key', 'gudang')->value('id')),
                                ])
                                ->createOptionUsing(function (array $data): int {
                                    $consumable = Consumable::create(array_merge($data, [
                                        'stock' => 0,
                                        'is_active' => true,
                                    ]));

                                    return (int) $consumable->getKey();
                                }),

                            TextInput::make('quantity')
                                ->label('Jumlah Masuk')
                                ->numeric()
                                ->default(1)
                                ->minValue(1)
                                ->required(),

                            TextInput::make('unit_price')
                                ->label('Harga per Satuan (Rp)')
                                ->numeric()
                                ->prefix('Rp')
                                ->minValue(0)
                                ->placeholder('Opsional untuk barang lama')
                                ->visible(fn (): bool => (bool) auth()->user()?->canViewFinancials())
                                ->dehydrated(fn (): bool => (bool) auth()->user()?->canViewFinancials())
                                ->required(fn (Get $get): bool => (bool) auth()->user()?->canViewFinancials()
                                    && GovernanceService::isProcurementStrictMode()
                                    && $get('../../entry_type') === Procurement::TYPE_NEW),

                            TextInput::make('notes')
                                ->label('Catatan Item (Opsional)')
                                ->maxLength(255)
                                ->columnSpanFull(),
                        ]),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query
                ->with(['fundingSource', 'creator'])
                ->withSum('items as asset_qty', 'quantity')
                ->withSum('consumableItems as consumable_qty', 'quantity')
                ->withCount([
                    'assetItems',
                    'assetItems as unprinted_units_count' => fn (Builder $q) => $q->whereNull('qr_printed_at'),
                ]))
            ->defaultSort('id', 'desc')
            ->recordUrl(fn (Procurement $record): string => self::getUrl('view', ['record' => $record]))
            ->columns([
                TextColumn::make('batch_code')
                    ->label('Kode Batch')
                    ->searchable()
                    ->sortable()
                    ->copyable(),

                TextColumn::make('entry_type')
                    ->label('Jenis')
                    ->badge()
                    ->color(fn (?string $state): string => $state === Procurement::TYPE_NEW ? 'success' : 'gray')
                    ->formatStateUsing(fn (?string $state): string => $state === Procurement::TYPE_NEW ? 'Barang Baru' : 'Inventaris Lama'),

                TextColumn::make('fundingSource.name')
                    ->label('Sumber Anggaran')
                    ->placeholder('— (Tanpa info anggaran)')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('received_at')
                    ->label('Tgl Masuk')
                    ->date('d M Y')
                    ->placeholder('—')
                    ->sortable(),

                TextColumn::make('total_qty')
                    ->label('Isi Batch')
                    ->badge()
                    ->color('info')
                    ->getStateUsing(fn (Procurement $record): string => number_format((int) ($record->asset_qty ?? 0) + (int) ($record->consumable_qty ?? 0)).' item')
                    ->description(function (Procurement $record): ?string {
                        $parts = [];
                        $assetQty = (int) ($record->asset_qty ?? 0);
                        $bhpQty = (int) ($record->consumable_qty ?? 0);
                        if ($assetQty > 0) {
                            $parts[] = "Aset: {$assetQty}";
                        }
                        if ($bhpQty > 0) {
                            $parts[] = "BHP: {$bhpQty}";
                        }

                        return $parts !== [] ? implode(' · ', $parts) : null;
                    }),

                TextColumn::make('qr_print_status')
                    ->label('Status QR')
                    ->badge()
                    ->getStateUsing(function (Procurement $record): string {
                        $totalUnits = (int) ($record->asset_items_count ?? 0);
                        if ($totalUnits === 0) {
                            return '—';
                        }
                        $unprinted = (int) ($record->unprinted_units_count ?? 0);

                        return $unprinted > 0 ? "{$unprinted} belum dicetak" : 'Semua tercetak';
                    })
                    ->color(function (Procurement $record): string {
                        $totalUnits = (int) ($record->asset_items_count ?? 0);
                        if ($totalUnits === 0) {
                            return 'gray';
                        }

                        return (int) ($record->unprinted_units_count ?? 0) > 0 ? 'warning' : 'success';
                    }),

                TextColumn::make('photos_count')
                    ->label('Foto')
                    ->badge()
                    ->color(fn (Procurement $record): string => empty($record->photo_paths) ? 'gray' : 'primary')
                    ->getStateUsing(fn (Procurement $record): string => count($record->photo_paths ?? []).' foto'),

                TextColumn::make('total_price')
                    ->label('Total Nilai (Rp)')
                    ->getStateUsing(fn (Procurement $record): int => $record->totalPrice())
                    ->money('IDR', locale: 'id')
                    ->visible(fn (): bool => (bool) auth()->user()?->canViewFinancials()),

                IconColumn::make('generated_at')
                    ->label('Tergenerate')
                    ->boolean()
                    ->getStateUsing(fn (Procurement $record): bool => $record->isGenerated())
                    ->tooltip(fn (Procurement $record): string => $record->isGenerated()
                        ? 'Unit/stok sudah dibuat pada '.$record->generated_at->format('d M Y H:i')
                        : 'Belum digenerate'),
            ])
            ->filters([
                SelectFilter::make('entry_type')
                    ->label('Jenis Pencatatan')
                    ->options(Procurement::entryTypeOptions()),

                SelectFilter::make('funding_source_id')
                    ->label('Sumber Anggaran')
                    ->relationship('fundingSource', 'name')
                    ->preload(),
            ])
            ->recordActions([
                ViewAction::make()->label('Detail'),

                Action::make('printBatchQr')
                    ->label(fn (Procurement $record): string => (int) ($record->unprinted_units_count ?? 0) > 0
                        ? "Cetak QR ({$record->unprinted_units_count})"
                        : 'Cetak QR')
                    ->icon('heroicon-o-qr-code')
                    ->color(fn (Procurement $record): string => (int) ($record->unprinted_units_count ?? 0) > 0 ? 'primary' : 'gray')
                    ->visible(fn (Procurement $record): bool => $record->isGenerated()
                        && (int) ($record->asset_items_count ?? 0) > 0
                        && Gate::allows('print', AssetItem::class))
                    ->url(fn (Procurement $record): string => route('print.qr', ['procurement_id' => $record->id]))
                    ->openUrlInNewTab(),

                Action::make('viewPhotos')
                    ->label('Foto')
                    ->icon('heroicon-o-photo')
                    ->color('gray')
                    ->visible(fn (Procurement $record): bool => ! empty($record->photo_paths) && Gate::allows('view', $record))
                    ->modalHeading(fn (Procurement $record): string => "Foto Kedatangan — {$record->batch_code}")
                    ->modalContent(fn (Procurement $record) => view('filament.components.procurement-photos', ['procurement' => $record]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Tutup'),

                Action::make('generateInventory')
                    ->label('Generate Unit')
                    ->icon('heroicon-o-bolt')
                    ->color('success')
                    ->visible(fn (Procurement $record): bool => ! $record->isGenerated() && Gate::allows('update', $record))
                    ->requiresConfirmation()
                    ->action(function (Procurement $record): void {
                        try {
                            $result = ProcurementService::generateInventory($record);
                            Notification::make()
                                ->title('Inventaris berhasil digenerate')
                                ->body("Dibuat {$result['units_created']} unit satuan, +{$result['bulk_added']} stok massal, & +{$result['consumables_added']} stok BHP.")
                                ->success()
                                ->send();
                        } catch (ValidationException $e) {
                            Notification::make()
                                ->title('Gagal generate inventaris')
                                ->body(collect($e->errors())->flatten()->first() ?? $e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),

                EditAction::make()->label(__('common.edit')),

                Action::make('cancelAndDelete')
                    ->label('Batalkan')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->visible(fn (Procurement $record): bool => Gate::allows('delete', $record))
                    ->requiresConfirmation()
                    ->modalHeading(fn (Procurement $record): string => "Batalkan & Hapus Batch {$record->batch_code}?")
                    ->modalDescription(function (Procurement $record): string {
                        $units = (int) ($record->asset_items_count ?? $record->assetItems()->count());
                        $bhp = (int) ($record->consumable_qty ?? 0);

                        return "Membatalkan batch ini akan otomatis menghapus {$units} unit fisik satuan, mengurangi tambahan stok wadah massal, serta mengembalikan {$bhp} stok Bahan Habis Pakai dari batch ini. Lanjutkan?";
                    })
                    ->action(function (Procurement $record): void {
                        try {
                            $res = ProcurementService::cancelAndDelete($record);
                            Notification::make()
                                ->title('Batch barang masuk dibatalkan & dihapus')
                                ->body("{$res['units_deleted']} unit satuan dihapus, {$res['bulk_reverted']} stok massal & {$res['consumables_reverted']} stok BHP dikembalikan.")
                                ->success()
                                ->send();
                        } catch (ValidationException $e) {
                            Notification::make()
                                ->title('Tidak dapat membatalkan batch')
                                ->body(collect($e->errors())->flatten()->first() ?? $e->getMessage())
                                ->danger()
                                ->persistent()
                                ->send();
                        }
                    }),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            AssetItemsRelationManager::class,
            ConsumableTransactionsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProcurements::route('/'),
            'create' => CreateProcurement::route('/create'),
            'view' => ViewProcurement::route('/{record}'),
            'edit' => EditProcurement::route('/{record}/edit'),
        ];
    }
}
