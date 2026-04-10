<x-mail::message>
# Greetings, {{ $name }}

You are invited to register for **{{ $event->name }}**!

{{ $event->starts_at->format('F j, Y') }} - {{ $event->ends_at->format('F j, Y') }}<br>
{{ $event->location }}

We are using our own newish, half-assed rego system. The button/link below will allow you to register and pay for exactly one-person, preferably yourself. If you wish to bring a plus one, an entourage, or whatever, respond to this email including, at minimum, an email and hash name for each individual. If you have any problems, questions, and/or concerns, you may also reply to this email.

<x-mail::button :url="$url">
Click Here
</x-mail::button>

Not planning to attend? You can [decline your invite]({{ $url . '?action=decline' }}) to let us know!

ON-ON,<br>
-tmh

<p style="font-size: 14px; color: #888;">
    If you do not wish to receive further emails, you can <a href="{{ $url . '?action=unsubscribe' }}">unsubscribe here</a>.
</p>
</x-mail::message>
