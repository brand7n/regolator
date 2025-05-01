<?php

namespace App\Livewire;

use Livewire\Component;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class Regos extends Component
{
    public Collection $regos;
    public string $orderby;
    public int $count;
    public string $direction;
    public int $max;
    public int $event_id;

    protected array $sortable = ['rego_paid_at', 'name', 'kennel'];

    function mount()
    {
        $this->regos = collect();
        $this->orderby = 'rego_paid_at';
        $this->direction = 'asc';
        $this->max = 130;
        $this->event_id = 1;
    }

    public function render()
    {
        if (!in_array($this->orderby, $this->sortable)) {
            $this->orderby = 'rego_paid_at';
        }
        if ($this->direction !== 'asc') {
            $this->direction = 'desc';
        }

        $verifiedUsers = DB::table('users')
            ->join('orders', function ($join) { 
                $join->on('users.id', '=', 'orders.user_id')
                     ->where('orders.event_id', '=', $this->event_id)
                     ->where('orders.status', '=', 'PAYMENT_VERIFIED');
            })
            ->select('users.id', 'orders.verified_at')
            ->get()
            ->keyBy('id');

        $this->count = $verifiedUsers->count();
        $this->regos = User::whereIn('id', $verifiedUsers->keys())
            ->get()
            ->map(function ($user) use ($verifiedUsers) {
                $user->rego_paid_at = new Carbon($verifiedUsers[$user->id]->verified_at);
                return $user;
            })
            ->sortBy([$this->orderby, $this->direction]);

        return view('livewire.regos');
    }
}
