import { formatCents, humanize } from '@/lib/format';
import type { LatestDay, MonthToDate } from '@/types/game';

const weatherLabel = {
    fair: 'Fair',
    rain: 'Rain',
    hot: 'Hot',
} as const;

/**
 * The last day your café traded, and the month so far: what the app opens
 * on, and what the nightly notification links to. Phone-sized first.
 */
export function LatestDayCard({
    day,
    monthToDate,
}: {
    day: LatestDay;
    monthToDate: MonthToDate | null;
}) {
    const date = new Date(`${day.date}T12:00:00`).toLocaleDateString('en-GB', {
        weekday: 'long',
        day: 'numeric',
        month: 'long',
    });

    return (
        <section
            aria-label="Latest day"
            className="space-y-3 rounded-xl border p-4"
        >
            <div className="flex items-baseline justify-between gap-2">
                <h2 className="font-medium">{date}</h2>
                <span className="text-sm text-muted-foreground">
                    {weatherLabel[day.weather]}
                    {day.open && day.weather === 'rain' && !day.terrace_usable
                        ? ' · terrace shut'
                        : ''}
                </span>
            </div>

            {day.open ? (
                <dl className="grid grid-cols-2 gap-3 tabular-nums">
                    <div>
                        <dt className="text-xs text-muted-foreground">
                            Customers
                        </dt>
                        <dd className="text-2xl font-semibold">
                            {day.customers.toLocaleString('es-ES')}
                        </dd>
                    </div>
                    <div>
                        <dt className="text-xs text-muted-foreground">
                            Takings (net of IVA)
                        </dt>
                        <dd className="text-2xl font-semibold">
                            {formatCents(day.revenue_cents)}
                        </dd>
                    </div>
                </dl>
            ) : (
                <p className="text-sm text-muted-foreground">Closed.</p>
            )}

            {day.events.length > 0 && (
                <ul className="space-y-1 text-sm">
                    {day.events.map((e) => (
                        <li key={e}>{humanize(e)}</li>
                    ))}
                </ul>
            )}

            <p className="border-t pt-2 text-sm text-muted-foreground tabular-nums">
                {monthToDate && (
                    <>
                        Month {day.month} so far:{' '}
                        {formatCents(monthToDate.revenue_cents)} from{' '}
                        {monthToDate.customers.toLocaleString('es-ES')}{' '}
                        customers over {monthToDate.days_open}{' '}
                        {monthToDate.days_open === 1 ? 'day' : 'days'}{' '}
                        open.{' '}
                    </>
                )}
                Cash {formatCents(day.cash_after_cents)}; the month's bills go
                out on its last day.
            </p>
        </section>
    );
}
