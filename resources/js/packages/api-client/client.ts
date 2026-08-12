import axios from 'axios';
import { configuredBasePath } from '../routing/basePath';

export const apiClient = axios.create({
    baseURL: `${configuredBasePath()}/api/v1`,
    headers: {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
    },
    withCredentials: true,
    timeout: 10_000,
});

apiClient.interceptors.response.use(
    (response) => response,
    (error: unknown) => Promise.reject(error),
);
