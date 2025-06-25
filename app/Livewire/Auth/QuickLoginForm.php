<?php

namespace App\Livewire\Auth;

use Livewire\Component;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use App\Mail\QuickLogin;

class QuickLoginForm extends Component
{
    public $email = '';
    public $name = '';
    public $userExists = null;

    public function checkEmail()
    {
        Log::info("checking {$this->email}");

        $this->validate(['email' => 'required|email']);
        $user = User::where('email', $this->email)->first();

        if ($user) {
            $this->userExists = true;
            $this->sendMagicLink($user);
        } else {
            $this->userExists = false;
        }
    }

    public function registerAndSendLink()
    {
        Log::info("creating {$this->email} for {$this->name}");

        $this->validate([
            'email' => 'required|email|unique:users,email',
            'name' => 'required|string',
        ]);

        Log::info("creating {$this->email} for {$this->name}");
        $user = User::create([
            'name' => $this->name,
            'email' => $this->email,
            'password' => Str::random(40),
        ]);

        $this->sendMagicLink($user);
        $this->reset(['name', 'userExists']);
    }

    protected function sendMagicLink(User $user)
    {
        $quick_login = $user->getQuickLogin();
        if (!$quick_login) {
            return;
        }
        Mail::to($user)->send(new QuickLogin($user, url('/quicklogin/' . $quick_login)));
        session()->flash('status', 'Login link sent. Check your email and SPAM folder!!');
        $this->reset(['name', 'userExists']);
    }

    public function render()
    {
        return view('livewire.auth.quick-login-form');
    }
}
