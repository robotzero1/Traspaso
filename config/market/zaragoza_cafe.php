<?php

/*
|--------------------------------------------------------------------------
| Market parameter sheet: cafés and café-bars in Zaragoza
|--------------------------------------------------------------------------
|
| Every tunable number the generator and the engine use (SPEC.md §7).
|
| ⚠️ Every value here is a PLACEHOLDER until it has been checked against
| research. Each section has a `source` key: it starts with "PLACEHOLDER"
| and should be replaced with the real source once the numbers are
| verified. `php artisan market:placeholders` lists the sections that
| are still placeholders.
|
| Conventions:
| - Money is integer cents. Keys holding money end in `_cents`.
| - Percentile tables map percent (0–100) to value. 0 and 100 are the
|   hard minimum and maximum; values in between are interpolated linearly.
| - Weight tables are relative: they don't need to add up to 100.
| - Correlations are with standard normal "scores" for location, floor
|   area and condition. The squares of each group must add up to ≤ 1.
|
*/

return [

    'city' => 'Zaragoza',

    /*
    |--------------------------------------------------------------------------
    | Generation: how many businesses, and what kind
    |--------------------------------------------------------------------------
    */

    'business_count' => [
        'source' => 'PLACEHOLDER: game design (SPEC §2 says 100–200)',
        'min' => 100,
        'max' => 200,
    ],

    // Where listings appear: weight = population × (1 + commercial_boost ×
    // (tourist_index + office_index) / 20), so busy commercial areas get
    // more listings than their population alone would give them.
    'neighbourhood_weighting' => [
        'source' => 'PLACEHOLDER: to verify against listing counts per district',
        'commercial_boost' => 1.0,
    ],

    'categories' => [
        'source' => 'PLACEHOLDER: manual sample of ~100 listings',
        'weights' => [
            'cafe' => 55,
            'cafe_bar' => 45,
        ],
    ],

    // Licence weights by category. A café-bar never has a plain café licence.
    'licences' => [
        'source' => 'PLACEHOLDER: manual sample of ~100 listings',
        'weights' => [
            'cafe' => ['cafe' => 85, 'cafe_bar' => 15],
            'cafe_bar' => ['cafe_bar' => 80, 'bar_musical' => 20],
        ],
    ],

    'kitchens' => [
        'source' => 'PLACEHOLDER: manual sample of ~100 listings',
        'weights' => [
            'cafe' => ['none' => 40, 'basic' => 50, 'full' => 10],
            'cafe_bar' => ['none' => 15, 'basic' => 50, 'full' => 35],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Location: footfall 0–10
    |--------------------------------------------------------------------------
    |
    | footfall = Σ index_weights × neighbourhood index
    |          + street type bonus
    |          + normal(0, noise_sd), clamped to 0–10.
    |
    */

    'footfall' => [
        'source' => 'PLACEHOLDER: to derive from OSM footfall proxies (milestone 8)',
        'index_weights' => [
            'student' => 0.20,
            'tourist' => 0.25,
            'office' => 0.25,
            'transport' => 0.30,
        ],
        'noise_sd' => 1.0,
        'street_types' => [
            'main_street' => ['weight' => 20, 'footfall_bonus' => 2.0],
            'square' => ['weight' => 10, 'footfall_bonus' => 1.5],
            'secondary_street' => ['weight' => 45, 'footfall_bonus' => 0.5],
            'side_street' => ['weight' => 25, 'footfall_bonus' => -1.0],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Premises
    |--------------------------------------------------------------------------
    */

    'floor_area_m2' => [
        'source' => 'PLACEHOLDER: manual sample of ~100 listings',
        'percentiles' => [0 => 35, 10 => 40, 25 => 47, 50 => 55, 75 => 66, 90 => 80, 100 => 90],
    ],

    'seating' => [
        'source' => 'PLACEHOLDER: manual sample of ~100 listings',
        'indoor_seats_per_m2' => 0.5,
        // Relative spread around floor area × seats per m².
        'indoor_seats_sd' => 0.1,
        'min_indoor_seats' => 6,
        'terrace_probability' => 0.55,
        'terrace_tables' => ['min' => 2, 'max' => 8],
        'seats_per_terrace_table' => 4,
    ],

    // 1–10. Drawn as a continuous value, rounded, then clamped.
    'condition' => [
        'source' => 'PLACEHOLDER: game design',
        'percentiles' => [0 => 1, 10 => 3, 25 => 4, 50 => 6, 75 => 7, 90 => 8, 100 => 10],
    ],

    'equipment_age_years' => [
        'source' => 'PLACEHOLDER: manual sample of ~100 listings',
        'percentiles' => [0 => 0, 10 => 1, 25 => 3, 50 => 6, 75 => 10, 90 => 14, 100 => 25],
        // Older equipment goes with worse condition.
        'correlation' => ['condition' => -0.6],
    ],

    // 0–100. normal(mean, sd), correlated with condition, then clamped.
    'base_reputation' => [
        'source' => 'PLACEHOLDER: game design',
        'mean' => 55,
        'sd' => 12,
        'min' => 10,
        'max' => 90,
        'correlation' => ['condition' => 0.3],
    ],

    /*
    |--------------------------------------------------------------------------
    | Money: rent and traspaso
    |--------------------------------------------------------------------------
    |
    | The overall distribution follows the percentiles exactly; the
    | correlations only decide which businesses get the expensive draws.
    |
    */

    'rent' => [
        'source' => 'PLACEHOLDER: manual sample of ~100 listings',
        'percentiles_cents' => [
            0 => 35_000,
            10 => 50_000,
            25 => 60_000,
            50 => 72_500,
            75 => 90_000,
            90 => 115_000,
            100 => 200_000,
        ],
        'rounding_cents' => 2_500,
        'correlation' => ['location' => 0.5, 'floor_area' => 0.4],
    ],

    'traspaso' => [
        'source' => 'PLACEHOLDER: manual sample of ~100 listings',
        'percentiles_cents' => [
            0 => 300_000,
            10 => 800_000,
            25 => 1_200_000,
            50 => 1_800_000,
            75 => 2_700_000,
            90 => 4_500_000,
            100 => 9_000_000,
        ],
        'rounding_cents' => 50_000,
        'correlation' => ['location' => 0.45, 'condition' => 0.35, 'floor_area' => 0.2],
    ],

    /*
    |--------------------------------------------------------------------------
    | Trading: day parts, tickets and seasonality (used by the engine)
    |--------------------------------------------------------------------------
    */

    'day_parts' => [
        'source' => 'PLACEHOLDER: typical Zaragoza trading hours / own observation',
        // Hours are on a 0–27 clock so night (0–3) sorts after evening.
        // demand_mix: how much each driver contributes to that day part.
        'morning' => [
            'start_hour' => 7, 'end_hour' => 12,
            'demand_mix' => ['office' => 0.35, 'transport' => 0.30, 'student' => 0.20, 'population' => 0.15],
        ],
        'lunch' => [
            'start_hour' => 12, 'end_hour' => 16,
            'demand_mix' => ['office' => 0.40, 'tourist' => 0.25, 'population' => 0.20, 'transport' => 0.15],
        ],
        'afternoon' => [
            'start_hour' => 16, 'end_hour' => 20,
            'demand_mix' => ['student' => 0.35, 'population' => 0.35, 'tourist' => 0.15, 'transport' => 0.15],
        ],
        'evening' => [
            'start_hour' => 20, 'end_hour' => 24,
            'demand_mix' => ['population' => 0.40, 'tourist' => 0.35, 'student' => 0.25],
        ],
        'night' => [
            'start_hour' => 24, 'end_hour' => 27,
            'demand_mix' => ['tourist' => 0.50, 'student' => 0.50],
        ],
    ],

    'licence_day_parts' => [
        'source' => 'PLACEHOLDER: Zaragoza licensing ordinance (opening-hours rules)',
        'cafe' => ['morning', 'lunch', 'afternoon', 'evening'],
        'cafe_bar' => ['morning', 'lunch', 'afternoon', 'evening', 'night'],
        'bar_musical' => ['morning', 'lunch', 'afternoon', 'evening', 'night'],
    ],

    // Average spend per customer, before price level, by day part.
    'average_ticket_cents' => [
        'source' => 'PLACEHOLDER: own observation / menus',
        'morning' => ['min' => 350, 'max' => 500],
        'lunch' => ['min' => 450, 'max' => 1_000],
        'afternoon' => ['min' => 350, 'max' => 500],
        'evening' => ['min' => 600, 'max' => 1_000],
        'night' => ['min' => 700, 'max' => 1_200],
    ],

    // Jan … Dec. August empties out; October has the Pilar festival.
    'seasonality' => [
        'source' => 'PLACEHOLDER: Zaragoza climate + Pilar festival in October',
        'multipliers' => [
            1 => 0.85, 2 => 0.88, 3 => 0.95, 4 => 1.00, 5 => 1.05, 6 => 1.05,
            7 => 0.95, 8 => 0.85, 9 => 1.00, 10 => 1.15, 11 => 1.00, 12 => 1.05,
        ],
    ],

    'terrace_usable_days' => [
        'source' => 'PLACEHOLDER: AEMET climate normals',
        'days' => [
            1 => 8, 2 => 10, 3 => 16, 4 => 20, 5 => 25, 6 => 27,
            7 => 26, 8 => 26, 9 => 25, 10 => 20, 11 => 12, 12 => 8,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Costs (used by the engine)
    |--------------------------------------------------------------------------
    */

    'cogs' => [
        'source' => 'PLACEHOLDER: hospitality benchmarks (28–35%)',
        'share_of_revenue' => ['budget' => 0.28, 'standard' => 0.31, 'premium' => 0.35],
    ],

    'iva' => [
        'source' => 'PLACEHOLDER: AEAT, hostelería rate',
        'rate' => 0.10,
    ],

    'staff' => [
        'source' => 'PLACEHOLDER: SMI / hostelería collective agreement for Zaragoza',
        // Full-time gross per payment; there are 14 payments a year, so the
        // monthly cost is gross × payments_per_year / 12.
        'gross_per_payment_cents' => ['min' => 118_400, 'max' => 150_000],
        'payments_per_year' => 14,
        'employer_social_security_rate' => 0.315,
        'full_time_hours_per_week' => 40,
    ],

    // Monthly cuota by the owner's net monthly income (upper bound, cents).
    'cuota_autonomo' => [
        'source' => 'PLACEHOLDER: Seguridad Social tables',
        'bands' => [
            ['max_income_cents' => 67_000, 'cuota_cents' => 20_000],
            ['max_income_cents' => 130_000, 'cuota_cents' => 29_400],
            ['max_income_cents' => 170_000, 'cuota_cents' => 31_000],
            ['max_income_cents' => 270_000, 'cuota_cents' => 35_000],
            ['max_income_cents' => 400_000, 'cuota_cents' => 43_000],
            ['max_income_cents' => null, 'cuota_cents' => 53_000],
        ],
    ],

    'utilities' => [
        'source' => 'PLACEHOLDER: supplier estimates',
        'month_cents' => ['min' => 25_000, 'max' => 50_000],
    ],

    'insurance' => [
        'source' => 'PLACEHOLDER: quotes',
        'month_cents' => ['min' => 3_000, 'max' => 6_000],
    ],

    'terrace_fee' => [
        'source' => 'PLACEHOLDER: Zaragoza ordenanza fiscal (tasa de veladores)',
        'per_table_per_year_cents' => 10_000,
    ],

    /*
    |--------------------------------------------------------------------------
    | Fictional names
    |--------------------------------------------------------------------------
    |
    | "<prefix> <name>", e.g. "Cafetería El Cierzo". Not an economic value.
    |
    */

    'names' => [
        'source' => 'game content',
        'prefixes' => [
            'cafe' => ['Café', 'Cafetería'],
            'cafe_bar' => ['Bar', 'Café-Bar', 'Taberna'],
        ],
        'names' => [
            'El Cierzo', 'La Esquina', 'Los Arcos', 'La Plazuela', 'El Tranvía', 'El Rincón',
            'La Parada', 'El Patio', 'La Glorieta', 'El Olivo', 'Azahar', 'Aurora',
            'Mercurio', 'La Luna', 'El Sol', 'Bambú', 'La Brújula', 'El Faro',
            'La Taza', 'El Molinillo', 'La Cafetera', 'El Grano', 'Arábica', 'La Tertulia',
            'El Mostrador', 'La Barra', 'El Ancla', 'La Veleta', 'El Reloj', 'La Ventana',
            'El Balcón', 'La Terraza', 'El Puente', 'La Ribera', 'El Cruce', 'La Fuente',
            'El Paseo', 'La Avenida', 'El Mirador', 'La Torre', 'El Jardín', 'La Huerta',
            'El Almendro', 'La Encina', 'El Naranjo', 'La Higuera', 'El Romero', 'La Lavanda',
            'El Tomillo', 'La Canela', 'El Cacao', 'La Vainilla', 'El Azúcar', 'La Miel',
            'El Croissant', 'La Tostada', 'El Churro', 'La Merienda', 'El Vermut', 'La Caña',
            'El Encuentro', 'La Pausa', 'El Momento', 'La Costumbre', 'El Recreo', 'La Charla',
            'Nube', 'Brisa', 'Lucero', 'Estrella', 'Cometa', 'Galaxia',
            'Hoja', 'Trébol', 'Amapola', 'Girasol', 'Margarita', 'Violeta',
        ],
    ],

];
