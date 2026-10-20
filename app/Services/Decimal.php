<?php

namespace App\Services;

use Illuminate\Validation\ValidationException;

/** Decimal strings keep rupiah calculations independent of floating point. */
final class Decimal
{
    public const MAX = '999999999999.99';

    public static function round(string|int $value, int $scale = 2): string
    {
        $value = (string) $value;
        $half = '0.'.str_repeat('0', $scale).'5';

        return bcadd($value, bccomp($value, '0', $scale + 8) < 0 ? '-'.$half : $half, $scale);
    }

    public static function money(string|int $value): string
    {
        $value = self::round($value);
        if (bccomp($value, self::MAX, 2) > 0 || bccomp($value, '0', 2) < 0) {
            throw ValidationException::withMessages(['amount' => 'Nilai transaksi melebihi batas yang diperbolehkan.']);
        }

        return $value;
    }

    public static function sum(iterable $values, int $scale = 2): string
    {
        $sum = '0';
        foreach ($values as $value) {
            $sum = bcadd($sum, (string) $value, $scale);
        }

        return bcadd($sum, '0', $scale);
    }
}
