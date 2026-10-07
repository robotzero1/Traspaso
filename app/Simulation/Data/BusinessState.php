<?php

namespace App\Simulation\Data;

use App\Simulation\Data\Concerns\Guard;
use App\Simulation\Data\Concerns\Immutable;

/**
 * A snapshot of the player's business at the start (or end) of a month.
 * Scores run 0–100. Cash can go negative; the game decides what that means.
 *
 * $modifiers are lasting event effects still in force; $pendingEvents are
 * events from last month waiting for the player's choice. $localTrend is
 * how the spot's custom has drifted since purchase (1.0 = as bought): over
 * the years streets gain and lose offices, shops and residents.
 */
final readonly class BusinessState
{
    use Immutable;

    /**
     * @param  list<Modifier>  $modifiers
     * @param  list<EventRecord>  $pendingEvents
     */
    public function __construct(
        public BusinessProfile $profile,
        public int $cashCents,
        public float $reputation,
        public int $staffCount,
        public float $staffMorale,
        public float $equipmentHealth,
        public int $equipmentAgeMonths,
        public float $stockQuality,
        public array $modifiers = [],
        public array $pendingEvents = [],
        public float $localTrend = 1.0,
    ) {
        Guard::between('reputation', $reputation, 0, 100);
        Guard::nonNegative('staffCount', $staffCount);
        Guard::between('staffMorale', $staffMorale, 0, 100);
        Guard::between('equipmentHealth', $equipmentHealth, 0, 100);
        Guard::nonNegative('equipmentAgeMonths', $equipmentAgeMonths);
        Guard::between('stockQuality', $stockQuality, 0, 100);
        Guard::listOf('modifiers', $modifiers, Modifier::class);
        Guard::listOf('pendingEvents', $pendingEvents, EventRecord::class);
        Guard::positive('localTrend', $localTrend);
    }

    public function modifierSet(): ModifierSet
    {
        return new ModifierSet($this->modifiers);
    }
}
