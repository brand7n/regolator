<div  class="flex justify-center">
    @assets
    <script src="https://www.paypal.com/sdk/js?client-id={{ $key }}&enable-funding=venmo&disable-funding=credit,card"></script>
    @endassets

    <div id="paypal-button-container"></div>

    @script
    <script>
        paypal.Buttons({
            // style: {
            //    shape: 'rect',
            //    color: 'gold',
            //    layout: 'vertical',
            //    label: 'paypal',
            //  },

            createOrder: function(data, actions) {
                return actions.order.create({
                    intent: 'CAPTURE',
                    purchase_units: [{ 
                        amount: { 
                            currency_code: "USD",
                            value: {{ $price }},
                            description: "{{ $description }}" 
                        }
                    }]
                });
            },

            onApprove: function(data, actions) {
                return actions.order.capture().then(function(details) {
                    console.log('Transaction completed by '  + details.payer.name.given_name + '!');
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
</div>
