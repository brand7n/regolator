<?php

namespace App\Filament\Resources\KennelResource\Pages;

use App\Filament\Resources\KennelResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListKennels extends ListRecords
{
    protected static string $resource = KennelResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
