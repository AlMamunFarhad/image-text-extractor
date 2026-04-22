<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Image to Text Extractor</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background: #ffffff;
            background-image: radial-gradient(at 0% 0%, hsla(253,16%,7%,1) 0, transparent 50%), 
                              radial-gradient(at 50% 0%, hsla(225,39%,30%,1) 0, transparent 50%), 
                              radial-gradient(at 100% 0%, hsla(339,49%,30%,1) 0, transparent 50%);
            background: #f8fafc;
            background-image: 
                radial-gradient(at 0% 0%, rgba(79, 70, 229, 0.05) 0px, transparent 50%),
                radial-gradient(at 50% 0%, rgba(124, 58, 237, 0.05) 0px, transparent 50%),
                radial-gradient(at 100% 0%, rgba(219, 39, 119, 0.05) 0px, transparent 50%);
            min-height: 100vh;
        }
        .glass-panel {
            background: rgba(255, 255, 255, 0.8);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.05), 0 1px 3px rgba(0, 0, 0, 0.05);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border-radius: 32px;
            border: 1px solid rgba(255, 255, 255, 1);
        }
        .upload-area {
            border: 2px dashed rgba(79, 70, 229, 0.2);
            background: rgba(248, 250, 252, 0.5);
            transition: all 0.3s ease;
        }
        .upload-area:hover, .upload-area.dragover {
            background: rgba(79, 70, 229, 0.05);
            border-color: #4f46e5;
        }
        
        /* Spinner */
        .loader {
            border: 4px solid rgba(79, 70, 229, 0.1);
            border-radius: 50%;
            border-top: 4px solid #4f46e5;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .fade-in {
            animation: fadeIn 0.5s ease-in-out;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body class="flex items-center justify-center p-4 text-gray-800">

    <div class="glass-panel w-full max-w-3xl p-8 md:p-12 fade-in">
        <div class="text-center mb-10">
            <h1 class="text-4xl md:text-5xl font-bold text-gray-900 mb-4 tracking-tight">Vision Scanner</h1>
            <p class="text-gray-600 text-lg font-medium">Upload any file and easily extract key details</p>
        </div>

        <form id="uploadForm" class="mb-8">
            <div id="dropZone" class="upload-area rounded-2xl p-10 flex flex-col items-center justify-center cursor-pointer relative overflow-hidden group">
                <input type="file" id="imageInput" name="file" accept="image/*,application/pdf,text/plain,.tiff,.heic,.heif" class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-10" required>
                
                <div id="uploadPlaceholder" class="flex flex-col items-center transition-opacity duration-300">
                    <svg class="w-16 h-16 text-indigo-500 mb-4 group-hover:scale-110 transition-transform duration-300" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path></svg>
                    <p class="text-gray-700 font-semibold text-xl mb-2">Drop file here or click to upload</p>
                    <p class="text-gray-500 text-sm">Supported: Images, PDF, TXT, TIFF, HEIC (Max 10MB)</p>
                </div>
                
                <div id="imagePreviewContainer" class="hidden absolute inset-0 w-full h-full z-0 p-4">
                    <img id="imagePreview" src="" alt="Preview" class="w-full h-full object-contain rounded-xl shadow-lg">
                </div>
            </div>

            <div class="mt-6 flex justify-center">
                <button type="submit" id="submitBtn" class="bg-gradient-to-r from-indigo-600 to-violet-700 text-white font-bold text-lg px-8 py-3 rounded-full shadow-lg hover:shadow-2xl hover:scale-105 transition-all duration-300 disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2">
                    <span>Scan & Extract</span>
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                </button>
            </div>
        </form>

        <div id="loadingState" class="hidden flex-col items-center justify-center py-10 bg-indigo-50 rounded-3xl border border-indigo-100 mb-6">
            <div class="loader mb-6"></div>
            <p class="text-indigo-700 font-bold text-xl mb-4 animate-pulse">Scanning Document...</p>
            
            <div class="w-full max-w-md bg-gray-200 rounded-full h-4 mb-2 overflow-hidden shadow-inner">
                <div id="progressBar" class="bg-gradient-to-r from-indigo-500 to-purple-600 h-full w-0 transition-all duration-300 ease-out"></div>
            </div>
            <p id="progressPercentage" class="text-indigo-600 font-bold text-lg">0%</p>
            <p class="text-gray-500 text-sm mt-2">AI is analyzing your file, please wait a moment...</p>
        </div>

        <div id="errorAlert" class="hidden bg-red-500 bg-opacity-90 text-white p-4 rounded-xl shadow-md mb-6 flex items-start gap-3">
            <svg class="w-6 h-6 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            <p id="errorMessage" class="font-medium"></p>
        </div>

        <div id="resultContainer" class="hidden fade-in space-y-6">
            <div class="bg-white rounded-3xl p-8 shadow-2xl border border-gray-100 overflow-hidden relative">
                <!-- Decorative Circle -->
                <div class="absolute -top-10 -right-10 w-32 h-32 bg-indigo-50 rounded-full opacity-50"></div>
                
                <div class="flex flex-col md:flex-row items-center gap-6 mb-8 border-b border-gray-100 pb-8">
                    <div class="w-24 h-24 bg-gradient-to-tr from-indigo-500 to-purple-600 rounded-2xl flex items-center justify-center text-white shadow-lg shadow-indigo-200 shrink-0">
                        <svg class="w-12 h-12" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                    </div>
                    <div class="text-center md:text-left">
                        <h2 id="cardName" class="text-3xl font-extrabold text-gray-900 tracking-tight mb-1">---</h2>
                        <p id="cardTitle" class="text-indigo-600 font-semibold text-lg uppercase tracking-wider">---</p>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="flex items-center gap-4 p-4 bg-gray-50 rounded-2xl border border-gray-100">
                        <div class="w-10 h-10 bg-white rounded-xl flex items-center justify-center text-indigo-500 shadow-sm">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path></svg>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500 font-bold uppercase">Phone</p>
                            <p id="cardPhone" class="text-gray-800 font-medium font-mono text-base">---</p>
                        </div>
                    </div>

                    <div class="flex items-center gap-4 p-4 bg-gray-50 rounded-2xl border border-gray-100">
                        <div class="w-10 h-10 bg-white rounded-xl flex items-center justify-center text-indigo-500 shadow-sm">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
                        </div>
                        <div class="overflow-hidden">
                            <p class="text-xs text-gray-500 font-bold uppercase">Email</p>
                            <p id="cardEmail" class="text-gray-800 font-medium truncate text-base">---</p>
                        </div>
                    </div>
                </div>

                <div class="mt-8 space-y-6">
                    <div>
                        <div class="flex items-center gap-2 mb-3">
                            <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"></path></svg>
                            <h3 class="text-lg font-bold text-gray-800 uppercase tracking-tight">Skills & Expertise</h3>
                        </div>
                        <div id="cardSkills" class="flex flex-wrap gap-2">
                            <!-- Skills will go here -->
                        </div>
                    </div>

                    <div>
                        <div class="flex items-center gap-2 mb-3">
                            <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l9-5-9-5-9 5 9 5zm0 0l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14z"></path></svg>
                            <h3 class="text-lg font-bold text-gray-800 uppercase tracking-tight">Education Background</h3>
                        </div>
                        <div id="cardEducation" class="p-4 bg-indigo-50 bg-opacity-50 rounded-2xl text-gray-700 leading-relaxed italic">
                            ---
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // ... (previous variables)
        const resultContainer = document.getElementById('resultContainer');
        const cardName = document.getElementById('cardName');
        const cardTitle = document.getElementById('cardTitle');
        const cardPhone = document.getElementById('cardPhone');
        const cardEmail = document.getElementById('cardEmail');
        const cardSkills = document.getElementById('cardSkills');
        const cardEducation = document.getElementById('cardEducation');
        const progressBar = document.getElementById('progressBar');
        const progressPercentage = document.getElementById('progressPercentage');
        const errorAlert = document.getElementById('errorAlert');

        let progressInterval;

        function startProgress() {
            let progress = 0;
            progressBar.style.width = '0%';
            progressPercentage.textContent = '0%';
            
            clearInterval(progressInterval);
            progressInterval = setInterval(() => {
                if (progress < 95) {
                    progress += Math.random() * 3; // Natural feel
                    if (progress > 95) progress = 95;
                    const displayProgress = Math.floor(progress);
                    progressBar.style.width = displayProgress + '%';
                    progressPercentage.textContent = displayProgress + '%';
                }
            }, 500);
        }

        function stopProgress(success = true) {
            clearInterval(progressInterval);
            if (success) {
                progressBar.style.width = '100%';
                progressPercentage.textContent = '100%';
            } else {
                progressBar.style.width = '0%';
                progressPercentage.textContent = '0%';
            }
        }

        // Handle form submission
        uploadForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const file = imageInput.files[0];
            if (!file) return;

            // Reset UI
            errorAlert.classList.add('hidden');
            resultContainer.classList.add('hidden');
            loadingState.classList.remove('hidden');
            loadingState.classList.add('flex');
            submitBtn.disabled = true;

            startProgress();

            const formData = new FormData();
            formData.append('file', file);

            try {
                const response = await fetch('/upload-image', {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                    body: formData
                });

                const data = await response.json();

                if (response.ok && data.success) {
                    stopProgress(true);
                    setTimeout(() => parseAndShowResult(data.text), 500);
                } else {
                    stopProgress(false);
                    showError(data.message || 'An unknown error occurred. Please try again.');
                }
            } catch (error) {
                stopProgress(false);
                console.error('Upload Error:', error);
                showError('Server is taking too long or connection lost. Please check your internet and try again.');
            } finally {
                setTimeout(() => {
                    loadingState.classList.add('hidden');
                    loadingState.classList.remove('flex');
                    submitBtn.disabled = false;
                }, 1000);
            }
        });

        function parseAndShowResult(text) {
            const lines = text.split('\n');
            const data = {};
            
            lines.forEach(line => {
                const [key, ...valueParts] = line.split(':');
                if (key && valueParts.length > 0) {
                    data[key.trim()] = valueParts.join(':').trim();
                }
            });

            cardName.textContent = data.NAME || 'Not found';
            cardTitle.textContent = data.TITLE || 'Not found';
            cardPhone.textContent = data.PHONE || 'Not found';
            cardEmail.textContent = data.EMAIL || 'Not found';
            
            // Skills tags
            cardSkills.innerHTML = '';
            const skills = (data.SKILLS || 'Not found').split(',');
            skills.forEach(skill => {
                const span = document.createElement('span');
                span.className = 'px-3 py-1 bg-indigo-100 text-indigo-700 text-sm font-semibold rounded-full border border-indigo-200';
                span.textContent = skill.trim();
                cardSkills.appendChild(span);
            });

            cardEducation.textContent = data.EDUCATION || 'Not found';
            
            resultContainer.classList.remove('hidden');
        }

        function showError(msg) {
            errorMessage.textContent = msg;
            errorAlert.classList.remove('hidden');
        }

        // Previous functions remain same
        function showPreview(file) {
            if (!file) return;
            uploadPlaceholder.classList.add('hidden');
            imagePreviewContainer.classList.remove('hidden');
            if (file.type.startsWith('image/')) {
                const reader = new FileReader();
                reader.onload = function(e) { imagePreview.src = e.target.result; }
                reader.readAsDataURL(file);
            } else if (file.type === 'application/pdf') {
                imagePreview.src = 'https://cdn-icons-png.flaticon.com/512/337/337946.png';
            } else if (file.type === 'text/plain') {
                imagePreview.src = 'https://cdn-icons-png.flaticon.com/512/337/337960.png';
            }
        }
    </script>
</body>
</html>
