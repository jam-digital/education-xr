<?php

namespace App\Filament\Resources\Payments\Tables;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\Subject;
use App\Filament\Resources\Accounts\AccountResource;
use App\Filament\Resources\Payments\PaymentResource;
use App\Models\Payment;
use App\Models\Promotion;
use App\Models\VrLesson;
use App\Services\PaymentService;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Throwable;

class PaymentsTable
{
    public static function configure(Table $table, bool $inAccount = false): Table
    {
        $manage = fn () => PaymentResource::canManage();

        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('user', 'lesson', 'promotion'))
            ->defaultSort('created_at', 'desc')
            ->columns(array_values(array_filter([
                TextColumn::make('invoice_number')->label('Рахунок')->fontFamily('mono')->searchable()->copyable(),
                TextColumn::make('created_at')->label('Дата')->dateTime('d.m.Y H:i')->sortable()
                    ->description(fn (Payment $r) => $r->paid_at && ! $r->paid_at->isSameDay($r->created_at) ? 'оплачено '.$r->paid_at->format('d.m.Y') : null),
                $inAccount ? null : TextColumn::make('user.school_or_org_name')->label('Заклад')->wrap()
                    ->formatStateUsing(fn ($state, Payment $r) => $r->user->display_name)
                    ->description(fn (Payment $r) => $r->user->edrpou_tax_id)
                    ->url(fn (Payment $r) => AccountResource::getUrl('view', ['record' => $r->user]))
                    ->searchable(['school_or_org_name', 'contact_person_name', 'edrpou_tax_id']),
                TextColumn::make('grant')->label('Що оплачено')->state(fn (Payment $r) => $r->grantLabel())->wrap(),
                TextColumn::make('amount')->label('Сума')->money(fn (Payment $r) => $r->currency)->sortable()->alignEnd(),
                TextColumn::make('payment_method')->label('Спосіб')->badge()
                    ->description(fn (Payment $r) => $r->gateway_name),
                TextColumn::make('status')->label('Статус')->badge(),
                TextColumn::make('docs')->label('Документи')->html()
                    ->state(fn (Payment $r) => collect([
                        $r->invoice_pdf_url ? '<a class="xr-link" target="_blank" href="'.route('admin.payment.document', [$r, 'invoice']).'">Рахунок</a>' : null,
                        $r->act_pdf_url ? '<a class="xr-link" target="_blank" href="'.route('admin.payment.document', [$r, 'act']).'">Акт</a>' : null,
                    ])->filter()->implode(' · ') ?: '—'),
            ])))
            ->filters(array_values(array_filter([
                Filter::make('period')->label('Період')
                    ->schema([DatePicker::make('from')->label('З'), DatePicker::make('to')->label('По')])->columns(2)
                    ->query(fn (Builder $query, array $data) => $query
                        ->when($data['from'] ?? null, fn ($query, $d) => $query->whereDate('created_at', '>=', $d))
                        ->when($data['to'] ?? null, fn ($query, $d) => $query->whereDate('created_at', '<=', $d)))
                    ->indicateUsing(fn (array $data) => array_filter([
                        ($data['from'] ?? null) ? 'з '.date('d.m.Y', strtotime($data['from'])) : null,
                        ($data['to'] ?? null) ? 'по '.date('d.m.Y', strtotime($data['to'])) : null,
                    ])),
                $inAccount ? null : SelectFilter::make('user_id')->label('Заклад')
                    ->relationship('user', 'school_or_org_name', fn ($query) => $query->clients())->searchable()->preload(),
                SelectFilter::make('subject')->label('Предмет')->options(Subject::class)
                    ->query(fn (Builder $query, array $data) => $query->when($data['value'], fn ($query, $subject) => $query->where(fn ($w) => $w
                        ->where('subject_category', $subject)->orWhereHas('lesson', fn ($l) => $l->where('subject_category', $subject))))),
                SelectFilter::make('lesson_id')->label('VR-урок')->options(fn () => VrLesson::orderBy('sort')->pluck('title', 'id')),
                SelectFilter::make('promotion_id')->label('Акція')->options(fn () => Promotion::where('promo_type', '!=', 'promo_code')->pluck('title', 'id')),
                SelectFilter::make('payment_method')->label('Тип оплати')->options(PaymentMethod::class),
                SelectFilter::make('status')->label('Статус')->options(PaymentStatus::class),
            ])))
            ->filtersFormColumns(2)
            ->recordActions([
                Action::make('confirm')->label('Оплачено')->icon(Heroicon::OutlinedCheckCircle)->color('success')->button()
                    ->visible(fn (Payment $r) => $manage() && $r->status === PaymentStatus::Pending)
                    ->requiresConfirmation()->modalHeading(fn (Payment $r) => "Підтвердити оплату {$r->invoice_number}?")
                    ->modalDescription(fn (Payment $r) => "Заклад: {$r->user->display_name}. Буде згенеровано акт і відкрито доступ: {$r->grantLabel()}.")
                    ->schema([Textarea::make('comment')->label('Коментар')->placeholder('Платіжне доручення №…')->rows(2)])
                    ->action(function (Payment $r, array $data) {
                        try {
                            app(PaymentService::class)->confirm($r, $data['comment'] ?? null);
                            Notification::make()->title('Оплату підтверджено, доступ відкрито')->success()->send();
                        } catch (Throwable $e) {
                            Notification::make()->title($e->getMessage())->danger()->send();
                        }
                    }),
                ActionGroup::make([
                    Action::make('invoicePdf')->label('Рахунок PDF')->icon(Heroicon::OutlinedDocumentArrowDown)
                        ->visible(fn (Payment $r) => (bool) $r->invoice_pdf_url)
                        ->url(fn (Payment $r) => route('admin.payment.document', [$r, 'invoice']), shouldOpenInNewTab: true),
                    Action::make('actPdf')->label('Акт PDF')->icon(Heroicon::OutlinedDocumentCheck)
                        ->visible(fn (Payment $r) => (bool) $r->act_pdf_url)
                        ->url(fn (Payment $r) => route('admin.payment.document', [$r, 'act']), shouldOpenInNewTab: true),
                    Action::make('fail')->label('Скасувати рахунок')->icon(Heroicon::OutlinedXCircle)->color('danger')
                        ->visible(fn (Payment $r) => $manage() && $r->status === PaymentStatus::Pending)
                        ->requiresConfirmation()->action(fn (Payment $r) => $r->update(['status' => PaymentStatus::Failed])),
                    Action::make('refund')->label('Повернення коштів')->icon(Heroicon::OutlinedReceiptRefund)->color('danger')
                        ->visible(fn (Payment $r) => auth()->user()->isSuperAdmin() && $r->status === PaymentStatus::Completed)
                        ->requiresConfirmation()->modalDescription('Статус зміниться на «Повернено». Доступи треба забрати окремо в картці закладу.')
                        ->schema([Textarea::make('reason')->label('Причина')->required()])
                        ->action(fn (Payment $r, array $data) => app(PaymentService::class)->refund($r, $data['reason'])),
                ]),
            ]);
    }
}
