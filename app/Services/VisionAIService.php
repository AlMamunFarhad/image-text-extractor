<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class VisionAIService
{
    protected $prompt = "Extract details from this document and return them in this exact format. If a field is missing, write 'Not found'.
                            
    NAME: [Full Name]
    TITLE: [Job Title]
    PHONE: [Phone Number]
    EMAIL: [Email Address]
    SKILLS: [Skill 1, Skill 2, ...]
    EDUCATION: [Education details]
    
    IMPORTANT: 
    - Use ONLY the labels above.
    - Do not add any conversational text or Markdown symbols.
    - If unreadable, reply ONLY with 'UNCLEAR_IMAGE'.";

    public function process($base64Data, $mimeType)
    {
        // 1. PRIMARY: Gemini (Flash 1.5)
        $geminiKey = config('services.gemini.key');
        if ($geminiKey) {
            try {
                $response = Http::timeout(60)->withOptions(['verify' => false])->withHeaders([
                    'Content-Type' => 'application/json',
                ])->post('https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key=' . $geminiKey, [
                    'contents' => [['parts' => [['text' => $this->prompt], ['inline_data' => ['mime_type' => $mimeType, 'data' => $base64Data]]]]]
                ]);

                if ($response->successful()) {
                    $res = $response->json()['candidates'][0]['content']['parts'][0]['text'] ?? null;
                    if ($res && !str_contains($res, 'UNCLEAR_IMAGE')) {
                        Log::info("Success: Processed with Gemini.");
                        return $res;
                    }
                } else {
                    Log::error("Gemini Error Response: " . $response->body());
                }
            } catch (\Exception $e) { 
                Log::warning("Gemini Exception: " . $e->getMessage()); 
            }
        }

        // 2. FALLBACK: OpenAI (GPT-4o)
        $openaiKey = config('services.openai.key');
        if ($openaiKey && !str_contains($mimeType, 'pdf')) {
            try {
                $response = Http::timeout(60)->withOptions(['verify' => false])->withHeaders([
                    'Authorization' => 'Bearer ' . $openaiKey,
                    'Content-Type' => 'application/json',
                ])->post('https://api.openai.com/v1/chat/completions', [
                    'model' => 'gpt-4o',
                    'messages' => [['role' => 'user', 'content' => [['type' => 'text', 'text' => $this->prompt], ['type' => 'image_url', 'image_url' => ['url' => "data:{$mimeType};base64,{$base64Data}"]]]]]
                ]);

                if ($response->successful()) {
                    $res = $response->json()['choices'][0]['message']['content'] ?? null;
                    if ($res && !str_contains($res, 'UNCLEAR_IMAGE')) {
                        Log::info("Success: Processed with OpenAI.");
                        return $res;
                    }
                } else {
                    Log::error("OpenAI Error Response: " . $response->body());
                }
            } catch (\Exception $e) { 
                Log::warning("OpenAI Exception: " . $e->getMessage()); 
            }
        }

        // 3. FINAL ERROR: If both failed
        throw new \Exception("The system failed to process the document with both Gemini and OpenAI. Please check your API keys or ensure the image is clear.");
    }
}
