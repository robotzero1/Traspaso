<?php

namespace App\Simulation\Events;

use App\Simulation\Data\BusinessState;
use InvalidArgumentException;

/**
 * The measures event probabilities and outcome weights can depend on.
 * Most run 0–1; staff_count and equipment_age_years are plain counts.
 */
final readonly class EventSignals
{
    /** @param array<string, float> $values */
    public function __construct(private array $values) {}

    public static function from(BusinessState $state, float $quality, float $utilisation, float $comfortableUtilisation): self
    {
        return new self([
            'equipment_wear' => 1 - $state->equipmentHealth / 100,
            'low_morale' => 1 - $state->staffMorale / 100,
            'low_quality' => 1 - $quality / 100,
            'low_reputation' => 1 - $state->reputation / 100,
            'reputation' => $state->reputation / 100,
            'overwork' => max(0.0, $utilisation - $comfortableUtilisation),
            'staff_count' => (float) $state->staffCount,
            'equipment_age_years' => $state->equipmentAgeMonths / 12,
        ]);
    }

    /**
     * base + Σ factor × signal, clamped to 0–1.
     *
     * @param  array<string, int|float>  $formula
     */
    public function evaluate(array $formula): float
    {
        $value = 0.0;

        foreach ($formula as $name => $factor) {
            if ($name === 'base') {
                $value += $factor;

                continue;
            }

            $signal = $this->values[$name] ?? throw new InvalidArgumentException("Unknown event signal [{$name}].");
            $value += $factor * $signal;
        }

        return min(1.0, max(0.0, $value));
    }
}
