<?php

namespace App\Filament\Support;

use Illuminate\Support\Facades\DB;

class Fmt
{
    /** Пошук без урахування регістру: ilike у PostgreSQL, like в інших БД */
    public static function like(): string
    {
        return DB::connection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';
    }

    public static function bytes(?int $bytes): string
    {
        if ($bytes === null) {
            return '—';
        }
        $units = ['Б', 'КБ', 'МБ', 'ГБ', 'ТБ'];
        $i = 0;
        $v = (float) $bytes;
        while ($v >= 1024 && $i < count($units) - 1) {
            $v /= 1024;
            $i++;
        }

        return number_format($v, $i >= 3 ? 1 : 0, ',', ' ').' '.$units[$i];
    }

    public static function batteryColor(?int $pct): string
    {
        return match (true) {
            $pct === null => 'gray',
            $pct < 20 => 'danger',
            $pct < 50 => 'warning',
            default => 'success',
        };
    }
}
