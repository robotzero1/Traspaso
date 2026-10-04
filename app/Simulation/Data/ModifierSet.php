<?php

namespace App\Simulation\Data;

/**
 * The combined effect of the modifiers active this month. Multipliers
 * multiply, everything else adds up.
 */
final readonly class ModifierSet
{
    /**
     * @param  list<Modifier>  $modifiers
     */
    public function __construct(public array $modifiers = []) {}

    public function demand(DayPart $part): float
    {
        return $this->product(ModifierEffect::Demand, $part);
    }

    public function capacity(DayPart $part): float
    {
        return $this->product(ModifierEffect::Capacity, $part);
    }

    public function rent(): float
    {
        return $this->product(ModifierEffect::Rent);
    }

    public function qualityPenalty(): float
    {
        return $this->sum(ModifierEffect::QualityPenalty);
    }

    public function cogsShare(): float
    {
        return $this->sum(ModifierEffect::CogsShare);
    }

    public function staffShortage(): int
    {
        return (int) round($this->sum(ModifierEffect::StaffShortage));
    }

    public function monthlyCostCents(): int
    {
        return (int) round($this->sum(ModifierEffect::MonthlyCost));
    }

    /**
     * The modifiers that are still active next month.
     *
     * @return list<Modifier>
     */
    public function tick(): array
    {
        return array_values(array_filter(array_map(fn (Modifier $m) => $m->tick(), $this->modifiers)));
    }

    private function product(ModifierEffect $effect, ?DayPart $part = null): float
    {
        $product = 1.0;

        foreach ($this->matching($effect, $part) as $modifier) {
            $product *= $modifier->value;
        }

        return $product;
    }

    private function sum(ModifierEffect $effect): float
    {
        return array_sum(array_map(fn (Modifier $m) => $m->value, $this->matching($effect)));
    }

    /** @return list<Modifier> */
    private function matching(ModifierEffect $effect, ?DayPart $part = null): array
    {
        return array_values(array_filter(
            $this->modifiers,
            fn (Modifier $m) => $m->effect === $effect && ($part === null || $m->appliesTo($part)),
        ));
    }
}
