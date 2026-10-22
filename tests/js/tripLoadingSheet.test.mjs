import test from 'node:test';
import assert from 'node:assert/strict';
import { buildTripLoadingDocument, printTripLoadingSheet } from '../../resources/js/tripLoadingSheet.js';

const detail = {
    delivery: { code: 'DEL-1', warehouse_code: 'WH-1', warehouse_name: 'Main warehouse', driver_name: 'Driver Demo', vehicle_code: 'VEH-1', plate_no: '7K/2040', notes: 'Handle carefully' },
    trip: { code: 'TRIP-1', planned_date: '2026-10-22', status: 'assigned', route_name: 'North / South' },
    stops: [{ id: 1 }, { id: 2 }],
    stock: [
        { product_id: 1, product_name: 'Water <fresh> & clean', product_sku: 'WATER-5G', unit: 'bottle', planned_quantity: 14, loaded_quantity: 0 },
        { product_id: 2, product_name: 'Small water', product_sku: 'WATER-S', unit: 'case', planned_quantity: 2.5, loaded_quantity: 1 },
        { product_id: 3, product_name: 'Unplanned item', product_sku: 'EXTRA', unit: 'bottle', planned_quantity: 0, loaded_quantity: 5 },
    ],
};

test('loading sheet uses the complete trip stock and planned quantities before loading', () => {
    const document = buildTripLoadingDocument(detail);
    assert.equal(document.reference, 'TRIP-1');
    assert.deepEqual(document.rows, [
        ['Water <fresh> & clean', 'WATER-5G', 'bottle', '14'],
        ['Small water', 'WATER-S', 'case', '2.5'],
    ]);
    assert.deepEqual(document.total, ['Total units to load', '16.5']);
    assert.ok(document.facts.some(([label, value]) => label === 'Stops' && value === 2));
    assert.ok(document.facts.some(([label, value]) => label === 'Route' && value === 'North / South'));
    assert.match(document.notes, /Handle carefully/);
});

function setupPrint(t, getSettings) {
    const writes = [];
    const popup = { opener: {}, document: { open() {}, write(html) { writes.push(html); }, close() {} } };
    globalThis.window = { open: () => popup, axios: { get: getSettings } };
    t.after(() => { delete globalThis.window; });
    return { writes, popup };
}

test('prints A4 portrait with company branding, totals and escaped product text', async (t) => {
    const { writes, popup } = setupPrint(t, async () => ({ data: { data: {
        company: { name: 'Valley Water' },
        documents: { trip_loading_sheet: { label: 'Trip loading sheet', paper_size: '80mm', orientation: 'landscape', show_signatures: true, show_notes: true } },
    } } }));
    await printTripLoadingSheet(detail);
    const html = writes.at(-1);
    assert.equal(popup.opener, null);
    assert.match(html, /@page \{ size: A4 portrait;/);
    assert.match(html, /Valley Water/);
    assert.match(html, /Water &lt;fresh&gt; &amp; clean/);
    assert.match(html, /Total units to load<\/th><th>16.5<\/th>/);
    assert.match(html, /Warehouse signature/);
    assert.match(html, /Driver signature/);
    assert.doesNotMatch(html, /Unplanned item/);
});

test('a settings failure produces a readable print error', async (t) => {
    const { writes } = setupPrint(t, async () => { throw new Error('Print settings unavailable'); });
    await printTripLoadingSheet(detail);
    assert.match(writes.at(-1), /Print settings unavailable/);
    assert.doesNotMatch(writes.at(-1), /window.print/);
});
