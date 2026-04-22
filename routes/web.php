<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\VisionScannerController;

Route::get('/', function () {
    return view('welcome');
});

Route::post('/upload-image', [VisionScannerController::class, 'upload'])->name('upload.image');
