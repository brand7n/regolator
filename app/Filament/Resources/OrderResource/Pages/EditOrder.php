<?php

namespace App\Filament\Resources\OrderResource\Pages;

use App\Filament\Resources\OrderResource;
use App\Mail\PaymentConfirmation;
use App\Mail\RegoInvite;
use App\Models\Order;
use App\Models\OrderStatus;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class EditOrder extends EditRecord
{
    protected static string $resource = OrderResource::class;

    protected function getHeaderActions(): array
    {
        /** @var Order $order */
        $order = $this->record;

        return [
            Actions\Action::make('block')
                ->label('Block User')
                ->icon('heroicon-o-no-symbol')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('Block this user?')
                ->modalDescription('This will prevent the user from being invited to this event.')
                ->visible(fn () => $order->status !== OrderStatus::Blocked)
                ->action(function () use ($order) {
                    $order->status = OrderStatus::Blocked;
                    $order->save();
                    $this->refreshFormData(['status']);
                }),
            Actions\Action::make('unblock')
                ->label('Unblock User')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Unblock this user?')
                ->modalDescription('This will allow the user to be invited to this event again.')
                ->visible(fn () => $order->status === OrderStatus::Blocked)
                ->action(function () use ($order) {
                    $order->status = OrderStatus::Waitlisted;
                    $order->save();
                    $this->refreshFormData(['status']);
                }),
            Actions\Action::make('resend_invite')
                ->label('Resend Invite')
                ->icon('heroicon-o-envelope')
                ->requiresConfirmation()
                ->visible(fn () => $order->status === OrderStatus::Invited)
                ->action(function () use ($order) {
                    try {
                        $user = $order->user;
                        $event = $order->event;
                        $quickLogin = $user->getQuickLogin($event->ends_at);
                        $eventUrl = route('events.show', $event);

                        Mail::to($user)->send(new RegoInvite($user, $event, url('/quicklogin/'.$quickLogin.'?action='.$eventUrl)));
                        Notification::make()->title('Invite sent to '.$user->email)->success()->send();
                    } catch (\Throwable $e) {
                        Log::error('failed to resend invite', ['order_id' => $order->id, 'error' => $e->getMessage()]);
                        Notification::make()->title('Failed to send invite')->danger()->send();
                    }
                }),
            Actions\Action::make('resend_payment')
                ->label('Resend Payment Confirmation')
                ->icon('heroicon-o-envelope')
                ->requiresConfirmation()
                ->visible(fn () => $order->status === OrderStatus::PaymentVerified)
                ->action(function () use ($order) {
                    try {
                        $user = $order->user;
                        $event = $order->event;
                        $quickLogin = $user->getQuickLogin($event->ends_at);
                        $eventUrl = route('events.show', $event);

                        Mail::to($user)->send(new PaymentConfirmation($user, $event, url('/quicklogin/'.$quickLogin.'?action='.$eventUrl)));
                        Notification::make()->title('Payment confirmation sent to '.$user->email)->success()->send();
                    } catch (\Throwable $e) {
                        Log::error('failed to resend payment confirmation', ['order_id' => $order->id, 'error' => $e->getMessage()]);
                        Notification::make()->title('Failed to send confirmation')->danger()->send();
                    }
                }),
            Actions\DeleteAction::make(),
        ];
    }

    protected function afterSave(): void
    {
        /** @var Order $order */
        $order = $this->record;

        if ($order->status === OrderStatus::Invited && $order->wasChanged('status')) {
            try {
                $user = $order->user;
                $event = $order->event;
                $quickLogin = $user->getQuickLogin($event->ends_at);
                $eventUrl = route('events.show', $event);

                Mail::to($user)->send(new RegoInvite($user, $event, url('/quicklogin/'.$quickLogin.'?action='.$eventUrl)));
            } catch (\Throwable $e) {
                Log::error('failed to send invite on order edit', [
                    'order_id' => $order->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
