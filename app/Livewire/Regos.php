<?php

namespace App\Livewire;

use Livewire\Component;
use App\Models\User;

class Regos extends Component
{
    public $regos = [];

    function __construct()
    {
       $this->regos = User::whereNotNull('rego_paid_at')->orderBy('name', 'asc')->get();
    }

    public function render()
    {
        return view('livewire.regos');
    }
}
