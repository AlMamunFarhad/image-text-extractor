<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class VisionAIService
{
    public const PRESCRIPTION_KEYS = [
        'left_sph', 'left_cyl', 'left_pd', 'left_ax',
        'right_sph', 'right_cyl', 'right_pd', 'right_ax',
    ];

    protected string $prompt = <<<'PROMPT'
    You are an OCR and structured data extraction assistant for eyeglass prescriptions.

    Read the prescription image carefully.

    Extract only values for: SPH, CYL, AX, PD — separately for LEFT eye (OS) and RIGHT eye (OD). Map OD/right eye to right_* fields and OS/left eye to left_* fields. If labels use other conventions, infer left vs right from context.

    Return ONLY one JSON object (no markdown fences, no explanation, no comments, no extra keys).

    Rules:
    - If a value is missing or unreadable for a field, use JSON null for that field.
    - Present numeric values must be JSON strings: preserve + or - signs for SPH and CYL.
    - AX: digits only as a string (e.g. "180"). If missing, null.
    - PD: may be a decimal, as a string (e.g. "31.5"). If a single total PD is given, use the same string for both left_pd and right_pd when per-eye PD is not split.
    - Do not include OCR confidence or any text outside the JSON object.

    Exact keys (in this order in your output object):
    left_sph, left_cyl, left_pd, left_ax, right_sph, right_cyl, right_pd, right_ax

    If the entire prescription is unreadable, return the same object with every value null.
PROMPT;

    public function process($base64Data, $mimeType)
    {
        if ($this->isPdfMimeType($mimeType)) {
            $pdfBinary = base64_decode((string) $base64Data, true);
            if ($pdfBinary === false) {
                throw new \Exception('Invalid PDF data.');
            }
            [$base64Data, $mimeType] = $this->rasterizePdfFirstPageToPng($pdfBinary);
        }

        // 1. PRIMARY: Gemini (Flash 1.5)
        // $geminiKey = config('services.gemini.key');
        // if ($geminiKey) {
        //     try {
        //         $response = Http::timeout(60)->withOptions(['verify' => false])->withHeaders([
        //             'Content-Type' => 'application/json',
        //         ])->post('https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key=' . $geminiKey, [
        //             'contents' => [['parts' => [['text' => $this->prompt],
        //              ['inline_data' => ['mime_type' => $mimeType, 'data' => $base64Data]]]]]
        //         ]);

        //         if ($response->successful()) {
        //             $res = $response->json()['candidates'][0]['content']['parts'][0]['text'] ?? null;
        //             if ($res && !str_contains($res, 'UNCLEAR_IMAGE')) {
        //                 Log::info("Success: Processed with Gemini.");
        //                 return $res;
        //             }
        //         } else {
        //             Log::error("Gemini Error Response: " . $response->body());
        //         }
        //     } catch (\Exception $e) {
        //         Log::warning("Gemini Exception: " . $e->getMessage());
        //     }
        // }

        $openaiKey = config('services.openai.key');
        if (!$openaiKey) {
            throw new \Exception(
                'OpenAI is not configured. Set OPENAI_API_KEY in your environment.'
            );
        }

        try {
            $response = Http::timeout(120)->withOptions(['verify' => false])->withHeaders([
                'Authorization' => 'Bearer '.$openaiKey,
                'Content-Type' => 'application/json',
            ])->post('https://api.openai.com/v1/chat/completions', [
                'model' => 'gpt-4o',
                'response_format' => ['type' => 'json_object'],
                'messages' => [['role' => 'user', 'content' => [
                    ['type' => 'text', 'text' => $this->prompt],
                    ['type' => 'image_url', 'image_url' =>
                    ['url' => "data:{$mimeType};base64,{$base64Data}"]]]]],
            ]);

            if ($response->successful()) {
                $raw = $response->json()['choices'][0]['message']['content'] ?? null;
                if ($raw !== null && $raw !== '') {
                    Log::info('Success: Processed with OpenAI.');

                    return $this->normalizePrescriptionJson($raw);
                }
            } else {
                Log::error('OpenAI Error Response: '.$response->body());
            }
        } catch (\Exception $e) {
            Log::warning('OpenAI Exception: '.$e->getMessage());
        }

        throw new \Exception(
            'The vision service could not extract data from this document. Check OPENAI_API_KEY, try a clearer image, or verify the file format is supported.'
        );
    }

    public function normalizePrescriptionJson(string $raw): string
    {
        //changes
        $json = $this->stripMarkdownJsonFence(trim($raw));
        $decoded = json_decode($json, true);
        if (! is_array($decoded)) {
            throw new \Exception('The model returned invalid JSON for this prescription.');
        }

        $out = [];
        foreach (self::PRESCRIPTION_KEYS as $key) {
            $v = $decoded[$key] ?? null;
            if ($v === null || $v === '') {
                $out[$key] = null;

                continue;
            }
            if (is_int($v) || is_float($v)) {
                $out[$key] = $key === 'left_ax' || $key === 'right_ax'
                    ? (string) (int) $v
                    : $this->stringifyNumber($v);

                continue;
            }
            if (is_string($v)) {
                $trimmed = trim($v);
                if ($trimmed === '' || strtolower($trimmed) === 'null') {
                    $out[$key] = null;

                    continue;
                }
                if ($key === 'left_ax' || $key === 'right_ax') {
                    if (preg_match('/^\d+$/', $trimmed)) {
                        $out[$key] = $trimmed;
                    } else {
                        $digits = preg_replace('/\D/', '', $trimmed);
                        $out[$key] = $digits !== '' ? $digits : null;
                    }
                } else {
                    $out[$key] = $trimmed;
                }
            } else {
                $out[$key] = null;
            }
        }

        return json_encode($out, JSON_UNESCAPED_UNICODE);
    }

    protected function stringifyNumber(int|float $v): string
    {
        if (abs($v - (int) $v) < 1e-9) {
            return (string) (int) $v;
        }

        return rtrim(rtrim(sprintf('%.4f', $v), '0'), '.');
    }

    protected function stripMarkdownJsonFence(string $raw): string
    {
        if (preg_match('/^```(?:json)?\s*([\s\S]*?)\s*```$/i', $raw, $m)) {
            return trim($m[1]);
        }

        return $raw;
    }

    protected function isPdfMimeType(string $mimeType): bool
    {
        $normalized = strtolower($mimeType);

        return str_contains($normalized, 'pdf');
    }

    /**
     * @return array{0: string, 1: string} [base64 png, image/png]
     */
    protected function rasterizePdfFirstPageToPng(string $pdfBinary): array
    {
        if (! extension_loaded('imagick')) {
            throw new \Exception(
                'PDF uploads need the PHP Imagick extension (and Ghostscript where required). Enable imagick for your PHP build in Laragon, or upload a PNG/JPEG of the prescription.'
            );
        }

        try {
            $imagick = new \Imagick;
            $imagick->setResolution(144, 144);
            $imagick->readImageBlob($pdfBinary, 'prescription.pdf');
            $imagick->setIteratorIndex(0);
            $imagick->setImageFormat('png');
            $imagick->setImageAlphaChannel(\Imagick::ALPHACHANNEL_REMOVE);
            $imagick->setBackgroundColor(new \ImagickPixel('white'));
            $flat = $imagick->mergeImageLayers(\Imagick::LAYERMETHOD_FLATTEN);
            $pngBinary = $flat->getImageBlob();
            $flat->clear();
            $flat->destroy();
            $imagick->clear();
            $imagick->destroy();
        } catch (\Throwable $e) {
            Log::warning('PDF rasterize failed: '.$e->getMessage());
            throw new \Exception(
                'Could not convert this PDF to an image. Try a non-password PDF or export the prescription as PNG/JPEG.'
            );
        }

        if ($pngBinary === '' || $pngBinary === false) {
            throw new \Exception('PDF conversion produced no image.');
        }

        return [base64_encode($pngBinary), 'image/png'];
    }
}
