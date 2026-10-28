{{-- The verification and reset emails (README "Email"): plain, no images, no tracking. --}}
@php($dir = $locale === 'ar' ? 'rtl' : 'ltr')
<!DOCTYPE html>
<html lang="{{ $locale }}" dir="{{ $dir }}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ $button }}</title>
</head>
<body dir="{{ $dir }}" style="margin:0;padding:24px 16px;background:#ffffff;color:#1a1a1a;font-family:Arial,Helvetica,sans-serif;font-size:16px;line-height:1.6;text-align:{{ $dir === 'rtl' ? 'right' : 'left' }};">
<div dir="{{ $dir }}" style="max-width:520px;margin:0 auto;">
<p style="margin:0 0 16px;">{{ __('mail.greeting', ['name' => $name]) }}</p>
<p style="margin:0 0 24px;">{{ $intro }}</p>
<p style="margin:0 0 24px;"><a href="{{ $url }}" style="display:inline-block;padding:12px 20px;background:#1f4fd1;color:#ffffff;text-decoration:none;border-radius:4px;font-weight:bold;">{{ $button }}</a></p>
<p style="margin:0 0 16px;">{{ __('mail.expires') }}</p>
<p style="margin:0 0 8px;">{{ __('mail.fallback') }}</p>
<p dir="ltr" style="margin:0 0 24px;word-break:break-all;text-align:left;"><a href="{{ $url }}" style="color:#1f4fd1;">{{ $url }}</a></p>
<p style="margin:0 0 24px;">{{ $ignore }}</p>
<p style="margin:0;color:#555555;font-size:14px;">{{ __('mail.footer') }}</p>
</div>
</body>
</html>
