<?php

namespace App\Filament\Resources\Accounts\Pages;

use App\Enums\AuditAction;
use App\Filament\Resources\Accounts\AccountResource;
use App\Services\Audit;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditAccount extends EditRecord
{
    protected static string $resource = AccountResource::class;

    protected function getHeaderActions(): array
    {
        return [ViewAction::make(), DeleteAction::make()];
    }

    protected function afterSave(): void
    {
        if ($this->record->wasChanged('status')) {
            Audit::log(AuditAction::AccountStatus, $this->record,
                old: ['status' => $this->record->getOriginal('status')?->value ?? $this->record->getOriginal('status')],
                new: ['status' => $this->record->status->value]);
        }
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->record]);
    }
}
