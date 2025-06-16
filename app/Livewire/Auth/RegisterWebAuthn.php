<?php

namespace App\Livewire\Auth;

use Livewire\Component;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Remodulate\WebauthnFFI;
use RuntimeException;

class RegisterWebAuthn extends Component
{
    public string $challenge;
    public bool $registered = false;
    public string $error = '';
    public array $registrationData;
    public string $rp_id = 'rego.test';
    public string $rp_origin = 'https://rego.test';

    private WebauthnFFI $webauthn;

    public function mount()
    {
        try {
            Log::debug('Starting WebAuthn registration');
            $this->webauthn = new WebauthnFFI(Log::getLogger(), $this->rp_id, $this->rp_origin);
            
            $user = Auth::user();
            Log::debug('Got authenticated user', ['user_id' => $user->id]);
            
            $this->registrationData = $this->webauthn->registerBegin([
                'user_id' => (string)$user->id,
                'user_name' => $user->name,
            ]);
            
            Log::debug('Registration data received', ['data' => $this->registrationData]);
            
            // Store the registration data in session
            $this->storeRegistrationData();
            
            // Extract challenge from registration data
            $this->challenge = $this->extractChallenge();
            
        } catch (RuntimeException $e) {
            Log::error('WebAuthn registration failed', ['error' => $e->getMessage()]);
            $this->error = 'Registration failed: ' . $e->getMessage();
        } catch (\Exception $e) {
            Log::error('Unexpected error during registration', ['error' => $e->getMessage()]);
            $this->error = 'An unexpected error occurred: ' . $e->getMessage();
        }
    }

    public function register($credential)
    {
        try {
            Log::debug('Processing registration credential', [
                'credential_type' => gettype($credential),
                'is_array' => is_array($credential)
            ]);

            if (!is_array($credential)) {
                throw new RuntimeException('Invalid credential format: expected array');
            }

            // Initialize WebauthnFFI
            $this->webauthn = new WebauthnFFI(Log::getLogger(), $this->rp_id, $this->rp_origin);

            $this->validateCredential($credential);
            
            // Get stored registration data
            $registrationData = $this->getStoredRegistrationData();
            
            // Verify the credential
            $result = $this->webauthn->registerFinish([
                'registration' => $registrationData['registration'],
                'client_data' => $credential,
            ]);

            // Store the verified credential
            $this->storeVerifiedCredential($result);

            // Clear registration data from session
            $this->clearRegistrationData();

            $this->registered = true;
            return redirect()->intended(route('dashboard'));

        } catch (RuntimeException $e) {
            Log::error('WebAuthn registration failed', ['error' => $e->getMessage()]);
            $this->error = 'Registration failed: ' . $e->getMessage();
        } catch (\Exception $e) {
            Log::error('Unexpected error during registration', ['error' => $e->getMessage()]);
            $this->error = 'An unexpected error occurred: ' . $e->getMessage();
        }
    }

    private function storeRegistrationData(): void
    {
        Log::debug('Storing registration data in session', [
            'session_id' => session()->getId(),
            'session_name' => session()->getName()
        ]);
        
        session()->put('webauthn.registration', $this->registrationData);
        session()->save();
        
        // Verify storage
        $storedData = session()->get('webauthn.registration');
        if (empty($storedData)) {
            throw new RuntimeException('Failed to store registration data in session');
        }
        
        Log::debug('Registration data stored successfully');
    }

    private function extractChallenge(): string
    {
        if (!isset($this->registrationData['challenge']['publicKey']['challenge'])) {
            throw new RuntimeException('Invalid registration data structure: missing challenge');
        }
        
        // The challenge is already base64url encoded in the response
        return $this->registrationData['challenge']['publicKey']['challenge'];
    }

    private function validateCredential(array $credential): void
    {
        if (empty($credential['response']['clientDataJSON'])) {
            throw new RuntimeException('Invalid credential: missing clientDataJSON');
        }

        if (empty($credential['response']['attestationObject'])) {
            throw new RuntimeException('Invalid credential: missing attestationObject');
        }

        $decodedClientData = base64_decode($credential['response']['clientDataJSON'], true);
        if ($decodedClientData === false) {
            throw new RuntimeException('Invalid base64 encoding in clientDataJSON');
        }

        $clientData = json_decode($decodedClientData, true);
        if (!$clientData) {
            throw new RuntimeException('Invalid clientDataJSON format: ' . json_last_error_msg());
        }

        // Verify challenge matches
        $storedData = $this->getStoredRegistrationData();
        if ($clientData['challenge'] !== $storedData['challenge']['publicKey']['challenge']) {
            throw new RuntimeException('Challenge mismatch');
        }
    }

    private function getStoredRegistrationData(): array
    {
        $registrationData = session()->get('webauthn.registration');
        if (!$registrationData) {
            throw new RuntimeException('Registration session expired');
        }
        return $registrationData;
    }

    private function storeVerifiedCredential(array $result): void
    {
        $user = Auth::user();

        // Log the result structure for debugging
        Log::debug('Registration result structure:', ['result' => $result]);
        
        $user->webauthn_credentials()->create([
            'passkey' => $result,
        ]);
    }

    private function clearRegistrationData(): void
    {
        Log::debug('Clearing registration data from session');
        session()->forget('webauthn.registration');
        session()->save();
    }

    public function render()
    {
        return view('livewire.auth.register-webauthn');
    }
} 