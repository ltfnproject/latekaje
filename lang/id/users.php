<?php

return [
    'model_label' => 'Pengguna',
    'model_plural' => 'Pengguna',

    // Form
    'name_label' => 'Nama',
    'email_label' => 'Email',
    'code_label' => 'ID Anggota',
    'code_placeholder' => 'Khusus akun anggota',
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
