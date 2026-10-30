<?php

namespace App\Filament\Resources\Promotions\Schemas;

use App\Enums\Currency;
use App\Enums\PromoType;
use App\Models\User;
use App\Models\VrLesson;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class PromotionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->columns(3)->components([
            Section::make('Акція')->columnSpan(2)->columns(2)->schema([
                TextInput::make('title')->label('Заголовок')->required()->columnSpanFull()
                    ->placeholder('Безліміт на всі VR-уроки на рік'),
                Select::make('promo_type')->label('Тип')->options(PromoType::class)->required()->live()->default(PromoType::AllSubjectsBundle),
                TextInput::make('promo_code_str')->label('Промокод')->unique(ignoreRecord: true)->alphaDash()
                    ->dehydrateStateUsing(fn ($state) => $state ? mb_strtoupper($state) : null)
                    ->visible(fn (Get $get) => self::is($get, PromoType::PromoCode))
                    ->required(fn (Get $get) => self::is($get, PromoType::PromoCode)),
                Textarea::make('description')->label('Опис для кабінету')->rows(3)->columnSpanFull(),
                FileUpload::make('banner_url')->label('Банер')->image()->disk('public')->directory('promotions')->columnSpanFull(),
            ]),
            Section::make('Показ')->columnSpan(1)->schema([
                Toggle::make('is_active')->label('Активна')->default(true),
                DateTimePicker::make('starts_at')->label('Початок показу')->seconds(false)->default(now()),
                DateTimePicker::make('expires_at')->label('Завершення')->seconds(false)->after('starts_at'),
                Select::make('target_user_ids')->label('Кому показувати')->multiple()->searchable()
                    ->options(fn () => User::clients()->orderBy('school_or_org_name')->get()->mapWithKeys(fn ($u) => [$u->id => $u->display_name]))
                    ->placeholder('Усім закладам')
                    ->formatStateUsing(fn ($state) => $state === ['ALL'] ? [] : $state)
                    ->dehydrateStateUsing(fn ($state) => $state ? array_values($state) : ['ALL']),
                TextInput::make('max_redemptions')->label('Ліміт використань')->numeric()->minValue(1)->placeholder('без ліміту'),
            ]),
            Section::make('Умови')->columnSpan(2)->columns(3)->schema([
                TextInput::make('price')->label('Ціна акції')->numeric()->minValue(0)
                    ->hidden(fn (Get $get) => self::is($get, PromoType::PromoCode)),
                Select::make('currency')->label('Валюта')->options(Currency::class)->default(Currency::UAH)->required(),
                TextInput::make('discount_percent')->label('Знижка, %')->numeric()->minValue(1)->maxValue(100)
                    ->visible(fn (Get $get) => self::is($get, PromoType::DiscountPack, PromoType::PromoCode)),
                Toggle::make('all_lessons')->label('Усі VR-уроки')->live()->columnSpanFull()
                    ->afterStateHydrated(fn (Toggle $component, $record) => $component->state(! $record || in_array('ALL', $record->included_lesson_ids ?? ['ALL'], true)))
                    ->dehydrated(false),
                Select::make('included_lesson_ids')->label('Уроки в акції')->multiple()->preload()->columnSpanFull()
                    ->options(fn () => VrLesson::orderBy('sort')->pluck('title', 'id'))
                    ->hidden(fn (Get $get) => $get('all_lessons'))
                    ->formatStateUsing(fn ($state) => $state === ['ALL'] ? [] : $state)
                    ->dehydrateStateUsing(fn ($state, Get $get) => $get('all_lessons') || ! $state ? ['ALL'] : array_values($state)),
                TextInput::make('grants_tickets_count')->label('Нараховує запусків')->numeric()->minValue(0),
                TextInput::make('grants_duration_days')->label('Або безліміт на днів')->numeric()->minValue(0)->placeholder('365'),
            ]),
        ]);
    }

    private static function is(Get $get, PromoType ...$types): bool
    {
        $v = $get('promo_type');
        $v = $v instanceof PromoType ? $v : PromoType::tryFrom((string) $v);

        return in_array($v, $types, true);
    }
}
