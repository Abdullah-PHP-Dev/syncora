{{--
    Shared layout for every Socialeaz platform email (audit §6.1).
    Table-based, inline CSS, 600px, RTL-aware, bulletproof (VML) button, text chip so colour
    is never the only signal. Never put tokens or secrets in anything rendered here except the CTA link.
--}}
@php
    $dir = $rtl ? 'rtl' : 'ltr';
    $align = $rtl ? 'right' : 'left';
    $font = $rtl ? "Tahoma,'Segoe UI',Arial,sans-serif" : "-apple-system,'Segoe UI',Roboto,Arial,sans-serif";
    $brand ??= \App\Services\PlatformEmails\EmailBranding::get();
    $button = $brand['primary_color'] ?? '#5B3DF5';
@endphp
<!doctype html>
<html lang="{{ $locale }}" dir="{{ $dir }}" xmlns:v="urn:schemas-microsoft-com:vml" xmlns:o="urn:schemas-microsoft-com:office:office">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="color-scheme" content="light dark">
<meta name="supported-color-schemes" content="light dark">
<title>{{ $subject }}</title>
<!--[if mso]><style>table,td{mso-line-height-rule:exactly;}</style><![endif]-->
</head>
<body style="margin:0;padding:0;background:#F4F5F9;font-family:{!! $font !!};color:#161B2B;-webkit-text-size-adjust:100%;">
@if(!empty($test))
<div style="background:#FEF3C7;color:#92400E;font-size:12px;font-weight:700;text-align:center;padding:8px;">{{ __('platform_emails.test_banner') }}</div>
@endif
<div style="display:none;max-height:0;overflow:hidden;opacity:0;mso-hide:all;">{{ $preheader }}&#847;&zwnj;&nbsp;&#847;&zwnj;&nbsp;&#847;&zwnj;&nbsp;</div>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" dir="{{ $dir }}" style="background:#F4F5F9;">
<tr><td align="center" style="padding:24px 12px;">
<table role="presentation" width="600" cellpadding="0" cellspacing="0" dir="{{ $dir }}" style="max-width:600px;width:100%;background:#FFFFFF;border:1px solid #E7E9F0;border-radius:14px;overflow:hidden;">

    {{-- Header: logo + workspace --}}
    <tr><td style="padding:20px 28px;border-bottom:1px solid #EEF0F5;">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" dir="{{ $dir }}"><tr>
            <td align="{{ $align }}" style="font-size:16px;font-weight:700;color:#161B2B;">
                @if($brand['logo_url'])<img src="{{ $brand['logo_url'] }}" height="24" alt="" style="height:24px;width:auto;vertical-align:middle;border:0;border-radius:6px;"> @endif<span style="vertical-align:middle;color:{{ $brand['secondary_color'] }};">{{ $brand['app_name'] }}</span>
            </td>
            @if($workspace)
            <td align="{{ $rtl ? 'left' : 'right' }}" style="font-size:12px;color:#6B7385;">{{ __('notifications.ui.workspace') }}: <bdi>{{ $workspace }}</bdi></td>
            @endif
        </tr></table>
    </td></tr>

    {{-- Status bar --}}
    <tr><td style="height:4px;line-height:4px;font-size:0;background:{{ $color }};">&nbsp;</td></tr>

    {{-- Body --}}
    <tr><td align="{{ $align }}" style="padding:26px 28px 6px;text-align:{{ $align }};">
        <span style="display:inline-block;padding:3px 10px;border-radius:999px;background:{{ $color }}1A;color:{{ $color }};font-size:12px;font-weight:700;">{{ $chip }}</span>
        <h1 style="margin:14px 0 10px;font-size:22px;line-height:28px;font-weight:600;color:#161B2B;">{{ $headline }}</h1>
        @if($body)
            <p style="margin:0 0 16px;font-size:15px;line-height:23px;color:#3A4152;">{{ $body }}</p>
        @endif
    </td></tr>

    {{-- Administrator template blocks: rendered by TemplateRenderer (escaped values, purified HTML). --}}
    @if(!empty($blocks_html))
        {!! $blocks_html !!}
    @endif

    @if($details)
    <tr><td style="padding:0 28px 8px;">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" dir="{{ $dir }}" style="background:#F8F9FC;border:1px solid #EEF0F5;border-radius:10px;">
            @foreach($details as [$label, $value])
            <tr>
                <td align="{{ $align }}" style="padding:9px 14px;font-size:13px;color:#6B7385;width:34%;vertical-align:top;">{{ $label }}</td>
                <td align="{{ $align }}" style="padding:9px 14px;font-size:13px;color:#161B2B;font-weight:600;"><bdi>{{ $value }}</bdi></td>
            </tr>
            @endforeach
        </table>
    </td></tr>
    @endif

    @if($items)
    <tr><td style="padding:0 28px 8px;">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" dir="{{ $dir }}">
            @foreach(array_slice($items, 0, 10) as $item)
            <tr><td align="{{ $align }}" style="padding:12px 0;border-bottom:1px solid #EEF0F5;">
                <div style="font-size:14px;font-weight:600;color:#161B2B;">@if($item['url'])<a href="{{ $item['url'] }}" style="color:#161B2B;text-decoration:none;">{{ $item['title'] }}</a>@else{{ $item['title'] }}@endif</div>
                @if(!empty($item['body']))<div style="font-size:13px;line-height:20px;color:#3A4152;margin-top:3px;">{{ $item['body'] }}</div>@endif
                @if(!empty($item['when']))<div style="font-size:12px;color:#8A90A2;margin-top:3px;"><bdi>{{ $item['when'] }}</bdi></div>@endif
            </td></tr>
            @endforeach
        </table>
        @if(count($items) > 10)
            <p style="margin:10px 0 0;font-size:13px;color:#6B7385;">{{ __('notifications.ui.and_more', ['count' => count($items) - 10]) }}</p>
        @endif
    </td></tr>
    @endif

    @foreach($sections as $section)
    <tr><td align="{{ $align }}" style="padding:14px 28px 4px;">
        <h2 style="margin:0 0 6px;font-size:15px;font-weight:700;color:#161B2B;">{{ $section['title'] }}</h2>
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" dir="{{ $dir }}">
            @foreach($section['items'] as $item)
            <tr><td align="{{ $align }}" style="padding:9px 0;border-bottom:1px solid #EEF0F5;">
                <span style="font-size:11px;font-weight:700;color:#8A90A2;text-transform:uppercase;">{{ $item['category'] }}</span>
                <div style="font-size:14px;color:#161B2B;margin-top:2px;">@if($item['url'])<a href="{{ $item['url'] }}" style="color:#161B2B;text-decoration:none;">{{ $item['title'] }}</a>@else{{ $item['title'] }}@endif @if(($item['count'] ?? 1) > 1)<span style="color:#6B7385;">(×<bdi>{{ $item['count'] }}</bdi>)</span>@endif</div>
                @if(!empty($item['workspace']))<div style="font-size:12px;color:#8A90A2;"><bdi>{{ $item['workspace'] }}</bdi></div>@endif
            </td></tr>
            @endforeach
        </table>
    </td></tr>
    @endforeach

    @if($cta && $cta_url)
    <tr><td align="{{ $align }}" style="padding:18px 28px 8px;">
        <!--[if mso]>
        <v:roundrect xmlns:v="urn:schemas-microsoft-com:vml" href="{{ $cta_url }}" style="height:44px;v-text-anchor:middle;width:240px;" arcsize="18%" stroke="f" fillcolor="{{ $button }}">
            <w:anchorlock/><center style="color:#ffffff;font-family:Arial,sans-serif;font-size:15px;font-weight:bold;">{{ $cta }}</center>
        </v:roundrect>
        <![endif]-->
        <!--[if !mso]><!-- -->
        <a href="{{ $cta_url }}" style="display:inline-block;background:{{ $button }};color:#FFFFFF;font-size:15px;font-weight:700;line-height:44px;padding:0 26px;border-radius:9px;text-decoration:none;">{{ $cta }}</a>
        <!--<![endif]-->
        @if($secondary)
            <p style="margin:12px 0 0;font-size:13px;"><a href="{{ $secondary['url'] }}" style="color:{{ $button }};">{{ $secondary['label'] }}</a></p>
        @endif
    </td></tr>
    @endif

    @if($note)
    <tr><td align="{{ $align }}" style="padding:10px 28px 4px;font-size:13px;line-height:20px;color:#6B7385;">{{ $note }}</td></tr>
    @endif

    {{-- Footer --}}
    <tr><td align="{{ $align }}" style="padding:22px 28px 26px;font-size:12px;line-height:19px;color:#8A90A2;border-top:1px solid #EEF0F5;">
        @if(!empty($footer_custom))
            <p style="margin:0 0 8px;color:#6B7385;">{{ $footer_custom }}</p>
        @endif
        <p style="margin:0 0 6px;">{{ __('notifications.ui.need_help') }} <a href="mailto:{{ $brand['support_email'] }}" style="color:{{ $button }};"><bdi>{{ $brand['support_email'] }}</bdi></a></p>
        @if($category)
            <p style="margin:0 0 6px;">{{ $workspace ? __('notifications.ui.why_workspace', ['category' => $category, 'workspace' => $workspace]) : __('notifications.ui.why', ['category' => $category]) }}
            @if($manage_url) · <a href="{{ $manage_url }}" style="color:{{ $button }};">{{ __('notifications.ui.manage') }}</a>@endif
            @if($unsubscribe_url) · <a href="{{ $unsubscribe_url }}" style="color:#8A90A2;">{{ __('notifications.ui.unsubscribe') }}</a>@endif
            </p>
        @endif
        @php
            $socials = array_filter($brand['social'] ?? [], fn ($u) => is_string($u) && preg_match('#^https?://#', $u));
        @endphp
        @if($socials)
            <p style="margin:0 0 6px;">@foreach($socials as $network => $link)<a href="{{ $link }}" style="color:#8A90A2;text-decoration:none;">{{ ucfirst($network) }}</a>@if(!$loop->last) · @endif @endforeach</p>
        @endif
        <p style="margin:0;"><bdi>{{ $brand['copyright'] ?? $brand['app_name'] }}</bdi>@if($brand['footer_text']) · <bdi>{{ $brand['footer_text'] }}</bdi>@endif</p>
    </td></tr>

</table>
</td></tr>
</table>
</body>
</html>
