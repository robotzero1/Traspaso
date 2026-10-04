import {
    Bar,
    BarChart,
    CartesianGrid,
    Cell,
    Line,
    LineChart,
    ReferenceLine,
    ResponsiveContainer,
    Tooltip,
    XAxis,
    YAxis,
} from 'recharts';
import type { TooltipContentProps } from 'recharts';
import { formatCents, monthName } from '@/lib/format';
import type { MonthResultRow } from '@/types/game';

/*
 * Two single-measure charts rather than one dual-axis chart: cash (a level)
 * and monthly profit (a change, with sign). Tokens come from app.css so
 * light and dark mode each use their own validated steps.
 */

const axisTick = { fill: 'var(--viz-muted)', fontSize: 12 };

type Point = { month: number; label: string; cents: number };

const compact = new Intl.NumberFormat('es-ES', { maximumFractionDigits: 1 });

/** Axis labels: "45k €", "1,5k €", "550 €" — precise enough that ticks never repeat. */
function compactEuros(cents: number): string {
    const euros = cents / 100;

    if (Math.abs(euros) >= 10_000) {
        return `${Math.round(euros / 1000)}k €`;
    }

    return Math.abs(euros) >= 1000
        ? `${compact.format(euros / 1000)}k €`
        : `${Math.round(euros)} €`;
}

function ChartTooltip({
    active,
    payload,
    title,
}: Partial<TooltipContentProps<number, string>> & { title: string }) {
    if (!active || !payload?.length) {
        return null;
    }

    const point = payload[0].payload as Point;

    return (
        <div className="rounded-md border bg-popover px-3 py-2 text-sm text-popover-foreground shadow-sm">
            <div className="text-muted-foreground">{point.label}</div>
            <div>
                {title}:{' '}
                <span className="font-medium">{formatCents(point.cents)}</span>
            </div>
        </div>
    );
}

export function CashChart({
    openingCashCents,
    results,
}: {
    openingCashCents: number;
    results: MonthResultRow[];
}) {
    const data: Point[] = [
        { month: 0, label: 'After buying', cents: openingCashCents },
        ...results.map((r) => ({
            month: r.month,
            label: `Month ${r.month} · ${monthName(r.calendar_month)}`,
            cents: r.cash_after_cents,
        })),
    ];
    const last = data[data.length - 1];

    return (
        <figure className="space-y-2">
            <figcaption className="text-sm font-medium">
                Cash at the end of each month
                <span className="ml-2 font-normal text-muted-foreground">
                    now {formatCents(last.cents)}
                </span>
            </figcaption>
            <div
                className="h-56"
                role="img"
                aria-label={`Cash by month, from ${formatCents(openingCashCents)} to ${formatCents(last.cents)}`}
            >
                <ResponsiveContainer width="100%" height="100%">
                    <LineChart
                        data={data}
                        margin={{ top: 8, right: 16, bottom: 0, left: 8 }}
                    >
                        <CartesianGrid
                            vertical={false}
                            stroke="var(--viz-grid)"
                        />
                        <XAxis
                            dataKey="month"
                            tick={axisTick}
                            tickLine={false}
                            axisLine={{ stroke: 'var(--viz-baseline)' }}
                        />
                        <YAxis
                            tickFormatter={compactEuros}
                            tick={axisTick}
                            tickLine={false}
                            axisLine={false}
                            width={56}
                        />
                        {/* Below zero the game is over. */}
                        <ReferenceLine y={0} stroke="var(--viz-baseline)" />
                        <Tooltip
                            content={<ChartTooltip title="Cash" />}
                            cursor={{
                                stroke: 'var(--viz-baseline)',
                                strokeWidth: 1,
                            }}
                        />
                        <Line
                            type="linear"
                            dataKey="cents"
                            stroke="var(--viz-series-1)"
                            strokeWidth={2}
                            dot={{
                                r: 3,
                                fill: 'var(--viz-series-1)',
                                strokeWidth: 0,
                            }}
                            activeDot={{
                                r: 5,
                                stroke: 'var(--background)',
                                strokeWidth: 2,
                            }}
                            isAnimationActive={false}
                        />
                    </LineChart>
                </ResponsiveContainer>
            </div>
        </figure>
    );
}

type BarShapeProps = {
    x?: number;
    y?: number;
    width?: number;
    height?: number;
    fill?: string;
    payload?: Point;
};

/** A bar rounded only at its data end (away from zero), square at the baseline. */
function DataEndBar({
    x = 0,
    y = 0,
    width = 0,
    height = 0,
    fill,
    payload,
}: BarShapeProps) {
    const top = Math.min(y, y + height);
    const h = Math.abs(height);
    const r = Math.min(4, h, width / 2);

    if (h === 0) {
        return null;
    }

    const path =
        (payload?.cents ?? 0) >= 0
            ? `M${x},${top + h} V${top + r} Q${x},${top} ${x + r},${top} H${x + width - r} Q${x + width},${top} ${x + width},${top + r} V${top + h} Z`
            : `M${x},${top} V${top + h - r} Q${x},${top + h} ${x + r},${top + h} H${x + width - r} Q${x + width},${top + h} ${x + width},${top + h - r} V${top} Z`;

    return <path d={path} fill={fill} />;
}

export function ProfitChart({ results }: { results: MonthResultRow[] }) {
    const data: Point[] = results.map((r) => ({
        month: r.month,
        label: `Month ${r.month} · ${monthName(r.calendar_month)}`,
        cents: r.profit_cents,
    }));
    const total = data.reduce((sum, p) => sum + p.cents, 0);

    return (
        <figure className="space-y-2">
            <figcaption className="text-sm font-medium">
                Profit each month
                <span className="ml-2 font-normal text-muted-foreground">
                    {total >= 0 ? 'profit' : 'loss'} so far {formatCents(total)}
                </span>
            </figcaption>
            <div
                className="h-56"
                role="img"
                aria-label={`Monthly profit; total so far ${formatCents(total)}`}
            >
                <ResponsiveContainer width="100%" height="100%">
                    <BarChart
                        data={data}
                        margin={{ top: 8, right: 16, bottom: 0, left: 8 }}
                    >
                        <CartesianGrid
                            vertical={false}
                            stroke="var(--viz-grid)"
                        />
                        <XAxis
                            dataKey="month"
                            tick={axisTick}
                            tickLine={false}
                            axisLine={false}
                        />
                        <YAxis
                            tickFormatter={compactEuros}
                            tick={axisTick}
                            tickLine={false}
                            axisLine={false}
                            width={56}
                        />
                        <ReferenceLine y={0} stroke="var(--viz-baseline)" />
                        <Tooltip
                            content={<ChartTooltip title="Profit" />}
                            cursor={{ fill: 'var(--viz-grid)', opacity: 0.5 }}
                        />
                        <Bar
                            dataKey="cents"
                            maxBarSize={24}
                            shape={(props: BarShapeProps) => (
                                <DataEndBar {...props} />
                            )}
                            isAnimationActive={false}
                        >
                            {data.map((p) => (
                                <Cell
                                    key={p.month}
                                    fill={
                                        p.cents >= 0
                                            ? 'var(--viz-series-1)'
                                            : 'var(--viz-negative)'
                                    }
                                />
                            ))}
                        </Bar>
                    </BarChart>
                </ResponsiveContainer>
            </div>
            <p className="text-xs text-muted-foreground">
                Bars above the line are profit, below are losses.
            </p>
        </figure>
    );
}
