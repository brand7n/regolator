<div>
    <div class="min-h-screen flex flex-col sm:justify-center items-center pt-6 sm:pt-0 bg-gray-100">
        <div class="w-full sm:max-w-md mt-6 px-6 py-4 bg-white shadow-md overflow-hidden sm:rounded-lg">
            <div class="mb-4 text-center">
                <h2 class="text-2xl font-bold text-gray-900">
                    Login with Passkey
                </h2>
                <p class="mt-2 text-sm text-gray-600">
                    Use your passkey to securely log in
                </p>
            </div>

            @if ($error)
                <div class="mb-4 p-4 text-sm text-red-700 bg-red-100 rounded-lg" role="alert">
                    {{ $error }}
                </div>
            @endif

            @if (!$authenticated)
                <div class="mt-6">
                    <button
                        x-data
                        x-on:click="
                            if (!window.webauthnJson) {
                                alert('WebAuthn library not loaded');
                                return;
                            }
                            
                            const challenge = '{{ $challenge }}';
                            const publicKey = {
                                challenge: challenge,
                                rpId: '{{ $rp_id }}',
                                allowCredentials: [],
                                userVerification: 'preferred',
                                timeout: 60000
                            };

                            window.webauthnJson.get(publicKey)
                                .then(credential => {
                                    console.log('Authentication successful:', credential);
                                    $wire.authenticate(credential);
                                })
                                .catch(error => {
                                    console.error('Authentication error:', error);
                                    if (error.name === 'NotAllowedError') {
                                        alert('Authentication was cancelled or failed');
                                    } else {
                                        alert('An error occurred: ' + error.message);
                                    }
                                });
                        "
                        class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                    >
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z" />
                        </svg>
                        Login with Passkey
                    </button>
                </div>
            @else
                <div class="text-center text-green-600">
                    <svg class="mx-auto h-12 w-12" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                    <p class="mt-2">Successfully authenticated!</p>
                    <p class="text-sm">Redirecting to dashboard...</p>
                </div>
            @endif
        </div>
    </div>
</div> 