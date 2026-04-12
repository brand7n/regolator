<?php

namespace App\Livewire;

use App\Models\Event;
use App\Models\Order;
use App\Models\OrderStatus;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class EventInfo extends Component
{
    public int $eventId;

    public ?Order $order = null;

    /** @var array<string, mixed> */
    public array $fields = [];

    /** @var array<int, array{name: string, label: string, type: string, rules: string|null, placeholder: string|null}> */
    public array $fieldDefinitions = [];

    public function mount(int $eventId): void
    {
        $this->eventId = $eventId;

        $event = Event::findOrFail($eventId);
        $this->fieldDefinitions = data_get($event->properties, 'fields', []);

        $this->order = Order::where('event_id', $eventId)
            ->where('user_id', Auth::id())
            ->first();

        if ($this->order) {
            $info = $this->order->event_info ?? [];
            foreach ($this->fieldDefinitions as $field) {
                $this->fields[$field['name']] = data_get($info, $field['name']);
            }
        }
    }

    public function submit(): void
    {
        $rules = [];
        foreach ($this->fieldDefinitions as $field) {
            $rules['fields.'.$field['name']] = $field['rules'] ?? 'nullable|string|max:512';
        }
        $this->validate($rules);

        $order = Order::firstOrNew([
            'event_id' => $this->eventId,
            'user_id' => auth()->id(),
        ]);

        if (! $order->exists) {
            $order->status = OrderStatus::Invited;
        }

        $order->event_info = $this->fields;
        $order->save();

        $this->order = $order;
        $this->dispatch('order-updated');

        session()->flash('message', 'Event info saved!');
    }

    public function render(): View
    {
        return view('livewire.event-info');
    }
}
