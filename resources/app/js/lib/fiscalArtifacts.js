import api from './api';

const DEFAULT_OBJECT_URL_TTL_MS = 5 * 60 * 1000;
const DEFAULT_PRINT_TIMEOUT_MS = 15 * 1000;

function isBrowserEnvironment() {
    return typeof window !== 'undefined' && typeof document !== 'undefined';
}

function sanitizeFileName(value, fallbackBaseName, extension) {
    const rawValue = String(value || '').trim();
    const normalized = rawValue
        .replace(/[\\/:*?"<>|]+/g, '-')
        .replace(/\s+/g, ' ')
        .trim();

    const fallback = extension ? `${fallbackBaseName}.${extension}` : fallbackBaseName;
    if (!normalized) return fallback;

    const lowerExtension = extension ? `.${String(extension).toLowerCase()}` : '';
    if (!lowerExtension || normalized.toLowerCase().endsWith(lowerExtension)) {
        return normalized;
    }

    return `${normalized}.${extension}`;
}

function parseContentDispositionFileName(headerValue) {
    const value = String(headerValue || '');
    if (!value) return '';

    const utfMatch = value.match(/filename\*=UTF-8''([^;]+)/i);
    if (utfMatch?.[1]) {
        try {
            return decodeURIComponent(utfMatch[1]);
        } catch {
            return utfMatch[1];
        }
    }

    const plainMatch = value.match(/filename="?([^";]+)"?/i);
    return plainMatch?.[1] ? plainMatch[1].trim() : '';
}

function fileNameFromUrl(url) {
    try {
        const normalizedUrl = new URL(String(url), window.location.origin);
        const lastSegment = normalizedUrl.pathname.split('/').filter(Boolean).pop() || '';
        return lastSegment.trim();
    } catch {
        return '';
    }
}

function stripApiBasePath(path) {
    const basePath = String(api.defaults.baseURL || '').replace(/\/+$/, '');
    const normalizedPath = String(path || '');

    if (!basePath || !basePath.startsWith('/')) return normalizedPath;
    if (normalizedPath === basePath) return '/';
    if (normalizedPath.startsWith(`${basePath}/`)) {
        return normalizedPath.slice(basePath.length) || '/';
    }

    return normalizedPath;
}

function normalizeArtifactUrl(url) {
    const rawUrl = String(url || '').trim();
    if (!rawUrl || !isBrowserEnvironment()) return rawUrl;

    try {
        const parsedUrl = new URL(rawUrl, window.location.origin);
        if (parsedUrl.host === window.location.host) {
            return `${stripApiBasePath(parsedUrl.pathname)}${parsedUrl.search}${parsedUrl.hash}`;
        }
    } catch {
        return rawUrl;
    }

    return rawUrl;
}

function resolveFileName(url, headers, fallbackBaseName, extension) {
    const headerFileName = parseContentDispositionFileName(headers?.['content-disposition'] || headers?.['Content-Disposition']);
    const urlFileName = fileNameFromUrl(url);

    return sanitizeFileName(
        headerFileName || urlFileName,
        fallbackBaseName,
        extension,
    );
}

function scheduleObjectUrlCleanup(objectUrl, delayMs = DEFAULT_OBJECT_URL_TTL_MS) {
    if (!objectUrl || !isBrowserEnvironment()) return;

    window.setTimeout(() => {
        URL.revokeObjectURL(objectUrl);
    }, delayMs);
}

function renderLoadingDocument(targetWindow, title) {
    if (!targetWindow || targetWindow.closed) return;

    targetWindow.document.write(`<!doctype html>
<html lang="pt-BR">
    <head>
        <meta charset="utf-8" />
        <title>${title}</title>
        <style>
            :root { color-scheme: light; }
            body {
                margin: 0;
                min-height: 100vh;
                display: grid;
                place-items: center;
                font-family: system-ui, sans-serif;
                background: #f3f4f6;
                color: #111827;
            }
            main {
                width: min(28rem, calc(100vw - 3rem));
                border-radius: 18px;
                background: #ffffff;
                border: 1px solid #d1d5db;
                box-shadow: 0 18px 45px rgba(15, 23, 42, 0.12);
                padding: 1.5rem;
            }
            h1 {
                margin: 0 0 0.5rem;
                font-size: 1.15rem;
            }
            p {
                margin: 0;
                color: #4b5563;
                line-height: 1.55;
            }
        </style>
    </head>
    <body>
        <main>
            <h1>${title}</h1>
            <p>Preparando o documento fiscal autenticado...</p>
        </main>
    </body>
</html>`);
    targetWindow.document.close();
}

async function resolveBlobErrorMessage(blob) {
    if (!(blob instanceof Blob)) return '';

    try {
        const text = await blob.text();
        if (!text) return '';

        try {
            const payload = JSON.parse(text);
            const errors = payload?.errors ? Object.values(payload.errors).flat().filter(Boolean) : [];

            return payload?.message || errors.join(' ') || text;
        } catch {
            return text;
        }
    } catch {
        return '';
    }
}

async function resolveRequestErrorMessage(error, fallbackMessage) {
    const blobMessage = await resolveBlobErrorMessage(error?.response?.data);
    if (blobMessage) return blobMessage;

    const validationErrors = error?.response?.data?.errors
        ? Object.values(error.response.data.errors).flat().filter(Boolean)
        : [];

    return error?.response?.data?.message
        || validationErrors.join(' ')
        || error?.message
        || fallbackMessage;
}

async function fetchArtifactBlob(url, {
    accept,
    fallbackBaseName = 'documento-fiscal',
    extension = 'pdf',
} = {}) {
    const requestUrl = normalizeArtifactUrl(url);
    const response = await api.get(requestUrl, {
        responseType: 'blob',
        headers: accept ? { Accept: accept } : undefined,
    });

    return {
        blob: response.data,
        fileName: resolveFileName(requestUrl, response.headers, fallbackBaseName, extension),
    };
}

function successResult(message, extra = {}) {
    return {
        success: true,
        blocked: false,
        message,
        ...extra,
    };
}

function failureResult(message, blocked = false, extra = {}) {
    return {
        success: false,
        blocked,
        message,
        ...extra,
    };
}

export async function openFiscalPdf(url, options = {}) {
    if (!isBrowserEnvironment()) {
        return failureResult('Visualização fiscal indisponível neste ambiente.');
    }

    const normalizedUrl = normalizeArtifactUrl(url);
    if (!normalizedUrl) {
        return failureResult('PDF fiscal indisponível para visualização.');
    }

    const viewer = window.open('', '_blank');
    if (!viewer) {
        return failureResult('O navegador bloqueou a abertura do documento fiscal.', true);
    }

    renderLoadingDocument(viewer, 'Abrindo documento fiscal');

    try {
        const { blob, fileName } = await fetchArtifactBlob(normalizedUrl, {
            accept: 'application/pdf',
            fallbackBaseName: options.fallbackBaseName || 'documento-fiscal',
            extension: 'pdf',
        });

        const objectUrl = URL.createObjectURL(blob);
        scheduleObjectUrlCleanup(objectUrl);
        viewer.location.replace(objectUrl);

        return successResult('', { fileName });
    } catch (error) {
        if (!viewer.closed) {
            viewer.close();
        }

        return failureResult(
            await resolveRequestErrorMessage(error, 'Não foi possível abrir o documento fiscal.'),
            false,
        );
    }
}

export async function downloadFiscalArtifact(url, options = {}) {
    if (!isBrowserEnvironment()) {
        return failureResult('Download fiscal indisponível neste ambiente.');
    }

    const normalizedUrl = normalizeArtifactUrl(url);
    if (!normalizedUrl) {
        return failureResult('Arquivo fiscal indisponível para download.');
    }

    try {
        const { blob, fileName } = await fetchArtifactBlob(normalizedUrl, {
            accept: options.accept || '*/*',
            fallbackBaseName: options.fallbackBaseName || 'documento-fiscal',
            extension: options.extension || 'xml',
        });

        const objectUrl = URL.createObjectURL(blob);
        const anchor = document.createElement('a');

        anchor.href = objectUrl;
        anchor.download = fileName;
        anchor.rel = 'noopener';
        anchor.style.display = 'none';

        document.body.appendChild(anchor);
        anchor.click();
        anchor.remove();

        scheduleObjectUrlCleanup(objectUrl, 30 * 1000);

        return successResult(
            options.successMessage || 'Arquivo fiscal preparado para download.',
            { fileName },
        );
    } catch (error) {
        return failureResult(
            await resolveRequestErrorMessage(error, 'Não foi possível baixar o arquivo fiscal.'),
            false,
        );
    }
}

async function printPdfBlob(blob, fileName, options = {}) {
    if (!isBrowserEnvironment()) {
        return failureResult('Impressão fiscal indisponível neste ambiente.');
    }

    if (!(blob instanceof Blob)) {
        return failureResult('PDF fiscal indisponível para impressão.');
    }

    const objectUrl = URL.createObjectURL(blob);

    return await new Promise((resolve) => {
        const iframe = document.createElement('iframe');
        let finished = false;
        let printRequested = false;

        const printDelayMs = Math.max(250, Number(options.printDelayMs || 500));

        const finish = (result) => {
            if (finished) return;
            finished = true;

            window.setTimeout(() => {
                iframe.remove();
                URL.revokeObjectURL(objectUrl);
            }, 60 * 1000);

            resolve(result);
        };

        const timeoutId = window.setTimeout(() => {
            finish(failureResult(
                'O navegador não concluiu a preparação da impressão fiscal.',
                true,
                { fileName },
            ));
        }, options.timeoutMs || DEFAULT_PRINT_TIMEOUT_MS);

        iframe.setAttribute('aria-hidden', 'true');
        iframe.style.position = 'fixed';
        iframe.style.right = '0';
        iframe.style.bottom = '0';
        iframe.style.width = '0';
        iframe.style.height = '0';
        iframe.style.border = '0';
        iframe.style.opacity = '0';
        iframe.style.pointerEvents = 'none';

        iframe.onload = () => {
            if (printRequested) return;

            try {
                const targetWindow = iframe.contentWindow;
                const currentHref = String(targetWindow?.location?.href || '');

                if (!currentHref || currentHref === 'about:blank') {
                    return;
                }

                if (!targetWindow || typeof targetWindow.print !== 'function') {
                    clearTimeout(timeoutId);
                    finish(failureResult(
                        'O navegador não disponibilizou a impressão do PDF fiscal.',
                        true,
                        { fileName },
                    ));
                    return;
                }

                printRequested = true;

                window.setTimeout(() => {
                    clearTimeout(timeoutId);

                    try {
                        targetWindow.focus?.();
                        targetWindow.print();

                        finish(successResult(
                            options.successMessage || 'Impressão fiscal enviada ao navegador.',
                            { fileName },
                        ));
                    } catch (error) {
                        finish(failureResult(
                            error?.message || 'Não foi possível acionar a impressão fiscal.',
                            true,
                            { fileName },
                        ));
                    }
                }, printDelayMs);
            } catch (error) {
                clearTimeout(timeoutId);
                finish(failureResult(
                    error?.message || 'Não foi possível acionar a impressão fiscal.',
                    true,
                    { fileName },
                ));
            }
        };

        iframe.onerror = () => {
            clearTimeout(timeoutId);
            finish(failureResult(
                'Falha ao carregar o PDF fiscal para impressão.',
                false,
                { fileName },
            ));
        };

        iframe.src = objectUrl;
        document.body.appendChild(iframe);
    });
}

export async function printFiscalPdf(url, options = {}) {
    if (!isBrowserEnvironment()) {
        return failureResult('Impressão fiscal indisponível neste ambiente.');
    }

    const normalizedUrl = String(url || '').trim();
    if (!normalizedUrl) {
        return failureResult('PDF fiscal indisponível para impressão.');
    }

    try {
        const { blob, fileName } = await fetchArtifactBlob(normalizedUrl, {
            accept: 'application/pdf',
            fallbackBaseName: options.fallbackBaseName || 'documento-fiscal',
            extension: 'pdf',
        });

        return await printPdfBlob(blob, fileName, options);
    } catch (error) {
        return failureResult(
            await resolveRequestErrorMessage(error, 'Não foi possível preparar a impressão fiscal.'),
            false,
        );
    }
}
