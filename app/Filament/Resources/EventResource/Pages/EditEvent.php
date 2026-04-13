<?php

namespace App\Filament\Resources\EventResource\Pages;

use App\Filament\Resources\EventResource;
use App\Mail\RegoInvite;
use App\Models\Event;
use App\Models\Order;
use App\Models\OrderStatus;
use App\Models\User;
use Filament\Actions;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

class EditEvent extends EditRecord
{
    protected static string $resource = EventResource::class;

    protected function getHeaderActions(): array
    {
        /** @var Event $event */
        $event = $this->record;

        return [
            Actions\Action::make('view_event')
                ->label('View Event')
                ->icon('heroicon-o-eye')
                ->url(route('events.show', $event))
                ->openUrlInNewTab(),
            Actions\Action::make('export_orders')
                ->label('Export CSV')
                ->icon('heroicon-o-arrow-down-tray')
                ->action(function () use ($event) {
                    Artisan::call('app:export-orders', ['eventId' => $event->id]);

                    $filename = "exports/orders_{$event->id}.csv";

                    return response()->streamDownload(function () use ($filename) {
                        echo Storage::get($filename);
                    }, "orders_{$event->id}.csv", ['Content-Type' => 'text/csv']);
                }),
            Actions\Action::make('invite_users')
                ->label('Invite Users')
                ->icon('heroicon-o-envelope')
                ->form([
                    Select::make('user_ids')
                        ->label('Users')
                        ->multiple()
                        ->searchable()
                        ->optionsLimit(500)
                        ->allowHtml()
                        ->options(function () use ($event) {
                            $existingUserIds = Order::where('event_id', $event->id)
                                ->pluck('user_id');

                            return User::whereNotIn('id', $existingUserIds)
                                ->orderBy('name')
                                ->get()
                                ->mapWithKeys(fn ($user) => [
                                    $user->id => '<div>'
                                        .'<span class="font-medium">'.e($user->name).'</span>'
                                        .'<br><span class="text-xs text-gray-400">'
                                        .e($user->email)
                                        .($user->kennel ? ' · '.e($user->kennel) : '')
                                        .($user->nerd_name ? ' · '.e($user->nerd_name) : '')
                                        .'</span></div>',
                                ]);
                        })
                        ->required(),
                ])
                ->action(function (array $data) use ($event) {
                    $count = 0;

                    foreach ($data['user_ids'] as $userId) {
                        $user = User::findOrFail($userId);

                        $order = Order::create([
                            'user_id' => $user->id,
                            'event_id' => $event->id,
                            'status' => OrderStatus::Invited,
                        ]);

                        $quickLogin = $user->getQuickLogin($event->ends_at);
                        $eventUrl = route('events.show', $event);

                        Mail::to($user)->send(new RegoInvite($user, $event, url('/quicklogin/'.$quickLogin.'?action='.$eventUrl)));
                        $count++;
                    }

                    Notification::make()
                        ->title("Invited {$count} user(s)")
                        ->success()
                        ->send();
                }),
            Actions\DeleteAction::make(),
        ];
    }
}
