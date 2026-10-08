import { Form } from '@inertiajs/react';
import SaleController from '@/actions/App/Http/Controllers/Game/SaleController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatCents } from '@/lib/format';

type Costs = {
    commission_cents: number;
    gestoria_cents: number;
    gain_cents: number;
    tax_cents: number;
    net_cents: number;
};

export type SaleOffer = {
    id: number;
    buyer: string;
    amount_cents: number;
    status:
        | 'open'
        | 'countered'
        | 'accepted'
        | 'rejected'
        | 'lapsed'
        | 'walked';
    counter_cents: number | null;
    made_on: string;
    expires_on: string;
};

export type SaleProps = {
    listing: {
        id: number;
        asking_cents: number;
        agency: boolean;
        price_cents: number | null;
        costs: Costs | null;
        listed_on: string;
        accepted_on: string | null;
        completes_on: string | null;
        completed_on: string | null;
        offers: SaleOffer[];
    } | null;
    value_cents: number;
    private: Costs;
    agency: Costs;
    offer_days: number;
    handover_days: number;
};

const statusText: Record<SaleOffer['status'], string> = {
    open: 'waiting for you',
    countered: 'considering your counter',
    accepted: 'accepted',
    rejected: 'rejected',
    lapsed: 'lapsed',
    walked: 'walked away',
};

function formatDay(date: string): string {
    return new Date(`${date}T12:00:00`).toLocaleDateString('en-GB', {
        day: 'numeric',
        month: 'short',
    });
}

/** Selling the café (SPEC §12): list it, then answer the buyers. */
export function SalePanel({
    gameId,
    sale,
}: {
    gameId: number;
    sale: SaleProps;
}) {
    const listing = sale.listing;

    if (listing === null) {
        return <ListForm gameId={gameId} sale={sale} />;
    }

    if (listing.accepted_on && listing.costs && listing.price_cents) {
        return (
            <section className="space-y-3">
                <Heading
                    title="Sale agreed"
                    description={`Agreed at ${formatCents(listing.price_cents)} on ${formatDay(listing.accepted_on)}. It completes on ${formatDay(listing.completes_on!)}, after that month's bills; the café trades until then.`}
                />
                <CostsTable price={listing.price_cents} costs={listing.costs} />
            </section>
        );
    }

    const open = listing.offers.filter((o) => o.status === 'open');
    const past = listing.offers.filter((o) => o.status !== 'open');

    return (
        <section className="space-y-4">
            <Heading
                title="Your café is for sale"
                description={`Asking ${formatCents(listing.asking_cents)}, ${listing.agency ? 'through an agency' : 'privately'}, since ${formatDay(listing.listed_on)}. Buyers look at the last 12 months' accounts; the café keeps trading.`}
            />
            {open.length === 0 && (
                <p className="text-sm text-muted-foreground">
                    No offers waiting. Buyers turn up at random, more often when
                    the price is close to what the café is worth (about{' '}
                    {formatCents(sale.value_cents)} now).
                </p>
            )}
            {open.map((offer) => (
                <OfferCard
                    key={offer.id}
                    gameId={gameId}
                    offer={offer}
                    askingCents={listing.asking_cents}
                />
            ))}
            {past.length > 0 && (
                <ul className="space-y-1 text-sm text-muted-foreground">
                    {past.map((o) => (
                        <li key={o.id}>
                            {formatDay(o.made_on)} · {o.buyer} offered{' '}
                            {formatCents(o.amount_cents)}
                            {o.counter_cents !== null &&
                                `, you countered ${formatCents(o.counter_cents)}`}{' '}
                            · {statusText[o.status]}
                        </li>
                    ))}
                </ul>
            )}
            <Form {...SaleController.destroy.form(gameId)}>
                {({ processing, errors }) => (
                    <>
                        <Button
                            variant="secondary"
                            size="sm"
                            disabled={processing}
                        >
                            Withdraw the listing
                        </Button>
                        <InputError message={errors.listing} />
                    </>
                )}
            </Form>
        </section>
    );
}

function ListForm({ gameId, sale }: { gameId: number; sale: SaleProps }) {
    const suggested = Math.round(sale.value_cents / 100 / 500) * 500;

    return (
        <section className="space-y-4">
            <Heading
                title="Sell the café"
                description={`A buyer would value it at about ${formatCents(sale.value_cents)}: the premises and fit-out, plus goodwill from the last 12 months' profit after your pay. Selling takes time; set the price too high and few buyers come.`}
            />
            <div className="grid gap-4 sm:grid-cols-2">
                <div>
                    <p className="mb-1 text-sm font-medium">
                        Selling privately, at that value
                    </p>
                    <CostsTable price={sale.value_cents} costs={sale.private} />
                </div>
                <div>
                    <p className="mb-1 text-sm font-medium">
                        Through an agency (more buyers)
                    </p>
                    <CostsTable price={sale.value_cents} costs={sale.agency} />
                </div>
            </div>
            <Form
                {...SaleController.store.form(gameId)}
                className="flex flex-wrap items-end gap-3"
            >
                {({ processing, errors }) => (
                    <>
                        <div className="grid gap-1">
                            <Label htmlFor="asking">Asking price (€)</Label>
                            <Input
                                id="asking"
                                name="asking"
                                type="number"
                                min={1000}
                                step={500}
                                defaultValue={suggested}
                                className="w-40"
                                required
                            />
                        </div>
                        <label className="flex items-center gap-2 text-sm">
                            <input type="checkbox" name="agency" value="1" />
                            Use an agency
                        </label>
                        <Button disabled={processing}>List it</Button>
                        <InputError message={errors.asking} />
                    </>
                )}
            </Form>
        </section>
    );
}

function OfferCard({
    gameId,
    offer,
    askingCents,
}: {
    gameId: number;
    offer: SaleOffer;
    askingCents: number;
}) {
    const action = SaleController.answer.form({
        game: gameId,
        offer: offer.id,
    });

    return (
        <div className="space-y-3 rounded-lg border p-3">
            <p>
                <strong>{offer.buyer}</strong> offers{' '}
                <strong>{formatCents(offer.amount_cents)}</strong>{' '}
                <Badge variant="secondary">
                    answer by {formatDay(offer.expires_on)}
                </Badge>
            </p>
            <Form {...action} className="flex flex-wrap items-end gap-2">
                {({ processing, errors }) => (
                    <>
                        <Button
                            name="answer"
                            value="accept"
                            size="sm"
                            disabled={processing}
                        >
                            Accept
                        </Button>
                        <Button
                            name="answer"
                            value="reject"
                            size="sm"
                            variant="secondary"
                            disabled={processing}
                        >
                            Reject
                        </Button>
                        <Input
                            name="counter"
                            type="number"
                            step={500}
                            min={Math.ceil(offer.amount_cents / 100) + 1}
                            max={askingCents / 100}
                            placeholder="Counter (€)"
                            aria-label="Counter-offer in euros"
                            className="w-36"
                        />
                        <Button
                            name="answer"
                            value="counter"
                            size="sm"
                            variant="outline"
                            disabled={processing}
                        >
                            Counter
                        </Button>
                        <InputError message={errors.counter ?? errors.offer} />
                    </>
                )}
            </Form>
            <p className="text-xs text-muted-foreground">
                A buyer answers a counter-offer overnight: they take it if it's
                within what they'd pay, or walk away.
            </p>
        </div>
    );
}

function CostsTable({ price, costs }: { price: number; costs: Costs }) {
    const rows: [string, number][] = [
        ['Price', price],
        ...(costs.commission_cents
            ? ([['Agency commission', -costs.commission_cents]] as [
                  string,
                  number,
              ][])
            : []),
        ['Gestoría', -costs.gestoria_cents],
        ['Income tax on the gain (IRPF)', -costs.tax_cents],
    ];

    return (
        <table className="w-full max-w-sm text-sm">
            <tbody>
                {rows.map(([label, cents]) => (
                    <tr key={label}>
                        <td className="py-0.5 text-muted-foreground">
                            {label}
                        </td>
                        <td className="py-0.5 text-right tabular-nums">
                            {formatCents(cents === 0 ? 0 : cents)}
                        </td>
                    </tr>
                ))}
                <tr className="border-t font-medium">
                    <td className="py-1">You keep</td>
                    <td className="py-1 text-right tabular-nums">
                        {formatCents(costs.net_cents)}
                    </td>
                </tr>
            </tbody>
        </table>
    );
}
