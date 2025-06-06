<?php

namespace App\Livewire\Auth;

use Livewire\Component;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class RegisterWebAuthn extends Component
{
    public string $challenge;
    public bool $registered = false;
    public string $error = '';

    public function mount()
    {
        // Generate a random challenge for registration
        $this->challenge = Str::random(32);
        session(['webauthn.register_challenge' => $this->challenge]);
    }

    public function register($credential)
    {
        try {
            Log::info('WebAuthn registration credential received', ['credential' => $credential]);

            // TODO: Implement proper WebAuthn verification
            // For now, we'll just store the credential ID
            $user = Auth::user();
            $user->webauthn_credentials()->create([
                'credential_id' => $credential['id'],
                'public_key' => $credential['response']['clientDataJSON'],
                'counter' => 0,
            ]);

            $this->registered = true;
            return redirect()->intended(route('dashboard'));
        } catch (\Exception $e) {
            Log::error('WebAuthn registration failed', ['error' => $e->getMessage()]);
            $this->error = 'Registration failed: ' . $e->getMessage();
        }
    }

    public function render()
    {
        return view('livewire.auth.register-webauthn');
    }
} 