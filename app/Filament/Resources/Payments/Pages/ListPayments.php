<?php

namespace App\Filament\Resources\Payments\Pages;

use App\Enums\PaymentMethod;
use App\Filament\Resources\Payments\PaymentResource;
use App\Filament\Support\PaymentForm;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Grid;
use Filament\Support\Icons\Heroicon;

class ListPayments extends ListRecords
{
    protected static string $resource = PaymentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('manual')->label('Провести оплату вручну')->icon(Heroicon::OutlinedCheckBadge)
                ->visible(fn () => PaymentResource::canManage())
                ->modalHeading('Провести оплату вручну')
                ->modalDescription('Для закладів, що платять за договором. Система створить фінансовий запис, акт виконаних робіт і миттєво відкриє доступ.')
                ->schema(PaymentForm::fields(withMethod: true))
                ->action(fn (array $data) => PaymentForm::record($data, PaymentMethod::from($data['payment_method']))),
            Action::make('invoice')->label('Виставити рахунок')->icon(Heroicon::OutlinedDocumentText)->color('gray')
                ->visible(fn () => PaymentResource::canManage())
                ->modalHeading('Рахунок-фактура для школи (безготівка, казначейство)')
                ->schema(PaymentForm::fields())
                ->action(fn (array $data) => PaymentForm::invoice($data)),
            Action::make('report')->label('Вивантажити фінансовий звіт')->icon(Heroicon::OutlinedArrowDownTray)->color('gray')
                ->modalHeading('Фінансовий звіт')->modalSubmitActionLabel('Завантажити')
                ->modalDescription('Зведення по закладах (продажі, нараховані й використані запуски, залишок) і деталізація всіх платежів.')
                ->schema([
                    Grid::make(2)->schema([
                        DatePicker::make('from')->label('З')->default(now()->startOfMonth())->required(),
                        DatePicker::make('to')->label('По')->default(now())->required(),
                    ]),
                    Select::make('user')->label('Заклад')->placeholder('Усі заклади')->searchable()
                        ->options(fn () => User::clients()->orderBy('school_or_org_name')->get()->mapWithKeys(fn ($u) => [$u->id => $u->display_name])),
                    Radio::make('format')->label('Формат')->inline()->options(['xlsx' => 'XLSX (Excel)', 'csv' => 'CSV'])->default('xlsx')->required(),
                ])
                ->action(fn (array $data) => $this->redirect(route('admin.report.finance', array_filter([
                    'from' => $data['from'], 'to' => $data['to'], 'format' => $data['format'], 'user' => $data['user'] ?? null,
                ])), navigate: false)),
        ];
    }
}
