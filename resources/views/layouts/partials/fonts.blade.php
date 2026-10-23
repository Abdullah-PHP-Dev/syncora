{{-- Inter for every page; IBM Plex Sans Arabic only on Arabic pages, where
     Arabic glyphs fall through to it (public/assets/css/typography.css).
     Inter is variable, so 400-900 is one file; Plex has no 800. --}}
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?{{ app()->getLocale() === 'ar' ? 'family=IBM+Plex+Sans+Arabic:wght@400;500;600;700&' : '' }}family=Inter:wght@400..900&display=swap" rel="stylesheet">
