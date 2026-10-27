import { suite, t, fmt, change } from './store';

/**
 * Insights built only from data the workspace's own connections returned.
 * kind 'measured' = a fact read from Google; kind 'suggestion' = a
 * recommendation drawn from those facts. Nothing is estimated beyond what
 * Google itself reports, and no insight claims what caused a change.
 */

const MIN_CHANGE = 0.1;

function trendInsight(key, current, previous, minPrevious, url) {
    const c = change(current, previous);
    if (c === null || previous < minPrevious || Math.abs(c) < MIN_CHANGE) return null;
    const up = c > 0;
    return {
        key,
        kind: 'measured',
        tone: up ? 'up' : 'down',
        title: t(`insights.${key}_${up ? 'up' : 'down'}`, { pct: fmt.pct(Math.abs(c), 0) }),
        text: t(`insights.${key}_text`, { current: fmt.int(current), previous: fmt.int(previous) }),
        url,
        action: t(`insights.${key}_action`),
    };
}

function lowConvertingPages(ga) {
    const total = ga.totals.current;
    if (!total.sessions || !total.key_events) return null;
    const siteRate = total.key_events / total.sessions;
    const floor = Math.max(30, total.sessions * 0.03);
    const pages = (ga.landing_pages || [])
        .filter((p) => p.sessions >= floor && p.key_events / p.sessions < siteRate * 0.5)
        .sort((a, b) => b.sessions - a.sessions);
    if (!pages.length) return null;
    const top = pages[0];
    return {
        key: 'low_converting',
        kind: 'measured',
        tone: 'warning',
        title: t('insights.low_converting', { n: pages.length }),
        text: t('insights.low_converting_text', {
            page: top.page || t('common.not_set'),
            sessions: fmt.int(top.sessions),
            rate: fmt.pct(top.key_events / top.sessions, 1),
            site: fmt.pct(siteRate, 1),
        }),
        url: suite.urls.analytics,
        action: t('insights.view_pages'),
    };
}

function ctrOpportunities(sc) {
    const queries = (sc.queries || []).filter((q) => q.impressions >= 100 && q.position > 3 && q.position <= 15);
    if (!queries.length) return null;
    queries.sort((a, b) => b.impressions - a.impressions);
    return {
        key: 'ctr_opportunity',
        kind: 'suggestion',
        tone: 'brand',
        title: t('insights.ctr_opportunity', { n: queries.length }),
        text: t('insights.ctr_opportunity_text', { query: queries[0].key, impressions: fmt.int(queries[0].impressions), position: fmt.dec(queries[0].position, 1) }),
        url: suite.urls.search_console,
        action: t('insights.view_queries'),
    };
}

function adsInsights(ads) {
    const out = [];
    if (ads.conversion_tracking === false) {
        out.push({ key: 'ads_tracking', kind: 'suggestion', tone: 'warning', title: t('insights.ads_tracking'), text: t('insights.ads_tracking_text'), url: suite.urls.advertising, action: t('insights.review') });
    }
    const c = ads.totals.current; const p = ads.totals.previous;
    const spend = change(c.cost, p.cost); const conv = change(c.conversions, p.conversions);
    if (spend !== null && conv !== null && spend >= MIN_CHANGE && conv <= -MIN_CHANGE) {
        out.push({
            key: 'ads_efficiency', kind: 'measured', tone: 'down',
            title: t('insights.ads_efficiency', { spend: fmt.pct(spend, 0), conv: fmt.pct(Math.abs(conv), 0) }),
            text: t('insights.ads_efficiency_text'), url: suite.urls.advertising, action: t('insights.view_campaigns'),
        });
    }
    return out;
}

function speedInsight(speed) {
    if (!speed || speed.score === null || speed.score >= 90) return null;
    const top = speed.opportunities[0];
    return {
        key: 'speed', kind: 'measured', tone: speed.score < 50 ? 'down' : 'warning',
        title: t('insights.speed', { score: speed.score, device: t('speed.' + speed.strategy).toLowerCase() }),
        text: top ? t('insights.speed_text', { n: speed.opportunities.length, top: top.title }) : t('insights.speed_text_none'),
        url: suite.urls.pagespeed, action: t('insights.view_report'),
    };
}

/** @returns {Array<{key, kind, tone, title, text, url, action}>} */
export function buildInsights({ ga = null, sc = null, ads = null, speed = null } = {}) {
    const items = [];
    if (sc) items.push(trendInsight('search_clicks', sc.totals.current.clicks, sc.totals.previous.clicks, 20, suite.urls.search_console));
    if (ga) items.push(trendInsight('sessions', ga.totals.current.sessions, ga.totals.previous.sessions, 50, suite.urls.analytics));
    if (ga) items.push(lowConvertingPages(ga));
    if (sc) items.push(ctrOpportunities(sc));
    if (ads) items.push(...adsInsights(ads));
    items.push(speedInsight(speed));
    return items.filter(Boolean);
}
