<?php

namespace App\Livewire\Auth;

use Livewire\Component;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class LoginWithWebAuthn extends Component
{
    public string $challenge;
    public bool $authenticated = false;
    public string $credentialIdBase64;

    public function mount()
    {
        $this->challenge = Str::random(32);
        session(['webauthn.challenge' => $this->challenge]);
        
        // For now, we'll use a dummy credential ID
        // In a real implementation, this would come from the user's stored credentials
        $this->credentialIdBase64 = base64_encode(random_bytes(32));
    }

    public function authenticate($credential)
    {
        try {
            // TODO: Implement proper WebAuthn verification
            // For now, we'll just log the credential and simulate success
            Log::info('WebAuthn credential received', ['credential' => $credential]);
            
            // In a real implementation, you would:
            // 1. Verify the credential using a WebAuthn library
            // 2. Get the user ID from the credential
            // 3. Log the user in
            
            // For testing, we'll just set authenticated to true
            $this->authenticated = true;
            
            return redirect()->intended(route('dashboard'));
        } catch (\Exception $e) {
            Log::error('WebAuthn authentication failed', ['error' => $e->getMessage()]);
            $this->addError('auth', 'Authentication failed: ' . $e->getMessage());
        }
    }

    public function render()
    {
        return view('livewire.auth.webauthn');
    }
}
