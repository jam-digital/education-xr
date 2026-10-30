<?php

namespace App\Filament\Support;

use App\Enums\LicenseType;
use App\Enums\Subject;
use App\Models\Device;
use App\Models\User;
use App\Models\VrLesson;
use App\Services\LicenseService;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Support\Carbon;

/** Спільна форма «+ Надати доступ» (картка закладу, масові операції) */
class AccessForm
{
    public static function fields(?User $client = null, ?string $presetType = null, bool $withDevices = true): array
    {
        return array_values(array_filter([
            Radio::make('scope')->label('Що відкрити')->inline()->live()->default('lessons')
                ->options(['lessons' => 'Окремі VR-уроки', 'subject' => 'Весь предметний курс', 'all' => 'Усі предмети']),
            Select::make('lesson_ids')->label('VR-уроки')->multiple()->preload()
                ->options(fn () => VrLesson::where('is_active', true)->orderBy('subject_category')->orderBy('sort')->get()
                    ->groupBy(fn (VrLesson $l) => $l->subject_category->getLabel())
                    ->map(fn ($g) => $g->pluck('title', 'id')->all())->all())
                ->visible(fn (Get $get) => $get('scope') === 'lessons')->required(fn (Get $get) => $get('scope') === 'lessons'),
            Select::make('subjects')->label('Предмети')->multiple()->options(Subject::options())
                ->helperText('Наприклад, «Уся Хімія»: відкриваються всі активні VR-уроки предмета.')
                ->visible(fn (Get $get) => $get('scope') === 'subject')->required(fn (Get $get) => $get('scope') === 'subject'),
            Radio::make('license_type')->label('Тип доступу')->required()->live()
                ->options([
                    LicenseType::TicketPool->value => 'Квитковий пул (кількість запусків уроку)',
                    LicenseType::TimeSubscription->value => 'Часова підписка (місяць, семестр, навчальний рік)',
                    LicenseType::Trial->value => 'Пробний період (демо для директора школи)',
                    LicenseType::UnlimitedPromo->value => 'Шкільний безліміт за акцією',
                ])->default($presetType ?? LicenseType::TicketPool->value),
            TextInput::make('tickets')->label('Кількість запусків')->numeric()->minValue(1)->default(100)
                ->visible(fn (Get $get) => $get('license_type') === LicenseType::TicketPool->value)
                ->required(fn (Get $get) => $get('license_type') === LicenseType::TicketPool->value),
            Radio::make('term_mode')->label('Термін')->inline()->live()
                ->options(['days' => 'Кількість днів', 'until' => 'Точна дата й час'])->default('days')
                ->visible(fn (Get $get) => $get('license_type') !== LicenseType::TicketPool->value),
            TextInput::make('days')->label('Днів доступу')->numeric()->minValue(1)
                ->default($presetType === LicenseType::Trial->value ? 7 : 30)
                ->helperText('7 днів тріалу, 30 днів підписки, 180 семестр або 365 навчальний рік. Якщо доступ ще діє, дні додаються до поточної дати завершення.')
                ->visible(fn (Get $get) => $get('license_type') !== LicenseType::TicketPool->value && $get('term_mode') !== 'until')
                ->required(fn (Get $get) => $get('license_type') !== LicenseType::TicketPool->value && $get('term_mode') !== 'until'),
            DateTimePicker::make('until')->label('Доступ до')->seconds(false)->minDate(now())
                ->visible(fn (Get $get) => $get('license_type') !== LicenseType::TicketPool->value && $get('term_mode') === 'until')
                ->required(fn (Get $get) => $get('license_type') !== LicenseType::TicketPool->value && $get('term_mode') === 'until'),
            $withDevices && $client ? Select::make('device_ids')->label('На яких окулярах')->multiple()
                ->options(fn () => $client->devices()->where('status', 'active')->get()
                    ->mapWithKeys(fn (Device $d) => [$d->id => "{$d->device_name} · {$d->hardware_serial}"]))
                ->placeholder('Увесь VR-клас закладу')
                ->helperText('Порожньо = усі зареєстровані окуляри закладу.') : null,
            Textarea::make('reason')->label('Коментар')->rows(2),
        ]));
    }

    /** @return string|array<string> 'ALL' або id уроків з урахуванням вибору «предметний курс» */
    public static function lessonScope(array $data): string|array
    {
        return match ($data['scope'] ?? (! empty($data['all_lessons']) ? 'all' : 'lessons')) {
            'all' => 'ALL',
            'subject' => VrLesson::where('is_active', true)->whereIn('subject_category', $data['subjects'] ?? [])->pluck('id')->all(),
            default => array_values($data['lesson_ids'] ?? []),
        };
    }

    /** @return int кількість створених / оновлених ліцензій */
    public static function apply(User $client, array $data): int
    {
        $type = LicenseType::from($data['license_type']);
        $timed = $type->isTimeBased();

        return count(app(LicenseService::class)->grant(
            $client,
            self::lessonScope($data),
            $type,
            tickets: $timed ? null : (int) $data['tickets'],
            days: $timed && ($data['term_mode'] ?? 'days') === 'days' ? (int) $data['days'] : null,
            until: $timed && ($data['term_mode'] ?? 'days') === 'until' ? Carbon::parse($data['until']) : null,
            deviceIds: ! empty($data['device_ids']) ? array_values($data['device_ids']) : ['ALL'],
            reason: $data['reason'] ?? null,
        ));
    }
}
