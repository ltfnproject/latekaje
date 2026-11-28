<?php

use App\Http\Controllers\PrintQrController;
use App\Http\Controllers\PrivateFileController;
use App\Http\Middleware\EnsureUserIsActive;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/admin');

Route::middleware(['auth', EnsureUserIsActive::class])->group(function (): void {
    Route::get('/print-qr', PrintQrController::class)->name('print.qr');
    Route::post('/print-qr/mark-printed', [PrintQrController::class, 'markPrinted'])
        ->name('print.qr.mark-printed');

    Route::get('/files/loans/{loan}/return-photo', [PrivateFileController::class, 'loanReturnPhoto'])
        ->name('files.loans.return-photo');

    Route::get('/files/procurements/{procurement}/photos/{index}', [PrivateFileController::class, 'procurementPhoto'])
        ->whereNumber('index')
        ->name('files.procurements.photo');
});

Route::redirect('/login', '/admin/login')->name('login');
