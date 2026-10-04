<?php

namespace App\Simulation\Events;

use App\Simulation\Data\DayPart;
use App\Simulation\Data\Modifier;
use App\Simulation\Data\ModifierEffect;
use InvalidArgumentException;

/**
 * What an event, outcome or choice does, read from the parameter sheet.
 */
final readonly class EventEffects
{
    private const KEYS = ['cost_cents', 'revenue_cents', 'reputation', 'morale', 'equipment_health', 'modifiers', 'add_competitor', 'remove_competitor'];

    /**
     * @param  list<array<string, mixed>>  $modifiers
     * @param  array<string, mixed>|null  $addCompetitor
     */
    public function __construct(
        public int $costCents = 0,
        public int $revenueCents = 0,
        public float $reputation = 0.0,
        public float $morale = 0.0,
        public float $equipmentHealth = 0.0,
        public array $modifiers = [],
        public ?array $addCompetitor = null,
        public ?string $removeCompetitor = null,
    ) {
        if ($costCents < 0 || $revenueCents < 0) {
            throw new InvalidArgumentException('Event cost_cents and revenue_cents must not be negative.');
        }
    }

    /** @param array<string, mixed> $config */
    public static function fromConfig(array $config): self
    {
        if ($unknown = array_diff(array_keys($config), self::KEYS)) {
            throw new InvalidArgumentException('Unknown event effect ['.implode(', ', $unknown).'].');
        }

        $effects = new self(
            costCents: $config['cost_cents'] ?? 0,
            revenueCents: $config['revenue_cents'] ?? 0,
            reputation: (float) ($config['reputation'] ?? 0),
            morale: (float) ($config['morale'] ?? 0),
            equipmentHealth: (float) ($config['equipment_health'] ?? 0),
            modifiers: $config['modifiers'] ?? [],
            addCompetitor: $config['add_competitor'] ?? null,
            removeCompetitor: $config['remove_competitor'] ?? null,
        );

        // Fail on a bad modifier when the sheet is read, not mid-game.
        $effects->modifiersFor('validation');

        return $effects;
    }

    /**
     * @return list<Modifier>
     */
    public function modifiersFor(string $source): array
    {
        return array_map(fn (array $m) => new Modifier(
            source: $source,
            effect: ModifierEffect::tryFrom($m['effect'] ?? '')
                ?? throw new InvalidArgumentException('Unknown modifier effect ['.($m['effect'] ?? '').'].'),
            value: (float) ($m['value'] ?? throw new InvalidArgumentException('A modifier needs a value.')),
            monthsRemaining: array_key_exists('months', $m)
                ? $m['months']
                : throw new InvalidArgumentException('A modifier needs months (null for permanent).'),
            dayParts: array_map(
                fn (string $p) => DayPart::tryFrom($p) ?? throw new InvalidArgumentException("Unknown day part [{$p}]."),
                $m['day_parts'] ?? [],
            ),
        ), $this->modifiers);
    }

    /**
     * For the event's payload, so the UI can describe it.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_filter([
            'cost_cents' => $this->costCents,
            'revenue_cents' => $this->revenueCents,
            'reputation' => $this->reputation,
            'morale' => $this->morale,
            'equipment_health' => $this->equipmentHealth,
            'modifiers' => $this->modifiers,
            'add_competitor' => $this->addCompetitor !== null,
            'remove_competitor' => $this->removeCompetitor,
        ], fn ($value) => $value !== 0 && $value !== 0.0 && $value !== [] && $value !== null && $value !== false);
    }
}
