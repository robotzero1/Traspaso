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

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [...get_object_vars($this), 'change' => round($this->change(), 4)];
    }
}
