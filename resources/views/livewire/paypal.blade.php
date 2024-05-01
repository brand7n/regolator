<div class="flex justify-center">
    @if ($terms_accepted && !$rego_paid_at)
        @assets
        <script src="https://www.paypal.com/sdk/js?client-id={{ $key }}&enable-funding=venmo&disable-funding=credit,card"></script>
        @endassets

        <div id="paypal-button-container"></div>

        @script
        <script>
            paypal.Buttons({
                createOrder: function(data, actions) {
                    return actions.order.create({
                        intent: 'CAPTURE',
                        purchase_units: [{ 
                            amount: { 
                                currency_code: "USD",
                                value: {{ $price }},
                                description: "ALL YOUR REGO ARE BELONG TO US" 
                            }
                        }]
                    });
                },

                onApprove: function(data, actions) {
                    return actions.order.capture().then(function(details) {
                        console.log('Transaction completed by ' + details.payer.name.given_name + '!');
                        $wire.approve(details).then(function () {
                            location.reload();        
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
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                rego paid at {{ $rego_paid_at->toDateTimeString() }}
            </h2>
        @else
        <div class="flex-col items-center">
            <p class="py-3 font-semibold text-gray-800 dark:text-gray-200">Lorem ipsum dolor sit amet, consectetur adipiscing elit. Proin egestas ipsum eget magna efficitur fringilla. Vestibulum placerat sit amet felis a molestie. Quisque vitae dolor non metus vehicula mollis. Proin et nisl eget ex consectetur scelerisque. Quisque vehicula sed arcu eu dictum. Integer id ligula ut neque pellentesque bibendum. Integer vitae sapien id dolor tempor lacinia et vitae nunc. Maecenas ut enim nisl. Curabitur bibendum interdum magna, eu consectetur lacus dictum sed. Sed dapibus dolor turpis, eu lobortis magna rutrum id. Fusce tempor eros pulvinar augue placerat, id mattis augue consequat. Vestibulum posuere vehicula lacinia. Duis tortor mi, semper nec iaculis eu, egestas rutrum elit.</p>
            <x-button class="py-3 items-center" wire:click="accept_terms">I Accept</x-button>
        </div>
        @endif
    @endif
</div>
