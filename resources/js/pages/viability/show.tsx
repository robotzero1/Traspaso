import { Head, Link, router } from '@inertiajs/react';
import { useEffect } from 'react';
import { PinMap } from '@/components/viability/pin-map';
import { formatCents, humanize } from '@/lib/format';
import { BuyForm } from '@/components/payments/buy-form';
import { disclaimer, privacy, terms } from '@/routes/legal';
import payments from '@/routes/payments';
import { create } from '@/routes/viability';

type Spot = {
    neighbourhood: string;
    street_type: string;
    footfall: number;
    footfall_by_day_part: Record<string, number>;
    footfall_percentile: number;
    rivals_within_150m: number;
    rivals_within_500m: number;
};

type Full = {
    runs: number;
    years: number;
    open: Record<string, number>;
    profit_by_year: {
        year: number;
        open: number;
        median: number | null;
        p10: number | null;
        p90: number | null;
    }[];
    sales_year_1_cents: number;
    payback: { median_months: number | null; share: number };
    // Reports made before milestone 21 don't have it.
    resale?: {
        year: number;
        value_cents: Spread | null;
        net_cents: Spread | null;
        total_return_cents: Spread | null;
        ahead: number;
    };
    cash_after_purchase_cents: number;
    owner_pay_month_cents: number;
    risks: { type: string; value: number }[];
};

type Spread = { median: number; p10: number; p90: number };

type Props = {
    report: {
        uuid: string;
        status: 'queued' | 'running' | 'done' | 'failed';
        inputs: Record<string, number | string | string[]> & {
            lat: number;
            lng: number;
        };
        unlocked: boolean;
    };
    map: { tile_url: string; attribution: string };
    preview: {
        runs: number;
        years: number;
        spot: Spot;
        open_year_1: number;
    } | null;
    full: Full | null;
    price_cents: number;
    payments_enabled: boolean;
};

const pct = (v: number) => `${Math.round(v * 100)}%`;

/** A viability report: the free preview, and the full report once paid. */
export default function ViabilityShow({
    report,
    map,
    preview,
    full,
    price_cents,
    payments_enabled,
}: Props) {
    const waiting = report.status === 'queued' || report.status === 'running';

    // The check runs on the queue (about half a minute): look again until it's done.
    useEffect(() => {
        if (!waiting) {
            return;
        }

        const timer = window.setInterval(
            () => router.reload({ only: ['report', 'preview', 'full'] }),
            3000,
        );

        return () => window.clearInterval(timer);
    }, [waiting]);

    return (
        <>
            <Head title="Viability check" />
            <main className="mx-auto max-w-2xl space-y-6 p-4 sm:p-6">
                <header className="space-y-1">
                    <h1 className="text-2xl font-semibold">Viability check</h1>
                    <p className="text-sm text-muted-foreground">
                        Keep this page's link: it's the way back to your report.
                    </p>
                </header>

                <PinMap
                    tileUrl={map.tile_url}
                    attribution={map.attribution}
                    maxZoom={19}
                    centre={[report.inputs.lat, report.inputs.lng]}
                    pin={{ lat: report.inputs.lat, lng: report.inputs.lng }}
                    zoom={16}
                />

                {waiting && (
                    <p role="status" className="rounded-lg border p-4">
                        Running the simulation… This takes about half a minute.
                    </p>
                )}
                {report.status === 'failed' && (
                    <p role="alert" className="rounded-lg border p-4">
                        Something went wrong running this check.{' '}
                        <Link href={create()} className="underline">
                            Try again
                        </Link>
                        .
                    </p>
                )}

                {preview && <Preview preview={preview} />}
                {preview &&
                    (full ? (
                        <FullReport full={full} />
                    ) : (
                        <Locked
                            years={preview.years}
                            uuid={report.uuid}
                            priceCents={price_cents}
                            enabled={payments_enabled}
                        />
                    ))}

                <p className="text-xs text-muted-foreground">
                    A simulation, not financial advice: {preview?.runs ?? ''}{' '}
                    possible futures of this café on a model calibrated to real
                    closure rates (INE), real footfall estimates and the real
                    cafés and bars nearby. Map data © OpenStreetMap
                    contributors. <LegalLinks />
                </p>
            </main>
        </>
    );
}

function Preview({ preview }: { preview: NonNullable<Props['preview']> }) {
    const { spot } = preview;
    const topShare = Math.max(
        1,
        Math.round((1 - spot.footfall_percentile) * 100),
    );

    return (
        <section className="space-y-4 rounded-xl border p-4">
            <div>
                <div className="text-sm text-muted-foreground">
                    Still open after one year
                </div>
                <div className="text-4xl font-semibold tabular-nums">
                    {pct(preview.open_year_1)}
                </div>
                <p className="text-sm text-muted-foreground">
                    of {preview.runs.toLocaleString('en')} simulated futures. A
                    typical new café in Spain: 75–80%.
                </p>
            </div>
            <dl className="grid gap-3 text-sm sm:grid-cols-2">
                <div>
                    <dt className="text-muted-foreground">Footfall here</dt>
                    <dd>
                        {spot.footfall.toFixed(1)} / 10 (busier than{' '}
                        {100 - topShare}% of Zaragoza's shopping streets)
                    </dd>
                </div>
                <div>
                    <dt className="text-muted-foreground">District</dt>
                    <dd>{spot.neighbourhood}</dd>
                </div>
                <div>
                    <dt className="text-muted-foreground">
                        Cafés and bars nearby
                    </dt>
                    <dd>
                        {spot.rivals_within_150m} within 150 m,{' '}
                        {spot.rivals_within_500m} within 500 m
                    </dd>
                </div>
                <div>
                    <dt className="text-muted-foreground">
                        Footfall by time of day
                    </dt>
                    <dd>
                        {Object.entries(spot.footfall_by_day_part)
                            .map(([p, f]) => `${humanize(p)} ${f.toFixed(1)}`)
                            .join(' · ')}
                    </dd>
                </div>
            </dl>
        </section>
    );
}

function Locked({
    years,
    uuid,
    priceCents,
    enabled,
}: {
    years: number;
    uuid: string;
    priceCents: number;
    enabled: boolean;
}) {
    return (
        <section className="space-y-2 rounded-xl border border-dashed p-4">
            <h2 className="font-medium">The full {years}-year report</h2>
            <p className="text-sm text-muted-foreground">
                The chance it's still open after 3 and {years} years, what it
                would pay you each year, when you'd earn back the traspaso, and
                the main risks.
            </p>
            <BuyForm
                action={payments.viability(uuid)}
                label="Buy the full report"
                priceCents={priceCents}
                enabled={enabled}
            />
        </section>
    );
}

function FullReport({ full }: { full: Full }) {
    const months = full.payback.median_months;

    return (
        <section className="space-y-5 rounded-xl border p-4">
            <h2 className="font-medium">The full {full.years}-year report</h2>

            <div className="grid grid-cols-3 gap-3 text-center tabular-nums">
                {[1, 3, full.years].map((y) => (
                    <div key={y}>
                        <div className="text-2xl font-semibold">
                            {pct(full.open[y] ?? 0)}
                        </div>
                        <div className="text-xs text-muted-foreground">
                            open after {y} {y === 1 ? 'year' : 'years'}
                        </div>
                    </div>
                ))}
            </div>
            <p className="text-xs text-muted-foreground">
                A typical new food and drink business in Spain: 75–80% after one
                year, 45–50% after five (INE).
            </p>

            <div className="space-y-2">
                <h3 className="text-sm font-medium">
                    What the café would make each year, before your pay
                </h3>
                <table className="w-full text-sm tabular-nums">
                    <thead className="text-left text-muted-foreground">
                        <tr>
                            <th className="font-normal">Year</th>
                            <th className="text-right font-normal">Typical</th>
                            <th className="text-right font-normal">
                                Range (8 in 10)
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        {full.profit_by_year.map((r) => (
                            <tr key={r.year} className="border-t">
                                <td className="py-1">{r.year}</td>
                                <td className="text-right">
                                    {r.median === null
                                        ? '—'
                                        : formatCents(r.median)}
                                </td>
                                <td className="text-right">
                                    {r.p10 === null || r.p90 === null
                                        ? '—'
                                        : `${formatCents(r.p10)} to ${formatCents(r.p90)}`}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
                <p className="text-xs text-muted-foreground">
                    Among the cafés still open that year. You'd live on this:
                    the model assumes you take{' '}
                    {formatCents(full.owner_pay_month_cents)} a month. Expected
                    sales in year one: {formatCents(full.sales_year_1_cents)}.
                </p>
            </div>

            <div className="text-sm">
                <h3 className="font-medium">Earning back the traspaso</h3>
                <p>
                    {months === null
                        ? `In the typical future, what's left after your pay doesn't add up to the traspaso within ${full.years} years.`
                        : `Typically after ${months} months, from what's left after your pay.`}{' '}
                    ({pct(full.payback.share)} of futures earn it back within{' '}
                    {full.years} years.)
                </p>
            </div>

            {full.resale && <Resale resale={full.resale} />}

            <div className="text-sm">
                <h3 className="font-medium">What to watch</h3>
                {full.risks.length === 0 ? (
                    <p>Nothing stands out from a typical café.</p>
                ) : (
                    <ul className="list-disc space-y-1 pl-5">
                        {full.risks.map((r) => (
                            <li key={r.type}>{riskText(r)}</li>
                        ))}
                    </ul>
                )}
                <p className="pt-1 text-muted-foreground">
                    Cash left after buying:{' '}
                    {formatCents(full.cash_after_purchase_cents)}.
                </p>
            </div>
        </section>
    );
}

function Resale({ resale }: { resale: NonNullable<Full['resale']> }) {
    const range = (s: Spread) =>
        `${formatCents(s.p10)} to ${formatCents(s.p90)}`;

    return (
        <div className="space-y-1 text-sm">
            <h3 className="font-medium">
                Selling after {resale.year} years, and what you'd end up with
            </h3>
            {resale.value_cents && resale.net_cents ? (
                <p>
                    If it's still open, the café would typically sell for{' '}
                    {formatCents(resale.value_cents.median)} (8 in 10:{' '}
                    {range(resale.value_cents)}), leaving you{' '}
                    {formatCents(resale.net_cents.median)} after the gestoría
                    and income tax on any gain.
                </p>
            ) : (
                <p>In most futures it isn't open after {resale.year} years.</p>
            )}
            {resale.total_return_cents && (
                <p>
                    All told, over every future, closures included: you'd
                    typically end up{' '}
                    {resale.total_return_cents.median >= 0
                        ? `${formatCents(resale.total_return_cents.median)} ahead`
                        : `${formatCents(-resale.total_return_cents.median)} down`}{' '}
                    on the money you put in, after paying yourself (8 in 10:{' '}
                    {range(resale.total_return_cents)}). {pct(resale.ahead)} of
                    futures end ahead.
                </p>
            )}
            <p className="text-xs text-muted-foreground">
                A buyer pays for the premises and fit-out, and for goodwill from
                the last year's profit after the owner's pay.
            </p>
        </div>
    );
}

function riskText(risk: { type: string; value: number }): string {
    switch (risk.type) {
        case 'rent_share':
            return `Rent is ${pct(risk.value)} of expected sales; cafés usually pay 8–15%.`;
        case 'quiet_spot':
            return `A quiet spot: footfall ${risk.value.toFixed(1)} out of 10.`;
        case 'crowded':
            return `Crowded: ${risk.value} cafés and bars within 150 m.`;
        case 'first_year':
            return `Riskier than a typical new café in its first year (${pct(risk.value)} still open, against 75–80%).`;
        case 'thin_cash':
            return `Little cash left after buying: about ${risk.value} months of rent and your pay.`;
        default:
            return humanize(risk.type);
    }
}

export function LegalLinks() {
    return (
        <span className="space-x-2">
            <Link href={terms()} className="underline">
                Terms
            </Link>
            <Link href={privacy()} className="underline">
                Privacy
            </Link>
            <Link href={disclaimer()} className="underline">
                Disclaimer
            </Link>
        </span>
    );
}
