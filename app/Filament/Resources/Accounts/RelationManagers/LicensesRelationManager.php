<?php

namespace App\Filament\Resources\Accounts\RelationManagers;

use App\Enums\LicenseStatus;
use App\Enums\LicenseType;
use App\Filament\Resources\Accounts\AccountResource;
use App\Filament\Support\AccessForm;
use App\Models\Device;
use App\Models\LessonLicense;
use App\Services\LicenseService;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Throwable;

class LicensesRelationManager extends RelationManager
{
    protected static bool $isLazy = false;

    protected static string $relationship = 'licenses';

    protected static ?string $title = 'Доступи до VR-уроків';

    protected static string|\BackedEnum|null $icon = Heroicon::OutlinedKey;

    public static function getBadge(Model $ownerRecord, string $pageClass): ?string
    {
        return (string) $ownerRecord->licenses()->where('status', 'active')->count();
    }

    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        $manage = fn () => AccountResource::canManage();

        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('lesson', 'user'))
            ->defaultSort('updated_at', 'desc')
            ->columns([
                TextColumn::make('lesson.title')->label('Урок')->weight('bold'),
                TextColumn::make('license_type')->label('Тип')->badge(),
                TextColumn::make('balance')->label('Залишок / термін')
                    ->state(fn (LessonLicense $r) => $r->balanceLabel())
                    ->color(fn (LessonLicense $r) => $r->license_type === LicenseType::TicketPool && $r->tickets_remaining < $r->user->low_balance_threshold ? 'danger' : null),
                TextColumn::make('tickets_consumed')->label('Використано')->numeric()
                    ->visible(fn () => true)->formatStateUsing(fn ($state, LessonLicense $r) => $r->license_type === LicenseType::TicketPool ? $state : '—'),
                TextColumn::make('allowed_device_ids')->label('Окуляри')
                    ->state(fn (LessonLicense $r) => in_array('ALL', $r->allowed_device_ids ?? ['ALL'], true)
                        ? 'усі' : Device::whereIn('id', $r->allowed_device_ids)->pluck('device_name')->implode(', ')),
                IconColumn::make('auto_renew')->label('Автопоповн.')->boolean(),
                TextColumn::make('status')->label('Статус')->badge()
                    ->state(fn (LessonLicense $r) => $r->status === LicenseStatus::Active && $r->valid_until?->isPast() ? LicenseStatus::Expired : $r->status),
                TextColumn::make('updated_at')->label('Змінено')->since()->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')->label('Статус')->options(LicenseStatus::class),
                SelectFilter::make('license_type')->label('Тип')->options(LicenseType::class),
            ])
            ->headerActions([
                Action::make('grant')->label('+ Надати доступ')->visible($manage)
                    ->modalHeading('Надати доступ до VR-уроків')->modalSubmitActionLabel('Надати доступ')
                    ->schema(fn () => AccessForm::fields($this->getOwnerRecord()))
                    ->action(function (array $data) {
                        try {
                            $n = AccessForm::apply($this->getOwnerRecord(), $data);
                            Notification::make()->title("Доступ надано ({$n})")->success()->send();
                        } catch (Throwable $e) {
                            Notification::make()->title($e->getMessage())->danger()->send();
                        }
                    }),
                Action::make('revokeAll')->label('Забрати всі')->color('danger')->outlined()->visible($manage)
                    ->requiresConfirmation()->modalHeading('Забрати доступ до всіх уроків?')
                    ->schema([Textarea::make('reason')->label('Причина')->required()])
                    ->action(function (array $data) {
                        $n = app(LicenseService::class)->revoke($this->getOwnerRecord()->licenses()->with('lesson', 'user')->where('status', '!=', 'revoked')->get(), $data['reason']);
                        Notification::make()->title("Відкликано: {$n}")->success()->send();
                    }),
            ])
            ->recordActions([ActionGroup::make([
                Action::make('adjust')->label('Коригувати баланс')->icon(Heroicon::OutlinedAdjustmentsHorizontal)
                    ->visible(fn (LessonLicense $r) => $manage() && $r->license_type === LicenseType::TicketPool && $r->status === LicenseStatus::Active)
                    ->modalHeading(fn (LessonLicense $r) => "Коригування запусків · {$r->lesson->title}")
                    ->modalDescription(fn (LessonLicense $r) => "Зараз: {$r->balanceLabel()}. Зміна записується в журнал безпеки.")
                    ->schema([
                        Radio::make('direction')->label('Дія')->inline()->options(['add' => 'Додати', 'sub' => 'Списати'])->default('add')->required(),
                        TextInput::make('amount')->label('Кількість запусків')->numeric()->minValue(1)->required(),
                        Textarea::make('reason')->label('Причина (обов’язково)')->required()->rows(2),
                    ])
                    ->action(function (LessonLicense $r, array $data) {
                        try {
                            $delta = (int) $data['amount'] * ($data['direction'] === 'sub' ? -1 : 1);
                            $r = app(LicenseService::class)->adjustBalance($r, $delta, $data['reason']);
                            Notification::make()->title("Баланс: {$r->balanceLabel()}")->success()->send();
                        } catch (Throwable $e) {
                            Notification::make()->title($e->getMessage())->danger()->send();
                        }
                    }),
                Action::make('revoke')->label('Забрати доступ')->icon(Heroicon::OutlinedNoSymbol)->color('danger')
                    ->visible(fn (LessonLicense $r) => $manage() && $r->status !== LicenseStatus::Revoked)
                    ->requiresConfirmation()->modalHeading(fn (LessonLicense $r) => "Забрати доступ до «{$r->lesson->title}»?")
                    ->schema([Textarea::make('reason')->label('Причина')->required()])
                    ->action(function (LessonLicense $r, array $data) {
                        app(LicenseService::class)->revoke([$r], $data['reason']);
                        Notification::make()->title('Доступ відкликано')->success()->send();
                    }),
                Action::make('restore')->label('Відновити')->icon(Heroicon::OutlinedArrowUturnLeft)->color('gray')
                    ->visible(fn (LessonLicense $r) => $manage() && $r->status === LicenseStatus::Revoked)
                    ->schema([Textarea::make('reason')->label('Причина')->required()])
                    ->action(fn (LessonLicense $r, array $data) => app(LicenseService::class)->restore($r, $data['reason'])),
            ])]);
    }
}
