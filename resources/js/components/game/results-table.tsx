import { formatCents, humanize, monthName } from '@/lib/format';
import type { MonthResultRow } from '@/types/game';

/** A plain P&L table; charts come in milestone 6. */
export function ResultsTable({ results }: { results: MonthResultRow[] }) {
    if (results.length === 0) {
        return (
            <p className="text-sm text-muted-foreground">
                No months played yet.
            </p>
        );
    }

    const last = results[results.length - 1];

    return (
        <div className="space-y-4">
            <div className="overflow-x-auto rounded-lg border">
                <table className="w-full text-sm">
                    <thead className="bg-muted/50 text-right">
                        <tr>
                            <th className="p-2 text-left">Month</th>
                            <th className="p-2">Customers</th>
                            <th className="p-2">Revenue</th>
                            <th className="p-2">Costs</th>
                            <th className="p-2">Profit</th>
                            <th className="p-2">Cash</th>
                        </tr>
                    </thead>
                    <tbody className="text-right">
                        {results.map((r) => (
                            <tr key={r.month} className="border-t">
                                <td className="p-2 text-left">
                                    {r.month}. {monthName(r.calendar_month)}
                                </td>
                                <td className="p-2">
                                    {r.customers.toLocaleString('es-ES')}
                                </td>
                                <td className="p-2">
                                    {formatCents(r.revenue_cents)}
                                </td>
                                <td className="p-2">
                                    {formatCents(
                                        r.revenue_cents - r.profit_cents,
                                    )}
                                </td>
                                <td
                                    className={`p-2 ${r.profit_cents < 0 ? 'text-red-600' : ''}`}
                                >
                                    {formatCents(r.profit_cents)}
                                </td>
                                <td className="p-2">
                                    {formatCents(r.cash_after_cents)}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>

            <div className="grid gap-4 md:grid-cols-2">
                <div className="rounded-lg border p-3 text-sm">
                    <div className="mb-2 font-medium">Last month's costs</div>
                    {(
                        [
                            ['Stock (COGS)', last.cogs_cents],
                            ['Staff', last.staff_cents],
                            ['Rent', last.rent_cents],
                            ['Utilities', last.utilities_cents],
                            ['Marketing', last.marketing_cents],
                            [
                                'Other (cuota, insurance, events…)',
                                last.other_cents,
                            ],
                            ['Taxes', last.taxes_cents],
                        ] as const
                    ).map(([label, cents]) => (
                        <div key={label} className="flex justify-between">
                            <span>{label}</span>
                            <span>{formatCents(cents)}</span>
                        </div>
                    ))}
                </div>
                <div className="rounded-lg border p-3 text-sm">
                    <div className="mb-2 font-medium">
                        Last month by time of day
                    </div>
                    {last.day_parts.map((p) => (
                        <div
                            key={p.day_part}
                            className="flex justify-between gap-2"
                        >
                            <span>{humanize(p.day_part)}</span>
                            <span>
                                {p.covers.toLocaleString('es-ES')} customers ·{' '}
                                {formatCents(p.revenue_cents)}
                                {p.demand > p.covers && (
                                    <span className="text-amber-600">
                                        {' '}
                                        · {p.demand - p.covers} turned away
                                    </span>
                                )}
                            </span>
                        </div>
                    ))}
                </div>
            </div>
        </div>
    );
}
