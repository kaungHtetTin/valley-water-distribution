import { printConfiguredDocument } from './printDocuments.js';

const number = (value) => Number(value || 0).toLocaleString();
const money = (value) => `${number(value)} MMK`;
const identity = (...values) => values.filter(Boolean).join(' · ');
const settlementLabels = { credit: 'Credit', cash_driver: 'Cash held by driver', cash_office: 'Cash paid to office', bank_office: 'Bank paid to office' };

export function buildDriverDeliveryDocument({ stop, delivery, trip, finalSaleMode = false, finalLines = [], orderDiscount = 0, resultStatus, results = {}, notes, settlement = {} }) {
    const saleMode = finalSaleMode && resultStatus !== 'failed';
    const subtotal = finalLines.reduce((sum, item) => sum + Number(item.sale_quantity || 0) * Number(item.unit_price || 0), 0);
    const discount = Math.min(Math.max(Number(orderDiscount || 0), 0), subtotal);
    const items = finalSaleMode ? (stop.items || []) : (stop.items || []).filter((item) => Number(item.delivered_quantity) > 0);
    const facts = [
        ['Customer', stop.shop_name],
        ['Order', stop.order_code],
        ['Invoice', stop.invoice_code],
        ['Trip', trip?.code || delivery.trip_code || delivery.code],
        ['Planned date', trip?.planned_date || delivery.planned_date],
        ['Route', stop.route_name || trip?.route_name || delivery.route_name],
        ['Vehicle', identity(delivery.vehicle_code, delivery.plate_no)],
        ['Delivery address', stop.delivery_address],
        ['Status', String(finalSaleMode ? resultStatus : stop.status).replaceAll('_', ' ')],
        ['Loaded quantity', number(stop.loaded_quantity)],
    ];

    if (finalSaleMode) facts.push(['Record', 'Current unsaved delivery result']);
    if (saleMode) {
        facts.push(['Subtotal', money(subtotal)], ['Order discount', money(discount)]);
        facts.push(['Settlement', settlementLabels[settlement.method] || settlement.method]);
    } else if (stop.settlement_method) {
        facts.push(['Settlement', settlementLabels[stop.settlement_method] || stop.settlement_method]);
        facts.push(['Final amount', money(stop.settlement_amount)], ['Credit due', stop.credit_due_date]);
    }

    return {
        title: 'Delivery voucher',
        reference: stop.code || stop.invoice_code || delivery.code,
        facts,
        columns: saleMode ? ['Product', 'Sale qty', 'FOC qty', 'Unit price', 'Amount'] : ['Product', 'Loaded', 'Delivered', 'Returned', 'Damaged'],
        rows: saleMode ? finalLines.map((item) => [
            `${item.product_name}\n${identity(item.product_sku, item.unit)}\nQty × price: ${number(item.sale_quantity)} × ${money(item.unit_price)}`,
            number(item.sale_quantity), number(item.foc_quantity), money(item.unit_price),
            money(Number(item.sale_quantity || 0) * Number(item.unit_price || 0)),
        ]) : items.map((item) => {
            const quantities = finalSaleMode ? (results[item.id] || item) : item;
            return [
                `${item.product_name}\n${identity(item.product_sku, item.unit, (item.item_type || 'sale').toUpperCase())}`,
                number(item.loaded_quantity), number(quantities.delivered_quantity), number(quantities.returned_quantity), number(quantities.damaged_quantity),
            ];
        }),
        total: saleMode ? ['Estimated payable', money(Math.max(subtotal - discount, 0))] : ['Total delivered units', number(items.reduce((sum, item) => sum + Number((finalSaleMode ? (results[item.id] || item) : item).delivered_quantity || 0), 0))],
        notes: notes ?? stop.notes,
        signatures: ['Driver signature', 'Customer signature'],
    };
}

export function printDriverDeliveryVoucher(data) {
    return printConfiguredDocument('delivery_voucher', buildDriverDeliveryDocument(data));
}
