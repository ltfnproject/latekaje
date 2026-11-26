<?php

return [
    // Shared actions
    'edit' => 'Ubah',
    'delete' => 'Hapus',
    'save' => 'Simpan',
    'cancel' => 'Batal',
    'yes' => 'Ya',
    'no' => 'Tidak',
    'import' => 'Import',
    'template_csv' => 'Template CSV',

    // Generic words
    'status' => 'Status',
    'active' => 'Aktif',
    'all' => 'Semua',
    'theme_light' => 'Mode Terang',
    'theme_dark' => 'Mode Gelap',
    'switch_light_mode' => 'Beralih ke mode terang',
    'switch_dark_mode' => 'Beralih ke mode gelap',

    // Navigation
    'nav_dashboard' => 'Dasbor',
    'nav_units' => 'Data Unit/QR',
    'nav_assets' => 'Aset',
    'nav_loans' => 'Peminjaman',
    'nav_users' => 'Kelola User',
    'nav_locations' => 'Lokasi',
    'nav_classes' => 'Kelas',
    'nav_students' => 'Siswa',
    'nav_settings' => 'Setting',
    'nav_about' => 'Tentang Aplikasi',
    'nav_location_data' => 'Data per Lokasi',
    'nav_activity_logs' => 'Log Aktivitas',
    'nav_operational_logs' => 'Log Operasional',
    'nav_my_activity_logs' => 'Riwayat Aktivitas Saya',
    'nav_categories' => 'Kategori Aset',
    'nav_funding_sources' => 'Sumber Anggaran',
    'nav_procurements' => 'Barang Masuk',
    'nav_bulk_stocks' => 'Wadah Pinjam Massal',
    'nav_consumables' => 'Bahan Habis Pakai',
    'activity_log_label' => 'Log Aktivitas',
    'log_time' => 'Waktu',
    'log_actor' => 'Pelaku',
    'log_action' => 'Aksi',
    'log_description' => 'Keterangan',
    'log_subject' => 'Objek',

    // Navigation groups
    'nav_group_transactions' => 'Transaksi',
    'nav_group_master' => 'Data Master',
    'nav_group_reports' => 'Laporan',
    'nav_group_system' => 'Sistem',

    // About page
    'about_version' => 'Versi :version',
    'about_body' => 'Aplikasi inventaris, barang masuk, stok bahan habis pakai, dan peminjaman alat jurusan Teknik Jaringan Komputer dan Telekomunikasi (TJKT) SMK Walisongo Pecangaan.',

    // Loan abilities (owned by the Loans domain elsewhere; labels live here
    // because Acl.php belongs to this workstream)
    'ability_loan_view_any' => 'Lihat Peminjaman',
    'ability_loan_create' => 'Buat pinjaman',
    'ability_loan_update' => 'Ubah / kembalikan pinjaman',
    'ability_loan_delete' => 'Hapus riwayat pinjam',
    'ability_loan_approve' => 'ACC / verifikasi pinjaman',
    'ability_activity_log_view_any' => 'Lihat Log Aktivitas',
];
