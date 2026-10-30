<?php

namespace App\Filament\Resources\VrLessons\Pages;

use App\Filament\Resources\VrLessons\VrLessonResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditVrLesson extends EditRecord
{
    protected static string $resource = VrLessonResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }

    protected function getFormActions(): array
    {
        return VrLessonResource::canManage() ? parent::getFormActions() : [];
    }
}
