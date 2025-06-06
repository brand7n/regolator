<div 
    x-data="webAuthnRegister('{{ $challenge }}', credential => $wire.register(credential))"
    x-init="start()"
>
    <script>
        function webAuthnRegister(challenge, onSuccess) {
            return {
                error: null,
                async start() {
                    try {
                        // Check WebAuthn support
                        if (!window.PublicKeyCredential) {
                            this.error = 'WebAuthn is not supported in your browser. Please use a modern browser that supports security keys.';
                            return;
                        }

                        console.log('Starting WebAuthn registration:', challenge);
                        
                        // Convert the base64 challenge to a Uint8Array
                        const challengeBuffer = Uint8Array.from(atob(challenge), c => c.charCodeAt(0));
                        console.log('Challenge buffer:', challengeBuffer);

                        // Get the user's ID from the server
                        const userId = '{{ Auth::id() }}';
                        const userHandle = new Uint8Array(16);
                        crypto.getRandomValues(userHandle);

                        const publicKey = {
                            challenge: challengeBuffer,
                            rp: {
                                name: '{{ config('app.name') }}',
                                id: window.location.hostname
                            },
                            user: {
                                id: userHandle,
                                name: '{{ Auth::user()->email }}',
                                displayName: '{{ Auth::user()->name }}'
                            },
                            pubKeyCredParams: [
                                { type: 'public-key', alg: -7 }, // ES256
                                { type: 'public-key', alg: -257 } // RS256
                            ],
                            timeout: 60000,
                            attestation: 'direct',
                            authenticatorSelection: {
                                authenticatorAttachment: 'platform',
                                userVerification: 'preferred',
                                requireResidentKey: false
                            }
                        };

                        console.log('Requesting credential creation with options:', publicKey);
                        const credential = await navigator.credentials.create({ publicKey });
                        console.log('Got credential:', credential);

                        // Convert the credential to a format we can send to the server
                        const credentialData = {
                            id: credential.id,
                            rawId: btoa(String.fromCharCode(...new Uint8Array(credential.rawId))),
                            type: credential.type,
                            response: {
                                attestationObject: btoa(String.fromCharCode(...new Uint8Array(credential.response.attestationObject))),
                                clientDataJSON: btoa(String.fromCharCode(...new Uint8Array(credential.response.clientDataJSON)))
                            }
                        };

                        console.log('Sending credential to server:', credentialData);
                        onSuccess(credentialData);
                    } catch (e) {
                        console.error('WebAuthn error:', e);
                        this.error = e.message || 'Registration failed.';
                    }
                }
            };
        }
    </script>

    <div class="flex items-center justify-center">
        <button 
            @click="start" 
            class="inline-flex items-center px-4 py-2 bg-gray-800 dark:bg-gray-200 border border-transparent rounded-md font-semibold text-xs text-white dark:text-gray-800 uppercase tracking-widest hover:bg-gray-700 dark:hover:bg-white focus:bg-gray-700 dark:focus:bg-white active:bg-gray-900 dark:active:bg-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150"
        >
            Register Security Key
        </button>
    </div>

    <div x-show="error" class="mt-2 text-sm text-red-600 dark:text-red-400" x-text="error"></div>
</div> 