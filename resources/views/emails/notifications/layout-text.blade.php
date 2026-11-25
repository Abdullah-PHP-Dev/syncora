[{{ $chip }}] {{ $headline }}

@if($body){{ $body }}

@endif
@foreach($details as [$label, $value])
{{ $label }}: {{ $value }}
@endforeach
@foreach($items as $item)
- {{ $item['title'] }}@if(!empty($item['when'])) ({{ $item['when'] }})@endif

@endforeach
@foreach($sections as $section)
{{ $section['title'] }}
@foreach($section['items'] as $item)
- {{ $item['title'] }}@if(($item['count'] ?? 1) > 1) (x{{ $item['count'] }})@endif

@endforeach

@endforeach
@if(!empty($blocks_html))
{!! trim(html_entity_decode(strip_tags(preg_replace(['#<br\s*/?>#i', '#</(p|tr|h2)>#i'], ["\n", "\n\n"], $blocks_html)))) !!}

@endif
@if($cta && $cta_url)
{{ $cta }}: {{ $cta_url }}
@endif
@if($note)

{{ $note }}
@endif

--
@if(!empty($footer_custom)){{ $footer_custom }}
@endif
{{ __('notifications.ui.need_help') }} {{ ($brand ?? \App\Services\PlatformEmails\EmailBranding::get())['support_email'] }}
@if($category){{ $workspace ? __('notifications.ui.why_workspace', ['category' => $category, 'workspace' => $workspace]) : __('notifications.ui.why', ['category' => $category]) }}
@endif
@if($manage_url){{ __('notifications.ui.manage') }}: {{ $manage_url }}
@endif
@if($unsubscribe_url){{ __('notifications.ui.unsubscribe') }}: {{ $unsubscribe_url }}
@endif
