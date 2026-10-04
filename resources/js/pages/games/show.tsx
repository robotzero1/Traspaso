import { Form, Head } from '@inertiajs/react';
import EndController from '@/actions/App/Http/Controllers/Game/EndController';
import MonthController from '@/actions/App/Http/Controllers/Game/MonthController';
import { BusinessBrowser } from '@/components/game/business-browser';
import {
    DecisionsForm,
    EffectsSummary,
} from '@/components/game/decisions-form';
import { GameHeader } from '@/components/game/game-header';
import { ResultsTable } from '@/components/game/results-table';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { formatCents, formatPercent, humanize } from '@/lib/format';
import { index } from '@/routes/games';
import type {
    BusinessForSale,
    BusinessStateProps,
    Competitor,
    DayPartValue,
    DecisionLimits,
    Decisions,
    GameEventRow,
    GameSummary,
    MonthResultRow,
    PendingEvent,
} from '@/types/game';

type Props = {
    game: GameSummary;
    businesses?: BusinessForSale[];
    business?: BusinessForSale;
    state?: BusinessStateProps;
    business_value_cents?: number;
    decisions?: Decisions;
    decision_limits?: DecisionLimits;
    allowed_day_parts?: DayPartValue[];
    day_parts?: { value: DayPartValue; start_hour: number; end_hour: number }[];
    pending_events?: PendingEvent[];
    results?: MonthResultRow[];
    events?: GameEventRow[];
    competitors?: Competitor[];
};

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
                            description="Pick the café you want to take over."
                        />
                        <BusinessBrowser
                            game={game}
                            businesses={props.businesses}
                        />
                    </section>
                )}

                {game.phase === 'over' && <GameOver {...props} />}

                {game.phase === 'ending' && (
                    <section className="space-y-4 rounded-xl border p-4">
                        <Heading
                            title="The year is over"
                            description={`Your business is worth about ${formatCents(props.business_value_cents ?? 0)}. Sell it, or keep it and count it in your net worth.`}
                        />
                        <Form
                            {...EndController.store.form(game.id)}
                            className="flex gap-3"
                        >
                            {({ processing, errors }) => (
                                <>
                                    <Button
                                        name="outcome"
                                        value="sell"
                                        disabled={processing}
                                    >
                                        Sell for{' '}
                                        {formatCents(
                                            props.business_value_cents ?? 0,
                                        )}
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
                )}

                {props.business && props.state && (
                    <BusinessPanel
                        business={props.business}
                        state={props.state}
                        value={props.business_value_cents ?? 0}
                    />
                )}

                {game.phase === 'playing' && props.decisions && (
                    <section className="space-y-4 rounded-xl border p-4">
                        <Heading
                            title={`Decisions for month ${game.current_month}`}
                            description="Save your decisions, then play the month."
                        />
                        <DecisionsForm
                            gameId={game.id}
                            decisions={props.decisions}
                            limits={props.decision_limits!}
                            dayParts={props.day_parts!}
                            allowedDayParts={props.allowed_day_parts!}
                            pendingEvents={props.pending_events ?? []}
                        />
                        <Form
                            {...MonthController.store.form(game.id)}
                            options={{ preserveScroll: true }}
                        >
                            {({ processing, errors }) => (
                                <>
                                    <Button disabled={processing}>
                                        Play month {game.current_month}
                                    </Button>
                                    <InputError
                                        message={
                                            errors.game ?? errors.decisions
                                        }
                                    />
                                </>
                            )}
                        </Form>
                    </section>
                )}

                {props.results && (
                    <section className="space-y-4">
                        <Heading title="Results" />
                        <ResultsTable results={props.results} />
                    </section>
                )}

                <div className="grid gap-6 md:grid-cols-2">
                    {props.events && props.events.length > 0 && (
                        <EventLog events={props.events} />
                    )}
                    {props.competitors && props.competitors.length > 0 && (
                        <Competitors competitors={props.competitors} />
                    )}
                </div>
            </div>
        </>
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
        <section className="grid gap-4 rounded-xl border p-4 md:grid-cols-2">
            <div>
                <Heading
                    title={business.fictional_name}
                    description={`${business.neighbourhood} · ${humanize(business.category)}`}
                />
                <dl className="grid grid-cols-2 gap-1 text-sm">
                    <dt className="text-muted-foreground">Seats</dt>
                    <dd>
                        {business.indoor_seats} inside, {business.terrace_seats}{' '}
                        terrace
                    </dd>
                    <dt className="text-muted-foreground">Rent</dt>
                    <dd>{formatCents(business.rent_month_cents)} / month</dd>
                    <dt className="text-muted-foreground">Licence · kitchen</dt>
                    <dd>
                        {humanize(business.licence)} · {business.kitchen}
                    </dd>
                    <dt className="text-muted-foreground">Business value</dt>
                    <dd>{formatCents(value)}</dd>
                </dl>
            </div>
            <div className="space-y-2 text-sm">
                <Meter label="Reputation" value={state.reputation} />
                <Meter label="Staff morale" value={state.staff_morale} />
                <Meter label="Equipment" value={state.equipment_health} />
                <Meter label="Quality served" value={state.stock_quality} />
                {state.modifiers.length > 0 && (
                    <div className="pt-2">
                        <div className="font-medium">In effect</div>
                        {state.modifiers.map((m, i) => (
                            <div key={i} className="text-muted-foreground">
                                {humanize(m.source)}: {humanize(m.effect)}{' '}
                                {m.value}
                                {m.months_remaining === null
                                    ? ' (permanent)'
                                    : ` (${m.months_remaining} more month${m.months_remaining === 1 ? '' : 's'})`}
                            </div>
                        ))}
                    </div>
                )}
            </div>
        </section>
    );
}

function Meter({ label, value }: { label: string; value: number }) {
    return (
        <div>
            <div className="flex justify-between">
                <span>{label}</span>
                <span>{Math.round(value)}</span>
            </div>
            <div className="h-2 rounded bg-muted">
                <div
                    className="h-2 rounded bg-primary"
                    style={{ width: `${Math.max(0, Math.min(100, value))}%` }}
                />
            </div>
        </div>
    );
}

function EventLog({ events }: { events: GameEventRow[] }) {
    return (
        <section className="space-y-2">
            <Heading title="Events" />
            <ul className="space-y-2 text-sm">
                {[...events].reverse().map((e) => (
                    <li
                        key={`${e.month}-${e.type}`}
                        className="rounded-lg border p-2"
                    >
                        <div className="font-medium">
                            Month {e.month}: {humanize(e.type)}
                            {e.payload.outcome &&
                                ` — ${humanize(e.payload.outcome)}`}
                        </div>
                        <EffectsSummary effects={e.payload.effects} />
                        {e.choice && <div>You chose: {humanize(e.choice)}</div>}
                    </li>
                ))}
            </ul>
        </section>
    );
}

function Competitors({ competitors }: { competitors: Competitor[] }) {
    return (
        <section className="space-y-2">
            <Heading title="Nearby competitors" />
            <ul className="space-y-1 text-sm">
                {competitors.map((c) => (
                    <li
                        key={c.key}
                        className="flex justify-between rounded-lg border p-2"
                    >
                        <span>
                            {c.name}{' '}
                            <span className="text-muted-foreground">
                                · {Math.round(c.distance_metres)} m
                            </span>
                        </span>
                        <span className="text-muted-foreground">
                            prices ×{c.price_level.toFixed(2)} · quality{' '}
                            {Math.round(c.quality)} · reputation{' '}
                            {Math.round(c.reputation)}
                        </span>
                    </li>
                ))}
            </ul>
        </section>
    );
}

function GameOver({ game }: Props) {
    const final = game.final_net_worth_cents ?? game.net_worth_cents;
    const change = final / game.starting_capital_cents - 1;

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
                        change >= 0 ? 'text-emerald-600' : 'text-red-600'
                    }
                >
                    ({formatPercent(change, 1)})
                </span>
            </p>
        </section>
    );
}

GameShow.layout = {
    breadcrumbs: [{ title: 'Games', href: index() }],
};
