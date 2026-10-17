@if($comparisonPlans->isNotEmpty() && $comparisonFeatures->isNotEmpty())
<section class="plans-comparison" aria-label="{{ __('Package feature comparison') }}">
    <div class="plans-toolbar"><div><h2>{{ __('Compare package features') }}</h2><p>{{ __('Review the configured allowances and benefits across active packages.') }}</p></div></div>
    <div class="plans-comparison-scroll" role="region" tabindex="0" aria-label="{{ __('Package feature comparison') }}"><table class="plans-comparison-table">
        <thead><tr><th scope="col">{{ __('Feature') }}</th>@foreach($comparisonPlans as $package)<th scope="col"><a href="{{ route('plans.edit', $package) }}">{{ $package->name }}</a><small>{{ number_format($package->price, 2) }} {{ $package->currency }}@unless($package->is_free) / {{ __('month') }}@endunless</small></th>@endforeach</tr></thead>
        <tbody><tr><th scope="row">{{ __('Duration') }}</th>@foreach($comparisonPlans as $package)<td>{{ $package->is_free ? __(':count days', ['count' => data_get($package->meta, 'trial_days', 14)]) : __('Paid') }}</td>@endforeach</tr>
        @foreach($comparisonFeatures as $feature)<tr><th scope="row">{{ $feature->name }}</th>@foreach($comparisonPlans as $package)
            @php($value = $package->entitlement_settings[$feature->key] ?? null)
            <td>@if($value === null || $value === '')<span class="plans-comparison-muted">—</span>@elseif($feature->type === 'boolean')<span class="{{ $value ? 'plans-comparison-yes' : 'plans-comparison-muted' }}">{{ $value ? __('Yes') : __('No') }}</span>@elseif(in_array($feature->type, ['capacity', 'quota'], true)){{ (int) $value === -1 ? __('Unlimited') : number_format((int) $value) }}@if($feature->type === 'quota')<small>{{ __(['monthly' => 'Per month', 'trial' => 'Total during trial', 'billing_cycle' => 'Per billing cycle'][data_get($package->meta, 'allowance_period', 'billing_cycle')] ?? 'Per billing cycle') }}</small>@endif @else{{ __($value) }}@endif</td>
        @endforeach</tr>@endforeach
        </tbody>
    </table></div>
</section>
@endif
