import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './resources/views/**/*.blade.php',
        './resources/js/**/*.{js,jsx}',
    ],

    // Button variants are chosen by prop (`btn-${variant}`), so they never appear as literal strings.
    safelist: ['btn-primary', 'btn-secondary', 'btn-danger', 'btn-ghost'],

    theme: {
        extend: {
            colors: {
                blush: '#F6DCE5',
                rose: {
                    DEFAULT: '#D98CA6',
                    dark: '#C4728F',
                },
                cream: '#FFF8F0',
                brown: '#6E514A',
                mist: '#F5F5F5',
                ink: '#292524',
            },
            fontFamily: {
                sans: ['Inter', ...defaultTheme.fontFamily.sans],
                display: ['"Playfair Display"', ...defaultTheme.fontFamily.serif],
            },
            boxShadow: {
                soft: '0 4px 20px -4px rgba(110, 81, 74, 0.12)',
            },
        },
    },

    plugins: [forms],
};
