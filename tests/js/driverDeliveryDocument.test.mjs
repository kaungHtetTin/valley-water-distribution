import test from 'node:test';
import assert from 'node:assert/strict';
import { buildDriverDeliveryDocument, printDriverDeliveryVoucher } from '../../resources/js/driverDeliveryDocument.js';

const data = {
    stop: { code: 'DEL-1', order_code: 'ORD-1', invoice_code: 'INV-1', shop_name: 'Customer', status: 'delivered', loaded_quantity: 12, notes: 'Saved completion note', items: [
        { id: 1, product_name: 'Water <5L>', product_sku: 'WATER', unit: 'bottle', loaded_quantity: 12, delivered_quantity: 10, returned_quantity: 1, damaged_quantity: 1 },
    ] },
    delivery: { code: 'DEL-1', planned_date: '2026-10-22', vehicle_code: 'VEH-1', plate_no: '7K/2040' },
    trip: { code: 'TRIP-1' },
};

test('saved delivery documents retain quantities, customer and trip references', () => {
    const document = buildDriverDeliveryDocument(data);
    assert.deepEqual(document.rows[0].slice(1), ['12', '10', '1', '1']);
    assert.deepEqual(document.total, ['Total delivered units', '10']);
    assert.equal(document.notes, 'Saved completion note');
    assert.ok(document.facts.some(([label, value]) => label === 'Trip' && value === 'TRIP-1'));
});

test('current final sale prints editable quantities, FOC and capped order discount', () => {
    const document = buildDriverDeliveryDocument({ ...data, finalSaleMode: true, resultStatus: 'delivered', orderDiscount: 500,
        finalLines: [{ product_name: 'Water', sale_quantity: 10, foc_quantity: 2, unit_price: 3000 }], notes: 'Current note',
    });
    assert.deepEqual(document.rows[0].slice(1), ['10', '2', '3,000 MMK', '30,000 MMK']);
    assert.deepEqual(document.total, ['Estimated payable', '29,500 MMK']);
    assert.equal(document.notes, 'Current note');
    assert.ok(document.facts.some(([label, value]) => label === 'Record' && value.includes('unsaved')));
});

function setup(t) {
    const writes = [];
    const popup = { opener: {}, document: { open() {}, write(html) { writes.push(html); }, close() {} } };
    const setting = { label: 'Delivery voucher', paper_size: 'Letter', orientation: 'landscape', margin_mm: 17, copies: 3, design: 'minimal', accent_color: '#123456', show_logo: true, show_address: true, show_contact: true, show_tax_number: true, show_notes: true, show_signatures: true, header_text: 'Header note', footer_text: 'Footer note' };
    const company = { name: 'Company', logo_url: '/logo.png', address: 'Company address', phone: 'Company phone', email: 'company@example.test', tax_no: 'TAX-1' };
    let calls = 0;
    globalThis.window = { open: () => popup, axios: { get: async () => { calls++; return { data: { data: { company, documents: { delivery_voucher: setting } } } }; } } };
    t.after(() => { delete globalThis.window; });
    return { writes, setting, calls: () => calls };
}

test('all saved layout, branding and copy settings reach the delivery printout', async (t) => {
    const { writes } = setup(t);
    await printDriverDeliveryVoucher(data);
    const html = writes.at(-1);
    assert.match(html, /size: Letter landscape; margin: 17mm/);
    assert.equal((html.match(/<article class="document design-minimal">/g) || []).length, 3);
    for (const text of ['Company address', 'Company phone', 'company@example.test', 'TAX-1', 'Header note', 'Footer note', 'Saved completion note', 'Driver signature', 'Customer signature']) assert.ok(html.includes(text), text);
    assert.equal((html.match(/<img src="\/logo.png"/g) || []).length, 3);
    assert.match(html, /Water &lt;5L&gt;/);
    assert.match(html, /#123456/);
    assert.match(html, /grid-template-columns: repeat\(2, 1fr\)/);
});

test('printing fetches changed settings again and respects every visibility switch', async (t) => {
    const state = setup(t);
    await printDriverDeliveryVoucher(data);
    Object.assign(state.setting, { paper_size: '58mm', copies: 1, show_logo: false, show_address: false, show_contact: false, show_tax_number: false, show_notes: false, show_signatures: false });
    await printDriverDeliveryVoucher(data);
    assert.equal(state.calls(), 2);
    const html = state.writes.at(-1);
    assert.match(html, /size: 58mm 210mm/);
    for (const text of ['/logo.png', 'Company address', 'Company phone', 'company@example.test', 'TAX-1', 'Saved completion note', 'Driver signature', 'Customer signature']) assert.ok(!html.includes(text), text);
    assert.equal((html.match(/<article class="document/g) || []).length, 1);
});

test('a settings error prints an error message without falling back to A5', async (t) => {
    const { writes } = setup(t);
    window.axios.get = async () => { throw new Error('Settings service unavailable'); };
    assert.equal(await printDriverDeliveryVoucher(data), null);
    assert.match(writes.at(-1), /Settings service unavailable/);
    assert.doesNotMatch(writes.at(-1), /@page|window.print/);
});

test('a missing delivery profile cannot silently print with defaults', async (t) => {
    const { writes } = setup(t);
    window.axios.get = async () => ({ data: { data: { documents: {} } } });
    assert.equal(await printDriverDeliveryVoucher(data), null);
    assert.match(writes.at(-1), /Printing settings are unavailable/);
    assert.doesNotMatch(writes.at(-1), /@page|window.print/);
});
