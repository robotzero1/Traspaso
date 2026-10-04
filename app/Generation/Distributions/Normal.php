<?php

namespace App\Generation\Distributions;

use InvalidArgumentException;

/**
 * The standard normal distribution's CDF and its inverse. Used to turn
 * correlated normal scores into percentiles (a Gaussian copula).
 */
final class Normal
{
    /** P(Z ≤ z). Absolute error below 1.5e-7. */
    public static function cdf(float $z): float
    {
        // Abramowitz & Stegun 7.1.26, applied to erf(|z| / √2).
        $x = abs($z) / M_SQRT2;
        $t = 1.0 / (1.0 + 0.3275911 * $x);
        $poly = $t * (0.254829592 + $t * (-0.284496736 + $t * (1.421413741 + $t * (-1.453152027 + $t * 1.061405429))));
        $erf = 1.0 - $poly * exp(-$x * $x);

        return $z >= 0 ? 0.5 * (1.0 + $erf) : 0.5 * (1.0 - $erf);
    }

    /** The z with P(Z ≤ z) = p, for p in (0, 1). Relative error below 1.2e-9. */
    public static function inverseCdf(float $p): float
    {
        if ($p <= 0.0 || $p >= 1.0) {
            throw new InvalidArgumentException("inverseCdf(): p ({$p}) must be strictly between 0 and 1.");
        }

        // Peter Acklam's rational approximation.
        $a = [-3.969683028665376e+01, 2.209460984245205e+02, -2.759285104469687e+02, 1.383577518672690e+02, -3.066479806614716e+01, 2.506628277459239e+00];
        $b = [-5.447609879822406e+01, 1.615858368580409e+02, -1.556989798598866e+02, 6.680131188771972e+01, -1.328068155288572e+01];
        $c = [-7.784894002430293e-03, -3.223964580411365e-01, -2.400758277161838e+00, -2.549732539343734e+00, 4.374664141464968e+00, 2.938163982698783e+00];
        $d = [7.784695709041462e-03, 3.224671290700398e-01, 2.445134137142996e+00, 3.754408661907416e+00];
        $low = 0.02425;

        if ($p < $low) {
            $q = sqrt(-2.0 * log($p));

            return ((((($c[0] * $q + $c[1]) * $q + $c[2]) * $q + $c[3]) * $q + $c[4]) * $q + $c[5])
                / (((($d[0] * $q + $d[1]) * $q + $d[2]) * $q + $d[3]) * $q + 1.0);
        }

        if ($p > 1.0 - $low) {
            return -self::inverseCdf(1.0 - $p);
        }

        $q = $p - 0.5;
        $r = $q * $q;

        return ((((($a[0] * $r + $a[1]) * $r + $a[2]) * $r + $a[3]) * $r + $a[4]) * $r + $a[5]) * $q
            / ((((($b[0] * $r + $b[1]) * $r + $b[2]) * $r + $b[3]) * $r + $b[4]) * $r + 1.0);
    }
}
