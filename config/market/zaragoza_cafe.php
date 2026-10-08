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
    | Game setup
    |--------------------------------------------------------------------------
    */

    'game' => [
        'source' => 'game design (SPEC §1–2)',
        'starting_capital_cents' => ['min' => 2_000_000, 'max' => 10_000_000],
        // Months a game lasts; null = no fixed end (stage two, SPEC §11):
        // the café trades until it goes bankrupt.
        'months' => null,
    ],

    // On top of the traspaso, the landlord holds a deposit (fianza), paid
    // back when the business is sold. It counts towards net worth.
    'purchase' => [
        'source' => 'PLACEHOLDER: typical Zaragoza commercial leases',
        'deposit_months_of_rent' => 2,
    ],

    // The business as the player finds it on the first day.
    // What the owner takes out of the business each month to live on. It
    // isn't a business cost (profit is before it, and taxes are on profit),
    // but it leaves the cash every month, so a café that can't pay its
    // owner runs down their savings. Goodwill is valued on profit after it.
    'owner' => [
        'source' => 'Game design: about the minimum wage after tax (user, 2026)',
        'pay_month_cents' => 120_000,
    ],

    // The viability check (SPEC §11): a real café or bar this close to the
    // pin is taken to be the café being checked, not a rival.
    'viability' => [
        'source' => 'game design',
        'own_place_metres' => 15,
    ],

    'takeover' => [
        'source' => 'PLACEHOLDER: game design',
        // Owner plus one employee (balancing pass: two cost more than a
        // typical café brings in).
        'staff_count' => 1,
        'staff_morale' => 70,
        // equipment health = base + per_condition × condition (1–10)
        'equipment_health' => ['base' => 40, 'per_condition' => 5],
        'stock_quality' => 55,
    ],

    'default_decisions' => [
        'source' => 'game design',
        'price_level' => 1.0,
        'open_day_parts' => ['morning', 'lunch', 'afternoon'],
        'open_days_per_week' => 6,
        'staff_count' => 1,
        'marketing_spend_cents' => 10_000,
        'quality_tier' => 'standard',
    ],

    // The range the player can set each decision within.
    'decision_limits' => [
        'source' => 'game design',
        'price_level' => ['min' => 0.7, 'max' => 1.6],
        'staff_count' => ['min' => 0, 'max' => 8],
        'marketing_spend_cents' => ['min' => 0, 'max' => 300_000],
    ],

    // What the business is worth to a buyer: what it was bought for is
    // part location and licence (which stay), part fixtures (which wear
    // with the equipment); on top comes goodwill from recent profits and
    // reputation.
    //   value = traspaso paid × (location_share + fixtures_share
    //           × (equipment_base + (1 − equipment_base) × health / 100))
    //         + profit_multiple_years × max(0, average monthly profit over
    //           the last profit_months × 12) × (reputation_base
    //           + reputation_per_point × reputation)
    'valuation' => [
        // Milestone 17: fitted so a café run as a typical owner runs it
        // (market:balance default strategy) sells after a year for 85–100%
        // of its traspaso, the listings being typical owners' asking prices
        // and buyers agreeing a little below asking (guess: 0–15%). A café
        // that doesn't pay its owner sells for the premises alone (location,
        // licence, fit-out): ~0.55–0.7 of the traspaso, a guess. A typical
        // traspaso is about 1.2 years of profit after the owner's pay (the
        // Spanish rule of thumb is 1–2 years), so goodwill of 0.4 years makes
        // about a third of it. To check against agreed prices if found.
        'source' => 'Fitted to the Zaragoza listing tiers (Oct 2026) via market:balance; shares and discount are guesses',
        'location_share' => 0.4,
        'fixtures_share' => 0.3,
        'equipment_base' => 0.5,
        'profit_months' => 12,
        // 0.75 → 0.5 (balancing pass) → 0.4 (milestone 17), with
        // location_share 0.5 → 0.4.
        'profit_multiple_years' => 0.4,
        'reputation_base' => 0.8,
        'reputation_per_point' => 0.004,
        'rounding_cents' => 50_000,
    ],

    // Selling the café (SPEC §12, milestone 18). Buyers arrive at random;
    // each values the café with BusinessValuation, give or take, and opens
    // below that. Nothing here is from data yet: all of it is a GUESS to
    // replace with aggregated figures on Zaragoza traspasos (time listed,
    // asking vs agreed price, agency fees).
    'sale' => [
        'source' => 'PLACEHOLDER: guesses; see docs/balance-report.md',
        // Serious buyers a month for a café asked at its value. Private:
        // portal listing only; agency: its buyer list and marketing too.
        'buyers_per_month' => ['private' => 0.4, 'agency' => 1.0],
        // Interest falls as the asking price rises above the value:
        // × exp(−sensitivity × (asking ÷ value − 1)), capped at max_interest.
        'price_sensitivity' => 3.0,
        'max_interest' => 1.5,
        // Fewer buyers in August and over Christmas.
        'season' => [8 => 0.3, 12 => 0.6, 1 => 0.8],
        // What one buyer would pay at most, around the valuation.
        'buyer_value_sd' => 0.15,
        // A buyer opens this far below their limit (never above asking)...
        'opening_discount' => 0.1,
        // ...and doesn't bother if their limit is under this share of asking.
        'min_offer_share' => 0.6,
        'offer_days' => 5,
        // From an accepted offer to completion (gestoría, the landlord's
        // paperwork); completion then falls on that month's last day.
        'handover_days' => 30,
        // Agency commission: a share of the price, with a minimum.
        'agency_commission_share' => 0.08,
        'agency_commission_min_cents' => 300_000,
        'gestoria_cents' => 80_000,
        // IRPF savings scale (LIRPF art. 76, from 2025): the gain (price less
        // the traspaso paid and the costs of the sale) is taxed here.
        // Simplified: amortisation of the traspaso isn't deducted from its cost.
        'tax_brackets' => [
            ['up_to_cents' => 600_000, 'rate' => 0.19],
            ['up_to_cents' => 5_000_000, 'rate' => 0.21],
            ['up_to_cents' => 20_000_000, 'rate' => 0.23],
            ['up_to_cents' => 30_000_000, 'rate' => 0.27],
            ['up_to_cents' => null, 'rate' => 0.30],
        ],
    ],

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

    // From Zaragoza café/bar traspaso listings (Oct 2026): compact coffee
    // shops of 25–50 m² up to flagship cafés of ~200 m².
    'floor_area_m2' => [
        'source' => 'Zaragoza café/bar listings sample, Oct 2026 (aggregated; no listings stored)',
        'percentiles' => [0 => 25, 10 => 35, 25 => 45, 50 => 60, 75 => 90, 90 => 130, 100 => 220],
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

    // Zaragoza café/bar listings (Oct 2026): neighbourhood spots €400–700 a
    // month, established cafés in active districts €800–1,800, prime
    // streets €2,500 and up (flagships far more; capped here).
    'rent' => [
        'source' => 'Zaragoza café/bar listings sample and market tiers, Oct 2026 (aggregated; no listings stored)',
        'percentiles_cents' => [
            0 => 35_000,
            10 => 45_000,
            25 => 60_000,
            50 => 90_000,
            75 => 150_000,
            90 => 250_000,
            100 => 800_000,
        ],
        'rounding_cents' => 2_500,
        // Balancing pass: busy spots must cost more to rent, or the busiest
        // street wins every game (0.5 left footfall-9 spots at ~€1,000).
        'correlation' => ['location' => 0.85, 'floor_area' => 0.4],
    ],

    // Zaragoza café/bar listings (Oct 2026): €10–30k for small or
    // neighbourhood places, €40–80k for established cafés in active
    // districts, €90–250k+ on prime streets.
    'traspaso' => [
        'source' => 'Zaragoza café/bar listings sample and market tiers, Oct 2026 (aggregated; no listings stored)',
        'percentiles_cents' => [
            0 => 600_000,
            10 => 1_200_000,
            25 => 2_000_000,
            50 => 4_000_000,
            75 => 7_000_000,
            90 => 11_000_000,
            100 => 25_000_000,
        ],
        'rounding_cents' => 50_000,
        // The asking price follows the location, then condition and size;
        // a kitchen with a smoke outlet (salida de humos, hard to get in a
        // residential building) and a terrace permit add to it.
        'correlation' => ['location' => 0.85, 'condition' => 0.25, 'floor_area' => 0.2, 'kitchen' => 0.2, 'terrace' => 0.15],
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
        // stop_factor: how readily passers-by stop in (1.0 = typical). A
        // quick coffee at the bar is an easy stop, so mornings and
        // afternoons bring more customers who each spend less.
        // demand_mix: how much each driver contributes to that day part.
        'morning' => [
            // Many drink their coffee standing at the bar: quick turnover.
            'intensity' => 1.2, 'turnover_per_seat_hour' => 2.5, 'stop_factor' => 1.8,
            'start_hour' => 7, 'end_hour' => 12,
            'demand_mix' => ['office' => 0.35, 'transport' => 0.30, 'student' => 0.20, 'population' => 0.15],
        ],
        'lunch' => [
            'intensity' => 1.0, 'turnover_per_seat_hour' => 1.0, 'stop_factor' => 1.0,
            'start_hour' => 12, 'end_hour' => 16,
            'demand_mix' => ['office' => 0.40, 'tourist' => 0.25, 'population' => 0.20, 'transport' => 0.15],
        ],
        'afternoon' => [
            'intensity' => 0.8, 'turnover_per_seat_hour' => 1.0, 'stop_factor' => 1.55,
            'start_hour' => 16, 'end_hour' => 20,
            'demand_mix' => ['student' => 0.35, 'population' => 0.35, 'tourist' => 0.15, 'transport' => 0.15],
        ],
        'evening' => [
            'intensity' => 0.9, 'turnover_per_seat_hour' => 0.8, 'stop_factor' => 1.0,
            'start_hour' => 20, 'end_hour' => 24,
            'demand_mix' => ['population' => 0.40, 'tourist' => 0.35, 'student' => 0.25],
        ],
        'night' => [
            'intensity' => 0.6, 'turnover_per_seat_hour' => 0.6, 'stop_factor' => 1.0,
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

    // Average spend per customer, including IVA, before price level: the
    // mix of what people order, not the price of a full meal. Many just
    // have a coffee at the bar (a café con leche is about €1.50 in
    // Zaragoza), others add a tostada or a pastry (about €3–3.50).
    'average_ticket_cents' => [
        'source' => 'PLACEHOLDER: own observation / menus (morning and afternoon revised: most orders are a coffee)',
        'morning' => ['min' => 180, 'max' => 300],
        'lunch' => ['min' => 450, 'max' => 1_000],
        'afternoon' => ['min' => 200, 'max' => 350],
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
    | Daily trading (stage two, SPEC §11)
    |--------------------------------------------------------------------------
    |
    | The daily engine spreads each month's demand over its days. The
    | weights below only say how a month's trade is shared between its
    | days: each day part's weights are divided by their average over the
    | month, so a café open every day sells what the monthly engine says
    | (seasonality already holds the month's typical weather and fiestas).
    |
    */

    // Relative trade by weekday, per day part. Public holidays trade like
    // a Sunday. A café open fewer than 7 days closes its quietest days for
    // the day parts it opens (the monthly engine counts this too).
    'day_of_week' => [
        'source' => 'PLACEHOLDER: a guess shaped on Spanish card spending by weekday (Friday–Saturday peak, Monday the low, Sunday strong at lunch); verify per day part against card-spend data (e.g. CaixaBank Research) or own counts',
        'weights' => [
            'morning' => ['mon' => 1.00, 'tue' => 1.00, 'wed' => 1.00, 'thu' => 1.00, 'fri' => 1.05, 'sat' => 1.05, 'sun' => 0.90],
            'lunch' => ['mon' => 0.85, 'tue' => 0.90, 'wed' => 0.95, 'thu' => 1.00, 'fri' => 1.15, 'sat' => 1.20, 'sun' => 1.10],
            'afternoon' => ['mon' => 0.90, 'tue' => 0.90, 'wed' => 0.95, 'thu' => 1.00, 'fri' => 1.10, 'sat' => 1.15, 'sun' => 1.00],
            'evening' => ['mon' => 0.70, 'tue' => 0.75, 'wed' => 0.85, 'thu' => 1.00, 'fri' => 1.35, 'sat' => 1.45, 'sun' => 0.85],
            'night' => ['mon' => 0.40, 'tue' => 0.40, 'wed' => 0.50, 'thu' => 0.90, 'fri' => 1.70, 'sat' => 2.00, 'sun' => 0.60],
        ],
    ],

    // Public holidays in Zaragoza (national, Aragón and local), as
    // month-day. Holy Thursday and Good Friday move with Easter and are
    // worked out from the date.
    'holidays' => [
        'source' => 'BOE national and BOA Aragón holiday calendars; Zaragoza local holidays (San Valero, Cincomarzada)',
        'fixed' => [
            '01-01', '01-06',
            '01-29', // San Valero (local)
            '03-05', // Cincomarzada (local)
            '04-23', // San Jorge, Día de Aragón
            '05-01', '08-15', '10-12', '11-01', '12-06', '12-08', '12-25',
        ],
        'easter' => ['holy_thursday', 'good_friday'],
    ],

    // The Fiestas del Pilar: nine days ending on the first Sunday on or
    // after 12 October (2023: 7–15, 2024: 5–13, 2025: 4–12 October).
    // October's seasonality (1.15) is mostly the Pilar; these weights put
    // that trade in the fiesta days, leaving the rest of October close to
    // an ordinary month.
    'pilar' => [
        'source' => 'PLACEHOLDER: weights a guess (dates from the Ayuntamiento de Zaragoza programmes 2023–2025); verify against card spending in Pilar week',
        'anchor_month_day' => '10-12',
        'days' => 9,
        'weights' => ['morning' => 1.3, 'lunch' => 1.5, 'afternoon' => 1.5, 'evening' => 1.8, 'night' => 2.0],
    ],

    // Day-to-day weather. Rain days are AEMET's mean number of days with
    // ≥ 1 mm at Zaragoza Aeropuerto (normals 1981–2010). Hot days (35 °C
    // or more) are an estimate from recent summers. Rain closes the
    // terrace; on dry days the terrace opens often enough to give
    // terrace_usable_days over the month. Effects multiply demand and are
    // spread so that the month's average stays 1.
    'weather' => [
        'source' => 'PLACEHOLDER: hot days and effects are guesses; rain days are AEMET normals 1981–2010, Zaragoza Aeropuerto (days ≥ 1 mm, read from a search summary: check on aemet.es)',
        'rain_days' => [
            1 => 4.0, 2 => 3.9, 3 => 3.7, 4 => 5.7, 5 => 6.4, 6 => 4.0,
            7 => 2.6, 8 => 2.3, 9 => 3.2, 10 => 5.4, 11 => 5.1, 12 => 4.8,
        ],
        'hot_days' => [6 => 2, 7 => 8, 8 => 6, 9 => 1],
        'effects' => [
            // Fewer people out; some step in to shelter.
            'rain' => ['morning' => 0.95, 'lunch' => 0.9, 'afternoon' => 0.85, 'evening' => 0.85, 'night' => 0.85],
            // Nobody goes out in the afternoon heat; evenings fill up.
            'hot' => ['morning' => 1.0, 'lunch' => 0.95, 'afternoon' => 0.75, 'evening' => 1.15, 'night' => 1.1],
        ],
    ],

    'daily' => [
        'source' => 'PLACEHOLDER: a guess (a café serving 100–300 people a day varies by roughly 10% from chance alone)',
        // Day-to-day randomness in demand on top of the month's: normal(1, noise_sd), clamped.
        'noise_sd' => 0.10,
        'noise_min' => 0.6,
        'noise_max' => 1.4,
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
        // How a spot's custom drifts over the years (offices, shops and
        // residents come and go): a lasting random walk, sd_per_year a year.
        // Calibrated (market:balance --years=5) so 45–50% of typical new
        // cafés are still open after 5 years (INE/DIRCE).
        'local_trend' => ['sd_per_year' => 0.245, 'min' => 0.4, 'max' => 1.6],
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
        // Calibrated (market:balance) so that, once the owner takes their
        // pay, 20–25% of typical new cafés fail in year 1, as INE/DIRCE and
        // Hostelería de España report for new cafés and bars.
        'base_rate' => 0.091,
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
        // Days the player has to answer an event before its default choice
        // is taken. An event can set its own deadline_days.
        'deadline_days' => 3,

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
        // Picking rivals when the player buys: the nearest other
        // businesses within distance_metres.max, up to nearby_count, at
        // their distance on the map (never closer than the min). Quality
        // comes from their condition (1–10).
        'nearby_count' => 5,
        'distance_metres' => ['min' => 40, 'max' => 500],
        'price_level' => ['min' => 0.9, 'max' => 1.1],
        'quality' => ['base' => 25, 'per_condition' => 6],
        // Too few listings nearby (common away from the centre): real cafés
        // and bars from OpenStreetMap within distance_metres.max make up
        // the numbers. Only their position is real; the name is fictional
        // and these are drawn.
        'unlisted' => [
            'poi_types' => ['cafe', 'nightlife'],
            'seats' => ['min' => 15, 'max' => 60],
            'condition' => ['min' => 3, 'max' => 8],
            'reputation' => ['min' => 35.0, 'max' => 65.0],
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
        // Full-time gross per payment (between SMI, €1,184, and ~€1,500);
        // there are 14 payments a year, so the monthly cost is
        // gross × payments_per_year / 12, plus employer social security.
        'gross_per_payment_cents' => 130_000,
        'payments_per_year' => 14,
        'employer_social_security_rate' => 0.315,
        'full_time_hours_per_week' => 40,
        // People needed on the floor every open hour. Hours the owner
        // (service.owner_hours_per_week) and staff don't cover are paid as
        // part-time cover at the same hourly cost, so a long opening day
        // costs wages (SPEC §6: a quiet day part shouldn't pay its way).
        'min_on_shift' => 1.0,
        // Days before a staff change takes effect. Hiring: finding and
        // signing someone up, about a week (a guess). Letting someone go:
        // 15 days' notice (Estatuto de los Trabajadores art. 49.1.c and
        // 53.1.c for temporary contracts over a year and objective
        // dismissals).
        'hire_lead_days' => 7,
        'notice_days' => 15,
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
