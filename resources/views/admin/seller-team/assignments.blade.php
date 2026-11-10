{{--
    Step 2 (workspaces) and step 3 (role + modules per selected workspace).
    $workspaces: [account_id, name, logo_url, default]; $current: rows keyed by workspace account (edit);
    $old: submitted values (invite form after a validation error); $prefix: unique per form.
--}}
@php
    $old ??= null;
    $current ??= collect();
    $planLocked ??= [];
    $single = count($workspaces) === 1;
    $state = function ($id) use ($old, $current, $single) {
        if (is_array($old)) {
            $w = $old[$id] ?? [];

            return ['on' => ($w['enabled'] ?? '0') === '1', 'role' => $w['role'] ?? 'member', 'mods' => array_keys(array_filter($w['modules'] ?? [])), 'levels' => $w['levels'] ?? []];
        }
        $row = $current[$id] ?? null;

        return [
            'on' => (bool) $row || ($single && $current->isEmpty()),
            'role' => $row?->role ?? 'member',
            'mods' => $row ? array_keys(array_filter($row->permissions ?? [], fn ($v) => $v !== 'none')) : [],
            'levels' => $row?->permissions ?? [],
        ];
    };
    $wsInitials = fn ($name) => mb_strtoupper(collect(explode(' ', trim($name)))->filter()->take(2)->map(fn ($p) => mb_substr($p, 0, 1))->implode(''));
@endphp
{{-- In <head>: Vue re-renders #app and drops <style> tags inside it. --}}
@push('styles')
<style>
    @foreach($workspaces as $ws)
    #{{ $prefix }}-cfg-{{ $ws['account_id'] }}{display:none}
    form:has(#{{ $prefix }}-ws-{{ $ws['account_id'] }}:checked) #{{ $prefix }}-cfg-{{ $ws['account_id'] }}{display:block}
    @endforeach
</style>
@endpush

<div class="st-step">
    <div class="st-step-head">
        <span class="st-step-num">2</span>
        <div><h3>{{ __('seller_team.step_workspaces') }}</h3><p>{{ __('seller_team.step_workspaces_help') }}</p></div>
        @unless($single)
            <label class="st-select-all"><input type="checkbox" data-ws-all>{{ __('seller_team.select_all') }}</label>
        @endunless
    </div>
    <div class="st-ws-grid">
        @foreach($workspaces as $ws)
            @php($s = $state($ws['account_id']))
            <label class="st-ws-pick">
                <input type="hidden" name="workspaces[{{ $ws['account_id'] }}][enabled]" value="0">
                <input type="checkbox" class="st-ws-check" id="{{ $prefix }}-ws-{{ $ws['account_id'] }}" name="workspaces[{{ $ws['account_id'] }}][enabled]" value="1" @checked($s['on'])>
                @if($ws['logo_url'])
                    <img class="st-ws-logo" src="{{ $ws['logo_url'] }}" alt="">
                @else
                    <span class="st-ws-logo" aria-hidden="true">{{ $wsInitials($ws['name']) }}</span>
                @endif
                <span class="st-ws-name">{{ $ws['name'] }}@if($ws['default'])<small>{{ __('seller_team.default_workspace') }}</small>@endif</span>
                <i class="bi bi-check-circle-fill st-ws-tick" aria-hidden="true"></i>
            </label>
        @endforeach
    </div>
</div>

<div class="st-step">
    <div class="st-step-head">
        <span class="st-step-num">3</span>
        <div><h3>{{ __('seller_team.step_access') }}</h3><p>{{ __('seller_team.step_access_help') }}</p></div>
    </div>
    <p class="st-ws-none">{{ __('seller_team.no_workspace_selected') }}</p>
    @foreach($workspaces as $ws)
        @php($s = $state($ws['account_id']))
        @php($base = 'workspaces['.$ws['account_id'].']')
        <fieldset class="st-ws-config" id="{{ $prefix }}-cfg-{{ $ws['account_id'] }}">
            <legend>{{ $ws['name'] }}</legend>
            @if($canAssignAdmin)
                <div class="st-ws-roles" role="radiogroup" aria-label="{{ __('seller_team.role') }}">
                    @foreach(['admin', 'member'] as $role)
                        <label class="st-role-option">
                            <input type="radio" name="{{ $base }}[role]" value="{{ $role }}" @checked($s['role'] === $role)>
                            <span><strong>{{ __('seller_team.roles.'.$role) }}</strong><small>{{ __('seller_team.role_help.'.$role) }}</small></span>
                        </label>
                    @endforeach
                </div>
            @else
                <input type="hidden" name="{{ $base }}[role]" value="member">
            @endif
            <p class="st-ws-admin-note"><i class="bi bi-stars" aria-hidden="true"></i>{{ __('seller_team.admin_access') }}</p>
            <div class="st-ws-modules">
                @foreach($modules as $key => $definition)
                    <div class="st-mod">
                        <label>
                            <input type="checkbox" name="{{ $base }}[modules][{{ $key }}]" value="1" @checked(in_array($key, $s['mods'], true))>
                            <span>{{ __($definition['label']) }}</span>
                            @if(in_array($key, $planLocked, true))<small class="st-mod-locked" title="{{ __('seller_team.not_in_plan_help') }}"><i class="bi bi-lock" aria-hidden="true"></i>{{ __('seller_team.not_in_plan') }}</small>@endif
                        </label>
                        <select name="{{ $base }}[levels][{{ $key }}]" aria-label="{{ __($definition['label']) }}">
                            <option value="write" @selected(($s['levels'][$key] ?? 'write') === 'write')>{{ __('seller_team.write') }}</option>
                            <option value="read" @selected(($s['levels'][$key] ?? 'write') === 'read')>{{ __('seller_team.read') }}</option>
                        </select>
                    </div>
                @endforeach
            </div>
        </fieldset>
    @endforeach
</div>
