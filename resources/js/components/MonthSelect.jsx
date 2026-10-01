import { useMemo } from 'react';

const monthValue = (date) => `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}`;

export function MonthSelect({ value, onChange, months = 120, ...props }) {
    const options = useMemo(() => {
        const current = new Date();
        const items = Array.from({ length: months }, (_, index) => {
            const date = new Date(current.getFullYear(), current.getMonth() - index, 1);
            return {
                value: monthValue(date),
                label: new Intl.DateTimeFormat(undefined, { month: 'long', year: 'numeric' }).format(date),
            };
        });

        if (value && !items.some((item) => item.value === value)) {
            const [year, month] = String(value).split('-').map(Number);
            if (year && month >= 1 && month <= 12) {
                const date = new Date(year, month - 1, 1);
                items.push({
                    value,
                    label: new Intl.DateTimeFormat(undefined, { month: 'long', year: 'numeric' }).format(date),
                });
            }
        }

        return items;
    }, [months, value]);

    return <select {...props} value={value} onChange={onChange}>{options.map((option) => <option value={option.value} key={option.value}>{option.label}</option>)}</select>;
}

