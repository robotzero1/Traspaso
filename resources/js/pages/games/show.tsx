import { Form, Head } from '@inertiajs/react';
import { useState } from 'react';
import EndController from '@/actions/App/Http/Controllers/Game/EndController';
import { BusinessBrowser } from '@/components/game/business-browser';
import { CashChart, ProfitChart } from '@/components/game/charts';
import {
    DecisionsForm,
    describeModifier,
    EffectsSummary,
} from '@/components/game/decisions-form';
import { GameHeader } from '@/components/game/game-header';
import { GameMap } from '@/components/game/game-map';
import { DayPartBreakdown, PnlTable } from '@/components/game/pnl-table';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { formatCents, formatPercent, humanize, monthName } from '@/lib/format';
import { cn } from '@/lib/utils';
import { index } from '@/routes/games';
import type {
    BusinessForSale,
    BusinessStateProps,
    Competitor,
    CostHints,
    DayPartValue,
    DecisionLimits,
    Decisions,
    GameEventRow,
    GameSummary,
    MapProps,
    MonthResultRow,
    PendingEvent,
} from '@/types/game';

type Props = {
    game: GameSummary;
    map: MapProps;
    businesses?: BusinessForSale[];
    business?: BusinessForSale;
    state?: BusinessStateProps;
    business_value_cents?: number;
    decisions?: Decisions;
    decision_limits?: DecisionLimits;
    allowed_day_parts?: DayPartValue[];
    day_parts?: { value: DayPartValue; start_hour: number; end_hour: number }[];
    pending_events?: PendingEvent[];
    opening_cash_cents?: number;
    cost_hints?: CostHints;
    results?: MonthResultRow[];
    events?: GameEventRow[];
    competitors?: Competitor[];
};

type Tab =
    | 'decisions'
    | 'results'
    | 'business'
    | 'map'
    | 'competitors'
    | 'events';

export default function GameShow(props: Props) {
    const { game } = props;

    return (
        <>
            <Head title={game.business_name ?? 'Choose a business'} />
            <div className="flex flex-col gap-6 p-4">
                <GameHeader game={game} />

                {game.phase === 'browsing' && props.businesses && (
                    <section className="space-y-4">
                        <Heading
                            title="Businesses for sale"
                            description="Pick the café you want to take over. Click one for the details."
                        />
                        <BusinessBrowser
                            game={game}
                            businesses={props.businesses}
                            map={props.map}
                        />
                    </section>
                )}

                {game.phase === 'over' && (
                    <GameOver game={game} results={props.results ?? []} />
                )}
                {game.phase === 'ending' && (
                    <EndOfYear
                        game={game}
                        value={props.business_value_cents ?? 0}
                    />
                )}

                {props.business && props.state && (
                    <Playing
                        {...props}
                        business={props.business}
                        state={props.state}
                    />
                )}
            </div>
        </>
    );
}

function Playing(
    props: Props & { business: BusinessForSale; state: BusinessStateProps },
) {
    const { game } = props;
    const results = props.results ?? [];
    const pending = props.pending_events ?? [];
    const [tab, setTab] = useState<Tab>(
        game.phase === 'playing' ? 'decisions' : 'results',
    );

    const tabs: { id: Tab; label: string; badge?: number; show: boolean }[] = [
        {
            id: 'decisions',
            label: 'Decisions',
            badge: pending.length,
            show: game.phase === 'playing',
        },
        { id: 'results', label: 'Results', show: true },
        { id: 'business', label: 'Your business', show: true },
        { id: 'map', label: 'Map', show: true },
        { id: 'competitors', label: 'Competitors', show: true },
        {
            id: 'events',
            label: 'Events',
            badge: props.events?.length,
            show: true,
        },
    ];

    return (
        <div className="space-y-4">
            <div
                role="tablist"
                aria-label="Game"
                className="flex flex-wrap gap-1 border-b"
            >
                {tabs
                    .filter((t) => t.show)
                    .map((t) => (
                        <button
                            key={t.id}
                            role="tab"
                            type="button"
                            aria-selected={tab === t.id}
                            onClick={() => setTab(t.id)}
                            className={cn(
                                '-mb-px border-b-2 px-3 py-2 text-sm',
                                tab === t.id
                                    ? 'border-primary font-medium'
                                    : 'border-transparent text-muted-foreground hover:text-foreground',
                            )}
                        >
                            {t.label}
                            {!!t.badge && (
                                <Badge variant="secondary" className="ml-2">
                                    {t.badge}
                                </Badge>
                            )}
                        </button>
                    ))}
            </div>

            <div role="tabpanel">
                {tab === 'decisions' && props.decisions && (
                    <DecisionsForm
                        key={game.current_month}
                        gameId={game.id}
                        month={game.current_month}
                        decisions={props.decisions}
                        limits={props.decision_limits!}
                        dayParts={props.day_parts!}
                        allowedDayParts={props.allowed_day_parts!}
                        pendingEvents={pending}
                        costHints={props.cost_hints!}
                        lastMonth={results.at(-1)?.day_parts}
                    />
                )}

                {tab === 'results' && (
                    <Results
                        results={results}
                        openingCashCents={
                            props.opening_cash_cents ?? game.cash_cents
                        }
                    />
                )}
                {tab === 'business' && (
                    <BusinessPanel
                        business={props.business}
                        state={props.state}
                        value={props.business_value_cents ?? 0}
                    />
                )}
                {tab === 'map' && (
                    <GameMap
                        map={props.map}
                        own={props.business}
                        competitors={props.competitors ?? []}
                    />
                )}
                {tab === 'competitors' && (
                    <Competitors competitors={props.competitors ?? []} />
                )}
                {tab === 'events' && <EventLog events={props.events ?? []} />}
            </div>
        </div>
    );
}

function Results({
    results,
    openingCashCents,
}: {
    results: MonthResultRow[];
    openingCashCents: number;
}) {
    if (results.length === 0) {
        return (
            <p className="text-sm text-muted-foreground">
                No months played yet. Set your decisions and play the first
                month.
            </p>
        );
    }

    const last = results[results.length - 1];

    return (
        <div className="space-y-6">
            <div className="grid gap-6 lg:grid-cols-2">
                <CashChart
                    openingCashCents={openingCashCents}
                    results={results}
                />
                <ProfitChart results={results} />
            </div>
            <section className="space-y-2">
                <h3 className="font-medium">Profit and loss</h3>
                <PnlTable results={results} />
            </section>
            <section className="max-w-xl space-y-2">
                <h3 className="font-medium">
                    Month {last.month} ({monthName(last.calendar_month)}) by
                    time of day
                </h3>
                <DayPartBreakdown result={last} />
            </section>
        </div>
    );
}

function BusinessPanel({
    business,
    state,
    value,
}: {
    business: BusinessForSale;
    state: BusinessStateProps;
    value: number;
}) {
    return (
        <section className="grid gap-6 md:grid-cols-2">
            <div className="space-y-3">
                <Heading
                    title={business.fictional_name}
                    description={`${humanize(business.category)} in ${business.neighbourhood}`}
                />
                <dl className="grid grid-cols-2 gap-1 text-sm">
                    <dt className="text-muted-foreground">Seats</dt>
                    <dd>
                        {business.indoor_seats} inside, {business.terrace_seats}{' '}
                        on the terrace
                    </dd>
                    <dt className="text-muted-foreground">Rent</dt>
                    <dd>{formatCents(business.rent_month_cents)} / month</dd>
                    <dt className="text-muted-foreground">Licence · kitchen</dt>
                    <dd>
                        {humanize(business.licence)} · {business.kitchen}
                    </dd>
                    <dt className="text-muted-foreground">Footfall</dt>
                    <dd>{business.footfall.toFixed(1)} / 10</dd>
                    <dt className="text-muted-foreground">You paid</dt>
                    <dd>{formatCents(business.traspaso_cents)}</dd>
                    <dt className="text-muted-foreground">Worth now</dt>
                    <dd>{formatCents(value)}</dd>
                </dl>
            </div>
            <div className="space-y-3 text-sm">
                <Meter label="Reputation" value={state.reputation} />
                <Meter label="Staff morale" value={state.staff_morale} />
                <Meter label="Equipment" value={state.equipment_health} />
                <Meter label="Quality served" value={state.stock_quality} />
                {state.modifiers.length > 0 && (
                    <div className="space-y-1 pt-2">
                        <div className="font-medium">In effect</div>
                        {state.modifiers.map((m, i) => (
                            <div key={i} className="text-muted-foreground">
                                {humanize(m.source)}:{' '}
                                {describeModifier(
                                    m.effect,
                                    m.value,
                                    m.months_remaining,
                                    m.day_parts,
                                )}
                            </div>
                        ))}
                    </div>
                )}
            </div>
        </section>
    );
}

function Meter({ label, value }: { label: string; value: number }) {
    const rounded = Math.round(value);

    return (
        <div>
            <div className="flex justify-between">
                <span>{label}</span>
                <span className="tabular-nums">{rounded} / 100</span>
            </div>
            <div
                className="h-2 rounded bg-muted"
                role="meter"
                aria-label={label}
                aria-valuenow={rounded}
                aria-valuemin={0}
                aria-valuemax={100}
            >
                <div
                    className="h-2 rounded bg-[var(--viz-series-1)]"
                    style={{ width: `${Math.max(0, Math.min(100, value))}%` }}
                />
            </div>
        </div>
    );
}

function EventLog({ events }: { events: GameEventRow[] }) {
    if (events.length === 0) {
        return (
            <p className="text-sm text-muted-foreground">
                Nothing has happened yet.
            </p>
        );
    }

    return (
        <ul className="max-w-2xl space-y-2 text-sm">
            {[...events].reverse().map((e) => (
                <li
                    key={`${e.month}-${e.type}`}
                    className="rounded-lg border p-3"
                >
                    <div className="font-medium">
                        Month {e.month}: {humanize(e.type)}
                        {e.payload.outcome && (
                            <span className="font-normal">
                                {' '}
                                — {humanize(e.payload.outcome).toLowerCase()}
                            </span>
                        )}
                    </div>
                    <EffectsSummary effects={e.payload.effects} />
                    {e.choice && (
                        <div className="mt-1">
                            You chose{' '}
                            <span className="font-medium">
                                {humanize(e.choice).toLowerCase()}
                            </span>
                            {e.resolved_month && (
                                <span className="text-muted-foreground">
                                    {' '}
                                    (took effect in month {e.resolved_month})
                                </span>
                            )}
                        </div>
                    )}
                    {e.choices.length > 0 && !e.choice && (
                        <div className="mt-1 text-amber-700 dark:text-amber-400">
                            Waiting for your choice.
                        </div>
                    )}
                </li>
            ))}
        </ul>
    );
}

function Competitors({ competitors }: { competitors: Competitor[] }) {
    if (competitors.length === 0) {
        return (
            <p className="text-sm text-muted-foreground">No rivals nearby.</p>
        );
    }

    return (
        <div className="max-w-3xl overflow-x-auto rounded-lg border">
            <table className="w-full text-sm tabular-nums">
                <thead className="bg-muted/50 text-right">
                    <tr>
                        <th className="p-2 text-left">Rival</th>
                        <th className="p-2">Distance</th>
                        <th className="p-2">Prices</th>
                        <th className="p-2">Quality</th>
                        <th className="p-2">Reputation</th>
                        <th className="p-2">Seats</th>
                    </tr>
                </thead>
                <tbody className="text-right">
                    {competitors.map((c) => (
                        <tr key={c.key} className="border-t">
                            <td className="p-2 text-left">{c.name}</td>
                            <td className="p-2">
                                {Math.round(c.distance_metres)} m
                            </td>
                            <td className="p-2">
                                {c.price_level === 1
                                    ? 'average'
                                    : formatPercent(c.price_level - 1)}
                            </td>
                            <td className="p-2">{Math.round(c.quality)}</td>
                            <td className="p-2">{Math.round(c.reputation)}</td>
                            <td className="p-2">{c.seats}</td>
                        </tr>
                    ))}
                </tbody>
            </table>
            <p className="p-2 text-xs text-muted-foreground">
                Rivals within a few hundred metres follow your prices, and
                improve when you take their customers.
            </p>
        </div>
    );
}

function EndOfYear({ game, value }: { game: GameSummary; value: number }) {
    return (
        <section className="space-y-4 rounded-xl border p-4">
            <Heading
                title="The year is over"
                description={`Your business would sell for about ${formatCents(value)}, and the landlord returns your ${formatCents(game.deposit_cents)} deposit. Sell, or keep it and count its value in your net worth.`}
            />
            <Form
                {...EndController.store.form(game.id)}
                className="flex flex-wrap gap-3"
            >
                {({ processing, errors }) => (
                    <>
                        <Button
                            name="outcome"
                            value="sell"
                            disabled={processing}
                        >
                            Sell for {formatCents(value)}
                        </Button>
                        <Button
                            name="outcome"
                            value="keep"
                            variant="secondary"
                            disabled={processing}
                        >
                            Keep it
                        </Button>
                        <InputError message={errors.game} />
                    </>
                )}
            </Form>
        </section>
    );
}

function GameOver({
    game,
    results,
}: {
    game: GameSummary;
    results: MonthResultRow[];
}) {
    const final = game.final_net_worth_cents ?? game.net_worth_cents;
    const change = final / game.starting_capital_cents - 1;
    const profit = results.reduce((sum, r) => sum + r.profit_cents, 0);
    const pay = results.reduce((sum, r) => sum + r.owner_pay_cents, 0);
    // How the closure statistics count it: out of cash, or couldn't pay its owner.
    const survived = game.status !== 'bankrupt' && profit >= pay;

    return (
        <section className="space-y-2 rounded-xl border p-4">
            <Heading
                title={game.status === 'bankrupt' ? 'Bankrupt' : 'Game over'}
                description={
                    game.status === 'bankrupt'
                        ? 'Your cash ran out.'
                        : game.sold_for_cents !== null
                          ? `You sold the business for ${formatCents(game.sold_for_cents)}.`
                          : 'You kept the business.'
                }
            />
            <p className="text-lg">
                Final net worth: <strong>{formatCents(final)}</strong>{' '}
                <span
                    className={
                        change >= 0
                            ? 'text-emerald-700 dark:text-emerald-400'
                            : 'text-red-600 dark:text-red-400'
                    }
                >
                    ({formatPercent(change, 1)})
                </span>
            </p>
            {results.length > 0 && (
                <p className="text-sm text-muted-foreground">
                    Over {results.length} months the café made{' '}
                    {formatCents(profit)} and you paid yourself{' '}
                    {formatCents(pay)}.{' '}
                    {survived
                        ? 'It paid its way: it would have survived its first year.'
                        : "It couldn't pay you a living, so in real life it would have closed or been sold on. One in four or five new cafés and bars in Spain closes within its first year (INE)."}
                </p>
            )}
        </section>
    );
}

GameShow.layout = {
    breadcrumbs: [{ title: 'Games', href: index() }],
};
