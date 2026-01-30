export const secureFetch = async (url, options = {}) => {
    const defaultOptions = {
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json'
        },
        credentials: 'include'
    };

    const mergedOptions = {
        ...defaultOptions,
        ...options,
        headers: {
            ...defaultOptions.headers,
            ...options.headers
        }
    };

    const res = await fetch(url, mergedOptions);

    if (!res.ok) {
        throw new Error(`Fetch failed: ${res.status}`);
    }

    // Check if response is JSON
    const contentType = res.headers.get("content-type");
    if (contentType && contentType.indexOf("application/json") !== -1) {
        return res.json();
    } else {
        return res; // Return response object for non-JSON (like blobs)
    }
};
