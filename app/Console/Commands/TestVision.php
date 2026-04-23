<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\VisionAIService;
use Illuminate\Support\Facades\Storage;

class TestVision extends Command
{
    protected $signature = 'test:vision {file}';
    protected $description = 'Test all Vision APIs with a specific file';

    public function handle(VisionAIService $aiService)
    {
        $filePath = $this->argument('file');
        
        if (!file_exists($filePath)) {
            $this->error("File not found at: $filePath");
            return;
        }

        $this->info("Starting diagnostic test for file: $filePath");
        
        $content = file_get_contents($filePath);
        $base64Data = base64_encode($content);
        $mimeType = mime_content_type($filePath);

        $this->info("Mime Type: $mimeType");
        $this->info("Base64 Length: " . strlen($base64Data));

        try {
            $result = $aiService->process($base64Data, $mimeType);
            $this->info("\n--- SUCCESS! ---");
            $this->line($result);
        } catch (\Exception $e) {
            $this->error("\n--- ALL ENGINES FAILED ---");
            $this->line($e->getMessage());
        }
    }
}
