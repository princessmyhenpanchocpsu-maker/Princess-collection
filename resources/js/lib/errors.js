// Laravel validation errors arrive as { errors: { field: [messages] } }. Keep the first message per field.
export function fieldErrors(error) {
    const errors = error?.response?.data?.errors;

    if (!errors || typeof errors !== 'object') return {};

    return Object.fromEntries(Object.entries(errors).map(([field, messages]) => [field, [].concat(messages)[0]]));
}

export function errorMessage(error, fallback = 'Something went wrong. Please try again.') {
    const status = error?.response?.status;

    if (!error?.response) return 'We could not reach the server. Check your connection and try again.';
    if (status === 429) return 'Too many attempts. Please wait a minute and try again.';
    if (status === 403) return error.response.data?.message || 'You do not have permission to do that.';
    if (status >= 500) return fallback;

    return error.response.data?.message || fallback;
}
