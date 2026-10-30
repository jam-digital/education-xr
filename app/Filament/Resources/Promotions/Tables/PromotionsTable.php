<?php

namespace App\Filament\Resources\Promotions\Tables;

use App\Enums\PromoType;
use App\Filament\Resources\Promotions\PromotionResource;
use App\Models\Promotion;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class PromotionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('title')->label('Акція')->weight('bold')->searchable()->wrap()
                    ->description(fn (Promotion $r) => $r->batch_label ? "Партія: {$r->batch_label}" : null),
                TextColumn::make('promo_type')->label('Тип')->badge(),
                TextColumn::make('promo_code_str')->label('Код')->fontFamily('mono')->copyable()->searchable()->placeholder('—'),
                TextColumn::make('price')->label('Ціна')->money(fn (Promotion $r) => $r->currency?->value ?? 'UAH')->placeholder('—'),
                TextColumn::make('benefit')->label('Дає')->state(fn (Promotion $r) => collect([
                    $r->grants_tickets_count ? "{$r->grants_tickets_count} запусків" : null,
                    $r->grants_duration_days ? "{$r->grants_duration_days} дн. безліміту" : null,
                    $r->discount_percent ? "−{$r->discount_percent}%" : null,
                ])->filter()->implode(', ') ?: '—')
                    ->description(fn (Promotion $r) => $r->coversAllLessons() ? 'усі уроки' : count($r->included_lesson_ids).' дод.'),
                TextColumn::make('redemptions_count')->label('Використано')
                    ->formatStateUsing(fn ($state, Promotion $r) => $state.($r->max_redemptions ? " / {$r->max_redemptions}" : '')),
                TextColumn::make('expires_at')->label('Діє до')->dateTime('d.m.Y')->placeholder('безстроково')->sortable()
                    ->color(fn (Promotion $r) => $r->expires_at?->isPast() ? 'danger' : null),
                ToggleColumn::make('is_active')->label('Активна')->disabled(fn () => ! PromotionResource::canManage()),
            ])
            ->filters([
                SelectFilter::make('promo_type')->label('Тип')->options(PromoType::class),
                SelectFilter::make('batch_label')->label('Партія промокодів')
                    ->options(fn () => Promotion::whereNotNull('batch_label')->distinct()->pluck('batch_label', 'batch_label')),
                TernaryFilter::make('is_active')->label('Активна'),
            ])
            ->recordActions([EditAction::make()])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }
}
