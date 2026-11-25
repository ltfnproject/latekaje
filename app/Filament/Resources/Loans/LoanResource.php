<?php

namespace App\Filament\Resources\Loans;

use App\Filament\Resources\Loans\Pages\CreateLoan;
use App\Filament\Resources\Loans\Pages\EditLoan;
use App\Filament\Resources\Loans\Pages\ListLoans;
use App\Filament\Resources\Loans\Schemas\LoanForm;
use App\Filament\Resources\Loans\Tables\LoansTable;
use App\Models\Loan;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class LoanResource extends Resource
{
    protected static ?string $model = Loan::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'borrower_name';

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return __('common.nav_group_transactions');
    }

    public static function getNavigationLabel(): string
    {
        return __('loans.nav_label');
    }

    public static function getModelLabel(): string
    {
        return __('loans.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('loans.plural_model_label');
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = auth()->user();

        if (! $user) {
            return $query->whereRaw('1 = 0');
        }

        // Peminjam (`role=users`) HANYA boleh melihat pinjaman miliknya sendiri.
        // Fail-closed bila akun tidak punya identitas (`code`/`member_id`), kecuali mode kiosk diaktifkan eksplisit.
        if ($user->isBorrower()) {
            if ($user->code || $user->member_id) {
                $query->where(function (Builder $w) use ($user): void {
                    if ($user->code) {
                        $w->orWhere('code', $user->code);
                    }
                    if ($user->member_id) {
                        $w->orWhere('member_id', $user->member_id);
                    }
                });
            } elseif (! config('latekaje.allow_generic_kiosk', false)) {
                $query->whereRaw('1 = 0');
            }
        }

        return $query;
    }

    public static function form(Schema $schema): Schema
    {
        return LoanForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return LoansTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListLoans::route('/'),
            'create' => CreateLoan::route('/create'),
            'edit' => EditLoan::route('/{record}/edit'),
        ];
    }
}
