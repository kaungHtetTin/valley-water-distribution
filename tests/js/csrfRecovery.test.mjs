import test from 'node:test';
import assert from 'node:assert/strict';
import axios from 'axios';
import { installCsrfRecovery } from '../../resources/js/csrfRecovery.js';

function setup({ rejectRetry = false, changedUser = false } = {}) {
    const calls = [];
    let sessionChanges = 0;
    const client = axios.create({ adapter: async (config) => {
        calls.push({ ...config, headers: new axios.AxiosHeaders(config.headers) });
        if (config.url === '/api/auth/user') {
            return { config, status: 200, data: { data: { user: { id: 5 } } } };
        }
        if (config.url === '/api/auth/csrf') {
            await new Promise((resolve) => setTimeout(resolve, 10));
            return { config, status: 200, data: { data: { csrf_token: 'fresh', user_id: changedUser ? 7 : 5 } } };
        }
        if (!config._valleyCsrfRetried || rejectRetry) {
            throw new axios.AxiosError('CSRF token mismatch.', 'ERR_BAD_REQUEST', config, null,
                { status: 419, data: { message: 'CSRF token mismatch.' }, config });
        }
        return { config, status: 200, data: { saved: true } };
    } });
    client.defaults.headers.common['X-CSRF-TOKEN'] = 'stale';
    installCsrfRecovery(client, {
        origin: 'http://localhost', refreshUrl: '/api/auth/csrf',
        onSessionChanged: () => sessionChanges++,
    });
    return { client, calls, sessionChanges: () => sessionChanges };
}

test('parallel rejected writes share one refresh, retain payload, and retry with fresh token', async () => {
    const { client, calls } = setup();
    await client.get('/api/auth/user');
    const body = new FormData();
    body.append('photo', 'upload');
    await Promise.all([client.post('/save', { amount: 12 }), client.post('/upload', body)]);
    assert.equal(calls.filter((c) => c.url === '/api/auth/csrf').length, 1);
    for (const url of ['/save', '/upload']) {
        const writes = calls.filter((c) => c.url === url);
        assert.equal(writes.length, 2);
        assert.equal(writes[0].headers.get('X-CSRF-TOKEN'), undefined);
        assert.equal(writes[1].headers.get('X-CSRF-TOKEN'), 'fresh');
        assert.equal(writes[0].data, writes[1].data);
    }
});

test('a second mismatch is returned instead of looping', async () => {
    const { client, calls } = setup({ rejectRetry: true });
    await assert.rejects(client.post('/save', {}));
    assert.equal(calls.filter((c) => c.url === '/save').length, 2);
    assert.equal(calls.filter((c) => c.url === '/api/auth/csrf').length, 1);
});

test('does not replay a write under another signed-in account', async () => {
    const { client, calls, sessionChanges } = setup({ changedUser: true });
    await client.get('/api/auth/user');
    await assert.rejects(client.post('/save', {}));
    assert.equal(calls.filter((c) => c.url === '/save').length, 1);
    assert.equal(sessionChanges(), 1);
});

test('does not recover cross-origin failures or send a fresh token to them', async () => {
    const { client, calls } = setup();
    await assert.rejects(client.post('https://example.com/save', {}));
    assert.equal(calls.length, 1);
});
