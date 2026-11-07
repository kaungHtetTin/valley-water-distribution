const { test } = require('node:test');
const assert = require('node:assert/strict');
const { appUrl, serverUrl, externalUrl } = require('../../native/electron/policy.cjs');
test('Office navigation permits only its own workspace and shared auth/attendance pages', () => {
    assert.ok(appUrl('https://www.valleymyanmar.com/public/office/orders'));
    assert.ok(appUrl('https://www.valleymyanmar.com/public/reset-password/example'));
    for (const url of ['https://www.valleymyanmar.com/public/driver', 'https://www.valleymyanmar.com.evil.test/public/office', 'http://www.valleymyanmar.com/public/office', 'https://user@www.valleymyanmar.com/public/office', 'https://www.valleymyanmar.com/public/office/../../driver', 'file:///C:/Windows/system.ini', 'javascript:alert(1)']) assert.equal(appUrl(url), false, url);
});
test('Downloads require the verified server and external links reject privileged schemes', () => {
    assert.ok(serverUrl('https://www.valleymyanmar.com/public/api/reports/operations/export'));
    assert.ok(externalUrl('https://maps.google.com/'));
    for (const url of ['file:///C:/secret', 'javascript:alert(1)', 'data:text/html,example', 'https://user:pass@example.com']) assert.equal(externalUrl(url), false);
});
