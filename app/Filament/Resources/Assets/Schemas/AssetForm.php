<?php

namespace App\Filament\Resources\Assets\Schemas;

use App\Models\Asset;
use App\Models\AssetCategory;
use App\Services\AssetCodeGenerator;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class AssetForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('category_id')
                ->label('Kategori Aset TJKT')
                ->relationship('category', 'name')
                ->getOptionLabelFromRecordUsing(fn (AssetCategory $record): string => $record->label())
                ->searchable(['code', 'name'])
                ->preload()
                ->live()
                ->afterStateUpdated(function (mixed $state, Set $set, Get $get, ?Asset $record): void {
                    $category = $state ? AssetCategory::find($state) : null;
                    if (! $category) {
                        return;
                    }

                    $set('jenis', $category->name);

                    if (! $record) {
                        $set('kode_aset', AssetCodeGenerator::nextAssetCode($category));
                        $set('tracking_mode', $category->default_tracking_mode);
                        $set('default_qr_preset', $category->default_qr_preset);
                    } elseif (blank($get('kode_aset'))) {
                        $set('kode_aset', AssetCodeGenerator::nextAssetCode($category));
                    }
                })
                ->helperText('Pilih kategori agar kode aset (TJKT-KAT-###), mode pelacakan, dan ukuran stiker terisi otomatis.'),

            TextInput::make('kode_aset')
                ->label(__('assets.code_label'))
                ->required()
                ->unique(ignoreRecord: true)
                ->placeholder('TJKT-NET-001')
                ->maxLength(255)
                ->suffixAction(
                    Action::make('generateKodeAset')
                        ->icon('heroicon-o-arrow-path')
                        ->tooltip('Generate ulang kode aset otomatis')
                        ->action(function (Get $get, Set $set): void {
                            $category = $get('category_id') ? AssetCategory::find($get('category_id')) : null;
                            $set('kode_aset', AssetCodeGenerator::nextAssetCode($category));
                        })
                ),

            TextInput::make('nama_alat')
                ->label(__('assets.name_label'))
                ->required()
                ->maxLength(255),

            TextInput::make('jenis')
                ->label(__('assets.type_label'))
                ->required()
                ->maxLength(255)
                ->helperText('Terisi otomatis dari kategori, boleh disesuaikan.'),

            TextInput::make('spesifikasi')
                ->label(__('assets.spec_label'))
                ->required()
                ->maxLength(255)
                ->columnSpanFull(),

            Select::make('tracking_mode')
                ->label('Mode Pelacakan Inventaris')
                ->options(Asset::trackingModeOptions())
                ->default(Asset::MODE_SERIALIZED)
                ->required()
                ->helperText('Satuan = 1 QR per barang fisik. Pinjam Massal = 1 QR untuk 1 wadah/box (cth: patchcord, tang, obeng, sensor mini).'),

            Select::make('default_qr_preset')
                ->label('Preset Ukuran Stiker QR')
                ->options(Asset::qrPresetOptions())
                ->default('medium_24')
                ->required(),

            Toggle::make('requires_approval')
                ->label('Alat Kritis / Wajib ACC KKK atau Toolman')
                ->default(false)
                ->helperText('Aktifkan untuk alat bernilai tinggi atau sensitif (cth: Fusion Splicer, OTDR, Router Core, Laptop).')
                ->columnSpanFull(),
        ]);
    }
}
