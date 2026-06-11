<x-mail::message>
{!! Str::markdown($message->body) !!}

@if(count($profileFields) > 0 || count($eventInfoFields) > 0)
<div style="margin-top: 16px; padding: 12px; background-color: #f8f9fa; border-radius: 4px; color: #333333;">
<p style="font-weight: bold; margin-bottom: 8px; color: #333333;">Your Info:</p>
<ul style="list-style: none; padding: 0; margin: 0; color: #333333;">
@foreach($profileFields as $label => $value)
<li style="padding: 2px 0; color: #333333;"><strong>{{ $label }}:</strong> {{ $value }}</li>
@endforeach
@foreach($eventInfoFields as $label => $value)
<li style="padding: 2px 0; color: #333333;"><strong>{{ $label }}:</strong> {{ $value }}</li>
@endforeach
</ul>
</div>
@endif

<x-mail::button :url="$url">
View Event
</x-mail::button>

<p style="font-size: 14px; color: #888;">
    If you do not wish to receive further emails, you can <a href="{{ $url . '?action=unsubscribe' }}">unsubscribe here</a>.
</p>
</x-mail::message>
