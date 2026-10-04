<?php

namespace App\Simulation\Data;

/**
 * The trading periods a business can choose to open for. Each one draws a
 * different crowd (offices at breakfast and lunch, students in the
 * afternoon, nightlife late), so which ones a business opens for matters
 * as much as how many hours it opens. Hour spans and demand weights live
 * in config/market, not here.
 */
enum DayPart: string
{
    /** Desayuno and almuerzo. */
    case Morning = 'morning';
    /** Menú del día, vermut. */
    case Lunch = 'lunch';
    /** Merienda, coffee after lunch. */
    case Afternoon = 'afternoon';
    /** Cañas, tapas, cena. */
    case Evening = 'evening';
    /** Copas after midnight. */
    case Night = 'night';
}
