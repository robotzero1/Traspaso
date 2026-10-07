import { Form, Head, Link } from '@inertiajs/react';
import GameController from '@/actions/App/Http/Controllers/Game/GameController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatCents, formatPercent } from '@/lib/format';
import { BuyForm } from '@/components/payments/buy-form';
import { index, show } from '@/routes/games';
import payments from '@/routes/payments';
import type { GameSummary } from '@/types/game';

type Props = {
    games: GameSummary[];
    starting_capital: {
        min_euros: number;
        max_euros: number;
        free_euros: number;
    };
    capital_tiers: {
        key: string;
        capital_euros: number;
        price_cents: number;
    }[];
    payments_enabled: boolean;
};

export default function GamesIndex({
    games,
    starting_capital,
    capital_tiers,
    payments_enabled,
}: Props) {
    return (
        <>
            <Head title="Games" />
            <div className="flex flex-col gap-8 p-4">
                <section className="max-w-md space-y-4">
                    <Heading
                        title="New game"
                        description="Take over a café in Zaragoza. Choose how much capital you start with."
                    />
                    <Form
                        {...GameController.store.form()}
                        className="space-y-4"
                    >
                        {({ processing, errors }) => (
                            <>
                                <div className="grid gap-2">
                                    <Label htmlFor="starting_capital_euros">
                                        Starting capital (€)
                                    </Label>
                                    <Input
                                        id="starting_capital_euros"
                                        name="starting_capital_euros"
                                        type="number"
                                        step={1000}
                                        min={starting_capital.min_euros}
                                        max={starting_capital.max_euros}
                                        defaultValue={Math.min(
                                            30000,
                                            starting_capital.max_euros,
                                        )}
                                        required
                                    />
                                    <p className="text-xs text-muted-foreground">
                                        From{' '}
                                        {starting_capital.min_euros.toLocaleString(
                                            'es-ES',
                                        )}{' '}
                                        € to{' '}
                                        {starting_capital.max_euros.toLocaleString(
                                            'es-ES',
                                        )}{' '}
                                        €.
                                    </p>
                                    <InputError
                                        message={errors.starting_capital_euros}
                                    />
                                </div>
                                <Button disabled={processing}>
                                    Start game
                                </Button>
                            </>
                        )}
                    </Form>
                    {capital_tiers.length > 0 && (
                        <div className="space-y-3 rounded-lg border p-3">
                            <div className="text-sm">
                                <div className="font-medium">
                                    Bring more savings
                                </div>
                                <p className="text-muted-foreground">
                                    Up to{' '}
                                    {starting_capital.free_euros.toLocaleString(
                                        'es-ES',
                                    )}{' '}
                                    € is free. More opens up bigger premises,
                                    kitchens, terraces and busier streets. It's
                                    yours for every game you start.
                                </p>
                            </div>
                            {capital_tiers.map((tier) => (
                                <BuyForm
                                    key={tier.key}
                                    action={payments.capital()}
                                    fields={{ tier: tier.key }}
                                    label={`Up to ${tier.capital_euros.toLocaleString('es-ES')} €`}
                                    priceCents={tier.price_cents}
                                    enabled={payments_enabled}
                                />
                            ))}
                        </div>
                    )}
                </section>

                <section className="space-y-4">
                    <Heading title="Your games" />
                    {games.length === 0 && (
                        <p className="text-sm text-muted-foreground">
                            No games yet.
                        </p>
                    )}
                    <ul className="divide-y rounded-lg border">
                        {games.map((game) => (
                            <li key={game.id}>
                                <Link
                                    href={show(game.id)}
                                    className="flex flex-wrap items-center justify-between gap-2 p-4 hover:bg-muted/50"
                                >
                                    <div>
                                        <div className="font-medium">
                                            {game.business_name ??
                                                'Choosing a business'}
                                        </div>
                                        <div className="text-sm text-muted-foreground">
                                            Started with{' '}
                                            {formatCents(
                                                game.starting_capital_cents,
                                            )}{' '}
                                            · month{' '}
                                            {game.months === null
                                                ? game.current_month
                                                : `${Math.min(game.current_month, game.months)} of ${game.months}`}
                                        </div>
                                    </div>
                                    <div className="flex items-center gap-3">
                                        <span className="text-sm">
                                            {formatCents(game.net_worth_cents)}{' '}
                                            <span className="text-muted-foreground">
                                                (
                                                {formatPercent(
                                                    game.net_worth_cents /
                                                        game.starting_capital_cents -
                                                        1,
                                                )}
                                                )
                                            </span>
                                        </span>
                                        <Badge
                                            variant={
                                                game.status === 'bankrupt'
                                                    ? 'destructive'
                                                    : 'secondary'
                                            }
                                        >
                                            {game.status}
                                        </Badge>
                                    </div>
                                </Link>
                            </li>
                        ))}
                    </ul>
                </section>
            </div>
        </>
    );
}

GamesIndex.layout = {
    breadcrumbs: [{ title: 'Games', href: index() }],
};
