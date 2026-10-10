import axios from 'axios';

// Single Axios instance for the whole app. Auth is a Sanctum session cookie
// (httpOnly), so no token is ever stored in JavaScript or localStorage.
const client = axios.create({
    baseURL: '/api/v1',
    withCredentials: true,
    withXSRFToken: true,
    headers: {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
    },
});

// Must be called once before the first state-changing request (login, register).
export const ensureCsrfCookie = () => axios.get('/sanctum/csrf-cookie', { withCredentials: true });

client.interceptors.response.use(undefined, async (error) => {
    const { config, response } = error;

    // 419 = the CSRF token expired (e.g. the tab sat open). Fetch a fresh one and retry once.
    if (response?.status === 419 && config && !config._csrfRetried) {
        config._csrfRetried = true;
        await ensureCsrfCookie();

        return client(config);
    }

    // 401 on anything other than the "who am I" probe means the session ended.
    if (response?.status === 401 && config?.url !== '/user') {
        window.dispatchEvent(new CustomEvent('auth:expired'));
    }

    return Promise.reject(error);
});

export default client;
