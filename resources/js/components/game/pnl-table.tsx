import { formatCents, humanize, monthName } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { MonthResultRow } from '@/types/game';

type Line = {
    label: string;
    value: (r: MonthResultRow) => number;
    kind?: 'cost' | 'total' | 'subtotal';
};

const lines: Line[] = [
    { label: 'Sales', value: (r) => r.revenue_cents - r.event_revenue_cents },
    { label: 'Event income', value: (r) => r.event_revenue_cents },
    { label: 'Revenue', value: (r) => r.revenue_cents, kind: 'subtotal' },
    { label: 'Stock (COGS)', value: (r) => r.cogs_cents, kind: 'cost' },
    { label: 'Staff', value: (r) => r.staff_cents, kind: 'cost' },
    { label: 'Rent', value: (r) => r.rent_cents, kind: 'cost' },
    { label: 'Utilities', value: (r) => r.utilities_cents, kind: 'cost' },
    { label: 'Marketing', value: (r) => r.marketing_cents, kind: 'cost' },
    {
        label: 'Other (cuota, insurance, maintenance, events)',
        value: (r) => r.other_cents,
        kind: 'cost',
    },
    { label: 'Taxes', value: (r) => r.taxes_cents, kind: 'cost' },
    { label: 'Profit', value: (r) => r.profit_cents, kind: 'total' },
];

/** Profit and loss: one column per month and a year-to-date total. */
export function PnlTable({ results }: { results: MonthResultRow[] }) {
    const total = (line: Line) =>
        results.reduce((sum, r) => sum + line.value(r), 0);
    const hasEventIncome = results.some((r) => r.event_revenue_cents > 0);

    return (
        <div className="overflow-x-auto rounded-lg border">
            <table className="w-full text-sm tabular-nums">
                <caption className="sr-only">Profit and loss by month</caption>
                <thead className="bg-muted">
                    <tr>
                        <th
                            scope="col"
                            className="sticky left-0 bg-muted p-2 text-left font-medium"
                        >
                            &nbsp;
                        </th>
                        {results.map((r) => (
                            <th
                                key={r.month}
                                scope="col"
                                className="p-2 text-right font-medium whitespace-nowrap"
                            >
                                {r.month}.{' '}
                                {monthName(r.calendar_month).slice(0, 3)}
                            </th>
                        ))}
                        <th scope="col" className="p-2 text-right font-medium">
                            Total
                        </th>
                    </tr>
                </thead>
                <tbody>
                    {lines.map((line) => {
                        // Sales and event income only need their own lines when there was event income.
                        if (
                            !hasEventIncome &&
                            (line.label === 'Event income' ||
                                line.label === 'Sales')
                        ) {
                            return null;
                        }

                        const emphasised =
                            line.kind === 'total' || line.kind === 'subtotal';

                        return (
                            <tr
                                key={line.label}
                                className={cn(
                                    'border-t',
                                    emphasised && 'font-medium',
                                    line.kind === 'total' && 'border-t-2',
                                )}
                            >
                                <th
                                    scope="row"
                                    className={cn(
                                        'sticky left-0 bg-background p-2 text-left font-normal whitespace-nowrap',
                                        emphasised && 'font-medium',
                                        line.kind === 'cost' &&
                                            'pl-5 text-muted-foreground',
                                    )}
                                >
                                    {line.label}
                                </th>
                                {results.map((r) => (
                                    <Cell
                                        key={r.month}
                                        cents={line.value(r)}
                                        line={line}
                                    />
                                ))}
                                <Cell cents={total(line)} line={line} />
                            </tr>
                        );
                    })}
                    <tr className="border-t text-muted-foreground">
                        <th
                            scope="row"
                            className="sticky left-0 bg-background p-2 text-left font-normal"
                        >
                            Customers
                        </th>
                        {results.map((r) => (
                            <td key={r.month} className="p-2 text-right">
                                {r.customers.toLocaleString('es-ES')}
                            </td>
                        ))}
                        <td className="p-2 text-right">
                            {results
                                .reduce((s, r) => s + r.customers, 0)
                                .toLocaleString('es-ES')}
                        </td>
                    </tr>
                    <tr className="border-t text-muted-foreground">
                        <th
                            scope="row"
                            className="sticky left-0 bg-background p-2 text-left font-normal"
                        >
                            Cash at month end
                        </th>
                        {results.map((r) => (
                            <td
                                key={r.month}
                                className="p-2 text-right whitespace-nowrap"
                            >
                                {formatCents(r.cash_after_cents)}
                            </td>
                        ))}
                        <td className="p-2" />
                    </tr>
                </tbody>
            </table>
        </div>
    );
}

function Cell({ cents, line }: { cents: number; line: Line }) {
    const shown = line.kind === 'cost' && cents !== 0 ? -cents : cents;

    return (
        <td
            className={cn(
                'p-2 text-right whitespace-nowrap',
                line.kind === 'total' &&
                    cents < 0 &&
                    'text-red-600 dark:text-red-400',
            )}
        >
            {formatCents(shown)}
        </td>
    );
}

/** Last month by time of day: who came, who was turned away. */
export function DayPartBreakdown({ result }: { result: MonthResultRow }) {
    const most = Math.max(
        ...result.day_parts.map((p) => Math.max(p.demand, p.covers)),
        1,
    );

    return (
        <div className="space-y-3">
            {result.day_parts.map((p) => (
                <div key={p.day_part} className="space-y-1 text-sm">
                    <div className="flex justify-between gap-2">
                        <span className="font-medium">
                            {humanize(p.day_part)}
                        </span>
                        <span className="text-muted-foreground tabular-nums">
                            {p.covers.toLocaleString('es-ES')} customers ·{' '}
                            {formatCents(p.revenue_cents)}
                        </span>
                    </div>
                    <div className="flex h-2 gap-[2px]" aria-hidden>
                        <div
                            className="h-2 rounded-l bg-[var(--viz-series-1)]"
                            style={{ width: `${(p.covers / most) * 100}%` }}
                        />
                        {p.demand > p.covers && (
                            <div
                                className="h-2 rounded-r bg-[var(--viz-negative)]"
                                style={{
                                    width: `${((p.demand - p.covers) / most) * 100}%`,
                                }}
                            />
                        )}
                    </div>
                    {p.demand > p.covers && (
                        <div className="text-xs text-muted-foreground">
                            {(p.demand - p.covers).toLocaleString('es-ES')}{' '}
                            turned away — not enough seats or staff
                        </div>
                    )}
                </div>
            ))}
        </div>
    );
}
