<?php

namespace App\Filament\Resources\AssetCategories;

use App\Filament\Resources\AssetCategories\Pages\CreateAssetCategory;
use App\Filament\Resources\AssetCategories\Pages\EditAssetCategory;
use App\Filament\Resources\AssetCategories\Pages\ListAssetCategories;
use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\AssetItem;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AssetCategoryResource extends Resource
{
    protected static ?string $model = AssetCategory::class;

    protected static ?string $recordTitleAttribute = 'name';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    protected static ?int $navigationSort = 9;

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return __('common.nav_group_master');
    }

    public static function getNavigationLabel(): string
    {
        return __('common.nav_categories');
    }

    public static function getModelLabel(): string
    {
        return 'Kategori Aset';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Kategori Aset';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('code')
                ->label('Kode Kategori (Prefix QR)')
                ->placeholder('cth: NET, FO, PC, IOT, TLS')
                ->required()
                ->unique(ignoreRecord: true)
                ->maxLength(12)
                ->dehydrateStateUsing(fn (?string $state): string => strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) $state) ?: ''))
                ->helperText('Dipakai otomatis untuk kode aset (TJKT-KODE-001) & stiker QR (TJKT-KODE-0001).'),

            TextInput::make('name')
                ->label('Nama Kategori')
                ->placeholder('cth: Perangkat Jaringan (Mikrotik, Switch, AP)')
                ->required()
                ->maxLength(120),

            Select::make('default_tracking_mode')
                ->label('Mode Pelacakan Default')
                ->options(Asset::trackingModeOptions())
                ->default(Asset::MODE_SERIALIZED)
                ->required(),

            Select::make('default_status')
                ->label('Status Awal Default')
                ->options([
                    AssetItem::STATUS_AVAILABLE => __('units.status_available'),
                    AssetItem::STATUS_INSTALLED => __('units.status_installed'),
                ])
                ->default(AssetItem::STATUS_AVAILABLE)
                ->required(),

            Select::make('default_qr_preset')
                ->label('Ukuran Stiker QR Default')
                ->options(Asset::qrPresetOptions())
                ->default('medium_24')
                ->required(),

            TextInput::make('sort_order')
                ->label('Urutan Tampil')
                ->numeric()
                ->default(10)
                ->minValue(0)
                ->maxValue(999),

            Textarea::make('description')
                ->label('Contoh Alat & Catatan')
                ->rows(2)
                ->maxLength(500)
                ->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order', 'asc')
            ->columns([
                TextColumn::make('code')
                    ->label('Kode')
                    ->badge()
                    ->color('primary')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('name')
                    ->label('Nama Kategori')
                    ->searchable()
                    ->sortable()
                    ->description(fn (AssetCategory $record): ?string => $record->description),

                TextColumn::make('default_tracking_mode')
                    ->label('Mode Default')
                    ->badge()
                    ->color(fn (?string $state): string => $state === Asset::MODE_BULK_RETURNABLE ? 'warning' : 'info')
                    ->formatStateUsing(fn (?string $state): string => $state === Asset::MODE_BULK_RETURNABLE ? 'Massal (1 QR/Wadah)' : 'Satuan (Per Unit)'),

                TextColumn::make('default_status')
                    ->label('Status Default')
                    ->badge()
                    ->color(fn (?string $state): string => $state === AssetItem::STATUS_INSTALLED ? 'info' : 'success')
                    ->formatStateUsing(fn (?string $state): string => AssetItem::statusOptions()[$state] ?? (string) $state),

                TextColumn::make('default_qr_preset')
                    ->label('Preset Stiker')
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'micro_12x12' => 'Micro 12×12mm',
                        'mini_12' => 'Mini 12mm',
                        'flag_12' => 'Cable Flag 12mm',
                        'medium_24' => 'Sedang 24mm',
                        'detail_a4' => 'Detail A4/A3',
                        default => (string) $state,
                    })
                    ->toggleable(),

                TextColumn::make('assets_count')
                    ->label('Katalog Aset')
                    ->counts('assets')
                    ->badge()
                    ->sortable(),
            ])
            ->recordActions([
                EditAction::make()->label(__('common.edit')),
                DeleteAction::make()->label(__('common.delete')),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAssetCategories::route('/'),
            'create' => CreateAssetCategory::route('/create'),
            'edit' => EditAssetCategory::route('/{record}/edit'),
        ];
    }
}
