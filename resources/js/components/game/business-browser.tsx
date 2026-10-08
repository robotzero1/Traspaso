import { Form } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import PurchaseController from '@/actions/App/Http/Controllers/Game/PurchaseController';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetFooter,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { formatCents, humanize } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { BusinessForSale, GameSummary, MapProps } from '@/types/game';
import { GameMap } from './game-map';

type SortKey = 'traspaso' | 'rent' | 'footfall' | 'condition' | 'seats';

type Filters = {
    neighbourhood: string;
    category: string;
    kitchen: string;
    minFootfall: number;
    terrace: boolean;
    affordable: boolean;
    search: string;
    sort: SortKey;
};

const initialFilters: Filters = {
    neighbourhood: '',
    category: '',
    kitchen: '',
    minFootfall: 0,
    terrace: false,
    affordable: true,
    search: '',
    sort: 'traspaso',
};

const sorters: Record<
    SortKey,
    (a: BusinessForSale, b: BusinessForSale) => number
> = {
    traspaso: (a, b) => a.traspaso_cents - b.traspaso_cents,
    rent: (a, b) => a.rent_month_cents - b.rent_month_cents,
    footfall: (a, b) => b.footfall - a.footfall,
    condition: (a, b) => b.condition - a.condition,
    seats: (a, b) =>
        b.indoor_seats + b.terrace_seats - (a.indoor_seats + a.terrace_seats),
};

const totalPrice = (b: BusinessForSale) => b.cash_needed_cents;

export function BusinessBrowser({
    game,
    businesses,
    map,
}: {
    game: GameSummary;
    businesses: BusinessForSale[];
    map: MapProps;
}) {
    const [filters, setFilters] = useState<Filters>(initialFilters);
    const [selected, setSelected] = useState<BusinessForSale | null>(null);
    const [view, setView] = useState<'list' | 'map'>('list');
    const set = <K extends keyof Filters>(key: K, value: Filters[K]) =>
        setFilters((f) => ({ ...f, [key]: value }));

    const neighbourhoods = useMemo(
        () => [...new Set(businesses.map((b) => b.neighbourhood))].sort(),
        [businesses],
    );

    const shown = useMemo(() => {
        const search = filters.search.trim().toLowerCase();

        return businesses
            .filter(
                (b) =>
                    !filters.neighbourhood ||
                    b.neighbourhood === filters.neighbourhood,
            )
            .filter((b) => !filters.category || b.category === filters.category)
            .filter((b) => !filters.kitchen || b.kitchen === filters.kitchen)
            .filter((b) => b.footfall >= filters.minFootfall)
            .filter((b) => !filters.terrace || b.terrace_seats > 0)
            .filter(
                (b) => !filters.affordable || totalPrice(b) <= game.cash_cents,
            )
            .filter(
                (b) =>
                    !search || b.fictional_name.toLowerCase().includes(search),
            )
            .sort(sorters[filters.sort]);
    }, [businesses, filters, game.cash_cents]);

    return (
        <div className="space-y-4">
            <div
                className="flex flex-wrap items-end gap-3 rounded-lg border p-3"
                role="search"
            >
                <Field label="Search" htmlFor="f-search">
                    <Input
                        id="f-search"
                        value={filters.search}
                        onChange={(e) => set('search', e.target.value)}
                        placeholder="Name"
                        className="w-36"
                    />
                </Field>
                <Field label="Neighbourhood" htmlFor="f-neighbourhood">
                    <NativeSelect
                        id="f-neighbourhood"
                        value={filters.neighbourhood}
                        onChange={(v) => set('neighbourhood', v)}
                    >
                        <option value="">All</option>
                        {neighbourhoods.map((n) => (
                            <option key={n} value={n}>
                                {n}
                            </option>
                        ))}
                    </NativeSelect>
                </Field>
                <Field label="Type" htmlFor="f-category">
                    <NativeSelect
                        id="f-category"
                        value={filters.category}
                        onChange={(v) => set('category', v)}
                    >
                        <option value="">All</option>
                        <option value="cafe">Café</option>
                        <option value="cafe_bar">Café-bar</option>
                    </NativeSelect>
                </Field>
                <Field label="Kitchen" htmlFor="f-kitchen">
                    <NativeSelect
                        id="f-kitchen"
                        value={filters.kitchen}
                        onChange={(v) => set('kitchen', v)}
                    >
                        <option value="">Any</option>
                        <option value="none">None</option>
                        <option value="basic">Basic</option>
                        <option value="full">Full</option>
                    </NativeSelect>
                </Field>
                <Field
                    label={`Footfall at least ${filters.minFootfall}`}
                    htmlFor="f-footfall"
                >
                    <input
                        id="f-footfall"
                        type="range"
                        min={0}
                        max={9}
                        step={1}
                        value={filters.minFootfall}
                        onChange={(e) =>
                            set('minFootfall', Number(e.target.value))
                        }
                        className="h-9 w-32"
                    />
                </Field>
                <label className="flex h-9 items-center gap-2 text-sm">
                    <input
                        type="checkbox"
                        checked={filters.terrace}
                        onChange={(e) => set('terrace', e.target.checked)}
                    />
                    Terrace
                </label>
                <label className="flex h-9 items-center gap-2 text-sm">
                    <input
                        type="checkbox"
                        checked={filters.affordable}
                        onChange={(e) => set('affordable', e.target.checked)}
                    />
                    Only what I can afford
                </label>
                <Field label="Sort by" htmlFor="f-sort">
                    <NativeSelect
                        id="f-sort"
                        value={filters.sort}
                        onChange={(v) => set('sort', v as SortKey)}
                    >
                        <option value="traspaso">Cheapest traspaso</option>
                        <option value="rent">Lowest rent</option>
                        <option value="footfall">Busiest spot</option>
                        <option value="condition">Best condition</option>
                        <option value="seats">Most seats</option>
                    </NativeSelect>
                </Field>
                <Button
                    variant="ghost"
                    size="sm"
                    onClick={() => setFilters(initialFilters)}
                >
                    Reset
                </Button>
            </div>

            <div className="flex flex-wrap items-center justify-between gap-2">
                <p className="text-sm text-muted-foreground" aria-live="polite">
                    Showing {shown.length} of {businesses.length} businesses.
                    Buying costs the traspaso plus a deposit for the landlord,
                    which you get back when you sell.
                </p>
                <div
                    role="group"
                    aria-label="View"
                    className="flex rounded-md border p-0.5 text-sm"
                >
                    {(['list', 'map'] as const).map((v) => (
                        <button
                            key={v}
                            type="button"
                            aria-pressed={view === v}
                            onClick={() => setView(v)}
                            className={cn(
                                'rounded px-3 py-1',
                                view === v
                                    ? 'bg-muted font-medium'
                                    : 'text-muted-foreground',
                            )}
                        >
                            {v === 'list' ? 'List' : 'Map'}
                        </button>
                    ))}
                </div>
            </div>

            {view === 'map' ? (
                <GameMap
                    map={map}
                    forSale={shown}
                    selectedId={selected?.id}
                    onSelect={setSelected}
                />
            ) : shown.length === 0 ? (
                <p className="rounded-lg border p-6 text-center text-sm text-muted-foreground">
                    Nothing matches these filters.
                </p>
            ) : (
                <div className="overflow-x-auto rounded-lg border">
                    <table className="w-full text-sm tabular-nums">
                        <thead className="bg-muted/50 text-left">
                            <tr>
                                <th className="p-2">Business</th>
                                <th className="p-2">Neighbourhood</th>
                                <th className="p-2 text-right">Seats</th>
                                <th className="p-2 text-right">Footfall</th>
                                <th className="p-2 text-right">Condition</th>
                                <th className="p-2 text-right">Rent / month</th>
                                <th className="p-2 text-right">Traspaso</th>
                                <th className="p-2 text-right">Cash needed</th>
                            </tr>
                        </thead>
                        <tbody>
                            {shown.map((b) => (
                                <tr
                                    key={b.id}
                                    className={cn(
                                        'cursor-pointer border-t hover:bg-muted/50',
                                        totalPrice(b) > game.cash_cents &&
                                            'text-muted-foreground',
                                    )}
                                    onClick={() => setSelected(b)}
                                >
                                    <td className="p-2">
                                        <button
                                            type="button"
                                            className="text-left font-medium underline-offset-2 hover:underline"
                                            onClick={() => setSelected(b)}
                                        >
                                            {b.fictional_name}
                                        </button>
                                        <div className="text-xs text-muted-foreground">
                                            {humanize(b.category)} ·{' '}
                                            {humanize(b.street_type)}
                                        </div>
                                    </td>
                                    <td className="p-2">{b.neighbourhood}</td>
                                    <td className="p-2 text-right">
                                        {b.indoor_seats}
                                        {b.terrace_seats > 0 && (
                                            <span className="text-muted-foreground">
                                                {' '}
                                                + {b.terrace_seats}
                                            </span>
                                        )}
                                    </td>
                                    <td className="p-2 text-right">
                                        {b.footfall.toFixed(1)}
                                    </td>
                                    <td className="p-2 text-right">
                                        {b.condition}/10
                                    </td>
                                    <td className="p-2 text-right whitespace-nowrap">
                                        {formatCents(b.rent_month_cents)}
                                    </td>
                                    <td className="p-2 text-right whitespace-nowrap">
                                        {formatCents(b.traspaso_cents)}
                                    </td>
                                    <td className="p-2 text-right whitespace-nowrap">
                                        {formatCents(b.cash_needed_cents)}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}

            <Sheet
                open={selected !== null}
                onOpenChange={(open) => !open && setSelected(null)}
            >
                <SheetContent className="overflow-y-auto">
                    {selected && (
                        <BusinessDetails game={game} business={selected} />
                    )}
                </SheetContent>
            </Sheet>
        </div>
    );
}

function BusinessDetails({
    game,
    business: b,
}: {
    game: GameSummary;
    business: BusinessForSale;
}) {
    const price = totalPrice(b);
    const affordable = price <= game.cash_cents;

    return (
        <>
            <SheetHeader>
                <SheetTitle>{b.fictional_name}</SheetTitle>
                <SheetDescription>
                    {humanize(b.category)} in {b.neighbourhood} ·{' '}
                    {humanize(b.street_type).toLowerCase()}
                </SheetDescription>
            </SheetHeader>
            <dl className="grid grid-cols-2 gap-x-4 gap-y-2 px-4 text-sm">
                <Detail label="Floor area">{b.floor_area_m2} m²</Detail>
                <Detail label="Seats">
                    {b.indoor_seats} inside
                    {b.terrace_seats > 0
                        ? `, ${b.terrace_seats} on the terrace`
                        : ', no terrace'}
                </Detail>
                <Detail label="Footfall">{b.footfall.toFixed(1)} / 10</Detail>
                {b.footfall_by_day_part && (
                    <Detail label="By time of day">
                        {Object.entries(b.footfall_by_day_part)
                            .map(
                                ([part, value]) =>
                                    `${humanize(part)} ${value.toFixed(1)}`,
                            )
                            .join(' · ')}
                    </Detail>
                )}
                <Detail label="Condition">{b.condition} / 10</Detail>
                <Detail label="Equipment age">
                    {b.equipment_age_years}{' '}
                    {b.equipment_age_years === 1 ? 'year' : 'years'}
                </Detail>
                <Detail label="Reputation">
                    {Math.round(b.base_reputation)} / 100
                </Detail>
                <Detail label="Kitchen">{humanize(b.kitchen)}</Detail>
                <Detail label="Licence">{humanize(b.licence)}</Detail>
                <Detail label="Rent">
                    {formatCents(b.rent_month_cents)} / month
                </Detail>
            </dl>
            <div className="mx-4 space-y-1 rounded-lg bg-muted/50 p-3 text-sm tabular-nums">
                <div className="flex justify-between">
                    <span>Traspaso</span>
                    <span>{formatCents(b.traspaso_cents)}</span>
                </div>
                <div className="flex justify-between">
                    <span>
                        Deposit and guarantee (paid back when you leave)
                    </span>
                    <span>{formatCents(b.buying_costs.held_cents)}</span>
                </div>
                <div className="flex justify-between">
                    <span>Lawyer: contract and lease assignment</span>
                    <span>{formatCents(b.buying_costs.legal_cents)}</span>
                </div>
                <div className="flex justify-between">
                    <span>Licence in your name</span>
                    <span>{formatCents(b.buying_costs.licence_cents)}</span>
                </div>
                <div className="flex justify-between border-t pt-1 font-medium">
                    <span>Cash needed</span>
                    <span>{formatCents(price)}</span>
                </div>
                <div className="flex justify-between text-muted-foreground">
                    <span>Cash left to trade with</span>
                    <span
                        className={cn(
                            !affordable && 'text-red-600 dark:text-red-400',
                        )}
                    >
                        {formatCents(game.cash_cents - price)}
                    </span>
                </div>
            </div>
            <SheetFooter>
                <Form {...PurchaseController.store.form(game.id)}>
                    {({ processing, errors }) => (
                        <div className="space-y-2">
                            <input
                                type="hidden"
                                name="business_id"
                                value={b.id}
                            />
                            <Button
                                className="w-full"
                                disabled={processing || !affordable}
                            >
                                {affordable
                                    ? `Buy for ${formatCents(price)}`
                                    : 'Not enough cash'}
                            </Button>
                            <InputError message={errors.business_id} />
                        </div>
                    )}
                </Form>
            </SheetFooter>
        </>
    );
}

function Detail({
    label,
    children,
}: {
    label: string;
    children: React.ReactNode;
}) {
    return (
        <>
            <dt className="text-muted-foreground">{label}</dt>
            <dd>{children}</dd>
        </>
    );
}

function Field({
    label,
    htmlFor,
    children,
}: {
    label: string;
    htmlFor: string;
    children: React.ReactNode;
}) {
    return (
        <div className="grid gap-1">
            <Label htmlFor={htmlFor} className="text-xs text-muted-foreground">
                {label}
            </Label>
            {children}
        </div>
    );
}

function NativeSelect({
    id,
    value,
    onChange,
    children,
}: {
    id: string;
    value: string;
    onChange: (value: string) => void;
    children: React.ReactNode;
}) {
    return (
        <select
            id={id}
            value={value}
            onChange={(e) => onChange(e.target.value)}
            className="h-9 rounded-md border border-input bg-transparent px-2 text-sm shadow-xs dark:bg-input/30"
        >
            {children}
        </select>
    );
}
