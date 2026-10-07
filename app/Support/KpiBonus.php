<?php

namespace App\Support;

class KpiBonus
{
    public static function amount(float $targetBonus, float $score): float
    {
        return round($targetBonus * min(max(round($score, 2), 0), 100) / 100, 2);
    }
}
