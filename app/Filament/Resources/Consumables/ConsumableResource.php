<?php

namespace App\Filament\Resources\Consumables;

use App\Filament\Resources\Consumables\Pages\CreateConsumable;
use App\Filament\Resources\Consumables\Pages\EditConsumable;
use App\Filament\Resources\Consumables\Pages\ListConsumables;
use App\Filament\Resources\Consumables\RelationManagers\TransactionsRelationManager;
use App\Models\Consumable;
use App\Models\ConsumableTransaction;
use App\Models\FundingSource;
use App\Models\Location;
use App\Models\Member;
use App\Services\ConsumableService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class ConsumableResource extends Resource
{
    protected static ?string $model = Consumable::class;

    protected static ?string $recordTitleAttribute = 'name';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBeaker;

    protected static ?int $navigationSort = 3;

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return __('common.nav_group_transactions');
    }

    public static function getNavigationLabel(): string
    {
        return __('common.nav_consumables');
    }

    public static function getModelLabel(): string
    {
        return 'Bahan Habis Pakai';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Bahan Habis Pakai';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->label('Nama Bahan')
                ->placeholder('cth: Konektor RJ45 Cat6, Kabel UTP Belden, Timah Solder')
                ->required()
                ->maxLength(150),

            Select::make('category_label')
                ->label('Kategori BHP')
                ->options(Consumable::categoryOptions())
                ->default('Umum')
                ->required(),

            TextInput::make('specification')
                ->label('Spesifikasi / Merk (Opsional)')
                ->placeholder('cth: Belden Cat6 305m / SC-APC Biru / 0.8mm')
                ->maxLength(255),

            Select::make('unit')
                ->label('Satuan')
                ->options(Consumable::unitOptions())
                ->default('pcs')
                ->required(),

            TextInput::make('stock')
                ->label('Stok Awal / Saat Ini')
                ->numeric()
                ->default(0)
                ->minValue(0)
                ->required()
                ->disabledOn('edit')
                ->helperText('Saat mengubah stok berjalan, gunakan tombol Restock / Catat Pemakaian / Koreksi Stok agar tercatat di riwayat.'),

            TextInput::make('min_stock')
                ->label('Batas Minimum Peringatan')
                ->numeric()
                ->default(10)
                ->minValue(0)
                ->required()
                ->helperText('Muncul peringatan di Dashboard jika stok ≤ angka ini.'),

            Select::make('location_id')
                ->label('Lokasi Simpan')
                ->relationship('location', 'label')
                ->default(fn () => Location::where('key', 'gudang')->value('id'))
                ->searchable()
                ->preload(),

            TextInput::make('storage_spot')
                ->label('Titik Simpan / Almari / Rak')
                ->placeholder('cth: Almari BHP · Laci 2')
                ->maxLength(120),

            Toggle::make('is_active')
                ->label('Aktif')
                ->default(true),

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
            ->modifyQueryUsing(fn (Builder $query) => $query->with('location'))
            ->defaultSort('name', 'asc')
            ->columns([
                TextColumn::make('name')
                    ->label('Nama Bahan')
                    ->searchable()
                    ->sortable()
                    ->description(fn (Consumable $record): ?string => $record->specification),

                TextColumn::make('category_label')
                    ->label('Kategori')
                    ->badge()
                    ->color('info')
                    ->sortable(),

                TextColumn::make('stock')
                    ->label('Stok')
                    ->badge()
                    ->formatStateUsing(fn (Consumable $record): string => number_format($record->stock).' '.$record->unit)
                    ->color(fn (Consumable $record): string => $record->isLowStock() ? 'danger' : 'success')
                    ->sortable(),

                TextColumn::make('min_stock')
                    ->label('Min. Stok')
                    ->formatStateUsing(fn (Consumable $record): string => number_format($record->min_stock).' '.$record->unit)
                    ->toggleable(),

                TextColumn::make('location.label')
                    ->label('Lokasi')
                    ->badge()
                    ->color('gray')
                    ->placeholder('—')
                    ->description(fn (Consumable $record): ?string => $record->storage_spot),

                IconColumn::make('is_active')
                    ->label(__('common.active'))
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Filter::make('low_stock')
                    ->label('Hanya Stok Menipis (≤ Min)')
                    ->query(fn (Builder $query): Builder => $query->where('is_active', true)->whereColumn('stock', '<=', 'min_stock')),

                SelectFilter::make('category_label')
                    ->label('Kategori BHP')
                    ->options(Consumable::categoryOptions()),
            ])
            ->recordActions([
                Action::make('recordUsage')
                    ->label('Pakai')
                    ->icon('heroicon-o-minus-circle')
                    ->color('warning')
                    ->visible(fn (Consumable $record): bool => Gate::allows('recordUsage', $record))
                    ->modalHeading(fn (Consumable $record): string => "Catat Pemakaian — {$record->name} (Sisa: {$record->stock} {$record->unit})")
                    ->form([
                        TextInput::make('quantity')
                            ->label(fn (Consumable $record): string => "Jumlah Dipakai ({$record->unit})")
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(fn (Consumable $record): int => max(1, (int) $record->stock))
                            ->default(1)
                            ->required(),

                        Select::make('member_id')
                            ->label('Penerima dari Daftar Siswa / Guru (Opsional)')
                            ->searchable()
                            ->options(fn () => Member::query()
                                ->where('is_active', true)
                                ->orderBy('name')
                                ->limit(300)
                                ->get()
                                ->mapWithKeys(fn (Member $m) => [$m->id => "{$m->name} ({$m->group})"]))
                            ->live()
                            ->afterStateUpdated(function (mixed $state, Set $set): void {
                                $member = $state ? Member::find($state) : null;
                                if ($member) {
                                    $set('recipient_name', $member->name);
                                    $set('recipient_group', $member->group);
                                }
                            }),

                        TextInput::make('recipient_name')
                            ->label('Nama Penerima / Penanggung Jawab')
                            ->placeholder('cth: XI TJKT 1 / Pak Budi')
                            ->maxLength(150),

                        TextInput::make('recipient_group')
                            ->label('Kelas / Unit (Opsional)')
                            ->placeholder('cth: XI TJKT 1 / Praktik FO')
                            ->maxLength(100),

                        Textarea::make('purpose')
                            ->label('Keperluan Pemakaian')
                            ->placeholder('cth: Praktik crimping kabel straight & cross mapel AIJ')
                            ->required()
                            ->rows(2)
                            ->maxLength(255),
                    ])
                    ->action(function (Consumable $record, array $data): void {
                        try {
                            $tx = ConsumableService::recordTransaction(
                                $record,
                                ConsumableTransaction::TYPE_OUT,
                                (int) $data['quantity'],
                                [
                                    'member_id' => $data['member_id'] ?? null,
                                    'recipient_name' => $data['recipient_name'] ?? null,
                                    'recipient_group' => $data['recipient_group'] ?? null,
                                    'purpose' => $data['purpose'] ?? null,
                                ],
                            );

                            Notification::make()
                                ->title('Pemakaian berhasil dicatat')
                                ->body("{$record->name} -{$tx->quantity} {$record->unit} (sisa: {$tx->stock_after} {$record->unit}).")
                                ->success()
                                ->send();
                        } catch (ValidationException $e) {
                            Notification::make()
                                ->title('Gagal mencatat pemakaian')
                                ->body(collect($e->errors())->flatten()->first() ?? $e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),

                Action::make('restock')
                    ->label('Restock')
                    ->icon('heroicon-o-plus-circle')
                    ->color('success')
                    ->visible(fn (Consumable $record): bool => Gate::allows('restock', $record))
                    ->modalHeading(fn (Consumable $record): string => "Tambah Stok / Restock — {$record->name}")
                    ->form([
                        TextInput::make('quantity')
                            ->label(fn (Consumable $record): string => "Jumlah Masuk ({$record->unit})")
                            ->numeric()
                            ->minValue(1)
                            ->default(1)
                            ->required(),

                        Select::make('funding_source_id')
                            ->label('Sumber Anggaran (Opsional)')
                            ->options(fn () => FundingSource::where('is_active', true)->orderByDesc('fiscal_year')->pluck('name', 'id'))
                            ->searchable(),

                        TextInput::make('unit_price')
                            ->label('Harga per Satuan (Rp)')
                            ->numeric()
                            ->prefix('Rp')
                            ->minValue(0)
                            ->visible(fn (): bool => (bool) auth()->user()?->canViewFinancials()),

                        Textarea::make('purpose')
                            ->label('Keterangan Pembelian / Restock')
                            ->placeholder('cth: Pembelian rutin praktik semester genap')
                            ->rows(2)
                            ->maxLength(255),
                    ])
                    ->action(function (Consumable $record, array $data): void {
                        try {
                            $tx = ConsumableService::recordTransaction(
                                $record,
                                ConsumableTransaction::TYPE_IN,
                                (int) $data['quantity'],
                                [
                                    'funding_source_id' => $data['funding_source_id'] ?? null,
                                    'unit_price' => auth()->user()?->canViewFinancials() ? ($data['unit_price'] ?? null) : null,
                                    'purpose' => $data['purpose'] ?? null,
                                ],
                            );

                            Notification::make()
                                ->title('Restock berhasil dicatat')
                                ->body("{$record->name} +{$tx->quantity} {$record->unit} (stok baru: {$tx->stock_after} {$record->unit}).")
                                ->success()
                                ->send();
                        } catch (ValidationException $e) {
                            Notification::make()
                                ->title('Gagal menambah stok')
                                ->body(collect($e->errors())->flatten()->first() ?? $e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),

                ActionGroup::make([
                    Action::make('adjustStock')
                        ->label('Koreksi Opname')
                        ->icon('heroicon-o-adjustments-horizontal')
                        ->color('gray')
                        ->visible(fn (Consumable $record): bool => Gate::allows('restock', $record))
                        ->modalHeading(fn (Consumable $record): string => "Koreksi Stok Fisik — {$record->name}")
                        ->form([
                            TextInput::make('new_stock')
                                ->label(fn (Consumable $record): string => "Stok Fisik Aktual ({$record->unit})")
                                ->numeric()
                                ->minValue(0)
                                ->default(fn (Consumable $record) => max(0, (int) $record->stock))
                                ->required(),
                            Textarea::make('purpose')
                                ->label('Alasan Koreksi Opname')
                                ->required()
                                ->rows(2)
                                ->maxLength(255),
                        ])
                        ->action(function (Consumable $record, array $data): void {
                            try {
                                $tx = ConsumableService::recordTransaction(
                                    $record,
                                    ConsumableTransaction::TYPE_ADJUSTMENT,
                                    (int) $data['new_stock'],
                                    ['purpose' => $data['purpose'] ?? null],
                                );

                                Notification::make()
                                    ->title('Stok fisik dikoreksi')
                                    ->body("{$record->name}: {$tx->stock_before} → {$tx->stock_after} {$record->unit}.")
                                    ->success()
                                    ->send();
                            } catch (ValidationException $e) {
                                Notification::make()
                                    ->title('Gagal koreksi stok')
                                    ->body(collect($e->errors())->flatten()->first() ?? $e->getMessage())
                                    ->danger()
                                    ->send();
                            }
                        }),
                    EditAction::make()->label(__('common.edit')),
                    DeleteAction::make()->label(__('common.delete')),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            TransactionsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListConsumables::route('/'),
            'create' => CreateConsumable::route('/create'),
            'edit' => EditConsumable::route('/{record}/edit'),
        ];
    }
}
