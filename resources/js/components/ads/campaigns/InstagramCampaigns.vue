<template>
  <div class="igc" :dir="i18n.dir">
    <nav class="igc-crumbs" :aria-label="t('breadcrumb')">
      <a :href="data.urls.dashboard">{{ t('ads_manager') }}</a><span aria-hidden="true">/</span><span>Instagram</span>
    </nav>

    <!-- ================= HEADER ================= -->
    <header class="igc-card igc-head">
      <span class="igc-mark" aria-hidden="true"><i class="bx bxl-instagram"></i></span>
      <div class="igc-head-main">
        <div class="igc-head-title">
          <h1>{{ t('ig.title') }}</h1>
          <span v-if="!account" class="igc-pill mut"><span class="d"></span>{{ t('not_connected') }}</span>
          <span v-else-if="!account.healthy" class="igc-pill bad"><span class="d"></span>{{ t('reconnect_needed') }}</span>
          <span v-else class="igc-pill ok"><span class="d"></span>{{ t('connected') }}</span>
          <span class="igc-tagline">{{ t('ig.tagline') }}</span>
        </div>
        <div v-if="account" class="igc-head-meta">
          <a v-if="ig" :href="ig.username ? 'https://www.instagram.com/' + ig.username : data.urls.connections" target="_blank" rel="noopener" class="igc-chip">
            <img v-if="ig.avatar_url" :src="ig.avatar_url" alt="" class="igc-chip-av"><i v-else class="bx bxl-instagram igc-chip-ic"></i>
            <span class="lbl">Instagram</span><bdi dir="auto" class="nm">{{ ig.username ? '@' + ig.username : ig.name }}</bdi>
          </a>
          <a v-else :href="data.urls.connections" class="igc-chip warn" :title="t('ig.no_identity_hint')"><i class="bx bxl-instagram igc-chip-ic"></i>{{ t('ig.no_identity') }}</a>
          <div ref="switcher" class="igc-dd">
            <button type="button" class="igc-chip btn" :aria-expanded="switchOpen ? 'true' : 'false'" aria-haspopup="listbox" @click="switchOpen = !switchOpen">
              <span class="lbl">{{ t('ad_account') }}</span><bdi dir="auto" class="nm">{{ account.name }}</bdi>
              <span class="mut">· {{ account.masked_id }}<template v-if="account.currency"> · {{ account.currency }}</template></span><i class="bx bx-chevron-down"></i>
            </button>
            <div v-if="switchOpen" class="igc-menu wide" role="listbox" :aria-label="t('ad_accounts')">
              <a v-for="a in data.accounts" :key="a.id" :href="switchUrl(a)" role="option" :aria-selected="a.selected ? 'true' : 'false'" class="igc-menu-acct" :class="{ on: a.selected }">
                <span class="igc-av">{{ initials(a.name) }}</span>
                <span class="txt"><bdi dir="auto">{{ a.name }}</bdi><small>{{ a.currency || '—' }} · {{ a.masked_id }}</small></span>
                <i v-if="a.selected" class="bx bx-check"></i>
              </a>
              <a :href="data.urls.connections" class="igc-menu-foot"><i class="bx bx-plus"></i>{{ t('connect_another') }}</a>
            </div>
          </div>
          <span class="igc-meta-txt">
            <span :title="account.business_name ? null : t('business_unknown')">{{ t('business') }}: <bdi dir="auto">{{ account.business_name || '—' }}</bdi></span>
            <span class="sep" aria-hidden="true">·</span>
            <span><i class="bx bx-time-five"></i> {{ account.last_synced_at ? t('synced_at', { when: dateTime(account.last_synced_at) }) : t('never_synced') }}</span>
          </span>
        </div>
        <p v-else class="igc-meta-txt">{{ t('ig.connect_hint') }}</p>
      </div>
      <div class="igc-head-actions">
        <button v-if="account" type="button" class="igc-btn ghost" :disabled="syncing || !account.healthy" @click="syncNow"><i class="bx bx-sync" :class="{ 'bx-spin': syncing }"></i>{{ syncing ? t('syncing') : t('sync_now') }}</button>
        <a v-if="data.can_manage" :href="data.urls.create" class="igc-btn primary"><i class="bx bx-plus"></i>{{ t('create_campaign') }}</a>
        <a v-else-if="!account" :href="data.urls.connections" class="igc-btn primary"><i class="bx bx-link"></i>{{ t('connect') }}</a>
        <div ref="moreMenu" class="igc-dd">
          <button type="button" class="igc-btn ghost icon" :aria-label="t('more')" :aria-expanded="moreOpen ? 'true' : 'false'" @click="moreOpen = !moreOpen"><i class="bx bx-dots-horizontal-rounded"></i></button>
          <div v-if="moreOpen" class="igc-menu end" role="menu">
            <a v-if="account" :href="metaUrl" target="_blank" rel="noopener" role="menuitem"><i class="bx bx-link-external"></i>{{ t('open_in_meta') }}</a>
            <a :href="data.urls.connections" role="menuitem"><i class="bx bx-link"></i>{{ t('manage_connections') }}</a>
            <a :href="data.urls.dashboard" role="menuitem"><i class="bx bx-grid-alt"></i>{{ t('all_platforms') }}</a>
          </div>
        </div>
      </div>
    </header>

    <div v-if="account && data.manage_blocker" class="igc-note" :class="data.manage_blocker === 'token_expired' ? 'bad' : ''" role="note">
      <i class="bx" :class="data.manage_blocker === 'token_expired' ? 'bx-error-circle' : 'bx-lock-alt'"></i>
      <div><strong>{{ t('blockers.' + data.manage_blocker) }}</strong><p>{{ t('blockers.' + data.manage_blocker + '_text') }}</p></div>
      <a v-if="data.manage_blocker === 'token_expired'" :href="data.urls.connections" class="igc-btn ghost sm">{{ t('reconnect') }}</a>
    </div>
    <div v-if="toastMsg" class="igc-toast" :class="toastOk ? 'ok' : 'bad'" role="status">
      <i class="bx" :class="toastOk ? 'bx-check-circle' : 'bx-error-circle'"></i><span>{{ toastMsg }}</span>
      <button type="button" :aria-label="t('dismiss')" @click="toastMsg = ''"><i class="bx bx-x"></i></button>
    </div>

    <section v-if="!account" class="igc-card igc-empty">
      <span class="igc-empty-ic"><i class="bx bxl-instagram"></i></span>
      <h2>{{ t('ig.empty_title') }}</h2>
      <p>{{ t('ig.empty_text') }}</p>
      <a :href="data.urls.connections" class="igc-btn primary"><i class="bx bx-link"></i>{{ t('connect') }}</a>
    </section>

    <template v-else>
      <!-- ================= TABS + TOOLS ================= -->
      <div class="igc-bar">
        <div class="igc-tabs" role="tablist" :aria-label="t('objects')">
          <button v-for="tb in TABS" :key="tb" type="button" role="tab" :aria-selected="tab === tb ? 'true' : 'false'" :class="{ on: tab === tb }" @click="setTab(tb)">
            {{ tb === 'overview' ? t('ig.overview') : t('tabs.' + tb) }}<span v-if="tb !== 'overview'" class="n">{{ int(data.counts[tb]) }}</span>
          </button>
        </div>
        <div class="igc-bar-end">
          <label class="igc-select">
            <i class="bx bx-calendar" aria-hidden="true"></i><span class="igc-sr">{{ t('range_label') }}</span>
            <select v-model.number="days" @change="loadInsights(false); loadAiCached()">
              <option v-for="d in [7, 14, 30, 90]" :key="d" :value="d">{{ t('range_option', { days: d }) }}</option>
            </select>
          </label>
          <button v-if="data.can_export" type="button" class="igc-btn ghost" :disabled="exporting || !account.healthy" @click="exportCsv"><i class="bx" :class="exporting ? 'bx-loader-alt bx-spin' : 'bx-download'"></i>{{ exporting ? t('reports.exporting') : t('reports.export') }}</button>
          <a v-else :href="data.urls.upgrade" class="igc-btn ghost" :title="t('reports.export_locked')"><i class="bx bx-lock-alt"></i>{{ t('reports.export') }}</a>
          <button type="button" class="igc-btn primary" @click="openReportForm"><i class="bx bx-envelope"></i>{{ t('reports.schedule') }}</button>
        </div>
      </div>

      <template v-if="tab === 'overview'">
        <!-- ================= KPI CARDS ================= -->
        <div class="igc-kpis">
          <div v-for="k in kpis" :key="k.key" class="igc-card igc-kpi" :class="{ pending: !ins && !insLoading }">
            <div class="top">
              <span class="ic" :style="{ background: k.tint[1], color: k.tint[0] }" aria-hidden="true"><i class="bx" :class="k.icon"></i></span>
              <span class="lbl">{{ k.label }}</span>
              <span v-if="k.delta" class="igc-delta" :class="k.delta.tone">{{ k.delta.text }}</span>
            </div>
            <div v-if="insLoading" class="igc-skel lg"></div>
            <div v-else class="val"><span v-if="k.prefix" class="pre">{{ k.prefix }}</span>{{ k.value }}<span v-if="k.suffix" class="suf">{{ k.suffix }}</span></div>
            <template v-if="!insLoading">
              <div v-if="k.bar !== undefined" class="bar-row">
                <div class="lbl-row"><small :title="k.barHint">{{ k.barLabel }}</small><b>{{ k.bar === null ? '—' : Math.round(k.bar) + '%' }}</b></div>
                <div class="igc-track"><span :style="{ width: Math.min(100, k.bar || 0) + '%' }"></span></div>
              </div>
              <div v-else class="foot">
                <small><span v-if="k.subDelta" class="igc-delta" :class="k.subDelta.tone">{{ k.subDelta.text }}</span> {{ k.sub }}</small>
                <ad-sparkline v-if="k.spark && k.spark.length > 1" :values="k.spark" :color="k.sparkColor" :w="92" :h="34" />
              </div>
            </template>
          </div>
        </div>

        <div class="igc-strip">
          <div v-for="m in strip" :key="m.key" class="igc-card igc-mini">
            <span class="lbl"><i class="bx" :class="m.icon" aria-hidden="true"></i>{{ m.label }}</span>
            <strong>{{ m.value }}</strong>
            <small><span v-if="m.delta" class="igc-delta" :class="m.delta.tone">{{ m.delta.text }}</span> {{ m.sub }}</small>
          </div>
        </div>

        <!-- ================= TREND + FUNNEL ================= -->
        <div class="igc-grid trend">
          <section class="igc-card igc-pad">
            <div class="igc-h">
              <div><h2>{{ t('ig.trend_title') }}</h2><p>{{ t('ig.trend_sub', { days }) }}</p></div>
              <div class="igc-h-side">
                <div class="igc-legend">
                  <span><i class="sq"></i>{{ t('ig.amount_spent') }}</span>
                  <span v-if="resultsComparable"><i class="ln"></i>{{ resultName }}</span>
                  <span v-if="hasRoasDaily"><i class="ln dash"></i>{{ t('ig.roas') }}</span>
                </div>
                <div class="igc-seg sm" role="tablist" :aria-label="t('ig.grouping')">
                  <button v-for="g in ['daily', 'weekly']" :key="g" type="button" role="tab" :aria-selected="grouping === g ? 'true' : 'false'" :class="{ on: grouping === g }" @click="grouping = g">{{ t('ig.groupings.' + g) }}</button>
                </div>
              </div>
            </div>
            <div v-if="insLoading" class="igc-skel chart"></div>
            <spend-results-chart v-else-if="trend" :spend="trend.spend" :results="resultsComparable ? trend.results : []" :ratio="hasRoasDaily ? trend.roas : []"
                                 :labels="trend.labels" :height="250" color="#E1306C" ratio-color="#7C3AED" :format-spend="moneyAxis" :format-count="int"
                                 :format-ratio="(v) => dec(v, 2) + '×'" :spend-label="t('ig.amount_spent')" :result-label="resultName" :ratio-label="t('ig.roas')" :aria-label="t('ig.trend_title')" />
            <p v-else class="igc-muted-line"><i class="bx bx-info-circle"></i>{{ insError || t('kpi.awaiting') }}</p>
          </section>

          <section class="igc-card igc-pad">
            <div class="igc-h"><div><h2>{{ t('ig.funnel_title') }}</h2><p>{{ t('ig.funnel_sub') }}</p></div></div>
            <ol v-if="funnel.length" class="igc-funnel">
              <li v-for="f in funnel" :key="f.key">
                <span class="lbl"><i class="bx" :class="f.icon" :style="{ color: f.color }"></i>{{ f.label }}</span>
                <span class="track"><span class="bar" :style="{ width: f.width + '%', background: f.color }"></span></span>
                <span class="num"><b>{{ f.value }}</b><small>{{ f.note }}</small></span>
              </li>
            </ol>
            <p v-else class="igc-muted-line"><i class="bx bx-info-circle"></i>{{ insLoading ? t('loading') : t('kpi.awaiting') }}</p>
            <p v-if="ins && kpiRoas !== null" class="igc-callout" v-html="roasCallout"></p>
          </section>
        </div>

        <!-- ================= BUDGET + COMPARISON + AI ================= -->
        <div class="igc-grid three">
          <section class="igc-card igc-pad">
            <div class="igc-h"><div><h2>{{ t('ig.budget_title') }}</h2><p>{{ t('ig.budget_sub') }}</p></div></div>
            <div v-if="donut.length" class="igc-donut">
              <svg viewBox="0 0 120 120" width="132" height="132" role="img" :aria-label="t('ig.budget_title')">
                <circle cx="60" cy="60" r="46" fill="none" stroke="#EEF1F6" stroke-width="16" />
                <circle v-for="seg in donut" :key="seg.key" cx="60" cy="60" r="46" fill="none" :stroke="seg.color" stroke-width="16"
                        :stroke-dasharray="seg.len + ' ' + (CIRC - seg.len)" :stroke-dashoffset="-seg.offset" transform="rotate(-90 60 60)" />
                <text x="60" y="55" text-anchor="middle" class="t1">{{ t('ig.spent') }}</text>
                <text x="60" y="72" text-anchor="middle" class="t2">{{ moneyCompact(ins.totals.current.spend) }}</text>
              </svg>
              <ul class="igc-donut-legend">
                <li v-for="seg in donut" :key="seg.key"><i :style="{ background: seg.color }"></i><bdi dir="auto">{{ seg.label }}</bdi><b>{{ seg.share }}%</b></li>
              </ul>
            </div>
            <p v-else class="igc-muted-line"><i class="bx bx-info-circle"></i>{{ insLoading ? t('loading') : t('ig.no_spend') }}</p>
            <div class="igc-pacing">
              <div class="row"><span>{{ t('ig.today_pacing') }}</span><span v-if="todaySpend !== null"><b>{{ money(todaySpend) }}</b> {{ t('ig.of_daily', { amount: money(activeDaily) }) }}</span><span v-else>—</span></div>
              <div class="igc-track"><span :style="{ width: Math.min(100, pacingPct || 0) + '%' }"></span></div>
              <div class="row sub"><small>{{ pacingPct === null ? t('ig.pacing_unavailable') : t('ig.pacing_note', { pct: Math.round(pacingPct), time: nowTime }) }}</small><small v-if="pacingState" class="igc-pacing-state" :class="pacingState">{{ t('ig.pacing.' + pacingState) }}</small></div>
            </div>
            <div class="igc-mini-stats">
              <div><small>{{ t('ig.daily_budgets') }}</small><b>{{ activeDaily ? money(activeDaily) : '—' }}</b></div>
              <div><small>{{ t('ig.avg_daily_spend') }}</small><b>{{ ins ? money(ins.all_placements_spend / days) : '—' }}</b></div>
              <div><small>{{ t('ig.lifetime_budgets') }}</small><b :title="s.lifetime_campaigns ? money(s.lifetime_budget_total) : null">{{ s.lifetime_campaigns ? moneyCompact(s.lifetime_budget_total) : t('ig.none_set') }}</b></div>
            </div>
          </section>

          <section class="igc-card igc-pad">
            <div class="igc-h">
              <div><h2>{{ t('ig.compare_title') }}</h2><p>{{ t('ig.compare_sub') }}</p></div>
              <div class="igc-seg sm" role="tablist" :aria-label="t('ig.compare_title')">
                <button v-for="m in ['roas', 'cpr', 'ctr']" :key="m" type="button" role="tab" :aria-selected="compareBy === m ? 'true' : 'false'" :class="{ on: compareBy === m }" @click="compareBy = m">{{ t('ig.compare_by.' + m) }}</button>
              </div>
            </div>
            <ul v-if="compareRows.length" class="igc-compare">
              <li v-for="r in compareRows" :key="r.id">
                <div class="h">
                  <span class="nm"><i :style="{ background: r.color }"></i><bdi dir="auto">{{ r.name }}</bdi><span v-if="r.top" class="igc-badge ok">{{ t('ig.top_performer') }}</span></span>
                  <small>{{ r.hint }}</small>
                </div>
                <div class="v"><div class="igc-track"><span :style="{ width: r.width + '%', background: r.color }"></span></div><b>{{ r.display }}</b></div>
              </li>
            </ul>
            <p v-else class="igc-muted-line"><i class="bx bx-info-circle"></i>{{ insLoading ? t('loading') : t('ig.compare_none') }}</p>
            <p class="igc-foot">{{ t('ig.compare_foot') }}</p>
          </section>

          <section class="igc-card igc-pad igc-ai" :aria-busy="aiLoading ? 'true' : 'false'">
            <div class="igc-h light"><div><h2><span class="spark"><i class="bx bxs-magic-wand"></i></span>{{ t('ig.ai_title') }}</h2><p>{{ t('ig.ai_sub') }}</p></div></div>
            <div v-if="aiLoading" class="igc-ai-list"><div v-for="n in 3" :key="n" class="igc-ai-item"><span class="igc-skel dark" style="width:40%"></span><span class="igc-skel dark"></span><span class="igc-skel dark" style="width:75%"></span></div></div>
            <div v-else-if="ai && ai.insights && ai.insights.length" class="igc-ai-list">
              <article v-for="(item, i) in ai.insights" :key="i" class="igc-ai-item">
                <span class="tag" :class="item.action">{{ t('ig.ai_tags.' + item.action) }}</span>
                <strong dir="auto">{{ item.title }}</strong>
                <p dir="auto">{{ item.text }}</p>
              </article>
            </div>
            <p v-else class="igc-ai-empty">{{ hasDelivery ? t('ai.empty') : t('ig.ai_no_data') }}</p>
            <p v-if="aiError" class="igc-ai-error" role="alert">{{ aiError }} <a v-if="aiCreditIssue" :href="data.urls.usage">{{ t('ai.view_usage') }}</a></p>
            <div class="igc-ai-foot">
              <button type="button" class="igc-btn light sm" :disabled="aiLoading || !hasDelivery" @click="generateAi"><i class="bx bxs-magic-wand"></i>{{ ai ? t('ai.regenerate') : t('ai.generate') }}</button>
              <a v-if="data.urls.ai_copilot" :href="data.urls.ai_copilot" class="igc-btn light-ghost sm"><i class="bx bx-bot"></i>{{ t('ig.ask_copilot') }}</a>
              <small>{{ ai ? t('ai.generated', { when: dateTime(ai.generated_at) }) : t('ai.credit_note') }} · {{ t('ig.ai_note') }}</small>
            </div>
          </section>
        </div>
      </template>

      <!-- ================= CAMPAIGNS (overview + campaigns tab) ================= -->
      <section v-if="tab === 'overview' || tab === 'campaigns'" ref="tableCard" class="igc-card igc-table-card">
        <div class="igc-table-h">
          <div class="ttl">
            <h2>{{ t('tabs.campaigns') }}</h2><span class="igc-count">{{ campaigns.length }}</span>
            <div v-if="campaigns.length" class="igc-chips" role="tablist" :aria-label="t('status_filter')">
              <button v-for="f in statusChips" :key="f.key" type="button" role="tab" :aria-selected="statusChip === f.key ? 'true' : 'false'" :class="{ on: statusChip === f.key }" @click="statusChip = f.key; page = 1">{{ t('chips.' + f.key) }} {{ f.count }}</button>
            </div>
          </div>
          <div v-if="campaigns.length" class="igc-tools">
            <label class="igc-search"><i class="bx bx-search" aria-hidden="true"></i><input v-model.trim="query" type="search" :placeholder="t('search.campaigns')" :aria-label="t('search.campaigns')" @input="page = 1"></label>
            <label class="igc-select sm"><i class="bx bx-sort-alt-2" aria-hidden="true"></i><span class="igc-sr">{{ t('sort') }}</span>
              <select v-model="sort" @change="page = 1"><option v-for="o in sortOptions" :key="o" :value="o">{{ t('ig.sorts.' + o) }}</option></select>
            </label>
            <div ref="filtersMenu" class="igc-dd">
              <button type="button" class="igc-btn ghost" :aria-expanded="filtersOpen ? 'true' : 'false'" @click="filtersOpen = !filtersOpen"><i class="bx bx-filter-alt"></i>{{ t('filters') }}<span v-if="objectiveFilter" class="igc-dotn">1</span></button>
              <div v-if="filtersOpen" class="igc-menu end form">
                <label>{{ t('objective') }}
                  <select v-model="objectiveFilter" @change="page = 1"><option value="">{{ t('any') }}</option><option v-for="o in objectives" :key="o" :value="o">{{ objectiveLabel(o) }}</option></select>
                </label>
                <button type="button" class="igc-link" @click="objectiveFilter = ''; page = 1">{{ t('clear_filters') }}</button>
              </div>
            </div>
            <div ref="columnsMenu" class="igc-dd">
              <button type="button" class="igc-btn ghost" :aria-expanded="columnsOpen ? 'true' : 'false'" @click="columnsOpen = !columnsOpen"><i class="bx bx-columns"></i>{{ t('columns') }}</button>
              <div v-if="columnsOpen" class="igc-menu end form">
                <label v-for="c in OPTIONAL_COLUMNS" :key="c" class="check"><input v-model="visibleCols" type="checkbox" :value="c" @change="saveCols">{{ t('ig.cols.' + c) }}</label>
              </div>
            </div>
          </div>
        </div>

        <div v-if="selected.length && data.can_manage" class="igc-bulk" role="region" :aria-label="t('bulk')">
          <strong>{{ t('selected_n', { n: selected.length }) }}</strong>
          <button type="button" class="igc-btn ghost sm" :disabled="busy" @click="askBulk('PAUSED')"><i class="bx bx-pause"></i>{{ t('pause') }}</button>
          <button type="button" class="igc-btn ghost sm" :disabled="busy" @click="askBulk('ACTIVE')"><i class="bx bx-play"></i>{{ t('activate') }}</button>
          <button type="button" class="igc-link" @click="selected = []">{{ t('clear') }}</button>
        </div>

        <div v-if="!campaigns.length" class="igc-empty sm">
          <span class="igc-empty-ic"><i class="bx bx-rocket"></i></span><h3>{{ t('empty.campaigns') }}</h3><p>{{ t('empty.campaigns_text') }}</p>
          <div class="igc-empty-a">
            <a v-if="data.can_manage" :href="data.urls.create" class="igc-btn primary"><i class="bx bx-plus"></i>{{ t('create_campaign') }}</a>
            <button v-if="account.healthy" type="button" class="igc-btn ghost" :disabled="syncing" @click="syncNow"><i class="bx bx-sync"></i>{{ t('sync_now') }}</button>
          </div>
        </div>
        <div v-else-if="!filtered.length" class="igc-empty sm"><p>{{ t('no_matches') }}</p><button type="button" class="igc-btn ghost" @click="resetFilters">{{ t('clear_filters') }}</button></div>
        <div v-else class="igc-table-wrap">
          <table class="igc-table">
            <thead><tr>
              <th class="cb"><input type="checkbox" :checked="allOnPage" :disabled="!data.can_manage" :aria-label="t('select_all')" @change="togglePage($event.target.checked)"></th>
              <th>{{ t('cols.campaign') }}</th><th>{{ t('cols.delivery') }}</th>
              <th v-if="col('budget')" class="num">{{ t('ig.cols.budget') }}</th>
              <th v-if="col('spent')" class="num">{{ t('cols.spent') }}</th>
              <th v-if="col('impressions')" class="num">{{ t('ig.cols.impressions') }}</th>
              <th v-if="col('link_clicks')" class="num">{{ t('ig.cols.link_clicks') }}</th>
              <th v-if="col('ctr')" class="num">{{ t('ig.cols.ctr') }}</th>
              <th v-if="col('cpc')" class="num">{{ t('ig.cols.cpc') }}</th>
              <th v-if="col('cpm')" class="num">{{ t('ig.cols.cpm') }}</th>
              <th v-if="col('results')" class="num">{{ t('cols.results') }}</th>
              <th v-if="col('cpr')" class="num">{{ t('ig.cols.cpr') }}</th>
              <th v-if="col('roas')" class="num">{{ t('ig.cols.roas') }}</th>
              <th class="num">{{ t('cols.actions') }}</th>
            </tr></thead>
            <tbody>
              <tr v-for="c in pageRows" :key="c.id" :class="{ sel: selected.includes(c.id) }">
                <td class="cb"><input v-model="selected" type="checkbox" :value="c.id" :disabled="!data.can_manage" :aria-label="t('select_row', { name: c.name })"></td>
                <td>
                  <div class="c-cell">
                    <span class="c-ic" :style="{ background: objColor(c.objective) }" aria-hidden="true"><i class="bx" :class="objIcon(c.objective)"></i></span>
                    <div class="c-txt">
                      <bdi dir="auto" class="c-nm" :title="c.name">{{ c.name || t('untitled') }}</bdi>
                      <span class="c-meta"><span v-if="c.objective" class="c-tag">{{ objectiveLabel(c.objective) }}</span>{{ placementsLabel(c) }}</span>
                    </div>
                  </div>
                </td>
                <td>
                  <div class="c-delivery">
                    <button type="button" class="igc-switch" role="switch" :aria-checked="isActive(c) ? 'true' : 'false'" :aria-label="isActive(c) ? t('pause_name', { name: c.name }) : t('activate_name', { name: c.name })"
                            :disabled="!data.can_manage || !c.remote_id || busyId === c.id || isArchived(c)" :title="toggleHint(c)" @click="askToggle(c)"><span></span></button>
                    <span class="igc-pill" :class="statusTone(c.status)"><span class="d"></span>{{ statusLabel(c.status) }}</span>
                  </div>
                </td>
                <td v-if="col('budget')" class="num">
                  <b class="c-money">{{ c.daily_budget !== null ? money(c.daily_budget) : (c.lifetime_budget !== null ? money(c.lifetime_budget) : '—') }}</b>
                  <span v-if="m(c) && budgetFor(c)" class="c-bar" :title="t('ig.budget_bar_hint')"><span :style="{ width: Math.min(100, (m(c).spend / budgetFor(c)) * 100) + '%' }"></span></span>
                  <small v-else-if="c.lifetime_budget !== null && c.daily_budget === null" class="c-sub">{{ t('lifetime') }}</small>
                </td>
                <td v-if="col('spent')" class="num"><b v-if="m(c)" class="c-money">{{ money(m(c).spend) }}</b><span v-else class="c-dash" :title="metricHint">—</span></td>
                <td v-if="col('impressions')" class="num">{{ m(c) ? compact(m(c).impressions) : '—' }}</td>
                <td v-if="col('link_clicks')" class="num">{{ m(c) ? int(m(c).link_clicks) : '—' }}</td>
                <td v-if="col('ctr')" class="num">{{ m(c) && m(c).link_ctr !== null ? pct(m(c).link_ctr) : '—' }}</td>
                <td v-if="col('cpc')" class="num">{{ m(c) && m(c).link_cpc !== null ? money(m(c).link_cpc) : '—' }}</td>
                <td v-if="col('cpm')" class="num">{{ m(c) && m(c).cpm !== null ? money(m(c).cpm) : '—' }}</td>
                <td v-if="col('results')" class="num">
                  <template v-if="m(c) && m(c).result_type"><b>{{ m(c).result_type === 'reach' ? compact(m(c).results) : int(m(c).results) }}</b><small class="c-sub">{{ resultLabel(m(c).result_type) }}</small></template>
                  <span v-else class="c-dash" :title="metricHint">—</span>
                </td>
                <td v-if="col('cpr')" class="num">
                  <template v-if="m(c) && m(c).cost_per_result !== null">{{ money(m(c).cost_per_result) }}<small v-if="m(c).result_type === 'reach'" class="c-sub">{{ t('ig.per_reached') }}</small></template><template v-else>—</template>
                </td>
                <td v-if="col('roas')" class="num"><span v-if="m(c) && m(c).roas !== null" class="c-roas" :class="m(c).roas >= 1 ? 'up' : 'down'"><i class="bx" :class="m(c).roas >= 1 ? 'bx-trending-up' : 'bx-trending-down'"></i>{{ dec(m(c).roas, 2) }}×</span><span v-else class="c-dash">—</span></td>
                <td class="num">
                  <div class="c-actions">
                    <a v-if="data.can_manage" :href="url('edit', c.id)" class="c-act" :title="t('edit')" :aria-label="t('edit') + ' · ' + c.name"><i class="bx bx-pencil"></i></a>
                    <button v-if="data.can_manage" type="button" class="c-act" :disabled="!c.remote_id || busyId === c.id" :title="t('duplicate')" :aria-label="t('duplicate') + ' · ' + c.name" @click="askDuplicate(c)"><i class="bx bx-copy"></i></button>
                    <div class="igc-dd">
                      <button type="button" class="c-act" :aria-label="t('more') + ' · ' + c.name" @click.stop="rowMenu = rowMenu === c.id ? null : c.id"><i class="bx bx-dots-horizontal-rounded"></i></button>
                      <div v-if="rowMenu === c.id" class="igc-menu end" role="menu" @click.stop>
                        <a v-if="c.remote_id" :href="metaUrl + '&selected_campaign_ids=' + c.remote_id" target="_blank" rel="noopener" role="menuitem"><i class="bx bx-link-external"></i>{{ t('open_in_meta') }}</a>
                        <button v-if="data.can_manage" type="button" class="danger" role="menuitem" @click="askDelete(c)"><i class="bx bx-trash"></i>{{ t('delete') }}</button>
                        <span v-if="!data.can_manage && !c.remote_id" class="muted">{{ t('no_actions') }}</span>
                      </div>
                    </div>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
        <div v-if="filtered.length" class="igc-pager">
          <span>{{ t('ig.showing', { from: (page - 1) * PAGE + 1, to: Math.min(page * PAGE, filtered.length), total: filtered.length }) }}</span>
          <div v-if="pages > 1" class="pg">
            <button type="button" class="c-act" :disabled="page === 1" :aria-label="t('prev')" @click="page--"><i class="bx bx-chevron-left"></i></button>
            <button v-for="n in pages" :key="n" type="button" class="c-act" :class="{ on: n === page }" :aria-current="n === page ? 'page' : null" @click="page = n">{{ n }}</button>
            <button type="button" class="c-act" :disabled="page === pages" :aria-label="t('next')" @click="page++"><i class="bx bx-chevron-right"></i></button>
          </div>
        </div>
      </section>

      <!-- ================= AD SETS / ADS / CREATIVES ================= -->
      <section v-if="['adsets', 'ads', 'creatives'].includes(tab)" class="igc-card igc-table-card">
        <div class="igc-table-h">
          <div class="ttl"><h2>{{ t('tabs.' + tab) }}</h2><span class="igc-count">{{ data[tab].length }}</span></div>
          <label v-if="data[tab].length" class="igc-search"><i class="bx bx-search" aria-hidden="true"></i><input v-model.trim="query" type="search" :placeholder="t('search.' + tab)" :aria-label="t('search.' + tab)" @input="page = 1"></label>
        </div>
        <div v-if="!data[tab].length" class="igc-empty sm"><span class="igc-empty-ic"><i class="bx bx-layer"></i></span><h3>{{ t('empty.' + tab) }}</h3><p>{{ t('empty.' + tab + '_text') }}</p></div>
        <div v-else class="igc-table-wrap">
          <table class="igc-table">
            <thead><tr>
              <th>{{ t('cols.name') }}</th><th>{{ tab === 'ads' ? t('cols.adset') : t('cols.campaign') }}</th>
              <th v-if="tab === 'ads'">{{ t('cols.campaign') }}</th>
              <th v-if="tab !== 'creatives'">{{ t('cols.status') }}</th>
              <th v-if="tab === 'adsets'" class="num">{{ t('cols.budget') }}</th>
              <th v-if="tab === 'adsets'">{{ t('cols.optimization') }}</th>
              <th v-if="tab === 'creatives'">{{ t('cols.copy') }}</th>
              <th v-if="tab === 'creatives'">{{ t('cols.cta') }}</th>
            </tr></thead>
            <tbody>
              <tr v-for="r in pageRows" :key="r.id">
                <td><bdi dir="auto" class="c-nm">{{ r.name || t('untitled') }}</bdi><span class="c-meta">{{ t('id_n', { id: r.id }) }}</span></td>
                <td><bdi dir="auto">{{ (tab === 'ads' ? r.adset : r.campaign) || '—' }}</bdi></td>
                <td v-if="tab === 'ads'"><bdi dir="auto">{{ r.campaign || '—' }}</bdi></td>
                <td v-if="tab !== 'creatives'"><span class="igc-pill" :class="statusTone(r.status)"><span class="d"></span>{{ statusLabel(r.status) }}</span></td>
                <td v-if="tab === 'adsets'" class="num">{{ r.budget !== null ? money(r.budget) : '—' }}</td>
                <td v-if="tab === 'adsets'">{{ r.optimization_goal ? objectiveLabel(r.optimization_goal) : '—' }}</td>
                <td v-if="tab === 'creatives'" class="c-copy"><strong v-if="r.headline" dir="auto">{{ r.headline }}</strong><span dir="auto">{{ r.message || '—' }}</span></td>
                <td v-if="tab === 'creatives'">{{ r.call_to_action ? objectiveLabel(r.call_to_action) : '—' }}</td>
              </tr>
            </tbody>
          </table>
        </div>
        <div v-if="filtered.length > PAGE" class="igc-pager"><span>{{ t('ig.showing', { from: (page - 1) * PAGE + 1, to: Math.min(page * PAGE, filtered.length), total: filtered.length }) }}</span>
          <div class="pg"><button type="button" class="c-act" :disabled="page === 1" :aria-label="t('prev')" @click="page--"><i class="bx bx-chevron-left"></i></button><button type="button" class="c-act" :disabled="page === pages" :aria-label="t('next')" @click="page++"><i class="bx bx-chevron-right"></i></button></div>
        </div>
      </section>

      <!-- ================= PLACEMENTS + REPORTS + SYNC ================= -->
      <div v-if="tab === 'overview'" class="igc-grid three">
        <section class="igc-card igc-pad">
          <div class="igc-h"><div><h2>{{ t('ig.placement_title') }}</h2><p>{{ t('ig.placement_sub') }}</p></div></div>
          <template v-if="placements.length">
            <div class="igc-stack" role="img" :aria-label="t('ig.placement_title')"><span v-for="p in placements" :key="p.key" :style="{ width: p.share + '%', background: p.color }" :title="p.label + ' · ' + p.share + '%'"></span></div>
            <ul class="igc-placements">
              <li v-for="p in placements" :key="p.key"><i :style="{ background: p.color }"></i><b>{{ p.label }}</b><span class="mut">{{ t('ig.of_spend', { pct: p.share }) }}</span><span>{{ money(p.spend) }}</span><b class="roas">{{ p.roas === null ? '—' : dec(p.roas, 2) + '×' }}</b></li>
            </ul>
          </template>
          <p v-else class="igc-muted-line"><i class="bx bx-info-circle"></i>{{ insLoading ? t('loading') : (ins && details.placements === null ? t('perf.part_unavailable') : t('kpi.awaiting')) }}</p>
        </section>

        <section class="igc-card igc-pad">
          <div class="igc-h"><div><h2>{{ t('ig.reports_title') }}</h2><p>{{ t('ig.reports_sub') }}</p></div><button type="button" class="igc-link" @click="openReportForm"><i class="bx bx-plus"></i>{{ t('reports.new') }}</button></div>
          <ul v-if="reports.length" class="igc-reports">
            <li v-for="r in reports" :key="r.id">
              <span class="ic"><i class="bx bx-file"></i></span>
              <span class="txt"><b dir="auto">{{ r.name }}</b><small>{{ cadence(r) }}<template v-if="r.status !== 'active'"> · {{ t('reports.paused') }}</template><template v-if="r.last_error"> · <span class="err">{{ t('reports.failed') }}</span></template></small></span>
              <span class="fmt">CSV</span>
              <button type="button" class="c-act" :disabled="reportBusy === r.id" :title="t('reports.send_now')" :aria-label="t('reports.send_now') + ' · ' + r.name" @click="sendReport(r)"><i class="bx bx-send"></i></button>
              <button type="button" class="c-act" :disabled="reportBusy === r.id" :title="r.status === 'active' ? t('reports.pause') : t('reports.resume')" @click="toggleReport(r)"><i class="bx" :class="r.status === 'active' ? 'bx-pause' : 'bx-play'"></i></button>
              <button type="button" class="c-act" :disabled="reportBusy === r.id" :title="t('delete')" @click="askDeleteReport(r)"><i class="bx bx-trash"></i></button>
            </li>
          </ul>
          <p v-else class="igc-muted-line"><i class="bx bx-envelope"></i>{{ t('ig.reports_none', { email: data.recipient_email || '—' }) }}</p>
        </section>

        <section class="igc-card igc-pad">
          <div class="igc-h"><div><h2>{{ t('ig.sync_title') }}</h2><p>{{ t('ig.sync_sub') }}</p></div><span class="igc-badge" :class="stepsDone === steps.length ? 'ok' : 'warn'"><span class="d"></span>{{ stepsDone === steps.length ? t('ig.healthy') : t('ig.needs_attention') }}</span></div>
          <ol class="igc-steps">
            <li v-for="st in steps" :key="st.key" :class="{ done: st.done }"><span class="ic"><i v-if="st.done" class="bx bx-check"></i></span><div><strong>{{ t('ig.steps.' + st.key) }}</strong><small>{{ st.text }}</small></div></li>
          </ol>
          <button v-if="account.healthy" type="button" class="igc-btn ghost block" :disabled="insLoading" @click="loadInsights(true)"><i class="bx bx-refresh" :class="{ 'bx-spin': insLoading }"></i>{{ t('refresh_insights') }}</button>
        </section>
      </div>
    </template>

    <!-- ================= REPORT FORM ================= -->
    <div v-if="reportForm" class="igc-modal" role="dialog" aria-modal="true" aria-labelledby="igc-report-t" @click.self="reportForm = null">
      <form class="igc-dialog wide" @submit.prevent="saveReport">
        <h3 id="igc-report-t">{{ t('reports.form_title') }}</h3>
        <div class="igc-form">
          <label class="full">{{ t('reports.name') }}<input ref="reportName" v-model.trim="reportForm.name" type="text" maxlength="120" required dir="auto"></label>
          <label>{{ t('reports.frequency') }}<select v-model="reportForm.frequency"><option v-for="f in ['daily', 'weekly', 'monthly']" :key="f" :value="f">{{ t('reports.frequencies.' + f) }}</option></select></label>
          <label v-if="reportForm.frequency === 'weekly'">{{ t('reports.weekday') }}<select v-model.number="reportForm.weekday"><option v-for="d in 7" :key="d" :value="d - 1">{{ weekdayName(d - 1) }}</option></select></label>
          <label v-if="reportForm.frequency === 'monthly'">{{ t('reports.day_of_month') }}<select v-model.number="reportForm.day_of_month"><option v-for="d in 28" :key="d" :value="d">{{ d }}</option></select></label>
          <label>{{ t('reports.time') }}<input v-model="reportForm.send_time" type="time" required></label>
          <label>{{ t('reports.period') }}<select v-model.number="reportForm.period_days"><option v-for="d in [7, 14, 30, 90]" :key="d" :value="d">{{ t('reports.period_n', { days: d }) }}</option></select></label>
          <div class="full igc-form-note"><i class="bx bx-envelope"></i><span>{{ t('reports.recipient_note', { email: data.recipient_email || '—' }) }}</span></div>
          <div class="full igc-form-note"><i class="bx bx-paperclip"></i><span>{{ t('ig.format_note') }}</span></div>
        </div>
        <p v-if="reportFormError" class="igc-ai-error" role="alert">{{ reportFormError }}</p>
        <div class="igc-dialog-a">
          <button type="button" class="igc-btn ghost" @click="reportForm = null">{{ t('cancel') }}</button>
          <button type="submit" class="igc-btn primary" :disabled="reportSaving"><i class="bx bx-calendar-check"></i>{{ reportSaving ? t('reports.saving') : t('reports.save') }}</button>
        </div>
      </form>
    </div>

    <!-- ================= CONFIRM ================= -->
    <div v-if="confirm" class="igc-modal" role="dialog" aria-modal="true" aria-labelledby="igc-confirm-t" @click.self="confirm = null">
      <div class="igc-dialog">
        <h3 id="igc-confirm-t">{{ confirm.title }}</h3>
        <p>{{ confirm.text }}</p>
        <div class="igc-dialog-a">
          <button ref="cancelBtn" type="button" class="igc-btn ghost" @click="confirm = null">{{ t('cancel') }}</button>
          <button type="button" class="igc-btn" :class="confirm.danger ? 'danger' : 'primary'" @click="runConfirm">{{ confirm.ok }}</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onBeforeUnmount, nextTick } from 'vue';
import { createTranslator } from '../facebook/i18n';
import SpendResultsChart from './SpendResultsChart.vue';
import AdSparkline from '../dashboard/AdSparkline.vue';

/*
 * Instagram Ads campaigns page: the seller's Meta ad account (Instagram ads
 * run on it) with every number narrowed to Instagram placements by
 * InstagramAdService. Campaign controls act on the whole Meta campaign.
 */
const props = defineProps({
  data: { type: Object, required: true },
  messages: { type: Object, default: () => ({}) },
  locale: { type: String, default: 'en' },
  csrf: { type: String, default: '' },
});

const i18n = createTranslator(props.messages, props.locale);
const { t, tc } = i18n;
const known = (key, fallback) => { const v = t(key); return v === key ? fallback : v; };

const TABS = ['overview', 'campaigns', 'adsets', 'ads', 'creatives'];
const OPTIONAL_COLUMNS = ['budget', 'spent', 'impressions', 'link_clicks', 'ctr', 'cpc', 'cpm', 'results', 'cpr', 'roas'];
const PAGE = 6;
const COLS_KEY = 'igc.columns';
const CIRC = 2 * Math.PI * 46;
const PALETTE = ['#E1306C', '#4F46E5', '#7C3AED', '#F97316', '#0EA5E9', '#14B8A6', '#A3A9BC'];

/* ---------------- state ---------------- */
const tab = ref('overview');
const days = ref(30);
const grouping = ref('daily');
const compareBy = ref('roas');
const query = ref(''); const sort = ref('spend'); const statusChip = ref('all'); const objectiveFilter = ref('');
const page = ref(1); const selected = ref([]); const visibleCols = ref(loadCols());
const switchOpen = ref(false); const moreOpen = ref(false); const filtersOpen = ref(false); const columnsOpen = ref(false); const rowMenu = ref(null);
const switcher = ref(null); const moreMenu = ref(null); const filtersMenu = ref(null); const columnsMenu = ref(null); const cancelBtn = ref(null); const tableCard = ref(null); const reportName = ref(null);
const syncing = ref(false); const busy = ref(false); const busyId = ref(null);
const ins = ref(null); const insLoading = ref(false); const insError = ref('');
const ai = ref(null); const aiLoading = ref(false); const aiError = ref(''); const aiCreditIssue = ref(false);
const toastMsg = ref(''); const toastOk = ref(true); const confirm = ref(null); const exporting = ref(false);
const reports = ref((props.data.reports || []).map((r) => ({ ...r })));
const reportForm = ref(null); const reportFormError = ref(''); const reportSaving = ref(false); const reportBusy = ref(null);
const campaigns = ref(props.data.campaigns.map((c) => ({ ...c })));

const account = computed(() => props.data.account);
const ig = computed(() => props.data.instagram);
const s = computed(() => props.data.summary);
const cur = computed(() => (account.value && account.value.currency) || '');
const details = computed(() => (ins.value && ins.value.details) || {});

/* ---------------- formatting ---------------- */
const nf = (v, o) => Number(v || 0).toLocaleString(i18n.intl, o);
function int(v) { return nf(v, { maximumFractionDigits: 0 }); }
function dec(v, d = 2) { return nf(v, { minimumFractionDigits: d, maximumFractionDigits: d }); }
function compact(v) { return nf(v, { notation: 'compact', maximumFractionDigits: 1 }); }
function pct(v, d = 2) { return dec(Number(v || 0) * 100, d) + '%'; }
function money(v) { return (cur.value ? cur.value + ' ' : '') + nf(v, { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }
function moneyCompact(v) { return (cur.value ? cur.value + ' ' : '') + (Math.abs(v) >= 10000 ? compact(v) : nf(v, { maximumFractionDigits: 0 })); }
function moneyAxis(v) { return nf(v, { maximumFractionDigits: 0 }); }
function dateTime(iso) { return new Date(iso).toLocaleString(i18n.intl, { month: 'short', day: 'numeric', year: 'numeric', hour: '2-digit', minute: '2-digit' }); }
function initials(name) { return String(name || '?').trim().split(/\s+/).slice(0, 2).map((w) => w.charAt(0)).join('').toUpperCase() || '?'; }
function objectiveLabel(v) { const k = String(v || '').toUpperCase(); const p = k.replace(/^OUTCOME_/, '').replace(/_/g, ' ').toLowerCase(); return known('objectives.' + k, p.charAt(0).toUpperCase() + p.slice(1)); }
function resultLabel(type) { return known('result_types.' + String(type || '').replace(/\./g, '_'), t('result_types.results')); }
function resultOne(type) { return known('result_one.' + String(type || '').replace(/\./g, '_'), t('result_one.results')); }
function url(kind, id) { return props.data.urls[kind].replace('CAMPAIGN_ID', id); }
function switchUrl(a) { return props.data.urls.switch.replace('ACCOUNT_ID', a.id); }
const metaUrl = computed(() => props.data.urls.meta + (account.value ? String(account.value.external_id).replace(/^act_/, '') : ''));
const nowTime = computed(() => new Date().toLocaleTimeString(i18n.intl, { hour: '2-digit', minute: '2-digit' }));
// Positive change is good, except for costs (inverse).
function delta(c, p, inverse = false) {
  if (c === null || c === undefined || !p) return null;
  const ch = (c - p) / Math.abs(p);
  const up = ch > 0;
  return { tone: Math.abs(ch) < 0.0005 ? 'flat' : (up !== inverse ? 'up' : 'down'), text: (up ? '▲ ' : '▼ ') + dec(Math.abs(ch) * 100, 1) + '%' };
}

/* ---------------- status + campaigns ---------------- */
function norm(st) {
  const v = String(st || '').toLowerCase();
  if (['active', 'enable', 'enabled', '1', 'true'].includes(v)) return 'active';
  if (['paused', 'disable', 'disabled', '0', 'false', ''].includes(v)) return 'paused';
  if (['archived', 'deleted'].includes(v)) return 'archived';
  if (['with_issues', 'disapproved', 'error'].includes(v)) return 'issue';
  if (['in_process', 'pending_review'].includes(v)) return 'pending';
  return 'other';
}
function isActive(c) { return norm(c.status) === 'active'; }
function isArchived(c) { return norm(c.status) === 'archived'; }
function statusLabel(st) { return t('statuses.' + norm(st)); }
function statusTone(st) { return { active: 'ok', paused: 'warn', archived: 'mut', issue: 'bad', pending: 'info', other: 'mut' }[norm(st)]; }
function toggleHint(c) {
  if (!props.data.can_manage) return t('blockers.' + (props.data.manage_blocker || 'member_read_only'));
  if (!c.remote_id) return t('not_on_meta');
  if (isArchived(c)) return t('archived_hint');
  return t('ig.toggle_hint');
}
const OBJ = { OUTCOME_SALES: ['#F97316', 'bx-trending-up'], OUTCOME_LEADS: ['#14B8A6', 'bx-id-card'], OUTCOME_TRAFFIC: ['#0EA5E9', 'bx-pointer'], OUTCOME_ENGAGEMENT: ['#D946EF', 'bx-heart'], OUTCOME_AWARENESS: ['#F59E0B', 'bx-show'], OUTCOME_APP_PROMOTION: ['#6366F1', 'bx-mobile'] };
function objColor(o) { return (OBJ[String(o || '').toUpperCase()] || ['#E1306C'])[0]; }
function objIcon(o) { return (OBJ[String(o || '').toUpperCase()] || ['', 'bxl-instagram'])[1]; }
function placementsLabel(c) { return [t('id_n', { id: c.id }), tc('adsets_n', c.adsets_count, { count: c.adsets_count })].join(' · '); }
function m(c) { return ins.value && ins.value.campaigns ? ins.value.campaigns[c.id] || null : null; }
function budgetFor(c) { return c.daily_budget ? c.daily_budget * days.value : (c.lifetime_budget || 0); }
const metricHint = computed(() => (insLoading.value ? t('loading') : (insError.value || (ins.value ? t('ig.no_ig_delivery') : t('kpi.awaiting')))));

/* ---------------- KPIs ---------------- */
const hasDelivery = computed(() => !!ins.value && (ins.value.totals.current.spend > 0 || ins.value.totals.current.impressions > 0));
const resultsComparable = computed(() => { const rt = ins.value && ins.value.totals.current.result_type; return !!rt && rt !== 'mixed'; });
const resultName = computed(() => (resultsComparable.value ? resultLabel(ins.value.totals.current.result_type) : t('cols.results')));
const activeDaily = computed(() => campaigns.value.filter(isActive).reduce((n, c) => n + Number(c.daily_budget || 0), 0));
const kpiRoas = computed(() => (ins.value ? ins.value.totals.current.roas : null));
const dailyRows = computed(() => details.value.daily || []);
const kpis = computed(() => {
  const c = ins.value ? ins.value.totals.current : null; const p = ins.value ? ins.value.totals.previous : null;
  const dash = '—';
  const util = c && activeDaily.value ? (ins.value.all_placements_spend / (activeDaily.value * days.value)) * 100 : null;
  return [
    { key: 'spend', icon: 'bx-wallet', tint: ['#4F46E5', '#EEF0FF'], label: t('ig.kpi.spend'), prefix: c ? cur.value : '', value: c ? nf(c.spend, { maximumFractionDigits: 0 }) : dash, delta: c && p ? delta(c.spend, p.spend) : null,
      bar: c ? util : undefined, barLabel: activeDaily.value ? t('ig.kpi.budget_util') : t('ig.kpi.no_daily'), barHint: t('ig.kpi.budget_util_hint') },
    { key: 'results', icon: 'bx-target-lock', tint: ['#E1306C', '#FFEAF2'], label: t('ig.kpi.results'),
      value: c ? (resultsComparable.value ? int(c.results) : t('kpi.mixed')) : dash,
      suffix: c && resultsComparable.value && c.cost_per_result !== null ? ' ' + t('ig.kpi.per_result', { amount: money(c.cost_per_result) }) : '',
      delta: c && p && resultsComparable.value ? delta(c.results, p.results) : null,
      subDelta: c && p && resultsComparable.value ? delta(c.cost_per_result, p.cost_per_result, true) : null,
      sub: c ? (resultsComparable.value ? t('ig.kpi.cpr_vs_prev') : t('kpi.mixed_text')) : (insError.value || t('kpi.awaiting')),
      spark: resultsComparable.value ? dailyRows.value.map((d) => d.results || 0) : null, sparkColor: '#E1306C' },
    { key: 'roas', icon: 'bx-line-chart', tint: ['#16A34A', '#E7F7EE'], label: t('ig.kpi.roas'), value: c && c.roas !== null ? dec(c.roas, 2) : dash, suffix: c && c.roas !== null ? '×' : '',
      delta: c && p && c.roas !== null ? delta(c.roas, p.roas) : null,
      sub: c ? (c.roas !== null ? t('ig.kpi.purchase_value', { amount: money(c.purchase_value) }) : t('ig.kpi.no_value')) : t('kpi.awaiting'),
      spark: c && c.roas !== null ? dailyRows.value.map((d) => (d.spend ? (d.purchase_value || 0) / d.spend : 0)) : null, sparkColor: '#16A34A' },
    { key: 'conversions', icon: 'bx-cart', tint: ['#7C3AED', '#F2EDFF'], label: t('ig.kpi.conversions'), value: c ? int(c.conversions) : dash,
      suffix: c && c.cvr !== null ? ' ' + t('ig.kpi.cvr', { rate: pct(c.cvr) }) : '', delta: c && p ? delta(c.conversions, p.conversions) : null,
      sub: c ? t('ig.kpi.conv_hint') : t('kpi.awaiting'), spark: c ? dailyRows.value.map((d) => d.conversions || 0) : null, sparkColor: '#7C3AED' },
  ];
});
const strip = computed(() => {
  const c = ins.value ? ins.value.totals.current : null; const p = ins.value ? ins.value.totals.previous : null;
  const acc = details.value.account || null;
  const v = (val, f) => (c && val !== null && val !== undefined ? f(val) : '—');
  const vs = t('ig.vs_prev');
  return [
    { key: 'campaigns', icon: 'bx-bullseye', label: t('ig.mini.campaigns'), value: int(s.value.total), sub: t('ig.mini.active_n', { n: s.value.active }) },
    { key: 'impressions', icon: 'bx-show', label: t('ig.mini.impressions'), value: v(c && c.impressions, compact), delta: c && p ? delta(c.impressions, p.impressions) : null, sub: c && p ? vs : '' },
    { key: 'reach', icon: 'bx-group', label: t('ig.mini.reach'), value: acc ? compact(acc.reach) : '—', sub: acc ? t('ig.mini.unique') : '' },
    { key: 'frequency', icon: 'bx-repeat', label: t('ig.mini.frequency'), value: acc && acc.reach ? dec(acc.impressions / acc.reach, 2) : '—', sub: t('ig.mini.frequency_sub') },
    { key: 'link_clicks', icon: 'bx-link', label: t('ig.mini.link_clicks'), value: v(c && c.link_clicks, compact), delta: c && p ? delta(c.link_clicks, p.link_clicks) : null, sub: c && p ? vs : '' },
    { key: 'ctr', icon: 'bx-pointer', label: t('ig.mini.ctr'), value: v(c && c.link_ctr, (x) => pct(x)), delta: c && p ? delta(c.link_ctr, p.link_ctr) : null, sub: c && p ? vs : '' },
    { key: 'cpc', icon: 'bx-coin', label: t('ig.mini.cpc'), value: v(c && c.link_cpc, money), delta: c && p ? delta(c.link_cpc, p.link_cpc, true) : null, sub: c && p ? vs : '' },
    { key: 'cpm', icon: 'bx-receipt', label: t('ig.mini.cpm'), value: v(c && c.cpm, money), delta: c && p ? delta(c.cpm, p.cpm, true) : null, sub: c && p ? vs : '' },
  ];
});

/* ---------------- trend ---------------- */
const hasRoasDaily = computed(() => dailyRows.value.some((d) => d.purchase_value > 0));
const trend = computed(() => {
  const d = dailyRows.value;
  if (!d.length) return null;
  const label = (iso) => new Date(iso + 'T00:00:00').toLocaleDateString(i18n.intl, { month: 'short', day: 'numeric' });
  if (grouping.value === 'daily') {
    return { spend: d.map((x) => x.spend), results: d.map((x) => x.results), roas: d.map((x) => (x.spend ? (x.purchase_value || 0) / x.spend : null)), labels: d.map((x) => label(x.date)) };
  }
  const weeks = [];
  d.forEach((x, i) => { if (i % 7 === 0) weeks.push({ spend: 0, results: 0, value: 0, start: x.date }); const w = weeks[weeks.length - 1]; w.spend += x.spend; w.results += x.results || 0; w.value += x.purchase_value || 0; });
  return { spend: weeks.map((w) => w.spend), results: weeks.map((w) => w.results), roas: weeks.map((w) => (w.spend ? w.value / w.spend : null)), labels: weeks.map((w) => label(w.start)) };
});

/* ---------------- funnel ---------------- */
const funnel = computed(() => {
  const a = details.value.account;
  if (!a || !a.impressions) return [];
  const w = (v) => Math.max(4, Math.min(100, Math.sqrt(v / a.impressions) * 100));
  const rows = [
    { key: 'impressions', icon: 'bx-show', color: '#4F6BFF', label: t('ig.funnel.impressions'), raw: a.impressions, value: compact(a.impressions), note: t('ig.funnel.top') },
    { key: 'reach', icon: 'bx-group', color: '#6366F1', label: t('ig.funnel.reach'), raw: a.reach, value: compact(a.reach), note: t('ig.funnel.of_impressions', { pct: pct(a.reach / a.impressions, 1) }) },
    { key: 'link_clicks', icon: 'bx-pointer', color: '#8B5CF6', label: t('ig.funnel.link_clicks'), raw: a.link_clicks, value: compact(a.link_clicks), note: a.reach ? t('ig.funnel.ctr_from_reach', { pct: pct(a.link_clicks / a.reach, 1) }) : '' },
    { key: 'conversions', icon: 'bx-cart', color: '#C026D3', label: t('ig.funnel.conversions'), raw: a.conversions, value: int(a.conversions), note: a.link_clicks ? t('ig.funnel.cvr', { pct: pct(a.conversions / a.link_clicks, 1) }) : '' },
  ];
  if (a.purchase_value > 0) rows.push({ key: 'revenue', icon: 'bx-dollar-circle', color: '#E1306C', label: t('ig.funnel.revenue'), raw: a.purchase_value, value: moneyCompact(a.purchase_value), note: a.spend ? t('ig.funnel.roas_on_sales', { roas: dec(a.purchase_value / a.spend, 2) }) : '' });
  // Square-root scale keeps small stages visible; revenue is money, so it sits just under conversions.
  rows.forEach((r, i) => { r.width = r.key === 'revenue' ? Math.max(4, rows[i - 1].width - 6) : w(r.raw); });
  return rows;
});
const esc = (v) => String(v).replace(/[&<>"]/g, (ch) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' })[ch]);
const roasCallout = computed(() => t('ig.roas_callout', { one: '<b>' + esc(money(1)) + '</b>', value: '<b>' + esc(money(kpiRoas.value)) + '</b>' }));

/* ---------------- budget utilization ---------------- */
const donut = computed(() => {
  if (!ins.value) return [];
  const rows = campaigns.value.map((c) => ({ c, mm: m(c) })).filter((r) => r.mm && r.mm.spend > 0).sort((a, b) => b.mm.spend - a.mm.spend);
  const total = rows.reduce((n, r) => n + r.mm.spend, 0);
  if (!total) return [];
  const top = rows.slice(0, 5).map((r, i) => ({ key: r.c.id, label: r.c.name || t('untitled'), spend: r.mm.spend, color: PALETTE[i] }));
  const rest = rows.slice(5).reduce((n, r) => n + r.mm.spend, 0);
  if (rest > 0) top.push({ key: 'other', label: t('ig.other_campaigns'), spend: rest, color: PALETTE[6] });
  let offset = 0;
  return top.map((x) => { const len = (x.spend / total) * CIRC; const seg = { ...x, share: Math.round((x.spend / total) * 100), len, offset }; offset += len; return seg; });
});
const todaySpend = computed(() => (ins.value && details.value.today_spend !== undefined ? details.value.today_spend : null));
const pacingPct = computed(() => (todaySpend.value !== null && activeDaily.value ? (todaySpend.value / activeDaily.value) * 100 : null));
const pacingState = computed(() => {
  if (pacingPct.value === null) return null;
  const now = new Date(); const dayPct = ((now.getHours() * 60 + now.getMinutes()) / 1440) * 100;
  if (pacingPct.value > dayPct + 20) return 'ahead';
  if (pacingPct.value < dayPct - 25) return 'behind';
  return 'on_pace';
});

/* ---------------- campaign comparison ---------------- */
const compareRows = computed(() => {
  if (!ins.value) return [];
  const rows = campaigns.value.map((c) => ({ c, mm: m(c) })).filter((r) => r.mm && r.mm.spend > 0);
  const val = (r) => (compareBy.value === 'roas' ? r.mm.roas : compareBy.value === 'cpr' ? r.mm.cost_per_result : r.mm.link_ctr);
  const sorted = rows.filter((r) => val(r) !== null).sort((a, b) => (compareBy.value === 'cpr' ? val(a) - val(b) : val(b) - val(a)));
  const none = rows.filter((r) => val(r) === null);
  const list = [...sorted, ...none].slice(0, 6);
  const max = Math.max(1e-9, ...sorted.map(val));
  const bestRoas = Math.max(0, ...rows.map((r) => r.mm.roas || 0));
  return list.map((r, i) => {
    const v = val(r);
    const display = v === null ? 'n/a' : compareBy.value === 'roas' ? dec(v, 2) + '×' : compareBy.value === 'cpr' ? money(v) : pct(v);
    const width = v === null ? 0 : compareBy.value === 'cpr' ? Math.max(6, (Math.min(...sorted.map(val)) / v) * 100) : Math.max(6, (v / max) * 100);
    return {
      id: r.c.id, name: r.c.name || t('untitled'), color: PALETTE[i % 6], display, width,
      hint: r.mm.cost_per_result !== null && r.mm.result_type ? t('ig.cost_per', { amount: money(r.mm.cost_per_result), result: resultOne(r.mm.result_type).toLowerCase() }) : '',
      top: compareBy.value === 'roas' && bestRoas > 0 && r.mm.roas === bestRoas,
    };
  });
});

/* ---------------- placements ---------------- */
const placements = computed(() => {
  const rows = (details.value.placements || []).filter((r) => r.spend > 0);
  const total = rows.reduce((n, r) => n + r.spend, 0);
  return rows.slice(0, 6).map((r, i) => {
    const pos = r.key.split('|')[1] || r.key;
    return { key: r.key, label: known('placements.instagram_' + pos, pos.replace(/_/g, ' ')), spend: r.spend, share: total ? Math.round((r.spend / total) * 100) : 0, roas: r.purchase_value > 0 && r.spend ? r.purchase_value / r.spend : null, color: PALETTE[i] };
  });
});

/* ---------------- sync checklist ---------------- */
const steps = computed(() => {
  const counts = props.data.counts;
  const synced = !!(ins.value || (account.value && account.value.insights_synced_at));
  return [
    { key: 'instagram', done: !!ig.value, text: ig.value ? (ig.value.username ? '@' + ig.value.username : ig.value.name) : t('ig.no_identity') },
    { key: 'account', done: !!account.value, text: account.value ? [account.value.name, account.value.masked_id, account.value.currency].filter(Boolean).join(' · ') : '' },
    { key: 'imported', done: counts.campaigns > 0, text: counts.campaigns ? t('imported_text', { campaigns: counts.campaigns, adsets: counts.adsets, ads: counts.ads }) : t('imported_none') },
    { key: 'insights', done: synced, text: synced ? t('insights_done', { when: dateTime((ins.value && ins.value.synced_at) || account.value.insights_synced_at) }) : (insError.value || t('insights_pending')) },
  ];
});
const stepsDone = computed(() => steps.value.filter((x) => x.done).length);

/* ---------------- table filtering ---------------- */
const objectives = computed(() => [...new Set(campaigns.value.map((c) => c.objective).filter(Boolean))]);
const statusChips = computed(() => {
  const n = (k) => campaigns.value.filter((c) => norm(c.status) === k).length;
  return [{ key: 'all', count: campaigns.value.length }, { key: 'active', count: n('active') }, { key: 'paused', count: n('paused') }];
});
const sortOptions = computed(() => (ins.value ? ['spend', 'roas', 'results', 'newest', 'name'] : ['newest', 'name', 'budget']));
const currentRows = computed(() => (['campaigns', 'overview'].includes(tab.value) ? campaigns.value : props.data[tab.value]) || []);
const filtered = computed(() => {
  const q = query.value.toLowerCase();
  let rows = currentRows.value.filter((r) => !q || [r.name, r.objective, r.campaign, r.adset, r.headline, r.remote_id, String(r.id)].some((v) => String(v || '').toLowerCase().includes(q)));
  if (!['campaigns', 'overview'].includes(tab.value)) return rows;
  rows = rows.filter((c) => (statusChip.value === 'all' || norm(c.status) === statusChip.value) && (!objectiveFilter.value || c.objective === objectiveFilter.value));
  const by = sortOptions.value.includes(sort.value) ? sort.value : sortOptions.value[0];
  const mv = (c, k) => { const x = m(c); return x && x[k] !== null && x[k] !== undefined ? x[k] : -1; };
  return [...rows].sort((a, b) => {
    if (by === 'name') return String(a.name || '').localeCompare(String(b.name || ''), i18n.intl);
    if (by === 'budget') return Number(b.daily_budget ?? b.lifetime_budget ?? -1) - Number(a.daily_budget ?? a.lifetime_budget ?? -1);
    if (by === 'spend') return mv(b, 'spend') - mv(a, 'spend');
    if (by === 'roas') return mv(b, 'roas') - mv(a, 'roas');
    if (by === 'results') return mv(b, 'results') - mv(a, 'results');
    return String(b.created_at || '').localeCompare(String(a.created_at || '')) || b.id - a.id;
  });
});
const pages = computed(() => Math.max(1, Math.ceil(filtered.value.length / PAGE)));
const pageRows = computed(() => filtered.value.slice((page.value - 1) * PAGE, page.value * PAGE));
const allOnPage = computed(() => pageRows.value.length > 0 && pageRows.value.every((c) => selected.value.includes(c.id)));
function togglePage(on) { const ids = pageRows.value.map((c) => c.id); selected.value = on ? [...new Set([...selected.value, ...ids])] : selected.value.filter((id) => !ids.includes(id)); }
function setTab(tb) { tab.value = tb; page.value = 1; query.value = ''; selected.value = []; }
function resetFilters() { query.value = ''; statusChip.value = 'all'; objectiveFilter.value = ''; page.value = 1; }
function loadCols() { try { const v = JSON.parse(window.localStorage.getItem(COLS_KEY)); if (Array.isArray(v)) return v.filter((c) => OPTIONAL_COLUMNS.includes(c)); } catch (e) { /* storage unavailable */ } return [...OPTIONAL_COLUMNS]; }
function saveCols() { try { window.localStorage.setItem(COLS_KEY, JSON.stringify(visibleCols.value)); } catch (e) { /* storage unavailable */ } }
function col(name) { return visibleCols.value.includes(name); }

/* ---------------- loading ---------------- */
function toast(msg, ok) { toastMsg.value = msg; toastOk.value = ok; }
function errorText(e, fallback) { return (e.response && e.response.data && (e.response.data.message || e.response.data.error)) || fallback; }
const jsonHeaders = () => ({ 'X-CSRF-TOKEN': props.csrf, Accept: 'application/json' });
async function loadInsights(fresh) {
  if (!account.value || !account.value.healthy) return;
  insLoading.value = true; insError.value = '';
  try {
    const { data } = await window.axios.get(props.data.urls.insights, { params: { days: days.value, fresh: fresh ? 1 : 0 } });
    if (data.success) { ins.value = data; if (fresh) toast(t('insights_updated'), true); } else { ins.value = null; insError.value = data.error || t('insights_failed'); }
  } catch (e) { ins.value = null; insError.value = errorText(e, t('insights_failed')); } finally { insLoading.value = false; }
}
async function loadAiCached() {
  if (!account.value) return;
  try { const { data } = await window.axios.get(props.data.urls.ai_insights, { params: { days: days.value } }); ai.value = data.data || null; } catch (e) { ai.value = null; }
}
async function generateAi() {
  aiLoading.value = true; aiError.value = ''; aiCreditIssue.value = false;
  try { const { data } = await window.axios.post(props.data.urls.ai_generate, { days: days.value }, { headers: jsonHeaders() }); ai.value = data.data; } catch (e) {
    const st = e.response && e.response.status; aiCreditIssue.value = st === 403 || st === 429; aiError.value = errorText(e, t('ai.failed'));
  } finally { aiLoading.value = false; }
}

/* ---------------- campaign actions ---------------- */
function ask(opts) { confirm.value = opts; nextTick(() => cancelBtn.value && cancelBtn.value.focus()); }
async function runConfirm() { const c = confirm.value; confirm.value = null; if (c) await c.run(); }
async function setStatus(c, status) {
  busyId.value = c.id;
  try { const { data } = await window.axios.patch(url('status', c.id), { status }, { headers: jsonHeaders() }); c.status = data.status || status.toLowerCase(); return true; } catch (e) { toast(errorText(e, t('status_failed')), false); return false; } finally { busyId.value = null; }
}
function askToggle(c) {
  const activate = !isActive(c);
  ask({ title: activate ? t('confirm_activate_title') : t('confirm_pause_title'), text: (activate ? t('confirm_activate_text', { name: c.name }) : t('confirm_pause_text', { name: c.name })) + ' ' + t('ig.whole_campaign'), ok: activate ? t('activate') : t('pause'),
    run: async () => { if (await setStatus(c, activate ? 'ACTIVE' : 'PAUSED')) toast(activate ? t('activated') : t('paused'), true); } });
}
function askBulk(status) {
  const rows = campaigns.value.filter((c) => selected.value.includes(c.id) && c.remote_id && !isArchived(c) && (status === 'ACTIVE') !== isActive(c));
  if (!rows.length) { toast(t('bulk_nothing'), false); return; }
  ask({ title: status === 'ACTIVE' ? t('confirm_activate_title') : t('confirm_pause_title'), text: tc('confirm_bulk_text', rows.length, { count: rows.length }), ok: status === 'ACTIVE' ? t('activate') : t('pause'),
    run: async () => { busy.value = true; let done = 0; for (const c of rows) { if (await setStatus(c, status)) done++; } busy.value = false; selected.value = []; if (done) toast(tc('bulk_done', done, { count: done }), done === rows.length); } });
}
function askDuplicate(c) {
  ask({ title: t('confirm_duplicate_title'), text: t('confirm_duplicate_text', { name: c.name }), ok: t('duplicate'),
    run: async () => { busyId.value = c.id; try { const { data } = await window.axios.post(url('duplicate', c.id), {}, { headers: jsonHeaders() }); toast(data.message || t('duplicated'), true); setTimeout(() => window.location.reload(), 1200); } catch (e) { toast(errorText(e, t('duplicate_failed')), false); } finally { busyId.value = null; } } });
}
function askDelete(c) {
  rowMenu.value = null;
  ask({ title: t('confirm_delete_title'), text: t('confirm_delete_text', { name: c.name }), ok: t('delete'), danger: true,
    run: async () => { const form = document.createElement('form'); form.method = 'POST'; form.action = url('destroy', c.id); form.innerHTML = `<input type="hidden" name="_method" value="DELETE"><input type="hidden" name="_token" value="${props.csrf}">`; document.body.appendChild(form); form.submit(); } });
}
async function syncNow() {
  if (syncing.value) return;
  syncing.value = true;
  try { const { data } = await window.axios.post(props.data.urls.sync, {}, { headers: { 'X-CSRF-TOKEN': props.csrf } }); const d = data.data || {}; toast(t('synced_msg', { campaigns: d.synced || 0, adsets: d.ad_sets || 0, ads: d.ads || 0 }), true); setTimeout(() => window.location.reload(), 1200); } catch (e) { toast(errorText(e, t('sync_failed')), false); syncing.value = false; }
}

/* ---------------- export + reports ---------------- */
function reportUrl(id, suffix = '') { return props.data.urls.report.replace(/\/0$/, '/' + id) + suffix; }
function weekdayName(d) { return new Date(2023, 0, 1 + d).toLocaleDateString(i18n.intl, { weekday: 'long' }); } // 1 Jan 2023 was a Sunday
function cadence(r) {
  if (r.frequency === 'weekly') return t('reports.every_weekday', { day: weekdayName(r.weekday), time: r.send_time });
  if (r.frequency === 'monthly') return t('reports.every_month', { day: r.day_of_month, time: r.send_time });
  return t('reports.every_day', { time: r.send_time });
}
async function exportCsv() {
  exporting.value = true;
  try {
    const res = await window.axios.get(props.data.urls.export, { params: { days: days.value }, responseType: 'blob' });
    const match = /filename="?([^";]+)"?/.exec(res.headers['content-disposition'] || '');
    const href = URL.createObjectURL(res.data); const a = document.createElement('a');
    a.href = href; a.download = match ? match[1] : 'instagram-ads.csv'; document.body.appendChild(a); a.click(); a.remove(); setTimeout(() => URL.revokeObjectURL(href), 1000);
  } catch (e) {
    let msg = t('reports.export_failed');
    try { const body = JSON.parse(await e.response.data.text()); msg = body.message || body.error || msg; } catch (x) { /* not JSON */ }
    toast(msg, false);
  } finally { exporting.value = false; }
}
function openReportForm() { reportFormError.value = ''; reportForm.value = { name: t('ig.default_report'), frequency: 'weekly', weekday: 0, day_of_month: 1, send_time: '09:00', period_days: 7 }; nextTick(() => reportName.value && reportName.value.focus()); }
async function saveReport() {
  reportSaving.value = true; reportFormError.value = '';
  try {
    const timezone = (window.Intl && Intl.DateTimeFormat().resolvedOptions().timeZone) || 'Asia/Riyadh';
    const { data } = await window.axios.post(props.data.urls.reports, { ...reportForm.value, timezone }, { headers: jsonHeaders() });
    reports.value.unshift(data.report); reportForm.value = null; toast(data.message || t('reports.saved'), true);
  } catch (e) { const errs = e.response && e.response.data && e.response.data.errors; reportFormError.value = errs ? Object.values(errs)[0][0] : errorText(e, t('reports.save_failed')); } finally { reportSaving.value = false; }
}
function replaceReport(u) { const i = reports.value.findIndex((r) => r.id === u.id); if (i > -1) reports.value.splice(i, 1, u); }
async function sendReport(r) {
  reportBusy.value = r.id;
  try { const { data } = await window.axios.post(reportUrl(r.id, '/send'), {}, { headers: jsonHeaders() }); replaceReport(data.report); toast(data.message, true); } catch (e) { if (e.response && e.response.data && e.response.data.report) replaceReport(e.response.data.report); toast(errorText(e, t('reports.send_failed')), false); } finally { reportBusy.value = null; }
}
async function toggleReport(r) {
  reportBusy.value = r.id;
  try { const { data } = await window.axios.patch(reportUrl(r.id), { status: r.status === 'active' ? 'paused' : 'active' }, { headers: jsonHeaders() }); replaceReport(data.report); } catch (e) { toast(errorText(e, t('reports.save_failed')), false); } finally { reportBusy.value = null; }
}
function askDeleteReport(r) {
  ask({ title: t('reports.delete_title'), text: t('reports.delete_text', { name: r.name }), ok: t('delete'), danger: true,
    run: async () => { try { await window.axios.delete(reportUrl(r.id), { headers: jsonHeaders() }); reports.value = reports.value.filter((x) => x.id !== r.id); } catch (e) { toast(errorText(e, t('reports.save_failed')), false); } } });
}

/* ---------------- menus ---------------- */
function closeMenus(e) {
  const outside = (el) => !el || !el.contains(e.target);
  if (switchOpen.value && outside(switcher.value)) switchOpen.value = false;
  if (moreOpen.value && outside(moreMenu.value)) moreOpen.value = false;
  if (filtersOpen.value && outside(filtersMenu.value)) filtersOpen.value = false;
  if (columnsOpen.value && outside(columnsMenu.value)) columnsOpen.value = false;
  rowMenu.value = null;
}
function onKey(e) { if (e.key === 'Escape') { switchOpen.value = moreOpen.value = filtersOpen.value = columnsOpen.value = false; rowMenu.value = null; confirm.value = null; reportForm.value = null; } }
onMounted(() => { document.addEventListener('click', closeMenus); document.addEventListener('keydown', onKey); loadInsights(false); loadAiCached(); });
onBeforeUnmount(() => { document.removeEventListener('click', closeMenus); document.removeEventListener('keydown', onKey); });
</script>

<style scoped>
.igc { --ink: #161B2B; --text: #4B5263; --muted: #8A92A3; --line: #E7E9F0; --line-soft: #F1F3F7; --wash: #F8F9FC;
  --brand: #004CFF; --brand-soft: #EEF3FF; --brand-line: #CFDCFF; --ig: #E1306C;
  --shadow: 0 1px 2px rgba(16, 24, 40, .04), 0 8px 24px rgba(16, 24, 40, .03);
  display: flex; flex-direction: column; gap: 16px; min-width: 0; color: var(--text); container-type: inline-size; container-name: igc; }
.igc-sr { position: absolute !important; width: 1px; height: 1px; overflow: hidden; clip: rect(0 0 0 0); white-space: nowrap; }
.igc-card { background: #fff; border: 1px solid var(--line); border-radius: 16px; box-shadow: var(--shadow); min-width: 0; }
.igc-pad { padding: 1.1rem 1.2rem; }
.igc-crumbs { display: flex; gap: .5rem; font-size: .74rem; font-weight: 700; letter-spacing: .1em; text-transform: uppercase; color: var(--muted); }
.igc-crumbs a { color: var(--muted); text-decoration: none; }
.igc-crumbs span:last-child { color: var(--ink); }

/* header */
.igc-head { position: relative; overflow: hidden; display: flex; flex-wrap: wrap; align-items: center; gap: 1rem 1.25rem; padding: 1.15rem 1.3rem; background: radial-gradient(70% 140% at 100% 0%, rgba(225, 48, 108, .07), transparent 60%), #fff; }
.igc-mark { width: 58px; height: 58px; border-radius: 17px; display: grid; place-items: center; flex-shrink: 0; color: #fff; font-size: 2rem; background: radial-gradient(circle at 30% 107%, #FDF497 0%, #FDF497 5%, #FD5949 45%, #D6249F 60%, #285AEB 90%); box-shadow: 0 10px 22px rgba(214, 36, 159, .28); }
.igc-head-main { flex: 1 1 420px; min-width: 0; display: flex; flex-direction: column; gap: .55rem; }
.igc-head-title { display: flex; flex-wrap: wrap; align-items: center; gap: .6rem; }
.igc-head h1 { margin: 0; font-size: 1.6rem; font-weight: 700; letter-spacing: -.02em; line-height: 1.25; color: var(--ink); }
.igc-tagline { font-size: .76rem; font-weight: 600; color: #7C3AED; }
.igc-head-meta { display: flex; flex-wrap: wrap; align-items: center; gap: .5rem .7rem; }
.igc-chip { display: inline-flex; align-items: center; gap: .4rem; height: 32px; max-width: 100%; padding: 0 .65rem; border: 1px solid var(--line); border-radius: 9px; background: #fff; color: var(--ink); font-size: .78rem; text-decoration: none; cursor: pointer; }
.igc-chip .lbl, .igc-chip .mut { color: var(--muted); white-space: nowrap; }
.igc-chip .nm { font-weight: 600; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; max-width: 180px; }
.igc-chip.warn { border-style: dashed; color: #B45309; }
.igc-chip-av { width: 18px; height: 18px; border-radius: 50%; object-fit: cover; }
.igc-chip-ic { color: var(--ig); font-size: 1rem; }
.igc-chip:hover { border-color: #F4B7CD; }
.igc-meta-txt { display: inline-flex; flex-wrap: wrap; align-items: center; gap: .35rem; margin: 0; font-size: .78rem; color: var(--muted); }
.igc-head-actions { display: flex; gap: .5rem; align-items: center; flex-wrap: wrap; }

/* buttons, pills, menus */
.igc-btn { display: inline-flex; align-items: center; justify-content: center; gap: .4rem; height: 38px; padding: 0 .95rem; border-radius: 10px; border: 1px solid transparent; font-size: .84rem; font-weight: 600; text-decoration: none; cursor: pointer; white-space: nowrap; transition: background .15s, border-color .15s, color .15s; }
.igc-btn.primary { background: var(--brand) linear-gradient(105deg, #004CFF, #5235FF); color: #fff; box-shadow: 0 6px 16px rgba(0, 76, 255, .2); }
.igc-btn.primary:hover { background: #0043E0 linear-gradient(105deg, #0043E0, #4429F0); color: #fff; }
.igc-btn.ghost { background: #fff; border-color: var(--line); color: var(--ink); }
.igc-btn.ghost:hover:not(:disabled) { border-color: #B9CCFF; color: var(--brand); }
.igc-btn.light { background: #fff; color: #4C1D95; }
.igc-btn.light-ghost { background: rgba(255, 255, 255, .1); border-color: rgba(255, 255, 255, .25); color: #fff; }
.igc-btn.danger { background: #DC2626; color: #fff; }
.igc-btn.icon { width: 38px; padding: 0; font-size: 1.15rem; }
.igc-btn.sm { height: 32px; padding: 0 .7rem; font-size: .78rem; }
.igc-btn.block { width: 100%; margin-top: .9rem; }
.igc-btn:disabled { opacity: .55; cursor: not-allowed; }
.igc-link { display: inline-flex; align-items: center; gap: 2px; border: 0; background: none; padding: 0; color: var(--brand); font-weight: 600; font-size: .78rem; cursor: pointer; text-decoration: none; }
.igc-pill, .igc-badge { display: inline-flex; align-items: center; gap: 5px; height: 22px; padding: 0 8px; border-radius: 999px; font-size: .7rem; font-weight: 600; white-space: nowrap; }
.igc-pill .d, .igc-badge .d { width: 6px; height: 6px; border-radius: 50%; background: currentColor; }
.igc-pill.ok, .igc-badge.ok { background: #E7F7EE; color: #15803D; }
.igc-pill.warn, .igc-badge.warn { background: #FFF6E5; color: #B45309; }
.igc-pill.bad { background: #FDECEC; color: #DC2626; }
.igc-pill.info { background: #EFF8FF; color: #175CD3; }
.igc-pill.mut { background: #F1F3F7; color: #64748B; }
.igc-dd { position: relative; }
.igc-menu { position: absolute; top: calc(100% + 6px); inset-inline-start: 0; z-index: 40; min-width: 220px; padding: 6px; background: #fff; border: 1px solid var(--line); border-radius: 12px; box-shadow: 0 18px 40px rgba(16, 24, 40, .16); display: flex; flex-direction: column; }
.igc-menu.end { inset-inline-start: auto; inset-inline-end: 0; }
.igc-menu.wide { min-width: 300px; }
.igc-menu > a, .igc-menu > button { display: flex; align-items: center; gap: 9px; padding: 8px 10px; border: 0; background: none; border-radius: 8px; color: var(--text); font-size: .82rem; font-weight: 500; text-decoration: none; text-align: start; cursor: pointer; }
.igc-menu > a:hover, .igc-menu > button:hover { background: var(--brand-soft); color: var(--brand); }
.igc-menu > .danger { color: #DC2626; }
.igc-menu .muted { padding: 8px 10px; font-size: .78rem; color: var(--muted); }
.igc-menu.form { padding: 12px; gap: 10px; min-width: 230px; }
.igc-menu.form label { display: flex; flex-direction: column; gap: 4px; font-size: .76rem; font-weight: 600; }
.igc-menu.form label.check { flex-direction: row; align-items: center; gap: 8px; font-weight: 500; font-size: .82rem; }
.igc-menu.form select { height: 34px; border: 1px solid var(--line); border-radius: 8px; padding: 0 .5rem; font-size: .82rem; }
.igc-menu-acct { display: flex; align-items: center; gap: 10px; padding: 8px 10px; border-radius: 8px; text-decoration: none; color: var(--text); }
.igc-menu-acct:hover, .igc-menu-acct.on { background: var(--brand-soft); }
.igc-menu-acct .txt { display: flex; flex-direction: column; min-width: 0; flex: 1; }
.igc-menu-acct bdi { font-weight: 600; color: var(--ink); font-size: .84rem; }
.igc-menu-acct small { font-size: .72rem; color: var(--muted); }
.igc-menu-foot { margin-top: 4px; border-top: 1px solid var(--line-soft); color: var(--brand) !important; font-weight: 600 !important; }
.igc-av { width: 32px; height: 32px; border-radius: 9px; display: grid; place-items: center; background: #FFEAF2; color: #BE185D; font-size: .74rem; font-weight: 700; }
.igc-note { display: flex; gap: 10px; align-items: center; padding: 12px 14px; border-radius: 12px; background: #F5F8FF; border: 1px solid #DCE5FB; font-size: .8rem; }
.igc-note > div { flex: 1; }
.igc-note strong { color: var(--ink); display: block; }
.igc-note p { margin: 2px 0 0; }
.igc-note.bad { background: #FFF8F7; border-color: #F6D3CF; }
.igc-toast { display: flex; align-items: center; gap: .6rem; padding: .7rem 1rem; border-radius: 12px; font-size: .84rem; font-weight: 500; }
.igc-toast.ok { background: #ECFDF3; color: #067647; border: 1px solid #ABEFC6; }
.igc-toast.bad { background: #FEF3F2; color: #B42318; border: 1px solid #FECDCA; }
.igc-toast button { margin-inline-start: auto; border: 0; background: none; color: inherit; font-size: 1.1rem; cursor: pointer; }

/* bar */
.igc-bar { display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: .75rem; }
.igc-bar-end { display: flex; flex-wrap: wrap; gap: .5rem; align-items: center; }
.igc-tabs { display: inline-flex; gap: 2px; padding: 4px; border-radius: 12px; background: #EEF0F6; overflow-x: auto; max-width: 100%; scrollbar-width: none; }
.igc-tabs button { display: inline-flex; align-items: center; gap: .45rem; height: 34px; padding: 0 .85rem; border: 0; border-radius: 9px; background: none; color: var(--text); font-size: .84rem; font-weight: 600; cursor: pointer; white-space: nowrap; }
.igc-tabs button.on { background: #fff; color: var(--ink); box-shadow: 0 1px 3px rgba(16, 24, 40, .1); }
.igc-tabs .n { font-size: .7rem; color: var(--muted); }
.igc-select { display: inline-flex; align-items: center; gap: .45rem; height: 38px; padding: 0 .75rem; border: 1px solid var(--line); border-radius: 10px; background: #fff; font-size: .82rem; color: var(--ink); }
.igc-select > .bx { color: var(--muted); }
.igc-select select { border: 0; background: none; font: inherit; font-weight: 500; color: var(--ink); outline: none; cursor: pointer; }
.igc-select.sm { height: 36px; }

/* KPI cards */
.igc-kpis { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 14px; }
.igc-kpi { display: flex; flex-direction: column; gap: .55rem; padding: 1rem 1.1rem; }
.igc-kpi.pending { border-style: dashed; box-shadow: none; }
.igc-kpi .top { display: flex; align-items: center; gap: .55rem; }
.igc-kpi .ic { width: 32px; height: 32px; border-radius: 9px; display: grid; place-items: center; font-size: 1.05rem; flex-shrink: 0; }
.igc-kpi .lbl { font-size: .8rem; font-weight: 600; color: var(--text); flex: 1; min-width: 0; }
.igc-kpi .val { font-size: 1.85rem; font-weight: 700; letter-spacing: -.02em; color: var(--ink); line-height: 1.15; font-variant-numeric: tabular-nums; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.igc-kpi .pre { font-size: .95rem; font-weight: 600; color: var(--muted); margin-inline-end: .25rem; }
.igc-kpi .suf { font-size: .9rem; font-weight: 600; color: var(--muted); }
.igc-kpi .bar-row { display: flex; flex-direction: column; gap: 5px; margin-top: auto; }
.igc-kpi .lbl-row { display: flex; justify-content: space-between; font-size: .74rem; color: var(--muted); }
.igc-kpi .lbl-row b { color: var(--ink); }
.igc-kpi .foot { display: flex; justify-content: space-between; align-items: flex-end; gap: 8px; margin-top: auto; }
.igc-kpi .foot small { font-size: .74rem; color: var(--muted); line-height: 1.4; min-width: 0; }
.igc-delta { display: inline-flex; align-items: center; padding: 1px 6px; border-radius: 6px; font-size: .7rem; font-weight: 700; white-space: nowrap; font-variant-numeric: tabular-nums; }
.igc-delta.up { background: #E7F7EE; color: #15803D; }
.igc-delta.down { background: #FDECEC; color: #DC2626; }
.igc-delta.flat { background: #F1F3F7; color: var(--muted); }
.igc-track { height: 6px; border-radius: 6px; background: #EEF1F6; overflow: hidden; }
.igc-track span { display: block; height: 100%; border-radius: 6px; background: linear-gradient(90deg, #4F46E5, #6366F1); }
.igc-skel { display: block; height: 12px; border-radius: 6px; background: linear-gradient(90deg, #EEF1F5 25%, #F6F8FB 50%, #EEF1F5 75%); background-size: 200% 100%; animation: igc-shimmer 1.2s infinite; }
.igc-skel.lg { height: 30px; width: 60%; }
.igc-skel.chart { height: 230px; width: 100%; border-radius: 12px; }
.igc-skel.dark { background: linear-gradient(90deg, rgba(255,255,255,.12) 25%, rgba(255,255,255,.2) 50%, rgba(255,255,255,.12) 75%); background-size: 200% 100%; }

/* mini metrics */
.igc-strip { display: grid; grid-template-columns: repeat(8, minmax(0, 1fr)); gap: 10px; }
.igc-mini { display: flex; flex-direction: column; gap: 3px; padding: .75rem .85rem; }
.igc-mini .lbl { display: flex; align-items: center; gap: .3rem; font-size: .72rem; color: var(--muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.igc-mini .lbl .bx { color: #A3A9BC; }
.igc-mini strong { font-size: 1.15rem; font-weight: 700; color: var(--ink); font-variant-numeric: tabular-nums; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.igc-mini small { font-size: .7rem; color: var(--muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

/* grids + headings */
.igc-grid { display: grid; gap: 16px; align-items: stretch; }
.igc-grid.trend { grid-template-columns: minmax(0, 1.75fr) minmax(300px, 1fr); }
.igc-grid.three { grid-template-columns: repeat(3, minmax(0, 1fr)); }
.igc-h { display: flex; justify-content: space-between; align-items: flex-start; gap: .75rem; margin-bottom: .9rem; flex-wrap: wrap; }
.igc-h h2 { margin: 0; font-size: 1rem; font-weight: 700; color: var(--ink); display: flex; align-items: center; gap: .45rem; }
.igc-h p { margin: 3px 0 0; font-size: .76rem; color: var(--muted); }
.igc-h-side { display: flex; flex-wrap: wrap; gap: .75rem; align-items: center; }
.igc-legend { display: flex; flex-wrap: wrap; gap: .8rem; font-size: .74rem; color: var(--text); }
.igc-legend span { display: inline-flex; align-items: center; gap: .35rem; }
.igc-legend .sq { width: 10px; height: 10px; border-radius: 2px; background: #CDD3E2; }
.igc-legend .ln { width: 14px; height: 3px; border-radius: 2px; background: #E1306C; }
.igc-legend .ln.dash { height: 0; border-top: 2px dashed #7C3AED; background: none; }
.igc-seg { display: inline-flex; padding: 3px; border: 1px solid var(--line); border-radius: 9px; background: var(--wash); gap: 2px; }
.igc-seg button { border: 0; background: none; height: 26px; padding: 0 .6rem; border-radius: 6px; font-size: .74rem; font-weight: 600; color: var(--text); cursor: pointer; }
.igc-seg button.on { background: #fff; color: var(--ink); box-shadow: 0 1px 3px rgba(16, 24, 40, .1); }
.igc-muted-line { display: flex; align-items: center; gap: .4rem; margin: 0; padding: 1.5rem 0; font-size: .8rem; color: var(--muted); }
.igc-foot { margin: .8rem 0 0; font-size: .72rem; color: var(--muted); }

/* funnel */
.igc-funnel { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 8px; }
.igc-funnel li { display: grid; grid-template-columns: 104px minmax(0, 1fr) auto; align-items: center; gap: .6rem; }
.igc-funnel .lbl { display: inline-flex; align-items: center; gap: .35rem; font-size: .78rem; font-weight: 600; color: var(--ink); white-space: nowrap; }
.igc-funnel .track { display: block; height: 30px; }
.igc-funnel .bar { display: block; height: 100%; border-radius: 8px; min-width: 6px; }
.igc-funnel .num { display: flex; flex-direction: column; align-items: flex-end; text-align: end; flex-shrink: 0; }
.igc-funnel .num b { font-size: .95rem; color: var(--ink); font-variant-numeric: tabular-nums; }
.igc-funnel .num small { font-size: .68rem; color: var(--muted); }
.igc-callout { margin: .9rem 0 0; padding: .65rem .8rem; border-radius: 10px; border: 1px solid #E4DAFE; background: #FAF7FF; font-size: .76rem; color: var(--text); }

/* budget */
.igc-donut { display: flex; align-items: center; gap: 1rem; flex-wrap: wrap; }
.igc-donut .t1 { font-size: 9px; fill: var(--muted); }
.igc-donut .t2 { font-size: 13px; font-weight: 700; fill: var(--ink); }
.igc-donut-legend { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 6px; flex: 1; min-width: 150px; }
.igc-donut-legend li { display: grid; grid-template-columns: 10px minmax(0, 1fr) auto; gap: .45rem; align-items: center; font-size: .76rem; color: var(--text); }
.igc-donut-legend i { width: 9px; height: 9px; border-radius: 3px; }
.igc-donut-legend bdi { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.igc-donut-legend b { color: var(--ink); }
.igc-pacing { margin-top: 1rem; display: flex; flex-direction: column; gap: 6px; }
.igc-pacing .row { display: flex; justify-content: space-between; gap: .5rem; font-size: .78rem; color: var(--text); }
.igc-pacing .row b { color: var(--ink); }
.igc-pacing .row.sub small { font-size: .7rem; color: var(--muted); }
.igc-pacing-state { font-weight: 700; }
.igc-pacing-state.on_pace { color: #15803D !important; } .igc-pacing-state.ahead { color: #B45309 !important; } .igc-pacing-state.behind { color: #175CD3 !important; }
.igc-mini-stats { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 8px; margin-top: .9rem; }
.igc-mini-stats div { display: flex; flex-direction: column; gap: 2px; padding: .55rem .65rem; border-radius: 10px; background: var(--wash); min-width: 0; }
.igc-mini-stats small { font-size: .68rem; color: var(--muted); }
.igc-mini-stats b { font-size: .84rem; color: var(--ink); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

/* comparison */
.igc-compare { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: .8rem; }
.igc-compare .h { display: flex; justify-content: space-between; gap: .5rem; align-items: baseline; }
.igc-compare .nm { display: inline-flex; align-items: center; gap: .4rem; min-width: 0; font-size: .8rem; font-weight: 600; color: var(--ink); }
.igc-compare .nm i { width: 12px; height: 12px; border-radius: 4px; flex-shrink: 0; }
.igc-compare .nm bdi { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.igc-compare small { font-size: .7rem; color: var(--muted); white-space: nowrap; }
.igc-compare .v { display: grid; grid-template-columns: minmax(0, 1fr) auto; gap: .6rem; align-items: center; margin-top: 4px; }
.igc-compare .v b { font-size: .82rem; color: var(--ink); font-variant-numeric: tabular-nums; }

/* AI */
.igc-ai { background: radial-gradient(120% 100% at 100% 0%, #7E22CE 0%, transparent 55%), linear-gradient(160deg, #3B0764, #1E1B4B); border-color: transparent; color: #E9D5FF; display: flex; flex-direction: column; }
.igc-h.light h2 { color: #fff; }
.igc-h.light p { color: #C4B5FD; }
.igc-h .spark { width: 26px; height: 26px; border-radius: 8px; display: grid; place-items: center; background: linear-gradient(135deg, #EC4899, #8B5CF6); color: #fff; font-size: .95rem; }
.igc-ai-list { display: flex; flex-direction: column; gap: .6rem; }
.igc-ai-item { display: flex; flex-direction: column; gap: .3rem; padding: .75rem .85rem; border-radius: 12px; background: rgba(255, 255, 255, .07); border: 1px solid rgba(255, 255, 255, .1); }
.igc-ai-item .tag { align-self: flex-start; padding: 1px 7px; border-radius: 5px; font-size: .62rem; font-weight: 800; letter-spacing: .08em; text-transform: uppercase; background: rgba(34, 197, 94, .18); color: #86EFAC; }
.igc-ai-item .tag.review_placements, .igc-ai-item .tag.refresh_creative { background: rgba(249, 115, 22, .2); color: #FDBA74; }
.igc-ai-item .tag.review_budget { background: rgba(99, 102, 241, .25); color: #C7D2FE; }
.igc-ai-item .tag.review_audience { background: rgba(14, 165, 233, .2); color: #7DD3FC; }
.igc-ai-item .tag.none { background: rgba(255, 255, 255, .12); color: #E9D5FF; }
.igc-ai-item strong { font-size: .84rem; color: #fff; line-height: 1.35; }
.igc-ai-item p { margin: 0; font-size: .76rem; color: #DDD6FE; line-height: 1.5; }
.igc-ai-empty { margin: 0; font-size: .8rem; color: #DDD6FE; line-height: 1.5; }
.igc-ai-error { margin: .6rem 0 0; padding: .5rem .7rem; border-radius: 10px; background: #FEF3F2; color: #B42318; font-size: .76rem; }
.igc-ai-error a { color: #B42318; font-weight: 600; }
.igc-ai-foot { display: flex; flex-wrap: wrap; align-items: center; gap: .5rem; margin-top: auto; padding-top: .9rem; }
.igc-ai-foot small { font-size: .7rem; color: #C4B5FD; }

/* table */
.igc-table-card { padding: 1rem 0 0; overflow: hidden; }
.igc-table-h { display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: .75rem; padding: 0 1.2rem .9rem; }
.igc-table-h .ttl { display: flex; flex-wrap: wrap; align-items: center; gap: .6rem; }
.igc-table-h h2 { margin: 0; font-size: 1.05rem; font-weight: 700; color: var(--ink); }
.igc-count { min-width: 22px; height: 20px; padding: 0 7px; border-radius: 999px; display: inline-grid; place-items: center; background: #FFEAF2; color: #BE185D; font-size: .72rem; font-weight: 700; }
.igc-chips { display: inline-flex; gap: 2px; padding: 3px; border-radius: 9px; background: #F1F3F7; }
.igc-chips button { height: 28px; padding: 0 .65rem; border: 0; border-radius: 7px; background: none; color: var(--text); font-size: .76rem; font-weight: 600; cursor: pointer; }
.igc-chips button.on { background: #fff; color: var(--brand); box-shadow: 0 1px 3px rgba(16, 24, 40, .1); }
.igc-tools { display: flex; flex-wrap: wrap; gap: .5rem; align-items: center; }
.igc-search { position: relative; display: inline-flex; }
.igc-search .bx { position: absolute; inset-inline-start: .65rem; top: 50%; transform: translateY(-50%); color: var(--muted); }
.igc-search input { height: 36px; width: 210px; border: 1px solid var(--line); border-radius: 10px; padding-inline: 2rem .75rem; font-size: .8rem; color: var(--ink); background: #fff; }
.igc-search input:focus { outline: none; border-color: var(--brand-line); box-shadow: 0 0 0 3px var(--brand-soft); }
.igc-dotn { min-width: 18px; height: 18px; border-radius: 999px; background: var(--brand); color: #fff; font-size: .68rem; display: inline-grid; place-items: center; }
.igc-bulk { display: flex; flex-wrap: wrap; align-items: center; gap: .6rem; margin: 0 1.2rem .9rem; padding: .5rem .8rem; border-radius: 10px; background: var(--brand-soft); font-size: .8rem; }
.igc-table-wrap { overflow-x: auto; }
.igc-table { width: 100%; border-collapse: collapse; font-size: .8rem; min-width: 1100px; }
.igc-table th { padding: .6rem .5rem; background: var(--wash); color: var(--muted); font-size: .66rem; font-weight: 700; letter-spacing: .05em; text-transform: uppercase; text-align: start; border-top: 1px solid var(--line-soft); border-bottom: 1px solid var(--line-soft); white-space: nowrap; }
.igc-table td { padding: .75rem .5rem; border-bottom: 1px solid var(--line-soft); vertical-align: middle; color: var(--text); white-space: nowrap; font-variant-numeric: tabular-nums; }
.igc-table th:first-child, .igc-table td:first-child { padding-inline-start: 1.2rem; }
.igc-table th:last-child, .igc-table td:last-child { padding-inline-end: 1.2rem; }
.igc-table tbody tr:hover td, .igc-table tbody tr.sel td { background: #FDF7FA; }
.igc-table .num { text-align: end; }
.igc-table .cb { width: 1%; }
.igc-table input[type="checkbox"] { width: 16px; height: 16px; accent-color: var(--brand); }
.c-cell { display: flex; align-items: center; gap: .6rem; min-width: 0; }
.c-ic { width: 32px; height: 32px; border-radius: 9px; display: grid; place-items: center; color: #fff; font-size: 1rem; flex-shrink: 0; }
.c-txt { display: flex; flex-direction: column; min-width: 0; }
.c-nm { display: block; font-weight: 600; color: var(--ink); max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.c-meta { display: flex; flex-wrap: wrap; align-items: center; gap: .35rem; margin-top: 2px; font-size: .68rem; color: var(--muted); }
.c-tag { padding: 0 6px; border-radius: 5px; background: #F1F3F7; color: var(--text); font-weight: 600; }
.c-delivery { display: flex; align-items: center; gap: .45rem; }
.c-money { color: var(--ink); font-weight: 700; }
.c-sub { display: block; font-size: .66rem; color: var(--muted); }
.c-bar { display: block; width: 64px; height: 4px; margin: 4px 0 0 auto; border-radius: 4px; background: #EEF1F6; overflow: hidden; }
.c-bar span { display: block; height: 100%; background: #4F46E5; border-radius: 4px; }
[dir="rtl"] .c-bar { margin: 4px auto 0 0; }
.c-dash { color: #B4BBCB; cursor: help; }
.c-roas { display: inline-flex; align-items: center; gap: 2px; font-weight: 700; }
.c-roas.up { color: #15803D; } .c-roas.down { color: #DC2626; }
.c-copy strong { display: block; color: var(--ink); max-width: 260px; overflow: hidden; text-overflow: ellipsis; }
.c-copy span { display: block; max-width: 260px; overflow: hidden; text-overflow: ellipsis; font-size: .74rem; color: var(--muted); }
.c-actions { display: inline-flex; gap: .3rem; }
.c-act { min-width: 30px; height: 30px; padding: 0 .4rem; border-radius: 8px; border: 1px solid var(--line); background: #fff; color: var(--text); display: inline-grid; place-items: center; font-size: .95rem; text-decoration: none; cursor: pointer; }
.c-act:hover:not(:disabled) { border-color: #B9CCFF; color: var(--brand); }
.c-act.on { background: var(--brand); border-color: var(--brand); color: #fff; font-weight: 600; font-size: .78rem; }
.c-act:disabled { opacity: .45; cursor: not-allowed; }
.igc-switch { position: relative; width: 36px; height: 20px; border-radius: 999px; border: 0; background: #D4D8E2; cursor: pointer; flex-shrink: 0; padding: 0; }
.igc-switch span { position: absolute; top: 3px; inset-inline-start: 3px; width: 14px; height: 14px; border-radius: 50%; background: #fff; transition: inset-inline-start .15s; }
.igc-switch[aria-checked="true"] { background: #4F46E5; }
.igc-switch[aria-checked="true"] span { inset-inline-start: 19px; }
.igc-switch:disabled { opacity: .5; cursor: not-allowed; }
.igc-switch:focus-visible, .c-act:focus-visible, .igc-btn:focus-visible { outline: 3px solid var(--brand-line); outline-offset: 2px; }
.igc-pager { display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: .75rem; padding: .85rem 1.2rem; font-size: .76rem; color: var(--muted); }
.igc-pager .pg { display: flex; gap: .3rem; }

/* placements, reports, sync */
.igc-stack { display: flex; height: 12px; border-radius: 6px; overflow: hidden; gap: 2px; margin-bottom: .9rem; }
.igc-stack span { display: block; height: 100%; }
.igc-placements { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: .55rem; }
.igc-placements li { display: grid; grid-template-columns: 10px minmax(70px, 1fr) auto auto 52px; gap: .55rem; align-items: center; font-size: .78rem; color: var(--text); }
.igc-placements i { width: 9px; height: 9px; border-radius: 3px; }
.igc-placements b { color: var(--ink); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.igc-placements .mut { font-size: .72rem; color: var(--muted); }
.igc-placements .roas { text-align: end; }
.igc-reports { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: .5rem; }
.igc-reports li { display: grid; grid-template-columns: auto minmax(0, 1fr) auto auto auto auto; gap: .45rem; align-items: center; }
.igc-reports .ic { width: 32px; height: 32px; border-radius: 9px; display: grid; place-items: center; background: #EEF0FF; color: #4F46E5; }
.igc-reports .txt { display: flex; flex-direction: column; min-width: 0; }
.igc-reports .txt b { font-size: .8rem; color: var(--ink); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.igc-reports .txt small { font-size: .7rem; color: var(--muted); }
.igc-reports .err { color: #DC2626; }
.igc-reports .fmt { padding: 1px 6px; border-radius: 5px; border: 1px solid var(--line); font-size: .64rem; font-weight: 700; color: var(--text); }
.igc-steps { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: .75rem; }
.igc-steps li { display: flex; gap: .65rem; }
.igc-steps .ic { width: 20px; height: 20px; border-radius: 50%; border: 2px solid #D4D8E2; display: grid; place-items: center; flex-shrink: 0; font-size: .8rem; }
.igc-steps li.done .ic { border-color: #BBF7D0; background: #E7F7EE; color: #15803D; }
.igc-steps strong { display: block; font-size: .8rem; color: var(--ink); }
.igc-steps small { display: block; font-size: .7rem; color: var(--muted); line-height: 1.4; }

/* empty, dialogs */
.igc-empty { text-align: center; padding: 2.4rem 1.5rem; }
.igc-empty.sm { padding: 1.8rem 1.2rem 2.2rem; }
.igc-empty-ic { width: 54px; height: 54px; border-radius: 16px; margin: 0 auto .8rem; display: grid; place-items: center; background: #FFEAF2; color: var(--ig); font-size: 1.6rem; }
.igc-empty h2, .igc-empty h3 { margin: 0 0 .3rem; font-size: 1rem; font-weight: 700; color: var(--ink); }
.igc-empty p { margin: 0 auto 1rem; color: var(--muted); font-size: .84rem; max-width: 50ch; }
.igc-empty-a { display: flex; gap: .5rem; justify-content: center; flex-wrap: wrap; }
.igc-modal { position: fixed; inset: 0; z-index: 2000; display: grid; place-items: center; padding: 16px; background: rgba(16, 24, 40, .45); }
.igc-dialog { width: min(440px, 100%); padding: 22px; border-radius: 16px; background: #fff; box-shadow: 0 24px 60px rgba(16, 24, 40, .3); }
.igc-dialog.wide { width: min(560px, 100%); }
.igc-dialog h3 { margin: 0 0 .5rem; font-size: 1.05rem; font-weight: 700; color: var(--ink); }
.igc-dialog p { margin: 0; font-size: .86rem; line-height: 1.5; overflow-wrap: anywhere; }
.igc-dialog-a { display: flex; justify-content: flex-end; gap: .5rem; margin-top: 1.2rem; }
.igc-form { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: .8rem; margin-top: .8rem; }
.igc-form label { display: flex; flex-direction: column; gap: 4px; font-size: .78rem; font-weight: 600; color: var(--text); }
.igc-form .full { grid-column: 1 / -1; }
.igc-form input, .igc-form select { height: 38px; border: 1px solid var(--line); border-radius: 9px; padding: 0 .65rem; font: inherit; font-weight: 500; font-size: .84rem; color: var(--ink); background: #fff; }
.igc-form-note { display: flex; gap: .45rem; align-items: flex-start; padding: .55rem .7rem; border-radius: 10px; background: var(--wash); font-size: .78rem; }
.igc-form-note .bx { color: var(--brand); margin-top: 2px; }

.igc[dir="rtl"] .bx-chevron-left, .igc[dir="rtl"] .bx-chevron-right { transform: scaleX(-1); }
.igc .bx-spin { animation: igc-spin 1s linear infinite; }
@keyframes igc-spin { to { transform: rotate(360deg); } }
@keyframes igc-shimmer { to { background-position: -200% 0; } }
@media (prefers-reduced-motion: reduce) { .igc * { animation: none !important; transition: none !important; } }

@container igc (max-width: 1180px) { .igc-strip { grid-template-columns: repeat(4, minmax(0, 1fr)); } }
@container igc (max-width: 1060px) {
  .igc-grid.trend { grid-template-columns: minmax(0, 1fr); }
  .igc-grid.three { grid-template-columns: repeat(2, minmax(0, 1fr)); }
  .igc-kpis { grid-template-columns: repeat(2, minmax(0, 1fr)); }
}
@container igc (max-width: 700px) {
  .igc-grid.three { grid-template-columns: minmax(0, 1fr); }
  .igc-strip { grid-template-columns: repeat(2, minmax(0, 1fr)); }
  .igc-head-actions, .igc-bar-end { width: 100%; }
  .igc-head-actions > .igc-btn:not(.icon), .igc-bar-end > * { flex: 1; }
  .igc-tools, .igc-search, .igc-search input { width: 100%; }
  .igc-form { grid-template-columns: minmax(0, 1fr); }
  .igc-head h1 { font-size: 1.35rem; }
}
@container igc (max-width: 440px) { .igc-kpis { grid-template-columns: minmax(0, 1fr); } .igc-mini-stats { grid-template-columns: minmax(0, 1fr); } }
</style>
