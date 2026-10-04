<?php

namespace App\Simulation\Data;

/**
 * What a lasting event effect changes while it's active.
 */
enum ModifierEffect: string
{
    /** Multiplies potential customers. */
    case Demand = 'demand';
    /** Multiplies capacity (seats and service). */
    case Capacity = 'capacity';
    /** Points off the quality served. */
    case QualityPenalty = 'quality_penalty';
    /** Added to the COGS share of revenue. */
    case CogsShare = 'cogs_share';
    /** Multiplies the rent. */
    case Rent = 'rent';
    /** Staff missing from the floor (still paid). */
    case StaffShortage = 'staff_shortage';
    /** An extra cost every month, in cents. */
    case MonthlyCost = 'monthly_cost';
}
