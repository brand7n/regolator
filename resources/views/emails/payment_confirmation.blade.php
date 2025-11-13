<x-mail::message>
# Greetings from Nittany Valley, {{ $name }}

## Payment Confirmation
Your payment for Holidaze has been received!

Please review your profile information, and if necessary edit by clicking the link below:

- Nerd Name: {{ $nerd_name }}
- Kennel: {{ $kennel }}

<x-mail::button :url="$url">
Click Here
</x-mail::button>

You can also use this link to view event information. We may post a schedule up there. At some point. Maybe not. Please review and refer back to this page as it may be updated at any time without notice.

ON-ON,<br>
-tmh
</x-mail::message>
