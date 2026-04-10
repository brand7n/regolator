<?php

namespace App\Filament\Resources\OrderResource\Pages;

use App\Filament\Resources\OrderResource;
use App\Mail\RegoInvite;
use App\Models\OrderStatus;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Mail;

class EditOrder extends EditRecord
{
    protected static string $resource = OrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function afterSave(): void
    {
        $order = $this->record;

        if ($order->status === OrderStatus::Invited && $order->wasChanged('status')) {
            $user = $order->user;
            $event = $order->event;
            $quickLogin = $user->getQuickLogin();
            $eventUrl = route('events.show', $event);

            Mail::to($user)->send(new RegoInvite($user, $event, url('/quicklogin/' . $quickLogin . '?action=' . $eventUrl)));
        }
    }
}
