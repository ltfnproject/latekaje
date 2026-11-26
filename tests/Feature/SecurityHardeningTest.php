<?php

namespace Tests\Feature;

use App\Filament\Widgets\ActiveLoansTable;
use App\Filament\Widgets\AttentionUnitsTable;
use App\Filament\Widgets\InventoryConditionChart;
use App\Filament\Widgets\InventoryLocationChart;
use App\Filament\Widgets\InventoryOverview;
use App\Filament\Widgets\LoanActivityChart;
use App\Filament\Widgets\UnreturnedTable;
use App\Models\ActivityLog;
use App\Models\Asset;
use App\Models\AssetItem;
use App\Models\Group;
use App\Models\Loan;
use App\Models\Location;
use App\Models\Member;
use App\Models\Setting;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\LoanService;
use App\Services\SecureImageService;
use App\Services\WebhookClient;
use App\Support\SafeUrl;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class SecurityHardeningTest extends TestCase
{
    public function test_borrower_blocked_from_print_qr_lokasi_settings_users_activity_logs_and_widgets(): void
    {
        $member = Member::updateOrCreate(
            ['code' => 'SEC9001'],
            ['name' => 'Siswa Uji Keamanan', 'group' => 'XI TJKT 1', 'member_type' => Member::TYPE_STUDENT, 'aktif' => true],
        );

        $borrower = User::updateOrCreate(
            ['email' => 'sec-borrower@latekaje.test'],
            [
                'name' => 'Siswa Uji Keamanan',
                'code' => 'SEC9001',
                'member_id' => $member->id,
                'password' => 'secret1234',
                'role' => 'users',
                'is_active' => true,
                'must_change_password' => false,
            ],
        );

        $toolman = User::updateOrCreate(
            ['email' => 'sec-toolman@latekaje.test'],
            [
                'name' => 'Toolman Uji',
                'password' => 'secret1234',
                'role' => 'staff',
                'is_active' => true,
                'must_change_password' => false,
            ],
        );

        $kkk = User::updateOrCreate(
            ['email' => 'sec-kkk@latekaje.test'],
            [
                'name' => 'KKK Uji',
                'password' => 'secret1234',
                'role' => 'admin',
                'is_active' => true,
                'must_change_password' => false,
            ],
        );

        try {
            // Borrower ditolak pada endpoint sensitif (activity-logs kini role-scoped: hanya lihat log pinjaman milik sendiri)
            $this->actingAs($borrower);
            $this->get('/print-qr?ids=all')->assertForbidden();
            $this->get('/admin/data-per-lokasi')->assertForbidden();
            $this->get('/admin/setting')->assertForbidden();
            $this->get('/admin/user-resource/users')->assertForbidden();
            $this->get('/admin/activity-logs')->assertOk();

            foreach ([
                ActiveLoansTable::class,
                AttentionUnitsTable::class,
                InventoryConditionChart::class,
                InventoryLocationChart::class,
                InventoryOverview::class,
                LoanActivityChart::class,
                UnreturnedTable::class,
            ] as $widgetClass) {
                $this->assertFalse($widgetClass::canView(), "{$widgetClass} tidak boleh terlihat oleh borrower");
            }

            // Toolman boleh cetak QR, lihat lokasi, & lihat log operasional terbatas, tapi tidak boleh buka setting
            $this->flushSession();
            $this->actingAs($toolman);
            $this->get('/print-qr?ids=all')->assertOk();
            $this->get('/admin/data-per-lokasi')->assertOk();
            $this->get('/admin/setting')->assertForbidden();
            $this->get('/admin/activity-logs')->assertOk();
            $this->assertTrue(InventoryOverview::canView());

            // KKK (admin) boleh buka activity logs & setting & print QR
            $this->flushSession();
            $this->actingAs($kkk);
            $this->get('/admin/activity-logs')->assertOk();
            $this->get('/admin/setting')->assertOk();
            $this->get('/print-qr?ids=all')->assertOk();
        } finally {
            $borrower->delete();
            $toolman->delete();
            $kkk->delete();
            $member->delete();
        }
    }

    public function test_anti_idor_loan_policy_and_private_return_photo_with_sanitization(): void
    {
        // Buat PNG valid + sisipkan payload teks berbahaya di ekor file untuk memastikan re-encode membersihkannya
        $img = imagecreatetruecolor(40, 30);
        ob_start();
        imagepng($img);
        $pngBytes = (string) ob_get_clean();
        imagedestroy($img);

        $polyglotBytes = $pngBytes.'<?php echo "evil"; ?>';
        $cleanPath = SecureImageService::sanitizeBytesAndStore($polyglotBytes, 'local', 'returns');

        $this->assertTrue(Storage::disk('local')->exists($cleanPath), 'foto hasil sanitasi harus tersimpan di disk local private');
        $this->assertFalse(Storage::disk('public')->exists($cleanPath), 'foto bukti tidak boleh tersimpan di disk public');

        $storedBytes = (string) Storage::disk('local')->get($cleanPath);
        $info = @getimagesizefromstring($storedBytes);
        $this->assertIsArray($info);
        $this->assertSame(IMAGETYPE_JPEG, $info[2], 'foto bukti harus di-reencode menjadi JPEG bersih');
        $this->assertStringNotContainsString('<?php', $storedBytes, 'payload polyglot harus dibersihkan');

        // File non-gambar wajib ditolak
        $rejected = false;
        try {
            SecureImageService::sanitizeBytesAndStore('<?php echo 1; ?>', 'local', 'returns');
        } catch (ValidationException) {
            $rejected = true;
        }
        $this->assertTrue($rejected, 'file non-gambar wajib ditolak oleh SecureImageService');

        $memberA = Member::updateOrCreate(
            ['code' => 'IDORA01'],
            ['name' => 'Peminjam A', 'group' => 'XI TJKT 1', 'member_type' => Member::TYPE_STUDENT, 'aktif' => true],
        );
        $memberB = Member::updateOrCreate(
            ['code' => 'IDORB02'],
            ['name' => 'Peminjam B', 'group' => 'XI TJKT 2', 'member_type' => Member::TYPE_STUDENT, 'aktif' => true],
        );

        $userA = User::updateOrCreate(
            ['email' => 'idora@latekaje.test'],
            ['name' => 'Peminjam A', 'code' => 'IDORA01', 'member_id' => $memberA->id, 'password' => 'secret1234', 'role' => 'users', 'is_active' => true, 'must_change_password' => false],
        );
        $userB = User::updateOrCreate(
            ['email' => 'idorb@latekaje.test'],
            ['name' => 'Peminjam B', 'code' => 'IDORB02', 'member_id' => $memberB->id, 'password' => 'secret1234', 'role' => 'users', 'is_active' => true, 'must_change_password' => false],
        );
        $staff = User::updateOrCreate(
            ['email' => 'idorstaff@latekaje.test'],
            ['name' => 'Toolman IDOR', 'password' => 'secret1234', 'role' => 'staff', 'is_active' => true, 'must_change_password' => false],
        );

        $loanA = Loan::create([
            'asset_item_id' => null,
            'borrower_name' => 'Peminjam A',
            'group' => 'XI TJKT 1',
            'code' => 'IDORA01',
            'member_id' => $memberA->id,
            'status' => 'kembali',
            'tanggal_pinjam' => now()->subHour(),
            'tanggal_kembali' => now(),
            'return_pin' => '112233',
            'return_photo_path' => $cleanPath,
        ]);

        try {
            $this->assertTrue(Gate::forUser($userA)->allows('view', $loanA), 'pemilik pinjaman harus boleh view');
            $this->assertFalse(Gate::forUser($userB)->allows('view', $loanA), 'peminjam lain tidak boleh view (anti-IDOR)');

            $url = route('files.loans.return-photo', $loanA);

            $this->actingAs($userB)->get($url)->assertForbidden();

            $resOwner = $this->actingAs($userA)->get($url);
            $resOwner->assertOk();
            $resOwner->assertHeader('X-Content-Type-Options', 'nosniff');
            $this->assertStringContainsString('private', (string) $resOwner->headers->get('Cache-Control'));
            $this->assertStringContainsString('no-store', (string) $resOwner->headers->get('Cache-Control'));

            $this->actingAs($staff)->get($url)->assertOk();
        } finally {
            Storage::disk('local')->delete($cleanPath);
            $loanA->delete();
            $userA->delete();
            $userB->delete();
            $staff->delete();
            $memberA->delete();
            $memberB->delete();
        }
    }

    public function test_role_governance_assistant_restricted_and_kkk_toolman_approve(): void
    {
        $superadmin = User::where('role', 'superadmin')->firstOrFail();

        $kkk = User::updateOrCreate(
            ['email' => 'gov-kkk@latekaje.test'],
            ['name' => 'Gov KKK', 'password' => 'secret1234', 'role' => 'admin', 'is_active' => true, 'must_change_password' => false],
        );
        $toolman = User::updateOrCreate(
            ['email' => 'gov-toolman@latekaje.test'],
            ['name' => 'Gov Toolman', 'password' => 'secret1234', 'role' => 'staff', 'is_active' => true, 'must_change_password' => false],
        );
        $assistant = User::updateOrCreate(
            ['email' => 'gov-assistant@latekaje.test'],
            ['name' => 'Gov Prakerin', 'password' => 'secret1234', 'role' => 'assistant', 'is_active' => true, 'must_change_password' => false],
        );
        $borrower = User::updateOrCreate(
            ['email' => 'gov-borrower@latekaje.test'],
            ['name' => 'Gov Siswa', 'password' => 'secret1234', 'role' => 'users', 'is_active' => true, 'must_change_password' => false],
        );

        $location = Location::firstOrCreate(['key' => 'gov_loc'], ['label' => 'Gov Loc']);
        $group = Group::firstOrCreate(['key' => 'gov_grp'], ['label' => 'Gov Group']);
        $asset = Asset::firstOrCreate(
            ['kode_aset' => 'GOV-AST-01'],
            ['nama_alat' => 'Alat Governance', 'jenis' => 'Uji', 'spesifikasi' => 'v4', 'kegunaan' => 'praktik', 'stok' => 1],
        );
        $item = AssetItem::firstOrCreate(
            ['nomor_seri_atau_qr' => 'GOV-QR-001'],
            ['asset_id' => $asset->id, 'status' => 'tersedia', 'kondisi' => 'baik', 'location_id' => $location->id],
        );
        $member = Member::firstOrCreate(['code' => 'GOVMEM1'], ['name' => 'Anggota Gov', 'group' => 'X TJKT 1', 'aktif' => true]);
        $loan = Loan::create([
            'asset_item_id' => null,
            'borrower_name' => 'Anggota Gov',
            'group' => 'X TJKT 1',
            'code' => 'GOVMEM1',
            'member_id' => $member->id,
            'status' => 'kembali',
            'tanggal_pinjam' => now(),
            'return_pin' => '998877',
        ]);
        $log = ActivityLogger::log('gov.check', 'Cek immutability audit log', $loan, [], $superadmin);

        try {
            // ACC pinjaman: hanya Superadmin, KKK (admin), dan Toolman (staff)
            $this->assertTrue($superadmin->canApproveLoans());
            $this->assertTrue($kkk->canApproveLoans());
            $this->assertTrue($toolman->canApproveLoans());
            $this->assertFalse($assistant->canApproveLoans(), 'Admin Lab/Prakerin tidak boleh ACC kritis');
            $this->assertFalse($borrower->canApproveLoans());

            // Finansial: hanya Superadmin & KKK (admin)
            $this->assertTrue($superadmin->canViewFinancials());
            $this->assertTrue($kkk->canViewFinancials());
            $this->assertFalse($toolman->canViewFinancials());
            $this->assertFalse($assistant->canViewFinancials());
            $this->assertFalse($borrower->canViewFinancials());

            // Admin Lab (assistant) dibatasi: tidak bisa delete apa pun, tidak bisa update loan, tidak bisa buka setting/audit
            $this->assertFalse(Gate::forUser($assistant)->allows('delete', $item));
            $this->assertFalse(Gate::forUser($assistant)->allows('delete', $asset));
            $this->assertFalse(Gate::forUser($assistant)->allows('delete', $member));
            $this->assertFalse(Gate::forUser($assistant)->allows('delete', $loan));
            $this->assertFalse(Gate::forUser($assistant)->allows('delete', $location));
            $this->assertFalse(Gate::forUser($assistant)->allows('delete', $group));
            $this->assertFalse(Gate::forUser($assistant)->allows('update', $loan));
            $this->assertFalse(Gate::forUser($assistant)->allows('approve', $loan));

            $this->actingAs($assistant);
            $this->get('/admin/setting')->assertForbidden();
            // Admin TJKT (Prakerin) boleh buka log operasional terbatas, namun tidak boleh melihat log admin (gov.check)
            $this->get('/admin/activity-logs')->assertOk();
            $this->assertFalse(Gate::forUser($assistant)->allows('view', $log));

            // Audit log bersifat append-only (immutable) bahkan untuk Superadmin
            $this->assertNotNull($log);
            $this->assertFalse(Gate::forUser($superadmin)->allows('delete', $log));
            $this->assertFalse(Gate::forUser($superadmin)->allows('update', $log));
            $this->assertFalse(Gate::forUser($kkk)->allows('delete', $log));
        } finally {
            $log?->delete();
            $loan->delete();
            $item->delete();
            $asset->delete();
            $member->delete();
            $group->delete();
            $location->delete();
            $kkk->delete();
            $toolman->delete();
            $assistant->delete();
            $borrower->delete();
        }
    }

    public function test_setting_secrets_encrypted_at_rest_and_ssrf_blocked(): void
    {
        $prevGowaPass = Setting::where('key', 'gowa_pass')->value('value');
        $prevWebhookSecret = Setting::where('key', 'webhook_secret')->value('value');

        try {
            Setting::set('gowa_pass', 'rahasia-gowa-123');
            Setting::set('webhook_secret', 'wh-sec-999');

            $rawGowa = (string) DB::table('settings')->where('key', 'gowa_pass')->value('value');
            $rawWebhook = (string) DB::table('settings')->where('key', 'webhook_secret')->value('value');

            $this->assertStringStartsWith('enc::', $rawGowa);
            $this->assertStringNotContainsString('rahasia-gowa-123', $rawGowa);
            $this->assertSame('rahasia-gowa-123', Setting::get('gowa_pass'));

            $this->assertStringStartsWith('enc::', $rawWebhook);
            $this->assertStringNotContainsString('wh-sec-999', $rawWebhook);
            $this->assertSame('wh-sec-999', Setting::get('webhook_secret'));

            // Anti-SSRF checks
            config(['latekaje.safe_url_allowed_hosts' => []]);
            $this->assertFalse(SafeUrl::isSafe('http://169.254.169.254/latest/meta-data/'));
            $this->assertFalse(SafeUrl::isSafe('file:///etc/passwd'));
            $this->assertFalse(SafeUrl::isSafe('http://127.0.0.1:3306/'));
            $this->assertFalse(SafeUrl::isSafe('http://localhost/admin'));
            $this->assertFalse(SafeUrl::isSafe('http://10.0.0.5/'));
            $this->assertFalse(SafeUrl::isSafe('http://192.168.1.1/'));
            $this->assertFalse(SafeUrl::isSafe('http://100.64.0.10:8099/send'));
            $this->assertTrue(SafeUrl::isSafe('https://example.com/webhook'));

            $blockedRes = WebhookClient::post('http://169.254.169.254/latest/meta-data/', ['ping' => true]);
            $this->assertFalse($blockedRes['ok']);
            $this->assertStringContainsString('[blocked]', $blockedRes['body']);

            // Allowlist internal host (kecuali cloud metadata yang SELALU diblokir)
            config(['latekaje.safe_url_allowed_hosts' => ['core-gowa', '100.64.0.10', '169.254.169.254']]);
            $this->assertTrue(SafeUrl::isSafe('http://core-gowa:3000/app/devices'));
            $this->assertTrue(SafeUrl::isSafe('http://100.64.0.10:8099/send'));
            $this->assertFalse(SafeUrl::isSafe('http://169.254.169.254/latest/meta-data/'), 'metadata cloud wajib tetap diblokir walau masuk allowlist');
        } finally {
            config(['latekaje.safe_url_allowed_hosts' => []]);
            if ($prevGowaPass === null) {
                Setting::where('key', 'gowa_pass')->delete();
            } else {
                DB::table('settings')->where('key', 'gowa_pass')->update(['value' => $prevGowaPass]);
            }
            if ($prevWebhookSecret === null) {
                Setting::where('key', 'webhook_secret')->delete();
            } else {
                DB::table('settings')->where('key', 'webhook_secret')->update(['value' => $prevWebhookSecret]);
            }
        }
    }

    public function test_inactive_user_blocked_and_must_change_password_enforced(): void
    {
        $member = Member::updateOrCreate(
            ['code' => 'SECCHG01'],
            ['name' => 'Siswa Ganti Pass', 'group' => 'X TJKT 1', 'member_type' => Member::TYPE_STUDENT, 'birth_date' => '2008-08-17', 'aktif' => true],
        );

        $user = User::updateOrCreate(
            ['email' => 'sec-active@latekaje.test'],
            [
                'name' => 'Siswa Ganti Pass',
                'code' => 'SECCHG01',
                'member_id' => $member->id,
                'password' => '17082008',
                'role' => 'users',
                'is_active' => false,
                'must_change_password' => true,
            ],
        );

        try {
            // 1. Akun nonaktif tidak boleh akses panel dan langsung ditendang middleware
            $this->assertFalse($user->canAccessPanel(Filament::getDefaultPanel()));
            $resInactive = $this->actingAs($user)->get('/admin/loans');
            $resInactive->assertRedirect();
            $this->assertStringContainsString('/admin/login', (string) $resInactive->headers->get('Location'));

            // 2. Akun aktif tapi must_change_password=true diarahkan ke halaman Profil
            $user->update(['is_active' => true, 'must_change_password' => true]);
            $this->flushSession();
            $resForceChange = $this->actingAs($user->fresh())->get('/admin/loans');
            $resForceChange->assertRedirect();
            $this->assertStringContainsString('/admin/profile', (string) $resForceChange->headers->get('Location'));

            // Halaman Profil sendiri tetap dapat diakses
            $this->actingAs($user->fresh())->get('/admin/profile')->assertOk();

            // 3. Setelah ganti password, flag must_change_password otomatis false dan bisa akses /admin/loans
            $user->update(['password' => 'PasswordBaruKuat#2026']);
            $user->refresh();
            $this->assertFalse($user->must_change_password);
            $this->flushSession();
            $this->actingAs($user)->get('/admin/loans')->assertOk();
        } finally {
            $user->delete();
            $member->delete();
        }
    }

    public function test_member_dob_ddmmyyyy_default_password_and_login(): void
    {
        $memberDob = Member::updateOrCreate(
            ['code' => 'DOB20261'],
            ['name' => 'Siswa DOB', 'group' => 'X TJKT 1', 'member_type' => Member::TYPE_STUDENT, 'birth_date' => '2008-08-17', 'aktif' => true],
        );

        $memberNoDob = Member::updateOrCreate(
            ['code' => 'NODOB2026'],
            ['name' => 'Guru Tanpa DOB', 'group' => 'GURU / STAF', 'member_type' => Member::TYPE_TEACHER, 'birth_date' => null, 'aktif' => true],
        );

        $userDob = User::updateOrCreate(
            ['code' => 'DOB20261', 'role' => 'users'],
            [
                'name' => $memberDob->name,
                'email' => 'DOB20261@member.latekaje',
                'password' => $memberDob->defaultInitialPassword(),
                'member_id' => $memberDob->id,
                'is_active' => true,
                'must_change_password' => true,
            ],
        );

        try {
            $this->assertSame('17082008', $memberDob->defaultInitialPassword());
            $this->assertSame('NODOB2026', $memberNoDob->defaultInitialPassword());

            $this->assertTrue(Auth::attempt(['email' => 'DOB20261', 'password' => '17082008']));
            $this->assertFalse(Auth::attempt(['email' => 'DOB20261', 'password' => 'DOB20261']));
        } finally {
            Auth::logout();
            $userDob->delete();
            $memberDob->delete();
            $memberNoDob->delete();
        }
    }

    public function test_local_html5_qrcode_bundle_and_security_headers(): void
    {
        $bundlePath = public_path('js/vendor/html5-qrcode.min.js');
        $this->assertFileExists($bundlePath);
        $this->assertGreaterThan(100_000, filesize($bundlePath));

        $res = $this->get('/admin/login');
        $res->assertOk();
        $res->assertSee('/js/vendor/html5-qrcode.min.js', false);
        $res->assertDontSee('cdn.jsdelivr.net', false);
        $res->assertDontSee('unpkg.com', false);

        $res->assertHeader('X-Frame-Options', 'DENY');
        $res->assertHeader('X-Content-Type-Options', 'nosniff');
        $res->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $this->assertStringContainsString('camera=(self)', (string) $res->headers->get('Permissions-Policy'));
    }

    public function test_activity_logger_records_and_redacts_secrets(): void
    {
        $superadmin = User::where('role', 'superadmin')->firstOrFail();

        $entry = ActivityLogger::log(
            'security.test_redact',
            'Uji redaksi field sensitif',
            null,
            [
                'password' => 'Rahasia123',
                'webhook_secret' => 'TokenRahasia',
                'return_pin' => '654321',
                'safe_field' => 'aman',
            ],
            $superadmin,
        );

        $location = Location::firstOrCreate(['key' => 'audit_loc'], ['label' => 'Audit Loc']);
        $asset = Asset::firstOrCreate(
            ['kode_aset' => 'AUD-AST-01'],
            ['nama_alat' => 'Mikrotik Audit', 'jenis' => 'Networking', 'spesifikasi' => 'RB941', 'kegunaan' => 'praktik', 'stok' => 1],
        );
        $item = AssetItem::firstOrCreate(
            ['nomor_seri_atau_qr' => 'AUD-QR-001'],
            ['asset_id' => $asset->id, 'status' => 'tersedia', 'kondisi' => 'baik', 'location_id' => $location->id],
        );

        $loan = null;

        try {
            $this->assertNotNull($entry);
            $this->assertSame('[REDACTED]', $entry->properties['password'] ?? null);
            $this->assertSame('[REDACTED]', $entry->properties['webhook_secret'] ?? null);
            $this->assertSame('[REDACTED]', $entry->properties['return_pin'] ?? null);
            $this->assertSame('aman', $entry->properties['safe_field'] ?? null);

            $this->actingAs($superadmin);
            $loan = LoanService::borrow($item->id, 'Peminjam Audit', 'XI TJKT 1', '123456');
            LoanService::returnLoan($loan, [
                'qr' => 'AUD-QR-001',
                'pin' => '123456',
                'returned_by' => 'Peminjam Audit',
                'return_relation' => 'sendiri',
            ]);

            $borrowLog = ActivityLog::where('action', 'loan.borrowed')->where('subject_id', $loan->id)->latest('id')->first();
            $returnLog = ActivityLog::where('action', 'loan.returned')->where('subject_id', $loan->id)->latest('id')->first();

            $this->assertNotNull($borrowLog);
            $this->assertNotNull($returnLog);
            $this->assertArrayNotHasKey('return_pin', $borrowLog->properties ?? []);
        } finally {
            if ($loan) {
                ActivityLog::where('subject_type', 'Loan')->where('subject_id', $loan->id)->delete();
                $loan->delete();
            }
            $entry?->delete();
            $item->delete();
            $asset->delete();
            $location->delete();
        }
    }
}
