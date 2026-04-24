<?php

namespace App\Livewire;

use App\Models\Event;
use App\Models\Order;
use App\Models\OrderStatus;
use App\Models\User;
use Auth;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\On;
use Livewire\Component;

class Paypal extends Component
{
    public string $key;

    public int $price;

    public string $event_tag;

    public ?Carbon $rego_paid_at = null;

    public bool $terms_accepted = false;

    /** @var array<string, bool> */
    public array $selected_addons = [];

    /** @var array<int, array{name: string, label: string, price: int, tag_suffix?: string}> */
    public array $addonDefinitions = [];

    public string $name;

    public string $sandbox;

    public ?Order $order = null;

    public ?Event $event = null;

    public function mount(int $eventId): void
    {
        $this->key = config('services.paypal.client_id');
        $this->sandbox = config('services.paypal.sandbox');

        /** @var User $user */
        $user = Auth::user();
        $this->name = $user->name;

        $this->event = Event::findOrFail($eventId);
        $this->order = $this->event->getOrder($user);
        $this->addonDefinitions = data_get($this->event->properties, 'addons', []);

        foreach ($this->addonDefinitions as $addon) {
            $this->selected_addons[$addon['name']] = false;
        }

    }

    #[On('order-updated')]
    public function handleUpdatedEvent(): void
    {
        $this->render();
    }

    public function render(): ?View
    {
        if (! $this->event) {
            return null;
        }

        $this->price = $this->event->base_price;
        $this->event_tag = $this->event->event_tag;

        foreach ($this->addonDefinitions as $addon) {
            if ($this->selected_addons[$addon['name']] ?? false) {
                $this->price += $addon['price'];
                $this->event_tag .= $addon['tag_suffix'] ?? '';
            }
        }

        /** @var User $user */
        $user = Auth::user();
        $this->order = $this->event->getOrder($user);
        if ($this->order && $this->order->status === OrderStatus::PaymentVerified) {
            $this->rego_paid_at = $this->order->verified_at;
        }
        $this->rego_paid_at = $this->event->regoPaidAt($user);

        return view('livewire.paypal');
    }

    public function setOrderID(string $orderID): void
    {
        activity()->causedBy(auth()->user())->withProperties([
            'event' => $this->event,
            'transaction' => $orderID,
        ])->log('paypal OrderID set');
        Log::info('paypal OrderID set', ['user' => Auth::user(), 'order' => $orderID]);

        /** @var User $user */
        $user = Auth::user();
        $order = Order::whereIn('status', [OrderStatus::Invited->value, OrderStatus::Accepted, OrderStatus::PaypalPending->value])
            ->where('event_id', $this->event->id)
            ->where('user_id', $user->id)
            ->whereNull('verified_at')
            ->first();

        if (! $order) {
            $existing = Order::where('event_id', $this->event->id)
                ->where('user_id', $user->id)
                ->first();

            Log::warning('setOrderID: no eligible order', [
                'user_id' => $user->id,
                'order_id' => $orderID,
                'existing_status' => $existing?->status->value ?? 'none',
                'existing_order_id' => $existing?->order_id,
            ]);

            if ($existing) {
                $this->js("alert('Payment could not be processed: your registration is in ".$existing->status->value." state. Please contact the event organizer.')");
            }

            return;
        }

        $order->order_id = $orderID;
        $order->status = OrderStatus::PaypalPending;
        $order->save();

        $this->skipRender();
    }

    /** @param array<string, mixed> $details */
    public function approve(array $details): void
    {
        Log::info('on approve callback', ['user' => Auth::user(), 'details' => $details]);

        /** @var User $user */
        $user = Auth::user();
        $order = Order::where('user_id', $user->id)
            ->where('order_id', $details['id'])
            ->first();

        if (! $order) {
            Log::error('no order found', ['user' => Auth::user(), 'order_id' => $details['id']]);

            return;
        }

        if ($order->verify()) {
            $this->rego_paid_at = Carbon::now();
        }
    }

    public function cancel(): void
    {
        Log::warning('transaction cancelled', ['user' => Auth::user()]);
        $this->dispatch('render-paypal');
    }

    public function error(mixed $err): void
    {
        Log::error('transaction error', ['user' => Auth::user(), 'error' => $err]);
        $this->dispatch('render-paypal');
    }

    public function accept_terms(): void
    {
        $this->terms_accepted = true;

        /** @var User $user */
        $user = Auth::user();

        if (! $this->order && ! $this->event->private) {
            $this->order = Order::create([
                'user_id' => $user->id,
                'event_id' => $this->event->id,
                'status' => OrderStatus::Accepted,
            ]);
        }

        if ($this->order && $this->event->base_price <= 0) {
            $this->order->verified_at = Carbon::now();
            $this->order->status = OrderStatus::PaymentVerified;
            $this->order->save();
            $this->rego_paid_at = $this->order->verified_at;
            activity()->causedBy($user)->log('free event registration completed');

            return;
        }

        activity()->causedBy($user)->log('terms accepted');
        $this->dispatch('render-paypal');
    }

    public function toggleAddon(string $addonName): void
    {
        $this->selected_addons[$addonName] = ! ($this->selected_addons[$addonName] ?? false);
        $this->dispatch('render-paypal');
    }

    public function decline(): void
    {
        /** @var User $user */
        $user = Auth::user();

        if ($this->order) {
            $this->order->status = OrderStatus::Cancelled;
            $this->order->save();
        }

        activity()->causedBy($user)->log('rego declined');

        $this->redirect('https://hashrego.com');
    }

    public function edit(): void
    {
        $this->redirect('/user/profile');
    }

    public function waitlist(): void
    {
        /** @var User $user */
        $user = Auth::user();

        $existing = Order::where('user_id', $user->id)
            ->where('event_id', $this->event->id)
            ->where('status', OrderStatus::Blocked)
            ->first();

        if ($existing) {
            $this->order = $existing;

            return;
        }

        $this->order = Order::create([
            'user_id' => $user->id,
            'event_id' => $this->event->id,
            'status' => OrderStatus::Waitlisted,
        ]);
    }
}
