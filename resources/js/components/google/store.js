import Vue from 'vue';
import axios from 'axios';

/**
 * Shared state for the Google Suite screens: booted once from the page
 * (GoogleSuitePresenter), then kept in sync by the screens' API calls.
 */
export const suite = Vue.observable({
    screen: 'overview',
    locale: 'en',
    dir: 'ltr',
    canManage: false,
    configured: false,
    account: { connected: false },
    properties: { ga4: [], search_console: [] },
    preferences: {},
    events: [],
    nextSyncAt: null,
    urls: { api: {} },
    messages: {},
    extra: {},
    range: { key: '28d', start: null, end: null },
    compare: true,
    toast: null,
});

export const SERVICES = ['ga4', 'search_console'];

/* Categorical series colours - fixed per entity, validated (dataviz validator, light surface). */
export const COLORS = { brand: '#064BFF', ga4: '#E8710A', search_console: '#1A73E8', google_ads: '#0B8A7A', pagespeed: '#188038', business_profile: '#188038', conversions: '#0B1F66', previous: '#9AA0B4' };
export const DEVICE_COLORS = { mobile: '#064BFF', desktop: '#E8710A', tablet: '#8FB0FF' };

export function init(boot) {
    suite.screen = boot.screen;
    suite.locale = boot.locale;
    suite.dir = boot.dir;
    suite.canManage = boot.can_manage;
    suite.configured = boot.configured;
    suite.account = boot.account || { connected: false };
    suite.properties = boot.properties || { ga4: [], search_console: [] };
    suite.preferences = boot.preferences || {};
    suite.events = boot.events || [];
    suite.nextSyncAt = boot.next_sync_at;
    suite.urls = boot.urls;
    suite.messages = boot.messages || {};
    suite.extra = boot;
    suite.compare = suite.preferences.compare_previous !== false;

    const query = new URLSearchParams(window.location.search);
    const key = query.get('range') || suite.preferences.default_range || '28d';
    suite.range = { key, start: query.get('start'), end: query.get('end') };
}

/* ---------------- i18n ---------------- */

export function raw(key) {
    return key.split('.').reduce((node, part) => (node && node[part] !== undefined ? node[part] : undefined), suite.messages);
}

export function t(key, params = {}) {
    let value = raw(key);
    if (typeof value !== 'string') return key;
    Object.keys(params).forEach((name) => {
        value = value.split(':' + name).join(params[name] === null || params[name] === undefined ? '' : String(params[name]));
    });
    return value;
}

export const serviceName = (service) => t('services.' + service);

/* ---------------- formatting ---------------- */

const numberLocale = () => (suite.locale === 'ar' ? 'ar-SA-u-nu-latn' : 'en-US');

export const fmt = {
    int: (v) => (v === null || v === undefined ? '—' : new Intl.NumberFormat(numberLocale(), { maximumFractionDigits: 0 }).format(v)),
    compact: (v) => (v === null || v === undefined ? '—' : new Intl.NumberFormat(numberLocale(), { notation: 'compact', maximumFractionDigits: v >= 1000 ? 1 : 0 }).format(v)),
    /* GA4 / Search Console rates are fractions (0.638 = 63.8%). */
    pct: (v, digits = 1) => (v === null || v === undefined ? '—' : new Intl.NumberFormat(numberLocale(), { style: 'percent', maximumFractionDigits: digits, minimumFractionDigits: digits }).format(v)),
    dec: (v, digits = 1) => (v === null || v === undefined ? '—' : new Intl.NumberFormat(numberLocale(), { maximumFractionDigits: digits, minimumFractionDigits: digits }).format(v)),
    duration: (seconds) => {
        const s = Math.round(seconds || 0);
        const m = Math.floor(s / 60);
        return m > 0 ? `${m}m ${String(s % 60).padStart(2, '0')}s` : `${s}s`;
    },
    money: (v, currency) => {
        if (!currency) return fmt.compact(v);
        try {
            return new Intl.NumberFormat(numberLocale(), { style: 'currency', currency, notation: v >= 10000 ? 'compact' : 'standard', maximumFractionDigits: v >= 10000 ? 1 : 0 }).format(v);
        } catch (e) {
            return `${currency} ${fmt.compact(v)}`;
        }
    },
    date: (iso, opts = { day: 'numeric', month: 'short' }) => {
        if (!iso) return '—';
        const d = iso.length === 10 ? new Date(iso + 'T00:00:00') : new Date(iso);
        return new Intl.DateTimeFormat(suite.locale === 'ar' ? 'ar-SA-u-nu-latn-ca-gregory' : 'en-GB', opts).format(d);
    },
    datetime: (iso) => fmt.date(iso, { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' }),
    ago: (iso) => {
        if (!iso) return t('common.never');
        const minutes = Math.round((Date.now() - new Date(iso).getTime()) / 60000);
        if (minutes < 1) return t('common.just_now');
        if (minutes < 60) return t('common.minutes_ago', { n: minutes });
        if (minutes < 60 * 48) return t('common.hours_ago', { n: Math.round(minutes / 60) });
        return t('common.days_ago', { n: Math.round(minutes / 1440) });
    },
    until: (iso) => {
        if (!iso) return t('common.manual_only');
        const minutes = Math.max(1, Math.round((new Date(iso).getTime() - Date.now()) / 60000));
        return minutes < 90 ? t('common.in_minutes', { n: minutes }) : t('common.in_hours', { n: Math.round(minutes / 60) });
    },
    /* Search Console countries are ISO 3166-1 alpha-3 codes. */
    country: (code) => (code ? String(code).toUpperCase() : t('common.not_set')),
};

/** % change, or null when there is nothing to compare against. */
export function change(current, previous) {
    if (previous === null || previous === undefined || previous === 0) return null;
    return (current - previous) / Math.abs(previous);
}

/* ---------------- API ---------------- */

export function rangeParams() {
    const params = { range: suite.range.key };
    if (suite.range.key === 'custom') {
        params.start = suite.range.start;
        params.end = suite.range.end;
    }
    return params;
}

export function setRange(range) {
    suite.range = { key: range.key, start: range.start || null, end: range.end || null };
    const url = new URL(window.location.href);
    url.searchParams.set('range', range.key);
    ['start', 'end'].forEach((p) => (range.key === 'custom' && range[p] ? url.searchParams.set(p, range[p]) : url.searchParams.delete(p)));
    window.history.replaceState({}, '', url);
}

/** Calls an endpoint; failures reject with {code, message, reconnect?, retryable?, errors?}. */
export async function api(method, url, { params, data, responseType } = {}) {
    try {
        const response = await axios({ method, url, params, data, responseType, headers: { Accept: 'application/json' } });
        return response.data;
    } catch (error) {
        throw normaliseError(error);
    }
}

export async function normaliseErrorAsync(error) {
    if (error && error.response && error.response.data instanceof Blob) {
        try {
            error.response.data = JSON.parse(await error.response.data.text());
        } catch (e) { /* not JSON */ }
    }
    return normaliseError(error);
}

export function normaliseError(error) {
    if (error && error.code && error.message && !error.response) return error;
    const response = error && error.response;
    if (!response) return { code: 'network', message: t('errors.network'), retryable: true };
    const body = response.data || {};
    if (body.error && body.error.code) return body.error;
    if (response.status === 422) {
        const first = body.errors ? Object.values(body.errors)[0] : null;
        return { code: 'validation', message: (first && first[0]) || body.message || t('errors.generic'), errors: body.errors || {} };
    }
    if (response.status === 403) return { code: body.code === 'package_feature_denied' ? 'package' : 'forbidden', message: body.message || t('errors.forbidden') };
    if (response.status === 429) return { code: 'quota_exceeded', message: t('errors.quota_exceeded'), retryable: true };
    // Unrecognised failure: keep the status visible so support can trace it.
    return { code: 'generic', message: `${t('errors.generic')} (HTTP ${response.status})`, status: response.status, retryable: true };
}

/* ---------------- helpers ---------------- */

export function selected(service) {
    return (suite.properties[service] || []).find((p) => p.is_selected) || null;
}

export function grants(service) {
    return !!(suite.account.connected && suite.account.scopes && suite.account.scopes[service]);
}

export function toast(message, type = 'success') {
    suite.toast = { message, type, id: Date.now() };
    window.clearTimeout(toast.timer);
    toast.timer = window.setTimeout(() => { suite.toast = null; }, 4200);
}

export function eventText(event) {
    const service = ['ga4', 'search_console'].includes(event.service) ? serviceName(event.service) : serviceName('google');
    const base = t('events.' + event.event, { service, name: event.message || '' });
    return base === 'events.' + event.event ? event.event : base;
}
