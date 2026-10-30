<?php

namespace App\Filament\Resources\Accounts\Pages;

use App\Enums\Role;
use App\Filament\Resources\Accounts\AccountResource;
use Filament\Resources\Pages\CreateRecord;

class CreateAccount extends CreateRecord
{
    protected static string $resource = AccountResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return $data + ['role' => Role::Client];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->record]);
    }
}
