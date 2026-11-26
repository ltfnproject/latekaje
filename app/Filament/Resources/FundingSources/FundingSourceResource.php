<?php

namespace App\Filament\Resources\FundingSources;

use App\Filament\Resources\FundingSources\Pages\CreateFundingSource;
use App\Filament\Resources\FundingSources\Pages\EditFundingSource;
use App\Filament\Resources\FundingSources\Pages\ListFundingSources;
use App\Models\FundingSource;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class FundingSourceResource extends Resource
{
    protected static ?string $model = FundingSource::class;

    protected static ?string $recordTitleAttribute = 'name';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static ?int $navigationSort = 14;

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return __('common.nav_group_master');
    }

    public static function getNavigationLabel(): string
    {
        return __('common.nav_funding_sources');
    }

    public static function getModelLabel(): string
    {
        return 'Sumber Anggaran';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Sumber Anggaran';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->label('Nama Sumber Anggaran')
                ->placeholder('cth: BOS / Kas Sekolah 2026, BANPEM 2025, Komite 2026')
                ->required()
                ->maxLength(150)
                ->helperText('Satu sumber anggaran dapat dipakai berkali-kali untuk banyak batch pembelanjaan.'),

            Select::make('source_type')
                ->label('Jenis Sumber Dana')
                ->options(FundingSource::sourceTypeOptions())
                ->default(FundingSource::TYPE_PURCHASE)
                ->required(),

            TextInput::make('fiscal_year')
                ->label('Tahun Anggaran')
                ->numeric()
                ->minValue(2000)
                ->maxValue(2100)
                ->placeholder((string) date('Y')),

            Toggle::make('is_active')
                ->label('Aktif (tampil di pilihan Barang Masuk)')
                ->default(true),

            Textarea::make('notes')
                ->label('Keterangan')
                ->rows(2)
                ->maxLength(500)
                ->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->withCount('procurements'))
            ->defaultSort('fiscal_year', 'desc')
            ->columns([
                TextColumn::make('name')
                    ->label('Nama Sumber Anggaran')
                    ->searchable()
                    ->sortable()
                    ->description(fn (FundingSource $record): ?string => $record->notes),

                TextColumn::make('source_type')
                    ->label('Jenis')
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        FundingSource::TYPE_PURCHASE => 'info',
                        FundingSource::TYPE_BANPEM => 'success',
                        FundingSource::TYPE_COMMITTEE => 'warning',
                        FundingSource::TYPE_CSR => 'primary',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (?string $state): string => FundingSource::sourceTypeOptions()[$state] ?? (string) $state),

                TextColumn::make('fiscal_year')
                    ->label('Tahun')
                    ->placeholder('—')
                    ->sortable(),

                TextColumn::make('procurements_count')
                    ->label('Batch Belanja')
                    ->badge()
                    ->color('primary')
                    ->sortable(),

                TextColumn::make('total_units')
                    ->label('Total Unit Masuk')
                    ->getStateUsing(fn (FundingSource $record): int => $record->totalUnitsEntered())
                    ->badge()
                    ->color('info'),

                TextColumn::make('total_estimated_value')
                    ->label('Estimasi Nilai (Rp)')
                    ->getStateUsing(fn (FundingSource $record): int => $record->totalEstimatedValue())
                    ->money('IDR', locale: 'id')
                    ->visible(fn (): bool => (bool) auth()->user()?->canViewFinancials()),

                IconColumn::make('is_active')
                    ->label(__('common.active'))
                    ->boolean(),
            ])
            ->filters([
                SelectFilter::make('source_type')
                    ->label('Jenis Sumber Dana')
                    ->options(FundingSource::sourceTypeOptions()),
            ])
            ->recordActions([
                EditAction::make()->label(__('common.edit')),
                DeleteAction::make()->label(__('common.delete')),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListFundingSources::route('/'),
            'create' => CreateFundingSource::route('/create'),
            'edit' => EditFundingSource::route('/{record}/edit'),
        ];
    }
}
