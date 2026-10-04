<?php

namespace App\Enums;

enum GameStatus: string
{
    case Active = 'active';
    case Bankrupt = 'bankrupt';
    case Finished = 'finished';
}
