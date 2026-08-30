{{--
    A single Overview KPI tile - label / big value / a "foot" area whose
    content genuinely varies per metric (a trend arrow, or just plain
    text), which is why it's a slot rather than another prop trying to
    cover every case. Replaces four hand-copied `dash-card dash-stat`
    blocks in admin/posts/dashboard.blade.php - same classes render
    inside, so the existing .dash-stat-* CSS keeps working untouched.

    Props:
      label  string - the small caption above the value, e.g. "Total Reach"
      value  string - already formatted by the caller (dash_short()
                      output, an em-dash, or a "X%" string - the exact
                      formatting differs per metric, so this component
                      doesn't try to guess it)

    Slots:
      foot       - content rendered inside .dash-stat-foot (a trend arrow,
                   an "Across N platforms" line, etc.)
      valueExtra - optional; when passed, the value renders next to this
                   content in a flex row instead of alone (the Connected
                   Accounts card is the one metric that shows its mini
                   platform icons beside the number, not below it)
      default    - optional; anything rendered after .dash-stat-foot, as
                   a direct sibling rather than nested inside it (the
                   Engagement Rate / Total Reach cards each mount an
                   ApexCharts sparkline <div> there)
--}}
@props([
    'label',
    'value',
])

<div {{ $attributes->class(['dash-card', 'dash-stat']) }}>
    <div class="dash-stat-label">{{ $label }}</div>
    @isset($valueExtra)
        <div class="d-flex align-items-end justify-content-between">
            <div class="dash-stat-value">{{ $value }}</div>
            {{ $valueExtra }}
        </div>
    @else
        <div class="dash-stat-value">{{ $value }}</div>
    @endisset
    <div class="dash-stat-foot">
        {{ $foot ?? '' }}
    </div>
    {{ $slot }}
</div>
