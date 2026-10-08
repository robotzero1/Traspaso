import { Form, Head } from '@inertiajs/react';
import { useState } from 'react';
import ViabilityController from '@/actions/App/Http/Controllers/ViabilityController';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { PinMap } from '@/components/viability/pin-map';
import type { Pin } from '@/components/viability/pin-map';
import { formatCents, humanize } from '@/lib/format';
import { LegalLinks } from '@/pages/viability/show';

type Props = {
    map: {
        tile_url: string;
        attribution: string;
        max_zoom: number;
        centre: [number, number];
    };
    day_parts: { value: string; start_hour: number; end_hour: number }[];
    licence_day_parts: Record<string, string[]>;
    purchase: {
        held_months: number;
        legal_base_cents: number;
        legal_share: number;
        licence_cents: number;
    };
    staff_max: number;
    runs: number;
    years: number;
};

const hour = (h: number) => `${String(h % 24).padStart(2, '0')}:00`;

/** The viability check's form: where the café is and what the listing says. */
export default function ViabilityCreate(props: Props) {
    const [pin, setPin] = useState<Pin>(null);
    const [licence, setLicence] = useState('cafe_bar');
    const allowed = props.licence_day_parts[licence] ?? [];

    return (
        <>
            <Head title="Will this café work?" />
            <main className="mx-auto max-w-2xl space-y-6 p-4 sm:p-6">
                <header className="space-y-2">
                    <h1 className="text-2xl font-semibold">
                        Will this café work?
                    </h1>
                    <p className="text-muted-foreground">
                        Thinking of taking over a café or bar in Zaragoza?
                        Describe it and we'll run{' '}
                        {props.runs.toLocaleString('en')} simulated{' '}
                        {props.years}-year futures of it, using real footfall at
                        that spot and the real cafés and bars around it.
                    </p>
                </header>

                <Form
                    {...ViabilityController.store.form()}
                    className="space-y-6"
                >
                    {({ errors, processing }) => (
                        <>
                            <section className="space-y-2">
                                <h2 className="font-medium">1. Where is it?</h2>
                                <p className="text-sm text-muted-foreground">
                                    Click the map on the street where the café
                                    is.
                                </p>
                                <PinMap
                                    tileUrl={props.map.tile_url}
                                    attribution={props.map.attribution}
                                    maxZoom={props.map.max_zoom}
                                    centre={props.map.centre}
                                    pin={pin}
                                    onPin={setPin}
                                />
                                <input
                                    type="hidden"
                                    name="lat"
                                    value={pin?.lat ?? ''}
                                />
                                <input
                                    type="hidden"
                                    name="lng"
                                    value={pin?.lng ?? ''}
                                />
                                <InputError
                                    message={errors.lat ?? errors.lng}
                                />
                            </section>

                            <section className="space-y-3">
                                <h2 className="font-medium">2. The listing</h2>
                                <div className="grid gap-3 sm:grid-cols-2">
                                    <Field
                                        name="traspaso_euros"
                                        label="Traspaso (€)"
                                        errors={errors}
                                        defaultValue={30000}
                                    />
                                    <Field
                                        name="rent_euros"
                                        label="Rent a month (€)"
                                        errors={errors}
                                        defaultValue={900}
                                    />
                                    <Field
                                        name="floor_area_m2"
                                        label="Floor area (m²)"
                                        errors={errors}
                                        defaultValue={60}
                                    />
                                    <Field
                                        name="indoor_seats"
                                        label="Seats inside"
                                        errors={errors}
                                        defaultValue={30}
                                    />
                                    <Field
                                        name="terrace_seats"
                                        label="Terrace seats (0 if none)"
                                        errors={errors}
                                        defaultValue={0}
                                    />
                                    <Choice
                                        name="licence"
                                        label="Licence"
                                        value={licence}
                                        onChange={setLicence}
                                        options={{
                                            cafe: 'Café (closes by midnight)',
                                            cafe_bar:
                                                'Café-bar (can open at night)',
                                        }}
                                    />
                                    <Choice
                                        name="kitchen"
                                        label="Kitchen"
                                        defaultValue="basic"
                                        options={{
                                            none: 'None',
                                            basic: 'Basic',
                                            full: 'Full, with smoke outlet',
                                        }}
                                    />
                                    <Choice
                                        name="condition"
                                        label="State of the premises"
                                        defaultValue="6"
                                        options={{
                                            '3': 'Needs work',
                                            '6': 'Average',
                                            '8': 'Good',
                                            '10': 'Newly refitted',
                                        }}
                                    />
                                </div>
                            </section>

                            <section className="space-y-3">
                                <h2 className="font-medium">
                                    3. You, and how you'd run it
                                </h2>
                                <Field
                                    name="capital_euros"
                                    label="Money you have to start (€)"
                                    hint={`It must cover the traspaso, the landlord's deposit and guarantee (${props.purchase.held_months} months' rent), and the buying fees (the lawyer, ${formatCents(props.purchase.legal_base_cents)} plus ${Math.round(props.purchase.legal_share * 100)}% of the traspaso; the licence's change of holder, ${formatCents(props.purchase.licence_cents)}). What's left is your cushion.`}
                                    errors={errors}
                                    defaultValue={50000}
                                />
                                <fieldset className="space-y-2">
                                    <legend className="text-sm font-medium">
                                        Opening hours
                                    </legend>
                                    <div className="grid gap-2 sm:grid-cols-2">
                                        {props.day_parts.map((p) => (
                                            <label
                                                key={p.value}
                                                className="flex items-center gap-2 text-sm"
                                            >
                                                <input
                                                    type="checkbox"
                                                    name="open_day_parts[]"
                                                    value={p.value}
                                                    defaultChecked={[
                                                        'morning',
                                                        'lunch',
                                                        'afternoon',
                                                    ].includes(p.value)}
                                                    disabled={
                                                        !allowed.includes(
                                                            p.value,
                                                        )
                                                    }
                                                />
                                                {humanize(p.value)} (
                                                {hour(p.start_hour)}–
                                                {hour(p.end_hour)})
                                            </label>
                                        ))}
                                    </div>
                                    <InputError
                                        message={errors.open_day_parts}
                                    />
                                </fieldset>
                                <div className="grid gap-3 sm:grid-cols-2">
                                    <Field
                                        name="staff_count"
                                        label="Employees (besides you)"
                                        errors={errors}
                                        defaultValue={1}
                                        max={props.staff_max}
                                    />
                                    <Choice
                                        name="quality_tier"
                                        label="What you'd serve"
                                        defaultValue="standard"
                                        options={{
                                            budget: 'Budget',
                                            standard: 'Standard',
                                            premium: 'Premium',
                                        }}
                                    />
                                </div>
                            </section>

                            <Button
                                disabled={processing || pin === null}
                                className="w-full sm:w-auto"
                            >
                                Run the check
                            </Button>
                            {pin === null && (
                                <p className="text-sm text-muted-foreground">
                                    Put the pin on the map first.
                                </p>
                            )}
                        </>
                    )}
                </Form>

                <p className="text-xs text-muted-foreground">
                    A simulation, not financial advice. It uses real footfall
                    estimates and the real cafés and bars nearby (from
                    OpenStreetMap), and market figures calibrated so that, as
                    INE reports, about a quarter of new cafés close in their
                    first year and about half within five. <LegalLinks />
                </p>
            </main>
        </>
    );
}

function Field({
    name,
    label,
    hint,
    errors,
    defaultValue,
    max,
}: {
    name: string;
    label: string;
    hint?: string;
    errors: Record<string, string>;
    defaultValue: number;
    max?: number;
}) {
    return (
        <div className="space-y-1">
            <Label htmlFor={name}>{label}</Label>
            <Input
                id={name}
                name={name}
                type="number"
                min={0}
                max={max}
                inputMode="numeric"
                defaultValue={defaultValue}
                required
            />
            {hint && <p className="text-xs text-muted-foreground">{hint}</p>}
            <InputError message={errors[name]} />
        </div>
    );
}

function Choice({
    name,
    label,
    options,
    defaultValue,
    value,
    onChange,
}: {
    name: string;
    label: string;
    options: Record<string, string>;
    defaultValue?: string;
    value?: string;
    onChange?: (value: string) => void;
}) {
    return (
        <div className="space-y-1">
            <Label htmlFor={name}>{label}</Label>
            <select
                id={name}
                name={name}
                defaultValue={value === undefined ? defaultValue : undefined}
                value={value}
                onChange={(e) => onChange?.(e.target.value)}
                className="h-9 w-full rounded-md border border-input bg-transparent px-2 text-sm"
            >
                {Object.entries(options).map(([v, text]) => (
                    <option key={v} value={v}>
                        {text}
                    </option>
                ))}
            </select>
        </div>
    );
}
