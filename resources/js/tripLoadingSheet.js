import { printConfiguredDocument } from './printDocuments.js';

const quantity = (value) => Number(value || 0).toLocaleString();
const identity = (...values) => values.filter(Boolean).join(' · ');

export function buildTripLoadingDocument({ delivery, trip, stock = [], stops = [] }) {
    const products = stock.filter((item) => Number(item.planned_quantity) > 0);

    return {
        title: 'Trip loading sheet',
        reference: trip?.code || delivery.trip_code || delivery.code,
        facts: [
            ['Planned date', trip?.planned_date || delivery.planned_date],
            ['Warehouse', identity(delivery.warehouse_code, delivery.warehouse_name)],
            ['Route', trip?.route_name || delivery.route_name],
            ['Driver', identity(delivery.driver_code, delivery.driver_name)],
            ['Vehicle', identity(delivery.vehicle_code, delivery.plate_no)],
            ['Stops', stops.length],
            ['Status', String(trip?.status || delivery.status || '').replaceAll('_', ' ')],
        ],
        columns: ['Product', 'SKU', 'Unit', 'Quantity to load'],
        rows: products.map((item) => [item.product_name, item.product_sku, item.unit, quantity(item.planned_quantity)]),
        total: ['Total units to load', quantity(products.reduce((sum, item) => sum + Number(item.planned_quantity), 0))],
        notes: ['Combined planned stock for all stops on this trip.', delivery.notes].filter(Boolean).join('\n'),
        signatures: ['Prepared by', 'Warehouse signature', 'Driver signature'],
    };
}

export function printTripLoadingSheet(detail) {
    return printConfiguredDocument('trip_loading_sheet', buildTripLoadingDocument(detail), {
        paper_size: 'A4',
        orientation: 'portrait',
    });
}
