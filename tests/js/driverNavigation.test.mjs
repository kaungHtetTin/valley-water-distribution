import test from 'node:test';
import assert from 'node:assert/strict';
import { driverRoute } from '../../resources/js/ui/driverNavigation.js';

test('driver deep links retain trip and stop IDs with an absolute runtime URL', () => {
    assert.deepEqual(driverRoute('/valley/public/driver/tasks/11/stops/21', 'http://localhost/valley/public/driver'), {
        base: '/valley/public/driver/tasks', tripId: '11', stopId: '21', closeout: false, invalid: false,
    });
});

test('trip closeout and history have distinct reloadable routes', () => {
    assert.equal(driverRoute('/driver/tasks/11/closeout', '/driver').closeout, true);
    assert.equal(driverRoute('/driver/history/11', '/driver', 'history').tripId, '11');
    assert.equal(driverRoute('/driver/tasks', '/driver').tripId, null);
    assert.equal(driverRoute('/driver/tasks/invalid', '/driver').invalid, true);
});
