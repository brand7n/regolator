<x-mail::message>
# Greetings from Nittany Valley, {{ $name }}

Please review your profile information, and if necessary edit by clicking the link below:

- Kennel: {{ $kennel }}
- Shirt Size: {{ $shirt_size }}
- Short Bus: {{ $short_bus }}

<x-mail::button :url="$url">
Click Here
</x-mail::button>

Details on His Glorious Theme: <a href="https://en.wikipedia.org/wiki/Dragnet_(franchise)">Dragnet</a>

ON-ON,<br>
-tmh
</x-mail::message>
