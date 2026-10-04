<?php

namespace App\Simulation\Exceptions;

use InvalidArgumentException;

/**
 * The decisions break a rule of the game, e.g. opening at night without a
 * licence that allows it. Callers should validate before simulating.
 */
final class DecisionNotAllowed extends InvalidArgumentException {}
