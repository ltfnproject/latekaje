<?php

namespace Tests\Feature;

use App\Http\Controllers\PrintQrController;
use App\Models\ActivityLog;
use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\AssetItem;
use App\Models\Consumable;
use App\Models\Location;
use App\Models\User;
use Database\Seeders\TjktCatalogSeeder;
use Tests\TestCase;

class Phase25PrintAndCatalogTest extends TestCase
{
    public function test_tjkt_catalog_seeder_is_clean_within_column_limits_and_idempotent(): void
    {
        $catalog = TjktCatalogSeeder::assetCatalog();
        $consumables = TjktCatalogSeeder::consumableCatalog();

        $this->assertGreaterThanOrEqual(120, count($catalog));
        $this->assertGreaterThanOrEqual(24, count($consumables));

        // 1. Cek keunikan kode_aset, panjang <= 255 karakter, & bebas label sumber dana/rahasia
        $seenCodes = [];
        foreach ($catalog as $row) {
            $this->assertArrayNotHasKey($row['kode'], $seenCodes, "Kode aset duplikat: {$row['kode']}");
            $seenCodes[$row['kode']] = true;

            $this->assertLessThanOrEqual(255, mb_strlen($row['nama']), "Nama terlalu panjang: {$row['kode']}");
            $this->assertLessThanOrEqual(255, mb_strlen($row['spek']), "Spesifikasi > 255 karakter: {$row['kode']}");
            $this->assertStringNotContainsStringIgnoringCase('banpem', $row['spek'], "Sumber dana tidak boleh masuk spek: {$row['kode']}");
            $this->assertStringNotContainsStringIgnoringCase('evercoss', $row['spek'], "Sumber dana CSR tidak boleh masuk spek: {$row['kode']}");
            $this->assertStringNotContainsStringIgnoringCase('rahasia', $row['spek'], "Kata rahasia tidak boleh ada: {$row['kode']}");
        }

        foreach ($consumables as $item) {
            $this->assertLessThanOrEqual(150, mb_strlen($item['name']), "Nama BHP > 150 karakter: {$item['name']}");
            $this->assertLessThanOrEqual(255, mb_strlen($item['spec']), "Spek BHP > 255 karakter: {$item['name']}");
        }

        // 2. Jalankan seeder 2× — harus 100% idempoten tanpa duplikat & stok BHP tetap 0
        $seededCodes = array_column($catalog, 'kode');
        $seededConsumableNames = array_column($consumables, 'name');

        try {
            $this->seed(TjktCatalogSeeder::class);
            $countAfterFirst = Asset::whereIn('kode_aset', $seededCodes)->count();
            $bhpAfterFirst = Consumable::whereIn('name', $seededConsumableNames)->count();

            $this->assertSame(count($catalog), $countAfterFirst);
            $this->assertSame(count($consumables), $bhpAfterFirst);

            // Jalankan kedua kali
            $this->seed(TjktCatalogSeeder::class);
            $this->assertSame($countAfterFirst, Asset::whereIn('kode_aset', $seededCodes)->count());
            $this->assertSame($bhpAfterFirst, Consumable::whereIn('name', $seededConsumableNames)->count());

            // Cek beberapa perangkat resmi terverifikasi dari KKK
            $splicer = Asset::where('kode_aset', 'TJKT-FO-001')->firstOrFail();
            $this->assertSame('Optical Fusion Splicer Joinwit JW4109', $splicer->nama_alat);
            $this->assertTrue($splicer->requires_approval);

            $tpSwitch = Asset::where('kode_aset', 'TJKT-NET-009')->firstOrFail();
            $this->assertStringContainsString('TL-SG1024', $tpSwitch->nama_alat);
            $this->assertStringContainsString('48 Gbps', $tpSwitch->spesifikasi);

            $spectra = Consumable::where('name', 'Kabel Fiber Optik Spectra Jellytube')->firstOrFail();
            $this->assertSame(0, $spectra->stock);
            $this->assertStringContainsString('G.657A', (string) $spectra->specification);
        } finally {
            Asset::whereIn('kode_aset', $seededCodes)->doesntHave('assetItems')->doesntHave('bulkStocks')->delete();
            Consumable::whereIn('name', $seededConsumableNames)->doesntHave('transactions')->delete();
        }
    }

    public function test_phase25_five_presets_thermal_print_offset_and_ptouch_csv_export(): void
    {
        $kkk = User::updateOrCreate(
            ['email' => 'p25-print-kkk@latekaje.test'],
            ['name' => 'KKK Print 2.5', 'password' => 'secret1234', 'role' => 'admin', 'is_active' => true, 'must_change_password' => false],
        );
        $borrower = User::updateOrCreate(
            ['email' => 'p25-print-user@latekaje.test'],
            ['name' => 'Siswa Print 2.5', 'code' => 'P25USR01', 'password' => 'secret1234', 'role' => 'users', 'is_active' => true, 'must_change_password' => false],
        );

        $gudang = Location::where('key', 'gudang')->firstOrFail();
        $iotCat = AssetCategory::where('code', 'IOT')->firstOrFail();

        $sensorAsset = Asset::create([
            'category_id' => $iotCat->id,
            'nama_alat' => '=FormulaInject Test Sensor',
            'spesifikasi' => 'Sensor 3.3V-5V',
            'kegunaan' => 'Praktik Siswa',
            'tracking_mode' => Asset::MODE_SERIALIZED,
            'default_qr_preset' => 'micro_12x12',
        ]);

        $unit = AssetItem::create([
            'asset_id' => $sensorAsset->id,
            'nomor_seri_atau_qr' => 'TJKT-IOT-8801',
            'serial_number_pabrik' => 'SN-IOT-8801',
            'kondisi' => 'baik',
            'status' => AssetItem::STATUS_AVAILABLE,
            'location_id' => $gudang->id,
            'storage_spot' => 'Box Sensor 1',
        ]);

        try {
            $this->actingAs($kkk);

            // 1. Auto-detect preset mengambil default_qr_preset dari aset (micro_12x12)
            $this->get('/print-qr?ids='.$unit->id)
                ->assertOk()
                ->assertSee('label-micro_12x12', false)
                ->assertSee('IOT-8801');

            // 2. Uji seluruh 5 preset monokrom resmi
            foreach (array_keys(PrintQrController::PRESETS) as $preset) {
                $res = $this->get('/print-qr?ids='.$unit->id.'&preset='.$preset);
                $res->assertOk();
                $res->assertSee("label-{$preset}", false);
                $res->assertSee('INVENTARIS TJKT');
            }

            // 3. Uji cetak format P-touch (M · Sedang 24mm) di kertas biasa A4 / F4 / A3 dengan Offset Slot (tanpa page-break per label!)
            foreach (['a4' => '210mm 297mm', 'f4' => '215mm 330mm', 'a3' => '297mm 420mm'] as $outKey => $expectedSize) {
                $sheetRes = $this->get('/print-qr?ids='.$unit->id.'&preset=medium_24&output='.$outKey.'&offset=3');
                $sheetRes->assertOk();
                $sheetRes->assertSee('size: '.$expectedSize, false);
                $sheetRes->assertSee('ptouch-sheet-grid', false);
                $sheetRes->assertSee('ptouch-slot-empty', false);
                $sheetRes->assertDontSee('break-after: page', false);
            }

            // 4. Uji Detail A3 dengan Slot Offset = 4
            $this->get('/print-qr?ids='.$unit->id.'&preset=detail_a4&paper=a3&offset=4')
                ->assertOk()
                ->assertSee('Slot Kosong #4')
                ->assertSee('SMK WALISONGO PECANGAAN')
                ->assertSee('SN-IOT-8801');

            // 5. Uji Export CSV Brother P-touch Editor (UTF-8 + anti CSV Formula Injection)
            $csvRes = $this->get('/print-qr/export-ptouch?ids='.$unit->id);
            $csvRes->assertOk();
            $this->assertStringContainsString('text/csv', (string) $csvRes->headers->get('Content-Type'));
            $csvRes->assertHeader('X-Content-Type-Options', 'nosniff');

            $csvBody = $csvRes->streamedContent();
            $this->assertStringContainsString('QR_CODE,NAMA_ALAT,KODE_ASET', $csvBody);
            $this->assertStringContainsString('TJKT-IOT-8801', $csvBody);
            $this->assertStringContainsString("'=FormulaInject Test Sensor", $csvBody);
            $this->assertStringContainsString('INVENTARIS TJKT', $csvBody);

            // 6. Uji Export Excel (.xlsx) Brother P-touch Editor
            $xlsxRes = $this->get('/print-qr/export-ptouch-xlsx?ids='.$unit->id);
            $xlsxRes->assertOk();
            $this->assertStringContainsString('spreadsheetml.sheet', (string) $xlsxRes->headers->get('Content-Type'));
            $xlsxRes->assertHeader('X-Content-Type-Options', 'nosniff');

            $tmpXlsx = tempnam(sys_get_temp_dir(), 'ptouch_test_').'.xlsx';
            file_put_contents($tmpXlsx, $xlsxRes->streamedContent());
            try {
                $loaded = \PhpOffice\PhpSpreadsheet\IOFactory::load($tmpXlsx);
                $sheet = $loaded->getSheetByName('DATA_PTOUCH');
                $this->assertNotNull($sheet);
                $this->assertSame('QR_CODE', $sheet->getCell('A1')->getValue());
                $this->assertSame('TJKT-IOT-8801', $sheet->getCell('A2')->getValue());
                $this->assertSame("'=FormulaInject Test Sensor", $sheet->getCell('B2')->getValue());
                $this->assertSame('INVENTARIS TJKT', $sheet->getCell('J2')->getValue());
            } finally {
                @unlink($tmpXlsx);
            }

            // 7. Borrower (users) wajib ditolak 403 untuk Export CSV & Excel P-touch
            $this->flushSession();
            $this->actingAs($borrower)
                ->get('/print-qr/export-ptouch?ids=all')
                ->assertForbidden();
            $this->actingAs($borrower)
                ->get('/print-qr/export-ptouch-xlsx?ids=all')
                ->assertForbidden();
        } finally {
            ActivityLog::whereIn('action', ['qr.printed', 'qr.ptouch_exported'])
                ->where('user_id', $kkk->id)
                ->delete();
            $unit->delete();
            $sensorAsset->delete();
            $kkk->delete();
            $borrower->delete();
        }
    }

    public function test_asset_catalog_omits_kegunaan_and_defaults_safely(): void
    {
        $kkk = User::updateOrCreate(
            ['email' => 'p25-nokeg-kkk@latekaje.test'],
            ['name' => 'KKK Uji Katalog', 'password' => 'secret1234', 'role' => 'admin', 'is_active' => true, 'must_change_password' => false],
        );

        $netCategory = AssetCategory::where('code', 'NET')->firstOrFail();
        $asset = null;

        try {
            $asset = Asset::create([
                'category_id' => $netCategory->id,
                'kode_aset' => 'TJKT-NET-998',
                'nama_alat' => 'Router Uji Tanpa Kegunaan',
                'jenis' => $netCategory->name,
                'spesifikasi' => '5x Gigabit Ethernet',
            ]);

            $this->assertSame('umum', $asset->fresh()->kegunaan);

            $this->actingAs($kkk)
                ->get('/admin/assets/create')
                ->assertOk()
                ->assertDontSee('Kegunaan');

            $this->actingAs($kkk)
                ->get('/admin/assets')
                ->assertOk();
        } finally {
            $asset?->delete();
            $kkk->delete();
        }
    }
}
