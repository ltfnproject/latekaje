<?php

namespace App\Http\Controllers;

use App\Models\Loan;
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
            // Backward compatibility untuk foto lama v3, tetap dilindungi auth + LoanPolicy.
            $disk = 'public';
        }

        if ($disk === null) {
            abort(404);
        }

        $fullPath = Storage::disk($disk)->path($path);
        $mime = Storage::disk($disk)->mimeType($path) ?: 'image/jpeg';

        $response = response()->file($fullPath, [
            'Content-Type' => $mime,
            'Content-Disposition' => 'inline; filename="bukti-pinjam-'.$loan->id.'.jpg"',
            'X-Content-Type-Options' => 'nosniff',
        ]);

        $response->setPrivate();
        $response->headers->set('Cache-Control', 'private, no-store, max-age=0');

        return $response;
    }
}
