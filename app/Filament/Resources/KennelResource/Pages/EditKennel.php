<?php

namespace App\Filament\Resources\KennelResource\Pages;

use App\Filament\Resources\KennelResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditKennel extends EditRecord
{
    protected static string $resource = KennelResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
