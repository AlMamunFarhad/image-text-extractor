<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\VisionScannerController;

Route::get('/', [VisionScannerController::class, 'index']);

Route::post('/upload-image', [VisionScannerController::class, 'upload'])->name('upload.image');

Route::get('/scan-status/{id}', [VisionScannerController::class, 'status'])->name('scan.status');
