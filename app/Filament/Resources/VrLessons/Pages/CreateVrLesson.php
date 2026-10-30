<?php

namespace App\Filament\Resources\VrLessons\Pages;

use App\Filament\Resources\VrLessons\VrLessonResource;
use Filament\Resources\Pages\CreateRecord;

class CreateVrLesson extends CreateRecord
{
    protected static string $resource = VrLessonResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('edit', ['record' => $this->record]);
    }
}
