<?php
class SoilTestHelpers {
    public static function getPHStatus($ph) {
        if ($ph < 6.0) return 'Acidic';
        if ($ph > 7.5) return 'Alkaline';
        return 'Optimal';
    }

    public static function getNitrogenStatus($value) {
        if ($value < 0.5) return ['Low', '#ff6b6b'];
        if ($value > 1.0) return ['High', '#4d96ff'];
        return ['Optimal', '#69db7c'];
    }

    public static function getPhosphorusStatus($value) {
        if ($value < 0.05) return ['Low', '#ff6b6b'];
        if ($value > 0.2) return ['High', '#4d96ff'];
        return ['Optimal', '#69db7c'];
    }

    public static function getPotassiumStatus($value) {
        if ($value < 1.0) return ['Low', '#ff6b6b'];
        if ($value > 2.0) return ['High', '#4d96ff'];
        return ['Optimal', '#69db7c'];
    }
}
?> 