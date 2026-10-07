import { Form, Link } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { formatCents } from '@/lib/format';
import { terms } from '@/routes/legal';

/**
 * Buying something digital: the price (IVA included), the buyer's consent
 * to immediate delivery and loss of the 14-day withdrawal right, then off
 * to Stripe Checkout.
 */
export function BuyForm({
    action,
    label,
    priceCents,
    enabled,
    fields = {},
}: {
    action: { url: string; method: 'post' };
    label: string;
    priceCents: number;
    enabled: boolean;
    fields?: Record<string, string>;
}) {
    if (!enabled) {
        return (
            <p className="text-sm text-muted-foreground">
                Payments aren't set up on this server yet.
            </p>
        );
    }

    return (
        <Form action={action.url} method={action.method} className="space-y-2">
            {({ processing, errors }) => (
                <>
                    {Object.entries(fields).map(([name, value]) => (
                        <input
                            key={name}
                            type="hidden"
                            name={name}
                            value={value}
                        />
                    ))}
                    <label className="flex items-start gap-2 text-xs text-muted-foreground">
                        <input
                            type="checkbox"
                            name="waive_withdrawal"
                            value="1"
                            required
                            className="mt-0.5"
                        />
                        <span>
                            I want it straight away, and understand that I then
                            lose the 14-day right of withdrawal. I accept the{' '}
                            <Link href={terms()} className="underline">
                                terms
                            </Link>
                            .
                        </span>
                    </label>
                    <Button disabled={processing}>
                        {label} · {formatCents(priceCents)}
                    </Button>
                    <p className="text-xs text-muted-foreground">
                        IVA included. Paid securely with Stripe; you get a
                        receipt by email.
                    </p>
                    <InputError
                        message={
                            errors.waive_withdrawal ??
                            errors.payment ??
                            errors.tier
                        }
                    />
                </>
            )}
        </Form>
    );
}
