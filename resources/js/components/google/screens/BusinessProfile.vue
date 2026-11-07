<template>
  <div class="gs-screen">
    <div class="gs-head">
      <div>
        <p class="gs-eyebrow">{{ t('outcomes.local') }}</p>
        <h1>{{ t('gbp.title') }}</h1>
        <p class="gs-sub">
          <template v-if="location">{{ location.name }}<template v-if="location.address"> · {{ location.address }}</template></template>
          <template v-else>{{ t('gbp.subtitle') }}</template>
        </p>
      </div>
      <div v-if="ready" class="gs-head-actions">
        <label v-if="gbp.locations.length > 1" class="gs-gbp-picker">
          <span class="gs-visually-hidden">{{ t('gbp.location') }}</span>
          <select v-model.number="locationId" class="gs-input" :aria-label="t('gbp.location')" @change="switchLocation">
            <option v-for="l in gbp.locations" :key="l.id" :value="l.id">{{ l.name }}</option>
          </select>
        </label>
        <gs-range @change="load(false)" />
        <a :href="suite.urls.composer" class="gs-btn gs-btn-primary"><i class="bx bx-edit"></i>{{ t('gbp.create_post') }}</a>
      </div>
    </div>

    <!-- Not connected / missing permission / no locations: say exactly what to do, no figures. -->
    <section v-if="!ready" class="gs-card gs-pad gs-empty-hero">
      <gs-service-icon service="business_profile" />
      <div>
        <h2>{{ t('gbp.' + blocker + '_title') }}</h2>
        <p>{{ t('gbp.' + blocker + '_text') }}</p>
        <ul class="gs-tips">
          <li v-for="(p, i) in tr('gbp.connect_points')" :key="i"><i class="bx bx-check"></i>{{ p }}</li>
        </ul>
      </div>
      <a v-if="suite.canManage" :href="suite.urls.hub_google" class="gs-btn gs-btn-primary"><i class="bx bx-link"></i>{{ t(blocker === 'connect' ? 'gbp.connect' : 'gbp.reconnect') }}</a>
      <p v-else class="gs-muted sm">{{ t('gbp.owner_connects') }}</p>
    </section>

    <template v-else>
      <gs-state v-if="error" kind="error" :error="error" @retry="load(true)" />

      <template v-if="!error || d">
        <section class="gs-kpis" :aria-busy="loading ? 'true' : 'false'">
          <div v-for="k in kpis" :key="k" class="gs-kpi">
            <span class="gs-kpi-label">{{ t('gbp.kpi.' + k) }}</span>
            <strong v-if="d" class="gs-kpi-value">{{ fmt.int(d.totals.current[k]) }}</strong>
            <span v-else class="gs-skel lg" aria-hidden="true"></span>
            <gs-delta v-if="d && suite.compare" :current="d.totals.current[k]" :previous="d.totals.previous[k]" />
          </div>
        </section>

        <section class="gs-card gs-pad">
          <div class="gs-card-head">
            <div>
              <p class="gs-eyebrow">{{ t('analytics.trend') }}</p>
              <div class="gs-seg" role="tablist">
                <button v-for="m in trendMetrics" :key="m" type="button" role="tab" :aria-selected="metric === m ? 'true' : 'false'" :class="{ active: metric === m }" @click="metric = m">{{ t('gbp.kpi.' + m) }}</button>
              </div>
            </div>
            <div class="gs-legend">
              <span><span class="gs-line-key" :style="{ background: COLORS.business_profile }"></span>{{ t('common.this_period') }}</span>
              <span v-if="suite.compare"><span class="gs-line-key dashed"></span>{{ t('common.previous') }}</span>
            </div>
          </div>
          <gs-state v-if="loading && !d" kind="loading" compact />
          <gs-state v-else-if="empty" kind="empty" :title="t('gbp.empty_title')" :text="t('gbp.empty_text')" compact />
          <gs-line-chart v-else-if="d" :series="trendSeries" :labels="d.trend.dates" area :aria-label="t('gbp.kpi.' + metric)" :height="240" />
        </section>

        <div v-if="d && !empty" class="gs-grid-2">
          <section class="gs-card gs-pad">
            <div class="gs-card-head"><div><h2>{{ t('gbp.sources_title') }}</h2><p class="gs-sub sm">{{ t('gbp.sources_sub') }}</p></div></div>
            <gs-donut :items="sourceItems" :aria="t('gbp.sources_title')" />
          </section>
          <section class="gs-card gs-pad">
            <div class="gs-card-head"><div><h2>{{ t('gbp.action_rate') }}</h2><p class="gs-sub sm">{{ t('gbp.action_rate_sub') }}</p></div></div>
            <strong class="gs-kpi-value">{{ d.action_rate === null ? '—' : fmt.pct(d.action_rate) }}</strong>
            <p class="gs-muted sm">{{ t('gbp.action_rate_hint') }}</p>
          </section>
        </div>
      </template>

      <!-- Posts SocialEaz published (or is publishing) to this location. -->
      <section v-if="d" class="gs-card gs-pad">
        <div class="gs-card-head">
          <div><h2>{{ t('gbp.posts_title') }}</h2><p class="gs-sub sm">{{ t('gbp.posts_sub') }}</p></div>
          <a :href="suite.urls.composer" class="gs-btn gs-btn-ghost"><i class="bx bx-plus"></i>{{ t('gbp.create_post') }}</a>
        </div>
        <ul v-if="d.posts && d.posts.length" class="gs-gbp-posts">
          <li v-for="p in d.posts" :key="p.id" class="gs-gbp-post">
            <img v-if="p.thumbnail" :src="p.thumbnail" alt="" class="gs-gbp-thumb" loading="lazy">
            <div class="gs-gbp-post-body">
              <p dir="auto">{{ p.content || t('gbp.photo_only') }}</p>
              <span class="gs-muted sm">
                <span class="gs-chip" :class="postTone(p.status)">{{ t('gbp.post_status.' + p.status) }}</span>
                {{ fmt.date(p.at, { day: 'numeric', month: 'short', year: 'numeric' }) }}
                <template v-if="p.url"> · <a :href="p.url" target="_blank" rel="noopener" class="gs-link sm">{{ t('gbp.view_post') }}</a></template>
              </span>
              <p v-if="p.error" class="gs-field-error sm">{{ p.error }}</p>
            </div>
          </li>
        </ul>
        <gs-state v-else kind="empty" :text="t('gbp.no_posts')" compact />
      </section>

      <!-- Reviews: newest first; replies are public on Google Maps. -->
      <section class="gs-card gs-pad" :aria-busy="reviewsLoading ? 'true' : 'false'">
        <div class="gs-card-head">
          <div>
            <h2>{{ t('gbp.reviews_title') }}</h2>
            <p v-if="reviews" class="gs-sub sm">
              <template v-if="reviews.average_rating !== null"><span class="gs-stars" :aria-label="t('gbp.rating_aria', { n: reviews.average_rating })">★</span> {{ fmt.dec(reviews.average_rating, 1) }} · </template>{{ t('gbp.reviews_total', { n: fmt.int(reviews.total) }) }}
            </p>
          </div>
          <div class="gs-seg" role="tablist">
            <button v-for="f in ['all', 'unanswered']" :key="f" type="button" role="tab" :aria-selected="filter === f ? 'true' : 'false'" :class="{ active: filter === f }" @click="filter = f">{{ t('gbp.filter_' + f) }}</button>
          </div>
        </div>

        <gs-state v-if="reviewsError" kind="error" :error="reviewsError" compact @retry="loadReviews()" />
        <div v-else-if="reviewsLoading && !reviews" class="gs-skel-block"><span v-for="n in 3" :key="n" class="gs-skel"></span></div>
        <gs-state v-else-if="reviews && !shownReviews.length" kind="empty" :text="t(filter === 'unanswered' ? 'gbp.no_unanswered' : 'gbp.no_reviews')" compact />

        <ul v-else-if="reviews" class="gs-reviews">
          <li v-for="r in shownReviews" :key="r.id" class="gs-review">
            <div class="gs-review-head">
              <strong>{{ r.author || t('gbp.anonymous') }}</strong>
              <span class="gs-stars" :aria-label="t('gbp.rating_aria', { n: r.rating })">{{ '★'.repeat(r.rating || 0) }}<span class="gs-stars-off">{{ '★'.repeat(5 - (r.rating || 0)) }}</span></span>
              <span class="gs-muted sm">{{ fmt.date(r.created_at, { day: 'numeric', month: 'short', year: 'numeric' }) }}</span>
            </div>
            <p v-if="r.comment" class="gs-review-text" dir="auto">{{ r.comment }}</p>
            <p v-else class="gs-muted sm">{{ t('gbp.rating_only') }}</p>

            <div v-if="r.reply && editing !== r.id" class="gs-review-reply">
              <span class="gs-label">{{ t('gbp.your_reply') }}</span>
              <p dir="auto">{{ r.reply.comment }}</p>
            </div>

            <form v-if="editing === r.id" class="gs-review-form" @submit.prevent="saveReply(r)">
              <label class="gs-field">
                <span class="gs-label">{{ t('gbp.reply_label') }}</span>
                <textarea v-model="draft" class="gs-input" rows="4" maxlength="4000" dir="auto" :disabled="saving" required></textarea>
              </label>
              <p class="gs-muted sm">{{ t('gbp.reply_public') }}</p>
              <span v-if="replyError" class="gs-field-error">{{ replyError }}</span>
              <div class="gs-row">
                <button type="submit" class="gs-btn gs-btn-primary" :disabled="saving || !draft.trim()">
                  <span v-if="saving" class="gs-spinner sm light" aria-hidden="true"></span>{{ t('gbp.publish_reply') }}
                </button>
                <button type="button" class="gs-btn gs-btn-ghost" :disabled="saving" @click="editing = null">{{ t('common.cancel') }}</button>
              </div>
            </form>
            <button v-else type="button" class="gs-link sm" @click="startReply(r)">
              <i class="bx" :class="r.reply ? 'bx-edit' : 'bx-reply'"></i>{{ t(r.reply ? 'gbp.edit_reply' : 'gbp.reply') }}
            </button>
          </li>
        </ul>

        <button v-if="reviews && reviews.next_page_token" type="button" class="gs-btn gs-btn-ghost" :disabled="reviewsLoading" @click="loadReviews(true)">{{ t('gbp.more_reviews') }}</button>
      </section>

      <p class="gs-note">
        <i class="bx bx-info-circle"></i>{{ t('gbp.source_note') }}
        <template v-if="location && location.maps_uri"> · <a :href="location.maps_uri" target="_blank" rel="noopener" class="gs-link">{{ t('gbp.view_on_maps') }}</a></template>
        <template v-if="fetchedAt"> · {{ t('common.last_synced') }} {{ fmt.ago(fetchedAt) }}</template>
      </p>
    </template>
  </div>
</template>

<script>
import { suite, t, raw, fmt, api, rangeParams, toast, COLORS } from '../store';
import GsRange from '../ui/GsRange.vue';
import GsDelta from '../ui/GsDelta.vue';
import GsState from '../ui/GsState.vue';
import GsDonut from '../ui/GsDonut.vue';
import GsLineChart from '../ui/GsLineChart.vue';
import GsServiceIcon from '../ui/GsServiceIcon.vue';

/* Google Business Profile: performance (views, calls, directions, website clicks), reviews and replies, per location. */
export default {
  name: 'GsBusinessProfile',
  components: { GsRange, GsDelta, GsState, GsDonut, GsLineChart, GsServiceIcon },
  data() {
    const gbp = suite.extra.business_profile || { locations: [] };
    const fromUrl = Number(new URLSearchParams(window.location.search).get('location'));
    const ids = gbp.locations.map((l) => l.id);
    return {
      suite, COLORS,
      locationId: ids.includes(fromUrl) ? fromUrl : (ids[0] || null),
      loading: false, result: null, metric: 'views',
      reviews: null, reviewsError: null, reviewsLoading: false, filter: 'all',
      editing: null, draft: '', saving: false, replyError: '',
    };
  },
  computed: {
    fmt: () => fmt,
    gbp() { return suite.extra.business_profile || { locations: [] }; },
    blocker() {
      if (!this.gbp.connected) return 'connect';
      if (!this.gbp.granted) return 'scope';
      if (!this.gbp.locations.length) return 'no_locations';
      return null;
    },
    ready() { return this.blocker === null; },
    location() { return this.gbp.locations.find((l) => l.id === this.locationId) || null; },
    d() { return this.result && this.result.data; },
    error() { return this.result && this.result.error; },
    fetchedAt() { return this.result && this.result.fetched_at; },
    kpis: () => ['views', 'calls', 'directions', 'website_clicks', 'messages', 'bookings'],
    trendMetrics: () => ['views', 'calls', 'directions', 'website_clicks'],
    empty() { return this.d && Object.values(this.d.totals.current).every((v) => v === 0); },
    trendSeries() {
      const s = this.d.trend[this.metric];
      const label = t('gbp.kpi.' + this.metric);
      const out = [{ key: 'cur', label: `${label} · ${t('common.this_period')}`, color: COLORS.business_profile, values: s.current }];
      if (suite.compare) out.push({ key: 'prev', label: `${label} · ${t('common.previous')}`, color: COLORS.previous, values: s.previous, dashed: true });
      return out;
    },
    sourceItems() {
      return [
        { key: 'search', label: t('gbp.source_search'), value: this.d.view_sources.search, color: COLORS.search_console },
        { key: 'maps', label: t('gbp.source_maps'), value: this.d.view_sources.maps, color: COLORS.business_profile },
      ];
    },
    shownReviews() {
      const list = (this.reviews && this.reviews.reviews) || [];
      return this.filter === 'unanswered' ? list.filter((r) => !r.reply) : list;
    },
  },
  mounted() {
    if (this.ready) { this.load(false); this.loadReviews(); }
  },
  methods: {
    t,
    tr: (key) => raw(key) || [],
    switchLocation() {
      const url = new URL(window.location.href);
      url.searchParams.set('location', this.locationId);
      window.history.replaceState({}, '', url);
      this.result = null; this.reviews = null; this.editing = null;
      this.load(false); this.loadReviews();
    },
    async load(fresh) {
      this.loading = true;
      try {
        this.result = await api('get', suite.urls.api.business_profile, { params: { ...rangeParams(), location: this.locationId, fresh: fresh ? 1 : 0 } });
      } catch (e) { this.result = { error: e }; } finally { this.loading = false; }
    },
    async loadReviews(more = false) {
      this.reviewsLoading = true; this.reviewsError = null;
      try {
        const res = await api('get', suite.urls.api.business_profile_reviews, { params: { location: this.locationId, page_token: more && this.reviews ? this.reviews.next_page_token : undefined } });
        if (res.error) { this.reviewsError = res.error; return; }
        this.reviews = more && this.reviews ? { ...res.data, reviews: [...this.reviews.reviews, ...res.data.reviews] } : res.data;
      } catch (e) { this.reviewsError = e; } finally { this.reviewsLoading = false; }
    },
    postTone(status) { return { live: 'is-success', failed: 'is-danger', scheduled: 'is-brand' }[status] || 'is-neutral'; },
    startReply(r) { this.editing = r.id; this.draft = r.reply ? r.reply.comment : ''; this.replyError = ''; },
    async saveReply(r) {
      this.saving = true; this.replyError = '';
      try {
        const res = await api('put', suite.urls.api.business_profile_reply, { data: { location: this.locationId, review_id: r.id, comment: this.draft.trim() } });
        r.reply = res.data.reply;
        this.editing = null;
        toast(res.message || t('gbp.reply_saved'));
      } catch (e) {
        this.replyError = (e.errors && e.errors.comment && e.errors.comment[0]) || e.message;
      } finally { this.saving = false; }
    },
  },
};
</script>
