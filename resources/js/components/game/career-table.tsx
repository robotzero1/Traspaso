import { Link } from '@inertiajs/react';
import Heading from '@/components/heading';
import { formatCents, formatPercent } from '@/lib/format';
import { show } from '@/routes/games';

export type CareerEntry = {
    game_id: number;
    current: boolean;
    name: string | null;
    neighbourhood: string | null;
    started_on: string | null;
    ended_on: string | null;
    traspaso_cents: number | null;
    months: number;
    profit_cents: number;
    owner_pay_cents: number;
    outcome: 'sold' | 'closed' | 'bankrupt' | 'running' | 'choosing';
    sold_for_cents: number | null;
    starting_capital_cents: number;
    net_worth_cents: number;
};

const outcomeText = (e: CareerEntry): string =>
    ({
        sold: `sold for ${formatCents(e.sold_for_cents ?? 0)}`,
        closed: 'closed down',
        bankrupt: 'bankrupt',
        running: 'running',
        choosing: 'choosing a café',
    })[e.outcome];

/** Every café of the owner's career (SPEC §12), with net worth across them all. */
export function CareerTable({ career }: { career: CareerEntry[] }) {
    const first = career[0];
    const last = career[career.length - 1];
    const change = last.net_worth_cents / first.starting_capital_cents - 1;

    return (
        <section className="space-y-3 rounded-xl border p-4">
            <Heading
                variant="small"
                title="Your career"
                description={`Started with ${formatCents(first.starting_capital_cents)}; worth ${formatCents(last.net_worth_cents)} now (${formatPercent(change, 1)}).`}
            />
            <div className="overflow-x-auto">
                <table className="w-full text-sm">
                    <thead className="text-left text-muted-foreground">
                        <tr>
                            <th className="py-1 pr-3 font-normal">Café</th>
                            <th className="py-1 pr-3 font-normal">Traspaso</th>
                            <th className="py-1 pr-3 font-normal">Months</th>
                            <th className="py-1 pr-3 font-normal">
                                Profit before your pay
                            </th>
                            <th className="py-1 font-normal">Outcome</th>
                        </tr>
                    </thead>
                    <tbody>
                        {career.map((e) => (
                            <tr
                                key={e.game_id}
                                className={e.current ? 'font-medium' : ''}
                            >
                                <td className="py-1 pr-3">
                                    <Link
                                        href={show(e.game_id)}
                                        className="underline-offset-2 hover:underline"
                                    >
                                        {e.name ?? '—'}
                                    </Link>
                                    {e.neighbourhood && (
                                        <span className="block text-xs text-muted-foreground">
                                            {e.neighbourhood}
                                        </span>
                                    )}
                                </td>
                                <td className="py-1 pr-3 tabular-nums">
                                    {e.traspaso_cents === null
                                        ? '—'
                                        : formatCents(e.traspaso_cents)}
                                </td>
                                <td className="py-1 pr-3 tabular-nums">
                                    {e.months}
                                </td>
                                <td className="py-1 pr-3 tabular-nums">
                                    {formatCents(e.profit_cents)}
                                </td>
                                <td className="py-1">{outcomeText(e)}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </section>
    );
}
