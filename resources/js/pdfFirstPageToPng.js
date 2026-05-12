import * as pdfjsLib from 'pdfjs-dist';
import pdfWorkerSrc from 'pdfjs-dist/build/pdf.worker.min.mjs?url';

pdfjsLib.GlobalWorkerOptions.workerSrc = pdfWorkerSrc;

export function isPdfFile(file) {
    return file.type === 'application/pdf' || /\.pdf$/i.test(file.name);
}

const MAX_CANVAS_WIDTH = 1800;

export async function pdfFileToPngFile(pdfFile) {
    const arrayBuffer = await pdfFile.arrayBuffer();
    const data = new Uint8Array(arrayBuffer);
    const version = pdfjsLib.version;

    let pdf = null;
    try {
        const loadingTask = pdfjsLib.getDocument({
            data,
            useSystemFonts: true,
            standardFontDataUrl: `https://cdn.jsdelivr.net/npm/pdfjs-dist@${version}/standard_fonts/`,
            cMapUrl: `https://cdn.jsdelivr.net/npm/pdfjs-dist@${version}/cmaps/`,
            cMapPacked: true,
        });

        pdf = await loadingTask.promise;
        const page = await pdf.getPage(1);
        const baseViewport = page.getViewport({ scale: 1 });
        const scale = Math.min(2.25, MAX_CANVAS_WIDTH / Math.max(baseViewport.width, 1));
        const viewport = page.getViewport({ scale });

        const canvas = document.createElement('canvas');
        const ctx = canvas.getContext('2d', { alpha: false });
        const w = Math.ceil(viewport.width);
        const h = Math.ceil(viewport.height);
        if (w < 8 || h < 8) {
            throw new Error('This PDF page has invalid dimensions.');
        }
        canvas.width = w;
        canvas.height = h;
        ctx.fillStyle = '#ffffff';
        ctx.fillRect(0, 0, w, h);

        const renderTask = page.render({
            canvasContext: ctx,
            viewport,
            intent: 'display',
        });

        if (renderTask?.promise) {
            await renderTask.promise;
        } else {
            await renderTask;
        }

        const blob = await new Promise((resolve) => {
            canvas.toBlob((b) => resolve(b), 'image/jpeg', 0.88);
        });
        if (!blob || blob.size < 800) {
            throw new Error(
                'This PDF did not render to a usable image (empty or too small). Try exporting the prescription as PNG/JPEG, or another PDF.'
            );
        }

        const baseName = pdfFile.name.replace(/\.pdf$/i, '') || 'prescription';

        return new File([blob], `${baseName}-page1.jpg`, { type: 'image/jpeg' });
    } finally {
        if (pdf) {
            try {
                await pdf.destroy();
            } catch {
                //
            }
        }
    }
}
