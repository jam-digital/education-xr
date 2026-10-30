<?php

namespace App\Filament\Resources\VrLessons\Tables;

use App\Enums\Subject;
use App\Filament\Resources\VrLessons\VrLessonResource;
use App\Filament\Support\Fmt;
use App\Models\VrLesson;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class VrLessonsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('sort')
            ->reorderable('sort', fn () => VrLessonResource::canManage())
            ->modifyQueryUsing(fn ($query) => $query->withCount(['licenses as active_licenses_count' => fn ($l) => $l->where('status', 'active')]))
            ->columns([
                ImageColumn::make('cover_image_url')->label('')->disk('public')->imageWidth(64)->imageHeight(40),
                TextColumn::make('title')->label('VR-урок')->weight('bold')->searchable()->wrap()
                    ->description(fn (VrLesson $r) => $r->android_package_name),
                TextColumn::make('subject_category')->label('Предмет')->badge()->sortable(),
                TextColumn::make('recommended_grades')->label('Класи')->placeholder('—')->toggleable(),
                IconColumn::make('methodical_guide_pdf_url')->label('Методичка')->boolean()->toggleable()
                    ->state(fn (VrLesson $r) => (bool) $r->methodical_guide_pdf_url),
                TextColumn::make('app_key')->label('Ключ')->fontFamily('mono')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('current_version_name')->label('Версія')->placeholder('—')
                    ->description(fn (VrLesson $r) => $r->current_version_code ? "code {$r->current_version_code}" : null),
                TextColumn::make('release_channel')->label('Канал')->badge(),
                TextColumn::make('size')->label('APK + OBB')->state(fn (VrLesson $r) => Fmt::bytes($r->total_size_bytes ?: null)),
                TextColumn::make('base_price')->label('Ціна пакета')->money('UAH')
                    ->description(fn (VrLesson $r) => "за {$r->default_tickets_count} запусків"),
                TextColumn::make('grace_period_seconds')->label('Grace')->suffix(' с')->toggleable(),
                TextColumn::make('active_licenses_count')->label('Шкіл')->badge()->color('gray'),
                ToggleColumn::make('is_active')->label('Активний')->disabled(fn () => ! VrLessonResource::canManage()),
            ])
            ->filters([
                SelectFilter::make('subject_category')->label('Предмет')->options(Subject::class)->multiple(),
                TernaryFilter::make('is_active')->label('Активний'),
            ])
            ->recordActions([EditAction::make()->label(fn () => VrLessonResource::canManage() ? 'Редагувати' : 'Відкрити')]);
    }
}
