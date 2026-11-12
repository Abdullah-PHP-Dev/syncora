// Thin axios wrapper for the CRM JSON API (window.axios carries the CSRF header).
export function createApi(base) {
    const url = (path) => `${base}/${path.replace(/^\//, '')}`;
    const unwrap = (promise) => promise.then((r) => r.data);

    return {
        get: (path, params = {}) => unwrap(window.axios.get(url(path), { params })),
        post: (path, data = {}) => unwrap(window.axios.post(url(path), data)),
        put: (path, data = {}) => unwrap(window.axios.put(url(path), data)),
        patch: (path, data = {}) => unwrap(window.axios.patch(url(path), data)),
        del: (path) => unwrap(window.axios.delete(url(path))),
    };
}

// Route templates are generated with id 0; swap in the real id.
export function withId(template, id) {
    return template.replace(/\/0(?=$|[/?#])/, `/${id}`);
}

// First validation message, or the server message, or a fallback.
export function errorMessage(error, fallback) {
    const data = error?.response?.data;
    if (data?.errors) {
        const first = Object.values(data.errors)[0];
        return Array.isArray(first) ? first[0] : first;
    }
    return data?.message || fallback;
}
