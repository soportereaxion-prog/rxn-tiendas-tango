// RXN PWA — Compresión de imágenes en cliente.
//
// Reglas (acordadas con Charly Iteración 42 — Fase 2):
//   - Aplica SOLO a imágenes (jpeg, png, webp). PDF/Office van crudos.
//   - Max lado largo: 1600px (mantiene proporción).
//   - Calidad: 0.80 (JPEG/WebP).
//   - Convierte PNG a JPEG SOLO si la PNG no tiene transparencia. Si tiene canal alfa, mantiene PNG.
//   - Si la imagen original ya pesa menos que el resultado, devuelve la original (no perder calidad).
//
// API: RxnPwaImageCompressor.compress(file, opts) → Promise<{blob, name, mime, originalSize, compressedSize}>

(function (global) {
    'use strict';

    const MAX_LONG_SIDE = 1600;
    const QUALITY = 0.80;
    const IMAGE_MIMES = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];

    function isImage(file) {
        return IMAGE_MIMES.includes((file.type || '').toLowerCase());
    }

    async function compress(file, opts = {}) {
        const maxSide = opts.maxLongSide || MAX_LONG_SIDE;
        const quality = opts.quality || QUALITY;

        if (!isImage(file)) {
            return {
                blob: file,
                name: file.name,
                mime: file.type,
                originalSize: file.size,
                compressedSize: file.size,
                compressed: false,
            };
        }

        const bitmap = await loadBitmap(file);
        const { width, height } = bitmap;
        const scale = Math.min(1, maxSide / Math.max(width, height));
        const targetW = Math.round(width * scale);
        const targetH = Math.round(height * scale);

        const canvas = document.createElement('canvas');
        canvas.width = targetW;
        canvas.height = targetH;
        const ctx = canvas.getContext('2d');
        // Mejor calidad de resampling
        ctx.imageSmoothingEnabled = true;
        ctx.imageSmoothingQuality = 'high';
        ctx.drawImage(bitmap, 0, 0, targetW, targetH);

        const hasAlpha = (file.type || '').includes('png') && (await pngHasAlpha(canvas));
        const targetMime = hasAlpha ? 'image/png' : 'image/jpeg';
        const targetExt = hasAlpha ? '.png' : '.jpg';

        const blob = await canvasToBlob(canvas, targetMime, quality);

        // Si la "compresión" terminó pesando más (común en imágenes ya optimizadas
        // o muy chicas), devolvemos la original — la idea es no perder calidad por gusto.
        if (blob.size >= file.size && scale >= 1) {
            return {
                blob: file,
                name: file.name,
                mime: file.type,
                originalSize: file.size,
                compressedSize: file.size,
                compressed: false,
            };
        }

        const baseName = file.name.replace(/\.[a-z0-9]+$/i, '') || 'foto';
        return {
            blob,
            name: baseName + targetExt,
            mime: targetMime,
            originalSize: file.size,
            compressedSize: blob.size,
            compressed: true,
        };
    }

    function loadBitmap(file) {
        if ('createImageBitmap' in global) {
            return global.createImageBitmap(file);
        }
        // Fallback Image element.
        return new Promise((resolve, reject) => {
            const img = new Image();
            const url = URL.createObjectURL(file);
            img.onload = () => {
                URL.revokeObjectURL(url);
                resolve(img);
            };
            img.onerror = (err) => {
                URL.revokeObjectURL(url);
                reject(err);
            };
            img.src = url;
        });
    }

    async function pngHasAlpha(canvas) {
        // Sample rápido: 1 pixel de las 4 esquinas + centro. Si alguno tiene alpha < 255, asumimos PNG con transparencia.
        const ctx = canvas.getContext('2d');
        const points = [
            [0, 0],
            [canvas.width - 1, 0],
            [0, canvas.height - 1],
            [canvas.width - 1, canvas.height - 1],
            [Math.floor(canvas.width / 2), Math.floor(canvas.height / 2)],
        ];
        for (const [x, y] of points) {
            const data = ctx.getImageData(x, y, 1, 1).data;
            if (data[3] < 255) return true;
        }
        return false;
    }

    function canvasToBlob(canvas, mime, quality) {
        return new Promise((resolve, reject) => {
            canvas.toBlob((blob) => {
                if (blob) resolve(blob);
                else reject(new Error('canvas.toBlob falló (' + mime + ')'));
            }, mime, quality);
        });
    }

    global.RxnPwaImageCompressor = { compress, isImage };
})(window);
