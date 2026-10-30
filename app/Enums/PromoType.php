<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum PromoType: string implements HasColor, HasLabel
{
    case AllSubjectsBundle = 'all_subjects_bundle';
    case SubjectPack = 'subject_pack_physics_chem_math';
    case NewLessonRelease = 'new_lesson_release';
    case DiscountPack = 'discount_pack';
    case TrialOffer = 'trial_school_offer';
    case PromoCode = 'promo_code';

    public function getLabel(): string
    {
        return match ($this) {
            self::AllSubjectsBundle => 'Усі предмети (комплексний пакет)',
            self::SubjectPack => 'Комплект «Фізика, Хімія, Математика»',
            self::NewLessonRelease => 'Новий VR-урок',
            self::DiscountPack => 'Знижка на пакет',
            self::TrialOffer => 'Пробний доступ для школи',
            self::PromoCode => 'Грантовий промокод',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::AllSubjectsBundle => 'success',
            self::SubjectPack => 'primary',
            self::NewLessonRelease => 'info',
            self::DiscountPack => 'warning',
            self::TrialOffer => 'primary',
            self::PromoCode => 'gray',
        };
    }
}
