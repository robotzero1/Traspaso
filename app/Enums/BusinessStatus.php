<?php

namespace App\Enums;

enum BusinessStatus: string
{
    case ForSale = 'for_sale';
    case OwnedByPlayer = 'owned_by_player';
    case Competitor = 'competitor';
    // Bought by someone else (or the player's own café, sold on): no longer
    // for sale, still trading, so it can be a rival.
    case Taken = 'taken';
}
