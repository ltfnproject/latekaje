<?php

namespace App\Filament\Pages;

use App\Models\AssetItem;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class Lokasi extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMap;

    public static function getNavigationLabel(): string
    {
        return __('common.nav_location_data');
    }

    public function getTitle(): string|\Illuminate\Contracts\Support\Htmlable
    {
        return __('common.nav_location_data');
    }

    protected static ?string $slug = 'data-per-lokasi';

    protected static ?int $navigationSort = 20;

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return __('common.nav_group_reports');
    }

    protected string $view = 'filament.pages.lokasi';

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user !== null && $user->is_active && ! $user->isBorrower();
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                AssetItem::query()->with(['location', 'asset', 'parentItem'])
            )
            ->columns([
                TextColumn::make('location.label')
                    ->label(__('units.location_label'))
                    ->badge()
                    ->color('info')
                    ->description(fn (AssetItem $record): ?string => $record->storage_spot)
                    ->searchable()
                    ->sortable(),

                TextColumn::make('asset.nama_alat')
                    ->label(__('units.name_label'))
                    ->description(fn (AssetItem $record): ?string => trim(($record->asset?->kode_aset ?? '').' · '.($record->asset?->spesifikasi ?? ''), ' ·'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('nomor_seri_atau_qr')
                    ->label(__('loans.col_qr'))
                    ->badge()
                    ->color('warning')
                    ->copyable()
                    ->searchable(),

                TextColumn::make('kondisi')
                    ->label(__('units.condition_label'))
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'baik' => 'success',
                        'rusak' => 'warning',
                        default => 'danger',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'baik' => __('units.condition_good'),
                        'rusak' => __('units.condition_damaged'),
                        'rusak_total' => __('units.condition_total_loss'),
                        default => $state,
                    }),

                TextColumn::make('status')
                    ->label(__('common.status'))
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        AssetItem::STATUS_AVAILABLE => 'success',
                        AssetItem::STATUS_BORROWED => 'warning',
                        AssetItem::STATUS_INSTALLED => 'info',
                        AssetItem::STATUS_RETIRED => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => AssetItem::statusOptions()[$state] ?? $state)
                    ->description(fn (AssetItem $record): ?string => $record->parentItem ? 'Di: '.$record->parentItem->nomor_seri_atau_qr : null),
            ])
            ->filters([
                SelectFilter::make('location_id')
                    ->label(__('units.location_label'))
                    ->relationship('location', 'label')
                    ->preload(),
                SelectFilter::make('status')
                    ->label(__('common.status'))
                    ->options(AssetItem::statusOptions()),
            ])
            ->defaultSort('location.label', 'asc')
            ->paginated([25, 50, 100, 'all'])
            ->defaultPaginationPageOption(50);
    }
}
