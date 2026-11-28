<?php

namespace App\Http\Controllers;

use App\Models\AssetItem;
use App\Models\BulkStock;
use App\Models\Procurement;
use App\Services\ActivityLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class PrintQrController extends Controller
{
    public function __invoke(Request $request): View
    {
        abort_unless(Gate::allows('print', AssetItem::class), 403);

        $type = $request->query('type') === 'bulk' ? 'bulk' : 'item';
        $ids = (string) $request->query('ids', 'all');
        $procurementId = $request->query('procurement_id');
        $onlyUnprinted = $request->boolean('only_unprinted', false);

        if ($type === 'bulk') {
            $query = BulkStock::query()->with(['asset', 'location'])->orderByDesc('id');
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
                'meta' => trim(($stock->location?->label ?? '').($stock->storage_spot ? ' · '.$stock->storage_spot : '')),
                'printed_at' => $stock->qr_printed_at,
                'print_count' => (int) $stock->qr_print_count,
            ]);

            $unprintedTotal = BulkStock::whereNull('qr_printed_at')->count();

            ActivityLogger::log(
                'qr.printed',
                "Membuka pratinjau cetak QR wadah massal ({$labels->count()} wadah)",
                null,
                ['type' => 'bulk', 'ids' => $ids, 'count' => $labels->count()],
            );

            return view('print-qrcode', [
                'items' => $stocks,
                'labels' => $labels,
                'printType' => 'bulk',
                'contextLabel' => $contextLabel,
                'unprintedTotal' => $unprintedTotal,
                'activeMode' => $ids,
                'procurement' => null,
            ]);
        }

        $query = AssetItem::query()
            ->with(['asset', 'location', 'procurement'])
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
            'meta' => trim(($item->location?->label ?? '').($item->storage_spot ? ' · '.$item->storage_spot : '')),
            'printed_at' => $item->qr_printed_at,
            'print_count' => (int) $item->qr_print_count,
        ]);

        $unprintedTotal = AssetItem::whereNull('qr_printed_at')->count();

        ActivityLogger::log(
            'qr.printed',
            "Membuka pratinjau cetak QR ({$items->count()} unit — {$contextLabel})",
            $procurement,
            [
                'type' => 'item',
                'ids' => $ids === 'all' ? 'all' : $items->pluck('id')->all(),
                'procurement_id' => $procurement?->id,
                'count' => $items->count(),
            ],
        );

        return view('print-qrcode', [
            'items' => $items,
            'labels' => $labels,
            'printType' => 'item',
            'contextLabel' => $contextLabel,
            'unprintedTotal' => $unprintedTotal,
            'activeMode' => $activeMode,
            'procurement' => $procurement,
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
