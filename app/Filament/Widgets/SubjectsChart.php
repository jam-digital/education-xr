<?php

namespace App\Filament\Widgets;

use App\Enums\Subject;
use App\Filament\Pages\Dashboard;
use App\Models\TicketLog;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

/** Запуски в розрізі шкільних предметів (ТЗ 3.1) */
class SubjectsChart extends ChartWidget
{
    use InteractsWithPageFilters;

    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    protected ?string $heading = 'Уроки за предметами';

    protected ?string $maxHeight = '280px';

    public function getDescription(): ?string
    {
        [$from, $to] = Dashboard::range($this->pageFilters);

        return 'Проведені VR-уроки, '.$from->format('d.m').' — '.$to->format('d.m.Y');
    }

    protected function getType(): string
    {
        return 'doughnut';
    }

    protected function getData(): array
    {
        [$from, $to] = Dashboard::range($this->pageFilters);
        $counts = TicketLog::query()->join('vr_lessons', 'vr_lessons.id', '=', 'ticket_logs.lesson_id')
            ->whereBetween('ticket_logs.started_at', [$from, $to])
            ->whereIn('ticket_logs.mode', ['online_realtime', 'offline_cached_sync'])
            ->selectRaw('vr_lessons.subject_category as subject, count(*) as sessions')
            ->groupBy('vr_lessons.subject_category')->pluck('sessions', 'subject');
        $subjects = collect(Subject::cases())->filter(fn (Subject $s) => ($counts[$s->value] ?? 0) > 0)->values();

        return [
            'datasets' => [[
                'label' => 'Уроків',
                'data' => $subjects->map(fn (Subject $s) => (int) $counts[$s->value])->all(),
                'backgroundColor' => $subjects->map(fn (Subject $s) => $s->hex())->all(),
                'borderWidth' => 0,
            ]],
            'labels' => $subjects->map(fn (Subject $s) => $s->getLabel())->all(),
        ];
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => ['legend' => ['display' => true, 'position' => 'bottom']],
            'scales' => ['x' => ['display' => false], 'y' => ['display' => false]],
        ];
    }
}
