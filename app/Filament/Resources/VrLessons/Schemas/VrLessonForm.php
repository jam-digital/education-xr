<?php

namespace App\Filament\Resources\VrLessons\Schemas;

use App\Enums\Subject;
use App\Filament\Resources\VrLessons\VrLessonResource;
use App\Filament\Support\Fmt;
use App\Models\VrLesson;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class VrLessonForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->columns(3)->disabled(fn () => ! VrLessonResource::canManage())->components([
            Section::make('Картка VR-уроку')->columnSpan(2)->columns(2)->schema([
                TextInput::make('title')->label('Назва теми')->required()->maxLength(255)->live(onBlur: true)->columnSpanFull()
                    ->placeholder('Хімія: Молекулярні сполуки та таблиця Менделєєва')
                    ->afterStateUpdated(fn (Set $set, ?string $state, string $operation) => $operation === 'create' ? $set('app_key', Str::slug($state).'-vr') : null),
                Select::make('subject_category')->label('Предмет')->options(Subject::class)->required()->default(Subject::Physics),
                TextInput::make('recommended_grades')->label('Рекомендовані класи')->placeholder('7–9 класи')->maxLength(40)
                    ->datalist(['5–6 класи', '7–9 класи', '10–11 класи', '7–11 класи']),
                TextInput::make('app_key')->label('Системний ключ (app_key)')->required()->alphaDash()->unique(ignoreRecord: true)
                    ->placeholder('chemistry-periodic-lab'),
                TextInput::make('android_package_name')->label('Android package name')->required()->unique(ignoreRecord: true)
                    ->regex('/^[a-z][a-z0-9_]*(\.[a-z0-9_]+)+$/')->placeholder('com.vrosvita.lesson.chemistry01'),
                TextInput::make('meta_store_app_id')->label('Meta Store App ID')->placeholder('необов’язково'),
                Textarea::make('description')->label('Опис навчальної мети')->rows(4)->columnSpanFull(),
                FileUpload::make('methodical_guide_pdf_url')->label('PDF-методичка для вчителя')->disk('public')->directory('lessons/guides')
                    ->acceptedFileTypes(['application/pdf'])->maxSize(51200)->downloadable()->openable()->columnSpanFull(),
            ]),
            Section::make('Ціна та урок')->columnSpan(1)->schema([
                TextInput::make('base_price')->label('Ціна за 100 запусків, ₴')->numeric()->minValue(0)->required()->default(0),
                TextInput::make('default_tickets_count')->label('Запусків у пакеті')->numeric()->minValue(1)->default(100)->required(),
                TextInput::make('grace_period_seconds')->label('Grace period, сек')->numeric()->minValue(0)->default(90)->required()
                    ->helperText('Перезапуск учнем протягом цього часу не списує новий запуск.'),
                TextInput::make('max_session_duration_minutes')->label('Тривалість уроку, хв')->numeric()->minValue(1)->default(45)->required(),
                TextInput::make('sort')->label('Порядок у каталозі')->numeric()->default(0),
                DateTimePicker::make('released_at')->label('Дата релізу')->seconds(false)
                    ->helperText('Уроки за останні 45 днів мають позначку NEW у кабінеті та на сайті.'),
                Toggle::make('is_active')->label('Активний (видно закладам і в окулярах)')->default(true),
            ]),
            Section::make('Медіа')->columnSpan(2)->columns(2)->schema([
                FileUpload::make('cover_image_url')->label('Постер для вебу (кабінет)')->image()->disk('public')->directory('lessons/covers')->imageEditor(),
                FileUpload::make('banner_vr_url')->label('Банер для 3D-меню в окулярах')->image()->disk('public')->directory('lessons/vr-banners')
                    ->helperText('Рекомендовано 2048×1024, PNG/JPG.'),
                TextInput::make('preview_video_url')->label('Відео-демонстрація досліду (URL)')->url()->columnSpanFull(),
            ]),
            Section::make('Поточна версія')->columnSpan(1)->visibleOn('edit')->schema([
                TextEntry::make('current_version_name')->label('Версія')->placeholder('ще не опубліковано')
                    ->formatStateUsing(fn ($state, VrLesson $record) => $state ? "{$state} ({$record->current_version_code})" : null),
                TextEntry::make('release_channel')->label('Канал')->badge(),
                TextEntry::make('apk_size_bytes')->label('APK')->formatStateUsing(fn ($state) => Fmt::bytes($state)),
                TextEntry::make('obb_size_bytes')->label('OBB')->formatStateUsing(fn ($state) => Fmt::bytes($state)),
                TextEntry::make('apk_sha256_hash')->label('SHA-256 APK')->fontFamily('mono')->size('xs')->copyable()->placeholder('—'),
            ]),
        ]);
    }
}
