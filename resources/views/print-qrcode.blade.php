<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="UTF-8">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ __('units.print_title') }} — {{ $contextLabel }}</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: system-ui, -apple-system, sans-serif; margin: 16px; color: #111827; background: #f8fafc; }
        .toolbar {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 12px 16px;
            margin-bottom: 16px;
            box-shadow: 0 1px 3px rgba(15, 23, 42, 0.06);
        }
        .toolbar-info h1 { font-size: 15px; margin: 0; font-weight: 700; color: #0f172a; }
        .toolbar-info p { font-size: 12px; margin: 3px 0 0; color: #64748b; }
        .toolbar-actions { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; }
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            border-radius: 8px;
            padding: 8px 13px;
            font-size: 13px;
            font-weight: 600;
            border: 1px solid #cbd5e1;
            background: #ffffff;
            color: #1e293b;
            cursor: pointer;
            text-decoration: none;
        }
        .btn:hover { background: #f1f5f9; }
        .btn-primary { background: #d97706; border-color: #b45309; color: #ffffff; }
        .btn-primary:hover { background: #b45309; }
        .btn-success { background: #059669; border-color: #047857; color: #ffffff; }
        .btn-success:hover { background: #047857; }
        .btn-tab-active { background: #fef3c7; border-color: #f59e0b; color: #92400e; }
        .status-banner {
            display: none;
            margin-bottom: 12px;
            padding: 10px 14px;
            border-radius: 10px;
            font-size: 13px;
            font-weight: 600;
            background: #ecfdf5;
            border: 1px solid #a7f3d0;
            color: #065f46;
        }
        .status-banner.is-visible { display: block; }
        .grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; }
        .sticker {
            position: relative;
            background: #ffffff;
            border: 1px dashed #999;
            border-radius: 8px;
            padding: 12px 10px 10px;
            text-align: center;
            page-break-inside: avoid;
        }
        .sticker-badge {
            display: inline-block;
            font-size: 10px;
            font-weight: 700;
            padding: 2px 7px;
            border-radius: 999px;
            margin-bottom: 6px;
        }
        .sticker-badge--new { background: #fef3c7; color: #92400e; border: 1px solid #fcd34d; }
        .sticker-badge--done { background: #dcfce7; color: #166534; border: 1px solid #86efac; }
        .sticker .name { font-weight: bold; font-size: 13px; margin-top: 6px; color: #000; }
        .sticker .code { font-size: 12px; color: #222; font-family: ui-monospace, monospace; margin-top: 2px; }
        .sticker .meta { font-size: 10px; color: #555; margin-top: 2px; }
        .empty-box {
            background: #ffffff;
            border: 1px dashed #cbd5e1;
            border-radius: 12px;
            padding: 32px 20px;
            text-align: center;
            color: #475569;
        }
        @media print {
            body { margin: 0; background: #fff; }
            .no-print { display: none !important; }
            .sticker { border: 1px dashed #999; }
        }
    </style>
</head>
<body>
    @php
        $labelIds = $labels->pluck('id')->values()->all();
        $unprintedInView = $labels->filter(fn (array $l) => empty($l['printed_at']))->count();
    @endphp

    <div class="toolbar no-print">
        <div class="toolbar-info">
            <h1>{{ $contextLabel }} — {{ $labels->count() }} {{ $printType === 'bulk' ? 'wadah' : 'unit' }}</h1>
            <p>
                {{ $unprintedInView }} belum dicetak di halaman ini · Total belum dicetak di sistem: <strong>{{ $unprintedTotal }}</strong>
            </p>
        </div>

        <div class="toolbar-actions">
            @if ($printType === 'bulk')
                <a href="{{ route('print.qr', ['type' => 'bulk', 'ids' => 'unprinted']) }}"
                   class="btn {{ $activeMode === 'unprinted' ? 'btn-tab-active' : '' }}">
                    Belum Dicetak ({{ $unprintedTotal }})
                </a>
                <a href="{{ route('print.qr', ['type' => 'bulk', 'ids' => 'all']) }}"
                   class="btn {{ $activeMode === 'all' ? 'btn-tab-active' : '' }}">
                    Semua Wadah
                </a>
            @else
                <a href="{{ route('print.qr', ['ids' => 'unprinted']) }}"
                   class="btn {{ $activeMode === 'unprinted' ? 'btn-tab-active' : '' }}">
                    Belum Dicetak ({{ $unprintedTotal }})
                </a>
                <a href="{{ route('print.qr', ['ids' => 'all']) }}"
                   class="btn {{ $activeMode === 'all' ? 'btn-tab-active' : '' }}">
                    Semua Unit
                </a>
                @if ($procurement)
                    <a href="{{ route('print.qr', ['procurement_id' => $procurement->id, 'only_unprinted' => 1]) }}"
                       class="btn">
                        Hanya yg Belum Dicetak di Batch Ini
                    </a>
                @endif
            @endif

            @if ($labels->isNotEmpty())
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

    <div id="print-status-banner" class="status-banner no-print"></div>

    @if ($labels->isEmpty())
        <div class="empty-box no-print">
            <strong>{{ __('units.print_empty') }}</strong>
            <p style="margin-top: 6px; font-size: 13px;">
                Semua stiker QR pada filter ini sudah berstatus <em>Sudah Dicetak</em>. Gunakan tombol <strong>Semua Unit</strong> di atas atau pilih <em>Tandai Belum Dicetak</em> di menu Data Unit/QR untuk cetak ulang.
            </p>
        </div>
    @else
        <div class="grid">
            @foreach ($labels as $label)
                <div class="sticker" data-label-id="{{ $label['id'] }}">
                    <div class="no-print">
                        @if (empty($label['printed_at']))
                            <span class="sticker-badge sticker-badge--new js-badge">BARU · BELUM DICETAK</span>
                        @else
                            <span class="sticker-badge sticker-badge--done js-badge">
                                Tercetak {{ $label['print_count'] }}× ({{ $label['printed_at']->format('d M Y') }})
                            </span>
                        @endif
                    </div>
                    {!! QrCode::size(90)->generate($label['code']) !!}
                    <div class="name">{{ $label['name'] }}</div>
                    <div class="code">{{ $label['code'] }}</div>
                    @if ($label['meta'] !== '')
                        <div class="meta">{{ $label['meta'] }}</div>
                    @endif
                </div>
            @endforeach
        </div>
    @endif

    <script>
        const LATEKAJE_PRINT_IDS = @json($labelIds);
        const LATEKAJE_PRINT_TYPE = @json($printType);
        const LATEKAJE_MARK_URL = @json(route('print.qr.mark-printed'));

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
                    el.className = 'sticker-badge sticker-badge--done js-badge';
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
    </script>
</body>
</html>
