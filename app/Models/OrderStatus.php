<?php

namespace App\Models;

enum OrderStatus: string
{
    case Waitlisted = 'WAITLISTED';
    case Invited = 'INVITED'; // if you are invited explicity or accepted from waitlist
    case Accepted = 'ACCEPTED'; // terms accepted and ready to pay
    case PaypalPending = 'PAYPAL_PENDING'; // paypal order created but not verified
    case PaymentVerified = 'PAYMENT_VERIFIED'; // paypment verified
    case Cancelled = 'CANCELLED'; // cancelled by user or admin
}