<?php

namespace App\Filament\Resources\TicketLogs\Pages;

use App\Filament\Resources\TicketLogs\TicketLogResource;
use Filament\Resources\Pages\ListRecords;

class ListTicketLogs extends ListRecords
{
    protected static string $resource = TicketLogResource::class;
}
