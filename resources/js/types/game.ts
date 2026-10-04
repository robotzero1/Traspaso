export type GamePhase = 'browsing' | 'playing' | 'ending' | 'over';

export type GameSummary = {
    id: number;
    status: 'active' | 'bankrupt' | 'finished';
    phase: GamePhase;
    current_month: number;
    months: number;
    calendar_month: number | null;
    start_date: string;
    starting_capital_cents: number;
    cash_cents: number;
    deposit_cents: number;
    net_worth_cents: number;
    sold_for_cents: number | null;
    final_net_worth_cents: number | null;
    business_name: string | null;
};

export type BusinessForSale = {
    id: number;
    fictional_name: string;
    neighbourhood: string;
    street_type: string;
    category: 'cafe' | 'cafe_bar';
    floor_area_m2: number;
    indoor_seats: number;
    terrace_seats: number;
    rent_month_cents: number;
    traspaso_cents: number;
    deposit_cents: number;
    licence: string;
    kitchen: string;
    condition: number;
    equipment_age_years: number;
    footfall: number;
    base_reputation: number;
};

export type DayPartValue =
    | 'morning'
    | 'lunch'
    | 'afternoon'
    | 'evening'
    | 'night';

export type Decisions = {
    price_level: number;
    open_day_parts: DayPartValue[];
    open_days_per_week: number;
    staff_count: number;
    marketing_spend_cents: number;
    quality_tier: 'budget' | 'standard' | 'premium';
    event_choices: Record<string, string>;
};

export type Range = { min: number; max: number };

export type DecisionLimits = {
    price_level: Range;
    staff_count: Range;
    marketing_spend_cents: Range;
};

export type BusinessStateProps = {
    reputation: number;
    staff_count: number;
    staff_morale: number;
    equipment_health: number;
    equipment_age_months: number;
    stock_quality: number;
    modifiers: {
        source: string;
        effect: string;
        value: number;
        months_remaining: number | null;
        day_parts: DayPartValue[];
    }[];
};

export type DayPartResult = {
    day_part: DayPartValue;
    potential_customers: number;
    demand: number;
    capacity: number;
    covers: number;
    revenue_cents: number;
};

export type MonthResultRow = {
    month: number;
    calendar_month: number;
    customers: number;
    revenue_cents: number;
    event_revenue_cents: number;
    cogs_cents: number;
    staff_cents: number;
    rent_cents: number;
    utilities_cents: number;
    marketing_cents: number;
    other_cents: number;
    taxes_cents: number;
    profit_cents: number;
    cash_after_cents: number;
    day_parts: DayPartResult[];
};

export type EventEffects = {
    cost_cents?: number;
    revenue_cents?: number;
    reputation?: number;
    morale?: number;
    equipment_health?: number;
    modifiers?: {
        effect: string;
        value: number;
        months: number | null;
        day_parts?: DayPartValue[];
    }[];
    add_competitor?: boolean;
    remove_competitor?: string;
};

export type PendingEvent = {
    key: string;
    type: string;
    month: number;
    choices: string[];
    payload: {
        effects?: EventEffects;
        outcome?: string;
        choice_effects?: Record<string, EventEffects>;
        default_choice?: string;
    };
};

export type GameEventRow = {
    month: number;
    type: string;
    payload: PendingEvent['payload'];
    choices: string[];
    choice: string | null;
    resolved_month: number | null;
};

export type Competitor = {
    key: string;
    name: string;
    distance_metres: number;
    price_level: number;
    quality: number;
    reputation: number;
    seats: number;
};

export type CostHints = {
    staff_per_person_cents: number;
    rent_cents: number;
    utilities_base_cents: number;
    utilities_per_open_hour_cents: number;
    cogs_share: Record<'budget' | 'standard' | 'premium', number>;
};
