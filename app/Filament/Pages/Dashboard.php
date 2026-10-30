<?php

namespace App\Filament\Pages;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Support\Carbon;

class Dashboard extends BaseDashboard
{
    use HasFiltersForm;

    protected static ?string $title = 'Дашборд';

    public function filtersForm(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columnSpanFull()->columns(4)->schema([
                Select::make('period')->label('Період доходу й аналітики')->live()->default('month')->selectablePlaceholder(false)
                    ->options(['today' => 'Сьогодні', 'week' => '7 днів', 'month' => '30 днів', 'quarter' => '90 днів', 'year' => 'Рік', 'custom' => 'Свій період']),
                DatePicker::make('from')->label('З')->visible(fn (Get $get) => $get('period') === 'custom'),
                DatePicker::make('to')->label('По')->visible(fn (Get $get) => $get('period') === 'custom'),
            ]),
        ]);
    }

    /** @return array{0: Carbon, 1: Carbon} */
    public static function range(?array $filters): array
    {
        $to = now()->endOfDay();
        $from = match ($filters['period'] ?? 'month') {
            'today' => now()->startOfDay(),
            'week' => now()->subDays(6)->startOfDay(),
            'quarter' => now()->subDays(89)->startOfDay(),
            'year' => now()->subYear()->startOfDay(),
            'custom' => Carbon::parse($filters['from'] ?? now()->subDays(29))->startOfDay(),
            default => now()->subDays(29)->startOfDay(),
        };
        if (($filters['period'] ?? null) === 'custom' && ! empty($filters['to'])) {
            $to = Carbon::parse($filters['to'])->endOfDay();
        }

        return [$from, $to];
    }
}
