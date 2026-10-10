import { createContext, useCallback, useContext, useEffect, useMemo, useState } from 'react';
import client from '../api/client';
import { useAuth } from './AuthContext';

const CartContext = createContext(null);

// The cart lives in the database; this context is only a mirror of the last server response.
// Every change returns the full recalculated cart, so prices and totals are never computed here.
export function CartProvider({ children }) {
    const { user } = useAuth();
    const [cart, setCart] = useState(null);
    const [loading, setLoading] = useState(false);
    const isCustomerSession = Boolean(user);

    const refresh = useCallback(async () => {
        if (!isCustomerSession) {
            setCart(null);

            return null;
        }

        setLoading(true);
        try {
            const { data } = await client.get('/cart');
            setCart(data.data);

            return data.data;
        } catch {
            return null;
        } finally {
            setLoading(false);
        }
    }, [isCustomerSession]);

    useEffect(() => {
        refresh();
    }, [refresh]);

    const value = useMemo(() => {
        const apply = async (request) => {
            const { data } = await request;
            setCart(data.data);

            return data.data;
        };

        return {
            cart,
            loading,
            count: cart?.item_count ?? 0,
            refresh,
            addItem: (variantId, quantity) => apply(client.post('/cart/items', { product_variant_id: variantId, quantity })),
            updateItem: (itemId, quantity) => apply(client.patch(`/cart/items/${itemId}`, { quantity })),
            removeItem: (itemId) => apply(client.delete(`/cart/items/${itemId}`)),
        };
    }, [cart, loading, refresh]);

    return <CartContext.Provider value={value}>{children}</CartContext.Provider>;
}

export const useCart = () => useContext(CartContext);
