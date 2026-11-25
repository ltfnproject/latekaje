<?php

use App\Services\MemberAccountSync;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('groups') || ! Schema::hasTable('members') || ! Schema::hasTable('users')) {
            return;
        }

        // Pastikan grup standar GURU & KARYAWAN tersedia dan tautkan akun peminjam yatim (member_id NULL)
        MemberAccountSync::repairOrphanedBorrowers();
    }

    public function down(): void
    {
        // Data repair bersifat non-destruktif; tidak perlu menghapus relasi member_id yang valid.
    }
};
