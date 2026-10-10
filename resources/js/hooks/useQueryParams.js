import { useCallback } from 'react';
import { useSearchParams } from 'react-router-dom';

// List filters live in the URL so a filtered admin table can be bookmarked, shared and reloaded.
// Changing any filter goes back to page 1.
export default function useQueryParams() {
    const [params, setParams] = useSearchParams();

    const set = useCallback(
        (changes) => {
            setParams(
                (current) => {
                    const next = new URLSearchParams(current);
                    if (!('page' in changes)) next.delete('page');
                    Object.entries(changes).forEach(([key, value]) => (value === '' || value == null ? next.delete(key) : next.set(key, value)));

                    return next;
                },
                { replace: true },
            );
        },
        [setParams],
    );

    return [Object.fromEntries(params.entries()), set, params.toString()];
}
