<?php

namespace App\Livewire;

use Illuminate\Support\Facades\Log;
use Livewire\Component;
use GuzzleHttp\Client;
use Auth;
use Illuminate\Support\Carbon;
use App\Models\User;
use App\Models\Order;

class Paypal extends Component
{
    public $key;
    public $price;
    public $event;
    public $rego_paid_at;
    public $terms_accepted = false;
    public $bonus_accepted = false;
    public $name;

    function __construct()
    {
        //parent::__construct();
        $this->key = config('services.paypal.client_id');
        /** @var User $user */
        $user = Auth::user();
        $this->rego_paid_at = $user->rego_paid_at;
        $this->name = $user->name;
    }

    protected function reinit()
    {
        $this->price = 165;
        $this->event = 'NVHHH1900';
        if ($this->bonus_accepted) {
            $this->price += 115;
            $this->event .= '_PLUS_EH3_32NDANAL';
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

        $order = new Order();
        $order->user()->associate(Auth::user());
        $order->order_id = $orderID;
        $order->save();

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
        Log::warning("transaction cancelled", ['user' => Auth::user()]);
        $this->dispatch('render-paypal');
    }

    public function error($err)
    {
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
