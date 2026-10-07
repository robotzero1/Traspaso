<?php

namespace App\Viability;

use App\Simulation\Data\DayPart;
use App\Simulation\Data\Kitchen;
use App\Simulation\Data\Licence;
use App\Simulation\Data\QualityTier;

/**
 * What the user tells the viability check: where the café is, the listing's
 * figures, the money they have, and how they would run it.
 */
final readonly class ViabilityInput
{
    /** @param list<DayPart> $openDayParts */
    public function __construct(
        public float $lat,
        public float $lng,
        public int $traspasoCents,
        public int $rentMonthCents,
        public int $floorAreaM2,
        public int $indoorSeats,
        public int $terraceSeats,
        public Licence $licence,
        public Kitchen $kitchen,
        /** 1–10: the state of the premises. */
        public int $condition,
        public int $capitalCents,
        public array $openDayParts,
        public int $staffCount,
        public QualityTier $qualityTier,
        public float $priceLevel = 1.0,
    ) {}

    /** @param array<string, mixed> $data as stored on the report */
    public static function fromArray(array $data): self
    {
        return new self(
            lat: (float) $data['lat'],
            lng: (float) $data['lng'],
            traspasoCents: (int) $data['traspaso_cents'],
            rentMonthCents: (int) $data['rent_month_cents'],
            floorAreaM2: (int) $data['floor_area_m2'],
            indoorSeats: (int) $data['indoor_seats'],
            terraceSeats: (int) $data['terrace_seats'],
            licence: Licence::from($data['licence']),
            kitchen: Kitchen::from($data['kitchen']),
            condition: (int) $data['condition'],
            capitalCents: (int) $data['capital_cents'],
            openDayParts: array_map(fn (string $p) => DayPart::from($p), $data['open_day_parts']),
            staffCount: (int) $data['staff_count'],
            qualityTier: QualityTier::from($data['quality_tier']),
            priceLevel: (float) ($data['price_level'] ?? 1.0),
        );
    }
}
