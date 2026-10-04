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
        // intensity: how busy the street is in that day part (1.0 = typical).
        // turnover_per_seat_hour: covers a seat can serve per hour.
        // demand_mix: how much each driver contributes to that day part.
        'morning' => [
            'intensity' => 1.2, 'turnover_per_seat_hour' => 1.5,
            'start_hour' => 7, 'end_hour' => 12,
            'demand_mix' => ['office' => 0.35, 'transport' => 0.30, 'student' => 0.20, 'population' => 0.15],
        ],
        'lunch' => [
            'intensity' => 1.0, 'turnover_per_seat_hour' => 1.0,
            'start_hour' => 12, 'end_hour' => 16,
            'demand_mix' => ['office' => 0.40, 'tourist' => 0.25, 'population' => 0.20, 'transport' => 0.15],
        ],
        'afternoon' => [
            'intensity' => 0.8, 'turnover_per_seat_hour' => 1.0,
            'start_hour' => 16, 'end_hour' => 20,
            'demand_mix' => ['student' => 0.35, 'population' => 0.35, 'tourist' => 0.15, 'transport' => 0.15],
        ],
        'evening' => [
            'intensity' => 0.9, 'turnover_per_seat_hour' => 0.8,
            'start_hour' => 20, 'end_hour' => 24,
            'demand_mix' => ['population' => 0.40, 'tourist' => 0.35, 'student' => 0.25],
        ],
        'night' => [
            'intensity' => 0.6, 'turnover_per_seat_hour' => 0.6,
            'start_hour' => 24, 'end_hour' => 27,
            'demand_mix' => ['tourist' => 0.50, 'student' => 0.50],
        ],
    ],

    // How much each day part suits each kind of business, as a multiplier
    // on potential customers. Kitchens matter at lunch and in the evening.
    'appeal' => [
        'source' => 'PLACEHOLDER: game design / own observation',
        'category' => [
            'cafe' => ['morning' => 1.0, 'lunch' => 0.9, 'afternoon' => 1.0, 'evening' => 0.6, 'night' => 0.3],
            'cafe_bar' => ['morning' => 0.8, 'lunch' => 1.0, 'afternoon' => 0.9, 'evening' => 1.0, 'night' => 1.0],
        ],
        'kitchen' => [
            'lunch' => ['none' => 0.6, 'basic' => 1.0, 'full' => 1.3],
            'evening' => ['none' => 0.8, 'basic' => 1.0, 'full' => 1.15],
        ],
    ],

    'licence_day_parts' => [
        'source' => 'PLACEHOLDER: Zaragoza licensing ordinance (opening-hours rules)',
        'cafe' => ['morning', 'lunch', 'afternoon', 'evening'],
        'cafe_bar' => ['morning', 'lunch', 'afternoon', 'evening', 'night'],
        'bar_musical' => ['morning', 'lunch', 'afternoon', 'evening', 'night'],
    ],

    // Where in each day part's ticket range a business sits: 0 = min,
    // 1 = max. Set by quality tier, shifted by kitchen at lunch and evening.
    'ticket_position' => [
        'source' => 'PLACEHOLDER: own observation / menus',
        'tier' => ['budget' => 0.25, 'standard' => 0.5, 'premium' => 0.75],
        'kitchen_offset' => ['none' => -0.2, 'basic' => 0.0, 'full' => 0.2],
        'kitchen_day_parts' => ['lunch', 'evening'],
    ],

    // Average spend per customer, including IVA, before price level.
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
    | Demand (used by the engine)
    |--------------------------------------------------------------------------
    |
    | potential per day part = potential_per_hour_at_footfall_10
    |     × (footfall / 10) ^ footfall_exponent
    |     × day part intensity × Σ demand_mix × driver × appeal
    |     × hours × open days × seasonality × noise
    |
    | Index drivers are index / index_reference; the population driver is
    | population / population_reference, capped.
    |
    */

    'demand' => [
        'source' => 'PLACEHOLDER: to calibrate against manual pedestrian counts',
        'potential_per_hour_at_footfall_10' => 400,
        'footfall_exponent' => 0.8,
        'index_reference' => 5.0,
        'population_reference' => 60_000,
        'population_driver_cap' => 2.0,
        // Month-to-month randomness in demand: normal(1, noise_sd), clamped.
        'noise_sd' => 0.05,
        'noise_min' => 0.8,
        'noise_max' => 1.2,
    ],

    // capture = base_rate × own attractiveness × condition × marketing
    //         / (1 + weight × Σ competitor attractiveness × decay
    //              + density_weight × competition density / density_reference)
    // The listed competitors are the nearby rivals the game models; the
    // neighbourhood's density stands for all the others.
    // attractiveness = reputation factor × price_level ^ −price_elasticity
    //                × quality factor
    'capture' => [
        'source' => 'PLACEHOLDER: game design, to tune in the balancing pass',
        'base_rate' => 0.044,
        'price_elasticity' => 0.7,
        'reputation' => ['base' => 0.3, 'per_point' => 0.014],
        'quality' => ['base' => 0.75, 'per_point' => 0.005],
        'condition' => ['base' => 0.9, 'per_point' => 0.02],
        // boost = max_boost × (1 − e^(−spend / scale_cents))
        'marketing' => ['max_boost' => 0.25, 'scale_cents' => 30_000],
        'competition' => [
            'weight' => 0.4,
            'distance_decay_metres' => 200,
            'density_weight' => 0.5,
            'density_reference' => 50,
        ],
    ],

    // Quality 0–100 served to customers: the tier's score, cut by worn
    // equipment. Below the threshold, equipment health scales quality down
    // linearly to min_factor at 0 health.
    'quality' => [
        'source' => 'PLACEHOLDER: game design',
        'tier_scores' => ['budget' => 35, 'standard' => 55, 'premium' => 80],
        'equipment_threshold' => 50,
        'equipment_min_factor' => 0.6,
    ],

    'service' => [
        'source' => 'PLACEHOLDER: own observation',
        'customers_per_person_hour' => 18,
        // The owner works in the business on top of the staff.
        'owner_hours_per_week' => 50,
        // Above this share of service capacity, service and morale suffer.
        'comfortable_utilisation' => 0.8,
        // service score = morale_weight × morale + base
        //               − overload_penalty × (utilisation − comfortable)
        'morale_weight' => 0.5,
        'base' => 15,
        'overload_penalty' => 100,
    ],

    /*
    |--------------------------------------------------------------------------
    | State evolution (used by the engine)
    |--------------------------------------------------------------------------
    |
    | Each month a score moves adjustment_rate of the way to its target.
    |
    */

    'reputation' => [
        'source' => 'PLACEHOLDER: game design, to tune in the balancing pass',
        // target = base + quality_weight × (quality − 50)
        //        − price_premium_penalty × (price level − 1), when above 1
        //        + price_discount_bonus × (1 − price level), when below 1
        //        + service_weight × (service − 50)
        'target_base' => 50,
        'quality_weight' => 0.6,
        'price_premium_penalty' => 80,
        'price_discount_bonus' => 15,
        'service_weight' => 0.3,
        'adjustment_rate' => 0.3,
    ],

    'morale' => [
        'source' => 'PLACEHOLDER: game design',
        // target = base − overwork_penalty × (utilisation − comfortable)
        'base' => 70,
        'overwork_penalty' => 100,
        'adjustment_rate' => 0.3,
    ],

    'equipment' => [
        'source' => 'PLACEHOLDER: game design',
        // Health lost per month: wear_per_month + wear_per_age_year × age.
        'wear_per_month' => 1.0,
        'wear_per_age_year' => 0.1,
    ],

    /*
    |--------------------------------------------------------------------------
    | Random events (engine step 7)
    |--------------------------------------------------------------------------
    |
    | Each month every eligible event rolls its probability; at most
    | max_per_month happen (in a random order).
    |
    | probability / outcome weights: base + Σ factor × signal, clamped 0–1.
    | Signals, each 0–1 unless noted:
    |   equipment_wear (1 − health/100), low_morale, low_quality,
    |   low_reputation, reputation, overwork (utilisation above comfortable),
    |   staff_count (people), equipment_age_years (years).
    |
    | requires: months (calendar months it can happen in), open_any (day
    |   parts, at least one open), terrace, min_staff, has_competitors.
    |
    | effects (immediate, in the month the event happens; modifiers start
    | next month): cost_cents, revenue_cents, reputation, morale,
    | equipment_health, modifiers, add_competitor, remove_competitor.
    |
    | choices: the player picks one before next month (default_choice if
    | they don't); its effects apply at the start of that month, and its
    | modifiers apply from that month. outcomes: one is drawn by weight.
    |
    | modifiers: effect (demand, capacity, quality_penalty, cogs_share,
    | rent, staff_shortage, monthly_cost), value, months (null =
    | permanent), day_parts (optional).
    |
    */

    'events' => [
        'source' => 'PLACEHOLDER: game design, to tune in the balancing pass',
        'max_per_month' => 2,

        'library' => [
            'equipment_failure' => [
                'probability' => ['base' => 0.02, 'equipment_wear' => 0.15],
                'effects' => ['equipment_health' => -15],
                'choices' => [
                    'repair' => ['cost_cents' => 140_000, 'equipment_health' => 40],
                    'limp_on' => ['modifiers' => [
                        ['effect' => 'capacity', 'value' => 0.75, 'months' => 2],
                        ['effect' => 'quality_penalty', 'value' => 5, 'months' => 2],
                    ]],
                ],
                'default_choice' => 'limp_on',
            ],

            'fridge_breakdown' => [
                'probability' => ['base' => 0.015, 'equipment_wear' => 0.05],
                'effects' => ['cost_cents' => 50_000, 'equipment_health' => -5],
            ],

            'water_leak' => [
                'probability' => ['base' => 0.02],
                'effects' => [
                    'cost_cents' => 80_000,
                    'modifiers' => [['effect' => 'capacity', 'value' => 0.9, 'months' => 1]],
                ],
            ],

            'burglary' => [
                'probability' => ['base' => 0.01],
                // The insurance excess.
                'effects' => ['cost_cents' => 100_000, 'morale' => -3],
            ],

            'health_inspection' => [
                'probability' => ['base' => 0.06],
                'outcomes' => [
                    'passed' => [
                        'weight' => ['base' => 1.0],
                        'effects' => ['reputation' => 1],
                    ],
                    'minor_fine' => [
                        'weight' => ['base' => 0.2, 'low_quality' => 0.5, 'equipment_wear' => 0.3],
                        'effects' => ['cost_cents' => 60_000, 'reputation' => -2],
                    ],
                    'serious_fine' => [
                        'weight' => ['base' => 0.02, 'low_quality' => 0.05, 'equipment_wear' => 0.1],
                        'effects' => [
                            'cost_cents' => 250_000,
                            'reputation' => -6,
                            'modifiers' => [['effect' => 'capacity', 'value' => 0.9, 'months' => 1]],
                        ],
                    ],
                ],
            ],

            'staff_quits' => [
                'requires' => ['min_staff' => 1],
                'probability' => ['base' => 0.0, 'staff_count' => 0.015, 'low_morale' => 0.15],
                'effects' => ['morale' => -5],
                'choices' => [
                    'recruit' => [
                        'cost_cents' => 30_000,
                        'modifiers' => [['effect' => 'staff_shortage', 'value' => 1, 'months' => 1]],
                    ],
                    'temp_agency' => ['cost_cents' => 90_000],
                ],
                'default_choice' => 'recruit',
            ],

            'supplier_price_rise' => [
                'probability' => ['base' => 0.04],
                'choices' => [
                    'accept' => ['modifiers' => [['effect' => 'cogs_share', 'value' => 0.02, 'months' => 6]]],
                    'switch_supplier' => ['modifiers' => [['effect' => 'quality_penalty', 'value' => 6, 'months' => 3]]],
                ],
                'default_choice' => 'accept',
            ],

            'rent_review' => [
                'probability' => ['base' => 0.015],
                'choices' => [
                    'accept' => ['modifiers' => [['effect' => 'rent', 'value' => 1.05, 'months' => null]]],
                    'negotiate' => [
                        'cost_cents' => 30_000,
                        'modifiers' => [['effect' => 'rent', 'value' => 1.02, 'months' => null]],
                    ],
                ],
                'default_choice' => 'accept',
            ],

            'bad_review' => [
                'probability' => ['base' => 0.01, 'low_quality' => 0.08, 'overwork' => 0.1],
                'effects' => ['reputation' => -6],
                'choices' => [
                    'reply_publicly' => ['reputation' => 3],
                    'ignore' => [],
                ],
                'default_choice' => 'ignore',
            ],

            'press_feature' => [
                'probability' => ['base' => 0.005, 'reputation' => 0.04],
                'effects' => [
                    'reputation' => 3,
                    'modifiers' => [['effect' => 'demand', 'value' => 1.15, 'months' => 2]],
                ],
            ],

            'catering_order' => [
                'probability' => ['base' => 0.02, 'reputation' => 0.03],
                'choices' => [
                    // Net of IVA; COGS is charged on it like any revenue.
                    'accept' => ['revenue_cents' => 120_000, 'cost_cents' => 25_000, 'morale' => -3],
                    'decline' => [],
                ],
                'default_choice' => 'decline',
            ],

            // Forecast for the coming weeks, so it lands in June–August.
            'heatwave' => [
                'requires' => ['months' => [5, 6, 7]],
                'probability' => ['base' => 0.3],
                'effects' => ['modifiers' => [
                    ['effect' => 'demand', 'value' => 0.85, 'months' => 1, 'day_parts' => ['morning', 'afternoon']],
                    ['effect' => 'demand', 'value' => 1.2, 'months' => 1, 'day_parts' => ['evening', 'night']],
                    // Air conditioning.
                    ['effect' => 'monthly_cost', 'value' => 12_000, 'months' => 1],
                ]],
            ],

            // A street festival next month. The Pilar (October) is in the
            // seasonality multipliers instead.
            'local_festival' => [
                'requires' => ['months' => [1, 2, 3, 4, 5, 6, 7, 8, 10, 11, 12]],
                'probability' => ['base' => 0.06],
                'choices' => [
                    'join_in' => [
                        'cost_cents' => 40_000,
                        'modifiers' => [['effect' => 'demand', 'value' => 1.35, 'months' => 1]],
                    ],
                    'business_as_usual' => [
                        'modifiers' => [['effect' => 'demand', 'value' => 1.1, 'months' => 1]],
                    ],
                ],
                'default_choice' => 'business_as_usual',
            ],

            'roadworks' => [
                'probability' => ['base' => 0.03],
                'effects' => ['modifiers' => [['effect' => 'demand', 'value' => 0.8, 'months' => 2]]],
            ],

            'new_offices_nearby' => [
                'probability' => ['base' => 0.015],
                'effects' => ['modifiers' => [
                    ['effect' => 'demand', 'value' => 1.12, 'months' => 6, 'day_parts' => ['morning', 'lunch']],
                ]],
            ],

            'noise_complaint' => [
                'requires' => ['open_any' => ['evening', 'night']],
                'probability' => ['base' => 0.08],
                'choices' => [
                    'soundproof' => ['cost_cents' => 150_000],
                    'pay_fine' => ['cost_cents' => 75_000, 'reputation' => -2],
                ],
                'default_choice' => 'pay_fine',
            ],

            'competitor_opens' => [
                'probability' => ['base' => 0.025],
                'effects' => ['add_competitor' => [
                    'distance_metres' => ['min' => 50, 'max' => 400],
                    'price_level' => ['min' => 0.9, 'max' => 1.1],
                    'quality' => ['min' => 40, 'max' => 75],
                    'reputation' => 45,
                    'seats' => ['min' => 20, 'max' => 50],
                ]],
            ],

            'competitor_closes' => [
                'requires' => ['has_competitors' => true],
                'probability' => ['base' => 0.015],
                // The least attractive nearby rival closes.
                'effects' => ['remove_competitor' => 'weakest'],
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Competitors (engine step 9)
    |--------------------------------------------------------------------------
    |
    | Rivals near the player follow the player's prices, drift in quality,
    | fight back with quality when the player is more attractive, and
    | their reputation moves towards a target like the player's does.
    |
    */

    'competitors' => [
        'source' => 'PLACEHOLDER: game design',
        'follow_radius_metres' => 300,
        'price_follow_rate' => 0.1,
        'price_noise_sd' => 0.01,
        'price_min' => 0.8,
        'price_max' => 1.3,
        'quality_noise_sd' => 1.5,
        // Quality points a month a rival adds while the player out-attracts it.
        'quality_response' => 1.0,
        'reputation_adjustment_rate' => 0.2,
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
        // Full-time gross per payment (between SMI, €1,184, and ~€1,500);
        // there are 14 payments a year, so the monthly cost is
        // gross × payments_per_year / 12, plus employer social security.
        'gross_per_payment_cents' => 130_000,
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
        // base + per hour open; aims at €250–500 a month.
        'base_month_cents' => 15_000,
        'per_open_hour_cents' => 50,
    ],

    'insurance' => [
        'source' => 'PLACEHOLDER: quotes',
        // Quotes range €30–60 a month.
        'month_cents' => 4_500,
    ],

    'maintenance' => [
        'source' => 'PLACEHOLDER: own estimate',
        'base_month_cents' => 5_000,
        'per_equipment_year_cents' => 500,
    ],

    // Pago fraccionado (modelo 130): a share of positive monthly profit.
    'income_tax' => [
        'source' => 'PLACEHOLDER: AEAT modelo 130',
        'rate' => 0.20,
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
