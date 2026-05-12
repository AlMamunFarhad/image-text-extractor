import { isPdfFile, pdfFileToPngFile } from './pdfFirstPageToPng';

const KEYS = [
    'left_sph',
    'left_cyl',
    'left_pd',
    'left_ax',
    'right_sph',
    'right_cyl',
    'right_pd',
    'right_ax',
];

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
}

async function readJsonResponse(response) {
    const text = await response.text();
    const t = text.trim();
    if (t.startsWith('<')) {
        throw new Error(
            'The server returned a web page instead of JSON (often CSRF/session or a PHP error). Refresh the page and try again.'
        );
    }
    try {
        return JSON.parse(text);
    } catch {
        throw new Error('The server response was not valid JSON.');
    }
}

function formatFileSize(bytes) {
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1048576) return `${(bytes / 1024).toFixed(1)} KB`;
    return `${(bytes / 1048576).toFixed(1)} MB`;
}

function displayCell(el, value) {
    if (value === null || value === undefined || value === '') {
        el.textContent = '—';
        el.classList.add('text-slate-400');
    } else {
        el.textContent = String(value);
        el.classList.remove('text-slate-400');
    }
}

function renderResults(resultContainer, text) {
    resultContainer.classList.remove('hidden');
    if (text === null || text === undefined || String(text).trim() === '') {
        document.getElementById('rawJson').textContent = '(empty response)';
        KEYS.forEach((k) => displayCell(document.getElementById(k), null));
        return;
    }
    let obj;
    try {
        obj = typeof text === 'string' ? JSON.parse(text) : text;
    } catch {
        document.getElementById('rawJson').textContent = String(text);
        KEYS.forEach((k) => displayCell(document.getElementById(k), null));
        return;
    }

    KEYS.forEach((k) =>
        displayCell(
            document.getElementById(k),
            Object.prototype.hasOwnProperty.call(obj, k) ? obj[k] : null
        )
    );
    document.getElementById('rawJson').textContent = JSON.stringify(obj, null, 2);
}

document.addEventListener('DOMContentLoaded', () => {
    const uploadForm = document.getElementById('uploadForm');
    const imageInput = document.getElementById('imageInput');
    const dropZone = document.getElementById('dropZone');
    const loadingState = document.getElementById('loadingState');
    const loadingTitle = document.getElementById('loadingTitle');
    const resultContainer = document.getElementById('resultContainer');
    const progressBar = document.getElementById('progressBar');
    const selectedFileRow = document.getElementById('selectedFileRow');
    const selectedFileName = document.getElementById('selectedFileName');
    const selectedFileMeta = document.getElementById('selectedFileMeta');
    const loadingFileName = document.getElementById('loadingFileName');

    if (!uploadForm || !imageInput) return;

    function setSelectedFileDisplay(file) {
        if (!file) {
            selectedFileRow.classList.add('hidden');
            selectedFileName.textContent = '';
            selectedFileName.removeAttribute('title');
            selectedFileMeta.textContent = '';
            return;
        }
        selectedFileName.textContent = file.name;
        selectedFileName.title = file.name;
        selectedFileMeta.textContent = formatFileSize(file.size);
        selectedFileRow.classList.remove('hidden');
    }

    imageInput.addEventListener('change', () => {
        setSelectedFileDisplay(imageInput.files[0] || null);
    });

    if (dropZone) {
        dropZone.addEventListener('dragover', (e) => {
            e.preventDefault();
            dropZone.classList.add('border-indigo-400', 'bg-indigo-50/50');
        });
        dropZone.addEventListener('dragleave', () => {
            dropZone.classList.remove('border-indigo-400', 'bg-indigo-50/50');
        });
        dropZone.addEventListener('drop', (e) => {
            e.preventDefault();
            dropZone.classList.remove('border-indigo-400', 'bg-indigo-50/50');
            const f = e.dataTransfer.files[0];
            if (!f) return;
            const okType = /^image\//i.test(f.type) || f.type === 'application/pdf';
            const okName = /\.(jpe?g|png|gif|webp|bmp|tiff?|heic|heif|pdf)$/i.test(f.name);
            if (!okType && !okName) return;
            const dt = new DataTransfer();
            dt.items.add(f);
            imageInput.files = dt.files;
            setSelectedFileDisplay(f);
        });

        dropZone.addEventListener('click', () => imageInput.click());
    }

    async function pollStatus(id, interval, progressState, attempt = 0) {
        const maxAttempts = 45;
        if (attempt >= maxAttempts) {
            clearInterval(interval);
            progressBar.style.width = '0%';
            loadingState.classList.add('hidden');
            loadingFileName.textContent = '';
            loadingFileName.removeAttribute('title');
            if (loadingTitle) loadingTitle.textContent = 'Reading prescription…';
            const errorAlert = document.getElementById('errorAlert');
            const errorMessage = document.getElementById('errorMessage');
            errorMessage.textContent =
                'Processing is taking too long or status checks failed. Refresh the page and try again.';
            errorAlert.classList.remove('hidden');
            return;
        }

        let data;
        try {
            const response = await fetch(`/scan-status/${id}`, {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });
            data = await readJsonResponse(response);
        } catch (err) {
            progressState.value = Math.min(95, progressState.value + 2);
            progressBar.style.width = `${progressState.value}%`;
            setTimeout(() => pollStatus(id, interval, progressState, attempt + 1), 2000);
            console.error('pollStatus', err);
            return;
        }

        if (data.status === 'completed') {
            clearInterval(interval);
            progressState.value = 100;
            progressBar.style.width = '100%';
            setTimeout(() => {
                loadingState.classList.add('hidden');
                loadingFileName.textContent = '';
                loadingFileName.removeAttribute('title');
                if (loadingTitle) loadingTitle.textContent = 'Reading prescription…';
                progressBar.style.width = '0%';
                renderResults(resultContainer, data.result);
            }, 500);
        } else if (data.status === 'failed') {
            clearInterval(interval);
            progressBar.style.width = '0%';
            loadingState.classList.add('hidden');
            loadingFileName.textContent = '';
            loadingFileName.removeAttribute('title');
            if (loadingTitle) loadingTitle.textContent = 'Reading prescription…';

            const errorAlert = document.getElementById('errorAlert');
            const errorMessage = document.getElementById('errorMessage');
            errorMessage.textContent =
                data.error || 'The system failed to process the document. Please try again.';
            errorAlert.classList.remove('hidden');

            setTimeout(() => {
                errorAlert.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }, 100);
        } else {
            progressState.value = Math.min(96, progressState.value + 4);
            progressBar.style.width = `${progressState.value}%`;
            setTimeout(() => pollStatus(id, interval, progressState, attempt + 1), 2000);
        }
    }

    uploadForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        const file = imageInput.files[0];
        if (!file) return;

        resultContainer.classList.add('hidden');
        const errorAlert = document.getElementById('errorAlert');
        if (errorAlert) errorAlert.classList.add('hidden');
        loadingState.classList.remove('hidden');
        loadingFileName.textContent = file.name;
        loadingFileName.title = file.name;
        progressBar.style.width = '0%';

        const progressState = { value: 0 };
        let mainInterval = null;

        function clearMainInterval() {
            if (mainInterval !== null) {
                clearInterval(mainInterval);
                mainInterval = null;
            }
        }

        function startMainProgressTick() {
            clearMainInterval();
            mainInterval = setInterval(() => {
                if (progressState.value < 82) {
                    progressState.value = Math.min(82, progressState.value + 2);
                }
                progressBar.style.width = `${progressState.value}%`;
            }, 420);
        }

        let uploadFile = file;
        if (isPdfFile(file)) {
            if (loadingTitle) loadingTitle.textContent = 'Converting PDF (first page)…';
            const pdfPhase = { value: 0 };
            const pdfInterval = setInterval(() => {
                if (pdfPhase.value < 32) {
                    pdfPhase.value += 1;
                    progressBar.style.width = `${pdfPhase.value}%`;
                }
            }, 220);
            try {
                uploadFile = await pdfFileToPngFile(file);
            } catch (err) {
                clearInterval(pdfInterval);
                clearMainInterval();
                progressBar.style.width = '0%';
                loadingState.classList.add('hidden');
                loadingFileName.textContent = '';
                loadingFileName.removeAttribute('title');
                if (loadingTitle) loadingTitle.textContent = 'Reading prescription…';
                const errorAlert = document.getElementById('errorAlert');
                const errorMessage = document.getElementById('errorMessage');
                errorMessage.textContent =
                    err?.message ||
                    'Could not read this PDF in the browser. Try another file or export as PNG/JPEG.';
                errorAlert.classList.remove('hidden');
                console.error(err);
                return;
            }
            clearInterval(pdfInterval);
            progressState.value = Math.max(progressState.value, pdfPhase.value, 28);
            progressBar.style.width = `${progressState.value}%`;
            if (loadingTitle) loadingTitle.textContent = 'Reading prescription…';
        }

        startMainProgressTick();

        const formData = new FormData();
        formData.append('file', uploadFile);

        try {
            const response = await fetch('/upload-image', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': csrfToken(),
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: formData,
            });
            const data = await readJsonResponse(response);
            if (data.success) {
                progressState.value = Math.max(progressState.value, 78);
                progressBar.style.width = `${progressState.value}%`;
                pollStatus(data.id, mainInterval, progressState);
            } else {
                throw new Error(data.message || 'Upload failed');
            }
        } catch (error) {
            clearMainInterval();
            progressBar.style.width = '0%';
            loadingState.classList.add('hidden');
            loadingFileName.textContent = '';
            loadingFileName.removeAttribute('title');
            if (loadingTitle) loadingTitle.textContent = 'Reading prescription…';

            const errorAlert = document.getElementById('errorAlert');
            const errorMessage = document.getElementById('errorMessage');
            errorMessage.textContent =
                error.message ||
                'The system failed to connect. Please check your internet or server.';
            errorAlert.classList.remove('hidden');

            console.error(error);
        }
    });
});
