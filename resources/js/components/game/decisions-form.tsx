import { Form } from '@inertiajs/react';
import { useState } from 'react';
import DecisionsController from '@/actions/App/Http/Controllers/Game/DecisionsController';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatCents, humanize } from '@/lib/format';
import type {
    DayPartValue,
    DecisionLimits,
    Decisions,
    EventEffects,
    PendingEvent,
} from '@/types/game';

type Props = {
    gameId: number;
    decisions: Decisions;
    limits: DecisionLimits;
    dayParts: { value: DayPartValue; start_hour: number; end_hour: number }[];
    allowedDayParts: DayPartValue[];
    pendingEvents: PendingEvent[];
};

export function DecisionsForm({
    gameId,
    decisions,
    limits,
    dayParts,
    allowedDayParts,
    pendingEvents,
}: Props) {
    const [marketingEuros, setMarketingEuros] = useState(
        decisions.marketing_spend_cents / 100,
    );

    return (
        <Form
            {...DecisionsController.update.form(gameId)}
            options={{ preserveScroll: true }}
            className="space-y-6"
        >
            {({ processing, errors, recentlySuccessful }) => (
                <>
                    {pendingEvents.length > 0 && (
                        <fieldset className="space-y-4 rounded-lg border border-amber-300 p-4">
                            <legend className="px-1 font-medium">
                                Decide before next month
                            </legend>
                            {pendingEvents.map((event) => (
                                <div key={event.key} className="space-y-2">
                                    <div className="font-medium">
                                        {humanize(event.type)}
                                    </div>
                                    {event.choices.map((choice) => (
                                        <label
                                            key={choice}
                                            className="flex items-start gap-2 text-sm"
                                        >
                                            <input
                                                type="radio"
                                                name={`event_choices[${event.key}]`}
                                                value={choice}
                                                defaultChecked={
                                                    (decisions.event_choices[
                                                        event.key
                                                    ] ??
                                                        event.payload
                                                            .default_choice) ===
                                                    choice
                                                }
                                            />
                                            <span>
                                                {humanize(choice)}
                                                {choice ===
                                                    event.payload
                                                        .default_choice && (
                                                    <span className="text-muted-foreground">
                                                        {' '}
                                                        (if you don't choose)
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
                            ))}
                            <InputError message={errors.event_choices} />
                        </fieldset>
                    )}

                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="grid gap-2">
                            <Label htmlFor="price_level">
                                Prices vs. local average (1 = average)
                            </Label>
                            <Input
                                id="price_level"
                                name="price_level"
                                type="number"
                                step={0.05}
                                min={limits.price_level.min}
                                max={limits.price_level.max}
                                defaultValue={decisions.price_level}
                            />
                            <InputError message={errors.price_level} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="quality_tier">Quality</Label>
                            <select
                                id="quality_tier"
                                name="quality_tier"
                                defaultValue={decisions.quality_tier}
                                className="h-9 rounded-md border bg-transparent px-3 text-sm"
                            >
                                <option value="budget">Budget</option>
                                <option value="standard">Standard</option>
                                <option value="premium">Premium</option>
                            </select>
                            <InputError message={errors.quality_tier} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="staff_count">
                                Staff (full-time, besides you)
                            </Label>
                            <Input
                                id="staff_count"
                                name="staff_count"
                                type="number"
                                min={limits.staff_count.min}
                                max={limits.staff_count.max}
                                defaultValue={decisions.staff_count}
                            />
                            <InputError message={errors.staff_count} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="marketing">
                                Marketing per month (€)
                            </Label>
                            <Input
                                id="marketing"
                                type="number"
                                step={10}
                                min={limits.marketing_spend_cents.min / 100}
                                max={limits.marketing_spend_cents.max / 100}
                                value={marketingEuros}
                                onChange={(e) =>
                                    setMarketingEuros(Number(e.target.value))
                                }
                            />
                            <input
                                type="hidden"
                                name="marketing_spend_cents"
                                value={Math.round(marketingEuros * 100)}
                            />
                            <InputError
                                message={errors.marketing_spend_cents}
                            />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="open_days_per_week">
                                Days open per week
                            </Label>
                            <Input
                                id="open_days_per_week"
                                name="open_days_per_week"
                                type="number"
                                min={1}
                                max={7}
                                defaultValue={decisions.open_days_per_week}
                            />
                            <InputError message={errors.open_days_per_week} />
                        </div>

                        <fieldset className="grid gap-2">
                            <legend className="mb-2 text-sm font-medium">
                                Open for
                            </legend>
                            {dayParts.map((part) => {
                                const allowed = allowedDayParts.includes(
                                    part.value,
                                );

                                return (
                                    <label
                                        key={part.value}
                                        className="flex items-center gap-2 text-sm"
                                    >
                                        <input
                                            type="checkbox"
                                            name="open_day_parts[]"
                                            value={part.value}
                                            disabled={!allowed}
                                            defaultChecked={decisions.open_day_parts.includes(
                                                part.value,
                                            )}
                                        />
                                        {humanize(part.value)} (
                                        {part.start_hour % 24}:00–
                                        {part.end_hour % 24}:00)
                                        {!allowed && (
                                            <span className="text-muted-foreground">
                                                — not allowed by your licence
                                            </span>
                                        )}
                                    </label>
                                );
                            })}
                            <InputError message={errors.open_day_parts} />
                        </fieldset>
                    </div>

                    <InputError message={errors.decisions} />
                    <div className="flex items-center gap-3">
                        <Button variant="secondary" disabled={processing}>
                            Save decisions
                        </Button>
                        {recentlySuccessful && (
                            <span className="text-sm text-muted-foreground">
                                Saved.
                            </span>
                        )}
                    </div>
                </>
            )}
        </Form>
    );
}

export function EffectsSummary({ effects }: { effects?: EventEffects }) {
    if (!effects) {
        return null;
    }

    const parts: string[] = [];

    if (effects.cost_cents)
        parts.push(`costs ${formatCents(effects.cost_cents)}`);
    if (effects.revenue_cents)
        parts.push(`brings in ${formatCents(effects.revenue_cents)}`);
    if (effects.reputation)
        parts.push(
            `reputation ${effects.reputation > 0 ? '+' : ''}${effects.reputation}`,
        );
    if (effects.morale)
        parts.push(`morale ${effects.morale > 0 ? '+' : ''}${effects.morale}`);
    if (effects.equipment_health)
        parts.push(
            `equipment ${effects.equipment_health > 0 ? '+' : ''}${effects.equipment_health}`,
        );

    for (const m of effects.modifiers ?? []) {
        const months =
            m.months === null
                ? 'from now on'
                : `for ${m.months} month${m.months === 1 ? '' : 's'}`;
        parts.push(`${humanize(m.effect)} ${m.value} ${months}`);
    }

    return parts.length > 0 ? (
        <span className="block text-muted-foreground">{parts.join(' · ')}</span>
    ) : null;
}
