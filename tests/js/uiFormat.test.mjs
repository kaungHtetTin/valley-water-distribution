import test from 'node:test';
import assert from 'node:assert/strict';
import { businessToday, formatDate, formatDateTime, formatMoney, formatNumber, setFormattingLocale } from '../../resources/js/ui/format.js';

test('business dates roll over in Yangon regardless of the browser timezone', () => {
    assert.equal(businessToday(new Date('2026-11-01T18:00:00Z')), '2026-11-02');
    assert.equal(businessToday(new Date('2026-11-01T17:00:00Z')), '2026-11-01');
});

test('calendar days and server local timestamps retain their business meaning', () => {
    setFormattingLocale('en');
    assert.equal(formatDate('2026-11-02'), formatDate('2026-11-02T05:30:00Z'));
    assert.equal(formatDateTime('2026-11-02 08:15:00'), formatDateTime('2026-11-02T01:45:00Z'));
    assert.equal(formatDate('invalid'), '—');
    assert.equal(formatDate(null), '—');
});

test('language changes immediately update quantities and MMK formatting', () => {
    setFormattingLocale('en');
    assert.equal(formatMoney(123456.4), '123,456 MMK');
    assert.equal(formatNumber(12.345), '12.35');
    setFormattingLocale('my');
    assert.equal(formatMoney(123456.4), `${new Intl.NumberFormat('my-MM', { maximumFractionDigits: 0 }).format(123456.4)} MMK`);
    setFormattingLocale('en');
});
