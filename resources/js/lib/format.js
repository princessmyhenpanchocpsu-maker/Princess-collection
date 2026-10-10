const peso = new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' });

export const formatPeso = (value) => peso.format(Number(value ?? 0));

export function formatDate(iso, { time = false } = {}) {
    if (!iso) return '';

    return new Intl.DateTimeFormat('en-PH', {
        dateStyle: 'medium',
        ...(time ? { timeStyle: 'short' } : {}),
    }).format(new Date(iso));
}

export const SIZE_ORDER = ['XS', 'S', 'M', 'L', 'XL'];

export function sortSizes(sizes) {
    const rank = (size) => {
        const index = SIZE_ORDER.indexOf(size);

        return index === -1 ? SIZE_ORDER.length : index;
    };

    return [...sizes].sort((a, b) => rank(a) - rank(b) || a.localeCompare(b));
}

// Swatch colours for common colour names. Unknown names fall back to a neutral swatch with a label.
const COLOR_HEX = {
    rose: '#D98CA6', blush: '#F6DCE5', pink: '#F4A6C0', cream: '#F3E9D8', white: '#FFFFFF', ivory: '#FFFFF0',
    black: '#2B2B2B', beige: '#D9C3A5', khaki: '#C3B091', brown: '#6E514A', mocha: '#7B5B4C', olive: '#7A8450',
    sage: '#A9BA9D', green: '#5E8C6A', lilac: '#C8B6E2', purple: '#8E6BBF', 'denim blue': '#5B7FA6', blue: '#4A78B5',
    navy: '#1F2D4D', 'light blue': '#A9C7E8', red: '#C0392B', maroon: '#7B1E2B', mustard: '#D4A72C', yellow: '#F2D04B',
    orange: '#E08A3C', gray: '#9A9A9A', grey: '#9A9A9A',
};

export const colorHex = (name) => COLOR_HEX[String(name).trim().toLowerCase()] ?? null;

export const titleCase = (value) =>
    String(value ?? '')
        .replace(/_/g, ' ')
        .replace(/\b\w/g, (letter) => letter.toUpperCase());

export function uuid() {
    if (crypto.randomUUID) return crypto.randomUUID();

    // crypto.randomUUID is unavailable on plain-http origins other than localhost.
    const bytes = crypto.getRandomValues(new Uint8Array(16));
    bytes[6] = (bytes[6] & 0x0f) | 0x40;
    bytes[8] = (bytes[8] & 0x3f) | 0x80;
    const hex = [...bytes].map((byte) => byte.toString(16).padStart(2, '0')).join('');

    return `${hex.slice(0, 8)}-${hex.slice(8, 12)}-${hex.slice(12, 16)}-${hex.slice(16, 20)}-${hex.slice(20)}`;
}
