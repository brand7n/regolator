<?php

namespace App\Livewire;

use Livewire\Component;
use App\Models\Order;
use Illuminate\Support\Facades\Auth;

class EventInfo extends Component
{
    public $eventId;
    public $order;
    public $cabin_number;
    public $shot_stop;
    public $reserved_by;

    protected $rules = [
        'cabin_number' => 'required|integer|between:1,15',
	'reserved_by' => 'required|string|max:512',
        'shot_stop'    => 'nullable|string|max:512',
    ];

    public function mount($eventId)
    {
        $this->eventId = $eventId;

        // If an order already exists for this user & event, load it
        $this->order = Order::where('event_id', $eventId)
            ->where('user_id', Auth::id())
            ->first();

        if ($this->order) {
            $this->cabin_number = data_get($this->order->event_info, 'cabin_number');
            $this->shot_stop    = data_get($this->order->event_info, 'shot_stop');
            $this->reserved_by  = data_get($this->order->event_info, 'reserved_by');
        }
    }

	public function submit()
	{
	    $this->validate();

	    $order = \App\Models\Order::firstOrNew([
		'event_id' => $this->eventId,
		'user_id'  => auth()->id(),
	    ]);

	    // Only set status when we're creating a new one
	    if (! $order->exists) {
		$order->status = 'INVITED';
	    }

	    $order->event_info = [
		'cabin_number' => $this->cabin_number,
		'shot_stop'    => $this->shot_stop,
		'reserved_by'  => $this->reserved_by,
	    ];

	    $order->save();

	    $this->order = $order;

	    // Re-render other components
	    $this->dispatch('order-updated');
	    \Log::info("dispatch");

	    session()->flash('message', 'Cabin info saved!');
	}

    public function render()
    {
        return view('livewire.event-info');
    }
}

