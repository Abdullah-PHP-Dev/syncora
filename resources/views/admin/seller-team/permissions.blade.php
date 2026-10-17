<div class="st-permission-heading"><h3>{{ __('seller_team.module_permissions') }}</h3><span>{{ __('seller_team.permission_default') }}</span></div>
<p class="st-permission-help">{{ __('seller_team.permissions_help') }}</p>
<div class="st-permission-grid">
@foreach($modules as $key => $definition)
    <div class="st-permission-field"><label for="{{ $prefix }}-{{ $key }}">{{ __($definition['label']) }}</label><select id="{{ $prefix }}-{{ $key }}" name="permissions[{{ $key }}]">@foreach(['none', 'read', 'write'] as $level)<option value="{{ $level }}" @selected(($values[$key] ?? 'none') === $level)>{{ __('seller_team.'.$level) }}</option>@endforeach</select></div>
@endforeach
</div>
