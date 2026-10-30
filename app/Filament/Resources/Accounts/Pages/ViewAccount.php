<?php

namespace App\Filament\Resources\Accounts\Pages;

use App\Enums\PaymentMethod;
use App\Filament\Resources\Accounts\AccountResource;
use App\Filament\Resources\Payments\PaymentResource;
use App\Filament\Support\AccessForm;
use App\Filament\Support\PaymentForm;
use App\Services\LicenseService;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;
use Throwable;

class ViewAccount extends ViewRecord
{
    protected static string $resource = AccountResource::class;

    public function getTitle(): string
    {
        return $this->record->display_name;
    }

    /** Вкладки «Доступи», «Окуляри», «Платежі», «Запуски» замість окремих блоків */
    public function hasCombinedRelationManagerTabsWithContent(): bool
    {
        return false;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('grant')->label('Надати доступ')->icon(Heroicon::OutlinedKey)
                ->visible(fn () => AccountResource::canManage())
                ->modalHeading('Надати доступ до VR-уроків')->modalSubmitActionLabel('Надати доступ')
                ->schema(fn () => AccessForm::fields($this->record))
                ->action(function (array $data) {
                    try {
                        $n = AccessForm::apply($this->record, $data);
                        Notification::make()->title("Доступ надано ({$n})")->success()->send();
                    } catch (Throwable $e) {
                        Notification::make()->title($e->getMessage())->danger()->send();
                    }
                }),
            Action::make('payment')->label('Провести оплату')->icon(Heroicon::OutlinedBanknotes)->color('gray')
                ->visible(fn () => PaymentResource::canManage())
                ->modalHeading('Провести оплату вручну')
                ->schema(fn () => PaymentForm::fields($this->record, withMethod: true))
                ->action(fn (array $data) => PaymentForm::record($data + ['user_id' => $this->record->id], PaymentMethod::from($data['payment_method']))),
            ActionGroup::make([
                EditAction::make(),
                Action::make('revokeAll')->label('Забрати всі доступи')->icon(Heroicon::OutlinedNoSymbol)->color('danger')
                    ->visible(fn () => AccountResource::canManage())
                    ->requiresConfirmation()->modalDescription('Усі активні ліцензії закладу буде миттєво відкликано на всіх окулярах.')
                    ->schema([Textarea::make('reason')->label('Причина')->required()])
                    ->action(function (array $data) {
                        $n = app(LicenseService::class)->revoke($this->record->licenses()->with('lesson', 'user')->get(), $data['reason']);
                        Notification::make()->title("Відкликано доступів: {$n}")->success()->send();
                    }),
            ]),
        ];
    }
}
