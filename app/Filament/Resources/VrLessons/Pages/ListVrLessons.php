<?php

namespace App\Filament\Resources\VrLessons\Pages;

use App\Filament\Resources\VrLessons\VrLessonResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListVrLessons extends ListRecords
{
    protected static string $resource = VrLessonResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Новий VR-урок')];
    }
}
