<?php

namespace App\Http\Controllers;

use App\Models\AssetItem;
use App\Models\BulkStock;
use App\Models\Procurement;
use App\Services\ActivityLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PrintQrController extends Controller
{
    public const PRESETS = [
        'micro_12x12' => [
            'label' => 'XXS · Micro 12×12mm',
            'tape' => 'Pita TZe 12mm Putih',
            'dims' => '12×12mm',
            'paper_hint' => 'Ukuran kustom 12×12mm · Margin: None · Auto-Cut ON (Sensor Mini / DHT / Soil)',
            'qr_size' => 34,
        ],
        'mini_12' => [
            'label' => 'XS · Mini 12mm',
            'tape' => 'Pita TZe 12mm Putih',
            'dims' => '40×12mm',
            'paper_hint' => 'Ukuran kustom 40×12mm (Landscape) · Margin: None · Auto-Cut ON (ESP / RAM / Tools)',
            'qr_size' => 36,
        ],
        'flag_12' => [
            'label' => 'CF · Cable Flag 12mm',
            'tape' => 'Pita TZe 12mm Kuning',
            'dims' => '72×12mm',
            'paper_hint' => 'Ukuran kustom 72×12mm (Landscape) · Margin: None · Auto-Cut ON (Lipat melingkar di kabel/adaptor)',
            'qr_size' => 34,
        ],
        'medium_24' => [
            'label' => 'M · Sedang 24mm',
            'tape' => 'Pita TZe 24mm Putih / Kuning',
            'dims' => '55×24mm',
            'paper_hint' => 'Ukuran kustom 55×24mm (Landscape) · Margin: None · Auto-Cut ON (Router / Switch / Splicer / Box)',
            'qr_size' => 68,
        ],
        'detail_a4' => [
            'label' => 'L/XL · Detail Lembar',
            'tape' => 'Kertas Stiker A4 / F4 / A3',
            'dims' => 'Kartu Detail',
            'paper_hint' => 'Kertas A4 / F4 (3 kolom) atau A3 (4 kolom) · Margin: Minimum · Atur Offset jika pakai lembar sisa',
            'qr_size' => 86,
        ],
    ];

    public const OUTPUTS = [
        'thermal' => [
            'label' => 'Pita Thermal PT-P750W',
            'page_size' => null,
        ],
        'a4' => [
            'label' => 'Kertas A4',
            'page_size' => '210mm 297mm',
        ],
        'f4' => [
            'label' => 'Kertas F4 / Folio',
            'page_size' => '215mm 330mm',
        ],
        'a3' => [
            'label' => 'Kertas A3',
            'page_size' => '297mm 420mm',
        ],
    ];

    public function __invoke(Request $request): View
    {
        abort_unless(Gate::allows('print', AssetItem::class), 403);

        $resolved = $this->resolveLabels($request);

        /** @var Collection<int, array<string, mixed>> $labels */
        $labels = $resolved['labels'];
        $requestedPreset = (string) $request->query('preset', 'auto');
        $activePreset = $this->resolvePreset($requestedPreset, $labels);

        // Dukungan parameter lama (?paper=a3/a4/f4) & parameter baru (?output=thermal|a4|f4|a3)
        $rawOutput = (string) $request->query('output', '');
        if ($rawOutput === '' && $request->filled('paper')) {
            $rawOutput = (string) $request->query('paper');
        }
        $activeOutput = array_key_exists($rawOutput, self::OUTPUTS)
            ? $rawOutput
            : ($activePreset === 'detail_a4' ? 'a4' : 'thermal');

        // Preset Detail L/XL selalu dicetak di kertas lembar (A4/F4/A3)
        if ($activePreset === 'detail_a4' && $activeOutput === 'thermal') {
            $activeOutput = 'a4';
        }

        $offset = max(0, min(60, (int) $request->query('offset', 0)));

        ActivityLogger::log(
            'qr.printed',
            "Membuka pratinjau cetak QR ({$labels->count()} ".($resolved['printType'] === 'bulk' ? 'wadah' : 'unit')." — {$resolved['contextLabel']} · {$activePreset} · {$activeOutput})",
            $resolved['procurement'],
            [
                'type' => $resolved['printType'],
                'preset' => $activePreset,
                'output' => $activeOutput,
                'ids' => $resolved['idsParam'] === 'all' ? 'all' : $labels->pluck('id')->all(),
                'procurement_id' => $resolved['procurement']?->id,
                'count' => $labels->count(),
            ],
        );

        return view('print-qrcode', [
            'items' => $resolved['models'],
            'labels' => $labels,
            'printType' => $resolved['printType'],
            'contextLabel' => $resolved['contextLabel'],
            'unprintedTotal' => $resolved['unprintedTotal'],
            'activeMode' => $resolved['activeMode'],
            'procurement' => $resolved['procurement'],
            'presets' => self::PRESETS,
            'outputs' => self::OUTPUTS,
            'activePreset' => $activePreset,
            'requestedPreset' => $requestedPreset,
            'activeOutput' => $activeOutput,
            'offset' => $offset,
            'queryBase' => $request->except(['preset', 'output', 'paper', 'offset']),
        ]);
    }

    public function exportPtouchCsv(Request $request): StreamedResponse
    {
        abort_unless(Gate::allows('print', AssetItem::class), 403);

        $resolved = $this->resolveLabels($request);
        /** @var Collection<int, array<string, mixed>> $labels */
        $labels = $resolved['labels'];

        ActivityLogger::log(
            'qr.ptouch_exported',
            "Mengekspor CSV Brother P-touch Editor ({$labels->count()} label — {$resolved['contextLabel']})",
            $resolved['procurement'],
            ['type' => $resolved['printType'], 'format' => 'csv', 'count' => $labels->count()],
        );

        $filename = 'ptouch-tjkt-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($labels): void {
            $out = fopen('php://output', 'w');
            // UTF-8 BOM untuk kompatibilitas Windows & Brother P-touch Editor
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, self::ptouchHeaders());

            foreach ($labels as $label) {
                fputcsv($out, self::ptouchRow($label));
            }

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function exportPtouchXlsx(Request $request): StreamedResponse
    {
        abort_unless(Gate::allows('print', AssetItem::class), 403);

        $resolved = $this->resolveLabels($request);
        /** @var Collection<int, array<string, mixed>> $labels */
        $labels = $resolved['labels'];

        ActivityLogger::log(
            'qr.ptouch_exported',
            "Mengekspor Excel (.xlsx) Brother P-touch Editor ({$labels->count()} label — {$resolved['contextLabel']})",
            $resolved['procurement'],
            ['type' => $resolved['printType'], 'format' => 'xlsx', 'count' => $labels->count()],
        );

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('DATA_PTOUCH');

        $headers = self::ptouchHeaders();
        foreach ($headers as $colIndex => $header) {
            $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex + 1);
            $sheet->setCellValueExplicit("{$colLetter}1", $header, DataType::TYPE_STRING);
            $sheet->getStyle("{$colLetter}1")->getFont()->setBold(true);
            $sheet->getColumnDimension($colLetter)->setAutoSize(true);
        }

        $rowNum = 2;
        foreach ($labels as $label) {
            foreach (self::ptouchRow($label) as $colIndex => $val) {
                $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex + 1);
                $sheet->setCellValueExplicit("{$colLetter}{$rowNum}", $val, DataType::TYPE_STRING);
            }
            $rowNum++;
        }

        $filename = 'ptouch-tjkt-'.now()->format('Ymd-His').'.xlsx';

        return response()->streamDownload(function () use ($spreadsheet): void {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
            $spreadsheet->disconnectWorksheets();
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function markPrinted(Request $request): JsonResponse
    {
        abort_unless(Gate::allows('print', AssetItem::class), 403);

        $validated = $request->validate([
            'type' => ['nullable', 'in:item,bulk'],
            'ids' => ['required', 'array', 'min:1', 'max:2000'],
            'ids.*' => ['integer', 'min:1'],
        ]);

        $type = $validated['type'] ?? 'item';
        $ids = array_values(array_unique(array_map('intval', $validated['ids'])));
        $now = now();

        if ($type === 'bulk') {
            $updated = BulkStock::whereIn('id', $ids)->update([
                'qr_printed_at' => $now,
                'qr_print_count' => DB::raw('qr_print_count + 1'),
            ]);

            ActivityLogger::log(
                'bulk_stock.qr_printed',
                "Menandai {$updated} wadah pinjam massal sebagai Sudah Dicetak QR",
                null,
                ['count' => $updated, 'ids' => array_slice($ids, 0, 50)],
            );
        } else {
            $updated = AssetItem::whereIn('id', $ids)->update([
                'qr_printed_at' => $now,
                'qr_print_count' => DB::raw('qr_print_count + 1'),
            ]);

            ActivityLogger::log(
                'asset_item.qr_printed',
                "Menandai {$updated} unit sebagai Sudah Dicetak QR",
                null,
                ['count' => $updated, 'ids' => array_slice($ids, 0, 50)],
            );
        }

        return response()->json([
            'ok' => true,
            'updated' => $updated,
            'printed_at' => $now->format('d M Y H:i'),
        ]);
    }

    /**
     * @return list<string>
     */
    private static function ptouchHeaders(): array
    {
        return [
            'QR_CODE',
            'NAMA_ALAT',
            'KODE_ASET',
            'KATEGORI',
            'SPESIFIKASI',
            'SN_PABRIK',
            'LOKASI',
            'TITIK_SIMPAN',
            'PRESET_QR',
            'HEADER',
        ];
    }

    /**
     * @param  array<string, mixed>  $label
     * @return list<string>
     */
    private static function ptouchRow(array $label): array
    {
        return [
            self::csvSafe((string) $label['code']),
            self::csvSafe((string) $label['name']),
            self::csvSafe((string) ($label['asset_code'] ?? '')),
            self::csvSafe((string) ($label['category_code'] ?? '')),
            self::csvSafe((string) ($label['specification'] ?? '')),
            self::csvSafe((string) ($label['factory_sn'] ?? '')),
            self::csvSafe((string) ($label['location'] ?? '')),
            self::csvSafe((string) ($label['storage_spot'] ?? '')),
            self::csvSafe((string) ($label['default_preset'] ?? 'medium_24')),
            'INVENTARIS TJKT',
        ];
    }

    /**
     * @return array{
     *   printType: string,
     *   idsParam: string,
     *   activeMode: string,
     *   contextLabel: string,
     *   unprintedTotal: int,
     *   procurement: Procurement|null,
     *   models: Collection,
     *   labels: Collection<int, array<string, mixed>>
     * }
     */
    private function resolveLabels(Request $request): array
    {
        $type = $request->query('type') === 'bulk' ? 'bulk' : 'item';
        $ids = (string) $request->query('ids', 'all');
        $procurementId = $request->query('procurement_id');
        $onlyUnprinted = $request->boolean('only_unprinted', false);

        if ($type === 'bulk') {
            $query = BulkStock::query()->with(['asset.category', 'location'])->orderByDesc('id');
            $contextLabel = 'Semua Wadah Pinjam Massal';

            if ($ids === 'unprinted') {
                $query->whereNull('qr_printed_at');
                $contextLabel = 'Wadah Massal — Belum Dicetak';
            } elseif ($ids !== 'all') {
                $idList = $this->parseIds($ids);
                if ($idList === []) {
                    $query->whereRaw('1 = 0');
                } else {
                    $query->whereIn('id', $idList);
                }
                $contextLabel = 'Wadah Massal Terpilih';
            }

            $stocks = $query->get();
            $labels = $stocks->map(fn (BulkStock $stock): array => [
                'id' => $stock->id,
                'code' => $stock->container_qr,
                'name' => $stock->label ?: ($stock->asset?->nama_alat ?? __('units.print_default_tool')),
                'asset_code' => $stock->asset?->kode_aset ?? '',
                'category_code' => $stock->asset?->category?->code ?? '',
                'specification' => $stock->asset?->spesifikasi ?? '',
                'factory_sn' => '',
                'location' => $stock->location?->label ?? '',
                'storage_spot' => $stock->storage_spot ?? '',
                'meta' => trim(($stock->location?->label ?? '').($stock->storage_spot ? ' · '.$stock->storage_spot : '')),
                'default_preset' => $stock->asset?->default_qr_preset ?: 'medium_24',
                'is_critical' => (bool) ($stock->asset?->requires_approval ?? false),
                'printed_at' => $stock->qr_printed_at,
                'print_count' => (int) $stock->qr_print_count,
            ]);

            return [
                'printType' => 'bulk',
                'idsParam' => $ids,
                'activeMode' => $ids,
                'contextLabel' => $contextLabel,
                'unprintedTotal' => BulkStock::whereNull('qr_printed_at')->count(),
                'procurement' => null,
                'models' => $stocks,
                'labels' => $labels,
            ];
        }

        $query = AssetItem::query()
            ->with(['asset.category', 'location', 'procurement'])
            ->orderByDesc('id');

        $procurement = null;
        $contextLabel = 'Semua Unit';
        $activeMode = $ids;

        if (filled($procurementId) && ctype_digit((string) $procurementId)) {
            $procurement = Procurement::find((int) $procurementId);
            if ($procurement) {
                $query->where('procurement_id', $procurement->id);
                $contextLabel = "Batch {$procurement->batch_code}";
                $activeMode = 'batch';
                if ($onlyUnprinted) {
                    $query->whereNull('qr_printed_at');
                    $contextLabel .= ' (Belum Dicetak)';
                }
            } else {
                $query->whereRaw('1 = 0');
            }
        } elseif ($ids === 'unprinted') {
            $query->whereNull('qr_printed_at');
            $contextLabel = 'Unit Baru / Belum Dicetak';
        } elseif ($ids !== 'all') {
            $idList = $this->parseIds($ids);
            if ($idList === []) {
                $query->whereRaw('1 = 0');
            } else {
                $query->whereIn('id', $idList);
            }
            $contextLabel = 'Unit Terpilih';
        }

        $items = $query->get();

        $labels = $items->map(fn (AssetItem $item): array => [
            'id' => $item->id,
            'code' => $item->nomor_seri_atau_qr,
            'name' => $item->asset?->nama_alat ?? __('units.print_default_tool'),
            'asset_code' => $item->asset?->kode_aset ?? '',
            'category_code' => $item->asset?->category?->code ?? '',
            'specification' => $item->asset?->spesifikasi ?? '',
            'factory_sn' => $item->serial_number_pabrik ?? '',
            'location' => $item->location?->label ?? '',
            'storage_spot' => $item->storage_spot ?? '',
            'meta' => trim(($item->location?->label ?? '').($item->storage_spot ? ' · '.$item->storage_spot : '')),
            'default_preset' => $item->asset?->default_qr_preset ?: ($item->asset?->category?->default_qr_preset ?: 'medium_24'),
            'is_critical' => (bool) ($item->asset?->requires_approval ?? false),
            'printed_at' => $item->qr_printed_at,
            'print_count' => (int) $item->qr_print_count,
        ]);

        return [
            'printType' => 'item',
            'idsParam' => $ids,
            'activeMode' => $activeMode,
            'contextLabel' => $contextLabel,
            'unprintedTotal' => AssetItem::whereNull('qr_printed_at')->count(),
            'procurement' => $procurement,
            'models' => $items,
            'labels' => $labels,
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $labels
     */
    private function resolvePreset(string $requestedPreset, Collection $labels): string
    {
        if (array_key_exists($requestedPreset, self::PRESETS)) {
            return $requestedPreset;
        }

        if ($labels->isEmpty()) {
            return 'medium_24';
        }

        $dominant = $labels
            ->pluck('default_preset')
            ->filter(fn ($p) => is_string($p) && array_key_exists($p, self::PRESETS))
            ->countBy()
            ->sortDesc()
            ->keys()
            ->first();

        return is_string($dominant) ? $dominant : 'medium_24';
    }

    /**
     * Cegah CSV/Excel Formula Injection saat dibuka di Excel / Brother P-touch Editor.
     */
    private static function csvSafe(string $value): string
    {
        $clean = str_replace(["\r", "\n"], ' ', trim($value));
        if ($clean !== '' && in_array($clean[0], ['=', '+', '-', '@', "\t"], true)) {
            return "'".$clean;
        }

        return $clean;
    }

    /**
     * @return list<int>
     */
    private function parseIds(string $ids): array
    {
        return collect(explode(',', $ids))
            ->map(fn ($v) => trim($v))
            ->filter(fn ($v) => ctype_digit($v))
            ->map(fn ($v) => (int) $v)
            ->values()
            ->all();
    }
}
