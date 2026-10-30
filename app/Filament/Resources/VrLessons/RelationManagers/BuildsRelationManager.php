<?php

namespace App\Filament\Resources\VrLessons\RelationManagers;

use App\Enums\AuditAction;
use App\Enums\ReleaseChannel;
use App\Filament\Forms\BuildFileUpload;
use App\Filament\Resources\VrLessons\VrLessonResource;
use App\Filament\Support\Fmt;
use App\Jobs\HashBuild;
use App\Models\Device;
use App\Models\LessonBuild;
use App\Services\Audit;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/** Менеджер білдів: завантаження APK + OBB, SHA-256, канали Beta → Production */
class BuildsRelationManager extends RelationManager
{
    protected static bool $isLazy = false;

    protected static string $relationship = 'builds';

    protected static ?string $title = 'Білди APK / OBB';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        $manage = fn () => VrLessonResource::canManage();

        return $table
            ->defaultSort('version_code', 'desc')
            ->poll(fn () => $this->getOwnerRecord()->builds()->where('hash_status', 'pending')->exists() ? '5s' : null)
            ->columns([
                TextColumn::make('version_name')->label('Версія')->weight('bold')
                    ->description(fn (LessonBuild $r) => "code {$r->version_code}"),
                TextColumn::make('channel')->label('Канал')->badge(),
                TextColumn::make('apk_size_bytes')->label('APK')->formatStateUsing(fn ($state) => Fmt::bytes($state)),
                TextColumn::make('obb_size_bytes')->label('OBB')->formatStateUsing(fn ($state) => Fmt::bytes($state))->placeholder('—'),
                TextColumn::make('apk_sha256_hash')->label('SHA-256')->fontFamily('mono')->limit(16)->copyable()
                    ->placeholder(fn (LessonBuild $r) => $r->hash_status === 'failed' ? 'помилка' : 'рахується…'),
                TextColumn::make('beta_device_ids')->label('Тестові окуляри')
                    ->state(fn (LessonBuild $r) => $r->channel === ReleaseChannel::Beta
                        ? (Device::whereIn('id', $r->beta_device_ids ?? [])->pluck('device_name')->implode(', ') ?: 'не обрано') : 'усі'),
                TextColumn::make('published_at')->label('Опубліковано')->dateTime('d.m.Y H:i')->placeholder('—'),
                TextColumn::make('uploadedBy.contact_person_name')->label('Завантажив')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->headerActions([
                Action::make('upload')->label('Завантажити білд')->icon(Heroicon::OutlinedArrowUpTray)->visible($manage)
                    ->modalWidth('3xl')->modalHeading('Новий білд')->modalSubmitActionLabel('Зберегти білд')
                    ->schema(fn () => [
                        TextInput::make('version_name')->label('Версія (version_name)')->placeholder('1.4.2')->required(),
                        TextInput::make('version_code')->label('version_code')->numeric()->required()->minValue(1)
                            ->default(fn () => (int) $this->getOwnerRecord()->builds()->max('version_code') + 1)
                            ->rule(fn () => 'unique:lesson_builds,version_code,NULL,id,lesson_id,'.$this->getOwnerRecord()->id),
                        BuildFileUpload::make('apk')->label('APK')->kind('apk')->appId(fn () => $this->getOwnerRecord()->id)->required(),
                        BuildFileUpload::make('obb')->label('OBB (кеш, якщо є)')->kind('obb')->appId(fn () => $this->getOwnerRecord()->id),
                        Radio::make('channel')->label('Канал релізу')->options(ReleaseChannel::class)->default(ReleaseChannel::Beta->value)->live()->required()
                            ->helperText('Beta: версія спершу йде лише на тестові окуляри. Потім «Опублікувати для всіх».'),
                        Select::make('beta_device_ids')->label('Тестові окуляри')->multiple()->searchable()
                            ->options(fn () => Device::where('status', 'active')->with('user')->get()
                                ->mapWithKeys(fn (Device $d) => [$d->id => "{$d->device_name} · {$d->user?->display_name}"]))
                            ->visible(fn (Get $get) => $get('channel') === ReleaseChannel::Beta->value),
                        Textarea::make('release_notes')->label('Що нового')->rows(2),
                    ])
                    ->action(function (array $data) {
                        $build = $this->getOwnerRecord()->builds()->create([
                            'version_code' => $data['version_code'],
                            'version_name' => $data['version_name'],
                            'channel' => $data['channel'],
                            'apk_storage_path' => $data['apk']['path'] ?? null,
                            'apk_size_bytes' => $data['apk']['size'] ?? null,
                            'obb_storage_path' => $data['obb']['path'] ?? null,
                            'obb_size_bytes' => $data['obb']['size'] ?? null,
                            'beta_device_ids' => $data['beta_device_ids'] ?? [],
                            'release_notes' => $data['release_notes'] ?? null,
                            'uploaded_by_admin_id' => auth()->id(),
                        ]);
                        HashBuild::dispatch($build);
                        if ($build->channel === ReleaseChannel::Production) {
                            $this->publish($build);
                        }
                        Notification::make()->title("Білд {$build->version_name} збережено")->body('SHA-256 рахується у фоні.')->success()->send();
                    }),
            ])
            ->recordActions([
                Action::make('publish')->label('Опублікувати для всіх')->icon(Heroicon::OutlinedRocketLaunch)->color('success')
                    ->visible(fn (LessonBuild $r) => $manage() && ! ($r->channel === ReleaseChannel::Production && $r->published_at))
                    ->requiresConfirmation()
                    ->modalDescription(fn (LessonBuild $r) => "Версія {$r->version_name} стане поточною на всіх окулярах закладів з доступом до уроку.")
                    ->action(fn (LessonBuild $r) => $this->publish($r)),
                ActionGroup::make([
                    Action::make('beta')->label('Тестові окуляри')->icon(Heroicon::OutlinedBeaker)->color('gray')
                        ->visible(fn (LessonBuild $r) => $manage() && $r->channel === ReleaseChannel::Beta)
                        ->fillForm(fn (LessonBuild $r) => ['beta_device_ids' => $r->beta_device_ids])
                        ->schema([
                            Select::make('beta_device_ids')->label('Окуляри для бета-версії')->multiple()->searchable()
                                ->options(fn () => Device::where('status', 'active')->with('user')->get()
                                    ->mapWithKeys(fn (Device $d) => [$d->id => "{$d->device_name} · {$d->user?->display_name}"])),
                        ])
                        ->action(fn (LessonBuild $r, array $data) => $r->update(['beta_device_ids' => $data['beta_device_ids'] ?? []])),
                    DeleteAction::make()->visible(fn (LessonBuild $r) => $manage() && ! $r->published_at),
                ]),
            ]);
    }

    private function publish(LessonBuild $build): void
    {
        $lesson = $this->getOwnerRecord();
        $old = $lesson->only(['current_version_name', 'current_version_code']);
        $build->update(['channel' => ReleaseChannel::Production, 'published_at' => now()]);
        $lesson->update([
            'current_version_code' => $build->version_code,
            'current_version_name' => $build->version_name,
            'release_channel' => ReleaseChannel::Production,
            'apk_storage_path' => $build->apk_storage_path,
            'apk_size_bytes' => $build->apk_size_bytes,
            'apk_sha256_hash' => $build->apk_sha256_hash,
            'obb_storage_path' => $build->obb_storage_path,
            'obb_size_bytes' => $build->obb_size_bytes,
            'obb_sha256_hash' => $build->obb_sha256_hash,
        ]);
        Audit::log(AuditAction::BuildPublish, old: $old + ['lesson' => $lesson->title],
            new: ['current_version_name' => $build->version_name, 'current_version_code' => $build->version_code]);
        Notification::make()->title("{$lesson->title} {$build->version_name} опубліковано для всіх")->success()->send();
    }
}
