@php
    $metricKeys = ['publishing_accounts', 'scheduled_published_posts', 'ai_credits', 'media_storage_gb'];
    $metrics = collect($metricKeys)->map(fn ($key) => $features->firstWhere('key', $key))->filter(fn ($feature) => $feature && isset($plan->entitlement_settings[$feature->key]));
    $benefitKeys = ['team_members', 'analytics_history_days', 'support_level', 'messaging_inbox_access'];
    $benefitRows = collect($benefitKeys)->map(fn ($key) => $features->firstWhere('key', $key))->filter(fn ($feature) => $feature && isset($plan->entitlement_settings[$feature->key]) && !in_array($plan->entitlement_settings[$feature->key], [false, 0, '', 'No'], true))->take(3);
    $periodLabel = __(['monthly' => 'Per month', 'trial' => 'Total during trial', 'billing_cycle' => 'Per billing cycle'][data_get($plan->meta, 'allowance_period', 'billing_cycle')] ?? 'Per billing cycle');
    $highlights = $presentation->highlights($plan, $features);
@endphp
                    @if($metrics->isNotEmpty())
                    <div class="se-package-metrics">
                        @foreach($metrics as $feature)
                        @php($value = $plan->entitlement_settings[$feature->key])
                        <div class="se-package-metric" data-home-feature="{{ $feature->key }}"><span>{{ $feature->name }}@if($feature->type === 'quota')<small>{{ $periodLabel }}</small>@endif</span><strong>{{ (int) $value === -1 ? '∞' : number_format((int) $value) }}</strong></div>
                        @endforeach
                    </div>
                    <ul class="se-package-benefits">@foreach($benefitRows as $feature)<li><span class="se-package-benefit-icon">@include('front.partials.home-icon', ['icon' => ['team_members' => 'team', 'analytics_history_days' => 'chart', 'support_level' => 'shield', 'messaging_inbox_access' => 'browser'][$feature->key]])</span><span class="se-package-benefit-label">{{ $feature->name }}</span><strong>{{ $presentation->value($plan, $feature) }}</strong></li>@endforeach</ul>
                    @else
                    <ul>@forelse(array_slice($highlights, 0, 6) as $highlight)<li>{{ $highlight }}</li>@empty<li>{{ __('Plan details are being updated. Check back soon for the full feature list.') }}</li>@endforelse</ul>
                    @endif
