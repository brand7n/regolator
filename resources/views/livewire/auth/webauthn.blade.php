<div 
    x-data="webAuthnLogin('{{ $challenge }}', credential => $wire.authenticate(credential))"
    x-init="start()"
>
    <script>
        function webAuthnLogin(challenge, onSuccess) {
            return {
                error: null,
                async start() {
                    try {
                        // Detailed WebAuthn support check
                        if (!window.PublicKeyCredential) {
                            console.log('WebAuthn API not found. Checking navigator.credentials...');
                            if (!navigator.credentials) {
                                this.error = 'WebAuthn is not supported in your browser. Please ensure you are using a modern browser and that WebAuthn is enabled.';
                                return;
                            }
                        }

                        console.log('WebAuthn support detected:', {
                            hasPublicKeyCredential: !!window.PublicKeyCredential,
                            hasCredentials: !!navigator.credentials,
                            browser: navigator.userAgent
                        });

                        console.log('Starting WebAuthn login:', challenge);
                        // Convert the base64 challenge to a Uint8Array
                        const challengeBuffer = Uint8Array.from(atob(challenge), c => c.charCodeAt(0));
                        console.log('Challenge buffer:', challengeBuffer);
                        
                        const publicKey = {
                            challenge: challengeBuffer,
                            timeout: 60000,
                            allowCredentials: [], // We'll handle this later when implementing credential storage
                            userVerification: "preferred"
                        };

                        console.log('Requesting credentials with options:', publicKey);
                        const assertion = await navigator.credentials.get({ publicKey });
                        console.log('Got assertion:', assertion);

                        // Convert the assertion to a format we can send to the server
                        const credential = {
                            id: assertion.id,
                            rawId: btoa(String.fromCharCode(...new Uint8Array(assertion.rawId))),
                            type: assertion.type,
                            response: {
                                authenticatorData: btoa(String.fromCharCode(...new Uint8Array(assertion.response.authenticatorData))),
                                clientDataJSON: btoa(String.fromCharCode(...new Uint8Array(assertion.response.clientDataJSON))),
                                signature: btoa(String.fromCharCode(...new Uint8Array(assertion.response.signature))),
                                userHandle: assertion.response.userHandle ? btoa(String.fromCharCode(...new Uint8Array(assertion.response.userHandle))) : null
                            }
                        };

                        console.log('Sending credential to server:', credential);
                        onSuccess(credential);
                    } catch (e) {
                        console.error('WebAuthn error:', e);
                        this.error = e.message || 'Authentication failed.';
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
            Login with Security Key
        </button>
    </div>

    <div x-show="error" class="mt-2 text-sm text-red-600 dark:text-red-400" x-text="error"></div>
</div>
