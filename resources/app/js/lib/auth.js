const TOKEN_KEY = 'simples_pdv_token';
const USER_KEY = 'simples_pdv_user';
const CONTEXT_KEY = 'simples_pdv_context';
const SETTINGS_ACCESS_KEY = 'simples_pdv_settings_access_key';
const TERMINAL_SESSION_KEY = 'simples_pdv_terminal_session';

const DEFAULT_RUNTIME = {
    mode: 'standalone',
    integrated: false,
    erp_home_url: '/dashboard',
    erp_login_url: '/login',
    erp_logout_url: '/logout',
    csrf_token: '',
};

export function getRuntime() {
    return {
        ...DEFAULT_RUNTIME,
        ...(window.__SIMPLS_PDV_RUNTIME__ || {}),
    };
}

export function isIntegratedMode() {
    const runtime = getRuntime();
    return Boolean(runtime.integrated) || runtime.mode === 'erp';
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
}

export function clearTerminalSession() {
    sessionStorage.removeItem(TERMINAL_SESSION_KEY);
}

export function clearAuthData() {
    clearToken();
    clearUser();
    clearContext();
    clearSettingsAccessKey();
    clearTerminalSession();
}

export function canReturnToErp() {
    return isIntegratedMode() && Boolean(getUser()?.can_access_erp);
}

export function resolvePdvExitLabel() {
    return canReturnToErp() ? 'Voltar para o ERP' : 'Sair';
}

export function redirectToErpLogin() {
    const runtime = getRuntime();
    clearAuthData();
    window.location.href = runtime.erp_login_url || '/login';
}

function postThenRedirect(action, token, redirectUrl) {
    fetch(action, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8',
            'X-CSRF-TOKEN': token,
            Accept: 'text/html,application/xhtml+xml',
        },
        body: new URLSearchParams({ _token: token }).toString(),
    })
        .catch(() => {})
        .finally(() => {
            window.location.href = redirectUrl;
        });
}

function revokeCurrentPdvToken() {
    const token = getToken();
    if (!token) return;

    fetch('/api/pdv/auth/logout', {
        method: 'POST',
        credentials: 'same-origin',
        keepalive: true,
        headers: {
            Authorization: `Bearer ${token}`,
            Accept: 'application/json',
        },
    }).catch(() => {});
}

export function exitIntegratedPdv() {
    if (!isIntegratedMode()) {
        return false;
    }

    const runtime = getRuntime();
    const userCanReturnToErp = canReturnToErp();
    revokeCurrentPdvToken();
    clearAuthData();

    if (userCanReturnToErp) {
        window.location.href = runtime.erp_home_url || '/dashboard';
        return true;
    }

    const csrfToken = runtime.csrf_token
        || document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
        || '';

    if (!csrfToken) {
        window.location.href = runtime.erp_login_url || '/login';
        return true;
    }

    postThenRedirect(runtime.erp_logout_url || '/logout', csrfToken, runtime.erp_login_url || '/login');
    return true;
}
