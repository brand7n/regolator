<?php

namespace App\Livewire;

use Illuminate\Support\Facades\Log;
use Livewire\Component;
use GuzzleHttp\Client;
use Auth;
use Illuminate\Support\Carbon;
use App\Models\{User, Order, OrderStatus, Event};

class Paypal extends Component
{
    public $key;
    public $price;
    public string $event_tag;
    public $rego_paid_at;
    public $terms_accepted = false;
    public $bonus_accepted = false;
    public $name;
    public string $sandbox;

    protected Event $event;

    function __construct()
    {
        //parent::__construct();
        $this->key = config('services.paypal.client_id');
        $this->sandbox = config('services.paypal.sandbox');
        /** @var User $user */
        $user = Auth::user();
        $this->rego_paid_at = $user->rego_paid_at;
        $this->name = $user->name;
        // TODO: lookup actual event when there's eventually more than one
        $this->event = Event::find(1);
    }

    protected function reinit()
    {
        $this->price = $this->event->base_price_in_dollars;
        $this->event_tag = $this->event->event_tag;
        // TODO: derive from event options
        if ($this->bonus_accepted) {
            $this->price += 115;
            $this->event_tag .= '_PLUS_EH3_32NDANAL';
        }
    }

    public function render()
    {
        $this->reinit();
        return view('livewire.paypal');
    }

    public function setOrderID($orderID)
    {
        activity()->causedBy(Auth::user())->withProperties([
            'event' => $this->event,
            'transaction' => $orderID,
        ])->log('order created');
        Log::info('order created', ['user' => Auth::user(), 'order' => $orderID]);

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
            $order->status = OrderStatus::PaypalPending->value;
            $order->save();
        } else {
            Log::warning('no order found', ['user' => Auth::user(), 'order_id' => $orderID]);
        }

        $this->skipRender();
    }

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
}
