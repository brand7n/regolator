<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use App\Mail\{RegoReminder, RegoInvite, PaymentConfirmation};
use App\Models\{Event, Order, OrderStatus};

class SendEmails extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:send-emails {email}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sends an invite email to the user';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        /** @var User $user */
        $user = User::where('email', $this->argument('email'))->first();
        if (!$user) {
            $this->error('User not found');
            return;
        }
        $quick_login = $user->getQuickLogin();

        $order = Order::where('user_id', $user->id)->where('event_id', 1)->first();
        if (!$order) {
            $this->info('Creating order');
            $order = new Order;
            $order->user_id = $user->id;
            $order->event_id = 1;
            $order->status = OrderStatus::Invited;
            $order->save();
        }

        if ($order->status == OrderStatus::Cancelled) {
            $this->error('User has already been cancelled');
            return;
        }

        if ($order->status == OrderStatus::PaymentVerified) {
            $this->info('User has already paid up, sending payment verification to ' . $user->name);
            try {
                Mail::to($user)->send(new PaymentConfirmation($user, url('/quicklogin/' . $quick_login)));
                activity()
                    ->causedBy(auth()->user() ?? null)
                    ->performedOn($user)
                    ->withProperties([
                        'event' => Event::findOrFail(1),
                        'order' => $order,
                    ])
                    ->log('sent payment verification');
            } catch (\Throwable $t) {
                Log::error("failed to send email", [
                    'user' => $user,
                    'error' => $t,
                ]);               
            }
	          return;
	      }

        $this->info('Sending invite to ' . $user->name);
        try {
            Mail::to($user)->send(new RegoInvite($user, url('/quicklogin/' . $quick_login)));
            activity()
                ->causedBy(auth()->user() ?? null)
                ->performedOn($user)
                ->withProperties([
                    'event' => Event::findOrFail(1),
                    'order' => $order,
                ])
                ->log('sent rego invite');
        } catch (\Throwable $t) {
            Log::error("failed to send email", [
                'user' => $user,
                'error' => $t,
            ]);               
        }
    }
}
