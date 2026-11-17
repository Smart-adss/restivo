<?php

namespace App\Support\Money;

final class Money
{
    public static function of(int|float|string $value): string
    {
        if (is_int($value)) {
            return number_format($value, 2, '.', '');
        }

        if (is_float($value)) {
            return number_format($value, 2, '.', '');
        }

        $normalized = trim($value);

        if ($normalized === '' || ! is_numeric($normalized)) {
            return '0.00';
        }

        return number_format((float) $normalized, 2, '.', '');
    }

    public static function add(string ...$amounts): string
    {
        $total = '0';

        foreach ($amounts as $amount) {
            $total = bcadd($total, self::of($amount), 4);
        }

        return self::round($total);
    }

    public static function sub(string $left, string $right): string
    {
        return self::round(bcsub(self::of($left), self::of($right), 4));
    }

    public static function mul(string $left, string $right): string
    {
        return self::round(bcmul(self::of($left), self::of($right), 4));
    }

    public static function percent(string $amount, string $ratePercent): string
    {
        return self::round(bcmul(self::of($amount), bcdiv(self::of($ratePercent), '100', 8), 4));
    }

    public static function extractInclusiveTax(string $gross, string $ratePercent): array
    {
        $gross = self::of($gross);
        $rate = self::of($ratePercent);

        if (bccomp($rate, '0', 4) === 0) {
            return ['net' => $gross, 'tax' => '0.00'];
        }

        $divisor = bcadd('1', bcdiv($rate, '100', 8), 8);
        $net = self::round(bcdiv($gross, $divisor, 8));
        $tax = self::sub($gross, $net);

        return ['net' => $net, 'tax' => $tax];
    }

    public static function compare(string $left, string $right): int
    {
        return bccomp(self::of($left), self::of($right), 2);
    }

    public static function round(string $value): string
    {
        return number_format((float) $value, 2, '.', '');
    }

    /**
     * Split an amount into N parts that sum exactly.
     *
     * @return list<string>
     */
    public static function split(string $amount, int $parts): array
    {
        if ($parts < 1) {
            return [];
        }

        $amount = self::of($amount);
        $base = self::round(bcdiv($amount, (string) $parts, 8));
        $values = array_fill(0, $parts, $base);
        $sum = self::mul($base, (string) $parts);
        $diff = self::sub($amount, $sum);
        $values[$parts - 1] = self::add($values[$parts - 1], $diff);

        return $values;
    }
}
