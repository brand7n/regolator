<?php

namespace App\Livewire;

use Livewire\Component;
use App\Models\User;

class Regos extends Component
{
    public $regos;
    public $order;
    public $count;

    function __construct()
    {
        //parent::__construct();
        $this->regos = collect();
        $this->order = 'name';

    }

    public function render()
    {
        $this->regos = User::whereNotNull('rego_paid_at')->orderBy($this->order, 'asc')->get();
        $this->count = $this->regos->count();
        return view('livewire.regos');
    }

    public function change()
    {

    }
}
