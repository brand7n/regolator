<x-mail::message>
{!! Str::markdown($message->body) !!}

@if(count($profileFields) > 0 || count($eventInfoFields) > 0)

**Your Info:**
@foreach($profileFields as $label => $value)
- **{{ $label }}:** {{ $value }}
@endforeach
@foreach($eventInfoFields as $label => $value)
- **{{ $label }}:** {{ $value }}
@endforeach

@endif

<x-mail::button :url="$url">
View Event
</x-mail::button>

<p style="font-size: 14px; color: #888;">
    If you do not wish to receive further emails, you can <a href="{{ $url . '?action=unsubscribe' }}">unsubscribe here</a>.
</p>
</x-mail::message>
