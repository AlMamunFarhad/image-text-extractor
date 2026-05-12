---
name: vision-scanner
description: >-
  Guides the Laravel prescription OCR pipeline: image upload, synchronous ProcessVisionScan,
  OpenAI gpt-4o with json_object response, VisionAIService eyeglass JSON schema (SPH, CYL,
  AX, PD per OD/OS), and welcome.blade.php JSON rendering. Use when editing VisionAIService,
  ProcessVisionScan, VisionScannerController upload validation, welcome.blade.php, routes,
  TestVision command, or OPENAI_API_KEY / GEMINI_API_KEY.
---

# Vision Scanner (prescription OCR)

## Flow

1. `POST /upload-image` validates `file` (images + **pdf**; max **10240** KB).
2. File stored under `storage/app/uploads`.
3. `VisionScan` row created, `ProcessVisionScan::dispatchSync($scan)`.
4. `VisionAIService::process()` returns **canonical JSON string** (8 keys, `null` or string per field).
5. Client polls `GET /scan-status/{id}`; `prescriptionWelcome.js` parses JSON for the UI.

## JSON schema (stored `result`)

Keys: `left_sph`, `left_cyl`, `left_pd`, `left_ax`, `right_sph`, `right_cyl`, `right_pd`, `right_ax`.

- Missing → JSON `null`.
- Present → JSON string; SPH/CYL keep sign; AX digits-only string; PD may include decimals.
- Single total PD may be duplicated to both `left_pd` and `right_pd` when split values are absent (per service prompt).

## OpenAI

- Model `gpt-4o`, `response_format: { type: json_object }`, vision via `image_url` data URI.
- **PDF**: The welcome page converts the **first page to PNG in the browser** (`pdfjs-dist` in `resources/js/`) before upload, so **Imagick is optional** for web users. `VisionAIService` still rasterizes PDF with **Imagick** when raw PDF bytes reach the server (e.g. `php artisan test:vision` on a `.pdf`). Gemini block remains commented.

## Files

- Prompt + normalization: `app/Services/VisionAIService.php` (`PRESCRIPTION_KEYS`, `normalizePrescriptionJson`).
- UI: `resources/views/welcome.blade.php` + `resources/js/prescriptionWelcome.js`, `resources/js/pdfFirstPageToPng.js`.

## CLI

- `php artisan test:vision {path}` — prints the JSON string returned by `process()`.
