import { formatCents, formatPercent, monthName } from '@/lib/format';
import type { GameSummary } from '@/types/game';

export function GameHeader({ game }: { game: GameSummary }) {
    const change = game.net_worth_cents / game.starting_capital_cents - 1;
    const month =
        game.months === null
            ? game.current_month
            : Math.min(game.current_month, game.months);

    return (
        <div className="grid gap-4 rounded-xl border p-4 sm:grid-cols-4">
            <Stat label="Month">
                {month}
                {game.months !== null && ` of ${game.months}`}
                {game.calendar_month !== null && (
                    <span className="text-muted-foreground">
                        {' '}
                        · {monthName(game.calendar_month)}
                    </span>
                )}
            </Stat>
            <Stat label="Cash">{formatCents(game.cash_cents)}</Stat>
            <Stat label="Net worth">
                {formatCents(game.net_worth_cents)}{' '}
                <span
                    className={
                        change >= 0 ? 'text-emerald-600' : 'text-red-600'
                    }
                >
                    {formatPercent(change)}
                </span>
            </Stat>
            <Stat label="Started with">
                {formatCents(game.starting_capital_cents)}
            </Stat>
        </div>
    );
}

function Stat({
    label,
    children,
}: {
    label: string;
    children: React.ReactNode;
}) {
    return (
        <div>
            <div className="text-xs text-muted-foreground uppercase">
                {label}
            </div>
            <div className="text-lg font-semibold">{children}</div>
        </div>
    );
}
