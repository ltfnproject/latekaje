<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Master Kategori Aset TJKT (9 kategori standar auto-code)
        if (! Schema::hasTable('asset_categories')) {
            Schema::create('asset_categories', function (Blueprint $table): void {
                $table->id();
                $table->string('code', 12)->unique();
                $table->string('name', 120);
                $table->string('default_tracking_mode', 30)->default('serialized');
                $table->string('default_status', 30)->default('tersedia');
                $table->string('default_qr_preset', 30)->default('medium_24');
                $table->string('description', 255)->nullable();
                $table->unsignedSmallInteger('sort_order')->default(10);
                $table->timestamps();
            });
        }

        // 2. Master Sumber Anggaran (1 sumber bisa dipakai berkali-kali untuk banyak proses barang masuk)
        if (! Schema::hasTable('funding_sources')) {
            Schema::create('funding_sources', function (Blueprint $table): void {
                $table->id();
                $table->string('name', 120)->unique();
                $table->string('source_type', 30)->default('pembelian')->index();
                $table->unsignedSmallInteger('fiscal_year')->nullable()->index();
                $table->text('notes')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        // 3. Barang Masuk / Pengadaan (tanpa nota, dengan foto rombongan privat & bulk repeater)
        if (! Schema::hasTable('procurements')) {
            Schema::create('procurements', function (Blueprint $table): void {
                $table->id();
                $table->string('batch_code', 40)->unique();
                $table->string('entry_type', 30)->default('new_procurement')->index();
                $table->foreignId('funding_source_id')->nullable()->constrained('funding_sources')->nullOnDelete();
                $table->date('received_at')->nullable()->index();
                $table->string('vendor', 150)->nullable();
                $table->text('notes')->nullable();
                $table->json('photo_paths')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('generated_at')->nullable();
                $table->timestamps();
            });
        }

        // 4. Rincian Item pada Batch Barang Masuk
        if (! Schema::hasTable('procurement_items')) {
            Schema::create('procurement_items', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('procurement_id')->constrained('procurements')->cascadeOnDelete();
                $table->foreignId('asset_id')->constrained('assets')->restrictOnDelete();
                $table->foreignId('location_id')->constrained('locations')->restrictOnDelete();
                $table->string('storage_spot', 100)->nullable();
                $table->unsignedInteger('quantity')->default(1);
                $table->unsignedBigInteger('unit_price')->nullable();
                $table->string('initial_condition', 20)->default('baik');
                $table->string('initial_status', 30)->default('tersedia');
                $table->text('serial_numbers_text')->nullable();
                $table->string('notes', 255)->nullable();
                $table->timestamps();
            });
        }

        // 5. Upgrade kolom pada `assets`
        Schema::table('assets', function (Blueprint $table): void {
            if (! Schema::hasColumn('assets', 'category_id')) {
                $table->foreignId('category_id')->nullable()->after('id')->constrained('asset_categories')->nullOnDelete();
            }
            if (! Schema::hasColumn('assets', 'tracking_mode')) {
                $table->string('tracking_mode', 30)->default('serialized')->after('kegunaan')->index();
            }
            if (! Schema::hasColumn('assets', 'requires_approval')) {
                $table->boolean('requires_approval')->default(false)->after('tracking_mode');
            }
            if (! Schema::hasColumn('assets', 'default_qr_preset')) {
                $table->string('default_qr_preset', 30)->nullable()->after('requires_approval');
            }
        });

        // 6. Upgrade non-destruktif `asset_items`: ubah status ENUM -> VARCHAR(30) & tambah kolom terpasang/parent/SN pabrik/rak
        $driver = DB::getDriverName();
        $prefix = DB::getTablePrefix();
        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::statement("ALTER TABLE `{$prefix}asset_items` MODIFY COLUMN `status` VARCHAR(30) NOT NULL DEFAULT 'tersedia'");
        }

        Schema::table('asset_items', function (Blueprint $table): void {
            if (! Schema::hasColumn('asset_items', 'serial_number_pabrik')) {
                $table->string('serial_number_pabrik', 120)->nullable()->after('nomor_seri_atau_qr')->index();
            }
            if (! Schema::hasColumn('asset_items', 'storage_spot')) {
                $table->string('storage_spot', 100)->nullable()->after('location_id');
            }
            if (! Schema::hasColumn('asset_items', 'procurement_id')) {
                $table->foreignId('procurement_id')->nullable()->after('storage_spot')->constrained('procurements')->nullOnDelete();
            }
            if (! Schema::hasColumn('asset_items', 'purchase_price')) {
                $table->unsignedBigInteger('purchase_price')->nullable()->after('procurement_id');
            }
            if (! Schema::hasColumn('asset_items', 'parent_asset_item_id')) {
                $table->foreignId('parent_asset_item_id')->nullable()->after('purchase_price')->constrained('asset_items')->nullOnDelete();
            }
            if (! Schema::hasColumn('asset_items', 'installed_at')) {
                $table->timestamp('installed_at')->nullable()->after('parent_asset_item_id');
            }
            if (! Schema::hasColumn('asset_items', 'installed_note')) {
                $table->string('installed_note', 255)->nullable()->after('installed_at');
            }
        });

        // 7. Master & Stok Wadah Pinjam Massal (`bulk_stocks`, siap Checkout Keranjang Fase 3)
        if (! Schema::hasTable('bulk_stocks')) {
            Schema::create('bulk_stocks', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('asset_id')->constrained('assets')->restrictOnDelete();
                $table->foreignId('location_id')->constrained('locations')->restrictOnDelete();
                $table->string('storage_spot', 100)->nullable();
                $table->string('container_qr', 64)->unique();
                $table->string('label', 150)->nullable();
                $table->unsignedInteger('total_qty')->default(0);
                $table->unsignedInteger('available_qty')->default(0);
                $table->unsignedInteger('borrowed_qty')->default(0);
                $table->unsignedInteger('damaged_qty')->default(0);
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }

        // 8. Menu Terpisah Bahan Habis Pakai (`consumables` & `consumable_transactions`)
        if (! Schema::hasTable('consumables')) {
            Schema::create('consumables', function (Blueprint $table): void {
                $table->id();
                $table->string('name', 150);
                $table->string('category_label', 80)->default('Umum')->index();
                $table->string('specification', 255)->nullable();
                $table->string('unit', 30)->default('pcs');
                $table->integer('stock')->default(0);
                $table->unsignedInteger('min_stock')->default(5);
                $table->foreignId('location_id')->nullable()->constrained('locations')->nullOnDelete();
                $table->string('storage_spot', 100)->nullable();
                $table->text('notes')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('consumable_transactions')) {
            Schema::create('consumable_transactions', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('consumable_id')->constrained('consumables')->cascadeOnDelete();
                $table->string('type', 20)->index(); // in | out | adjustment
                $table->unsignedInteger('quantity');
                $table->integer('stock_before')->default(0);
                $table->integer('stock_after')->default(0);
                $table->foreignId('funding_source_id')->nullable()->constrained('funding_sources')->nullOnDelete();
                $table->unsignedBigInteger('unit_price')->nullable();
                $table->foreignId('member_id')->nullable()->constrained('members')->nullOnDelete();
                $table->string('recipient_name', 150)->nullable();
                $table->string('recipient_group', 100)->nullable();
                $table->string('purpose', 255)->nullable();
                $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('transacted_at')->useCurrent()->index();
                $table->timestamps();
            });
        }

        // 9. Seed idempoten 9 Kategori Aset TJKT, Sumber Anggaran awal, & lokasi Server Room
        $now = now();
        $categories = [
            ['code' => 'NET', 'name' => 'Networking & Server', 'default_tracking_mode' => 'serialized', 'default_status' => 'tersedia', 'default_qr_preset' => 'medium_24', 'description' => 'MikroTik, Switch, Access Point, Server, UPS, Rak Server, PoE, Anti Surge', 'sort_order' => 1],
            ['code' => 'FO', 'name' => 'Fiber Optik (FTTH & GPON)', 'default_tracking_mode' => 'serialized', 'default_status' => 'tersedia', 'default_qr_preset' => 'medium_24', 'description' => 'Splicer, OTDR, OLT/ONT GPON, OPM, OLS, Cleaver, Stripper, HTB, SFP, Patchcord FO', 'sort_order' => 2],
            ['code' => 'PC', 'name' => 'Komputer, Laptop & Display', 'default_tracking_mode' => 'serialized', 'default_status' => 'tersedia', 'default_qr_preset' => 'detail_a4', 'description' => 'PC Lab TJKT/KKPI, Mini PC, Laptop, Monitor, Proyektor, Smartboard, Printer', 'sort_order' => 3],
            ['code' => 'CMP', 'name' => 'Komponen & Sparepart PC', 'default_tracking_mode' => 'serialized', 'default_status' => 'tersedia', 'default_qr_preset' => 'mini_12', 'description' => 'RAM, SSD NVMe, PSU, Processor, Heatsink, PCI LAN/WLAN/SoundCard, Enclosure', 'sort_order' => 4],
            ['code' => 'IOT', 'name' => 'IoT, Mikrokontroler & Sensor', 'default_tracking_mode' => 'serialized', 'default_status' => 'tersedia', 'default_qr_preset' => 'micro_12x12', 'description' => 'NodeMCU, ESP8266/ESP32, Arduino, Relay, Breadboard, DHT11/22, Soil Sensor', 'sort_order' => 5],
            ['code' => 'TLS', 'name' => 'Perkakas & Alat Servis (Tools)', 'default_tracking_mode' => 'serialized', 'default_status' => 'tersedia', 'default_qr_preset' => 'mini_12', 'description' => 'Tang Krimping, LAN Tester, Obeng, Blower, Solder Hakko, PSU 1501T, Multitester', 'sort_order' => 6],
            ['code' => 'ACC', 'name' => 'Aksesori & Peripheral', 'default_tracking_mode' => 'serialized', 'default_status' => 'tersedia', 'default_qr_preset' => 'mini_12', 'description' => 'Keyboard, Mouse, Mousepad, Webcam, VGA Splitter, Converter Display, OTG, KVM', 'sort_order' => 7],
            ['code' => 'KBL', 'name' => 'Kabel & Kelistrikan', 'default_tracking_mode' => 'bulk_returnable', 'default_status' => 'tersedia', 'default_qr_preset' => 'flag_12', 'description' => 'Kabel Power AC (C13/C5/C7), VGA, HDMI, Kabel Listrik Roll, USB, Patchcord UTP', 'sort_order' => 8],
            ['code' => 'SRP', 'name' => 'Sarana & Prasarana Ruangan (KIR)', 'default_tracking_mode' => 'serialized', 'default_status' => 'terpasang', 'default_qr_preset' => 'detail_a4', 'description' => 'Meja, Kursi, Almari, Rak, Showcase, Whiteboard, AC, Exhaust Fan, Kipas, Lampu', 'sort_order' => 9],
        ];

        foreach ($categories as $cat) {
            DB::table('asset_categories')->updateOrInsert(
                ['code' => $cat['code']],
                array_merge($cat, ['created_at' => $now, 'updated_at' => $now])
            );
        }

        // Pastikan lokasi standar TJKT lengkap (termasuk Server Room, Gudang TJKT, Kantor TJKT)
        foreach ([
            ['key' => 'gudang', 'label' => 'Gudang TJKT'],
            ['key' => 'ruang_kantor', 'label' => 'Kantor TJKT'],
            ['key' => 'lab_tjkt', 'label' => 'Lab TJKT'],
            ['key' => 'lab_kkpi', 'label' => 'Lab KKPI'],
            ['key' => 'lab_fo', 'label' => 'Lab FO'],
            ['key' => 'server_room', 'label' => 'Server Room'],
        ] as $loc) {
            if (! DB::table('locations')->where('key', $loc['key'])->exists()) {
                DB::table('locations')->insert([
                    'key' => $loc['key'],
                    'label' => $loc['label'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        // Sumber anggaran awal siap pakai (1 sumber bisa dipakai berkali-kali)
        foreach ([
            ['name' => 'BOS / Kas Sekolah 2026', 'source_type' => 'pembelian', 'fiscal_year' => 2026, 'notes' => 'Pembelian rutin / kas sekolah / BOS'],
            ['name' => 'BANPEM 2025', 'source_type' => 'banpem', 'fiscal_year' => 2025, 'notes' => 'Hibah / Bantuan Pemerintah (Splicer, OTDR, Laptop, OLT/ONT GPON)'],
            ['name' => 'Komite / Yayasan 2026', 'source_type' => 'komite', 'fiscal_year' => 2026, 'notes' => 'Dana Komite / Siswa / Yayasan'],
            ['name' => 'Hibah Industri / CSR', 'source_type' => 'csr', 'fiscal_year' => null, 'notes' => 'Hibah mitra industri (cth: Evercoss)'],
        ] as $src) {
            DB::table('funding_sources')->updateOrInsert(
                ['name' => $src['name']],
                array_merge($src, ['is_active' => true, 'created_at' => $now, 'updated_at' => $now])
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('consumable_transactions');
        Schema::dropIfExists('consumables');
        Schema::dropIfExists('bulk_stocks');

        Schema::table('asset_items', function (Blueprint $table): void {
            foreach (['parent_asset_item_id', 'procurement_id'] as $fk) {
                if (Schema::hasColumn('asset_items', $fk)) {
                    $table->dropConstrainedForeignId($fk);
                }
            }
            foreach (['serial_number_pabrik', 'storage_spot', 'purchase_price', 'installed_at', 'installed_note'] as $col) {
                if (Schema::hasColumn('asset_items', $col)) {
                    $table->dropColumn($col);
                }
            }
        });

        Schema::table('assets', function (Blueprint $table): void {
            if (Schema::hasColumn('assets', 'category_id')) {
                $table->dropConstrainedForeignId('category_id');
            }
            foreach (['tracking_mode', 'requires_approval', 'default_qr_preset'] as $col) {
                if (Schema::hasColumn('assets', $col)) {
                    $table->dropColumn($col);
                }
            }
        });

        Schema::dropIfExists('procurement_items');
        Schema::dropIfExists('procurements');
        Schema::dropIfExists('funding_sources');
        Schema::dropIfExists('asset_categories');
    }
};
