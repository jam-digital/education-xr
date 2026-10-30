<?php

namespace App\Filament\Resources\Promotions\Pages;

use App\Enums\Currency;
use App\Enums\PromoType;
use App\Filament\Resources\Promotions\PromotionResource;
use App\Models\Promotion;
use App\Models\VrLesson;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Str;

class ListPromotions extends ListRecords
{
    protected static string $resource = PromotionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Нова акція'),
            Action::make('generate')->label('Генератор промокодів')->icon(Heroicon::OutlinedSparkles)->color('gray')
                ->visible(fn () => PromotionResource::canManage())
                ->modalHeading('Генератор промокодів')->modalSubmitActionLabel('Згенерувати')
                ->modalDescription('Коди на знижку або на тестові запуски / дні доступу для партнерів і виставок.')
                ->schema([
                    TextInput::make('batch')->label('Назва партії')->required()->placeholder('Виставка Museum Expo 2026'),
                    Grid::make(2)->schema([
                        TextInput::make('count')->label('Кількість кодів')->numeric()->minValue(1)->maxValue(1000)->default(20)->required(),
                        TextInput::make('prefix')->label('Префікс')->default('XR')->alphaDash()->maxLength(10),
                    ]),
                    Radio::make('benefit')->label('Що дає код')->live()->required()->default('tickets')
                        ->options(['tickets' => 'Тестові запуски', 'days' => 'Дні доступу', 'discount' => 'Знижку на пакет']),
                    TextInput::make('value')->required()->numeric()->minValue(1)
                        ->label(fn (Get $get) => match ($get('benefit')) {
                            'days' => 'Днів', 'discount' => 'Знижка, %', default => 'Квитків'
                        })
                        ->default(10),
                    Select::make('lesson_ids')->label('Для уроків')->multiple()->preload()->placeholder('Усі уроки')
                        ->options(fn () => VrLesson::orderBy('sort')->pluck('title', 'id')),
                    Grid::make(2)->schema([
                        DateTimePicker::make('expires_at')->label('Діє до')->seconds(false)->default(now()->addMonths(3)),
                        TextInput::make('max')->label('Використань на код')->numeric()->minValue(1)->default(1),
                    ]),
                ])
                ->action(function (array $data) {
                    $prefix = mb_strtoupper($data['prefix'] ?: 'XR');
                    $n = 0;
                    $codes = [];
                    while ($n < (int) $data['count']) {
                        $code = $prefix.'-'.Str::upper(Str::random(4)).'-'.Str::upper(Str::random(4));
                        if (Promotion::where('promo_code_str', $code)->exists()) {
                            continue;
                        }
                        Promotion::create([
                            'title' => $data['batch'],
                            'promo_type' => PromoType::PromoCode,
                            'promo_code_str' => $code,
                            'currency' => Currency::UAH,
                            'included_lesson_ids' => $data['lesson_ids'] ?: ['ALL'],
                            'target_user_ids' => ['ALL'],
                            'grants_tickets_count' => $data['benefit'] === 'tickets' ? (int) $data['value'] : null,
                            'grants_duration_days' => $data['benefit'] === 'days' ? (int) $data['value'] : null,
                            'discount_percent' => $data['benefit'] === 'discount' ? min(100, (int) $data['value']) : null,
                            'max_redemptions' => $data['max'] ?: null,
                            'batch_label' => $data['batch'],
                            'is_active' => true,
                            'starts_at' => now(),
                            'expires_at' => $data['expires_at'],
                        ]);
                        $codes[] = $code;
                        $n++;
                    }
                    Notification::make()->title("Згенеровано кодів: {$n}")
                        ->body(implode(', ', array_slice($codes, 0, 5)).($n > 5 ? '…' : '').' Фільтр «Партія промокодів» покаже всі.')
                        ->success()->persistent()->send();
                }),
        ];
    }
}
