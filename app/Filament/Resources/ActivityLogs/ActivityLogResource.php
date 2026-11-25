<?php

namespace App\Filament\Resources\ActivityLogs;

use App\Filament\Resources\ActivityLogs\Pages\ListActivityLogs;
use App\Models\ActivityLog;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class ActivityLogResource extends Resource
{
    protected static ?string $model = ActivityLog::class;

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedDocumentMagnifyingGlass;

    protected static ?int $navigationSort = 35;

    protected static ?string $slug = 'activity-logs';

    public static function getNavigationGroup(): string | \UnitEnum | null
    {
        return __('common.nav_group_system');
    }

    public static function getNavigationLabel(): string
    {
        return __('common.nav_activity_logs');
    }

    public static function getModelLabel(): string
    {
        return __('common.activity_log_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('common.nav_activity_logs');
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
                        str_contains($state, 'delete'), str_contains($state, 'deactivat'), str_contains($state, 'force_close'), str_contains($state, 'revoke') => 'danger',
                        str_contains($state, 'maintenance'), str_contains($state, 'setting') => 'warning',
                        str_contains($state, 'return'), str_contains($state, 'activat'), str_contains($state, 'create') => 'success',
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
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('action')
                    ->label(__('common.log_action'))
                    ->options(fn (): array => ActivityLog::query()
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
