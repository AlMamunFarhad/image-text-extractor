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
        try {
            $request->validate([
                'file' => 'required|file|mimes:jpg,jpeg,png,gif,webp,bmp,tiff,heic,heif,pdf|max:10240',
            ]);

            $file = $request->file('file');
            $path = $file->store('uploads');

            $scan = VisionScan::create([
                'file_path' => $path,
                'mime_type' => $file->getMimeType(),
                'status' => 'pending'
            ]);

            // Using dispatchSync to avoid queue worker dependency for immediate results
            ProcessVisionScan::dispatch($scan);

            return response()->json([
                'success' => true,
                'id' => $scan->id,
                'message' => 'File processed successfully.'
            ]);
        } catch (\Exception $e) {
            \Log::error("Upload failed: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Upload or processing failed: ' . $e->getMessage()
            ], 500);
        }
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
