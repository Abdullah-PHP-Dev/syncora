import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
            },
        },
    },

    // QA finding: this app's admin theme is Bootstrap-based, and Bootstrap
    // uses a class literally named "collapse" everywhere (accordions,
    // navbar toggles, etc: class="collapse show"). Tailwind's JIT scanner
    // sees that exact string in the scanned .blade.php files and generates
    // its OWN "collapse" utility for it (`visibility: collapse` - meant for
    // hiding table rows without a layout shift), which collides with
    // Bootstrap's completely unrelated class of the same name. Whichever
    // stylesheet's rule for `.collapse` lands last in the compiled CSS
    // wins, so this was intermittently hiding every Bootstrap accordion's
    // content across the app (confirmed live: a real FAQ answer's `<p>`
    // was correctly in the DOM but invisible due to `visibility: collapse`
    // with no corresponding `.show` override). Disabling Tailwind's
    // visibility corePlugin removes `visible`/`invisible`/`collapse` from
    // Tailwind's own generated output entirely - confirmed unused as
    // actual Tailwind utilities anywhere in this codebase (grepped for
    // class="...visible..."/"...invisible..." first), so nothing else
    // relies on them.
    corePlugins: {
        visibility: false,
    },

    plugins: [forms],
};
