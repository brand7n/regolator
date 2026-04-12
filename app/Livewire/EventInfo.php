<?php

namespace App\Livewire;

use App\Models\Order;
use App\Models\OrderStatus;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class EventInfo extends Component
{
    public int $eventId;

    public ?Order $order = null;

    public ?int $cabin_number = null;

    public ?string $shot_stop = null;

    public ?string $reserved_by = null;

    /** @var array<string, string> */
    protected $rules = [
        'cabin_number' => 'required|integer|between:1,15',
        'reserved_by' => 'required|string|max:512',
        'shot_stop' => 'nullable|string|max:512',
    ];

    public function mount(int $eventId): void
    {
        $this->eventId = $eventId;

        // If an order already exists for this user & event, load it
        $this->order = Order::where('event_id', $eventId)
            ->where('user_id', Auth::id())
            ->first();

        if ($this->order) {
            $this->cabin_number = data_get($this->order->event_info, 'cabin_number');
            $this->shot_stop = data_get($this->order->event_info, 'shot_stop');
            $this->reserved_by = data_get($this->order->event_info, 'reserved_by');
        }
    }

    public function submit(): void
    {
        $this->validate();

        $order = Order::firstOrNew([
            'event_id' => $this->eventId,
            'user_id' => auth()->id(),
        ]);

        // Only set status when we're creating a new one
        if (! $order->exists) {
            $order->status = OrderStatus::Invited;
        }

        $order->event_info = [
            'cabin_number' => $this->cabin_number,
            'shot_stop' => $this->shot_stop,
            'reserved_by' => $this->reserved_by,
        ];

        $order->save();

        $this->order = $order;

        // Re-render other components
        $this->dispatch('order-updated');
        \Log::info('dispatch');

        session()->flash('message', 'Cabin info saved!');
    }

    public function render(): View
    {
        return view('livewire.event-info');
    }
}
