<?php

namespace App\Simulation\Data;

enum QualityTier: string
{
    case Budget = 'budget';
    case Standard = 'standard';
    case Premium = 'premium';
}
