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
        @case('link')
            <path d="m10 13 4-4m-6 6-2 2a4 4 0 0 1-5.7-5.7l4-4A4 4 0 0 1 10 6m4 12a4 4 0 0 0 5.7-.3l4-4A4 4 0 0 0 18 8l-2 2" transform="translate(1 -1) scale(.9)"/>
            @break
        @case('shield')
            <path d="M12 2 3 6v6c0 5 5 8 9 10 4-2 9-5 9-10V6L12 2Z"/><path d="m8 12 3 3 5-6"/>
            @break
        @case('check')
            <circle cx="12" cy="12" r="9"/><path d="m7 12 3 3 7-7"/>
            @break
        @case('publish')
            <path d="m21 3-7 18-4-7-7-4L21 3Z"/><path d="m10 14 6-6"/>
            @break
        @case('document')
            <path d="M14 2H5a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V9l-7-7Z"/><path d="M14 2v7h7M7 13h10M7 17h7"/>
            @break
        @case('browser')
            <rect x="2" y="3" width="20" height="18" rx="3"/><path d="M2 9h20M6 6h.01M10 6h.01M14 6h.01M7 13h10M7 17h6"/>
            @break
        @case('chevron-down')
            <path d="m6 9 6 6 6-6"/>
            @break
        @case('play')
            <circle cx="12" cy="12" r="10"/><path d="m10 7 7 5-7 5Z" fill="currentColor" stroke="none"/>
            @break
    @endswitch
</svg>
