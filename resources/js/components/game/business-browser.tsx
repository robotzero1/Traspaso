import { Form } from '@inertiajs/react';
import PurchaseController from '@/actions/App/Http/Controllers/Game/PurchaseController';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { formatCents, humanize } from '@/lib/format';
import type { BusinessForSale, GameSummary } from '@/types/game';

/** A plain list for now; filters and the map come in milestones 6–7. */
export function BusinessBrowser({
    game,
    businesses,
}: {
    game: GameSummary;
    businesses: BusinessForSale[];
}) {
    return (
        <div className="space-y-2">
            <p className="text-sm text-muted-foreground">
                {businesses.length} businesses for sale. Buying one costs the
                traspaso plus a deposit for the landlord, which you get back
                when you sell.
            </p>
            <Form {...PurchaseController.store.form(game.id)}>
                {({ processing, errors }) => (
                    <>
                        <InputError message={errors.business_id} />
                        <div className="overflow-x-auto rounded-lg border">
                            <table className="w-full text-sm">
                                <thead className="bg-muted/50 text-left">
                                    <tr>
                                        <th className="p-2">Business</th>
                                        <th className="p-2">Neighbourhood</th>
                                        <th className="p-2 text-right">m²</th>
                                        <th className="p-2 text-right">
                                            Seats
                                        </th>
                                        <th className="p-2 text-right">
                                            Footfall
                                        </th>
                                        <th className="p-2 text-right">
                                            Condition
                                        </th>
                                        <th className="p-2 text-right">
                                            Rent / month
                                        </th>
                                        <th className="p-2 text-right">
                                            Traspaso
                                        </th>
                                        <th className="p-2" />
                                    </tr>
                                </thead>
                                <tbody>
                                    {businesses.map((b) => {
                                        const price =
                                            b.traspaso_cents + b.deposit_cents;
                                        const affordable =
                                            price <= game.cash_cents;

                                        return (
                                            <tr key={b.id} className="border-t">
                                                <td className="p-2">
                                                    <div className="font-medium">
                                                        {b.fictional_name}
                                                    </div>
                                                    <div className="text-xs text-muted-foreground">
                                                        {humanize(b.category)} ·{' '}
                                                        {humanize(
                                                            b.street_type,
                                                        )}{' '}
                                                        · kitchen: {b.kitchen} ·
                                                        licence:{' '}
                                                        {humanize(b.licence)}
                                                    </div>
                                                </td>
                                                <td className="p-2">
                                                    {b.neighbourhood}
                                                </td>
                                                <td className="p-2 text-right">
                                                    {b.floor_area_m2}
                                                </td>
                                                <td className="p-2 text-right">
                                                    {b.indoor_seats}
                                                    {b.terrace_seats > 0 &&
                                                        ` + ${b.terrace_seats}`}
                                                </td>
                                                <td className="p-2 text-right">
                                                    {b.footfall.toFixed(1)}
                                                </td>
                                                <td className="p-2 text-right">
                                                    {b.condition}/10
                                                </td>
                                                <td className="p-2 text-right">
                                                    {formatCents(
                                                        b.rent_month_cents,
                                                    )}
                                                </td>
                                                <td className="p-2 text-right">
                                                    {formatCents(
                                                        b.traspaso_cents,
                                                    )}
                                                </td>
                                                <td className="p-2 text-right">
                                                    <Button
                                                        size="sm"
                                                        name="business_id"
                                                        value={b.id}
                                                        disabled={
                                                            processing ||
                                                            !affordable
                                                        }
                                                        title={
                                                            affordable
                                                                ? `Pay ${formatCents(price)}`
                                                                : 'Not enough cash'
                                                        }
                                                    >
                                                        Buy
                                                    </Button>
                                                </td>
                                            </tr>
                                        );
                                    })}
                                </tbody>
                            </table>
                        </div>
                    </>
                )}
            </Form>
        </div>
    );
}
