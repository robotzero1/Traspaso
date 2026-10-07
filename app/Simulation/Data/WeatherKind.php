<?php

namespace App\Simulation\Data;

/** The day's weather, as far as trade is concerned. */
enum WeatherKind: string
{
    case Fair = 'fair';
    /** At least 1 mm of rain: the terrace closes and fewer people are out. */
    case Rain = 'rain';
    /** 35 °C or more: the afternoon empties and the evening fills up. */
    case Hot = 'hot';
}
