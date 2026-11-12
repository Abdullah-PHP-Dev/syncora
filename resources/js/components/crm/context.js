import { createApi, withId, errorMessage } from './api';

// Everything a CRM page needs, built once by CrmApp and injected as `crm`.
export function createContext({ props, urls, messages, locale, userName }) {
    const t = (key, params = {}) => {
        let value = key.split('.').reduce((node, part) => (node && node[part] !== undefined ? node[part] : undefined), messages);
        if (typeof value !== 'string') return key;
        Object.keys(params).forEach((name) => { value = value.split(`:${name}`).join(params[name]); });
        return value;
    };
    const numberLocale = locale === 'ar' ? 'ar-SA-u-nu-latn' : 'en-US';
    const ctx = {
        t, locale, userName, urls, withId,
        api: createApi(urls.api),
        members: props.members || [],
        options: props.options || {},
        pipelines: props.pipelines || [],
        fields: props.fields || [],
        canWrite: props.canWrite !== false,
        currency: props.currency || 'SAR',
        actorId: props.actorId,
        bus: null,
        error: (e) => errorMessage(e, t('common.error')),
        money(value, currency) {
            const n = Number(value || 0);
            const short = Math.abs(n) >= 1e6 ? `${(n / 1e6).toFixed(n % 1e6 ? 2 : 0)}M` : Math.abs(n) >= 1e4 ? `${(n / 1e3).toFixed(n % 1e3 ? 1 : 0)}K` : n.toLocaleString(numberLocale);
            return `${currency || ctx.currency} ${short}`;
        },
        number: (n) => Number(n || 0).toLocaleString(numberLocale),
        date(value, withTime = false) {
            if (!value) return '—';
            const d = new Date(value);
            return d.toLocaleDateString(locale === 'ar' ? 'ar-SA-u-nu-latn-ca-gregory' : 'en-GB', withTime
                ? { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' }
                : { day: 'numeric', month: 'short', year: 'numeric' });
        },
        ago(value) {
            if (!value) return '—';
            const seconds = Math.round((Date.now() - new Date(value).getTime()) / 1000);
            const rtf = new Intl.RelativeTimeFormat(locale, { numeric: 'auto' });
            const steps = [[60, 'second'], [3600, 'minute'], [86400, 'hour'], [604800, 'day'], [2629800, 'week'], [31557600, 'month']];
            if (Math.abs(seconds) < 45) return t('common.just_now');
            for (let i = 1; i < steps.length; i++) {
                if (Math.abs(seconds) < steps[i][0]) return rtf.format(-Math.round(seconds / steps[i - 1][0]), steps[i - 1][1]);
            }
            return rtf.format(-Math.round(seconds / 31557600), 'year');
        },
        initials(name) {
            const parts = (name || '?').trim().split(/\s+/).map((p) => p.replace(/^(al|el)-/i, '')).filter((p) => !/^(bin|ibn|abu|al|el)$/i.test(p));
            const picked = parts.length > 1 ? [parts[0], parts[parts.length - 1]] : parts;
            return picked.map((p) => p[0] || '').join('').toUpperCase() || '?';
        },
        avatarClass(id) { return `c${(Number(id) % 5) + 1}`; },
        scoreColor: (s) => (s >= 70 ? '#16a34a' : s >= 45 ? '#d97706' : '#94a3b8'),
        memberName(id) { return (ctx.members.find((m) => m.id === id) || {}).name || t('common.unassigned'); },
        sourceLabel(s) { return s ? t(`sources.${s}`).replace(/^sources\./, s) : '—'; },
        pageUrl(name, id) { return id ? withId(urls.pages[name], id) : urls.pages[name]; },
    };
    return ctx;
}
