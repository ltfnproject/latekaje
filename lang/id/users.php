<?php

return [
    'model_label' => 'Pengguna',
    'model_plural' => 'Pengguna',

    // Form
    'section_account' => 'Akun Login & Peran',
    'section_borrower_profile' => 'Profil Peminjam (Siswa / Guru / Karyawan)',
    'section_borrower_profile_desc' => 'Otomatis disinkronkan dengan Data Master Anggota agar identitas & grup peminjam selalu lengkap.',
    'section_acl' => 'Hak Akses Khusus (ACL)',
    'link_member_label' => 'Tautkan ke Data Anggota (opsional)',
    'link_member_placeholder' => 'Kosongkan untuk otomatis membuat Data Anggota baru',
    'link_member_helper' => 'Jika anggota sudah terdaftar di Data Master Anggota, pilih di sini agar profil terisi otomatis.',
    'name_label' => 'Nama',
    'email_label' => 'Email',
    'code_label' => 'NIS / NIP / ID Anggota',
    'code_placeholder' => 'cth: 1001 (NIS siswa atau NIP guru/karyawan)',
    'role_label' => 'Role',
    'role_superadmin' => 'Super Admin (Tim IT)',
    'role_admin' => 'KKK (Kepala Lab)',
    'role_staff' => 'Toolman (Teknisi)',
    'role_assistant' => 'Admin Lab (Prakerin)',
    'role_users' => 'Peminjam (Siswa/Guru/Karyawan)',
    'account_active_label' => 'Akun aktif',
    'account_active_helper' => 'Matikan untuk memblokir login tanpa menghapus akun.',
    'must_change_password_label' => 'Wajib ganti password saat login',
    'must_change_password_helper' => 'Pengguna akan diarahkan ke halaman Profil sebelum dapat mengakses menu lain.',
    'must_change_password_title' => 'Wajib ganti password awal',
    'must_change_password_body' => 'Demi keamanan akun, silakan ganti password awal (tanggal lahir/ID) Anda terlebih dahulu sebelum melanjutkan.',
    'perm_allow_label' => 'Hak khusus: IZINKAN (di luar role)',
    'perm_deny_label' => 'Hak khusus: LARANG (walau role membolehkan)',
    'new_password_label' => 'Password Baru',
    'password_helper_edit' => 'Kosongkan bila tidak diganti.',
    'password_helper_borrower_create' => 'Boleh dikosongkan bila Tanggal Lahir atau NIS/NIP diisi — otomatis memakai format DDMMYYYY (wajib ganti saat login pertama).',

    // Table
    'member_data_label' => 'Data Anggota',
    'created_label' => 'Dibuat',

    // Toggle-active action
    'activate' => 'Aktifkan',
    'deactivate' => 'Nonaktifkan',
    'activated_title' => 'Akun diaktifkan',
    'deactivated_title' => 'Akun dinonaktifkan + sesi ditendang',

    // Pages
    'create_action' => 'Tambah Pengguna',

    // Abilities
    'ability_view_any' => 'Buka Kelola User',
    'ability_create' => 'Buat user',
    'ability_update' => 'Ubah user / password',
    'ability_delete' => 'Hapus user',
];
