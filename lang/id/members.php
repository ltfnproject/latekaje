<?php

return [
    'model_label' => 'Anggota',
    'model_plural' => 'Anggota',

    // Form
    'code_label' => 'ID Anggota',
    'code_placeholder' => 'cth: 1001 (boleh kosong)',
    'full_name_label' => 'Nama Lengkap',
    'name_label' => 'Nama',
    'group_label' => 'Grup',
    'group_placeholder' => 'cth: X TJKT 1',
    'member_type_label' => 'Kategori Anggota',
    'type_student' => 'Siswa',
    'type_teacher' => 'Guru',
    'type_employee' => 'Karyawan',
    'birth_date_label' => 'Tanggal Lahir',
    'birth_date_helper' => 'Dipakai sebagai password awal format DDMMYYYY (cth: 17082008) saat membuat akun login.',

    // Table
    'active_label' => 'Aktif',
    'active_helper' => 'Mematikan status aktif akan otomatis menonaktifkan akun login anggota ini.',
    'has_account_label' => 'Akun Login',
    'loans_label' => 'Pinjam',

    // Actions & notifications
    'create_action' => 'Tambah Anggota',
    'create_account' => 'Buat Akun',
    'reset_password' => 'Reset Password Awal',
    'password_reset_title' => 'Password awal berhasil direset',
    'bulk_create_accounts' => 'Buat Akun Massal',
    'bulk_accounts_created_title' => ':count akun login anggota berhasil dibuat',
    'account_created_title' => 'Akun login dibuat',
    'account_created_body' => 'ID: :code | Password awal: ID-nya (tanggal lahir belum diisi). Anggota wajib ganti password saat login pertama.',
    'account_created_body_dob' => 'ID: :code | Password awal: Tanggal Lahir format DDMMYYYY. Anggota wajib ganti password saat login pertama.',

    // Importer columns
    'import_col_code' => 'ID Anggota',
    'import_col_name' => 'Nama',
    'import_col_group' => 'Grup',
    'import_col_member_type' => 'Kategori (student/teacher/employee)',
    'import_col_birth_date' => 'Tanggal Lahir (YYYY-MM-DD)',
    'import_col_password' => 'Password',
    'import_completed' => 'Impor anggota selesai: :success baris berhasil',
    'import_failed_suffix' => ', :failed baris gagal',

    // Abilities
    'ability_view_any' => 'Lihat Anggota',
    'ability_create' => 'Tambah anggota',
];
