export const menuFiscalFiles = Object.freeze([
    {
        key: 'arquivo_i',
        title: 'Arquivo I',
        description: 'Registros do PAF-NFC-e',
        endpoint: '/menu-fiscal/arquivo-i',
        filename: 'menu-fiscal-arquivo-i.xml',
    },
    {
        key: 'arquivo_ii',
        title: 'Arquivo II',
        description: 'Saídas por CPF/CNPJ',
        endpoint: '/menu-fiscal/arquivo-ii',
        filename: 'menu-fiscal-arquivo-ii.xml',
    },
    {
        key: 'arquivo_iii',
        title: 'Arquivo III',
        description: 'Requisições externas',
        endpoint: '/menu-fiscal/arquivo-iii',
        filename: 'menu-fiscal-arquivo-iii.xml',
    },
    {
        key: 'arquivo_iv',
        title: 'Arquivo IV',
        description: 'Controle dos DAV',
        endpoint: '/menu-fiscal/arquivo-iv',
        filename: 'menu-fiscal-arquivo-iv.xml',
    },
]);

export function buildMenuFiscalPayload(file, filters = {}) {
    if (file?.key === 'arquivo_ii') {
        const date = new Date(`${filters.date_from || new Date().toISOString().slice(0, 10)}T00:00:00`);

        return {
            month: Number(date.getMonth() + 1),
            year: Number(date.getFullYear()),
        };
    }

    if (file?.key === 'arquivo_iv') {
        return {};
    }

    return {
        start_date: filters.date_from || undefined,
        end_date: filters.date_to || undefined,
    };
}

export async function resolveMenuFiscalRequestMessage(requestError, fallback) {
    const data = requestError?.response?.data;
    if (data instanceof Blob) {
        const text = await data.text();
        if (text) {
            try {
                const parsed = JSON.parse(text);
                return parsed?.message || fallback;
            } catch {
                return text;
            }
        }
    }

    return requestError?.response?.data?.message || fallback;
}
