<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="UTF-8">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ __('units.print_title') }} — {{ $contextLabel }}</title>
    @php
        $presetMeta = $presets[$activePreset] ?? $presets['medium_24'];
        $outputMeta = $outputs[$activeOutput] ?? $outputs['thermal'];
        $qrSize = (int) ($presetMeta['qr_size'] ?? 68);
        $isSheetMode = $activeOutput !== 'thermal';
        $isThermalCutMode = ! $isSheetMode && $activePreset !== 'detail_a4';

        $pageSizeCss = $isSheetMode
            ? ($outputMeta['page_size'] ?? '210mm 297mm')
            : match ($activePreset) {
                'micro_12x12' => '12mm 12mm',
                'mini_12' => '40mm 12mm',
                'flag_12' => '72mm 12mm',
                default => '55mm 24mm',
            };

        $slotDimsCss = match ($activePreset) {
            'micro_12x12' => 'width: 12mm; height: 12mm;',
            'mini_12' => 'width: 40mm; height: 12mm;',
            'flag_12' => 'width: 72mm; height: 12mm;',
            'medium_24' => 'width: 55mm; height: 24mm;',
            default => 'min-height: 36mm;',
        };

        $labelIds = $labels->pluck('id')->values()->all();
        $unprintedInView = $labels->filter(fn (array $l) => empty($l['printed_at']))->count();
        $ptouchCsvUrl = route('print.qr.export-ptouch', request()->query());
        $ptouchXlsxUrl = route('print.qr.export-ptouch-xlsx', request()->query());
    @endphp
    <style>
        :root {
            --cal-top: 0mm;
            --cal-left: 0mm;
            --cal-scale: 1;
        }
        * { box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Arial, Helvetica, sans-serif;
            margin: 16px;
            color: #0f172a;
            background: #f1f5f9;
        }

        /* ================= TOOLBAR LAYAR (TIDAK IKUT TERCETAK) ================= */
        .toolbar-wrap {
            max-width: 1180px;
            margin: 0 auto 18px;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        .toolbar {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            background: #ffffff;
            border: 1px solid #cbd5e1;
            border-radius: 12px;
            padding: 12px 16px;
            box-shadow: 0 1px 3px rgba(15, 23, 42, 0.06);
        }
        .toolbar-info h1 { font-size: 15px; margin: 0; font-weight: 700; color: #0f172a; }
        .toolbar-info p { font-size: 12px; margin: 3px 0 0; color: #475569; }
        .toolbar-actions { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; }
        .preset-bar {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            background: #ffffff;
            border: 1px solid #cbd5e1;
            border-radius: 12px;
            padding: 10px 14px;
        }
        .preset-pills { display: flex; flex-wrap: wrap; gap: 6px; align-items: center; }
        .bar-label { font-size: 12px; font-weight: 700; color: #334155; margin-right: 4px; }
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            border-radius: 8px;
            padding: 7px 12px;
            font-size: 12.5px;
            font-weight: 600;
            border: 1px solid #cbd5e1;
            background: #ffffff;
            color: #1e293b;
            cursor: pointer;
            text-decoration: none;
            line-height: 1.2;
        }
        .btn:hover { background: #f8fafc; }
        .btn-primary { background: #d97706; border-color: #b45309; color: #ffffff; }
        .btn-primary:hover { background: #b45309; }
        .btn-success { background: #059669; border-color: #047857; color: #ffffff; }
        .btn-success:hover { background: #047857; }
        .btn-dark { background: #0f172a; border-color: #0f172a; color: #ffffff; }
        .btn-dark:hover { background: #1e293b; }
        .btn-blue { background: #1d4ed8; border-color: #1e40af; color: #ffffff; }
        .btn-blue:hover { background: #1e40af; }
        .btn-tab-active { background: #fef3c7; border-color: #f59e0b; color: #92400e; }
        .btn-output-active { background: #dbeafe; border-color: #3b82f6; color: #1e40af; }
        .tape-guide {
            background: #fffbeb;
            border: 1px solid #fcd34d;
            border-radius: 10px;
            padding: 9px 14px;
            font-size: 12px;
            color: #78350f;
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
        }
        .tape-guide--sheet {
            background: #eff6ff;
            border-color: #93c5fd;
            color: #1e3a8a;
        }
        .cal-controls {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 10px;
            font-size: 12px;
            color: #334155;
        }
        .cal-controls label { display: inline-flex; align-items: center; gap: 4px; font-weight: 600; }
        .cal-controls input[type="number"] {
            width: 64px;
            padding: 4px 6px;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            font-size: 12px;
            background: #fff;
        }
        .status-banner {
            display: none;
            padding: 10px 14px;
            border-radius: 10px;
            font-size: 13px;
            font-weight: 600;
            background: #ecfdf5;
            border: 1px solid #a7f3d0;
            color: #065f46;
        }
        .status-banner.is-visible { display: block; }
        .sticker-status-pill {
            display: inline-block;
            font-size: 10px;
            font-weight: 700;
            padding: 2px 7px;
            border-radius: 999px;
            margin-bottom: 5px;
        }
        .pill-new { background: #fef3c7; color: #92400e; border: 1px solid #fcd34d; }
        .pill-done { background: #dcfce7; color: #166534; border: 1px solid #86efac; }
        .empty-box {
            max-width: 720px;
            margin: 24px auto;
            background: #ffffff;
            border: 1px dashed #94a3b8;
            border-radius: 12px;
            padding: 32px 20px;
            text-align: center;
            color: #475569;
        }

        /* ================= PRATINJAU THERMAL & KERTAS LEMBAR (100% MONOKROM #000000) ================= */
        .preview-stage {
            max-width: 1180px;
            margin: 0 auto;
        }
        .thermal-list {
            display: flex;
            flex-wrap: wrap;
            gap: 14px;
            align-items: flex-start;
        }
        .thermal-item-wrap {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            background: #ffffff;
            padding: 8px;
            border-radius: 10px;
            border: 1px solid #cbd5e1;
            break-inside: avoid;
            page-break-inside: avoid;
        }
        .ptouch-sheet-frame {
            background: #ffffff;
            border: 1px solid #cbd5e1;
            border-radius: 12px;
            padding: 8mm;
            box-shadow: 0 2px 6px rgba(15, 23, 42, 0.06);
        }
        .ptouch-sheet-grid {
            display: flex;
            flex-wrap: wrap;
            gap: 2.5mm;
            align-items: flex-start;
            align-content: flex-start;
        }
        .ptouch-sheet-item {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            break-inside: avoid;
            page-break-inside: avoid;
        }
        .ptouch-slot-empty {
            {{ $slotDimsCss }}
            border: 0.25mm dashed #cbd5e1;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 9px;
            color: #94a3b8;
        }
        .qr-svg svg {
            display: block;
            width: 100%;
            height: 100%;
            shape-rendering: crispEdges;
        }

        /* 1. XXS / MICRO 12x12mm (Pita 12mm Putih) */
        .label-micro_12x12 {
            width: 12mm;
            height: 12mm;
            background: #ffffff;
            color: #000000;
            border: 0.2mm solid #000000;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 0.4mm;
            overflow: hidden;
        }
        .label-micro_12x12 .qr-svg { width: 8.8mm; height: 8.8mm; }
        .label-micro_12x12 .micro-code {
            font-family: Consolas, 'Courier New', monospace;
            font-size: 4.2pt;
            font-weight: 700;
            line-height: 1;
            margin-top: 0.4mm;
            white-space: nowrap;
            color: #000000;
        }

        /* 2. XS / MINI 12mm (40x12mm — Pita 12mm Putih) */
        .label-mini_12 {
            width: 40mm;
            height: 12mm;
            background: #ffffff;
            color: #000000;
            border: 0.25mm solid #000000;
            display: flex;
            align-items: center;
            gap: 1.4mm;
            padding: 0.8mm 1.2mm;
            overflow: hidden;
        }
        .label-mini_12 .qr-svg { width: 9.8mm; height: 9.8mm; flex-shrink: 0; }
        .label-mini_12 .txt { min-width: 0; flex: 1; line-height: 1.1; }
        .label-mini_12 .hdr { font-size: 4.6pt; font-weight: 800; letter-spacing: 0.2pt; border-bottom: 0.2mm solid #000; padding-bottom: 0.3mm; }
        .label-mini_12 .code { font-family: Consolas, 'Courier New', monospace; font-size: 6.4pt; font-weight: 800; margin-top: 0.5mm; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .label-mini_12 .name { font-size: 5pt; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; margin-top: 0.2mm; }

        /* 3. CF / CABLE FLAG 12mm (72x12mm — Pita 12mm Kuning) */
        .label-flag_12 {
            width: 72mm;
            height: 12mm;
            background: #ffffff;
            color: #000000;
            border: 0.25mm solid #000000;
            display: flex;
            align-items: stretch;
            overflow: hidden;
        }
        .label-flag_12 .flag-wing {
            width: 27mm;
            display: flex;
            align-items: center;
            gap: 1mm;
            padding: 0.7mm 1mm;
            overflow: hidden;
        }
        .label-flag_12 .flag-fold {
            width: 18mm;
            border-left: 0.25mm dashed #000000;
            border-right: 0.25mm dashed #000000;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 4.5pt;
            font-weight: 700;
            letter-spacing: 0.3pt;
            color: #000000;
        }
        .label-flag_12 .qr-svg { width: 9.4mm; height: 9.4mm; flex-shrink: 0; }
        .label-flag_12 .txt { min-width: 0; flex: 1; line-height: 1.1; }
        .label-flag_12 .hdr { font-size: 4.2pt; font-weight: 800; }
        .label-flag_12 .code { font-family: Consolas, 'Courier New', monospace; font-size: 5.4pt; font-weight: 800; margin-top: 0.3mm; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .label-flag_12 .name { font-size: 4.4pt; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

        /* 4. M / SEDANG 24mm (55x24mm — Pita 24mm Putih / Kuning) */
        .label-medium_24 {
            width: 55mm;
            height: 24mm;
            background: #ffffff;
            color: #000000;
            border: 0.35mm solid #000000;
            display: flex;
            align-items: center;
            gap: 2.2mm;
            padding: 1.5mm 2mm;
            overflow: hidden;
        }
        .label-medium_24 .qr-svg { width: 19.5mm; height: 19.5mm; flex-shrink: 0; }
        .label-medium_24 .txt { min-width: 0; flex: 1; display: flex; flex-direction: column; justify-content: center; }
        .label-medium_24 .hdr {
            font-size: 6.5pt;
            font-weight: 900;
            letter-spacing: 0.4pt;
            border-bottom: 0.35mm solid #000000;
            padding-bottom: 0.6mm;
            margin-bottom: 1mm;
        }
        .label-medium_24 .code {
            font-family: Consolas, 'Courier New', monospace;
            font-size: 8.5pt;
            font-weight: 900;
            line-height: 1.1;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .label-medium_24 .name {
            font-size: 6.8pt;
            font-weight: 700;
            line-height: 1.15;
            margin-top: 0.8mm;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        .label-medium_24 .meta {
            font-size: 5.5pt;
            font-weight: 600;
            margin-top: 0.6mm;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        /* 5. L / XL DETAIL A4 / F4 / A3 */
        .sheet-grid {
            display: grid;
            grid-template-columns: repeat({{ $activeOutput === 'a3' ? 4 : 3 }}, 1fr);
            gap: 4mm;
            background: #ffffff;
            padding: 6mm;
            border: 1px solid #cbd5e1;
            border-radius: 12px;
        }
        .sheet-slot-empty {
            min-height: 36mm;
            border: 0.25mm dashed #cbd5e1;
            border-radius: 3mm;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 11px;
            color: #94a3b8;
        }
        .label-detail_a4 {
            background: #ffffff;
            color: #000000;
            border: 0.4mm solid #000000;
            border-radius: 2mm;
            padding: 2.2mm;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            min-height: 36mm;
            break-inside: avoid;
            page-break-inside: avoid;
        }
        .label-detail_a4 .hdr-bar {
            background: #000000;
            color: #ffffff;
            font-size: 6.5pt;
            font-weight: 800;
            letter-spacing: 0.4pt;
            padding: 0.9mm 1.8mm;
            display: flex;
            justify-content: space-between;
            align-items: center;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
        .label-detail_a4 .body-row {
            display: flex;
            align-items: center;
            gap: 2.5mm;
            margin-top: 1.8mm;
            flex: 1;
        }
        .label-detail_a4 .qr-svg { width: 22mm; height: 22mm; flex-shrink: 0; }
        .label-detail_a4 .txt { min-width: 0; flex: 1; }
        .label-detail_a4 .code {
            font-family: Consolas, 'Courier New', monospace;
            font-size: 9pt;
            font-weight: 900;
            line-height: 1.1;
        }
        .label-detail_a4 .name {
            font-size: 7.5pt;
            font-weight: 800;
            line-height: 1.2;
            margin-top: 0.8mm;
        }
        .label-detail_a4 .spec {
            font-size: 6pt;
            line-height: 1.2;
            margin-top: 0.6mm;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        .label-detail_a4 .foot {
            border-top: 0.25mm solid #000000;
            margin-top: 1.5mm;
            padding-top: 0.8mm;
            font-size: 5.8pt;
            font-weight: 700;
            display: flex;
            justify-content: space-between;
            gap: 4px;
        }

        /* ================= PRINT MEDIA RULES ================= */
        @page {
            size: {{ $pageSizeCss }};
            margin: {{ $isThermalCutMode ? '0mm' : '8mm' }};
        }
        @media print {
            body {
                margin: 0;
                padding: 0;
                background: #ffffff !important;
                color: #000000 !important;
            }
            .no-print { display: none !important; }
            .preview-stage {
                max-width: none;
                margin: 0;
                padding-top: var(--cal-top);
                padding-left: var(--cal-left);
                transform: scale(var(--cal-scale));
                transform-origin: top left;
            }
            @if ($isThermalCutMode)
                /* Mode Pita Thermal PT-P750W: 1 stiker = 1 halaman potong otomatis (auto-cut) */
                .thermal-list {
                    display: block;
                    gap: 0;
                }
                .thermal-item-wrap {
                    border: none;
                    border-radius: 0;
                    padding: 0;
                    margin: 0;
                    background: #ffffff;
                    page-break-after: always;
                    break-after: page;
                }
                .thermal-item-wrap:last-child {
                    page-break-after: auto;
                    break-after: auto;
                }
            @else
                /* Mode Kertas Biasa / Stiker Lembar (A4 / F4 / A3): susun rapat hemat kertas */
                .ptouch-sheet-frame,
                .sheet-grid {
                    border: none;
                    border-radius: 0;
                    padding: 0;
                    box-shadow: none;
                }
                .ptouch-slot-empty,
                .sheet-slot-empty {
                    border: none;
                    color: transparent;
                }
            @endif
        }
    </style>
</head>
<body>
    <div class="toolbar-wrap no-print">
        <div class="toolbar">
            <div class="toolbar-info">
                <h1>{{ $contextLabel }} — {{ $labels->count() }} {{ $printType === 'bulk' ? 'wadah' : 'unit' }}</h1>
                <p>
                    {{ $unprintedInView }} belum dicetak di halaman ini · Total belum dicetak di sistem: <strong>{{ $unprintedTotal }}</strong>
                </p>
            </div>

            <div class="toolbar-actions">
                @if ($printType === 'bulk')
                    <a href="{{ route('print.qr', ['type' => 'bulk', 'ids' => 'unprinted', 'preset' => $requestedPreset, 'output' => $activeOutput]) }}"
                       class="btn {{ $activeMode === 'unprinted' ? 'btn-tab-active' : '' }}">
                        Belum Dicetak ({{ $unprintedTotal }})
                    </a>
                    <a href="{{ route('print.qr', ['type' => 'bulk', 'ids' => 'all', 'preset' => $requestedPreset, 'output' => $activeOutput]) }}"
                       class="btn {{ $activeMode === 'all' ? 'btn-tab-active' : '' }}">
                        Semua Wadah
                    </a>
                @else
                    <a href="{{ route('print.qr', ['ids' => 'unprinted', 'preset' => $requestedPreset, 'output' => $activeOutput]) }}"
                       class="btn {{ $activeMode === 'unprinted' ? 'btn-tab-active' : '' }}">
                        Belum Dicetak ({{ $unprintedTotal }})
                    </a>
                    <a href="{{ route('print.qr', ['ids' => 'all', 'preset' => $requestedPreset, 'output' => $activeOutput]) }}"
                       class="btn {{ $activeMode === 'all' ? 'btn-tab-active' : '' }}">
                        Semua Unit
                    </a>
                    @if ($procurement)
                        <a href="{{ route('print.qr', ['procurement_id' => $procurement->id, 'only_unprinted' => 1, 'preset' => $requestedPreset, 'output' => $activeOutput]) }}"
                           class="btn">
                            Hanya Belum Dicetak di Batch Ini
                        </a>
                    @endif
                @endif

                @if ($labels->isNotEmpty())
                    <a href="{{ $ptouchXlsxUrl }}" class="btn btn-blue" title="Unduh Excel (.xlsx) siap Database Connect di Brother P-touch Editor (rekomendasi Windows)">
                        ⬇ Excel P-touch (.xlsx)
                    </a>
                    <a href="{{ $ptouchCsvUrl }}" class="btn btn-dark" title="Unduh CSV UTF-8 siap impor Brother P-touch Editor">
                        ⬇ CSV P-touch (.csv)
                    </a>
                    <button type="button" class="btn btn-primary" onclick="printAndMark()">
                        🖨️ {{ __('units.print_and_mark') }}
                    </button>
                    <button type="button" class="btn" onclick="window.print()">
                        {{ __('units.print_only') }}
                    </button>
                    <button type="button" class="btn btn-success" onclick="markPrintedOnly()">
                        ✓ {{ __('units.mark_printed') }}
                    </button>
                @endif
            </div>
        </div>

        <div class="preset-bar">
            <div class="preset-pills">
                <span class="bar-label">Format Stiker:</span>
                @foreach ($presets as $key => $meta)
                    @php
                        $nextOutput = ($key === 'detail_a4' && $activeOutput === 'thermal') ? 'a4' : $activeOutput;
                    @endphp
                    <a href="{{ route('print.qr', array_merge($queryBase, ['preset' => $key, 'output' => $nextOutput, 'offset' => $offset])) }}"
                       class="btn {{ $activePreset === $key ? 'btn-tab-active' : '' }}">
                        {{ $meta['label'] }}
                    </a>
                @endforeach
            </div>
        </div>

        <div class="preset-bar">
            <div class="preset-pills">
                <span class="bar-label">Media Cetak:</span>
                @foreach ($outputs as $outKey => $outMeta)
                    @if ($activePreset === 'detail_a4' && $outKey === 'thermal')
                        @continue
                    @endif
                    <a href="{{ route('print.qr', array_merge($queryBase, ['preset' => $activePreset, 'output' => $outKey, 'offset' => $outKey === 'thermal' ? 0 : $offset])) }}"
                       class="btn {{ $activeOutput === $outKey ? 'btn-output-active' : '' }}">
                        {{ $outMeta['label'] }}
                    </a>
                @endforeach
            </div>

            <div class="cal-controls">
                @if ($isSheetMode)
                    <form method="GET" action="{{ route('print.qr') }}" style="display:inline-flex;align-items:center;gap:8px;flex-wrap:wrap;">
                        @foreach ($queryBase as $k => $v)
                            @if (! is_array($v))
                                <input type="hidden" name="{{ $k }}" value="{{ $v }}">
                            @endif
                        @endforeach
                        <input type="hidden" name="preset" value="{{ $activePreset }}">
                        <input type="hidden" name="output" value="{{ $activeOutput }}">
                        <label title="Lewati N slot kosong di awal lembar stiker bekas">
                            Offset Slot:
                            <input type="number" name="offset" min="0" max="60" value="{{ $offset }}" onchange="this.form.submit()">
                        </label>
                    </form>
                @endif

                <label title="Geser atas/bawah saat print (tersimpan di browser)">
                    Atas (mm):
                    <input id="cal-top" type="number" step="0.5" min="-3" max="10" value="0" oninput="applyCalibration()">
                </label>
                <label title="Geser kiri/kanan saat print (tersimpan di browser)">
                    Kiri (mm):
                    <input id="cal-left" type="number" step="0.5" min="-3" max="10" value="0" oninput="applyCalibration()">
                </label>
                <label title="Skala cetak (85%–115%)">
                    Skala (%):
                    <input id="cal-scale" type="number" step="1" min="85" max="115" value="100" oninput="applyCalibration()">
                </label>
                <button type="button" class="btn" onclick="resetCalibration()" style="padding: 5px 8px; font-size: 11px;">Reset</button>
            </div>
        </div>

        <div class="tape-guide {{ $isSheetMode ? 'tape-guide--sheet' : '' }}">
            @if ($isSheetMode)
                <div>
                    <strong>Mode Kertas Lembar ({{ $outputMeta['label'] }} · {{ $outputMeta['page_size'] }}):</strong>
                    Format <strong>{{ $presetMeta['label'] }} ({{ $presetMeta['dims'] }})</strong> disusun rapat dalam 1 lembar kertas biasa/stiker — hemat kertas, tinggal gunting! Di dialog Print pilih kertas <strong>{{ $outputMeta['label'] }}</strong> & Skala <strong>100%</strong>.
                </div>
            @else
                <div>
                    <strong>Mode Pita Thermal ({{ $presetMeta['tape'] }}):</strong> {{ $presetMeta['paper_hint'] }}
                </div>
            @endif
            <div>
                <strong>Monokrom #000000</strong> · <strong>INVENTARIS TJKT</strong>
            </div>
        </div>

        <div id="print-status-banner" class="status-banner"></div>
    </div>

    @if ($labels->isEmpty())
        <div class="empty-box no-print">
            <strong>{{ __('units.print_empty') }}</strong>
            <p style="margin-top: 6px; font-size: 13px;">
                Semua stiker QR pada filter ini sudah berstatus <em>Sudah Dicetak</em>. Gunakan tombol <strong>Semua Unit</strong> di atas atau pilih <em>Tandai Belum Dicetak</em> di menu Data Unit/QR untuk cetak ulang.
            </p>
        </div>
    @else
        <div class="preview-stage">
            @if ($activePreset === 'detail_a4')
                <div class="sheet-grid">
                    @for ($i = 0; $i < $offset; $i++)
                        <div class="sheet-slot-empty">Slot Kosong #{{ $i + 1 }}</div>
                    @endfor

                    @foreach ($labels as $label)
                        <div>
                            <div class="no-print">
                                @if (empty($label['printed_at']))
                                    <span class="sticker-status-pill pill-new js-badge">BARU · BELUM DICETAK</span>
                                @else
                                    <span class="sticker-status-pill pill-done js-badge">
                                        Tercetak {{ $label['print_count'] }}× ({{ $label['printed_at']->format('d M Y') }})
                                    </span>
                                @endif
                            </div>
                            <div class="label-detail_a4" data-label-id="{{ $label['id'] }}">
                                <div class="hdr-bar">
                                    <span>INVENTARIS TJKT</span>
                                    <span>SMK WALISONGO PECANGAAN</span>
                                </div>
                                <div class="body-row">
                                    <div class="qr-svg">
                                        {!! QrCode::size($qrSize)->margin(0)->errorCorrection('M')->generate($label['code']) !!}
                                    </div>
                                    <div class="txt">
                                        <div class="code">{{ $label['code'] }}</div>
                                        <div class="name">{{ $label['name'] }}</div>
                                        @if (! empty($label['specification']))
                                            <div class="spec">{{ $label['specification'] }}</div>
                                        @endif
                                    </div>
                                </div>
                                <div class="foot">
                                    <span>{{ $label['meta'] !== '' ? $label['meta'] : 'TJKT' }}</span>
                                    <span>{{ ! empty($label['factory_sn']) ? 'S/N: '.$label['factory_sn'] : ($label['asset_code'] ?? '') }}</span>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="{{ $isSheetMode ? 'ptouch-sheet-frame' : '' }}">
                    <div class="{{ $isSheetMode ? 'ptouch-sheet-grid' : 'thermal-list' }}">
                        @if ($isSheetMode)
                            @for ($i = 0; $i < $offset; $i++)
                                <div class="ptouch-slot-empty">#{{ $i + 1 }}</div>
                            @endfor
                        @endif

                        @foreach ($labels as $label)
                            @php
                                $qrSvg = (string) QrCode::size($qrSize)->margin(0)->errorCorrection('M')->generate($label['code']);
                                $shortCode = preg_replace('/^TJKT-/', '', (string) $label['code']);
                            @endphp
                            <div class="{{ $isSheetMode ? 'ptouch-sheet-item' : 'thermal-item-wrap' }}" data-label-id="{{ $label['id'] }}">
                                <div class="no-print">
                                    @if (empty($label['printed_at']))
                                        <span class="sticker-status-pill pill-new js-badge">BARU · BELUM DICETAK</span>
                                    @else
                                        <span class="sticker-status-pill pill-done js-badge">
                                            Tercetak {{ $label['print_count'] }}× ({{ $label['printed_at']->format('d M Y') }})
                                        </span>
                                    @endif
                                </div>

                                @if ($activePreset === 'micro_12x12')
                                    <div class="label-micro_12x12">
                                        <div class="qr-svg">{!! $qrSvg !!}</div>
                                        <div class="micro-code">{{ $shortCode }}</div>
                                    </div>
                                @elseif ($activePreset === 'mini_12')
                                    <div class="label-mini_12">
                                        <div class="qr-svg">{!! $qrSvg !!}</div>
                                        <div class="txt">
                                            <div class="hdr">INVENTARIS TJKT</div>
                                            <div class="code">{{ $label['code'] }}</div>
                                            <div class="name">{{ $label['name'] }}</div>
                                        </div>
                                    </div>
                                @elseif ($activePreset === 'flag_12')
                                    <div class="label-flag_12">
                                        <div class="flag-wing">
                                            <div class="qr-svg">{!! $qrSvg !!}</div>
                                            <div class="txt">
                                                <div class="hdr">INVENTARIS TJKT</div>
                                                <div class="code">{{ $label['code'] }}</div>
                                                <div class="name">{{ $label['name'] }}</div>
                                            </div>
                                        </div>
                                        <div class="flag-fold">LIPAT KABEL</div>
                                        <div class="flag-wing">
                                            <div class="txt" style="text-align: right;">
                                                <div class="hdr">INVENTARIS TJKT</div>
                                                <div class="code">{{ $label['code'] }}</div>
                                                <div class="name">{{ $label['name'] }}</div>
                                            </div>
                                            <div class="qr-svg">{!! $qrSvg !!}</div>
                                        </div>
                                    </div>
                                @else
                                    <div class="label-medium_24">
                                        <div class="qr-svg">{!! $qrSvg !!}</div>
                                        <div class="txt">
                                            <div class="hdr">INVENTARIS TJKT{{ ! empty($label['is_critical']) ? ' ★' : '' }}</div>
                                            <div class="code">{{ $label['code'] }}</div>
                                            <div class="name">{{ $label['name'] }}</div>
                                            @if ($label['meta'] !== '')
                                                <div class="meta">{{ $label['meta'] }}</div>
                                            @endif
                                        </div>
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    @endif

    <script>
        const LATEKAJE_PRINT_IDS = @json($labelIds);
        const LATEKAJE_PRINT_TYPE = @json($printType);
        const LATEKAJE_PRESET = @json($activePreset);
        const LATEKAJE_OUTPUT = @json($activeOutput);
        const LATEKAJE_MARK_URL = @json(route('print.qr.mark-printed'));
        const CAL_STORAGE_KEY = 'latekaje_print_cal_' + LATEKAJE_PRESET + '_' + LATEKAJE_OUTPUT;

        function loadCalibration() {
            try {
                const raw = localStorage.getItem(CAL_STORAGE_KEY);
                if (!raw) return;
                const saved = JSON.parse(raw);
                if (saved.top !== undefined) document.getElementById('cal-top').value = saved.top;
                if (saved.left !== undefined) document.getElementById('cal-left').value = saved.left;
                if (saved.scale !== undefined) document.getElementById('cal-scale').value = saved.scale;
                applyCalibration(false);
            } catch (e) {}
        }

        function applyCalibration(persist = true) {
            const top = parseFloat(document.getElementById('cal-top')?.value || '0') || 0;
            const left = parseFloat(document.getElementById('cal-left')?.value || '0') || 0;
            const scale = Math.max(85, Math.min(115, parseFloat(document.getElementById('cal-scale')?.value || '100') || 100));

            document.documentElement.style.setProperty('--cal-top', top + 'mm');
            document.documentElement.style.setProperty('--cal-left', left + 'mm');
            document.documentElement.style.setProperty('--cal-scale', (scale / 100).toString());

            if (persist) {
                try {
                    localStorage.setItem(CAL_STORAGE_KEY, JSON.stringify({ top, left, scale }));
                } catch (e) {}
            }
        }

        function resetCalibration() {
            document.getElementById('cal-top').value = '0';
            document.getElementById('cal-left').value = '0';
            document.getElementById('cal-scale').value = '100';
            applyCalibration(true);
        }

        async function sendMarkPrinted() {
            if (!LATEKAJE_PRINT_IDS.length) return false;
            const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
            try {
                const res = await fetch(LATEKAJE_MARK_URL, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': token,
                    },
                    body: JSON.stringify({
                        type: LATEKAJE_PRINT_TYPE,
                        ids: LATEKAJE_PRINT_IDS,
                    }),
                });
                if (!res.ok) return false;
                const data = await res.json();
                const banner = document.getElementById('print-status-banner');
                if (banner) {
                    banner.textContent = '✓ ' + data.updated + ' label ditandai Sudah Dicetak (' + data.printed_at + ').';
                    banner.classList.add('is-visible');
                }
                document.querySelectorAll('.js-badge').forEach((el) => {
                    el.className = 'sticker-status-pill pill-done js-badge';
                    el.textContent = 'Sudah Dicetak (' + data.printed_at + ')';
                });
                return true;
            } catch (e) {
                return false;
            }
        }

        async function printAndMark() {
            await sendMarkPrinted();
            window.print();
        }

        async function markPrintedOnly() {
            await sendMarkPrinted();
        }

        loadCalibration();
    </script>
</body>
</html>
