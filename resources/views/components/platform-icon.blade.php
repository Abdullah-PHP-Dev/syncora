{{--
    Small solid-color platform icon badge - the exact look
    .social-icon-mini + a per-platform background class already had
    (see assets/css/admin.css), just driven by an inline color instead
    of a competing CSS class per platform.

    That per-platform CSS class approach is what this component
    replaces: admin/posts/dashboard.blade.php used to carry its own
    .socialeaz-dash-scoped copy of every platform's brand color
    (.social-icon-mini.facebook, .social-icon-mini.twitter, ...) on top
    of the ones already in the global assets/css/admin.css - and the
    two sets of values had drifted apart (Twitter/X, Google, and
    Instagram no longer matched between the two), so the same platform
    icon rendered a different color depending on which page/section it
    was in. Passing the color in from the one PHP source of truth
    ($platformBrandColors, built alongside $platformMeta) removes that
    second copy entirely.

    Props:
      icon   Boxicon suffix, e.g. 'bxl-facebook' (required)
      color  brand hex, e.g. '#1877F2' (defaults to the app's neutral
             accent purple so a missing platform still renders sanely)
      size   optional size variant - 'xs' adds the existing
             .social-icon-xs override class (used in compact table
             rows); the 22px "mini icons" size is still purely
             contextual (.dash-mini-icons .social-icon-mini) and needs
             no prop, it just falls out of where the component is
             placed
--}}
@props([
    'icon',
    'color' => '#7c5cff',
    'size' => null,
])

<span
    {{ $attributes->class(array_filter(['social-icon-mini', $size ? 'social-icon-'.$size : null])) }}
    style="background: {{ $color }};"
>
    <i class="bx {{ $icon }}"></i>
</span>
