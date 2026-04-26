<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vision Scanner | Professional Document Extraction</title>
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

    <div class="w-full max-w-2xl">
        <div class="text-center mb-10">
            <h1 class="text-4xl font-extrabold text-slate-900 mb-2">Vision<span class="text-indigo-600">Scanner</span></h1>
            <p class="text-slate-500 font-medium uppercase tracking-widest text-xs">Professional Data Extraction System</p>
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
                        <h2 class="text-xl font-bold text-slate-800">Choose a document</h2>
                        <p class="text-slate-400 text-sm mt-1">Images or PDFs up to 10MB</p>
                    </div>
                </div>
                <button type="submit" id="submitBtn" class="w-full py-4 bg-indigo-600 text-white font-bold rounded-2xl shadow-lg hover:bg-indigo-700 transition-all">
                    Process Document
                </button>
            </form>

            <div id="loadingState" class="hidden mt-10 flex flex-col items-center">
                <div class="loader mb-4"></div>
                <h3 class="text-lg font-bold text-slate-800">Processing Document...</h3>
                <div class="w-full bg-slate-100 rounded-full h-2 mt-8 overflow-hidden">
                    <div id="progressBar" class="bg-indigo-600 h-full w-0 transition-all duration-300"></div>
                </div>
            </div>

            <div id="resultContainer" class="hidden mt-10 pt-10 border-t border-slate-100 space-y-8">
                <div class="bg-indigo-50 p-6 rounded-[32px] border border-indigo-100 flex items-center gap-5">
                    <div>
                        <h3 id="cardName" class="text-2xl font-extrabold text-slate-900">---</h3>
                        <p id="cardTitle" class="text-indigo-600 font-bold">---</p>
                    </div>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="p-4 bg-slate-50 rounded-2xl">
                        <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1">Phone</p>
                        <p id="cardPhone" class="text-slate-800 font-bold">---</p>
                    </div>
                    <div class="p-4 bg-slate-50 rounded-2xl">
                        <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1">Email</p>
                        <p id="cardEmail" class="text-slate-800 font-bold">---</p>
                    </div>
                </div>
                <div>
                    <h4 class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-3">Skills</h4>
                    <div id="cardSkills" class="flex flex-wrap gap-2"></div>
                </div>
                <div>
                    <h4 class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-3">Education</h4>
                    <div id="cardEducation" class="p-5 bg-indigo-50/30 rounded-2xl text-slate-700 font-medium text-sm">---</div>
                </div>
            </div>
        </div>
    </div>

    <script>
        const uploadForm = document.getElementById('uploadForm');
        const imageInput = document.getElementById('imageInput');
        const dropZone = document.getElementById('dropZone');
        const loadingState = document.getElementById('loadingState');
        const resultContainer = document.getElementById('resultContainer');
        const progressBar = document.getElementById('progressBar');

        const cardName = document.getElementById('cardName');
        const cardTitle = document.getElementById('cardTitle');
        const cardPhone = document.getElementById('cardPhone');
        const cardEmail = document.getElementById('cardEmail');
        const cardSkills = document.getElementById('cardSkills');
        const cardEducation = document.getElementById('cardEducation');

        dropZone.addEventListener('click', () => imageInput.click());

        uploadForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            const file = imageInput.files[0];
            if (!file) return;

            resultContainer.classList.add('hidden');
            loadingState.classList.remove('hidden');
            
            let progress = 0;
            const interval = setInterval(() => {
                if (progress < 90) {
                    progress += 5;
                    progressBar.style.width = progress + '%';
                }
            }, 500);

            const formData = new FormData();
            formData.append('file', file);

            try {
                const response = await fetch('/upload-image', {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                    body: formData
                });
                const data = await response.json();
                if (data.success) {
                    pollStatus(data.id, interval);
                } else {
                    throw new Error(data.message || 'Upload failed');
                }
            } catch (error) { 
                clearInterval(interval);
                loadingState.classList.add('hidden');
                
                const errorAlert = document.getElementById('errorAlert');
                const errorMessage = document.getElementById('errorMessage');
                errorMessage.textContent = error.message || 'The system failed to connect. Please check your internet or server.';
                errorAlert.classList.remove('hidden');
                
                console.error(error); 
            }
        });

        async function pollStatus(id, interval) {
            const response = await fetch(`/scan-status/${id}`);
            const data = await response.json();

            if (data.status === 'completed') {
                clearInterval(interval);
                progressBar.style.width = '100%';
                setTimeout(() => {
                    loadingState.classList.add('hidden');
                    renderResults(data.result);
                }, 500);
            } else if (data.status === 'failed') {
                clearInterval(interval);
                loadingState.classList.add('hidden');
                
                // Show a beautiful error message
                const errorAlert = document.getElementById('errorAlert');
                const errorMessage = document.getElementById('errorMessage');
                errorMessage.textContent = data.error || 'The system failed to process the document. Please try again.';
                errorAlert.classList.remove('hidden');
                
                setTimeout(() => {
                    errorAlert.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }, 100);
            } else {
                setTimeout(() => pollStatus(id, interval), 2000);
            }
        }

        function renderResults(text) {
            resultContainer.classList.remove('hidden');
            const lines = text.split('\n');
            const data = { NAME: 'Not found', TITLE: 'Not found', PHONE: 'Not found', EMAIL: 'Not found', SKILLS: '', EDUCATION: '' };
            let currentKey = null;

            lines.forEach(line => {
                const cleanLine = line.replace(/\*/g, '').trim();
                if (!cleanLine) return;
                const colonIdx = cleanLine.indexOf(':');
                if (colonIdx !== -1 && colonIdx < 15) {
                    const keyCandidate = cleanLine.substring(0, colonIdx).trim().toUpperCase();
                    if (['NAME', 'TITLE', 'PHONE', 'EMAIL', 'SKILLS', 'EDUCATION'].includes(keyCandidate)) {
                        currentKey = keyCandidate;
                        data[currentKey] = cleanLine.substring(colonIdx + 1).trim();
                        return;
                    }
                }
                if (currentKey && ['SKILLS', 'EDUCATION'].includes(currentKey)) {
                    data[currentKey] += (data[currentKey] ? ' ' : '') + cleanLine;
                }
            });

            cardName.textContent = data.NAME;
            cardTitle.textContent = data.TITLE;
            cardPhone.textContent = data.PHONE;
            cardEmail.textContent = data.EMAIL;
            cardEducation.textContent = data.EDUCATION;
            
            cardSkills.innerHTML = '';
            const skills = data.SKILLS.split(/[,|]/);
            skills.forEach(skill => {
                const s = skill.trim();
                if (s && s !== 'Not found') {
                    const span = document.createElement('span');
                    span.className = 'px-3 py-1 bg-white border border-slate-200 text-indigo-600 rounded-2xl text-xs font-bold';
                    span.textContent = s;
                    cardSkills.appendChild(span);
                }
            });
            if (!cardSkills.innerHTML) cardSkills.innerHTML = '<span class="text-slate-400 text-xs italic">No skills identified</span>';
        }
    </script>
</body>
</html>
