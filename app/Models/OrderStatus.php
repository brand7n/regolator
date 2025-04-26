<?php

namespace App\Models;

enum OrderStatus: string
{
    case Waitlisted = 'WAITLISTED';
    case Invited = 'INVITED';
    case Accepted = 'ACCEPTED';
    case PaypalPending = 'PAYPAL_PENDING';
    case PaymentVerified = 'PAYMENT_VERIFIED';
    case Cancelled = 'CANCELLED';
}