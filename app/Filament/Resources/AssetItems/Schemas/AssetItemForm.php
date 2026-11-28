<?php

namespace App\Filament\Resources\AssetItems\Schemas;

use App\Models\Asset;
use App\Models\AssetItem;
use App\Models\Location;
use App\Services\AssetCodeGenerator;
use Filament\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class AssetItemForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('asset_id')
                ->label(__('units.asset_label'))
                ->relationship('asset', 'nama_alat')
                ->searchable(['kode_aset', 'nama_alat'])
                ->getOptionLabelFromRecordUsing(fn (Asset $record): string => "[{$record->kode_aset}] {$record->nama_alat}")
                ->preload()
                ->live()
                ->afterStateUpdated(function (mixed $state, Set $set, ?AssetItem $record): void {
                    if ($record || ! $state) {
                        return;
                    }

                    $asset = Asset::with('category')->find($state);
                    if ($asset) {
                        $set('nomor_seri_atau_qr', AssetCodeGenerator::nextUnitQr($asset));
                    }
                })
                ->required(),

            TextInput::make('nomor_seri_atau_qr')
                ->label(__('units.serial_label'))
                ->required()
                ->unique(ignoreRecord: true)
                ->maxLength(255)
                ->suffixAction(
                    Action::make('generate')
                        ->icon('heroicon-o-arrow-path')
                        ->tooltip('Generate kode QR pendek otomatis')
                        ->action(function (Get $get, Set $set): void {
                            $asset = $get('asset_id') ? Asset::with('category')->find($get('asset_id')) : null;
                            $set('nomor_seri_atau_qr', AssetCodeGenerator::nextUnitQr($asset));
                        })
                ),

            TextInput::make('serial_number_pabrik')
                ->label('Nomor Seri Pabrik (S/N)')
                ->placeholder('Opsional — SN tercetak di stiker pabrik')
                ->maxLength(120),

            TextInput::make('jumlah')
                ->label(__('units.qty_created_label'))
                ->numeric()
                ->default(1)
                ->minValue(1)
                ->maxValue(500)
                ->required()
                ->helperText(__('units.qty_created_helper'))
                ->hiddenOn('edit'),

            Textarea::make('sn_manual')
                ->label(__('units.sn_manual_label'))
                ->rows(3)
                ->placeholder(__('units.sn_manual_placeholder'))
                ->helperText(__('units.sn_manual_helper'))
                ->hiddenOn('edit'),

            Select::make('status')
                ->label(__('units.status_label'))
                ->options(AssetItem::statusOptions())
                ->default(AssetItem::STATUS_AVAILABLE)
                ->live()
                ->required(),

            Select::make('kondisi')
                ->label(__('units.condition_label'))
                ->options([
                    'baik' => __('units.condition_good'),
                    'rusak' => __('units.condition_damaged'),
                    'rusak_total' => __('units.condition_total_loss'),
                ])
                ->default('baik')
                ->required()
                ->live()
                ->afterStateUpdated(function (mixed $state, Set $set, Get $get): void {
                    $status = $get('status');
                    if (
                        in_array($state, ['rusak', 'rusak_total'], true)
                        && ! in_array($status, [AssetItem::STATUS_INSTALLED, AssetItem::STATUS_RETIRED], true)
                    ) {
                        $gudangId = Location::where('key', 'gudang')->value('id');

                        if ($gudangId) {
                            $set('location_id', $gudangId);
                        }
                    }
                }),

            Select::make('location_id')
                ->label(__('units.placement_label'))
                ->relationship('location', 'label')
                ->required()
                ->preload()
                ->searchable()
                ->afterStateHydrated(function (Select $component, mixed $state, Set $set, mixed $record): void {
                    if (! $record && blank($state)) {
                        $gudangId = Location::where('key', 'gudang')->value('id');

                        if ($gudangId) {
                            $set('location_id', $gudangId);
                            $component->state($gudangId);
                        }
                    }
                }),

            TextInput::make('storage_spot')
                ->label('Titik Simpan / Rak / Meja')
                ->placeholder('cth: Almari 1 · Rak B, Meja PC-04, Rak Server U3')
                ->maxLength(120),

            Select::make('parent_asset_item_id')
                ->label('Terpasang pada Perangkat Induk (Opsional)')
                ->searchable()
                ->preload()
                ->options(function (?AssetItem $record): array {
                    return AssetItem::query()
                        ->with('asset')
                        ->when($record, fn ($q) => $q->whereKeyNot($record->getKey()))
                        ->where('status', '!=', AssetItem::STATUS_RETIRED)
                        ->orderBy('nomor_seri_atau_qr')
                        ->limit(250)
                        ->get()
                        ->mapWithKeys(fn (AssetItem $item): array => [$item->getKey() => $item->displayLabel()])
                        ->all();
                })
                ->visible(fn (Get $get): bool => $get('status') === AssetItem::STATUS_INSTALLED)
                ->helperText('Isi jika komponen ini dipasang ke unit lain (cth: RAM/SSD dipasang ke PC-04).'),

            TextInput::make('installed_note')
                ->label('Catatan Pemasangan / Penghapusan')
                ->placeholder('cth: Terpasang di Lab KKPI Meja 04 / Rusak tidak ekonomis diperbaiki')
                ->maxLength(255)
                ->visible(fn (Get $get): bool => in_array($get('status'), [AssetItem::STATUS_INSTALLED, AssetItem::STATUS_RETIRED], true)),

            TextInput::make('purchase_price')
                ->label(__('units.purchase_price_label'))
                ->numeric()
                ->prefix('Rp')
                ->minValue(0)
                ->placeholder('Opsional untuk barang lama')
                ->visible(fn (): bool => (bool) auth()->user()?->canViewFinancials()),

            Placeholder::make('mounted_components_info')
                ->label('Komponen Terpasang di Perangkat Ini')
                ->content(function (?AssetItem $record): string {
                    if (! $record) {
                        return '-';
                    }

                    $components = $record->installedComponents()->with('asset')->get();
                    if ($components->isEmpty()) {
                        return 'Belum ada komponen yang ditautkan.';
                    }

                    return $components
                        ->map(fn (AssetItem $c): string => "{$c->nomor_seri_atau_qr} ({$c->asset?->nama_alat})")
                        ->implode(' • ');
                })
                ->visible(fn (?AssetItem $record): bool => $record !== null && $record->installedComponents()->exists())
                ->columnSpanFull(),
        ]);
    }
}
