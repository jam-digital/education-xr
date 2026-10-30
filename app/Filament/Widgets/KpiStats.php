<?php

namespace App\Filament\Widgets;

use App\Enums\PaymentStatus;
use App\Enums\UserStatus;
use App\Filament\Pages\Dashboard;
use App\Models\Device;
use App\Models\Payment;
use App\Models\TicketLog;
use App\Models\User;
use App\Services\Money;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/** Картки KPI (ТЗ 3.1) */
class KpiStats extends StatsOverviewWidget
{
    use InteractsWithPageFilters;

    protected static ?int $sort = 1;

    protected ?string $pollingInterval = '60s';

    protected function getColumns(): int
    {
        return 4;
    }

    protected function getStats(): array
    {
        [$from, $to] = Dashboard::range($this->pageFilters);

        $clients = User::clients()->where('status', UserStatus::Active->value)
            ->whereHas('licenses', fn ($query) => $query->where('status', 'active'))->count();
        $allClients = User::clients()->count();

        $devices = Device::where('status', 'active')->count();
        $online = Device::where('status', 'active')->where('last_sync_at', '>', now()->subMinutes(Device::ONLINE_MINUTES))->count();

        $revenue = (float) Payment::where('status', PaymentStatus::Completed->value)->where('currency', 'UAH')
            ->whereBetween('paid_at', [$from, $to])->sum('amount');
        $daily = collect(range(13, 0))->map(fn ($d) => (float) Payment::where('status', 'completed')
            ->whereDate('paid_at', now()->subDays($d)->toDateString())->sum('amount'))->all();

        // Проведений урок = сесія онлайн або синхронізована з офлайну (перезапуски в grace period не рахуються)
        $lessons = fn () => TicketLog::whereIn('mode', ['online_realtime', 'offline_cached_sync'])->whereIn('status', ['active', 'completed']);
        $used = fn ($since) => $lessons()->where('started_at', '>=', $since)->count();
        $usedSeries = collect(range(13, 0))->map(fn ($d) => $lessons()->whereDate('started_at', now()->subDays($d)->toDateString())->count())->all();

        return [
            Stat::make('Активні заклади освіти', $clients)->description("з {$allClients} акаунтів")->icon('heroicon-o-building-office-2'),
            Stat::make('Шкільні VR-окуляри онлайн / всього', "{$online} / {$devices}")
                ->description('онлайн = синхронізація за '.Device::ONLINE_MINUTES.' хв')->icon('heroicon-o-eye')
                ->color($online ? 'success' : 'gray'),
            Stat::make('Дохід за період', Money::format($revenue))
                ->description($from->format('d.m.Y').' — '.$to->format('d.m.Y'))->icon('heroicon-o-banknotes')
                ->chart($daily)->color('primary'),
            Stat::make('Проведено VR-уроків', $used(now()->startOfDay()).' сьогодні')
                ->description($used(now()->subDays(6)->startOfDay()).' за тиждень · '.$used(now()->subDays(29)->startOfDay()).' за місяць')
                ->icon('heroicon-o-academic-cap')->chart($usedSeries)->color('warning'),
        ];
    }
}
