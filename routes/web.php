<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\OcrController;

Route::get('/', function () {
    return redirect('/ocr');
});

// Route untuk halaman utama OCR
Route::get('/ocr', [OcrController::class, 'index'])->name('ocr.index');

// Route untuk memproses gambar (POST)
Route::post('/ocr/process', [OcrController::class, 'process'])->name('ocr.process');

// [NEW] Route untuk menyimpan data hasil reform (Registrasi)
Route::post('/ocr/store', [OcrController::class, 'store'])->name('ocr.store');
