<?php

namespace App\Filament\Resources\Loans\Pages;

use App\Filament\Resources\Loans\LoanResource;
use App\Models\Loan;
use App\Models\Member;
use App\Services\LoanService;
use App\Services\MemberAccountSync;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateLoan extends CreateRecord
{
    protected static string $resource = LoanResource::class;

    protected function handleRecordCreation(array $data): Loan
    {
        $user = auth()->user();
        $memberId = isset($data['member_id']) && $data['member_id'] !== '' ? (int) $data['member_id'] : null;

        // Anti-impersonasi: akun peminjam wajib memakai identitas miliknya sendiri di sisi server.
        if ($user?->isBorrower()) {
            if ($user->isPersonalBorrower()) {
                $member = $user->resolvedMember();

                // Self-heal profil jika akun lama belum tertaut atau grup masih kosong
                if (! $member || blank($member->group)) {
                    $chosenGroup = trim((string) ($data['group'] ?? ''));
                    $member = MemberAccountSync::syncFromUser($user, array_filter([
                        'group' => $chosenGroup !== '' ? $chosenGroup : null,
                    ], fn ($v) => $v !== null));
                }

                $memberId = $member?->id ?? $user->member_id;
                $data['code'] = $member?->code ?? $user->code;
                $data['borrower_name'] = $member?->name ?? $user->name;
                $data['group'] = $member?->group ?? ($data['group'] ?? '-');
            } elseif (! config('latekaje.allow_generic_kiosk', false)) {
                abort(403, 'Akun peminjam belum terhubung ke data anggota.');
            }
        } elseif ($memberId && ($member = Member::find($memberId))) {
            // Konsistensi: bila petugas memilih anggota, snapshot identitas diambil dari master.
            $data['code'] = $member->code;
            $data['borrower_name'] = $member->name;
            $data['group'] = $member->group;
        }

        $loan = LoanService::borrow(
            (int) $data['asset_item_id'],
            (string) $data['borrower_name'],
            (string) $data['group'],
            (string) ($data['return_pin'] ?? ''),
            $memberId,
            isset($data['code']) && $data['code'] !== '' ? (string) $data['code'] : null,
        );

        Notification::make()
            ->title(__('loans.created_title', ['pin' => $loan->return_pin]))
            ->body(__('loans.created_body'))
            ->success()
            ->persistent()
            ->send();

        return $loan;
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }
}
