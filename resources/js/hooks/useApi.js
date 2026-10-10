import axios from 'axios';
import { useCallback, useEffect, useState } from 'react';

/**
 * Run a GET-style request and track its state.
 * `request` receives an AbortSignal and returns an Axios promise; `data` is the response body.
 */
export default function useApi(request, deps = []) {
    const [state, setState] = useState({ data: null, loading: true, error: null });
    const [attempt, setAttempt] = useState(0);

    useEffect(() => {
        const controller = new AbortController();

        setState((previous) => ({ ...previous, loading: true, error: null }));

        request(controller.signal)
            .then((response) => setState({ data: response.data, loading: false, error: null }))
            .catch((error) => {
                if (!axios.isCancel(error)) setState({ data: null, loading: false, error });
            });

        return () => controller.abort();
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [...deps, attempt]);

    const reload = useCallback(() => setAttempt((count) => count + 1), []);
    const setData = useCallback((data) => setState((previous) => ({ ...previous, data })), []);

    return { ...state, reload, setData };
}
