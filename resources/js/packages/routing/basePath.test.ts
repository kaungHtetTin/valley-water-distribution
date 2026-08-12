import { afterEach, describe, expect, it } from 'vitest';
import { applicationPathname, configuredBasePath } from './basePath';

function setBasePath(value: string) {
    document.head.innerHTML = `<meta name="app-base-path" content="${value}">`;
}

describe('deployment base path', () => {
    afterEach(() => { document.head.innerHTML = ''; });

    it('supports applications hosted at the domain root', () => {
        setBasePath('/');
        expect(configuredBasePath()).toBe('');
        expect(applicationPathname('/office/customers')).toBe('/office/customers');
    });

    it('strips a normalized APP_URL path before application routing', () => {
        setBasePath('/valley-water/public/');
        expect(configuredBasePath()).toBe('/valley-water/public');
        expect(applicationPathname('/valley-water/public/office/customers')).toBe('/office/customers');
    });
});
