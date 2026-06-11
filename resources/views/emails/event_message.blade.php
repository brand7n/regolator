<x-mail::message>
{!! Str::markdown($message->body) !!}

@if(count($profileFields) > 0 || count($eventInfoFields) > 0)

**Your Info:**

<x-mail::table>
| | |
|:--|:--|
@foreach($profileFields as $label => $value)
| **{{ $label }}** | {{ $value }} |
@endforeach
@foreach($eventInfoFields as $label => $value)
| **{{ $label }}** | {{ $value }} |
@endforeach
</x-mail::table>
@endif

<x-mail::button :url="$url">
View Event
</x-mail::button>

<p style="font-size: 14px; color: #888;">
    If you do not wish to receive further emails, you can <a href="{{ $unsubscribeUrl }}">unsubscribe here</a>.
</p>
</x-mail::message>
