<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vision_scans', function (Blueprint $row) {
            $row->id();
            $row->string('file_path');
            $row->string('mime_type');
            $row->string('status')->default('pending');
            $row->text('result')->nullable();
            $row->text('error')->nullable();
            $row->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vision_scans');
    }
};
