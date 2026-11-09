<template>
  <div class="sp" :dir="i18n.dir">

    <!-- Hero -->
    <section class="sp-hero">
      <span class="sp-eyebrow"><i class="bx bxs-zap" aria-hidden="true"></i> {{ t('eyebrow') }}</span>
      <h1>{{ t('title') }} <span class="sp-grad-text">{{ t('title_highlight') }}</span></h1>
      <p>{{ t('subtitle') }}</p>

      <div class="sp-cycle" role="radiogroup" :aria-label="t('monthly') + ' / ' + t('yearly')">
        <button v-for="c in ['monthly', 'yearly']" :key="c" type="button" role="radio" class="sp-cycle-btn" :data-cycle="c"
                :class="{ 'is-active': billingCycle === c }" :aria-checked="billingCycle === c ? 'true' : 'false'" @click="billingCycle = c">
          {{ t(c) }}
          <span v-if="c === 'yearly' && bestSaving > 0" class="sp-save-chip">{{ t('save_up_to', { percent: bestSaving }) }}</span>
        </button>
      </div>
    </section>

    <!-- Current plan -->
    <section v-if="currentSubscription" class="sp-current" :aria-label="t('your_plan')">
      <span class="sp-current-icon"><i class="bx bx-crown" aria-hidden="true"></i></span>
      <div class="sp-current-main">
        <small>{{ t('your_plan') }}</small>
        <strong>{{ currentSubscription.bundle_name }} <span class="sp-status" :class="'is-' + currentSubscription.status">{{ statusLabel(currentSubscription.status) }}</span></strong>
        <div v-if="period" class="sp-period">
          <div class="sp-period-bar" role="progressbar" :aria-valuenow="period.used" aria-valuemin="0" aria-valuemax="100"><span :style="{ width: period.used + '%' }"></span></div>
          <small>{{ tc('days_left', period.left) }}</small>
        </div>
      </div>
      <dl class="sp-current-dates">
        <div><dt>{{ t('started') }}</dt><dd>{{ formatDate(currentSubscription.start_date) }}</dd></div>
        <div><dt>{{ currentSubscription.status === 'active' ? t('renews') : t('ends') }}</dt><dd>{{ formatDate(currentSubscription.end_date) }}</dd></div>
      </dl>
      <a v-if="urls.usage" :href="urls.usage" class="sp-btn sp-btn-ghost">{{ t('usage') }} <i class="bx bx-right-arrow-alt sp-flip" aria-hidden="true"></i></a>
    </section>

    <!-- Plans -->
    <div v-if="loading" class="sp-grid" aria-busy="true" :aria-label="t('loading')">
      <div v-for="n in 3" :key="n" class="sp-card sp-skeleton"><span></span><span></span><span></span><span></span></div>
    </div>
    <p v-else-if="failed" class="sp-empty" role="alert">{{ t('load_failed') }}</p>
    <p v-else-if="!packages.length" class="sp-empty">{{ t('empty') }}</p>

    <div v-else class="sp-grid">
      <article v-for="(p, index) in packages" :key="p.id" class="sp-card"
               :class="{ 'is-popular': p.is_recommended && !p.is_current, 'is-current': p.is_current }">
        <span v-if="p.is_current" class="sp-ribbon is-current"><i class="bx bx-check-circle" aria-hidden="true"></i> {{ t('current_plan') }}</span>
        <span v-else-if="p.is_recommended" class="sp-ribbon"><i class="bx bxs-star" aria-hidden="true"></i> {{ t('most_popular') }}</span>

        <div class="sp-card-head">
          <span class="sp-plan-icon"><i class="bx" :class="planIcon(index)" aria-hidden="true"></i></span>
          <h2>{{ p.name }}</h2>
          <p v-if="p.description">{{ p.description }}</p>
        </div>

        <div class="sp-price">
          <div class="sp-price-line">
            <span class="sp-currency">{{ p.currency }}</span>
            <strong>{{ formatPrice(perMonth(p)) }}</strong>
            <span class="sp-per">{{ t('per_month') }}</span>
          </div>
          <div class="sp-price-note">
            <template v-if="billingCycle === 'yearly'">
              {{ t('billed_yearly', { amount: formatPrice(p.yearly.price), currency: p.currency }) }}
              <span v-if="saving(p) > 0" class="sp-save-chip sm">{{ t('save', { percent: saving(p) }) }}</span>
            </template>
            <template v-else>{{ t('billed_monthly') }}</template>
          </div>
        </div>

        <template v-if="p.can_select === false">
          <button type="button" class="sp-btn sp-btn-block sp-btn-disabled" disabled aria-disabled="true">{{ p.blocked_label }}</button>
          <p class="sp-blocked">{{ p.selection_message }}</p>
        </template>
        <a v-else :href="checkoutUrl(p)" class="sp-btn sp-btn-block" :class="p.is_recommended || p.is_current ? 'sp-btn-primary' : 'sp-btn-outline'">
          {{ p.is_current ? (p.selection_label || t('continue_current')) : t('choose', { plan: p.name }) }}
          <i class="bx bx-right-arrow-alt sp-flip" aria-hidden="true"></i>
        </a>

        <div class="sp-features">
          <p class="sp-features-title">{{ t('included') }}</p>
          <ul>
            <li v-for="(f, i) in highlights(p)" :key="'h' + i" class="is-on">
              <span class="sp-tick" aria-hidden="true"><i class="bx bx-check"></i></span><span>{{ f }}</span>
            </li>
            <li v-for="e in p.entitlements || []" :key="e.key" :class="included(e) ? 'is-on' : 'is-off'">
              <span class="sp-tick" aria-hidden="true"><i class="bx" :class="included(e) ? 'bx-check' : 'bx-minus'"></i></span>
              <span class="sp-feature-name">{{ e.name }}</span>
              <span v-if="valueLabel(e)" class="sp-feature-value">{{ valueLabel(e) }}</span>
              <span v-if="!included(e)" class="sr-only">{{ t('not_included') }}</span>
            </li>
          </ul>
        </div>
      </article>
    </div>

    <!-- Compare -->
    <section v-if="!loading && packages.length > 1 && compareRows.length" class="sp-compare">
      <div class="sp-section-head">
        <h2>{{ t('compare') }}</h2>
        <p>{{ t('compare_sub') }}</p>
      </div>
      <div class="sp-table-wrap">
        <table class="sp-table">
          <thead>
            <tr>
              <th scope="col">{{ t('feature') }}</th>
              <th v-for="p in packages" :key="p.id" scope="col" :class="{ 'is-popular': p.is_recommended }">
                {{ p.name }}
                <small>{{ p.currency }} {{ formatPrice(perMonth(p)) }} {{ t('per_month') }}</small>
              </th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="row in compareRows" :key="row.key">
              <th scope="row">{{ row.name }}</th>
              <td v-for="cell in row.cells" :key="cell.id" :class="{ 'is-popular': cell.popular }">
                <span v-if="cell.label" class="sp-cell-value">{{ cell.label }}</span>
                <span v-else-if="cell.on" class="sp-cell-yes" role="img" :aria-label="t('included')"><i class="bx bx-check" aria-hidden="true"></i></span>
                <span v-else class="sp-cell-no" role="img" :aria-label="t('not_included')"><i class="bx bx-minus" aria-hidden="true"></i></span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>

    <!-- Trust -->
    <section class="sp-trust">
      <div v-for="(icon, key) in trustIcons" :key="key" class="sp-trust-item">
        <span class="sp-trust-icon"><i class="bx" :class="icon" aria-hidden="true"></i></span>
        <div><strong>{{ t('trust.' + key + '.title') }}</strong><span>{{ t('trust.' + key + '.text') }}</span></div>
      </div>
    </section>
    <p v-if="urls.help" class="sp-help">{{ t('help') }} <a :href="urls.help">{{ t('help_link') }} <i class="bx bx-right-arrow-alt sp-flip" aria-hidden="true"></i></a></p>
  </div>
</template>

<script>
import { createTranslator } from '../ads/facebook/i18n';

const ICONS = ['bx-rocket', 'bxs-star', 'bx-buildings', 'bx-diamond'];

export default {
  name: 'SubscriptionPlans',

  props: {
    // subs.blade.php: localized plans endpoint, usage and help links.
    urls: { type: Object, default: () => ({ plans: '/subscription/plans' }) },
    // __('subscriptions.page') for the current locale.
    messages: { type: Object, default: () => ({}) },
    locale: { type: String, default: 'en' }
  },

  data() {
    return {
      loading: false,
      failed: false,
      packages: [],
      currentSubscription: null,
      billingCycle: 'monthly',
      trustIcons: { secure: 'bx-shield-quarter', payments: 'bx-credit-card', flexible: 'bx-transfer-alt' }
    };
  },

  computed: {
    i18n() { return createTranslator(this.messages, this.locale); },

    bestSaving() {
      return this.packages.reduce((best, p) => Math.max(best, this.saving(p)), 0);
    },

    // How far through the current period, and days left.
    period() {
      const s = this.currentSubscription;
      if (!s || !s.start_date || !s.end_date) return null;
      const start = new Date(s.start_date).getTime();
      const end = new Date(s.end_date).getTime();
      if (!(end > start)) return null;
      const now = Date.now();
      return {
        used: Math.min(100, Math.max(0, Math.round(100 * (now - start) / (end - start)))),
        left: Math.max(0, Math.ceil((end - now) / 86400000))
      };
    },

    // Every catalog feature at least one plan includes, compared across plans.
    compareRows() {
      const first = this.packages[0];
      if (!first || !first.entitlements) return [];
      return first.entitlements.map(e => ({
        key: e.key,
        name: e.name,
        cells: this.packages.map(p => {
          const entry = (p.entitlements || []).find(x => x.key === e.key) || { type: e.type, value: null };
          return { id: p.id, on: this.included(entry), label: this.included(entry) ? this.valueLabel(entry) : null, popular: p.is_recommended };
        })
      })).filter(row => row.cells.some(c => c.on));
    }
  },

  mounted() {
    this.loadPlans();
  },

  methods: {
    t(key, params) { return this.i18n.t(key, params); },
    tc(key, n, params) { return this.i18n.tc(key, n, params); },

    loadPlans() {
      this.loading = true;
      this.failed = false;
      window.axios.get(this.urls.plans || '/subscription/plans')
        .then(response => {
          const data = response.data.data || {};
          this.packages = data.packages || [];
          this.currentSubscription = data.current_subscription || null;
        })
        .catch(() => { this.failed = true; })
        .finally(() => { this.loading = false; });
    },

    planIcon(index) { return ICONS[index % ICONS.length]; },

    perMonth(p) {
      return this.billingCycle === 'yearly' ? Number(p.yearly.price) / 12 : Number(p.monthly.price);
    },

    // Yearly vs twelve monthly payments, rounded down so it never overstates.
    saving(p) {
      const monthly = Number(p.monthly.price) * 12;
      const yearly = Number(p.yearly.price);
      return monthly > 0 && yearly > 0 && yearly < monthly ? Math.floor(100 * (1 - yearly / monthly)) : 0;
    },

    // Admin-written highlight lines (Plans > display features), shown above the catalog.
    highlights(p) {
      return Array.isArray(p.display_features) ? p.display_features.filter(Boolean) : [];
    },

    included(e) {
      const v = e.value;
      if (e.type === 'boolean') return v === true || v === 1 || v === '1';
      if (e.type === 'capacity' || e.type === 'quota') return Number(v) === -1 || Number(v) > 0;
      return v !== null && v !== undefined && v !== '' && v !== false;
    },

    valueLabel(e) {
      if (!this.included(e) || e.type === 'boolean') return null;
      if (e.type === 'capacity' || e.type === 'quota') {
        return Number(e.value) === -1 ? this.t('unlimited') : Number(e.value).toLocaleString(this.i18n.intl);
      }
      return String(e.value);
    },

    statusLabel(status) {
      const label = this.t('statuses.' + status);
      return label.startsWith('statuses.') ? status : label;
    },

    formatPrice(price) {
      const n = Number(price) || 0;
      const whole = Math.abs(n - Math.round(n)) < 0.005;
      return n.toLocaleString(this.i18n.intl, { minimumFractionDigits: whole ? 0 : 2, maximumFractionDigits: 2 });
    },

    formatDate(date) {
      if (!date) return '—';
      return new Date(date).toLocaleDateString(this.locale === 'ar' ? 'ar-SA-u-nu-latn-ca-gregory' : 'en-GB', { day: 'numeric', month: 'short', year: 'numeric' });
    },

    checkoutUrl(p) {
      const planId = this.billingCycle === 'yearly' ? p.yearly.plan_id : p.monthly.plan_id;
      return `${p.checkout_url}?plan_id=${encodeURIComponent(planId)}&cycle=${this.billingCycle}`;
    }
  }
};
</script>

<style scoped>
.sp {
  /* Socialeaz brand (brand-theme.css, loaded by layouts.app). */
  --purple: var(--theme-purple, #720aff); --blue: var(--theme-blue, #064bff); --grad: var(--theme-gradient, linear-gradient(110deg, #004cff, #710bff));
  --soft: var(--theme-soft, #f1eaff); --ink: var(--theme-ink, #09072d); --muted: var(--theme-muted, #626498); --line: var(--theme-border, #edf0fa);
  --ok: #15803D;
  max-width: 1180px; margin: 0 auto; padding: 8px 0 48px; color: var(--ink);
}
.sp *, .sp *::before, .sp *::after { box-sizing: border-box; }
.sr-only { position: absolute; width: 1px; height: 1px; overflow: hidden; clip: rect(0,0,0,0); white-space: nowrap; }
[dir="rtl"] .sp-flip { transform: scaleX(-1); }

/* Hero */
.sp-hero { position: relative; padding: 48px 24px 40px; border-radius: 28px; text-align: center; overflow: hidden;
  background: radial-gradient(900px 300px at 50% -40%, rgba(114, 10, 255, .16), transparent 70%), radial-gradient(600px 260px at 100% 0, rgba(6, 75, 255, .10), transparent 70%), #fff;
  border: 1px solid var(--line); }
.sp-eyebrow { display: inline-flex; align-items: center; gap: 6px; padding: 7px 14px; border-radius: 999px; background: var(--soft); color: var(--purple); font-size: 12px; font-weight: 700; letter-spacing: .05em; text-transform: uppercase; }
.sp-hero h1 { margin: 18px auto 12px; max-width: 760px; font-size: clamp(30px, 4vw, 46px); line-height: 1.15; font-weight: 800; letter-spacing: -.03em; }
.sp-grad-text { background: var(--grad); -webkit-background-clip: text; background-clip: text; color: transparent; }
.sp-hero > p { max-width: 560px; margin: 0 auto; color: var(--muted); font-size: 16px; line-height: 1.7; }

.sp-cycle { display: inline-flex; gap: 4px; margin-top: 28px; padding: 5px; border: 1px solid var(--line); border-radius: 999px; background: #F7F8FD; }
.sp-cycle-btn { display: inline-flex; align-items: center; gap: 8px; min-height: 42px; padding: 0 22px; border: 0; border-radius: 999px; background: transparent; color: var(--muted); font: inherit; font-size: 14.5px; font-weight: 600; cursor: pointer; transition: background .15s, color .15s, box-shadow .15s; }
.sp-cycle-btn.is-active { background: #fff; color: var(--ink); box-shadow: 0 4px 14px rgba(9, 7, 45, .08); }
.sp-cycle-btn:focus-visible, .sp-btn:focus-visible { outline: 3px solid rgba(114, 10, 255, .35); outline-offset: 2px; }
.sp-save-chip { display: inline-flex; align-items: center; padding: 3px 9px; border-radius: 999px; background: #DCFCE7; color: var(--ok); font-size: 11.5px; font-weight: 700; white-space: nowrap; }
.sp-save-chip.sm { margin-inline-start: 6px; padding: 2px 8px; font-size: 11px; }

/* Current plan */
.sp-current { display: flex; align-items: center; gap: 18px; flex-wrap: wrap; margin-top: 20px; padding: 18px 22px; border: 1px solid var(--line); border-radius: 20px; background: #fff; }
.sp-current-icon { display: grid; place-items: center; flex-shrink: 0; width: 48px; height: 48px; border-radius: 14px; background: var(--grad); color: #fff; font-size: 22px; }
.sp-current-main { flex: 1 1 260px; min-width: 0; display: flex; flex-direction: column; gap: 4px; }
.sp-current-main > small { color: var(--muted); font-size: 12px; font-weight: 600; letter-spacing: .05em; text-transform: uppercase; }
.sp-current-main > strong { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; font-size: 19px; }
.sp-status { padding: 3px 10px; border-radius: 999px; background: var(--soft); color: var(--purple); font-size: 12px; font-weight: 700; }
.sp-status.is-active { background: #DCFCE7; color: var(--ok); }
.sp-status.is-expired, .sp-status.is-cancelled { background: #FEE2E2; color: #B91C1C; }
.sp-period { display: flex; align-items: center; gap: 12px; margin-top: 4px; }
.sp-period-bar { flex: 1; max-width: 280px; height: 6px; border-radius: 999px; background: #EEF0F8; overflow: hidden; }
.sp-period-bar span { display: block; height: 100%; border-radius: inherit; background: var(--grad); }
.sp-period small { color: var(--muted); font-size: 12.5px; font-weight: 600; white-space: nowrap; }
.sp-current-dates { display: flex; gap: 28px; margin: 0; }
.sp-current-dates dt { color: var(--muted); font-size: 12px; font-weight: 600; }
.sp-current-dates dd { margin: 2px 0 0; font-size: 14.5px; font-weight: 700; }

/* Buttons */
.sp-btn { display: inline-flex; align-items: center; justify-content: center; gap: 8px; min-height: 44px; padding: 0 18px; border: 1px solid transparent; border-radius: 14px; font: inherit; font-size: 14.5px; font-weight: 700; text-decoration: none; white-space: nowrap; cursor: pointer; transition: box-shadow .15s, transform .15s, background .15s; }
.sp-btn i { font-size: 18px; }
.sp-btn-block { width: 100%; min-height: 50px; }
.sp-btn-primary { background: var(--grad); color: #fff; box-shadow: 0 10px 24px rgba(54, 11, 255, .22); }
.sp-btn-primary:hover { color: #fff; box-shadow: 0 14px 30px rgba(54, 11, 255, .32); transform: translateY(-1px); }
.sp-btn-outline { border-color: #D9C6FF; background: #fff; color: var(--purple); }
.sp-btn-outline:hover { background: var(--soft); color: var(--purple); }
.sp-btn-ghost { border-color: var(--line); background: #fff; color: var(--ink); }
.sp-btn-ghost:hover { border-color: #D9C6FF; color: var(--purple); }
.sp-btn-disabled { background: #F1F2F7; color: var(--muted); cursor: not-allowed; }

/* Plan cards */
.sp-grid { display: flex; flex-wrap: wrap; justify-content: center; gap: 22px; margin-top: 28px; }
.sp-card { position: relative; flex: 1 1 300px; max-width: 370px; display: flex; flex-direction: column; gap: 20px; padding: 28px 26px; border: 1px solid var(--line); border-radius: 24px; background: #fff; box-shadow: 0 1px 2px rgba(9, 7, 45, .04); transition: box-shadow .2s, transform .2s; }
.sp-card:hover { box-shadow: 0 18px 44px rgba(9, 7, 45, .08); transform: translateY(-2px); }
.sp-card.is-popular { border: 2px solid transparent; background: linear-gradient(#fff, #fff) padding-box, var(--grad) border-box; box-shadow: 0 24px 60px rgba(54, 11, 255, .14); }
.sp-card.is-current { border-color: #BBF7D0; }
.sp-ribbon { position: absolute; top: -13px; inset-inline-start: 50%; transform: translateX(-50%); display: inline-flex; align-items: center; gap: 5px; padding: 6px 14px; border-radius: 999px; background: var(--grad); color: #fff; font-size: 12px; font-weight: 700; white-space: nowrap; box-shadow: 0 8px 18px rgba(54, 11, 255, .25); }
[dir="rtl"] .sp-ribbon { transform: translateX(50%); }
.sp-ribbon.is-current { background: var(--ok); box-shadow: 0 8px 18px rgba(21, 128, 61, .22); }
.sp-card-head h2 { margin: 14px 0 6px; font-size: 22px; font-weight: 800; letter-spacing: -.01em; }
.sp-card-head p { margin: 0; color: var(--muted); font-size: 14px; line-height: 1.6; }
.sp-plan-icon { display: grid; place-items: center; width: 46px; height: 46px; border-radius: 14px; background: var(--soft); color: var(--purple); font-size: 22px; }
.is-popular .sp-plan-icon { background: var(--grad); color: #fff; }
.sp-price { padding: 18px 0; border-block: 1px solid var(--line); }
.sp-price-line { display: flex; align-items: baseline; gap: 6px; flex-wrap: wrap; }
.sp-currency { color: var(--muted); font-size: 15px; font-weight: 700; }
.sp-price-line strong { font-size: 44px; line-height: 1; font-weight: 800; letter-spacing: -.03em; }
.sp-per { color: var(--muted); font-size: 14px; font-weight: 600; }
.sp-price-note { display: flex; align-items: center; flex-wrap: wrap; gap: 4px; margin-top: 10px; color: var(--muted); font-size: 13px; }
.sp-blocked { margin: -10px 0 0; color: var(--muted); font-size: 12.5px; line-height: 1.5; }

.sp-features-title { margin: 0 0 12px; color: var(--muted); font-size: 12px; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; }
.sp-features ul { display: flex; flex-direction: column; gap: 11px; margin: 0; padding: 0; list-style: none; }
.sp-features li { display: flex; align-items: center; gap: 10px; font-size: 14.5px; }
.sp-features li.is-off { color: #9EA1C0; }
.sp-tick { display: grid; place-items: center; flex-shrink: 0; width: 22px; height: 22px; border-radius: 50%; background: var(--soft); color: var(--purple); font-size: 15px; }
.is-off .sp-tick { background: #F3F4F8; color: #B4B7CF; }
.sp-feature-name { flex: 1; min-width: 0; }
.sp-feature-value { flex-shrink: 0; padding: 2px 9px; border-radius: 999px; background: #F5F6FB; color: var(--ink); font-size: 12.5px; font-weight: 700; }

.sp-skeleton { min-height: 460px; gap: 16px; }
.sp-skeleton span { display: block; height: 18px; border-radius: 8px; background: linear-gradient(90deg, #F1F2F7 25%, #E8E9F2 37%, #F1F2F7 63%); background-size: 400% 100%; animation: sp-shimmer 1.3s ease infinite; }
.sp-skeleton span:nth-child(1) { width: 46px; height: 46px; border-radius: 14px; }
.sp-skeleton span:nth-child(2) { width: 50%; }
.sp-skeleton span:nth-child(3) { width: 70%; height: 44px; }
.sp-skeleton span:nth-child(4) { height: 50px; border-radius: 14px; }
@keyframes sp-shimmer { 0% { background-position: 100% 50%; } 100% { background-position: 0 50%; } }
.sp-empty { margin: 28px 0 0; padding: 28px; border: 1px dashed var(--line); border-radius: 20px; color: var(--muted); text-align: center; }

/* Compare */
.sp-compare { margin-top: 48px; }
.sp-section-head { text-align: center; margin-bottom: 20px; }
.sp-section-head h2 { margin: 0 0 6px; font-size: 26px; font-weight: 800; letter-spacing: -.02em; }
.sp-section-head p { margin: 0; color: var(--muted); font-size: 14.5px; }
.sp-table-wrap { border: 1px solid var(--line); border-radius: 20px; background: #fff; overflow-x: auto; }
.sp-table { width: 100%; min-width: 560px; border-collapse: collapse; font-size: 14.5px; }
.sp-table th, .sp-table td { padding: 14px 18px; border-bottom: 1px solid var(--line); text-align: center; }
.sp-table tr:last-child th, .sp-table tr:last-child td { border-bottom: 0; }
.sp-table th[scope="row"], .sp-table thead th:first-child { text-align: start; font-weight: 600; }
.sp-table thead th { background: #FAFAFE; font-size: 15px; font-weight: 800; vertical-align: bottom; }
.sp-table thead th small { display: block; margin-top: 4px; color: var(--muted); font-size: 12px; font-weight: 600; }
.sp-table .is-popular { background: rgba(114, 10, 255, .035); }
.sp-table thead th.is-popular { color: var(--purple); }
.sp-cell-yes { display: inline-grid; place-items: center; width: 24px; height: 24px; border-radius: 50%; background: var(--soft); color: var(--purple); font-size: 16px; }
.sp-cell-no { display: inline-grid; place-items: center; color: #C3C5DA; font-size: 18px; }
.sp-cell-value { font-weight: 700; }

/* Trust */
.sp-trust { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 16px; margin-top: 40px; }
.sp-trust-item { display: flex; gap: 14px; padding: 18px; border: 1px solid var(--line); border-radius: 18px; background: #fff; }
.sp-trust-icon { display: grid; place-items: center; flex-shrink: 0; width: 42px; height: 42px; border-radius: 12px; background: var(--soft); color: var(--purple); font-size: 20px; }
.sp-trust-item > div { display: flex; flex-direction: column; gap: 3px; }
.sp-trust-item strong { font-size: 15px; }
.sp-trust-item > div > span { color: var(--muted); font-size: 13px; line-height: 1.55; }
.sp-help { margin: 24px 0 0; color: var(--muted); font-size: 14px; text-align: center; }
.sp-help a { display: inline-flex; align-items: center; gap: 2px; color: var(--purple); font-weight: 700; text-decoration: none; }

@media (prefers-reduced-motion: reduce) {
  .sp-card, .sp-btn, .sp-cycle-btn { transition: none; }
  .sp-card:hover, .sp-btn-primary:hover { transform: none; }
  .sp-skeleton span { animation: none; }
}
@media (max-width: 991.98px) {
  .sp-trust { grid-template-columns: minmax(0, 1fr); }
}
@media (max-width: 640px) {
  .sp-hero { padding: 32px 16px 28px; border-radius: 22px; }
  .sp-cycle { display: flex; width: 100%; }
  .sp-cycle-btn { flex: 1; justify-content: center; padding: 0 10px; }
  .sp-current { padding: 16px; }
  .sp-current-dates { gap: 20px; }
  .sp-current .sp-btn-ghost { width: 100%; }
  .sp-card { max-width: none; padding: 24px 20px; }
  .sp-price-line strong { font-size: 38px; }
}
</style>
