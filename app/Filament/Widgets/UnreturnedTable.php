<?php

namespace App\Filament\Widgets;

use App\Models\Loan;
use App\Services\RecapService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class UnreturnedTable extends BaseWidget
{
    protected static ?int $sort = 4;

    protected int | string | array $columnSpan = 'full';

    public static function canView(): bool
    {
        $user = auth()->user();

        return $user !== null && $user->is_active && ! $user->isBorrower();
    }

    protected function getTableHeading(): string | \Illuminate\Contracts\Support\Htmlable | null
    {
        return __('dashboard.unreturned_heading');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Loan::query()
                    ->with(['assetItem.asset'])
                    ->where('status', 'aktif')
                    ->orderBy('tanggal_pinjam')
            )
            ->headerActions([
                Action::make('kirimRekap')
                    ->label(__('recap.send_now'))
                    ->icon('heroicon-o-paper-airplane')
                    ->color('info')
                    ->visible(fn (): bool => in_array(auth()->user()?->role, ['superadmin', 'admin', 'staff', 'assistant'], true))
                    ->action(function (): void {
                        abort_unless(in_array(auth()->user()?->role, ['superadmin', 'admin', 'staff', 'assistant'], true), 403);

                        $result = RecapService::sendNow();

                        Notification::make()
                            ->title(__('recap.sent_title', ['total' => $result['total']]))
                            ->body(implode(' | ', array_map(
                                fn ($k, $v) => "[$k] $v",
                                array_keys($result['channels']),
                                $result['channels']
                            )))
                            ->success()
                            ->send();
                    }),
            ])
            ->columns([
                TextColumn::make('assetItem.nomor_seri_atau_qr')
                    ->label(__('dashboard.col_qr'))
                    ->formatStateUsing(fn (?string $state): string => $state ?? __('dashboard.unit_deleted'))
                    ->searchable(),

                TextColumn::make('assetItem.asset.nama_alat')
                    ->label(__('dashboard.col_tool'))
                    ->searchable(),

                TextColumn::make('borrower_name')
                    ->label(__('dashboard.col_borrower'))
                    ->description(fn ($record) => $record->group)
                    ->searchable(),

                TextColumn::make('lama_pinjam')
                    ->label(__('dashboard.col_duration'))
                    ->badge()
                    ->color(function ($record): string {
                        $days = $record->tanggal_pinjam
                            ? (int) floor($record->tanggal_pinjam->diffInDays(now()))
                            : 0;

                        return $days >= 7 ? 'danger' : ($days >= 3 ? 'warning' : 'gray');
                    })
                    ->state(fn ($record): string => Loan::formatDuration($record->tanggal_pinjam)),

                TextColumn::make('return_pin')
                    ->label(__('dashboard.col_pin'))
                    ->badge()
                    ->color('info')
                    ->copyable()
                    ->visible(fn () => ! auth()->user()?->isBorrower() || filled(auth()->user()?->code) || filled(auth()->user()?->member_id))
                    ->formatStateUsing(function (?string $state, $record): string {
                        if (! $state) {
                            return '-';
                        }

                        return auth()->user()?->ownsLoan($record) ?? false ? $state : '••••••';
                    }),
            ])
            ->paginated([10, 25, 50])
            ->defaultPaginationPageOption(10);
    }
}
