<?php

namespace App\Livewire;

use Illuminate\Support\Facades\Log;
use Livewire\Component;
use GuzzleHttp\Client;
use Auth;
use Illuminate\Support\Carbon;
use App\Models\{User, Order, OrderStatus, Event};
use Livewire\Attributes\On;

class Paypal extends Component
{
    public $key;
    public $price;
    public string $event_tag;
    public ?Carbon $rego_paid_at = null;
    public $terms_accepted = false;
    public $bonus_accepted = false;
    public $name;
    public string $sandbox;
    public ?Order $order;
    public ?Event $event = null;

    public function mount(int $eventId)
    {
        $this->key = config('services.paypal.client_id');
        $this->sandbox = config('services.paypal.sandbox');

        /** @var User $user */
        $user = Auth::user();
        $this->name = $user->name;

        $this->event = Event::findOrFail($eventId);

        Log::info('mount', ['event' => $this->event]);
    }

    #[On('order-updated')]
    public function handleUpdatedEvent()
    {
        Log::info('evented');
        $this->render();
    }

    public function render()
    {
        if (!$this->event) {
            return;
        }

        $this->price = $this->event->base_price_in_dollars;
        $this->event_tag = $this->event->event_tag;

        // TODO: derive from event options
        if ($this->bonus_accepted) {
            $this->price += 115;
            $this->event_tag .= '_PLUS_EH3_32NDANAL';
        }

        /** @var User $user */
        $user = Auth::user();
        $this->order = $this->event->getOrder($user);
        if ($this->order && $this->order->status === OrderStatus::PaymentVerified) {
            $this->rego_paid_at = $this->order->verified_at;
            Log::info('payment verified: ', ['rego_paid_at' => $this->rego_paid_at]);
        } else {
            Log::info('payment not verified: ', ['order' => $this->order]);
        }
        $this->rego_paid_at = $this->event->regoPaidAt($user);
        // TODO: check for pending status to prevent user from paying again?

        Log::info('render', ['price' => $this->price, 'rego_paid_at' => $this->rego_paid_at]);
        return view('livewire.paypal');
    }

    public function setOrderID($orderID)
    {
        activity()->causedBy(Auth::user())->withProperties([
            'event' => $this->event,
            'transaction' => $orderID,
        ])->log('paypal OrderID set');
        Log::info('paypal OrderID set', ['user' => Auth::user(), 'order' => $orderID]);

        /** @var User $user */
        $user = Auth::user();
        // TODO: accepted
        $order = Order::whereIn('status', [OrderStatus::Invited->value, OrderStatus::PaypalPending->value])
            ->where('event_id', $this->event->id)
            ->where('user_id', $user->id)
            ->whereNull('verified_at')
            ->first();

        if ($order) {
            $order->order_id = $orderID;
            $order->status = OrderStatus::PaypalPending;
            $order->save();
        } else {
            Log::warning('no order found', ['user' => Auth::user(), 'order_id' => $orderID]);
            return;
        }

        $this->skipRender();
    }

    // TODO: sometimes this doesn't get called...what will the page do?
    public function approve($details)
    {
        Log::info('on approve callback', ['user' => Auth::user(), 'details' => $details]);

        /** @var User $user */
        $user = Auth::user();
        $order = Order::where('user_id', $user->id)
            ->where('order_id', $details['id'])
            ->first();

        if (!$order) {
            Log::error('no order found', ['user' => Auth::user(), 'order_id' => $details['id']]);
            return;
        }

        if ($order->verify()) {
            $this->rego_paid_at = Carbon::now();
        }
    }

    public function cancel()
    {
        // TODO: set order to accepted and clear order ID
        Log::warning("transaction cancelled", ['user' => Auth::user()]);
        $this->dispatch('render-paypal');
    }

    public function error($err)
    {
        // TODO: set order to accepted and clear order ID
        Log::error("transaction error", ['user' => Auth::user(), 'error' => $err]);
        $this->dispatch('render-paypal');
    }

    public function accept_terms()
    {
        $this->terms_accepted = true;
        activity()->causedBy(Auth::user())->log('terms accepted');
        $this->dispatch('render-paypal');
    }

    public function toggle_bonus()
    {
        $this->bonus_accepted = !$this->bonus_accepted;
        $this->dispatch('render-paypal');
    }

    // option to decline rego
    public function decline()
    {
        activity()->causedBy(Auth::user())->log('rego declined');
        return redirect()->to('https://hashrego.com');
    }

    public function edit()
    {
        return redirect()->to('/user/profile');
    }

    public function waitlist() 
    {
        /** @var User $user */
        $user = Auth::user();

        //Log::info('waitlist', ['user' => Auth::user()]);
        $this->order = Order::create([
            'user_id' => $user->id,
            'event_id' => $this->event->id,
            'status' => OrderStatus::Waitlisted,
        ]);
    }
}
