{{-- Plain-text part: no HTML escaping here, it is not HTML. --}}
{!! __('mail.greeting', ['name' => $name]) !!}

{!! $intro !!}

{!! $button !!}: {!! $url !!}

{!! __('mail.expires') !!}

{!! $ignore !!}

{!! __('mail.footer') !!}
