<?php

namespace Tests\Feature;

use App\Filament\Resources\UserResource\Pages\ListUsers;
use App\Models\Group;
use App\Models\Member;
use App\Models\User;
use App\Services\MemberAccountSync;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class AccountMemberUnificationTest extends TestCase
{
    public function test_user_created_as_borrower_syncs_member_and_distinguishes_student_teacher_employee(): void
    {
        $guru = User::updateOrCreate(
            ['email' => 'sync-guru@latekaje.test'],
            ['name' => 'Pak Ahmad', 'code' => 'NIP-G901', 'password' => 'secret1234', 'role' => 'users', 'is_active' => true, 'must_change_password' => false],
        );
        $karyawan = User::updateOrCreate(
            ['email' => 'sync-karyawan@latekaje.test'],
            ['name' => 'Mas Dedi', 'code' => 'NIP-K901', 'password' => 'secret1234', 'role' => 'users', 'is_active' => true, 'must_change_password' => false],
        );
        $siswa = User::updateOrCreate(
            ['email' => 'sync-siswa@latekaje.test'],
            ['name' => 'Rina Siswa', 'code' => 'NIS-S901', 'password' => 'secret1234', 'role' => 'users', 'is_active' => true, 'must_change_password' => false],
        );

        try {
            $mGuru = MemberAccountSync::syncFromUser($guru, [
                'member_type' => Member::TYPE_TEACHER,
                'group' => 'GURU',
                'birth_date' => '1985-04-12',
            ]);
            $mKaryawan = MemberAccountSync::syncFromUser($karyawan, [
                'member_type' => Member::TYPE_EMPLOYEE,
                'group' => 'KARYAWAN',
                'birth_date' => '1991-09-20',
            ]);
            $mSiswa = MemberAccountSync::syncFromUser($siswa, [
                'member_type' => Member::TYPE_STUDENT,
                'group' => 'xi tjkt 1', // sengaja huruf kecil untuk uji normalisasi grup
                'birth_date' => '2009-02-15',
            ]);

            $guru->refresh();
            $karyawan->refresh();
            $siswa->refresh();

            $this->assertNotNull($mGuru);
            $this->assertSame($mGuru->id, $guru->member_id);
            $this->assertTrue($guru->isTeacher());
            $this->assertFalse($guru->isStudent());
            $this->assertSame('GURU', $guru->borrowerGroup());
            $this->assertSame(__('members.type_teacher'), $guru->memberTypeLabel());

            $this->assertNotNull($mKaryawan);
            $this->assertSame($mKaryawan->id, $karyawan->member_id);
            $this->assertTrue($karyawan->isEmployee());
            $this->assertSame('KARYAWAN', $karyawan->borrowerGroup());
            $this->assertSame(__('members.type_employee'), $karyawan->memberTypeLabel());

            $this->assertNotNull($mSiswa);
            $this->assertSame($mSiswa->id, $siswa->member_id);
            $this->assertTrue($siswa->isStudent());
            $this->assertSame('XI TJKT 1', $siswa->borrowerGroup(), 'huruf kapital grup harus dinormalisasi cocok dengan tabel groups');
            $this->assertTrue(Group::where('label', 'GURU')->exists());
            $this->assertTrue(Group::where('label', 'KARYAWAN')->exists());
        } finally {
            $memberIds = array_filter([$guru->member_id, $karyawan->member_id, $siswa->member_id]);
            $guru->delete();
            $karyawan->delete();
            $siswa->delete();
            if ($memberIds !== []) {
                Member::whereIn('id', $memberIds)->delete();
            }
        }
    }

    public function test_two_way_sync_between_member_and_user_and_password_preservation(): void
    {
        $member = Member::updateOrCreate(
            ['code' => 'TWOWAY99'],
            [
                'name' => 'Anggota Sinkron Awal',
                'group' => 'X TJKT 1',
                'member_type' => Member::TYPE_STUDENT,
                'birth_date' => '2008-12-05',
                'aktif' => true,
            ],
        );

        $user = null;

        try {
            // 1. Provision akun pertama kali memakai tanggal lahir DDMMYYYY
            $user = MemberAccountSync::provisionAccountForMember($member);
            $this->assertSame($member->id, $user->member_id);
            $this->assertTrue($user->must_change_password);
            $this->assertTrue(Hash::check('05122008', $user->password));

            // 2. Siswa ganti password sendiri
            $user->update(['password' => 'PasswordSiswaSendiri#2026']);
            $user->refresh();
            $this->assertFalse($user->must_change_password);

            // 3. Re-provision tanpa explicit password tidak boleh menimpa password pribadi siswa
            MemberAccountSync::provisionAccountForMember($member);
            $user->refresh();
            $this->assertTrue(Hash::check('PasswordSiswaSendiri#2026', $user->password));

            // 4. Reset eksplisit mengembalikan password ke DDMMYYYY + must_change_password=true
            MemberAccountSync::provisionAccountForMember($member, resetExistingPassword: true);
            $user->refresh();
            $this->assertTrue(Hash::check('05122008', $user->password));
            $this->assertTrue($user->must_change_password);

            // 5. Edit di Member otomatis sinkron ke User
            $member->update(['name' => 'Nama Diubah Dari Anggota', 'aktif' => false]);
            $user->refresh();
            $this->assertSame('Nama Diubah Dari Anggota', $user->name);
            $this->assertFalse($user->is_active);

            // 6. Edit di User otomatis sinkron balik ke Member
            $user->update(['name' => 'Nama Diubah Dari User', 'is_active' => true]);
            $member->refresh();
            $this->assertSame('Nama Diubah Dari User', $member->name);
            $this->assertTrue($member->aktif);
        } finally {
            $user?->delete();
            $member->delete();
        }
    }

    public function test_orphaned_borrower_repaired_and_loan_create_page_renders_cleanly(): void
    {
        // Simulasi bug persis seperti screenshot UAT: akun `budi` dengan code tetapi member_id NULL
        $orphan = User::updateOrCreate(
            ['email' => 'budi-yatim@latekaje.test'],
            [
                'name' => 'Budi Yatim',
                'code' => 'BUDIYATIM99',
                'member_id' => null,
                'password' => 'secret1234',
                'role' => 'users',
                'is_active' => true,
                'must_change_password' => false,
            ],
        );

        try {
            $this->assertNull($orphan->member_id);

            // Jalankan repair otomatis
            $repairedCount = MemberAccountSync::repairOrphanedBorrowers();
            $this->assertGreaterThanOrEqual(1, $repairedCount);

            $orphan->refresh();
            $this->assertNotNull($orphan->member_id, 'akun yatim wajib tertaut ke Member setelah repair');
            $this->assertNotEmpty($orphan->borrowerGroup(), 'akun yatim wajib memiliki grup valid setelah repair');
            $this->assertTrue(Group::where('label', $orphan->borrowerGroup())->exists());

            // Halaman Pinjam Alat (/admin/loans/create) harus render 200 dengan identitas kategori & grup terisi
            $this->flushSession();
            $res = $this->actingAs($orphan)->get('/admin/loans/create');
            $res->assertOk();
            $res->assertSee('BUDIYATIM99');
            $res->assertSee(__('members.type_student'));
        } finally {
            $memberId = $orphan->member_id;
            $orphan->delete();
            if ($memberId) {
                Member::whereKey($memberId)->delete();
            }
        }
    }

    public function test_user_and_member_resources_render_and_indonesian_validation_loaded(): void
    {
        app()->setLocale('id');

        $msg = __('validation.required', ['attribute' => __('validation.attributes.group')]);
        $this->assertSame('Grup / Asal wajib diisi.', $msg);
        $this->assertNotSame('validation.required', $msg);

        $superadmin = User::where('role', 'superadmin')->firstOrFail();

        $this->flushSession();
        $this->actingAs($superadmin);
        $this->get('/admin/user-resource/users')->assertOk();
        $this->get('/admin/user-resource/users/create')->assertOk();
        $this->get('/admin/members')->assertOk();
        $this->get('/admin/members/create')->assertOk();
    }

    public function test_users_table_default_sorts_by_highest_role_then_code(): void
    {
        $superadmin = User::where('role', 'superadmin')->firstOrFail();

        $created = collect([
            User::create(['name' => 'Sort User 10010', 'email' => 'sort-u-10010@latekaje.test', 'code' => '10010', 'password' => 'secret1234', 'role' => 'users', 'is_active' => true, 'must_change_password' => false]),
            User::create(['name' => 'Sort User 900', 'email' => 'sort-u-900@latekaje.test', 'code' => '900', 'password' => 'secret1234', 'role' => 'users', 'is_active' => true, 'must_change_password' => false]),
            User::create(['name' => 'Sort User 1002', 'email' => 'sort-u-1002@latekaje.test', 'code' => '1002', 'password' => 'secret1234', 'role' => 'users', 'is_active' => true, 'must_change_password' => false]),
            User::create(['name' => 'Sort User NoCode', 'email' => 'sort-u-nocode@latekaje.test', 'code' => null, 'password' => 'secret1234', 'role' => 'users', 'is_active' => true, 'must_change_password' => false]),
            User::create(['name' => 'Sort Assistant', 'email' => 'sort-asst@latekaje.test', 'code' => '3001', 'password' => 'secret1234', 'role' => 'assistant', 'is_active' => true, 'must_change_password' => false]),
            User::create(['name' => 'Sort Staff', 'email' => 'sort-staff@latekaje.test', 'code' => '2001', 'password' => 'secret1234', 'role' => 'staff', 'is_active' => true, 'must_change_password' => false]),
            User::create(['name' => 'Sort KKK', 'email' => 'sort-kkk@latekaje.test', 'code' => '1001', 'password' => 'secret1234', 'role' => 'admin', 'is_active' => true, 'must_change_password' => false]),
        ]);

        try {
            $this->actingAs($superadmin);

            $component = Livewire::test(ListUsers::class);
            $ids = $component->instance()->getFilteredSortedTableQuery()->pluck('id')->all();

            $positions = [
                'superadmin' => array_search($superadmin->id, $ids, true),
                'admin' => array_search($created[6]->id, $ids, true),
                'staff' => array_search($created[5]->id, $ids, true),
                'assistant' => array_search($created[4]->id, $ids, true),
                'u_900' => array_search($created[1]->id, $ids, true),
                'u_1002' => array_search($created[2]->id, $ids, true),
                'u_10010' => array_search($created[0]->id, $ids, true),
                'u_nocode' => array_search($created[3]->id, $ids, true),
            ];

            foreach ($positions as $label => $pos) {
                $this->assertIsInt($pos, "Record {$label} harus muncul pada hasil query daftar user");
            }

            $this->assertLessThan($positions['admin'], $positions['superadmin']);
            $this->assertLessThan($positions['staff'], $positions['admin']);
            $this->assertLessThan($positions['assistant'], $positions['staff']);
            $this->assertLessThan($positions['u_900'], $positions['assistant']);
            $this->assertLessThan($positions['u_1002'], $positions['u_900']);
            $this->assertLessThan($positions['u_10010'], $positions['u_1002']);
            $this->assertLessThan($positions['u_nocode'], $positions['u_10010']);
        } finally {
            $created->each->delete();
        }
    }
}
