<div class="flex justify-center">
    @if ($terms_accepted && !$rego_paid_at)
        @assets
        <script src="https://www.paypal.com/sdk/js?client-id={{ $key }}&disable-funding=credit,card"></script>
        @endassets

        <div class="flex-col items-center space-y-4">
            <x-button class="py-3 items-center " wire:click="toggle_bonus">{{ $bonus_accepted ? 'Remove' : 'Add' }} EH3 Rego Deal ({{ $bonus_accepted ? '-' : '+' }}$115)</x-button>
            <p class="font-semibold text-gray-800 dark:text-gray-200">
                TOTAL: ${{ $price }}
            </p>
            <div id="paypal-button-container"></div>
        </div>

        @script
        <script>
            paypal.Buttons({
                createOrder: function(data, actions) {
                    return actions.order.create({
                        intent: 'CAPTURE',
                        soft_descriptor: "{{ $event }}",
                        purchase_units: [{
                            amount: {
                                currency_code: "USD",
                                value: {{ $price }},
                                breakdown: {
                                    item_total: {
                                        currency_code: "USD",
                                        value: {{ $price }},
                                    }
                                }
                            },
                            items: [{
                                name: "{{ $event }} rego for {{ $name }}",
                                quantity: 1,
                                unit_amount: {
                                    currency_code: "USD",
                                    value: {{ $price }}
                                }
                            }]
                        }]
                    });
                },

                onApprove: function(data, actions) {
                    return actions.order.capture().then(function(details) {
                        console.log('Transaction completed by ' + details.payer.name.given_name + '!');
                        $wire.approve(details).then(function () {
                            //location.reload();
                        });
                    });
                },

                onCancel: function(data, actions) {
                    console.log("Cancelled");
                    $wire.cancel().then(function () {
                        location.reload();                
                    });
                },

                onError: function(err) {
                    console.log(err);
                    $wire.error(err).then(function () {
                        location.reload();                
                    });
                }
            }).render('#paypal-button-container')
        </script>
        @endscript
    @else
        @if ($rego_paid_at)
        <div class="flex-col items-center">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                Rego paid for {{ $name }} at {{ $rego_paid_at->toDateTimeString() }}.
            </h2>
            <p class="py-3 font-semibold text-gray-800 dark:text-gray-200">
Verify your profile and edit rego preferences by clicking below. Hint: This is also how you get on short bus.
</p>
            <x-button class="py-3 items-center" wire:click="edit">Edit</x-button>
        </div>
        @else
        <div class="flex-col items-center">
            <p class="py-3 font-semibold text-gray-800 dark:text-gray-200">
Participating in hashing and hashing events is a potentially hazardous activity that could result in injury or death. I am participating in this event at my own risk and I assume all risk and responsibility for injuries I may incur as a direct or indirect result of my participating in this event.</p>

            <p class="py-3 font-semibold text-gray-800 dark:text-gray-200">
Having read this Waiver and knowing the risks involved in my participation in this event, I, for myself and anyone entitled to act on my behalf, waive and release the Nittany Valley Hash House Harriers, its sponsors, representatives, officers and management from all claims or liabilities of any kind arising out of my participation in this event, even though that liability may arise out of negligence or carelessness on the part of the persons or organizations named in this Waiver.</p>

            <p class="py-3 font-semibold text-gray-800 dark:text-gray-200">
Further, I agree to defend, indemnify and hold harmless the Nittany Valley Hash House Harriers, its sponsors, representatives, officers and management from any and all claims which may result from my participation in this event.</p>

            <p class="py-3 font-semibold text-gray-800 dark:text-gray-200">
I certify I have read this Waiver, I understand it, and I agree to its terms relating to the NVHHH #1900 event or activity hosted by the Nittany Valley Hash House Harriers.</p>
</p>
            <x-button class="py-3 items-center" wire:click="accept_terms">I Accept</x-button>
        </div>
        @endif
    @endif
</div>
