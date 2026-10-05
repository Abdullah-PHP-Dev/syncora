<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
    @switch($icon)
        @case('spark')
            <path d="m12 2 2.8 6.8L22 12l-7.2 3.2L12 22l-2.8-6.8L2 12l7.2-3.2Z"/><path d="m20 2 .5 1.5L22 4l-1.5.5L20 6l-.5-1.5L18 4l1.5-.5Z"/>
            @break
        @case('calendar')
            <rect x="3" y="5" width="18" height="17" rx="3"/><path d="M7 2v6m10-6v6M3 10h18m-13 5h3v3H8z"/>
            @break
        @case('chart')
            <rect x="3" y="13" width="4" height="8" rx="1"/><rect x="10" y="8" width="4" height="13" rx="1"/><rect x="17" y="3" width="4" height="18" rx="1"/>
            @break
        @case('team')
            <circle cx="9" cy="7" r="4" fill="currentColor" stroke="none"/><path d="M2 20v-3a7 7 0 0 1 14 0v3Z" fill="currentColor" stroke="none"/><path d="M16 3a4 4 0 0 1 0 8m3 3a6 6 0 0 1 3 6"/>
            @break
        @case('play')
            <circle cx="12" cy="12" r="10"/><path d="m10 7 7 5-7 5Z" fill="currentColor" stroke="none"/>
            @break
    @endswitch
</svg>
