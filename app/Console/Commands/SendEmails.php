<?php

namespace App\Console\Commands;

use App\Mail\PaymentConfirmation;
use App\Mail\RegoInvite;
use App\Mail\RegoReminder;
use App\Models\Event;
use App\Models\Order;
use App\Models\OrderStatus;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendEmails extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:send-emails {eventId}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sends an email to the users';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $eventId = $this->argument('eventId');
        $event = Event::findOrFail($eventId);
        $users = User::whereHas('orders', function ($query) use ($eventId) {
            $query->where('event_id', $eventId)
                ->where('status', 'PAYMENT_VERIFIED');
        })
            ->orderBy('name')
            ->get();

        foreach ($users as $user) {
            $quick_login = $user->getQuickLogin($event->ends_at);
            $eventUrl = route('events.show', $event);

            $order = Order::where('user_id', $user->id)->where('event_id', $eventId)->first();
            // if (!$order) {
            //     $this->info('Creating order');
            //     $order = new Order;
            //     $order->user_id = $user->id;
            //     $order->event_id = 1;
            //     $order->status = OrderStatus::Invited;
            //     $order->save();
            // }

            if ($order->status == OrderStatus::Cancelled) {
                $this->error('User has already been cancelled');

                return;
            }

            // if ($order->status == OrderStatus::PaymentVerified) {
            //     $this->info('User has already paid up, sending payment verification to ' . $user->name);
            //     try {
            //         Mail::to($user)->send(new PaymentConfirmation($user, $event, url('/quicklogin/' . $quick_login . '?action=' . $eventUrl)));
            //         activity()
            //             ->causedBy(auth()->user() ?? null)
            //             ->performedOn($user)
            //             ->withProperties([
            //                 'event' => $event,
            //                 'order' => $order,
            //             ])
            //             ->log('sent payment verification');
            //     } catch (\Throwable $t) {
            //         Log::error("failed to send email", [
            //             'user' => $user,
            //             'error' => $t,
            //         ]);
            //     }
            //     return;
            // }

            //     $this->info('Sending invite to ' . $user->name);
            //     try {
            //         Mail::to($user)->send(new RegoInvite($user, $event, url('/quicklogin/' . $quick_login . '?action=' . $eventUrl)));
            //         activity()
            //             ->causedBy(auth()->user() ?? null)
            //             ->performedOn($user)
            //             ->withProperties([
            //                 'event' => $event,
            //                 'order' => $order,
            //             ])
            //             ->log('sent rego invite');
            //     } catch (\Throwable $t) {
            //         Log::error("failed to send email", [
            //             'user' => $user,
            //             'error' => $t,
            //         ]);
            //     }
            // }

            // public function send_reminder()
            // {
            $this->info('Sending reminder to '.$user->name);
            try {
                Mail::to($user)->send(new RegoReminder($user, url('/quicklogin/'.$quick_login.'?action='.$eventUrl)));
                activity()
                    ->causedBy(auth()->user() ?? null)
                    ->performedOn($user)
                    ->withProperties([
                        'event' => $event,
                        'order' => $order,
                    ])
                    ->log('sent rego reminder');
            } catch (\Throwable $t) {
                Log::error('failed to send email', [
                    'user' => $user,
                    'error' => $t,
                ]);
            }
            sleep(1);
        }
    }
}
