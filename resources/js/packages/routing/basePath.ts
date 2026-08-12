function normalizeBasePath(path: string): string {
    const trimmed = path.trim();

    if (trimmed === '' || trimmed === '/') {
        return '';
    }

    return `/${trimmed.replace(/^\/+|\/+$/g, '')}`;
}

export function configuredBasePath(): string {
    const configured = document.querySelector<HTMLMetaElement>('meta[name="app-base-path"]')?.content ?? '';

    return normalizeBasePath(configured);
}

export function applicationPathname(pathname: string = window.location.pathname): string {
    const basePath = configuredBasePath();

    if (basePath && (pathname === basePath || pathname.startsWith(`${basePath}/`))) {
        return pathname.slice(basePath.length) || '/';
    }

    return pathname;
}
