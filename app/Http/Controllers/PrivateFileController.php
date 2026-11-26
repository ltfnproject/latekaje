<?php

namespace App\Http\Controllers;

use App\Models\Loan;
use App\Models\Procurement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PrivateFileController extends Controller
{
    public function loanReturnPhoto(Request $request, Loan $loan): BinaryFileResponse
    {
        abort_unless((bool) $request->user()?->can('view', $loan), 403);

        $path = trim((string) ($loan->return_photo_path ?? ''));

        if ($path === '' || str_contains($path, '..') || str_starts_with($path, '/') || str_starts_with($path, '\\')) {
            abort(404);
        }

        $disk = null;
        if (Storage::disk('local')->exists($path)) {
            $disk = 'local';
        } elseif (Storage::disk('public')->exists($path)) {
            $disk = 'public';
        }

        if ($disk === null) {
            abort(404);
        }

        return self::servePrivateImage($disk, $path, 'bukti-pinjam-'.$loan->id.'.jpg');
    }

    public function procurementPhoto(Request $request, Procurement $procurement, int $index): BinaryFileResponse
    {
        abort_unless((bool) $request->user()?->can('view', $procurement), 403);

        $photos = array_values(array_filter((array) ($procurement->photo_paths ?? []), 'is_string'));
        $path = isset($photos[$index]) ? trim($photos[$index]) : '';

        if ($path === '' || str_contains($path, '..') || str_starts_with($path, '/') || str_starts_with($path, '\\')) {
            abort(404);
        }

        if (! Storage::disk('local')->exists($path)) {
            abort(404);
        }

        return self::servePrivateImage('local', $path, "barang-masuk-{$procurement->batch_code}-".($index + 1).'.jpg');
    }

    private static function servePrivateImage(string $disk, string $path, string $filename): BinaryFileResponse
    {
        $fullPath = Storage::disk($disk)->path($path);
        $mime = Storage::disk($disk)->mimeType($path) ?: 'image/jpeg';

        $response = response()->file($fullPath, [
            'Content-Type' => $mime,
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
            'X-Content-Type-Options' => 'nosniff',
        ]);

        $response->setPrivate();
        $response->headers->set('Cache-Control', 'private, no-store, max-age=0');

        return $response;
    }
}
