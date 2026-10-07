<?php

namespace App\Balance;

/** How one balance game ended. */
final readonly class GameOutcome
{
    public function __construct(
        public string $strategy,
        public int $seed,
        public int $startingCapitalCents,
        public bool $bought,
        public ?string $neighbourhood = null,
        public ?float $footfall = null,
        public int $traspasoCents = 0,
        public int $rentMonthCents = 0,
        public int $rivals = 0,
        public int $monthsPlayed = 0,
        public ?int $bankruptInMonth = null,
        public int $totalProfitCents = 0,
        public int $netWorthCents = 0,
        public int $ownerPaidCents = 0,
        public int $years = 1,
        /** The year (from purchase, 1 = first) the café closed in; null if still open. */
        public ?int $closedInYear = null,
        /** @var list<int> profit (before the owner's pay) of each month played */
        public array $profitsByMonth = [],
        /** @var list<int> revenue of each month played */
        public array $revenueByMonth = [],
    ) {}

    /** Net worth change as a share of starting capital. */
    public function change(): float
    {
        return ($this->netWorthCents - $this->startingCapitalCents) / $this->startingCapitalCents;
    }

    public function bankrupt(): bool
    {
        return $this->bankruptInMonth !== null;
    }

    /**
     * Whether the café failed in its first year, as the closure statistics
     * count it: the cash ran out, or over the year it didn't earn enough
     * to pay its owner (a real owner would close or sell up).
     */
    public function failed(): bool
    {
        return $this->closedBy(1);
    }

    /** Closed by the end of the given year from purchase. */
    public function closedBy(int $year): bool
    {
        return $this->bought && $this->closedInYear !== null && $this->closedInYear <= $year;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $vars = get_object_vars($this);
        unset($vars['profitsByMonth'], $vars['revenueByMonth']);

        return [...$vars, 'change' => round($this->change(), 4)];
    }
}
