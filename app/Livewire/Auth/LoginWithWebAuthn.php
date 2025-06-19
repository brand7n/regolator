<?php

namespace App\Livewire\Auth;

use Livewire\Component;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use App\Models\User;
use Remodulate\WebauthnFFI;

class LoginWithWebAuthn extends Component
{
    public string $challenge;
    public bool $authenticated = false;
    public string $error = '';
    public string $rp_id = 'rego.test';
    public string $rp_origin = 'https://rego.test';
    public string $email = 'brandin@remodulate.com';

    private WebauthnFFI $webauthn;

    public function mount()
    {
        try {
            Log::debug('Starting WebAuthn login');
            
            // Debug FFI initialization
            Log::debug('Initializing WebauthnFFI', [
                'rp_id' => $this->rp_id,
                'rp_origin' => $this->rp_origin
            ]);
            
            $this->webauthn = new WebauthnFFI(Log::getLogger(), $this->rp_id, $this->rp_origin);
            
            // Debug FFI initialization success
            Log::debug('WebauthnFFI initialized successfully');
            
            // Get credentials for for the user attempting to login
            $user = User::whereEmail($this->email)->first();
            
            // Convert their credentials to passkeys
            $passkeys = $user->webauthn_credentials->pluck('passkey')->toArray();
            Log::debug('Converted credentials to passkeys', ['count' => count($passkeys)]);
            
            if (empty($passkeys)) {
                throw new \RuntimeException('No WebAuthn credentials found');
            }
            
            // Debug FFI call
            Log::debug('Calling authenticateBegin with passkeys');
            
            // Since we're doing a login, we don't know the user_id yet
            // We'll use a placeholder that will be validated during authentication
            $authData = $this->webauthn->authenticateBegin([
                'user_id' => 'login',  // This will be validated during authentication
                'passkeys' => $passkeys,
                'rp_id' => $this->rp_id,
                'rp_origin' => $this->rp_origin,
            ]);
            
            Log::debug('Authentication data received', ['data' => $authData]);
            
            // Store the authentication data in session
            session()->put('webauthn.auth', $authData);
            session()->save();
            
            // Extract challenge from authentication data
            if (!isset($authData['challenge']['publicKey']['challenge'])) {
                throw new \RuntimeException('Invalid authentication data structure: missing challenge');
            }
            
            $this->challenge = $authData['challenge']['publicKey']['challenge'];
            Log::debug('Challenge extracted', ['challenge' => $this->challenge]);
            
        } catch (\RuntimeException $e) {
            Log::error('WebAuthn login failed', ['error' => $e->getMessage()]);
            $this->error = 'Login failed: ' . $e->getMessage();
        } catch (\Exception $e) {
            Log::error('Unexpected error during login', ['error' => $e->getMessage()]);
            $this->error = 'An unexpected error occurred: ' . $e->getMessage();
        }
    }

    public function authenticate($credential)
    {
        try {
            Log::debug('Processing authentication credential', [
                'credential_type' => gettype($credential),
                'is_array' => is_array($credential)
            ]);

            if (!is_array($credential)) {
                throw new \RuntimeException('Invalid credential format: expected array');
            }

            // Initialize WebauthnFFI
            $this->webauthn = new WebauthnFFI(Log::getLogger(), $this->rp_id, $this->rp_origin);

            // Get stored authentication data
            $authData = session()->get('webauthn.auth');
            if (!$authData) {
                throw new \RuntimeException('Authentication session expired');
            }
            Log::debug('Auth data before', ['auth_data' => $authData]);
            $bytes = random_bytes(32); // 32 bytes = 256 bits of entropy
            $authData['auth_state']['ast']['challenge'] = rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
            Log::debug('Auth data after', ['auth_data' => $authData]);

            // Verify the credential
            $result = $this->webauthn->authenticateFinish([
                'auth_state' => $authData['auth_state'],
                'client_data' => $credential,
            ]);

            Log::debug('Authentication result', ['result' => $result]);

            // Get credentials for for the user attempting to login
            $user = User::whereEmail($this->email)->first();

            // Log the user in
            Auth::login($user);
            $this->authenticated = true;

            // TODO: Update the passkey counter if authentication was successful

            // Clear authentication data from session
            session()->forget('webauthn.auth');
            session()->save();

            return redirect()->intended(route('dashboard'));

        } catch (\RuntimeException $e) {
            Log::error('WebAuthn authentication failed', ['error' => $e->getMessage()]);
            $this->error = 'Authentication failed: ' . $e->getMessage();
        } catch (\Exception $e) {
            Log::error('Unexpected error during authentication', ['error' => $e->getMessage()]);
            $this->error = 'An unexpected error occurred: ' . $e->getMessage();
        }
    }

    public function render()
    {
        return view('livewire.auth.login-webauthn');
    }
}
