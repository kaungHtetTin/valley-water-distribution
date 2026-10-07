export function installCsrfRecovery(client, { refreshUrl, origin, onSessionChanged }) {
    let refreshPromise;
    let userId;
    const sameOrigin = (config) => {
        try {
            return new URL(config.url, config.baseURL || origin).origin === origin;
        } catch {
            return false;
        }
    };

    client.interceptors.request.use((config) => {
        if (sameOrigin(config)) {
            config._valleyUserId ??= userId;
            if (!config._valleyCsrfRetried) {
                // Do not let a stale meta header override the current cookie.
                delete config.headers['X-CSRF-TOKEN'];
            }
        }
        return config;
    });

    client.interceptors.response.use((response) => {
        if (sameOrigin(response.config)) {
            const data = response.data?.data;
            if (data && Object.hasOwn(data, 'user')) userId = data.user?.id ?? null;
            if (response.config.url.endsWith('/auth/logout')) userId = null;
        }
        return response;
    }, async (error) => {
        const config = error.config;
        if (!config || !sameOrigin(config)) throw error;
        if (error.response?.status === 401 && userId != null) onSessionChanged();
        if (error.response?.status !== 419 || config._valleyCsrfRetried
            || ['get', 'head', 'options'].includes((config.method || 'get').toLowerCase())) throw error;

        // CSRF middleware rejected the request before its action ran. Share one
        // refresh for concurrent failures and retry each rejected action once.
        config._valleyCsrfRetried = true;
        if (!refreshPromise) {
            refreshPromise = client.get(refreshUrl).finally(() => { refreshPromise = null; });
        }
        const response = await refreshPromise;
        const session = response.data.data;
        if (config._valleyUserId !== undefined && config._valleyUserId !== session.user_id) {
            onSessionChanged();
            error.response.data.message = 'Your session changed. Please sign in again.';
            throw error;
        }
        config.headers['X-CSRF-TOKEN'] = session.csrf_token;
        return client.request(config);
    });
}
