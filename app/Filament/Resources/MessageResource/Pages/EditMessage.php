<?php

namespace App\Filament\Resources\MessageResource\Pages;

use App\Filament\Resources\MessageResource;
use App\Jobs\SendEventMessage;
use App\Mail\EventMessage;
use App\Models\Message;
use App\Models\MessageRecipient;
use App\Models\MessageRecipientStatus;
use App\Models\MessageStatus;
use App\Models\Order;
use Filament\Actions;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\HtmlString;

class EditMessage extends EditRecord
{
    protected static string $resource = MessageResource::class;

    protected function getHeaderActions(): array
    {
        /** @var Message $message */
        $message = $this->record;

        return [
            Actions\Action::make('preview')
                ->label('Preview Email')
                ->icon('heroicon-o-eye')
                ->modalContent(function () use ($message) {
                    $orders = $message->resolveRecipients();
                    if ($orders->isEmpty()) {
                        return new HtmlString('<p class="p-4 text-gray-500">No matching recipients found.</p>');
                    }
                    $sampleOrder = $orders->first();
                    $user = $sampleOrder->user;
                    $event = $message->event;
                    $quickLogin = $user->getQuickLogin($event->ends_at);
                    $eventUrl = route('events.show', $event);
                    $url = url('/quicklogin/'.$quickLogin.'?action='.$eventUrl);

                    $mailable = new EventMessage($message, $user, $sampleOrder, $url);

                    return new HtmlString($mailable->render());
                })
                ->modalSubmitAction(false)
                ->visible(fn () => $message->status === MessageStatus::Draft),

            Actions\Action::make('send_test')
                ->label('Send Test')
                ->icon('heroicon-o-beaker')
                ->color('gray')
                ->form([
                    Select::make('user_ids')
                        ->label('Test Recipients')
                        ->multiple()
                        ->searchable()
                        ->optionsLimit(500)
                        ->allowHtml()
                        ->options(function () use ($message) {
                            return Order::where('event_id', $message->event_id)
                                ->with('user')
                                ->get()
                                ->mapWithKeys(fn (Order $order) => [
                                    $order->user_id => '<div>'
                                        .'<span class="font-medium">'.e($order->user->name).'</span>'
                                        .'<br><span class="text-xs text-gray-400">'.e($order->user->email).'</span>'
                                        .'</div>',
                                ]);
                        })
                        ->required(),
                ])
                ->action(function (array $data) use ($message) {
                    $event = $message->event;
                    $count = 0;
                    $failed = 0;

                    foreach ($data['user_ids'] as $userId) {
                        $order = Order::where('user_id', $userId)
                            ->where('event_id', $event->id)
                            ->first();

                        if (! $order) {
                            continue;
                        }

                        try {
                            $user = $order->user;
                            $quickLogin = $user->getQuickLogin($event->ends_at);
                            $eventUrl = route('events.show', $event);
                            $url = url('/quicklogin/'.$quickLogin.'?action='.$eventUrl);

                            Mail::to($user)->send(new EventMessage($message, $user, $order, $url));
                            $count++;
                        } catch (\Throwable $e) {
                            Log::error('failed to send test message', [
                                'message_id' => $message->id,
                                'user_id' => $userId,
                                'error' => $e->getMessage(),
                            ]);
                            $failed++;
                        }
                    }

                    $msg = "Sent test to {$count} user(s)";
                    if ($failed) {
                        $msg .= ", {$failed} failed";
                    }

                    Notification::make()
                        ->title($msg)
                        ->status($failed ? 'warning' : 'success')
                        ->send();
                })
                ->visible(fn () => $message->status === MessageStatus::Draft),

            Actions\Action::make('send')
                ->label('Send Message')
                ->icon('heroicon-o-paper-airplane')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Send this message?')
                ->modalDescription(function () use ($message) {
                    $count = $message->resolveRecipients()->count();

                    return "This will queue {$count} email(s) for delivery.";
                })
                ->visible(fn () => $message->status === MessageStatus::Draft && ! empty($message->recipient_filter))
                ->action(function () use ($message) {
                    $orders = $message->resolveRecipients();

                    if ($orders->isEmpty()) {
                        Notification::make()
                            ->title('No matching recipients found')
                            ->warning()
                            ->send();

                        return;
                    }

                    $message->status = MessageStatus::Sending;
                    $message->save();

                    $count = 0;
                    foreach ($orders as $order) {
                        $recipient = MessageRecipient::create([
                            'message_id' => $message->id,
                            'user_id' => $order->user_id,
                            'order_id' => $order->id,
                            'status' => MessageRecipientStatus::Queued,
                        ]);

                        SendEventMessage::dispatch($message, $recipient);
                        $count++;
                    }

                    Notification::make()
                        ->title("Queued {$count} email(s) for delivery")
                        ->success()
                        ->send();

                    $this->refreshFormData(['status']);
                }),

            Actions\Action::make('resend_failed')
                ->label('Resend Failed')
                ->icon('heroicon-o-arrow-path')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Resend failed emails?')
                ->modalDescription(function () use ($message) {
                    return "This will retry {$message->failed_count} failed recipient(s).";
                })
                ->visible(fn () => $message->failed_count > 0)
                ->action(function () use ($message) {
                    $failedRecipients = $message->recipients()
                        ->where('status', MessageRecipientStatus::Failed)
                        ->get();

                    $message->status = MessageStatus::Sending;
                    $message->save();

                    $count = 0;
                    /** @var MessageRecipient $recipient */
                    foreach ($failedRecipients as $recipient) {
                        $recipient->status = MessageRecipientStatus::Queued;
                        $recipient->error = null;
                        $recipient->save();

                        SendEventMessage::dispatch($message, $recipient);
                        $count++;
                    }

                    Notification::make()
                        ->title("Retrying {$count} failed email(s)")
                        ->success()
                        ->send();

                    $this->refreshFormData(['status']);
                }),

            Actions\DeleteAction::make()
                ->visible(fn () => $message->status === MessageStatus::Draft),
        ];
    }
}
