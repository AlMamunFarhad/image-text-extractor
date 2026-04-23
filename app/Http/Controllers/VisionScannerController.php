<?php

namespace App\Http\Controllers;

use App\Models\VisionScan;
use App\Jobs\ProcessVisionScan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class VisionScannerController extends Controller
{
    public function index()
    {
        return view('welcome');
    }

    public function upload(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:jpg,jpeg,png,gif,pdf,webp,bmp,txt,tiff,heic,heif|max:10240',
        ]);

        $file = $request->file('file');
        $path = $file->store('uploads');

        $scan = VisionScan::create([
            'file_path' => $path,
            'mime_type' => $file->getMimeType(),
            'status' => 'pending'
        ]);

        ProcessVisionScan::dispatch($scan);

        return response()->json([
            'success' => true,
            'id' => $scan->id,
            'message' => 'File uploaded successfully. Processing started.'
        ]);
    }

    public function status($id)
    {
        $scan = VisionScan::findOrFail($id);
        
        return response()->json([
            'status' => $scan->status,
            'result' => $scan->result,
            'error' => $scan->error
        ]);
    }
}
