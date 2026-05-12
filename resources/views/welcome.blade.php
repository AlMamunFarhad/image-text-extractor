<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Prescription OCR | Eyeglass extraction</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Outfit', sans-serif;
            background: #f8fafc;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }
        .glass-panel {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 40px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.05);
            border: 1px solid #e2e8f0;
        }
        .upload-zone {
            border: 2px dashed #cbd5e1;
            background: #f1f5f9;
            transition: all 0.3s ease;
        }
        .upload-zone:hover {
            border-color: #6366f1;
            background: #f8fafc;
        }
        .loader {
            border: 4px solid #f3f3f3;
            border-top: 4px solid #6366f1;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        .hidden { display: none !important; }
        #errorAlert {
            background: #fff1f2;
            border: 1px solid #fecdd3;
            border-radius: 24px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            display: flex;
            align-items: flex-start;
            gap: 1rem;
            box-shadow: 0 10px 15px -3px rgba(159, 18, 57, 0.05);
        }
    </style>
</head>
<body class="p-6">

    <div class="w-full max-w-3xl">
        <div class="text-center mb-10">
            <h1 class="text-4xl font-extrabold text-slate-900 mb-2">Prescription<span class="text-indigo-600">OCR</span></h1>
            <p class="text-slate-500 font-medium uppercase tracking-widest text-xs">Eyeglass SPH · CYL · AX · PD (OD / OS)</p>
        </div>

        <div id="errorAlert" class="hidden">
            <div class="w-10 h-10 bg-rose-100 rounded-xl flex items-center justify-center shrink-0">
                <svg class="w-6 h-6 text-rose-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
            </div>
            <div>
                <h4 class="text-sm font-bold text-rose-900 uppercase tracking-widest mb-1">Extraction Error</h4>
                <p id="errorMessage" class="text-rose-700 font-medium text-sm"></p>
            </div>
        </div>

        <div class="glass-panel p-8 md:p-12">
            <form id="uploadForm" class="space-y-8">
                <div id="dropZone" class="upload-zone rounded-[32px] p-12 text-center cursor-pointer group">
                    <input type="file" id="imageInput" class="hidden" accept="image/*,application/pdf">
                    <div class="flex flex-col items-center">
                        <div class="w-16 h-16 bg-white rounded-2xl flex items-center justify-center shadow-sm mb-4 group-hover:scale-110 transition-transform">
                            <svg class="w-8 h-8 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                        </div>
                        <h2 class="text-xl font-bold text-slate-800">Upload prescription image or PDF</h2>
                        <p class="text-slate-400 text-sm mt-1">PNG, JPEG, WebP, PDF (first page, converted in browser) · up to 10MB</p>
                    </div>
                </div>
                <div id="selectedFileRow" class="hidden rounded-2xl border border-slate-200 bg-white px-4 py-3 text-center">
                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1">Selected file</p>
                    <p id="selectedFileName" class="text-sm font-semibold text-slate-800 truncate max-w-full mx-auto" title=""></p>
                    <p id="selectedFileMeta" class="text-xs text-slate-500 mt-0.5"></p>
                </div>
                <button type="submit" id="submitBtn" class="w-full py-4 bg-indigo-600 text-white font-bold rounded-2xl shadow-lg hover:bg-indigo-700 transition-all">
                    Extract prescription
                </button>
            </form>

            <div id="loadingState" class="hidden mt-10 flex flex-col items-center">
                <div class="loader mb-4"></div>
                <h3 id="loadingTitle" class="text-lg font-bold text-slate-800">Thinking about prescription…</h3>
                <p id="loadingFileName" class="text-sm text-slate-500 mt-2 max-w-full px-4 text-center truncate" title=""></p>
                <div class="w-full bg-slate-100 rounded-full h-2 mt-8 overflow-hidden">
                    <div id="progressBar" class="bg-indigo-600 h-full w-0 transition-all duration-300"></div>
                </div>
            </div>

            <div id="resultContainer" class="hidden mt-10 pt-10 border-t border-slate-100 space-y-6">
                <p class="text-center text-[10px] font-bold text-slate-400 uppercase tracking-widest">Structured result</p>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="rounded-[28px] border border-indigo-100 bg-indigo-50/50 p-6">
                        <h3 class="text-sm font-extrabold text-indigo-900 uppercase tracking-wider mb-4">Left eye (OS)</h3>
                        <dl class="space-y-3 text-sm">
                            <div class="flex justify-between gap-4"><dt class="text-slate-500 font-medium">SPH</dt><dd id="left_sph" class="font-bold text-slate-900 tabular-nums">—</dd></div>
                            <div class="flex justify-between gap-4"><dt class="text-slate-500 font-medium">CYL</dt><dd id="left_cyl" class="font-bold text-slate-900 tabular-nums">—</dd></div>
                            <div class="flex justify-between gap-4"><dt class="text-slate-500 font-medium">AX</dt><dd id="left_ax" class="font-bold text-slate-900 tabular-nums">—</dd></div>
                            <div class="flex justify-between gap-4"><dt class="text-slate-500 font-medium">PD</dt><dd id="left_pd" class="font-bold text-slate-900 tabular-nums">—</dd></div>
                        </dl>
                    </div>
                    <div class="rounded-[28px] border border-slate-200 bg-slate-50 p-6">
                        <h3 class="text-sm font-extrabold text-slate-800 uppercase tracking-wider mb-4">Right eye (OD)</h3>
                        <dl class="space-y-3 text-sm">
                            <div class="flex justify-between gap-4"><dt class="text-slate-500 font-medium">SPH</dt><dd id="right_sph" class="font-bold text-slate-900 tabular-nums">—</dd></div>
                            <div class="flex justify-between gap-4"><dt class="text-slate-500 font-medium">CYL</dt><dd id="right_cyl" class="font-bold text-slate-900 tabular-nums">—</dd></div>
                            <div class="flex justify-between gap-4"><dt class="text-slate-500 font-medium">AX</dt><dd id="right_ax" class="font-bold text-slate-900 tabular-nums">—</dd></div>
                            <div class="flex justify-between gap-4"><dt class="text-slate-500 font-medium">PD</dt><dd id="right_pd" class="font-bold text-slate-900 tabular-nums">—</dd></div>
                        </dl>
                    </div>
                </div>
                <details class="rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm">
                    <summary class="cursor-pointer font-semibold text-slate-600">Raw JSON</summary>
                    <pre id="rawJson" class="mt-3 overflow-x-auto text-xs text-slate-700 font-mono whitespace-pre-wrap break-all"></pre>
                </details>
            </div>
        </div>
    </div>

</body>
</html>
