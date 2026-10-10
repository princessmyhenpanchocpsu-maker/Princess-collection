import { useEffect, useState } from 'react';
import client from '../api/client';

// Categories and public settings are needed by the navbar, footer and several pages.
// Each is fetched once per page load and shared.
const cache = {};

function useShared(key, url, fallback) {
    const [value, setValue] = useState(fallback);

    useEffect(() => {
        let active = true;

        cache[key] ??= client.get(url).then((response) => response.data.data);
        cache[key]
            .then((data) => active && setValue(data))
            .catch(() => {
                delete cache[key];
            });

        return () => {
            active = false;
        };
    }, [key, url]);

    return value;
}

export const useCategories = () => useShared('categories', '/categories', []);

export const useStoreSettings = () => useShared('settings', '/settings', null);

export const clearStoreData = (key) => delete cache[key];
