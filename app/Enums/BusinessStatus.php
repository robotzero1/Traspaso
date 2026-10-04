<?php

namespace App\Enums;

enum BusinessStatus: string
{
    case ForSale = 'for_sale';
    case OwnedByPlayer = 'owned_by_player';
    case Competitor = 'competitor';
}
