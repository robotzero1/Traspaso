<?php

namespace App\Generation;

use App\Simulation\Data\BusinessCategory;
use App\Simulation\Data\ParameterSheet;
use App\Simulation\Rng\SeededRng;

/**
 * Draws unique fictional names like "Cafetería El Cierzo" within a market.
 */
final class NameDrawer
{
    private const ATTEMPTS = 20;

    /** @var array<string, true> */
    private array $used = [];

    /** @param  list<string>  $taken  names already in use, never drawn */
    public function __construct(
        private readonly ParameterSheet $sheet,
        private readonly SeededRng $rng,
        array $taken = [],
    ) {
        $this->used = array_fill_keys($taken, true);
    }

    public function draw(BusinessCategory $category): string
    {
        $prefixes = $this->sheet->array("names.prefixes.{$category->value}");
        $names = $this->sheet->array('names.names');

        for ($attempt = 0; $attempt < self::ATTEMPTS; $attempt++) {
            $name = $this->rng->pick($prefixes).' '.$this->rng->pick($names);

            if (! isset($this->used[$name])) {
                return $this->use($name);
            }
        }

        // Every combination is likely taken: number the last one drawn.
        for ($n = 2; isset($this->used["{$name} {$n}"]); $n++);

        return $this->use("{$name} {$n}");
    }

    private function use(string $name): string
    {
        $this->used[$name] = true;

        return $name;
    }
}
