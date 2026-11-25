<?php

namespace App\Http\Controllers;

use App\Models\AssetItem;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class PrintQrController extends Controller
{
    public function __invoke(Request $request): View
    {
        abort_unless(Gate::allows('print', AssetItem::class), 403);

        $ids = (string) $request->query('ids', 'all');

        $query = AssetItem::query()->with('asset')->orderBy('nomor_seri_atau_qr');

        if ($ids !== 'all') {
            $idList = collect(explode(',', $ids))
                ->map(fn ($v) => trim($v))
                ->filter(fn ($v) => ctype_digit($v))
                ->map(fn ($v) => (int) $v)
                ->values()
                ->all();

            if ($idList === []) {
                $query->whereRaw('1 = 0');
            } else {
                $query->whereIn('id', $idList);
            }
        }

        $items = $query->get();

        ActivityLogger::log(
            'qr.printed',
            "Mencetak label QR ({$items->count()} unit)",
            null,
            ['ids' => $ids === 'all' ? 'all' : $items->pluck('id')->all(), 'count' => $items->count()],
        );

        return view('print-qrcode', [
            'items' => $items,
        ]);
    }
}
