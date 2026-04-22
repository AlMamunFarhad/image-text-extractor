<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class VisionScannerController extends Controller
{
    public function upload(Request $request)
    {
        ini_set('max_execution_time', 300); // 5 minutes
        ini_set('memory_limit', '512M');
        
        $request->validate([
            'file' => 'required|file|mimes:jpg,jpeg,png,gif,pdf,webp,bmp,txt,tiff,heic,heif|max:10240', // Max 10MB
        ]);

        $file = $request->file('file');
        $base64Data = base64_encode(file_get_contents($file->getRealPath()));
        $mimeType = $file->getMimeType();

        $apiKey = env('GEMINI_API_KEY');

        if (!$apiKey) {
            return response()->json([
                'success' => false,
                'message' => 'Gemini API Key is not set. Please provide the API Key in the .env file.'
            ], 500);
        }

        // Call Gemini API with increased timeout (120 seconds) - Using gemini-2.5-flash for better performance and quota
        $response = Http::timeout(120)->withHeaders([
            'Content-Type' => 'application/json',
        ])->post('https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=' . $apiKey, [
            'contents' => [
                [
                    'parts' => [
                        [
                            'text' => "Extract details from this document and return them in this exact format. If a field is missing, write 'Not found'.
                            
                            NAME: [Full Name]
                            TITLE: [Job Title]
                            PHONE: [Phone Number]
                            EMAIL: [Email Address]
                            SKILLS: [Skill 1, Skill 2, ...]
                            EDUCATION: [Education details]
                            
                            IMPORTANT: 
                            - Use ONLY the labels above.
                            - Do not add any conversational text or Markdown symbols.
                            - If unreadable, reply ONLY with 'UNCLEAR_IMAGE'."
                        ],
                        [
                            'inline_data' => [
                                'mime_type' => $mimeType,
                                'data' => $base64Data
                            ]
                        ]
                    ]
                ]
            ],
            'generationConfig' => [
                'temperature' => 0.1,
            ]
        ]);

        if ($response->successful()) {
            $data = $response->json();
            $extractedText = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
            $extractedText = trim($extractedText);

            if ($extractedText === 'UNCLEAR_IMAGE') {
                return response()->json([
                    'success' => false,
                    'message' => 'The image is unclear. Please provide a clear image so the text can be understood.'
                ], 422);
            }

            if (empty($extractedText)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No text found in this image.'
                ], 422);
            }

            return response()->json([
                'success' => true,
                'text' => $extractedText
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'API Error: ' . $response->body()
        ], 500);
    }
}
