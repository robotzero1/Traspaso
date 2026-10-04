const euros = new Intl.NumberFormat('es-ES', {
    style: 'currency',
    currency: 'EUR',
    maximumFractionDigits: 0,
});

/** Money is stored as integer cents and only formatted here, in the UI. */
export function formatCents(cents: number): string {
    return euros.format(cents / 100);
}

export function formatPercent(value: number, digits = 0): string {
    return `${value >= 0 ? '+' : ''}${(value * 100).toFixed(digits)}%`;
}

const monthNames = [
    'January',
    'February',
    'March',
    'April',
    'May',
    'June',
    'July',
    'August',
    'September',
    'October',
    'November',
    'December',
];

export function monthName(calendarMonth: number): string {
    return monthNames[calendarMonth - 1] ?? '';
}

/** "equipment_failure" → "Equipment failure" */
export function humanize(value: string): string {
    const words = value.replaceAll('_', ' ');

    return words.charAt(0).toUpperCase() + words.slice(1);
}
