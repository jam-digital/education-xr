<?php

namespace App\Filament\Resources\Leads\Tables;

use App\Filament\Resources\Leads\LeadResource;
use App\Models\Lead;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class LeadsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')->label('Дата')->dateTime('d.m.Y H:i')->sortable(),
                TextColumn::make('name')->label('Ім’я')->searchable()->weight('semibold'),
                TextColumn::make('email')->label('Email')->searchable()->copyable(),
                TextColumn::make('phone')->label('Телефон')->placeholder('—'),
                TextColumn::make('company')->label('Організація')->searchable()->placeholder('—'),
                TextColumn::make('message')->label('Повідомлення')->limit(60)->tooltip(fn (Lead $record) => $record->message)->placeholder('—')->wrap(),
                IconColumn::make('is_processed')->label('Опрацьовано')->boolean(),
            ])
            ->filters([
                TernaryFilter::make('is_processed')->label('Опрацьовано')->default(false),
            ])
            ->recordActions([
                Action::make('done')->label('Опрацьовано')->icon('heroicon-o-check')->color('success')
                    ->visible(fn (Lead $record) => ! $record->is_processed && LeadResource::canManage())
                    ->action(fn (Lead $record) => $record->update(['is_processed' => true])),
                DeleteAction::make()->visible(fn () => LeadResource::canManage()),
            ]);
    }
}
