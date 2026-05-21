import axios from 'axios';
import {
    clearAuthData,
    clearSettingsAccessKey,
    getSettingsAccessKey,
    getToken,
    isIntegratedMode,
    redirectToErpLogin,
} from './auth';

const api = axios.create({
    baseURL: '/api/pdv',
});

api.interceptors.request.use((config) => {
    const token = getToken();

    if (token) {
        config.headers.Authorization = `Bearer ${token}`;
    }

    const settingsAccessKey = getSettingsAccessKey();
    if (settingsAccessKey) {
        config.headers['X-Settings-Access'] = settingsAccessKey;
    }

    return config;
});

api.interceptors.response.use(
    (response) => response,
    (error) => {
        if (error?.response?.status === 401) {
            if (isIntegratedMode()) {
                redirectToErpLogin();
            } else {
                clearAuthData();
                if (window.location.pathname !== '/login') {
                    window.location.href = '/login';
                }
            }
        }

        if (error?.response?.status === 403 && error?.response?.data?.code === 'settings_authorization_required') {
            clearSettingsAccessKey();

            if (window.location.pathname.startsWith('/configuracoes')) {
                window.location.href = '/?unlockSettings=1';
            }
        }

        return Promise.reject(error);
    },
);

export default api;
