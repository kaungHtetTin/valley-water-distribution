export const TIME_ZONE = 'Asia/Yangon';
let activeLocale;
export const setFormattingLocale = (locale) => { activeLocale = locale; };
export const currentLocale = () => (activeLocale || (typeof document !== 'undefined' ? document.querySelector('.app-root')?.dataset.locale : 'en')) === 'my' ? 'my-MM' : 'en-GB';

export function formatNumber(value, options = {}) {
    return new Intl.NumberFormat(currentLocale(), { maximumFractionDigits: 2, ...options }).format(Number(value || 0));
}

export function formatMoney(value) {
    return `${formatNumber(value, { maximumFractionDigits: 0 })} MMK`;
}

export function formatDate(value, options = {}) {
    if (!value) return '—';
    // Date-only values represent a calendar day; parse at noon in the business timezone.
    const raw = String(value);
    let normalized = raw.replace(' ', 'T');
    if (/^\d{4}-\d{2}-\d{2}$/.test(raw)) normalized = `${raw}T12:00:00+06:30`;
    else if (/^\d{4}-\d{2}-\d{2}T[\d:.]+$/.test(normalized)) normalized += '+06:30';
    const date = value instanceof Date ? value : new Date(normalized);
    if (Number.isNaN(date.getTime())) return '—';
    return new Intl.DateTimeFormat(currentLocale(), { timeZone: TIME_ZONE, year: 'numeric', month: 'short', day: '2-digit', ...options }).format(date);
}

export const formatDateTime = (value) => formatDate(value, { hour: '2-digit', minute: '2-digit' });
export const businessToday = (date = new Date()) => new Intl.DateTimeFormat('en-CA', { timeZone: TIME_ZONE, year: 'numeric', month: '2-digit', day: '2-digit' }).format(date);
