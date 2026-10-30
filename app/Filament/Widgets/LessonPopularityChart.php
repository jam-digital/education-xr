<?php

namespace App\Filament\Widgets;

use App\Filament\Pages\Dashboard;
use App\Models\TicketLog;
use App\Models\VrLesson;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

/** Популярність VR-уроків: кількість сесій і середній час (ТЗ 3.1) */
class LessonPopularityChart extends ChartWidget
{
    use InteractsWithPageFilters;

    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 'full';

    protected ?string $heading = 'Популярність VR-уроків';

    protected ?string $maxHeight = '320px';

    public function getDescription(): ?string
    {
        [$from, $to] = Dashboard::range($this->pageFilters);

        return 'Проведені уроки та середня тривалість, '.$from->format('d.m').' — '.$to->format('d.m.Y');
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        [$from, $to] = Dashboard::range($this->pageFilters);
        $stats = TicketLog::query()->whereBetween('started_at', [$from, $to])
            ->whereIn('mode', ['online_realtime', 'offline_cached_sync'])
            ->selectRaw('lesson_id, count(*) as sessions, avg(session_duration_sec) as avg_sec')
            ->groupBy('lesson_id')->get()->keyBy('lesson_id');
        $lessons = VrLesson::orderBy('sort')->get()
            ->sortByDesc(fn ($a) => $stats[$a->id]->sessions ?? 0)->values();

        return [
            'datasets' => [
                [
                    'label' => 'Проведено уроків',
                    'data' => $lessons->map(fn ($a) => (int) ($stats[$a->id]->sessions ?? 0))->all(),
                    'backgroundColor' => '#8b5cf6',
                    'borderRadius' => 4,
                    'yAxisID' => 'y',
                ],
                [
                    'label' => 'Сер. час, хв',
                    'data' => $lessons->map(fn ($a) => round(($stats[$a->id]->avg_sec ?? 0) / 60, 1))->all(),
                    'backgroundColor' => '#f59e0b',
                    'borderRadius' => 4,
                    'yAxisID' => 'y1',
                ],
            ],
            'labels' => $lessons->map(fn (VrLesson $l) => $l->subject_category->getLabel().': '.preg_replace('/^[^:]+:\s*/u', '', $l->title))->all(),
        ];
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => ['legend' => ['display' => true, 'position' => 'bottom']],
            'scales' => [
                'y' => ['beginAtZero' => true, 'position' => 'left', 'title' => ['display' => true, 'text' => 'сесії']],
                'y1' => ['beginAtZero' => true, 'position' => 'right', 'grid' => ['drawOnChartArea' => false], 'title' => ['display' => true, 'text' => 'хв']],
            ],
        ];
    }
}
