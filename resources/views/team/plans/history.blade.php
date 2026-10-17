@if($changes->isNotEmpty())
<section class="package-panel"><h2>{{ __('Recent changes') }}</h2><p class="package-muted">{{ __('The latest saved versions and the administrator who made each change.') }}</p><div class="package-history">
@foreach($changes as $change)
<details><summary><span><strong>{{ $change->actor?->name ?? __('System / former administrator') }}</strong><small>{{ $change->created_at->format('Y-m-d H:i') }}</small></span><span class="package-type">{{ __('Version') }} {{ $change->after['version'] ?? '—' }}</span></summary><dl>
@foreach($change->after as $key => $value)
@if(!in_array($key, ['created_at', 'updated_at', 'id', 'name']) && ($change->before === null || ($change->before[$key] ?? null) !== $value))
<div><dt>{{ $key }}</dt><dd>{{ is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) : (is_bool($value) ? ($value ? __('Yes') : __('No')) : $value) }}</dd></div>
@endif
@endforeach
</dl></details>
@endforeach
</div></section>
@endif
