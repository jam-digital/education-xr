<?php

namespace App\Filament\Support;

use App\Enums\PaymentMethod;
use App\Models\Payment;
use App\Models\Promotion;
use App\Models\User;
use App\Models\VrLesson;
use App\Services\PaymentService;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Throwable;

/** Форми «Провести оплату вручну» і «Виставити рахунок» */
class PaymentForm
{
    public static function fields(?User $client = null, bool $withMethod = false): array
    {
        return array_values(array_filter([
            $client ? null : Select::make('user_id')->label('Заклад')->required()->searchable()
                ->getSearchResultsUsing(fn (string $s) => User::clients()->where(fn ($query) => $query->where('school_or_org_name', Fmt::like(), "%{$s}%")
                    ->orWhere('contact_person_name', Fmt::like(), "%{$s}%")->orWhere('edrpou_tax_id', 'like', "%{$s}%"))
                    ->limit(30)->get()->mapWithKeys(fn (User $u) => [$u->id => $u->display_name.($u->edrpou_tax_id ? " · {$u->edrpou_tax_id}" : '')]))
                ->getOptionLabelUsing(fn ($value) => User::find($value)?->display_name),
            Radio::make('source')->label('За що оплата')->inline()->live()->default('lesson')
                ->options(['lesson' => 'VR-урок', 'promo' => 'Акція / пакет']),
            Select::make('lesson_id')->label('VR-урок')->live()
                ->options(fn () => VrLesson::where('is_active', true)->orderBy('sort')->pluck('title', 'id'))
                ->visible(fn (Get $get) => $get('source') === 'lesson')->required(fn (Get $get) => $get('source') === 'lesson')
                ->afterStateUpdated(function (Set $set, ?string $state) {
                    if ($lesson = VrLesson::find($state)) {
                        $set('amount', (float) $lesson->base_price);
                        $set('tickets', $lesson->default_tickets_count);
                    }
                }),
            Select::make('promotion_id')->label('Акція')->live()
                ->options(fn () => Promotion::where('promo_type', '!=', 'promo_code')->orderByDesc('created_at')->pluck('title', 'id'))
                ->visible(fn (Get $get) => $get('source') === 'promo')->required(fn (Get $get) => $get('source') === 'promo')
                ->afterStateUpdated(function (Set $set, ?string $state) {
                    if ($p = Promotion::find($state)) {
                        $set('amount', (float) $p->price);
                        $set('tickets', $p->grants_tickets_count);
                        $set('days', $p->grants_duration_days);
                    }
                }),
            Grid::make(3)->schema([
                TextInput::make('amount')->label('Сума, ₴')->numeric()->minValue(0)->required(),
                TextInput::make('tickets')->label('Нарахувати запусків')->numeric()->minValue(0)->default(100),
                TextInput::make('days')->label('Або днів доступу')->numeric()->minValue(0),
            ]),
            $withMethod ? Select::make('payment_method')->label('Спосіб оплати')->required()
                ->options([PaymentMethod::BankInvoice->value => 'За рахунком (безготівка)', PaymentMethod::ManualAdmin->value => 'Інше (вручну адміном)'])
                ->default(PaymentMethod::BankInvoice->value) : null,
            Textarea::make('comment')->label('Коментар')->rows(2)->placeholder('Договір №…, платіжне доручення №…'),
        ]));
    }

    private static function args(array $data): array
    {
        return [
            User::findOrFail($data['user_id']),
            ($data['source'] ?? 'lesson') === 'lesson' ? VrLesson::find($data['lesson_id'] ?? null) : null,
            ($data['source'] ?? 'lesson') === 'promo' ? Promotion::find($data['promotion_id'] ?? null) : null,
        ];
    }

    public static function record(array $data, PaymentMethod $method): ?Payment
    {
        try {
            [$client, $lesson, $promo] = self::args($data);
            $p = app(PaymentService::class)->recordManual($client, $lesson, $promo, $method,
                (float) $data['amount'], (int) ($data['tickets'] ?? 0), (int) ($data['days'] ?? 0), $data['comment'] ?? null);
            Notification::make()->title("Оплату {$p->invoice_number} проведено")->body('Акт згенеровано, доступ відкрито: '.$p->grantLabel())->success()->send();

            return $p;
        } catch (Throwable $e) {
            Notification::make()->title($e->getMessage())->danger()->send();

            return null;
        }
    }

    public static function invoice(array $data): ?Payment
    {
        try {
            [$client, $lesson, $promo] = self::args($data);
            $p = app(PaymentService::class)->createInvoice($client, $lesson, $promo,
                (float) $data['amount'], (int) ($data['tickets'] ?? 0), (int) ($data['days'] ?? 0), $data['comment'] ?? null);
            Notification::make()->title("Рахунок {$p->invoice_number} виставлено")->success()->send();

            return $p;
        } catch (Throwable $e) {
            Notification::make()->title($e->getMessage())->danger()->send();

            return null;
        }
    }
}
