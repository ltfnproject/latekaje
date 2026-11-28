<?php

namespace App\Filament\Resources\BulkStocks;

use App\Filament\Resources\BulkStocks\Pages\CreateBulkStock;
use App\Filament\Resources\BulkStocks\Pages\EditBulkStock;
use App\Filament\Resources\BulkStocks\Pages\ListBulkStocks;
use App\Models\Asset;
use App\Models\AssetItem;
use App\Models\BulkStock;
use App\Models\Location;
use App\Services\ActivityLogger;
use App\Services\AssetCodeGenerator;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;

class BulkStockResource extends Resource
{
    protected static ?string $model = BulkStock::class;

    protected static ?string $recordTitleAttribute = 'label';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCube;

    protected static ?int $navigationSort = 13;

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return __('common.nav_group_master');
    }

    public static function getNavigationLabel(): string
    {
        return __('common.nav_bulk_stocks');
    }

    public static function getModelLabel(): string
    {
        return 'Wadah Pinjam Massal';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Wadah Pinjam Massal';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('asset_id')
                ->label('Katalog Aset (Mode Pinjam Massal)')
                ->relationship(
                    'asset',
                    'nama_alat',
                    fn (Builder $query) => $query->where('tracking_mode', Asset::MODE_BULK_RETURNABLE)
                )
                ->getOptionLabelFromRecordUsing(fn (Asset $record): string => "[{$record->kode_aset}] {$record->nama_alat}")
                ->searchable(['kode_aset', 'nama_alat'])
                ->preload()
                ->live()
                ->afterStateUpdated(function (mixed $state, Set $set, Get $get, ?BulkStock $record): void {
                    if ($record || ! $state) {
                        return;
                    }
                    $asset = Asset::with('category')->find($state);
                    if (! $asset) {
                        return;
                    }
                    $set('container_qr', AssetCodeGenerator::nextUnitQr($asset));
                    if (blank($get('label'))) {
                        $set('label', "Box {$asset->nama_alat}");
                    }
                })
                ->required()
                ->helperText('Hanya menampilkan aset dengan mode "Pinjam Massal (1 QR/Wadah)".'),

            TextInput::make('container_qr')
                ->label('Kode QR Wadah / Box')
                ->required()
                ->unique(ignoreRecord: true)
                ->maxLength(64)
                ->suffixAction(
                    Action::make('generateContainerQr')
                        ->icon('heroicon-o-arrow-path')
                        ->action(function (Get $get, Set $set): void {
                            $asset = $get('asset_id') ? Asset::with('category')->find($get('asset_id')) : null;
                            $set('container_qr', AssetCodeGenerator::nextUnitQr($asset));
                        })
                ),

            TextInput::make('label')
                ->label('Nama Wadah / Kontainer')
                ->placeholder('cth: Box Patchcord UTP 1m — Almari 1')
                ->required()
                ->maxLength(150),

            Select::make('location_id')
                ->label(__('units.placement_label'))
                ->relationship('location', 'label')
                ->default(fn () => Location::where('key', 'gudang')->value('id'))
                ->searchable()
                ->preload()
                ->required(),

            TextInput::make('storage_spot')
                ->label('Titik Simpan / Almari / Rak')
                ->placeholder('cth: Almari 2 · Rak B')
                ->maxLength(120),

            TextInput::make('total_qty')
                ->label('Total Isi Wadah')
                ->numeric()
                ->default(0)
                ->minValue(0)
                ->live()
                ->afterStateUpdated(function (mixed $state, Set $set, ?BulkStock $record): void {
                    if (! $record) {
                        $set('available_qty', max(0, (int) $state));
                    }
                })
                ->required(),

            TextInput::make('available_qty')
                ->label('Tersedia (Siap Pinjam)')
                ->numeric()
                ->default(0)
                ->minValue(0)
                ->required(),

            TextInput::make('borrowed_qty')
                ->label('Sedang Dipinjam')
                ->numeric()
                ->default(0)
                ->minValue(0)
                ->disabled()
                ->dehydrated(false)
                ->helperText('Diperbarui otomatis saat transaksi peminjaman massal (Fase 3).'),

            TextInput::make('damaged_qty')
                ->label('Rusak / Tidak Layak')
                ->numeric()
                ->default(0)
                ->minValue(0)
                ->required(),

            Textarea::make('notes')
                ->label('Catatan')
                ->rows(2)
                ->maxLength(500)
                ->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['asset.category', 'location']))
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('container_qr')
                    ->label('QR Wadah')
                    ->badge()
                    ->color('primary')
                    ->searchable()
                    ->copyable(),

                TextColumn::make('label')
                    ->label('Nama Wadah & Alat')
                    ->searchable()
                    ->sortable()
                    ->description(fn (BulkStock $record): ?string => $record->asset
                        ? "[{$record->asset->kode_aset}] {$record->asset->nama_alat}"
                        : null),

                TextColumn::make('location.label')
                    ->label(__('units.location_label'))
                    ->badge()
                    ->color('info')
                    ->description(fn (BulkStock $record): ?string => $record->storage_spot),

                TextColumn::make('available_qty')
                    ->label('Tersedia')
                    ->badge()
                    ->color(fn (int $state): string => $state > 0 ? 'success' : 'danger')
                    ->sortable(),

                TextColumn::make('borrowed_qty')
                    ->label('Dipinjam')
                    ->badge()
                    ->color(fn (int $state): string => $state > 0 ? 'warning' : 'gray')
                    ->sortable(),

                TextColumn::make('damaged_qty')
                    ->label('Rusak')
                    ->badge()
                    ->color(fn (int $state): string => $state > 0 ? 'danger' : 'gray')
                    ->sortable(),

                TextColumn::make('total_qty')
                    ->label('Total')
                    ->badge()
                    ->color('info')
                    ->sortable(),

                TextColumn::make('qr_printed_at')
                    ->label(__('units.print_status_label'))
                    ->badge()
                    ->state(fn (BulkStock $record): string => $record->isQrPrinted()
                        ? __('units.print_status_printed')
                        : __('units.print_status_unprinted'))
                    ->color(fn (BulkStock $record): string => $record->isQrPrinted() ? 'success' : 'warning')
                    ->description(fn (BulkStock $record): ?string => $record->isQrPrinted()
                        ? $record->qr_printed_at->format('d M Y H:i').' · '.max(1, (int) $record->qr_print_count).'×'
                        : null)
                    ->sortable(),
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

                SelectFilter::make('location_id')
                    ->label(__('units.location_label'))
                    ->relationship('location', 'label')
                    ->preload(),
            ])
            ->headerActions([
                Action::make('printUnprintedBulkQr')
                    ->label('Cetak QR Wadah Belum Dicetak')
                    ->icon('heroicon-o-printer')
                    ->color('primary')
                    ->badge(fn (): int => BulkStock::whereNull('qr_printed_at')->count())
                    ->badgeColor('warning')
                    ->visible(fn (): bool => Gate::allows('print', AssetItem::class))
                    ->url(fn (): string => route('print.qr', ['type' => 'bulk', 'ids' => 'unprinted']))
                    ->openUrlInNewTab(),
            ])
            ->recordActions([
                Action::make('printBulkContainerQr')
                    ->label(__('units.print_qr'))
                    ->icon('heroicon-o-qr-code')
                    ->color(fn (BulkStock $record): string => $record->isQrPrinted() ? 'gray' : 'primary')
                    ->visible(fn (): bool => Gate::allows('print', AssetItem::class))
                    ->url(fn (BulkStock $record): string => route('print.qr', ['type' => 'bulk', 'ids' => $record->id]))
                    ->openUrlInNewTab(),
                Action::make('adjustStock')
                    ->label('Koreksi Stok')
                    ->icon('heroicon-o-adjustments-horizontal')
                    ->color('warning')
                    ->visible(fn (BulkStock $record): bool => Gate::allows('update', $record))
                    ->modalHeading(fn (BulkStock $record): string => "Koreksi Stok Wadah — {$record->container_qr}")
                    ->form([
                        TextInput::make('available_qty')
                            ->label('Jumlah Tersedia (Baik)')
                            ->numeric()
                            ->minValue(0)
                            ->default(fn (BulkStock $record) => $record->available_qty)
                            ->required(),
                        TextInput::make('damaged_qty')
                            ->label('Jumlah Rusak')
                            ->numeric()
                            ->minValue(0)
                            ->default(fn (BulkStock $record) => $record->damaged_qty)
                            ->required(),
                        Textarea::make('reason')
                            ->label('Alasan Penyesuaian / Opname')
                            ->required()
                            ->rows(2)
                            ->maxLength(255),
                    ])
                    ->action(function (BulkStock $record, array $data): void {
                        $beforeAvail = $record->available_qty;
                        $beforeDamaged = $record->damaged_qty;

                        $newAvail = max(0, (int) $data['available_qty']);
                        $newDamaged = max(0, (int) $data['damaged_qty']);
                        $newTotal = $newAvail + $newDamaged + (int) $record->borrowed_qty;

                        $record->update([
                            'available_qty' => $newAvail,
                            'damaged_qty' => $newDamaged,
                            'total_qty' => $newTotal,
                        ]);

                        ActivityLogger::log(
                            'bulk_stock.adjusted',
                            "Koreksi wadah {$record->container_qr} ({$record->label}): tersedia {$beforeAvail}→{$newAvail}, rusak {$beforeDamaged}→{$newDamaged} ({$data['reason']})",
                            $record,
                            [
                                'container_qr' => $record->container_qr,
                                'before_available' => $beforeAvail,
                                'after_available' => $newAvail,
                                'before_damaged' => $beforeDamaged,
                                'after_damaged' => $newDamaged,
                                'reason' => $data['reason'],
                            ],
                        );

                        Notification::make()
                            ->title('Stok wadah diperbarui')
                            ->success()
                            ->send();
                    }),
                EditAction::make()->label(__('common.edit')),
                DeleteAction::make()->label(__('common.delete')),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBulkStocks::route('/'),
            'create' => CreateBulkStock::route('/create'),
            'edit' => EditBulkStock::route('/{record}/edit'),
        ];
    }
}
