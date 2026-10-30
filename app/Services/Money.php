<?php

namespace App\Services;

/** Сума прописом українською для рахунків і актів */
class Money
{
    private const ONES_M = ['', 'один', 'два', 'три', 'чотири', "п'ять", 'шість', 'сім', 'вісім', "дев'ять"];

    private const ONES_F = ['', 'одна', 'дві', 'три', 'чотири', "п'ять", 'шість', 'сім', 'вісім', "дев'ять"];

    private const TEENS = ['десять', 'одинадцять', 'дванадцять', 'тринадцять', 'чотирнадцять', "п'ятнадцять", 'шістнадцять', 'сімнадцять', 'вісімнадцять', "дев'ятнадцять"];

    private const TENS = ['', '', 'двадцять', 'тридцять', 'сорок', "п'ятдесят", 'шістдесят', 'сімдесят', 'вісімдесят', "дев'яносто"];

    private const HUNDREDS = ['', 'сто', 'двісті', 'триста', 'чотириста', "п'ятсот", 'шістсот', 'сімсот', 'вісімсот', "дев'ятсот"];

    public static function format(float $amount, string $currency = 'UAH'): string
    {
        $sign = ['UAH' => '₴', 'USD' => '$', 'EUR' => '€'][$currency] ?? $currency;

        return number_format($amount, 2, ',', ' ').' '.$sign;
    }

    public static function inWordsUah(float $amount): string
    {
        $uah = (int) floor($amount);
        $kop = (int) round(($amount - $uah) * 100);
        $words = $uah === 0 ? 'нуль' : self::number($uah);

        return mb_strtoupper(mb_substr($words, 0, 1)).mb_substr($words, 1).' '
            .self::plural($uah, 'гривня', 'гривні', 'гривень').' '
            .str_pad((string) $kop, 2, '0', STR_PAD_LEFT).' '.self::plural($kop, 'копійка', 'копійки', 'копійок');
    }

    private static function number(int $n): string
    {
        $groups = [
            [1_000_000_000, ['мільярд', 'мільярди', 'мільярдів'], false],
            [1_000_000, ['мільйон', 'мільйони', 'мільйонів'], false],
            [1_000, ['тисяча', 'тисячі', 'тисяч'], true],
        ];
        $out = [];
        foreach ($groups as [$div, $forms, $fem]) {
            $g = intdiv($n, $div);
            if ($g) {
                $out[] = self::triad($g, $fem).' '.self::plural($g, ...$forms);
            }
            $n %= $div;
        }
        if ($n) {
            $out[] = self::triad($n, true); // «гривня» жіночого роду
        }

        return implode(' ', $out);
    }

    private static function triad(int $n, bool $fem): string
    {
        $w = [self::HUNDREDS[intdiv($n, 100)]];
        $r = $n % 100;
        if ($r >= 10 && $r < 20) {
            $w[] = self::TEENS[$r - 10];
        } else {
            $w[] = self::TENS[intdiv($r, 10)];
            $w[] = ($fem ? self::ONES_F : self::ONES_M)[$r % 10];
        }

        return implode(' ', array_filter($w));
    }

    private static function plural(int $n, string $one, string $few, string $many): string
    {
        $n = abs($n) % 100;
        $d = $n % 10;
        if ($n > 10 && $n < 20) {
            return $many;
        }

        return $d === 1 ? $one : ($d >= 2 && $d <= 4 ? $few : $many);
    }
}
