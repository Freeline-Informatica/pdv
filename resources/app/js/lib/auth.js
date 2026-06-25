const TOKEN_KEY = 'simples_pdv_token';
const USER_KEY = 'simples_pdv_user';
const CONTEXT_KEY = 'simples_pdv_context';
const SETTINGS_ACCESS_KEY = 'simples_pdv_settings_access_key';
const CANCEL_ACCESS_KEY = 'simples_pdv_cancel_access_key';
const TERMINAL_SESSION_KEY = 'simples_pdv_terminal_session';

function emitTerminalSessionUpdated(terminal) {
    if (typeof window === 'undefined' || typeof window.dispatchEvent !== 'function') {
        return;
    }

    window.dispatchEvent(new CustomEvent('pdv:terminal-session-updated', {
        detail: terminal ?? null,
    }));
}

export function getToken() {
    return localStorage.getItem(TOKEN_KEY);
}

export function setToken(token) {
    localStorage.setItem(TOKEN_KEY, token);
}

export function clearToken() {
    localStorage.removeItem(TOKEN_KEY);
}

export function getUser() {
    const rawUser = localStorage.getItem(USER_KEY);
    if (!rawUser) return null;

    try {
        return JSON.parse(rawUser);
    } catch {
        return null;
    }
}

export function setUser(user) {
    localStorage.setItem(USER_KEY, JSON.stringify(user));
}

export function clearUser() {
    localStorage.removeItem(USER_KEY);
}

export function getContext() {
    const rawContext = localStorage.getItem(CONTEXT_KEY);
    if (!rawContext) return null;

    try {
        return JSON.parse(rawContext);
    } catch {
        return null;
    }
}

export function setContext(context) {
    if (!context) {
        localStorage.removeItem(CONTEXT_KEY);
        return;
    }

    localStorage.setItem(CONTEXT_KEY, JSON.stringify({
        grupo_empresarial_id: context.grupo_empresarial_id ?? null,
        estabelecimento_id: context.estabelecimento_id ?? null,
    }));
}

export function clearContext() {
    localStorage.removeItem(CONTEXT_KEY);
}

export function getUserRole() {
    return getUser()?.role || null;
}

function sameContext(left, right) {
    return String(left?.grupo_empresarial_id ?? '') === String(right?.grupo_empresarial_id ?? '')
        && String(left?.estabelecimento_id ?? '') === String(right?.estabelecimento_id ?? '');
}

export function setAuthSession(token, user, context = null) {
    clearSettingsAccessKey();
    clearTerminalSession();
    setToken(token);
    setUser(user);
    setContext(context);
}

export function consumeBootstrappedSession() {
    const bootstrap = window.__SIMPLS_PDV_BOOTSTRAP__;

    if (!bootstrap?.token || !bootstrap?.user) {
        return;
    }

    const currentUser = getUser();
    const currentContext = getContext();
    const nextContext = bootstrap.context || null;

    if (currentUser?.id && currentUser.id !== bootstrap.user.id) {
        clearTerminalSession();
    }

    if (!sameContext(currentContext, nextContext)) {
        clearTerminalSession();
    }

    clearSettingsAccessKey();
    setToken(bootstrap.token);
    setUser(bootstrap.user);
    setContext(nextContext);
    delete window.__SIMPLS_PDV_BOOTSTRAP__;
}

export function getSettingsAccessKey() {
    return sessionStorage.getItem(SETTINGS_ACCESS_KEY);
}

export function setSettingsAccessKey(accessKey) {
    sessionStorage.setItem(SETTINGS_ACCESS_KEY, accessKey);
}

export function clearSettingsAccessKey() {
    sessionStorage.removeItem(SETTINGS_ACCESS_KEY);
}

export function getCancelAccessKey() {
    return sessionStorage.getItem(CANCEL_ACCESS_KEY);
}

export function setCancelAccessKey(accessKey) {
    sessionStorage.setItem(CANCEL_ACCESS_KEY, accessKey);
}

export function clearCancelAccessKey() {
    sessionStorage.removeItem(CANCEL_ACCESS_KEY);
}

export function getTerminalSession() {
    const rawTerminal = sessionStorage.getItem(TERMINAL_SESSION_KEY);
    if (!rawTerminal) return null;

    try {
        return JSON.parse(rawTerminal);
    } catch {
        return null;
    }
}

export function setTerminalSession(terminal) {
    sessionStorage.setItem(TERMINAL_SESSION_KEY, JSON.stringify(terminal));
    emitTerminalSessionUpdated(terminal);
}

export function clearTerminalSession() {
    sessionStorage.removeItem(TERMINAL_SESSION_KEY);
    emitTerminalSessionUpdated(null);
}

export function clearAuthData() {
    clearToken();
    clearUser();
    clearContext();
    clearSettingsAccessKey();
    clearCancelAccessKey();
    clearTerminalSession();
}
