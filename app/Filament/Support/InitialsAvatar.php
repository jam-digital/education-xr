<?php

namespace App\Filament\Support;

use Filament\AvatarProviders\Contracts\AvatarProvider;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Model;

/** Аватар без фото: кругла плашка з двома літерами (ім’я + прізвище), без зовнішніх сервісів */
class InitialsAvatar implements AvatarProvider
{
    public function get(Model $record): string
    {
        $words = preg_split('/\s+/u', trim(Filament::getNameForDefaultAvatar($record))) ?: ['?'];
        $initials = mb_strtoupper(mb_substr($words[0], 0, 1).(isset($words[1]) ? mb_substr($words[1], 0, 1) : ''));
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64"><circle cx="32" cy="32" r="32" fill="#7c3aed"/>'
            .'<text x="32" y="41" font-family="Inter,Arial,sans-serif" font-size="26" font-weight="600" fill="#fff" text-anchor="middle">'
            .htmlspecialchars($initials).'</text></svg>';

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }
}
