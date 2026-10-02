import axios from 'axios';
window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

const csrfToken = document.head.querySelector('meta[name="csrf-token"]');
if (csrfToken) {
    window.axios.defaults.headers.common['X-CSRF-TOKEN'] = csrfToken.content;
}

import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

// Runtime config from the server (layouts/app.blade.php) wins, so one
// committed build works on every environment; Vite env is the fallback
// for pages that don't render it.
const reverb = window.reverbConfig ?? {
    key: import.meta.env.VITE_REVERB_APP_KEY,
    host: import.meta.env.VITE_REVERB_HOST,
    port: import.meta.env.VITE_REVERB_PORT,
    scheme: import.meta.env.VITE_REVERB_SCHEME,
};

// Without a key/host there is no Reverb to connect to - skip Echo instead
// of retrying a dead socket forever (pages check `if (window.Echo)`).
if (reverb.key && reverb.host) {
    const tls = (reverb.scheme ?? 'https') === 'https';
    window.Echo = new Echo({
        broadcaster: 'reverb',
        key: reverb.key,
        wsHost: reverb.host,
        wsPort: reverb.port || (tls ? 443 : 80),
        wssPort: reverb.port || 443,
        forceTLS: tls,
        enabledTransports: ['ws', 'wss'],
    });
}
