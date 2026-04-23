<?php

namespace App\Jobs;

use App\Models\VisionScan;
use App\Services\VisionAIService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class ProcessVisionScan implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $scan;

    public function __construct(VisionScan $scan)
    {
        $this->scan = $scan;
    }

    public function handle(VisionAIService $aiService)
    {
        try {
            $this->scan->update(['status' => 'processing']);

            if (!Storage::disk('local')->exists($this->scan->file_path)) {
                throw new \Exception("File not found on disk: " . $this->scan->file_path);
            }

            $content = Storage::disk('local')->get($this->scan->file_path);
            $base64Data = base64_encode($content);
            
            $result = $aiService->process($base64Data, $this->scan->mime_type);

            $this->scan->update([
                'status' => 'completed',
                'result' => $result
            ]);
        } catch (\Throwable $e) { // Catching ALL errors including fatal ones
            Log::error("Job Fatal Error: " . $e->getMessage());
            $this->scan->update([
                'status' => 'failed',
                'error' => $e->getMessage()
            ]);
        }
    }
}
