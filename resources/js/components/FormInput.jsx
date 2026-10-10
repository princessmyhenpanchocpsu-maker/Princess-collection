import { Eye, EyeOff } from 'lucide-react';
import { useId, useState } from 'react';

/**
 * Labelled input with inline API/validation error. `as` may be "input", "textarea" or "select".
 * Pass type="password" to get a show/hide toggle.
 */
export default function FormInput({ label, error, hint, as: Field = 'input', type = 'text', className = '', children, ...props }) {
    const id = useId();
    const [revealed, setRevealed] = useState(false);
    const isPassword = type === 'password';
    const describedBy = error ? `${id}-error` : hint ? `${id}-hint` : undefined;

    return (
        <div className={className}>
            {label && (
                <label htmlFor={id} className="label">
                    {label}
                    {props.required && <span className="text-rose-dark"> *</span>}
                </label>
            )}
            <div className="relative">
                <Field
                    id={id}
                    {...(Field === 'input' ? { type: isPassword && revealed ? 'text' : type } : {})}
                    aria-invalid={error ? 'true' : undefined}
                    aria-describedby={describedBy}
                    className={`input ${isPassword ? 'pr-11' : ''} ${error ? 'border-red-400 focus:border-red-500 focus:ring-red-500' : ''}`}
                    {...props}
                >
                    {children}
                </Field>
                {isPassword && (
                    <button
                        type="button"
                        onClick={() => setRevealed((value) => !value)}
                        className="absolute inset-y-0 right-0 flex items-center px-3 text-brown/60 hover:text-ink"
                        aria-label={revealed ? 'Hide password' : 'Show password'}
                    >
                        {revealed ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
                    </button>
                )}
            </div>
            {error ? (
                <p id={`${id}-error`} className="mt-1.5 text-sm text-red-600">
                    {error}
                </p>
            ) : (
                hint && (
                    <p id={`${id}-hint`} className="mt-1.5 text-xs text-brown/60">
                        {hint}
                    </p>
                )
            )}
        </div>
    );
}
