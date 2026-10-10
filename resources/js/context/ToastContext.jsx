import { createContext, useCallback, useContext, useMemo, useRef, useState } from 'react';
import Toast from '../components/Toast';

const ToastContext = createContext(null);

export function ToastProvider({ children }) {
    const [toasts, setToasts] = useState([]);
    const nextId = useRef(1);

    const dismiss = useCallback((id) => setToasts((list) => list.filter((toast) => toast.id !== id)), []);

    const show = useCallback(
        (type, message) => {
            const id = nextId.current++;
            setToasts((list) => [...list.slice(-2), { id, type, message }]);
            setTimeout(() => dismiss(id), type === 'error' ? 6000 : 3500);
        },
        [dismiss],
    );

    const api = useMemo(
        () => ({ success: (message) => show('success', message), error: (message) => show('error', message) }),
        [show],
    );

    return (
        <ToastContext.Provider value={api}>
            {children}
            <div
                aria-live="polite"
                className="pointer-events-none fixed inset-x-0 bottom-4 z-[60] flex flex-col items-center gap-2 px-4 sm:inset-x-auto sm:right-4 sm:items-end"
            >
                {toasts.map((toast) => (
                    <Toast key={toast.id} {...toast} onDismiss={() => dismiss(toast.id)} />
                ))}
            </div>
        </ToastContext.Provider>
    );
}

export const useToast = () => useContext(ToastContext);
