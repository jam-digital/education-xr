<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Accounts\AccountResource;
use App\Models\Device;
use App\Models\LessonLicense;
use Filament\Widgets\Widget;

/**
 * Розумні сповіщення (ТЗ 3.1): мало квитків, підписка/тріал спливає за 3 дні,
 * окуляри з підозрілою активністю (відкат годинника або офлайн понад 5 днів).
 */
class SmartAlerts extends Widget
{
    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.widgets.smart-alerts';

    protected function getViewData(): array
    {
        $low = LessonLicense::active()->where('license_type', 'ticket_pool')
            ->where('tickets_remaining', '<', config('vrosvita.alerts.low_tickets'))
            ->orderBy('tickets_remaining')->with('user', 'lesson')->limit(12)->get();

        $expiring = LessonLicense::active()->where('license_type', '!=', 'ticket_pool')
            ->whereBetween('valid_until', [now(), now()->addDays(config('vrosvita.alerts.expiring_days'))])
            ->orderBy('valid_until')->with('user', 'lesson')->limit(12)->get();

        $suspicious = Device::where('status', 'active')
            ->where(fn ($query) => $query->whereNotNull('clock_tamper_detected_at')
                ->orWhere('last_sync_at', '<', now()->subDays(Device::STALE_OFFLINE_DAYS)))
            ->with('user')->orderByDesc('clock_tamper_detected_at')->orderBy('last_sync_at')->limit(12)->get();

        return [
            'low' => $low,
            'expiring' => $expiring,
            'suspicious' => $suspicious,
            'url' => fn ($user) => $user ? AccountResource::getUrl('view', ['record' => $user]) : '#',
        ];
    }
}
