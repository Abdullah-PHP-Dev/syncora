@php
    $brandAssets = [
        'Instagram' => 'instagram',
        'Facebook' => 'facebook',
        'LinkedIn' => 'linkedin',
        'YouTube' => 'youtube',
        'TikTok' => 'tiktok',
        'X' => 'x',
        'Snapchat' => 'snapchat',
        'WhatsApp' => 'whatsapp',
        'Email Marketing' => 'envelope',
    ];
@endphp
@if(isset($brandAssets[$platform]))
    {!! str_replace('<svg ', '<svg aria-hidden="true" focusable="false" ', file_get_contents(public_path('assets/icons/social/'.$brandAssets[$platform].'.svg'))) !!}
@endif
