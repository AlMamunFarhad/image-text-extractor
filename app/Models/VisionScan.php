<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VisionScan extends Model
{
    protected $fillable = ['file_path', 'mime_type', 'status', 'result', 'error'];
}
