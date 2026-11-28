<?php

namespace Database\Seeders;

use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\Consumable;
use App\Models\Location;
use Illuminate\Database\Seeder;

class TjktCatalogSeeder extends Seeder
{
    /**
     * Seed Katalog Master Aset TJKT (nama = jenis+merk/model, spesifikasi = spek teknis asli)
     * serta Master Bahan Habis Pakai (stok awal 0).
     * 100% idempoten: tidak menimpa data yang sudah ada dan tidak membuat stok fisik fiktif.
     */
    public function run(): void
    {
        $categories = AssetCategory::query()->get()->keyBy('code');
        $gudangId = Location::where('key', 'gudang')->value('id');

        foreach (self::assetCatalog() as $row) {
            $cat = $categories->get($row['cat']);

            Asset::firstOrCreate(
                ['kode_aset' => $row['kode']],
                [
                    'category_id' => $cat?->id,
                    'nama_alat' => $row['nama'],
                    'jenis' => $cat?->name ?? $row['cat'],
                    'spesifikasi' => $row['spek'],
                    'kegunaan' => $row['kegunaan'] ?? 'Praktik Siswa',
                    'tracking_mode' => $row['mode'] ?? Asset::MODE_SERIALIZED,
                    'requires_approval' => (bool) ($row['kritis'] ?? false),
                    'default_qr_preset' => $row['preset'] ?? ($cat?->default_qr_preset ?? 'medium_24'),
                    'stok' => 0,
                ],
            );
        }

        foreach (self::consumableCatalog() as $item) {
            Consumable::firstOrCreate(
                ['name' => $item['name']],
                [
                    'category_label' => $item['category'],
                    'specification' => $item['spec'],
                    'unit' => $item['unit'],
                    'stock' => 0,
                    'min_stock' => $item['min_stock'],
                    'location_id' => $gudangId,
                    'is_active' => true,
                ],
            );
        }
    }

    /**
     * @return list<array{kode: string, cat: string, nama: string, spek: string, mode?: string, kritis?: bool, preset?: string, kegunaan?: string}>
     */
    public static function assetCatalog(): array
    {
        $S = Asset::MODE_SERIALIZED;
        $B = Asset::MODE_BULK_RETURNABLE;

        return [
            // ======================== 1. NET — Networking & Server ========================
            ['kode' => 'TJKT-NET-001', 'cat' => 'NET', 'nama' => 'Router MikroTik CCR', 'spek' => 'Cloud Core Router rackmount 1U, CPU multi-core Tilera/ARM, Gigabit RJ45 & SFP/SFP+, RouterOS Level 6', 'mode' => $S, 'kritis' => true, 'preset' => 'medium_24', 'kegunaan' => 'non_praktik'],
            ['kode' => 'TJKT-NET-002', 'cat' => 'NET', 'nama' => 'Router MikroTik RB941-2nD (hAP lite)', 'spek' => '4× Port Fast Ethernet 10/100 Mbps, Wi-Fi 2.4GHz 802.11b/g/n, CPU 650MHz, RAM 32MB, RouterOS L4, daya Micro-USB 5V', 'mode' => $S, 'preset' => 'medium_24'],
            ['kode' => 'TJKT-NET-003', 'cat' => 'NET', 'nama' => 'Router MikroTik RB951-2nD / RB951Ui-2HnD', 'spek' => '5× Port Fast Ethernet 10/100 Mbps, Wi-Fi 2.4GHz 802.11b/g/n, USB 2.0, PoE-out port 5, RouterOS L4, daya 24V DC', 'mode' => $S, 'preset' => 'medium_24'],
            ['kode' => 'TJKT-NET-004', 'cat' => 'NET', 'nama' => 'Router MikroTik RBD52G (hAP ac²)', 'spek' => '5× Port Gigabit 10/100/1000 Mbps, Dual-Band Wi-Fi 2.4/5GHz 802.11ac, CPU Quad-Core ARM 716MHz, RAM 128MB, RouterOS L4', 'mode' => $S, 'preset' => 'medium_24'],
            ['kode' => 'TJKT-NET-005', 'cat' => 'NET', 'nama' => 'Router MikroTik RB750', 'spek' => '5× Port Fast Ethernet 10/100 Mbps, RouterBOARD desktop compact, RouterOS L4, daya 12–24V DC', 'mode' => $S, 'preset' => 'medium_24'],
            ['kode' => 'TJKT-NET-006', 'cat' => 'NET', 'nama' => 'Router MikroTik RB750r2 (hEX lite)', 'spek' => '5× Port Fast Ethernet 10/100 Mbps, CPU QCA9533 850MHz, RAM 64MB, Passive PoE-in, RouterOS L4', 'mode' => $S, 'preset' => 'medium_24'],
            ['kode' => 'TJKT-NET-007', 'cat' => 'NET', 'nama' => 'Router MikroTik RB750Gr3 / RB752Gr3 (hEX)', 'spek' => '5× Port Gigabit Ethernet, CPU Dual-Core 880MHz, RAM 256MB, slot microSD & USB, IPSec hardware acceleration, RouterOS L4', 'mode' => $S, 'preset' => 'medium_24'],
            ['kode' => 'TJKT-NET-008', 'cat' => 'NET', 'nama' => 'Router MikroTik RB450', 'spek' => '5× Port Ethernet 10/100 Mbps, serial console RS232, RouterOS L4/L5, daya PoE/DC jack', 'mode' => $S, 'preset' => 'medium_24'],
            ['kode' => 'TJKT-NET-009', 'cat' => 'NET', 'nama' => 'Switch Unmanaged Gigabit TP-Link TL-SG1024 (24-Port)', 'spek' => '24× Port RJ45 10/100/1000 Mbps Unmanaged Plug-and-Play, Auto-MDI/MDIX, switching capacity 48 Gbps, MAC 8K, Jumbo Frame 10KB, casing baja rackmount fanless, AC 100–240V', 'mode' => $S, 'preset' => 'medium_24'],
            ['kode' => 'TJKT-NET-010', 'cat' => 'NET', 'nama' => 'Switch Managed Cisco', 'spek' => 'Managed Switch Cisco Layer 2/3, dukungan VLAN 802.1Q, Trunking, STP/RSTP, CLI Console & Web UI', 'mode' => $S, 'kritis' => true, 'preset' => 'medium_24'],
            ['kode' => 'TJKT-NET-011', 'cat' => 'NET', 'nama' => 'Switch Managed MikroTik CRS', 'spek' => 'Cloud Router Switch MikroTik, Gigabit RJ45 + uplink SFP/SFP+, dual-boot RouterOS/SwOS, VLAN hardware offloading', 'mode' => $S, 'kritis' => true, 'preset' => 'medium_24'],
            ['kode' => 'TJKT-NET-012', 'cat' => 'NET', 'nama' => 'Switch Managed MikroTik CSS', 'spek' => 'Cloud Smart Switch MikroTik (SwOS), Gigabit RJ45 + uplink SFP, VLAN 802.1Q, port mirroring & bandwidth limit', 'mode' => $S, 'preset' => 'medium_24'],
            ['kode' => 'TJKT-NET-013', 'cat' => 'NET', 'nama' => 'Switch Managed MikroTik RB260GS', 'spek' => '5× Port Gigabit Ethernet + 1× SFP cage, SwOS Web Managed, Passive PoE-in port 1, daya 6–31V DC', 'mode' => $S, 'preset' => 'medium_24'],
            ['kode' => 'TJKT-NET-014', 'cat' => 'NET', 'nama' => 'Switch Unmanaged TP-Link (5/8-Port)', 'spek' => 'Desktop Unmanaged Switch TP-Link 5/8 Port RJ45 Auto-MDI/MDIX, Plug-and-Play', 'mode' => $S, 'preset' => 'medium_24'],
            ['kode' => 'TJKT-NET-015', 'cat' => 'NET', 'nama' => 'Switch Unmanaged D-Link', 'spek' => 'Unmanaged Ethernet Switch D-Link RJ45 Auto-Negotiation & Auto-MDI/MDIX, Plug-and-Play', 'mode' => $S, 'preset' => 'medium_24'],
            ['kode' => 'TJKT-NET-016', 'cat' => 'NET', 'nama' => 'Switch Unmanaged Edimax', 'spek' => 'Unmanaged Ethernet Switch Edimax RJ45 Auto-MDI/MDIX, Plug-and-Play', 'mode' => $S, 'preset' => 'medium_24'],
            ['kode' => 'TJKT-NET-017', 'cat' => 'NET', 'nama' => 'Switch Unmanaged Cisco', 'spek' => 'Unmanaged Ethernet Switch Cisco Small Business RJ45, Plug-and-Play', 'mode' => $S, 'preset' => 'medium_24'],
            ['kode' => 'TJKT-NET-018', 'cat' => 'NET', 'nama' => 'Access Point TP-Link TL-WA701ND', 'spek' => 'Wireless N 150Mbps 2.4GHz Access Point, mode AP/Client/Repeater/Bridge/Multi-SSID, dukungan Passive PoE 9V DC', 'mode' => $S, 'preset' => 'medium_24'],
            ['kode' => 'TJKT-NET-019', 'cat' => 'NET', 'nama' => 'Server Utama TJKT', 'spek' => 'Unit Server Fisik Ruang Server TJKT, layanan jaringan, virtualisasi & aplikasi lokal', 'mode' => $S, 'kritis' => true, 'preset' => 'medium_24', 'kegunaan' => 'non_praktik'],
            ['kode' => 'TJKT-NET-020', 'cat' => 'NET', 'nama' => 'UPS Server', 'spek' => 'Uninterruptible Power Supply cadangan daya AC untuk rak server & perangkat inti jaringan', 'mode' => $S, 'kritis' => true, 'preset' => 'medium_24', 'kegunaan' => 'non_praktik'],
            ['kode' => 'TJKT-NET-021', 'cat' => 'NET', 'nama' => 'Rak Server 19"', 'spek' => 'Cabinet Rack Server standar 19 inci dengan pintu kaca/perforasi & PDU kelistrikan', 'mode' => $S, 'preset' => 'detail_a4', 'kegunaan' => 'non_praktik'],
            ['kode' => 'TJKT-NET-022', 'cat' => 'NET', 'nama' => 'PoE Injector 9V DC', 'spek' => 'Passive PoE Injector Output 9V DC (untuk Access Point 9V seperti TL-WA701ND)', 'mode' => $S, 'preset' => 'flag_12'],
            ['kode' => 'TJKT-NET-023', 'cat' => 'NET', 'nama' => 'PoE Injector 12V DC', 'spek' => 'Passive PoE Injector Output 12V DC untuk perangkat jaringan 12V', 'mode' => $S, 'preset' => 'flag_12'],
            ['kode' => 'TJKT-NET-024', 'cat' => 'NET', 'nama' => 'PoE Injector 19V DC', 'spek' => 'Passive PoE Injector Output 19V DC untuk perangkat jaringan 19V', 'mode' => $S, 'preset' => 'flag_12'],
            ['kode' => 'TJKT-NET-025', 'cat' => 'NET', 'nama' => 'PoE Injector 24V DC', 'spek' => 'Passive PoE Injector Output 24V DC (MikroTik / Ubiquiti 24V)', 'mode' => $S, 'preset' => 'flag_12'],
            ['kode' => 'TJKT-NET-026', 'cat' => 'NET', 'nama' => 'PoE Injector 48V DC', 'spek' => 'PoE Injector Output 48V DC (IEEE 802.3af/at untuk AP/Switch/IP Camera 48V)', 'mode' => $S, 'preset' => 'flag_12'],
            ['kode' => 'TJKT-NET-027', 'cat' => 'NET', 'nama' => 'PoE Splitter', 'spek' => 'Pemisah jalur daya DC dan data Ethernet RJ45 dari kabel PoE', 'mode' => $S, 'preset' => 'mini_12'],
            ['kode' => 'TJKT-NET-028', 'cat' => 'NET', 'nama' => 'Anti Surge SPC', 'spek' => 'Ethernet Surge Protector penangkal lonjakan petir/tegangan pada kabel UTP', 'mode' => $S, 'preset' => 'mini_12'],
            ['kode' => 'TJKT-NET-029', 'cat' => 'NET', 'nama' => 'Anti Surge Ubiquiti ETH-SP', 'spek' => 'Ubiquiti ETH-SP-G2 Gigabit Ethernet ESD/Surge Protector outdoor/indoor', 'mode' => $S, 'preset' => 'mini_12'],
            ['kode' => 'TJKT-NET-030', 'cat' => 'NET', 'nama' => 'Watt Meter Digital AC', 'spek' => 'Alat ukur daya (Watt), tegangan (V), arus (A), dan kWh listrik AC 220V', 'mode' => $S, 'preset' => 'medium_24'],

            // ======================== 2. FO — Fiber Optik FTTH & GPON ========================
            ['kode' => 'TJKT-FO-001', 'cat' => 'FO', 'nama' => 'Optical Fusion Splicer Joinwit JW4109', 'spek' => 'Core-to-Core PAS alignment, SM/MM/DS/NZDS, loss 0.02 dB (SM), splice ≤9 dtk, heater ≤30 dtk, LCD 5" X/Y zoom 350×, baterai Li-ion 7200mAh (~200–300 siklus)', 'mode' => $S, 'kritis' => true, 'preset' => 'medium_24'],
            ['kode' => 'TJKT-FO-002', 'cat' => 'FO', 'nama' => 'OTDR Novker NK5600', 'spek' => 'Single-Mode 1310/1550nm, dynamic range 32/30 dB, event dead zone ≤0.8m, jarak uji s/d 240 km, layar sentuh 5.6", built-in VFL/OPM/OLS, format .sor, USB/SD/LAN', 'mode' => $S, 'kritis' => true, 'preset' => 'medium_24'],
            ['kode' => 'TJKT-FO-003', 'cat' => 'FO', 'nama' => 'Charger OTDR Novker NK5600', 'spek' => 'Adaptor pengisi daya khusus unit OTDR Novker NK5600, input AC 100–240V', 'mode' => $S, 'preset' => 'flag_12'],
            ['kode' => 'TJKT-FO-004', 'cat' => 'FO', 'nama' => 'Optical Power Meter (OPM) TriBrer APM80T-V10', 'spek' => 'Rentang ukur +10 s/d -70 dBm, 6 λ (850/1300/1310/1490/1550/1625nm), akurasi ±0.2 dB, built-in VFL 10mW, konektor universal 2.5mm, memori 500 data, 2×AA/Micro-USB', 'mode' => $S, 'kritis' => true, 'preset' => 'medium_24'],
            ['kode' => 'TJKT-FO-005', 'cat' => 'FO', 'nama' => 'Optical Light Source (OLS)', 'spek' => 'Sumber cahaya laser stabil 1310/1550nm untuk pengujian redaman link fiber optik bersama OPM', 'mode' => $S, 'kritis' => true, 'preset' => 'medium_24'],
            ['kode' => 'TJKT-FO-006', 'cat' => 'FO', 'nama' => 'Optical Line Terminal (OLT) HSGQ', 'spek' => 'OLT GPON/EPON 1U rackmount 19", standar ITU-T G.984 / IEEE 802.3ah, split ratio s/d 1:128, uplink Gigabit RJ45 & SFP/SFP+, VLAN/QoS/CLI/Web/SNMP, AC 100–240V', 'mode' => $S, 'kritis' => true, 'preset' => 'medium_24'],
            ['kode' => 'TJKT-FO-007', 'cat' => 'FO', 'nama' => 'Optical Network Terminal (ONT) Huawei EchoLife', 'spek' => 'GPON ITU-T G.984 Class B+, konektor SC/UPC–APC, 1310/1490nm, sensitivitas ≤-27 dBm, Ethernet LAN (FE/GE), manajemen OMCI/Web UI, daya 12V DC', 'mode' => $S, 'preset' => 'medium_24'],
            ['kode' => 'TJKT-FO-008', 'cat' => 'FO', 'nama' => 'Optical Network Terminal (ONT) Standar', 'spek' => 'Modem ONU/ONT EPON/GPON konektor optik SC + port Ethernet RJ45, daya 12V DC', 'mode' => $S, 'preset' => 'medium_24'],
            ['kode' => 'TJKT-FO-009', 'cat' => 'FO', 'nama' => 'Fiber Cleaver Sentakel Model Sumitomo FC-6S', 'spek' => 'High-precision fiber cleaver SM/MM, cladding 125µm, coating 0.25–0.9mm, panjang potong 9–16mm, sudut potong tipikal ≤0.5°, umur pisau ~36.000 potongan (12 posisi)', 'mode' => $S, 'kritis' => true, 'preset' => 'mini_12'],
            ['kode' => 'TJKT-FO-010', 'cat' => 'FO', 'nama' => 'Fiber Cleaver Joinwit', 'spek' => 'Pemotong core fiber optik presisi SM/MM, cladding 125µm, sudut potong ≤0.5° dengan kotak penampung sisa serat', 'mode' => $S, 'kritis' => true, 'preset' => 'mini_12'],
            ['kode' => 'TJKT-FO-011', 'cat' => 'FO', 'nama' => 'Fiber Stripper Tarmoc TFA-253SFO', 'spek' => 'Tang pengupas FO 3 lubang stainless steel — lubang 1: coating 250µm→125µm, lubang 2: buffer 0.9mm, lubang 3: jaket patchcord 2.0–3.0mm', 'mode' => $S, 'preset' => 'mini_12'],
            ['kode' => 'TJKT-FO-012', 'cat' => 'FO', 'nama' => 'Stripper Cladding FO Joinwit', 'spek' => 'Tang pengupas lapisan coating/cladding fiber optik 125µm/250µm presisi', 'mode' => $S, 'preset' => 'mini_12'],
            ['kode' => 'TJKT-FO-013', 'cat' => 'FO', 'nama' => 'Stripper Dropcore FO', 'spek' => 'Tang pengupas jaket luar kabel dropcore FTTH 1–2 core dengan pengukur panjang kupasan', 'mode' => $S, 'preset' => 'mini_12'],
            ['kode' => 'TJKT-FO-014', 'cat' => 'FO', 'nama' => 'Botol Pompa Alkohol FTTH', 'spek' => 'Dispenser pompa tekan bahan plastik/HDPE anti-tumpah untuk alkohol pembersih core fiber optik', 'mode' => $S, 'preset' => 'mini_12'],
            ['kode' => 'TJKT-FO-015', 'cat' => 'FO', 'nama' => 'Media Converter HTB 10/100 Mbps', 'spek' => 'Konverter Fiber Optik Single-Mode SC (A/B) ke Ethernet RJ45 10/100 Mbps, jarak s/d 20 km, daya 5V DC', 'mode' => $S, 'preset' => 'mini_12'],
            ['kode' => 'TJKT-FO-016', 'cat' => 'FO', 'nama' => 'Media Converter HTB Gigabit 10/100/1000', 'spek' => 'Konverter Fiber Optik Single-Mode SC ke Gigabit Ethernet 10/100/1000 Mbps, jarak s/d 20 km, daya 5V DC', 'mode' => $S, 'preset' => 'mini_12'],
            ['kode' => 'TJKT-FO-017', 'cat' => 'FO', 'nama' => 'Media Converter HTB Slot SFP', 'spek' => 'Konverter Media Fiber Optik dengan 1× Slot SFP 1.25G + 1× Port RJ45 Gigabit, daya 5V DC', 'mode' => $S, 'preset' => 'mini_12'],
            ['kode' => 'TJKT-FO-018', 'cat' => 'FO', 'nama' => 'Adaptor Media Converter HTB', 'spek' => 'Adaptor daya switching AC 100–240V ke output DC 5V 2A untuk Media Converter HTB', 'mode' => $S, 'preset' => 'flag_12'],
            ['kode' => 'TJKT-FO-019', 'cat' => 'FO', 'nama' => 'Modul SFP Fiber Optic 1.25G', 'spek' => 'Transceiver optik SFP 1.25Gbps Single-Mode LC/BiDi, jarak 3–20 km, hot-pluggable', 'mode' => $S, 'preset' => 'mini_12'],
            ['kode' => 'TJKT-FO-020', 'cat' => 'FO', 'nama' => 'Modul SFP GPON OLT', 'spek' => 'Transceiver SFP GPON OLT Class C+/C++, Tx 1490nm / Rx 1310nm, konektor SC/UPC, rasio split 1:128', 'mode' => $S, 'kritis' => true, 'preset' => 'mini_12'],
            ['kode' => 'TJKT-FO-021', 'cat' => 'FO', 'nama' => 'Modul SFP LAN UTP (RJ45)', 'spek' => 'Transceiver SFP Copper 1000BASE-T ke konektor RJ45, jarak s/d 100m via Cat5e/Cat6', 'mode' => $S, 'preset' => 'mini_12'],
            ['kode' => 'TJKT-FO-022', 'cat' => 'FO', 'nama' => 'SFP Heatsink', 'spek' => 'Pendingin pasif aluminium untuk modul SFP pada switch/OLT rack server', 'mode' => $B, 'preset' => 'medium_24'],
            ['kode' => 'TJKT-FO-023', 'cat' => 'FO', 'nama' => 'Fiber Optic Splitter PLC', 'spek' => 'Passive Optical Splitter PLC Single-Mode konektor SC/UPC-APC untuk distribusi jaringan FTTH', 'mode' => $S, 'preset' => 'mini_12'],
            ['kode' => 'TJKT-FO-024', 'cat' => 'FO', 'nama' => 'Joint Closure PAZ Inline 12–24 Core', 'spek' => 'Inline fiber splice closure kapasitas 12–24 core, weatherproof/waterproof tahan debu & air, untuk instalasi udara (aerial) & duct, dimensi ~320×180×120mm', 'mode' => $S, 'preset' => 'medium_24'],
            ['kode' => 'TJKT-FO-025', 'cat' => 'FO', 'nama' => 'ODP Solid 12 Core', 'spek' => 'Optical Distribution Point outdoor standar FTTH kapasitas 12 core, bodi ABS/PC tahan UV, IP55–IP66, slot adapter SC & tray manajemen splitter PLC, pasang tiang/dinding', 'mode' => $S, 'preset' => 'medium_24'],
            ['kode' => 'TJKT-FO-026', 'cat' => 'FO', 'nama' => 'OTB Joint Box Mini 2–4 Core', 'spek' => 'Kotak terminasi/sambung FO kapasitas 2–4 core, bodi ABS tahan benturan, dukungan adapter SC Simplex & sleeve pelindung splice, pasang dinding/drop cable FTTH', 'mode' => $S, 'preset' => 'mini_12'],
            ['kode' => 'TJKT-FO-027', 'cat' => 'FO', 'nama' => 'Fiber Patchcord NETLINK (Single Mode)', 'spek' => 'Patchcord FO terminasi pabrik SC–SC/SC–LC (UPC/APC), SM 9/125µm OS2, Insertion Loss ≤0.3 dB, Return Loss ≥50 dB (UPC) / ≥60 dB (APC), jaket kuning LSZH', 'mode' => $B, 'preset' => 'flag_12'],
            ['kode' => 'TJKT-FO-028', 'cat' => 'FO', 'nama' => 'Fiber Patchcord NETLINK (Multi Mode)', 'spek' => 'Patchcord FO terminasi pabrik SC–SC/SC–LC, MM 50/125µm OM2/OM3, Insertion Loss ≤0.3 dB, jaket LSZH 2.0/3.0mm', 'mode' => $B, 'preset' => 'flag_12'],

            // ======================== 3. PC — Komputer, Laptop & Display ========================
            ['kode' => 'TJKT-PC-001', 'cat' => 'PC', 'nama' => 'PC Praktik Lab TJKT', 'spek' => 'Komputer Desktop Praktik Ruang Lab TJKT (casing ATX/mATX, motherboard, CPU, RAM, storage, PSU)', 'mode' => $S, 'preset' => 'detail_a4'],
            ['kode' => 'TJKT-PC-002', 'cat' => 'PC', 'nama' => 'PC Praktik Lab KKPI', 'spek' => 'Komputer Desktop Praktik Ruang Lab KKPI (casing ATX/mATX, motherboard, CPU, RAM, storage, PSU)', 'mode' => $S, 'preset' => 'detail_a4'],
            ['kode' => 'TJKT-PC-003', 'cat' => 'PC', 'nama' => 'PC Operasional Kantor TJKT', 'spek' => 'Komputer Desktop administrasi & operasional Ruang Kantor TJKT', 'mode' => $S, 'preset' => 'detail_a4', 'kegunaan' => 'non_praktik'],
            ['kode' => 'TJKT-PC-004', 'cat' => 'PC', 'nama' => 'Mini PC Lenovo ThinkCentre M710q', 'spek' => 'Desktop Tiny 1L Lenovo ThinkCentre, Intel Core/Pentium, DDR4 SODIMM, NVMe/SATA, Gigabit LAN, DisplayPort, USB 3.0, adaptor eksternal 65W', 'mode' => $S, 'kritis' => true, 'preset' => 'medium_24'],
            ['kode' => 'TJKT-PC-005', 'cat' => 'PC', 'nama' => 'Laptop HP Victus 15 (Core i5-13420H)', 'spek' => 'Intel Core i5-13420H (8C/12T s/d 4.6GHz), layar 15.6" FHD IPS 144Hz, RAM DDR4-3200, SSD 512GB PCIe Gen4 NVMe, NVIDIA RTX 3050/4050 6GB, Wi-Fi 6, RJ45, HDMI 2.1, USB-C', 'mode' => $S, 'kritis' => true, 'preset' => 'medium_24'],
            ['kode' => 'TJKT-PC-006', 'cat' => 'PC', 'nama' => 'Charger Laptop HP Victus', 'spek' => 'Adaptor daya asli HP Smart AC Power Adapter 150W/200W untuk Laptop HP Victus 15', 'mode' => $S, 'preset' => 'flag_12'],
            ['kode' => 'TJKT-PC-007', 'cat' => 'PC', 'nama' => 'Monitor Acer 14"', 'spek' => 'Monitor LCD/LED Acer ukuran 14 inci, input VGA', 'mode' => $S, 'preset' => 'detail_a4'],
            ['kode' => 'TJKT-PC-008', 'cat' => 'PC', 'nama' => 'Monitor LG 14"', 'spek' => 'Monitor LCD/LED LG ukuran 14 inci, input VGA', 'mode' => $S, 'preset' => 'detail_a4'],
            ['kode' => 'TJKT-PC-009', 'cat' => 'PC', 'nama' => 'Monitor Samsung 14"', 'spek' => 'Monitor LCD/LED Samsung ukuran 14 inci, input VGA', 'mode' => $S, 'preset' => 'detail_a4'],
            ['kode' => 'TJKT-PC-010', 'cat' => 'PC', 'nama' => 'Monitor IBM 17"', 'spek' => 'Monitor LCD Square IBM ukuran 17 inci, resolusi 1280×1024, input VGA', 'mode' => $S, 'preset' => 'detail_a4'],
            ['kode' => 'TJKT-PC-011', 'cat' => 'PC', 'nama' => 'Monitor Acer 21"', 'spek' => 'Monitor LED Widescreen Acer 21.5 inci Full HD 1920×1080, input VGA/HDMI', 'mode' => $S, 'preset' => 'detail_a4'],
            ['kode' => 'TJKT-PC-012', 'cat' => 'PC', 'nama' => 'Monitor AOC 21"', 'spek' => 'Monitor LED Widescreen AOC 21.5 inci Full HD 1920×1080, input VGA/HDMI', 'mode' => $S, 'preset' => 'detail_a4'],
            ['kode' => 'TJKT-PC-013', 'cat' => 'PC', 'nama' => 'Monitor HP 21"', 'spek' => 'Monitor LED Widescreen HP 21.5 inci Full HD 1920×1080, input VGA/HDMI', 'mode' => $S, 'preset' => 'detail_a4'],
            ['kode' => 'TJKT-PC-014', 'cat' => 'PC', 'nama' => 'Monitor AOC 22"', 'spek' => 'Monitor LED Widescreen AOC 22 inci Full HD 1920×1080, input VGA/HDMI', 'mode' => $S, 'preset' => 'detail_a4'],
            ['kode' => 'TJKT-PC-015', 'cat' => 'PC', 'nama' => 'LED Projector', 'spek' => 'Proyektor presentasi ruang praktik/kelas, input HDMI & VGA, daya AC 220V', 'mode' => $S, 'kritis' => true, 'preset' => 'medium_24'],
            ['kode' => 'TJKT-PC-016', 'cat' => 'PC', 'nama' => 'Smartboard Interactive ITBoard', 'spek' => 'Interactive Flat Panel 4K UHD (3840×2160) DLED anti-glare tempered glass, IR multi-touch s/d 40 titik, Android Octa-Core 8GB/128GB, kamera 48MP + 8-array mic, Wi-Fi 6, HDMI/USB-C/slot OPS', 'mode' => $S, 'kritis' => true, 'preset' => 'detail_a4'],
            ['kode' => 'TJKT-PC-017', 'cat' => 'PC', 'nama' => 'Printer Epson L360', 'spek' => 'Printer InkTank All-in-One (Print/Scan/Copy), 4 warna CMYK seri tinta 664, USB 2.0', 'mode' => $S, 'kritis' => true, 'preset' => 'medium_24', 'kegunaan' => 'non_praktik'],
            ['kode' => 'TJKT-PC-018', 'cat' => 'PC', 'nama' => 'Mesin Absensi FingerSpot', 'spek' => 'Terminal absensi biometrik sidik jari FingerSpot, koneksi LAN/USB, daya DC', 'mode' => $S, 'preset' => 'medium_24', 'kegunaan' => 'non_praktik'],

            // ======================== 4. CMP — Komponen & Sparepart PC ========================
            ['kode' => 'TJKT-CMP-001', 'cat' => 'CMP', 'nama' => 'RAM DDR2 1GB', 'spek' => 'Modul memori PC Desktop DIMM DDR2 1GB 240-Pin', 'mode' => $S, 'preset' => 'mini_12'],
            ['kode' => 'TJKT-CMP-002', 'cat' => 'CMP', 'nama' => 'RAM DDR3 1GB', 'spek' => 'Modul memori PC Desktop DIMM DDR3 1GB PC3-10600/12800', 'mode' => $S, 'preset' => 'mini_12'],
            ['kode' => 'TJKT-CMP-003', 'cat' => 'CMP', 'nama' => 'RAM DDR3 2GB', 'spek' => 'Modul memori PC Desktop DIMM DDR3 2GB PC3-10600/12800', 'mode' => $S, 'preset' => 'mini_12'],
            ['kode' => 'TJKT-CMP-004', 'cat' => 'CMP', 'nama' => 'RAM DDR3 4GB', 'spek' => 'Modul memori PC Desktop DIMM DDR3 4GB PC3-12800 (1600MHz)', 'mode' => $S, 'preset' => 'mini_12'],
            ['kode' => 'TJKT-CMP-005', 'cat' => 'CMP', 'nama' => 'RAM DDR3 8GB', 'spek' => 'Modul memori PC Desktop DIMM DDR3 8GB PC3-12800 (1600MHz)', 'mode' => $S, 'preset' => 'mini_12'],
            ['kode' => 'TJKT-CMP-006', 'cat' => 'CMP', 'nama' => 'RAM DDR4 4GB', 'spek' => 'Modul memori PC Desktop DIMM DDR4 4GB 2666/3200MHz', 'mode' => $S, 'preset' => 'mini_12'],
            ['kode' => 'TJKT-CMP-007', 'cat' => 'CMP', 'nama' => 'RAM DDR4 8GB', 'spek' => 'Modul memori PC Desktop DIMM DDR4 8GB 2666/3200MHz', 'mode' => $S, 'preset' => 'mini_12'],
            ['kode' => 'TJKT-CMP-008', 'cat' => 'CMP', 'nama' => 'SSD NVMe Samsung', 'spek' => 'Solid State Drive M.2 2280 PCIe NVMe Samsung (lengkapi kapasitas GB pada unit/catatan)', 'mode' => $S, 'preset' => 'mini_12'],
            ['kode' => 'TJKT-CMP-009', 'cat' => 'CMP', 'nama' => 'SSD NVMe V-Gen', 'spek' => 'Solid State Drive M.2 2280 PCIe Gen3 NVMe V-Gen (lengkapi kapasitas GB pada unit/catatan)', 'mode' => $S, 'preset' => 'mini_12'],
            ['kode' => 'TJKT-CMP-010', 'cat' => 'CMP', 'nama' => 'SSD NVMe WD-Black', 'spek' => 'Solid State Drive M.2 2280 PCIe Gen4 NVMe Western Digital Black (lengkapi kapasitas GB)', 'mode' => $S, 'preset' => 'mini_12'],
            ['kode' => 'TJKT-CMP-011', 'cat' => 'CMP', 'nama' => 'PCIe NVMe Enclosure 3.0 ×4', 'spek' => 'Adapter Expansion Card PCIe 3.0 ×4 to M.2 NVMe M-Key untuk PC Desktop', 'mode' => $S, 'preset' => 'mini_12'],
            ['kode' => 'TJKT-CMP-012', 'cat' => 'CMP', 'nama' => 'PCIe NVMe Enclosure Gen4 ×16', 'spek' => 'Adapter Expansion Card PCIe Gen4 ×16 to M.2 NVMe untuk PC/Server', 'mode' => $S, 'preset' => 'mini_12'],
            ['kode' => 'TJKT-CMP-013', 'cat' => 'CMP', 'nama' => 'PCIe NVMe Enclosure ×16', 'spek' => 'Adapter Expansion Card PCIe ×16 to M.2 NVMe untuk PC Desktop', 'mode' => $S, 'preset' => 'mini_12'],
            ['kode' => 'TJKT-CMP-014', 'cat' => 'CMP', 'nama' => 'Processor Intel Core2 / Pentium (LGA 775)', 'spek' => 'CPU Desktop Intel Core 2 Duo / Pentium Dual-Core Socket LGA 775', 'mode' => $S, 'preset' => 'mini_12'],
            ['kode' => 'TJKT-CMP-015', 'cat' => 'CMP', 'nama' => 'Processor Intel Core i3 Gen 3 / G630-G2030 (LGA 1155)', 'spek' => 'CPU Desktop Intel Socket LGA 1155 Sandy/Ivy Bridge', 'mode' => $S, 'preset' => 'mini_12'],
            ['kode' => 'TJKT-CMP-016', 'cat' => 'CMP', 'nama' => 'Processor Intel Core i5 Gen 4 (LGA 1150)', 'spek' => 'CPU Desktop Intel Core i5 Haswell 4-Core Socket LGA 1150', 'mode' => $S, 'preset' => 'mini_12'],
            ['kode' => 'TJKT-CMP-017', 'cat' => 'CMP', 'nama' => 'Heatsink Fan LGA 775', 'spek' => 'Pendingin prosesor heatsink aluminium + kipas 4-Pin PWM Socket LGA 775', 'mode' => $S, 'preset' => 'mini_12'],
            ['kode' => 'TJKT-CMP-018', 'cat' => 'CMP', 'nama' => 'Heatsink Fan LGA 1155', 'spek' => 'Pendingin prosesor heatsink aluminium + kipas 4-Pin PWM Socket LGA 1155/1150', 'mode' => $S, 'preset' => 'mini_12'],
            ['kode' => 'TJKT-CMP-019', 'cat' => 'CMP', 'nama' => 'Power Supply Unit (PSU) PC ATX', 'spek' => 'PSU Desktop standar ATX 24-Pin + 4-Pin CPU + SATA, input AC 220V', 'mode' => $S, 'preset' => 'medium_24'],
            ['kode' => 'TJKT-CMP-020', 'cat' => 'CMP', 'nama' => 'PCI SoundCard', 'spek' => 'Kartu ekspansi audio slot PCI dengan jack 3.5mm Line-Out/Mic', 'mode' => $S, 'preset' => 'mini_12'],
            ['kode' => 'TJKT-CMP-021', 'cat' => 'CMP', 'nama' => 'PCI LAN Card', 'spek' => 'Kartu ekspansi jaringan Ethernet RJ45 slot PCI/PCIe', 'mode' => $S, 'preset' => 'mini_12'],
            ['kode' => 'TJKT-CMP-022', 'cat' => 'CMP', 'nama' => 'PCI WLAN Card', 'spek' => 'Kartu ekspansi Wi-Fi Wireless 802.11n/ac slot PCI/PCIe dengan antena eksternal', 'mode' => $S, 'preset' => 'mini_12'],
            ['kode' => 'TJKT-CMP-023', 'cat' => 'CMP', 'nama' => 'Optical Drive DVD-RW Internal', 'spek' => 'Drive optik baca/tulis CD/DVD 5.25" antarmuka SATA internal PC', 'mode' => $S, 'preset' => 'medium_24'],

            // ======================== 5. IOT — IoT, Mikrokontroler & Sensor ========================
            ['kode' => 'TJKT-IOT-001', 'cat' => 'IOT', 'nama' => 'NodeMCU ESP8266 V3', 'spek' => 'Development board IoT Wi-Fi 2.4GHz ESP-12E, USB-to-Serial CH340, 11 GPIO, Micro-USB 5V', 'mode' => $S, 'preset' => 'mini_12'],
            ['kode' => 'TJKT-IOT-002', 'cat' => 'IOT', 'nama' => 'Generic ESP8266 Module', 'spek' => 'Modul mikrokontroler Wi-Fi 802.11b/g/n SoC ESP8266, tegangan kerja 3.3V', 'mode' => $S, 'preset' => 'mini_12'],
            ['kode' => 'TJKT-IOT-003', 'cat' => 'IOT', 'nama' => 'Modul Relay 4 Channel 3V DC', 'spek' => 'Relay board 4 kanal optocoupler trigger 3.3V DC (kompatibel ESP8266/ESP32), beban AC 250V 10A', 'mode' => $S, 'preset' => 'mini_12'],
            ['kode' => 'TJKT-IOT-004', 'cat' => 'IOT', 'nama' => 'Modul Relay 4 Channel 5V DC', 'spek' => 'Relay board 4 kanal optocoupler trigger 5V DC (kompatibel Arduino/MCU), beban AC 250V 10A', 'mode' => $S, 'preset' => 'mini_12'],
            ['kode' => 'TJKT-IOT-005', 'cat' => 'IOT', 'nama' => 'Breadboard Praktik IoT', 'spek' => 'Papan proyek tanpa solder (solderless protoboard) 400/830 titik koneksi', 'mode' => $S, 'preset' => 'mini_12'],
            ['kode' => 'TJKT-IOT-006', 'cat' => 'IOT', 'nama' => 'Sensor Suhu & Kelembapan DHT11/DHT22', 'spek' => 'Sensor digital suhu & kelembapan udara single-wire, tegangan 3.3–5V DC', 'mode' => $S, 'preset' => 'micro_12x12'],
            ['kode' => 'TJKT-IOT-007', 'cat' => 'IOT', 'nama' => 'Sensor Kelembapan Tanah (Soil Moisture)', 'spek' => 'Probe sensor kelembapan tanah + modul komparator LM393 output Analog/Digital 3.3–5V DC', 'mode' => $S, 'preset' => 'micro_12x12'],
            ['kode' => 'TJKT-IOT-008', 'cat' => 'IOT', 'nama' => 'LAN Module IoT (Ethernet)', 'spek' => 'Modul Ethernet RJ45 antarmuka SPI (ENC28J60/W5500) untuk Arduino/ESP', 'mode' => $S, 'preset' => 'micro_12x12'],
            ['kode' => 'TJKT-IOT-009', 'cat' => 'IOT', 'nama' => 'Adaptor USB A Male to Micro USB Female', 'spek' => 'Konverter konektor daya/data USB Type-A Male ke Micro-USB Female', 'mode' => $B, 'preset' => 'mini_12'],

            // ======================== 6. TLS — Perkakas & Alat Servis ========================
            ['kode' => 'TJKT-TLS-001', 'cat' => 'TLS', 'nama' => 'Tang Krimping RJ45 Deli', 'spek' => 'Crimping tool RJ45/RJ11 merk Deli, bodi baja karbon dengan pemotong & pengupas kabel UTP', 'mode' => $S, 'preset' => 'mini_12'],
            ['kode' => 'TJKT-TLS-002', 'cat' => 'TLS', 'nama' => 'Tang Krimping RJ45 Standar', 'spek' => 'Crimping tool 8P8C/6P6C praktik crimping kabel jaringan UTP', 'mode' => $B, 'preset' => 'medium_24'],
            ['kode' => 'TJKT-TLS-003', 'cat' => 'TLS', 'nama' => 'LAN Cable Tester Merah', 'spek' => 'Penguji kontinuitas pin 1–8 & ground kabel UTP RJ45/RJ11 (unit master + remote, baterai 9V)', 'mode' => $S, 'preset' => 'mini_12'],
            ['kode' => 'TJKT-TLS-004', 'cat' => 'TLS', 'nama' => 'LAN Cable Tester Standar', 'spek' => 'Penguji koneksi kabel LAN RJ45/RJ11 indikator LED 1–8 (master + remote)', 'mode' => $S, 'preset' => 'mini_12'],
            ['kode' => 'TJKT-TLS-005', 'cat' => 'TLS', 'nama' => 'Obeng Plus (+)', 'spek' => 'Obeng kembang Phillips bermagnet untuk perakitan & bongkar pasang PC', 'mode' => $B, 'preset' => 'medium_24'],
            ['kode' => 'TJKT-TLS-006', 'cat' => 'TLS', 'nama' => 'Obeng Minus (−)', 'spek' => 'Obeng pipih slotted untuk perakitan & servis perangkat', 'mode' => $B, 'preset' => 'medium_24'],
            ['kode' => 'TJKT-TLS-007', 'cat' => 'TLS', 'nama' => 'Obeng Plus-Minus (±)', 'spek' => 'Obeng bolak-balik 2-in-1 ujung Phillips & Slotted', 'mode' => $B, 'preset' => 'medium_24'],
            ['kode' => 'TJKT-TLS-008', 'cat' => 'TLS', 'nama' => 'Obeng HP Presisi', 'spek' => 'Obeng mikro presisi untuk pembongkaran smartphone & komponen SMD', 'mode' => $B, 'preset' => 'medium_24'],
            ['kode' => 'TJKT-TLS-009', 'cat' => 'TLS', 'nama' => 'Kuas Pembersih PC', 'spek' => 'Kuas bulu halus pembersih debu motherboard, heatsink, dan casing PC', 'mode' => $B, 'preset' => 'medium_24'],
            ['kode' => 'TJKT-TLS-010', 'cat' => 'TLS', 'nama' => 'Mini Vacuum Cleaner', 'spek' => 'Penyedot debu portable mini untuk pembersihan keyboard & perangkat elektronik', 'mode' => $S, 'preset' => 'medium_24'],
            ['kode' => 'TJKT-TLS-011', 'cat' => 'TLS', 'nama' => 'TalkBaut 2-Way Radio (HT)', 'spek' => 'Handy Talky radio komunikasi 2 arah UHF/VHF portable untuk koordinasi kegiatan', 'mode' => $S, 'kritis' => true, 'preset' => 'medium_24'],
            ['kode' => 'TJKT-TLS-012', 'cat' => 'TLS', 'nama' => 'Blower Servis Kasadi', 'spek' => 'Hot Air SMD Rework Station Kasadi pengatur suhu & aliran udara untuk servis PCB/IC', 'mode' => $S, 'kritis' => true, 'preset' => 'medium_24'],
            ['kode' => 'TJKT-TLS-013', 'cat' => 'TLS', 'nama' => 'Mini Blower Elektronika', 'spek' => 'Blower angin listrik portable untuk pembersihan debu perangkat PC/jaringan', 'mode' => $S, 'preset' => 'medium_24'],
            ['kode' => 'TJKT-TLS-014', 'cat' => 'TLS', 'nama' => 'Solder Hakko', 'spek' => 'Soldering Iron Hakko elemen pemanas keramik, AC 220V untuk penyolderan komponen elektronika', 'mode' => $S, 'preset' => 'mini_12'],
            ['kode' => 'TJKT-TLS-015', 'cat' => 'TLS', 'nama' => 'Power Supply DC 1501T', 'spek' => 'Adjustable DC Bench Power Supply 0–15V 1A dengan indikator tegangan/arus & proteksi short', 'mode' => $S, 'kritis' => true, 'preset' => 'medium_24'],
            ['kode' => 'TJKT-TLS-016', 'cat' => 'TLS', 'nama' => 'Multitester Digital', 'spek' => 'Multimeter digital pengukur tegangan AC/DC, arus, resistansi (Ohm), dioda & buzzer kontinuitas', 'mode' => $S, 'preset' => 'medium_24'],
            ['kode' => 'TJKT-TLS-017', 'cat' => 'TLS', 'nama' => 'Kaca Pembesar Servis', 'spek' => 'Lup / kaca pembesar meja untuk inspeksi jalur & solderan komponen mikro PCB', 'mode' => $S, 'preset' => 'medium_24'],
            ['kode' => 'TJKT-TLS-018', 'cat' => 'TLS', 'nama' => 'PCB Board Stand', 'spek' => 'Dudukan penjepit papan PCB/motherboard smartphone untuk memudahkan penyolderan', 'mode' => $S, 'preset' => 'medium_24'],
            ['kode' => 'TJKT-TLS-019', 'cat' => 'TLS', 'nama' => 'Pinset Servis Presisi', 'spek' => 'Pinset stainless steel ujung runcing/bengkok anti-statis untuk komponen SMD', 'mode' => $B, 'preset' => 'medium_24'],
            ['kode' => 'TJKT-TLS-020', 'cat' => 'TLS', 'nama' => 'Tools Mini Kape (Pry Tool)', 'spek' => 'Pembuka casing & konektor fleksibel laptop/smartphone', 'mode' => $B, 'preset' => 'medium_24'],
            ['kode' => 'TJKT-TLS-021', 'cat' => 'TLS', 'nama' => 'Tang Potong Kecil', 'spek' => 'Diagonal flush cutter presisi untuk pemotongan kaki komponen & kabel tipis', 'mode' => $B, 'preset' => 'medium_24'],

            // ======================== 7. ACC — Aksesori & Peripheral ========================
            ['kode' => 'TJKT-ACC-001', 'cat' => 'ACC', 'nama' => 'Keyboard USB', 'spek' => 'Keyboard standar QWERTY 104-key konektor USB Type-A (multi-merk operasional lab)', 'mode' => $B, 'preset' => 'medium_24'],
            ['kode' => 'TJKT-ACC-002', 'cat' => 'ACC', 'nama' => 'Keyboard PS/2', 'spek' => 'Keyboard standar QWERTY konektor PS/2 6-pin ungu', 'mode' => $B, 'preset' => 'medium_24'],
            ['kode' => 'TJKT-ACC-003', 'cat' => 'ACC', 'nama' => 'Mouse Optik USB', 'spek' => 'Mouse optik 3-tombol + scroll wheel konektor USB Type-A', 'mode' => $B, 'preset' => 'medium_24'],
            ['kode' => 'TJKT-ACC-004', 'cat' => 'ACC', 'nama' => 'Mouse Optik PS/2', 'spek' => 'Mouse optik konektor PS/2 6-pin hijau', 'mode' => $B, 'preset' => 'medium_24'],
            ['kode' => 'TJKT-ACC-005', 'cat' => 'ACC', 'nama' => 'Mousepad Acer', 'spek' => 'Alas mouse permukaan kain halus & dasar karet anti-slip merk Acer', 'mode' => $B, 'preset' => 'medium_24'],
            ['kode' => 'TJKT-ACC-006', 'cat' => 'ACC', 'nama' => 'Live Cam / Webcam USB', 'spek' => 'Kamera webcam USB 2.0 HD dengan mikrofon internal, klip monitor', 'mode' => $S, 'preset' => 'mini_12'],
            ['kode' => 'TJKT-ACC-007', 'cat' => 'ACC', 'nama' => '4-Port VGA Splitter', 'spek' => 'Pembagi sinyal video analog VGA 1 input ke 4 output monitor simultan, daya DC', 'mode' => $S, 'preset' => 'medium_24'],
            ['kode' => 'TJKT-ACC-008', 'cat' => 'ACC', 'nama' => 'Converter Display DP to VGA', 'spek' => 'Adapter konverter DisplayPort Male ke VGA Female 1080p', 'mode' => $B, 'preset' => 'mini_12'],
            ['kode' => 'TJKT-ACC-009', 'cat' => 'ACC', 'nama' => 'Converter Display HDMI to VGA', 'spek' => 'Adapter konverter HDMI Male ke VGA Female 1080p', 'mode' => $B, 'preset' => 'mini_12'],
            ['kode' => 'TJKT-ACC-010', 'cat' => 'ACC', 'nama' => 'OTG Kit', 'spek' => 'Adapter USB On-The-Go untuk menghubungkan perangkat USB ke smartphone/tablet', 'mode' => $B, 'preset' => 'mini_12'],
            ['kode' => 'TJKT-ACC-011', 'cat' => 'ACC', 'nama' => 'USB Bluetooth Dongle', 'spek' => 'Adapter Bluetooth USB nano receiver/transmitter untuk PC Desktop', 'mode' => $S, 'preset' => 'mini_12'],
            ['kode' => 'TJKT-ACC-012', 'cat' => 'ACC', 'nama' => 'Joystick KVM', 'spek' => 'Perangkat kontrol/switch KVM pengelola multi-PC', 'mode' => $S, 'preset' => 'medium_24'],
            ['kode' => 'TJKT-ACC-013', 'cat' => 'ACC', 'nama' => 'Cooling Pad Laptop', 'spek' => 'Alas pendingin laptop dengan kipas bertenaga USB 5V', 'mode' => $S, 'preset' => 'medium_24'],
            ['kode' => 'TJKT-ACC-014', 'cat' => 'ACC', 'nama' => 'Charger Baterai AA/AAA', 'spek' => 'Pengisi daya baterai isi ulang Ni-MH ukuran AA/AAA, input AC 220V', 'mode' => $S, 'preset' => 'medium_24'],

            // ======================== 8. KBL — Kabel & Kelistrikan (Bulk Returnable) ========================
            ['kode' => 'TJKT-KBL-001', 'cat' => 'KBL', 'nama' => 'Patchcord UTP LAN', 'spek' => 'Kabel jaringan UTP Cat5e/Cat6 terminasi RJ45 siap pakai untuk praktik & server', 'mode' => $B, 'preset' => 'medium_24'],
            ['kode' => 'TJKT-KBL-002', 'cat' => 'KBL', 'nama' => 'Kabel Power AC C13', 'spek' => 'Kabel daya listrik AC 220V colokan Schuko ke IEC C13 (PC/Monitor/Switch)', 'mode' => $B, 'preset' => 'medium_24'],
            ['kode' => 'TJKT-KBL-003', 'cat' => 'KBL', 'nama' => 'Kabel Power AC C5 (Mickey Mouse)', 'spek' => 'Kabel daya listrik AC 220V colokan Schuko ke IEC C5 3-pin (adaptor laptop/proyektor)', 'mode' => $B, 'preset' => 'medium_24'],
            ['kode' => 'TJKT-KBL-004', 'cat' => 'KBL', 'nama' => 'Kabel Power AC C7 (Angka 8)', 'spek' => 'Kabel daya listrik AC 220V colokan EU 2-pin ke IEC C7 angka 8 (printer/adaptor)', 'mode' => $B, 'preset' => 'medium_24'],
            ['kode' => 'TJKT-KBL-005', 'cat' => 'KBL', 'nama' => 'Kabel Power AC C13 Male–Female (UPS)', 'spek' => 'Kabel ekstensi daya IEC C14 Male ke IEC C13 Female untuk UPS/PDU rack server', 'mode' => $B, 'preset' => 'medium_24'],
            ['kode' => 'TJKT-KBL-006', 'cat' => 'KBL', 'nama' => 'Kabel VGA', 'spek' => 'Kabel display analog D-Sub 15-Pin Male–Male dengan mur pengunci', 'mode' => $B, 'preset' => 'medium_24'],
            ['kode' => 'TJKT-KBL-007', 'cat' => 'KBL', 'nama' => 'Kabel HDMI', 'spek' => 'Kabel audio-video digital High-Speed HDMI Male–Male 1080p/4K', 'mode' => $B, 'preset' => 'medium_24'],
            ['kode' => 'TJKT-KBL-008', 'cat' => 'KBL', 'nama' => 'Kabel Listrik Roll / Stop Kontak', 'spek' => 'Stop kontak ekstensi multi-lubang kabel roll AC 220V standar SNI', 'mode' => $B, 'preset' => 'medium_24'],
            ['kode' => 'TJKT-KBL-009', 'cat' => 'KBL', 'nama' => 'Stop Kontak Power T', 'spek' => 'Steker cabang 3 arah (T-Multi) AC 220V standar SNI', 'mode' => $B, 'preset' => 'medium_24'],
            ['kode' => 'TJKT-KBL-010', 'cat' => 'KBL', 'nama' => 'Kabel USB A to A', 'spek' => 'Kabel data USB Type-A Male ke Type-A Male', 'mode' => $B, 'preset' => 'medium_24'],
            ['kode' => 'TJKT-KBL-011', 'cat' => 'KBL', 'nama' => 'Kabel USB A to B', 'spek' => 'Kabel data USB Type-A ke USB Type-B (printer / Arduino Uno)', 'mode' => $B, 'preset' => 'medium_24'],
            ['kode' => 'TJKT-KBL-012', 'cat' => 'KBL', 'nama' => 'Kabel Micro USB', 'spek' => 'Kabel data & daya USB Type-A ke Micro-USB (NodeMCU / alat ukur)', 'mode' => $B, 'preset' => 'medium_24'],
            ['kode' => 'TJKT-KBL-013', 'cat' => 'KBL', 'nama' => 'Kabel Data SATA', 'spek' => 'Kabel data SATA III 6Gbps 7-pin untuk HDD/SSD/DVD internal PC', 'mode' => $B, 'preset' => 'medium_24'],
            ['kode' => 'TJKT-KBL-014', 'cat' => 'KBL', 'nama' => 'Kabel Data ATA / IDE', 'spek' => 'Kabel ribbon PATA/IDE 40/80-wire legacy untuk praktik pengenalan hardware', 'mode' => $B, 'preset' => 'medium_24'],

            // ======================== 9. SRP — Sarana & Prasarana Ruangan (KIR) ========================
            ['kode' => 'TJKT-SRP-001', 'cat' => 'SRP', 'nama' => 'Meja Praktik Siswa', 'spek' => 'Meja komputer ruang praktik Lab TJKT / Lab KKPI', 'mode' => $S, 'preset' => 'detail_a4', 'kegunaan' => 'non_praktik'],
            ['kode' => 'TJKT-SRP-002', 'cat' => 'SRP', 'nama' => 'Meja Guru', 'spek' => 'Meja kerja guru / instruktur di Kantor TJKT & ruang praktik', 'mode' => $S, 'preset' => 'detail_a4', 'kegunaan' => 'non_praktik'],
            ['kode' => 'TJKT-SRP-003', 'cat' => 'SRP', 'nama' => 'Kursi Praktik Siswa', 'spek' => 'Kursi duduk ruang praktik Lab TJKT / Lab KKPI', 'mode' => $S, 'preset' => 'detail_a4', 'kegunaan' => 'non_praktik'],
            ['kode' => 'TJKT-SRP-004', 'cat' => 'SRP', 'nama' => 'Kursi Guru', 'spek' => 'Kursi kerja guru di Kantor TJKT & ruang praktik', 'mode' => $S, 'preset' => 'detail_a4', 'kegunaan' => 'non_praktik'],
            ['kode' => 'TJKT-SRP-005', 'cat' => 'SRP', 'nama' => 'Almari Inventaris', 'spek' => 'Almari penyimpanan alat praktik & arsip jurusan TJKT', 'mode' => $S, 'preset' => 'detail_a4', 'kegunaan' => 'non_praktik'],
            ['kode' => 'TJKT-SRP-006', 'cat' => 'SRP', 'nama' => 'Rak Penyimpanan Gudang', 'spek' => 'Rak susun besi/kayu penyimpanan peralatan di Gudang & Kantor TJKT', 'mode' => $S, 'preset' => 'detail_a4', 'kegunaan' => 'non_praktik'],
            ['kode' => 'TJKT-SRP-007', 'cat' => 'SRP', 'nama' => 'Showcase / Etalase Kaca', 'spek' => 'Lemari pajang kaca untuk penyimpanan alat ukur & perangkat sensitif', 'mode' => $S, 'preset' => 'detail_a4', 'kegunaan' => 'non_praktik'],
            ['kode' => 'TJKT-SRP-008', 'cat' => 'SRP', 'nama' => 'Whiteboard Set', 'spek' => 'Papan tulis putih dinding/standing lengkap dengan tempat spidol & penghapus', 'mode' => $S, 'preset' => 'detail_a4', 'kegunaan' => 'non_praktik'],
            ['kode' => 'TJKT-SRP-009', 'cat' => 'SRP', 'nama' => 'Air Conditioner (AC) Split', 'spek' => 'Unit pendingin ruangan AC Split indoor+outdoor (Server Room & Lab)', 'mode' => $S, 'preset' => 'detail_a4', 'kegunaan' => 'non_praktik'],
            ['kode' => 'TJKT-SRP-010', 'cat' => 'SRP', 'nama' => 'Exhaust Fan', 'spek' => 'Kipas sirkulasi pembuangan udara panas dinding/plafon ruangan', 'mode' => $S, 'preset' => 'detail_a4', 'kegunaan' => 'non_praktik'],
            ['kode' => 'TJKT-SRP-011', 'cat' => 'SRP', 'nama' => 'Kipas Angin Ruangan', 'spek' => 'Kipas angin dinding/berdiri pendingin ruang lab & kantor', 'mode' => $S, 'preset' => 'detail_a4', 'kegunaan' => 'non_praktik'],
            ['kode' => 'TJKT-SRP-012', 'cat' => 'SRP', 'nama' => 'Dispenser Air Galon', 'spek' => 'Dispenser air minum panas/dingin Kantor TJKT', 'mode' => $S, 'preset' => 'detail_a4', 'kegunaan' => 'non_praktik'],
            ['kode' => 'TJKT-SRP-013', 'cat' => 'SRP', 'nama' => 'Speaker Aktif Ruangan', 'spek' => 'Speaker audio aktif pendukung pembelajaran di kantor/lab', 'mode' => $S, 'preset' => 'medium_24', 'kegunaan' => 'non_praktik'],
            ['kode' => 'TJKT-SRP-014', 'cat' => 'SRP', 'nama' => 'Jam Dinding', 'spek' => 'Jam dinding analog/digital penunjuk waktu ruangan', 'mode' => $S, 'preset' => 'medium_24', 'kegunaan' => 'non_praktik'],
            ['kode' => 'TJKT-SRP-015', 'cat' => 'SRP', 'nama' => 'Lampu Penerangan Ruangan', 'spek' => 'Lampu LED penerangan plafon ruang praktik & kantor TJKT', 'mode' => $S, 'preset' => 'medium_24', 'kegunaan' => 'non_praktik'],
        ];
    }

    /**
     * @return list<array{name: string, category: string, spec: string, unit: string, min_stock: int}>
     */
    public static function consumableCatalog(): array
    {
        return [
            ['name' => 'Kabel Fiber Optik Spectra Jellytube', 'category' => 'Kabel Roll/Meteran', 'spec' => 'Single-Mode ITU-T G.657A, Loose Jelly Tube anti-air, 3-Sling baja self-supporting outdoor/aerial, jaket PE/LSZH', 'unit' => 'meter', 'min_stock' => 50],
            ['name' => 'Kabel UTP Cat 6 Commscope', 'category' => 'Kabel Roll/Meteran', 'spec' => 'Unshielded Twisted Pair Cat 6 23AWG Solid Copper (305m/box)', 'unit' => 'meter', 'min_stock' => 30],
            ['name' => 'Kabel UTP Cat 6 Belden', 'category' => 'Kabel Roll/Meteran', 'spec' => 'Unshielded Twisted Pair Cat 6 23AWG Belden Original (305m/box)', 'unit' => 'meter', 'min_stock' => 30],
            ['name' => 'Kabel UTP Cat 5e Commscope', 'category' => 'Kabel Roll/Meteran', 'spec' => 'Unshielded Twisted Pair Cat 5e 24AWG Commscope (305m/box)', 'unit' => 'meter', 'min_stock' => 30],
            ['name' => 'Konektor RJ45 Standar', 'category' => 'Konektor & Material FO/LAN', 'spec' => 'Modular Plug 8P8C Cat5e/Cat6 standar praktik crimping', 'unit' => 'pcs', 'min_stock' => 50],
            ['name' => 'Konektor RJ45 Commscope', 'category' => 'Konektor & Material FO/LAN', 'spec' => 'Modular Plug 8P8C Original Commscope gold-plated', 'unit' => 'pcs', 'min_stock' => 30],
            ['name' => 'Konektor FO SC-APC (Hijau)', 'category' => 'Konektor & Material FO/LAN', 'spec' => 'Fast Connector Fiber Optik SC-APC hijau (angled polish)', 'unit' => 'pcs', 'min_stock' => 10],
            ['name' => 'Konektor FO SC-UPC (Biru)', 'category' => 'Konektor & Material FO/LAN', 'spec' => 'Fast Connector Fiber Optik SC-UPC biru (ultra physical contact)', 'unit' => 'pcs', 'min_stock' => 10],
            ['name' => 'Barrel FO (Adapter Coupler SC)', 'category' => 'Konektor & Material FO/LAN', 'spec' => 'Adapter penyambung SC-to-SC Simplex Fiber Optik', 'unit' => 'pcs', 'min_stock' => 10],
            ['name' => 'Baut Rak Server (Cage Nut M6)', 'category' => 'Konektor & Material FO/LAN', 'spec' => 'Set mur sangkar + baut M6 standar rackmount 19"', 'unit' => 'pcs', 'min_stock' => 20],
            ['name' => 'Jumper Wire IoT', 'category' => 'Bahan Servis & IoT', 'spec' => 'Kabel jumper Dupont Male-Male / Male-Female / Female-Female 20cm', 'unit' => 'set', 'min_stock' => 5],
            ['name' => 'Timah Solder (Tinol)', 'category' => 'Bahan Servis & IoT', 'spec' => 'Kawat timah solder rosin core diameter 0.8mm', 'unit' => 'roll', 'min_stock' => 2],
            ['name' => 'Flux Pasta Solder', 'category' => 'Bahan Servis & IoT', 'spec' => 'Pasta flux pembantu penyolderan komponen elektronika & SMD', 'unit' => 'pcs', 'min_stock' => 2],
            ['name' => 'Mata Solder (Tip Hakko)', 'category' => 'Bahan Servis & IoT', 'spec' => 'Ujung pengganti soldering iron tipe lancip/pisau', 'unit' => 'pcs', 'min_stock' => 2],
            ['name' => 'Solatip Anti Panas (Kapton Tape)', 'category' => 'Bahan Servis & IoT', 'spec' => 'Lakban isolasi polimida tahan panas untuk pelindung PCB saat blower', 'unit' => 'roll', 'min_stock' => 2],
            ['name' => 'Lem Body / LCD HP', 'category' => 'Bahan Servis & IoT', 'spec' => 'Perekat khusus frame/backdoor/LCD smartphone (B7000/T7000)', 'unit' => 'pcs', 'min_stock' => 2],
            ['name' => 'Alkohol 96% Pembersih Core FO', 'category' => 'Bahan Servis & IoT', 'spec' => 'Alkohol teknis 96% isi ulang pembersih core sebelum splicing FO', 'unit' => 'botol', 'min_stock' => 1],
            ['name' => 'Screen Cleaner', 'category' => 'Bahan Servis & IoT', 'spec' => 'Cairan pembersih layar monitor/laptop bebas alkohol keras', 'unit' => 'botol', 'min_stock' => 1],
            ['name' => 'Baterai AAA', 'category' => 'ATK & Operasional', 'spec' => 'Baterai alkaline 1.5V ukuran AAA untuk alat ukur/remote', 'unit' => 'pcs', 'min_stock' => 6],
            ['name' => 'Tinta Epson 664 Cyan (L360)', 'category' => 'ATK & Operasional', 'spec' => 'Tinta botol refill warna Cyan (biru) 70ml seri Epson 664', 'unit' => 'botol', 'min_stock' => 1],
            ['name' => 'Tinta Epson 664 Magenta (L360)', 'category' => 'ATK & Operasional', 'spec' => 'Tinta botol refill warna Magenta (merah) 70ml seri Epson 664', 'unit' => 'botol', 'min_stock' => 1],
            ['name' => 'Tinta Epson 664 Yellow (L360)', 'category' => 'ATK & Operasional', 'spec' => 'Tinta botol refill warna Yellow (kuning) 70ml seri Epson 664', 'unit' => 'botol', 'min_stock' => 1],
            ['name' => 'Tinta Epson 664 Black (L360)', 'category' => 'ATK & Operasional', 'spec' => 'Tinta botol refill warna Black (hitam) 70ml seri Epson 664', 'unit' => 'botol', 'min_stock' => 1],
            ['name' => 'CD-R Blank 700MB', 'category' => 'ATK & Operasional', 'spec' => 'Keping cakram optik rekam sekali 700MB / 80 menit', 'unit' => 'pcs', 'min_stock' => 5],
            ['name' => 'Ribbon Pack 7755', 'category' => 'ATK & Operasional', 'spec' => 'Pita printer / ribbon pack tipe 7755', 'unit' => 'pack', 'min_stock' => 1],
            ['name' => 'Ribbon Pack 8758', 'category' => 'ATK & Operasional', 'spec' => 'Pita printer / ribbon pack tipe 8758', 'unit' => 'pack', 'min_stock' => 1],
        ];
    }
}
