<?php

namespace App\Livewire\Auth;

use App\Mail\QuickLogin;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberUtil;
use Livewire\Component;

class QuickLoginForm extends Component
{
    public $email = '';

    public $name = '';

    public $phone = '';

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

    protected function validatePhone()
    {
        $phoneUtil = PhoneNumberUtil::getInstance();

        try {
            $parsed = $phoneUtil->parse($this->phone, 'US'); // use 'AUTO' or user country code
            if (! $phoneUtil->isValidNumber($parsed)) {
                throw new \Exception('Invalid phone number');
            }

            // You can normalize to E.164 format (e.g., +15555551212)
            $this->phone = $phoneUtil->format($parsed, PhoneNumberFormat::E164);

        } catch (NumberParseException|\Exception $e) {
            $this->addError('phone', 'The phone number is not valid.');
        }
    }

    public function registerAndSendLink()
    {
        Log::info("creating {$this->email} for {$this->name}");

        $this->validate([
            'email' => 'required|email|unique:users,email',
            'name' => 'required|string',
            'phone' => 'required|string',
        ]);
        $this->validatePhone();

        Log::info("creating {$this->email} for {$this->name}");
        $user = User::create([
            'name' => $this->name,
            'email' => $this->email,
            'password' => Str::random(40),
            'phone' => $this->phone,
        ]);

        $this->sendMagicLink($user);
        $this->reset(['name', 'userExists']);
    }

    protected function sendMagicLink(User $user)
    {
        $quick_login = $user->getQuickLogin();
        if (! $quick_login) {
            return;
        }
        Mail::to($user)->send(new QuickLogin($user, url('/quicklogin/'.$quick_login)));
        session()->flash('status', 'Login link sent. Check your email and SPAM folder!!');
        $this->reset(['name', 'userExists']);
    }

    public function render()
    {
        return view('livewire.auth.quick-login-form');
    }
}
