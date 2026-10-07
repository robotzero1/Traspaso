import { Form } from '@inertiajs/react';
import { Minus, Plus } from 'lucide-react';
import { useState } from 'react';
import DecisionsController from '@/actions/App/Http/Controllers/Game/DecisionsController';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { formatCents, humanize } from '@/lib/format';
import { cn } from '@/lib/utils';
import type {
    ScheduledDecision,
    CostHints,
    DayPartResult,
    DayPartValue,
    DecisionLimits,
    Decisions,
    EventEffects,
    PendingEvent,
} from '@/types/game';

type DayPartOption = {
    value: DayPartValue;
    start_hour: number;
    end_hour: number;
};

type Props = {
    gameId: number;
    month: number;
    decisions: Decisions;
    limits: DecisionLimits;
    dayParts: DayPartOption[];
    allowedDayParts: DayPartValue[];
    pendingEvents: PendingEvent[];
    costHints: CostHints;
    lastMonth?: DayPartResult[];
    /** Changes saved but not yet in effect (next day, or staff lead times). */
    scheduled?: ScheduledDecision[];
    fastForward?: boolean;
};

const tiers = [
    {
        value: 'budget',
        label: 'Budget',
        hint: 'Cheaper stock, lower tickets; customers notice.',
    },
    { value: 'standard', label: 'Standard', hint: 'What most cafés serve.' },
    {
        value: 'premium',
        label: 'Premium',
        hint: 'Better stock and higher tickets; costs more.',
    },
] as const;

const WEEKS_PER_MONTH = 52 / 12;

export function DecisionsForm({
    gameId,
    month,
    decisions,
    limits,
    dayParts,
    allowedDayParts,
    pendingEvents,
    costHints,
    lastMonth = [],
    scheduled = [],
    fastForward = false,
}: Props) {
    const [price, setPrice] = useState(decisions.price_level);
    const [tier, setTier] = useState(decisions.quality_tier);
    const [staff, setStaff] = useState(decisions.staff_count);
    const [days, setDays] = useState(decisions.open_days_per_week);
    const [parts, setParts] = useState<DayPartValue[]>(
        decisions.open_day_parts,
    );
    const [marketingEuros, setMarketingEuros] = useState(
        decisions.marketing_spend_cents / 100,
    );
    const [choices, setChoices] = useState<Record<string, string>>(() =>
        Object.fromEntries(
            pendingEvents.map((e) => [
                e.key,
                decisions.event_choices[e.key] ??
                    e.payload.default_choice ??
                    e.choices[0],
            ]),
        ),
    );

    const hours = (p: DayPartOption) => p.end_hour - p.start_hour;
    const hoursPerDay = dayParts
        .filter((p) => parts.includes(p.value))
        .reduce((sum, p) => sum + hours(p), 0);
    const staffCost = staff * costHints.staff_per_person_cents;
    // Open hours you and your staff can't cover are paid as part-time
    // cover, at a full-timer's hourly cost (the engine's Staffing rule).
    const openHoursPerWeek = hoursPerDay * days;
    const coverHoursPerWeek = Math.max(
        0,
        openHoursPerWeek * costHints.min_on_shift -
            staff * costHints.full_time_hours_per_week -
            costHints.owner_hours_per_week,
    );
    const coverCost =
        (coverHoursPerWeek * costHints.staff_per_person_cents) /
        costHints.full_time_hours_per_week;
    const utilities =
        costHints.utilities_base_cents +
        costHints.utilities_per_open_hour_cents *
            hoursPerDay *
            days *
            WEEKS_PER_MONTH;
    const fixedCosts =
        staffCost +
        coverCost +
        costHints.rent_cents +
        utilities +
        marketingEuros * 100;
    const pricePercent = Math.round((price - 1) * 100);

    const togglePart = (value: DayPartValue) =>
        setParts((current) =>
            current.includes(value)
                ? current.filter((p) => p !== value)
                : [...current, value],
        );

    return (
        <Form
            {...DecisionsController.update.form(gameId)}
            options={{ preserveScroll: true }}
            className="grid gap-6 lg:grid-cols-[1fr_18rem]"
        >
            {({ processing, errors, recentlySuccessful }) => (
                <>
                    <div className="space-y-6">
                        {pendingEvents.length > 0 && (
                            <section className="space-y-3">
                                <h3 className="font-medium">
                                    Decide before you play month {month}
                                </h3>
                                {pendingEvents.map((event) => (
                                    <fieldset
                                        key={event.key}
                                        className="space-y-2 rounded-lg border border-amber-300 p-3 dark:border-amber-700"
                                    >
                                        <legend className="px-1 font-medium">
                                            {humanize(event.type)}{' '}
                                            <span className="font-normal text-muted-foreground">
                                                · month {event.month}
                                            </span>
                                        </legend>
                                        <div className="grid gap-2 sm:grid-cols-2">
                                            {event.choices.map((choice) => (
                                                <label
                                                    key={choice}
                                                    className={cn(
                                                        'flex cursor-pointer gap-2 rounded-md border p-2 text-sm',
                                                        choices[event.key] ===
                                                            choice &&
                                                            'border-primary bg-muted/50',
                                                    )}
                                                >
                                                    <input
                                                        type="radio"
                                                        name={`event_choices[${event.key}]`}
                                                        value={choice}
                                                        checked={
                                                            choices[
                                                                event.key
                                                            ] === choice
                                                        }
                                                        onChange={() =>
                                                            setChoices((c) => ({
                                                                ...c,
                                                                [event.key]:
                                                                    choice,
                                                            }))
                                                        }
                                                        className="mt-1"
                                                    />
                                                    <span>
                                                        <span className="font-medium">
                                                            {humanize(choice)}
                                                        </span>
                                                        {choice ===
                                                            event.payload
                                                                .default_choice && (
                                                            <span className="text-muted-foreground">
                                                                {' '}
                                                                · if you don't
                                                                choose
                                                            </span>
                                                        )}
                                                        <EffectsSummary
                                                            effects={
                                                                event.payload
                                                                    .choice_effects?.[
                                                                    choice
                                                                ]
                                                            }
                                                        />
                                                    </span>
                                                </label>
                                            ))}
                                        </div>
                                    </fieldset>
                                ))}
                                <InputError message={errors.event_choices} />
                            </section>
                        )}

                        <section className="space-y-2">
                            <div className="flex items-baseline justify-between">
                                <Label htmlFor="price_level">Prices</Label>
                                <span className="text-sm tabular-nums">
                                    {pricePercent === 0
                                        ? 'Local average'
                                        : `${pricePercent > 0 ? '+' : ''}${pricePercent}% vs. local average`}
                                </span>
                            </div>
                            <input
                                id="price_level"
                                name="price_level"
                                type="range"
                                min={limits.price_level.min}
                                max={limits.price_level.max}
                                step={0.05}
                                value={price}
                                onChange={(e) =>
                                    setPrice(Number(e.target.value))
                                }
                                className="w-full"
                            />
                            <p className="text-xs text-muted-foreground">
                                Higher prices raise each ticket, but fewer
                                people come in and your reputation suffers if it
                                isn't worth it.
                            </p>
                            <InputError message={errors.price_level} />
                        </section>

                        <fieldset className="space-y-2">
                            <legend className="text-sm font-medium">
                                Quality
                            </legend>
                            <div className="grid gap-2 sm:grid-cols-3">
                                {tiers.map((t) => (
                                    <label
                                        key={t.value}
                                        className={cn(
                                            'cursor-pointer rounded-md border p-2 text-sm',
                                            tier === t.value &&
                                                'border-primary bg-muted/50',
                                        )}
                                    >
                                        <input
                                            type="radio"
                                            name="quality_tier"
                                            value={t.value}
                                            checked={tier === t.value}
                                            onChange={() => setTier(t.value)}
                                            className="sr-only"
                                        />
                                        <span className="font-medium">
                                            {t.label}
                                        </span>
                                        <span className="block text-xs text-muted-foreground">
                                            {t.hint} Stock costs{' '}
                                            {Math.round(
                                                costHints.cogs_share[t.value] *
                                                    100,
                                            )}
                                            % of sales.
                                        </span>
                                    </label>
                                ))}
                            </div>
                            <InputError message={errors.quality_tier} />
                        </fieldset>

                        <fieldset className="space-y-2">
                            <legend className="text-sm font-medium">
                                Opening hours
                            </legend>
                            <div className="grid gap-2 sm:grid-cols-2 xl:grid-cols-3">
                                {dayParts.map((part) => {
                                    const allowed = allowedDayParts.includes(
                                        part.value,
                                    );
                                    const open = parts.includes(part.value);
                                    const last = lastMonth.find(
                                        (r) => r.day_part === part.value,
                                    );

                                    return (
                                        <label
                                            key={part.value}
                                            className={cn(
                                                'rounded-md border p-2 text-sm',
                                                allowed
                                                    ? 'cursor-pointer'
                                                    : 'cursor-not-allowed opacity-60',
                                                open &&
                                                    'border-primary bg-muted/50',
                                            )}
                                        >
                                            <span className="flex items-center gap-2">
                                                <input
                                                    type="checkbox"
                                                    name="open_day_parts[]"
                                                    value={part.value}
                                                    checked={open}
                                                    disabled={!allowed}
                                                    onChange={() =>
                                                        togglePart(part.value)
                                                    }
                                                />
                                                <span className="font-medium">
                                                    {humanize(part.value)}
                                                </span>
                                                <span className="text-muted-foreground tabular-nums">
                                                    {part.start_hour % 24}:00–
                                                    {part.end_hour % 24}:00
                                                </span>
                                            </span>
                                            <span className="mt-1 block text-xs text-muted-foreground">
                                                {!allowed
                                                    ? 'Your licence doesn’t allow it.'
                                                    : last
                                                      ? `Last month: ${last.covers.toLocaleString('es-ES')} customers, ${formatCents(last.revenue_cents)}${last.demand > last.covers ? `, ${last.demand - last.covers} turned away` : ''}`
                                                      : 'Closed last month.'}
                                            </span>
                                        </label>
                                    );
                                })}
                            </div>
                            <InputError message={errors.open_day_parts} />

                            <div className="flex items-center gap-3 pt-2">
                                <Label
                                    htmlFor="open_days_per_week"
                                    className="whitespace-nowrap"
                                >
                                    Days a week
                                </Label>
                                <input
                                    id="open_days_per_week"
                                    name="open_days_per_week"
                                    type="range"
                                    min={1}
                                    max={7}
                                    value={days}
                                    onChange={(e) =>
                                        setDays(Number(e.target.value))
                                    }
                                    className="w-40"
                                />
                                <span className="text-sm tabular-nums">
                                    {days}
                                </span>
                            </div>
                            <InputError message={errors.open_days_per_week} />
                        </fieldset>

                        <div className="grid gap-6 sm:grid-cols-2">
                            <section className="space-y-2">
                                <Label htmlFor="staff_count">Staff</Label>
                                <div className="flex items-center gap-2">
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="icon"
                                        aria-label="One fewer"
                                        onClick={() =>
                                            setStaff((s) =>
                                                Math.max(
                                                    limits.staff_count.min,
                                                    s - 1,
                                                ),
                                            )
                                        }
                                    >
                                        <Minus />
                                    </Button>
                                    <input
                                        id="staff_count"
                                        name="staff_count"
                                        readOnly
                                        value={staff}
                                        className="h-9 w-12 rounded-md border text-center tabular-nums"
                                    />
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="icon"
                                        aria-label="One more"
                                        onClick={() =>
                                            setStaff((s) =>
                                                Math.min(
                                                    limits.staff_count.max,
                                                    s + 1,
                                                ),
                                            )
                                        }
                                    >
                                        <Plus />
                                    </Button>
                                </div>
                                <p className="text-xs text-muted-foreground">
                                    Full-time, besides you. Too few and
                                    customers get turned away; staff get tired.
                                </p>
                                <InputError message={errors.staff_count} />
                            </section>

                            <section className="space-y-2">
                                <Label htmlFor="marketing">
                                    Marketing per month (€)
                                </Label>
                                <input
                                    id="marketing"
                                    type="number"
                                    step={10}
                                    min={limits.marketing_spend_cents.min / 100}
                                    max={limits.marketing_spend_cents.max / 100}
                                    value={marketingEuros}
                                    onChange={(e) =>
                                        setMarketingEuros(
                                            Number(e.target.value),
                                        )
                                    }
                                    className="h-9 w-32 rounded-md border px-3 tabular-nums"
                                />
                                <input
                                    type="hidden"
                                    name="marketing_spend_cents"
                                    value={Math.round(marketingEuros * 100)}
                                />
                                <p className="text-xs text-muted-foreground">
                                    The first euros work hardest; returns tail
                                    off.
                                </p>
                                <InputError
                                    message={errors.marketing_spend_cents}
                                />
                            </section>
                        </div>
                    </div>

                    <aside className="space-y-4 lg:sticky lg:top-4 lg:self-start">
                        <div className="space-y-1 rounded-lg border p-3 text-sm tabular-nums">
                            <div className="font-medium">
                                Costs you're committing to
                            </div>
                            <Row label={`Staff (${staff})`} cents={staffCost} />
                            {coverCost > 0 && (
                                <Row
                                    label={`Part-time cover (~${Math.round(coverHoursPerWeek * WEEKS_PER_MONTH)} h)`}
                                    cents={coverCost}
                                />
                            )}
                            <Row label="Rent" cents={costHints.rent_cents} />
                            <Row
                                label={`Utilities (~${Math.round(hoursPerDay * days * WEEKS_PER_MONTH)} h open)`}
                                cents={utilities}
                            />
                            <Row
                                label="Marketing"
                                cents={marketingEuros * 100}
                            />
                            <div className="flex justify-between border-t pt-1 font-medium">
                                <span>About</span>
                                <span>{formatCents(fixedCosts)} / month</span>
                            </div>
                            <p className="pt-1 text-xs text-muted-foreground">
                                Plus stock, insurance, maintenance, the cuota de
                                autónomo, taxes and any events. On top, you take{' '}
                                {formatCents(costHints.owner_pay_cents)} a month
                                to live on.
                            </p>
                        </div>

                        <InputError message={errors.decisions ?? errors.game} />
                        <div className="flex flex-col gap-2">
                            <Button disabled={processing || parts.length === 0}>
                                Save
                            </Button>
                            <p className="text-xs text-muted-foreground">
                                The café trades every day; tonight at 23:00 the
                                day is played. Changes apply from the next day,
                                staff after their notice or hiring time.
                            </p>
                            {scheduled.map((s) => (
                                <p
                                    key={s.from}
                                    className="text-xs text-muted-foreground"
                                >
                                    From {s.from}:{' '}
                                    {Object.keys(s.changes)
                                        .map((k) => k.replaceAll('_', ' '))
                                        .join(', ')}
                                </p>
                            ))}
                            {fastForward && (
                                <Button
                                    name="and_play"
                                    value="1"
                                    variant="secondary"
                                    disabled={processing || parts.length === 0}
                                >
                                    Fast-forward to the end of month {month}
                                </Button>
                            )}
                            {recentlySuccessful && (
                                <span className="text-center text-sm text-muted-foreground">
                                    Saved.
                                </span>
                            )}
                        </div>
                    </aside>
                </>
            )}
        </Form>
    );
}

function Row({ label, cents }: { label: string; cents: number }) {
    return (
        <div className="flex justify-between gap-2">
            <span className="text-muted-foreground">{label}</span>
            <span>{formatCents(cents)}</span>
        </div>
    );
}

export function EffectsSummary({ effects }: { effects?: EventEffects }) {
    if (!effects) {
        return null;
    }

    const parts: string[] = [];
    const signed = (n: number) => `${n > 0 ? '+' : ''}${n}`;

    if (effects.cost_cents)
        parts.push(`costs ${formatCents(effects.cost_cents)}`);
    if (effects.revenue_cents)
        parts.push(`brings in ${formatCents(effects.revenue_cents)}`);
    if (effects.reputation)
        parts.push(`reputation ${signed(effects.reputation)}`);
    if (effects.morale) parts.push(`staff morale ${signed(effects.morale)}`);
    if (effects.equipment_health)
        parts.push(`equipment ${signed(effects.equipment_health)}`);
    if (effects.add_competitor) parts.push('a new rival opens nearby');
    if (effects.remove_competitor) parts.push('a rival closes');

    for (const m of effects.modifiers ?? []) {
        parts.push(describeModifier(m.effect, m.value, m.months, m.day_parts));
    }

    if (parts.length === 0) {
        parts.push('no effect');
    }

    return (
        <span className="block text-xs text-muted-foreground">
            {parts.join(' · ')}
        </span>
    );
}

export function describeModifier(
    effect: string,
    value: number,
    months: number | null,
    dayParts: DayPartValue[] = [],
): string {
    const percent = (v: number) =>
        `${v >= 1 ? '+' : '−'}${Math.round(Math.abs(v - 1) * 100)}%`;
    const when =
        dayParts.length > 0
            ? ` (${dayParts.map(humanize).join(', ').toLowerCase()})`
            : '';
    const lasting =
        months === null
            ? ' from now on'
            : ` for ${months} month${months === 1 ? '' : 's'}`;

    const what: Record<string, string> = {
        demand: `customers ${percent(value)}${when}`,
        capacity: `capacity ${percent(value)}${when}`,
        rent: `rent ${percent(value)}`,
        quality_penalty: `quality −${value}`,
        cogs_share: `stock costs +${Math.round(value * 100)} points of sales`,
        staff_shortage: `${value} staff short`,
        monthly_cost: `${formatCents(value)} extra a month`,
    };

    return (what[effect] ?? humanize(effect)) + lasting;
}
