import { createContext, useCallback, useContext, useEffect, useMemo, useState } from 'react';
import client, { ensureCsrfCookie } from '../api/client';

const AuthContext = createContext(null);

export function AuthProvider({ children }) {
    // `loading` until the first "who am I" request settles, so routes don't flash a login redirect.
    const [state, setState] = useState({ user: null, loading: true });

    useEffect(() => {
        client
            .get('/user')
            .then(({ data }) => setState({ user: data.data, loading: false }))
            .catch(() => setState({ user: null, loading: false }));

        const onExpired = () => setState({ user: null, loading: false });
        window.addEventListener('auth:expired', onExpired);

        return () => window.removeEventListener('auth:expired', onExpired);
    }, []);

    const authenticate = useCallback(async (url, payload) => {
        await ensureCsrfCookie();
        const { data } = await client.post(url, payload);
        setState({ user: data.data, loading: false });

        return data.data;
    }, []);

    const value = useMemo(
        () => ({
            user: state.user,
            loading: state.loading,
            isAdmin: state.user?.role === 'admin',
            login: (credentials) => authenticate('/login', credentials),
            register: (details) => authenticate('/register', details),
            logout: async () => {
                await client.post('/logout').catch(() => {});
                setState({ user: null, loading: false });
            },
            setUser: (user) => setState({ user, loading: false }),
        }),
        [state, authenticate],
    );

    return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}

export const useAuth = () => useContext(AuthContext);
