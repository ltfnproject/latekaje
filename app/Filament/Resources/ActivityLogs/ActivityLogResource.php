<?php

namespace App\Filament\Resources\ActivityLogs;

use App\Filament\Resources\ActivityLogs\Pages\ListActivityLogs;
use App\Models\ActivityLog;
use App\Models\Loan;
use App\Policies\ActivityLogPolicy;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ActivityLogResource extends Resource
{
    protected static ?string $model = ActivityLog::class;

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedDocumentMagnifyingGlass;

    protected static ?int $navigationSort = 35;

    protected static ?string $slug = 'activity-logs';

    public static function getNavigationGroup(): string | \UnitEnum | null
    {
        $user = auth()->user();

        if ($user?->isBorrower()) {
            return __('common.nav_group_transactions');
        }

        if ($user && in_array($user->role, ['staff', 'assistant'], true)) {
            return __('common.nav_group_reports');
        }

        return __('common.nav_group_system');
    }

    public static function getNavigationLabel(): string
    {
        $user = auth()->user();

        if ($user?->isBorrower()) {
            return __('common.nav_my_activity_logs');
        }

        if ($user && in_array($user->role, ['staff', 'assistant'], true)) {
            return __('common.nav_operational_logs');
        }

        return __('common.nav_activity_logs');
    }

    public static function getModelLabel(): string
    {
        return __('common.activity_log_label');
    }

    public static function getPluralModelLabel(): string
    {
        return self::getNavigationLabel();
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with('user');
        $user = auth()->user();

        if (! $user || ! $user->is_active) {
            return $query->whereRaw('1 = 0');
        }

        // Super Admin & KKK (Ketua Jurusan) melihat seluruh audit log
        if (in_array($user->role, ['superadmin', 'admin'], true)) {
            return $query;
        }

        // Toolman & Admin Prakerin melihat log operasional (peminjaman, barang terpasang/copot, kabel massal, bahan habis pakai)
        if (in_array($user->role, ['staff', 'assistant'], true)) {
            return $query->where(function (Builder $w): void {
                foreach (ActivityLogPolicy::OPERATIONAL_PREFIXES as $prefix) {
                    $w->orWhere('action', 'like', $prefix.'%');
                }
            });
        }

        // Peminjam hanya melihat log peminjaman milik dirinya sendiri (fail-closed)
        if ($user->isBorrower()) {
            if (! $user->code && ! $user->member_id) {
                return $query->whereRaw('1 = 0');
            }

            $ownLoanIds = Loan::query()
                ->where(function (Builder $w) use ($user): void {
                    if ($user->code) {
                        $w->orWhere('code', $user->code);
                    }
                    if ($user->member_id) {
                        $w->orWhere('member_id', $user->member_id);
                    }
                })
                ->pluck('id')
                ->all();

            if ($ownLoanIds === []) {
                return $query->whereRaw('1 = 0');
            }

            return $query
                ->where('action', 'like', 'loan.%')
                ->where('subject_type', 'Loan')
                ->whereIn('subject_id', $ownLoanIds);
        }

        return $query->whereRaw('1 = 0');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        $isAdminViewer = in_array(auth()->user()?->role, ['superadmin', 'admin'], true);

        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')
                    ->label(__('common.log_time'))
                    ->dateTime('d M Y H:i:s')
                    ->sortable(),

                TextColumn::make('user.name')
                    ->label(__('common.log_actor'))
                    ->placeholder('Sistem')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('action')
                    ->label(__('common.log_action'))
                    ->badge()
                    ->color(fn (string $state): string => match (true) {
                        str_contains($state, 'delete'), str_contains($state, 'deactivat'), str_contains($state, 'force_close'), str_contains($state, 'revoke'), str_contains($state, 'retire') => 'danger',
                        str_contains($state, 'maintenance'), str_contains($state, 'setting'), str_contains($state, 'delegation'), str_contains($state, 'strict'), str_contains($state, 'used') => 'warning',
                        str_contains($state, 'return'), str_contains($state, 'activat'), str_contains($state, 'create'), str_contains($state, 'restock'), str_contains($state, 'generated') => 'success',
                        default => 'info',
                    })
                    ->searchable(),

                TextColumn::make('description')
                    ->label(__('common.log_description'))
                    ->wrap()
                    ->searchable(),

                TextColumn::make('subject_type')
                    ->label(__('common.log_subject'))
                    ->formatStateUsing(fn (?string $state, $record): string => $state ? "{$state}#{$record->subject_id}" : '-')
                    ->badge()
                    ->color('gray')
                    ->toggleable(),

                TextColumn::make('ip_address')
                    ->label('IP')
                    ->placeholder('-')
                    ->visible($isAdminViewer)
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('action')
                    ->label(__('common.log_action'))
                    ->options(fn (): array => self::getEloquentQuery()
                        ->reorder()
                        ->select('action')
                        ->distinct()
                        ->orderBy('action')
                        ->pluck('action', 'action')
                        ->all()),
            ])
            ->paginated([25, 50, 100])
            ->defaultPaginationPageOption(25);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListActivityLogs::route('/'),
        ];
    }
}
