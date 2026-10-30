<template>
  <div class="adc" :class="'adc-' + pf" :dir="i18n.dir" :style="brandVars">
    <nav class="adc-crumbs" :aria-label="t('breadcrumb')">
      <a :href="data.urls.dashboard">{{ t('ads_manager') }}</a><span aria-hidden="true">/</span><span>{{ BRAND.name }}</span>
    </nav>

    <!-- ================= HEADER ================= -->
    <header class="adc-card adc-head">
      <span class="adc-mark" :class="pf" aria-hidden="true"><i class="bx" :class="BRAND.icon"></i></span>
      <div class="adc-head-main">
        <div class="adc-head-title">
          <h1>{{ ps('title') }}</h1>
          <span v-if="!account" class="adc-pill mut"><span class="d"></span>{{ t('not_connected') }}</span>
          <span v-else-if="!account.healthy" class="adc-pill bad"><span class="d"></span>{{ t('reconnect_needed') }}</span>
          <span v-else class="adc-pill ok"><span class="d"></span>{{ t('connected') }}</span>
          <span class="adc-tagline">{{ ps('tagline') }}</span>
        </div>
        <div v-if="account" class="adc-head-meta">
          <a v-if="isIg && ig" :href="ig.username ? 'https://www.instagram.com/' + ig.username : data.urls.connections" target="_blank" rel="noopener" class="adc-chip">
            <img v-if="ig.avatar_url" :src="ig.avatar_url" alt="" class="adc-chip-av"><i v-else class="bx bxl-instagram adc-chip-ic"></i>
            <span class="lbl">Instagram</span><bdi dir="auto" class="nm">{{ ig.username ? '@' + ig.username : ig.name }}</bdi>
          </a>
          <a v-else-if="isIg" :href="data.urls.connections" class="adc-chip warn" :title="t('meta.no_identity_hint')"><i class="bx bxl-instagram adc-chip-ic"></i>{{ t('meta.no_identity') }}</a>
          <div ref="switcher" class="adc-dd">
            <button type="button" class="adc-chip btn" :aria-expanded="switchOpen ? 'true' : 'false'" aria-haspopup="listbox" @click="switchOpen = !switchOpen">
              <span class="lbl">{{ t('ad_account') }}</span><bdi dir="auto" class="nm">{{ account.name }}</bdi>
              <span class="mut">· {{ account.masked_id }}<template v-if="account.currency"> · {{ account.currency }}</template></span><i class="bx bx-chevron-down"></i>
            </button>
            <div v-if="switchOpen" class="adc-menu wide" role="listbox" :aria-label="t('ad_accounts')">
              <a v-for="a in data.accounts" :key="a.id" :href="switchUrl(a)" role="option" :aria-selected="a.selected ? 'true' : 'false'" class="adc-menu-acct" :class="{ on: a.selected }">
                <span class="adc-av">{{ initials(a.name) }}</span>
                <span class="txt"><bdi dir="auto">{{ a.name }}</bdi><small>{{ a.currency || '—' }} · {{ a.masked_id }}</small></span>
                <i v-if="a.selected" class="bx bx-check"></i>
              </a>
              <a :href="data.urls.connections" class="adc-menu-foot"><i class="bx bx-plus"></i>{{ t('connect_another') }}</a>
            </div>
          </div>
          <span class="adc-meta-txt">
            <span :title="account.business_name ? null : t('business_unknown')">{{ t('business') }}: <bdi dir="auto">{{ account.business_name || '—' }}</bdi></span>
            <span class="sep" aria-hidden="true">·</span>
            <span><i class="bx bx-time-five"></i> {{ account.last_synced_at ? t('synced_at', { when: dateTime(account.last_synced_at) }) : t('never_synced') }}</span>
          </span>
        </div>
        <p v-else class="adc-meta-txt">{{ ps('connect_hint') }}</p>
      </div>
      <div class="adc-head-actions">
        <button v-if="account" type="button" class="adc-btn ghost" :disabled="syncing || !account.healthy" @click="syncNow"><i class="bx bx-sync" :class="{ 'bx-spin': syncing }"></i>{{ syncing ? t('syncing') : t('sync_now') }}</button>
        <a v-if="data.can_manage" :href="data.urls.create" class="adc-btn primary"><i class="bx bx-plus"></i>{{ t('create_campaign') }}</a>
        <a v-else-if="!account" :href="data.urls.connections" class="adc-btn primary"><i class="bx bx-link"></i>{{ t('connect') }}</a>
        <div ref="moreMenu" class="adc-dd">
          <button type="button" class="adc-btn ghost icon" :aria-label="t('more')" :aria-expanded="moreOpen ? 'true' : 'false'" @click="moreOpen = !moreOpen"><i class="bx bx-dots-horizontal-rounded"></i></button>
          <div v-if="moreOpen" class="adc-menu end" role="menu">
            <a v-if="account" :href="metaUrl" target="_blank" rel="noopener" role="menuitem"><i class="bx bx-link-external"></i>{{ t('open_in_meta') }}</a>
            <a :href="data.urls.connections" role="menuitem"><i class="bx bx-link"></i>{{ t('manage_connections') }}</a>
            <a :href="data.urls.dashboard" role="menuitem"><i class="bx bx-grid-alt"></i>{{ t('all_platforms') }}</a>
          </div>
        </div>
      </div>
    </header>

    <div v-if="account && data.manage_blocker" class="adc-note" :class="data.manage_blocker === 'token_expired' ? 'bad' : ''" role="note">
      <i class="bx" :class="data.manage_blocker === 'token_expired' ? 'bx-error-circle' : 'bx-lock-alt'"></i>
      <div><strong>{{ t('blockers.' + data.manage_blocker) }}</strong><p>{{ t('blockers.' + data.manage_blocker + '_text') }}</p></div>
      <a v-if="data.manage_blocker === 'token_expired'" :href="data.urls.connections" class="adc-btn ghost sm">{{ t('reconnect') }}</a>
    </div>
    <div v-if="toastMsg" class="adc-toast" :class="toastOk ? 'ok' : 'bad'" role="status">
      <i class="bx" :class="toastOk ? 'bx-check-circle' : 'bx-error-circle'"></i><span>{{ toastMsg }}</span>
      <button type="button" :aria-label="t('dismiss')" @click="toastMsg = ''"><i class="bx bx-x"></i></button>
    </div>

    <section v-if="!account" class="adc-card adc-empty">
      <span class="adc-empty-ic"><i class="bx bxl-instagram"></i></span>
      <h2>{{ ps('empty_title') }}</h2>
      <p>{{ ps('empty_text') }}</p>
      <a :href="data.urls.connections" class="adc-btn primary"><i class="bx bx-link"></i>{{ t('connect') }}</a>
    </section>

    <template v-else>
      <!-- ================= TABS + TOOLS ================= -->
      <div class="adc-bar">
        <div class="adc-tabs" role="tablist" :aria-label="t('objects')">
          <button v-for="tb in TABS" :key="tb" type="button" role="tab" :aria-selected="tab === tb ? 'true' : 'false'" :class="{ on: tab === tb }" @click="setTab(tb)">
            {{ tb === 'overview' ? t('meta.overview') : t('tabs.' + tb) }}<span v-if="tb !== 'overview'" class="n">{{ int(data.counts[tb]) }}</span>
          </button>
        </div>
        <div class="adc-bar-end">
          <label class="adc-select">
            <i class="bx bx-calendar" aria-hidden="true"></i><span class="adc-sr">{{ t('range_label') }}</span>
            <select v-model.number="days" @change="loadInsights(false); loadAiCached()">
              <option v-for="d in [7, 14, 30, 90]" :key="d" :value="d">{{ t('range_option', { days: d }) }}</option>
            </select>
          </label>
          <button v-if="data.can_export" type="button" class="adc-btn ghost" :disabled="exporting || !account.healthy" @click="exportCsv"><i class="bx" :class="exporting ? 'bx-loader-alt bx-spin' : 'bx-download'"></i>{{ exporting ? t('reports.exporting') : t('reports.export') }}</button>
          <a v-else :href="data.urls.upgrade" class="adc-btn ghost" :title="t('reports.export_locked')"><i class="bx bx-lock-alt"></i>{{ t('reports.export') }}</a>
          <button type="button" class="adc-btn primary" @click="openReportForm"><i class="bx bx-envelope"></i>{{ t('reports.schedule') }}</button>
        </div>
      </div>

      <template v-if="tab === 'overview'">
        <!-- ================= KPI CARDS ================= -->
        <div class="adc-kpis">
          <div v-for="k in kpis" :key="k.key" class="adc-card adc-kpi" :class="{ pending: !ins && !insLoading }">
            <div class="top">
              <span class="ic" :style="{ background: k.tint[1], color: k.tint[0] }" aria-hidden="true"><i class="bx" :class="k.icon"></i></span>
              <span class="lbl">{{ k.label }}</span>
              <span v-if="k.delta" class="adc-delta" :class="k.delta.tone">{{ k.delta.text }}</span>
            </div>
            <div v-if="insLoading" class="adc-skel lg"></div>
            <div v-else class="val"><span v-if="k.prefix" class="pre">{{ k.prefix }}</span>{{ k.value }}<span v-if="k.suffix" class="suf">{{ k.suffix }}</span></div>
            <template v-if="!insLoading">
              <div v-if="k.bar !== undefined" class="bar-row">
                <div class="lbl-row"><small :title="k.barHint">{{ k.barLabel }}</small><b>{{ k.bar === null ? '—' : Math.round(k.bar) + '%' }}</b></div>
                <div class="adc-track"><span :style="{ width: Math.min(100, k.bar || 0) + '%' }"></span></div>
              </div>
              <div v-else class="foot">
                <small><span v-if="k.subDelta" class="adc-delta" :class="k.subDelta.tone">{{ k.subDelta.text }}</span> {{ k.sub }}</small>
                <ad-sparkline v-if="k.spark && k.spark.length > 1" :values="k.spark" :color="k.sparkColor" :w="92" :h="34" />
              </div>
            </template>
          </div>
        </div>

        <div class="adc-strip">
          <div v-for="m in strip" :key="m.key" class="adc-card adc-mini">
            <span class="lbl"><i class="bx" :class="m.icon" aria-hidden="true"></i>{{ m.label }}</span>
            <strong>{{ m.value }}</strong>
            <small><span v-if="m.delta" class="adc-delta" :class="m.delta.tone">{{ m.delta.text }}</span> {{ m.sub }}</small>
          </div>
        </div>

        <!-- ================= TREND + FUNNEL ================= -->
        <div class="adc-grid trend">
          <section class="adc-card adc-pad">
            <div class="adc-h">
              <div><h2>{{ t('meta.trend_title') }}</h2><p>{{ ps('trend_sub', { days }) }}</p></div>
              <div class="adc-h-side">
                <div class="adc-legend">
                  <span><i class="sq"></i>{{ t('meta.amount_spent') }}</span>
                  <span v-if="resultsComparable"><i class="ln"></i>{{ resultName }}</span>
                  <span v-if="hasRoasDaily"><i class="ln dash"></i>{{ t('meta.roas') }}</span>
                </div>
                <div class="adc-seg sm" role="tablist" :aria-label="t('meta.grouping')">
                  <button v-for="g in ['daily', 'weekly']" :key="g" type="button" role="tab" :aria-selected="grouping === g ? 'true' : 'false'" :class="{ on: grouping === g }" @click="grouping = g">{{ t('meta.groupings.' + g) }}</button>
                </div>
              </div>
            </div>
            <div v-if="insLoading" class="adc-skel chart"></div>
            <spend-results-chart v-else-if="trend" :spend="trend.spend" :results="resultsComparable ? trend.results : []" :ratio="hasRoasDaily ? trend.roas : []"
                                 :labels="trend.labels" :height="250" :color="BRAND.accent" ratio-color="#7C3AED" :format-spend="moneyAxis" :format-count="int"
                                 :format-ratio="(v) => dec(v, 2) + '×'" :spend-label="t('meta.amount_spent')" :result-label="resultName" :ratio-label="t('meta.roas')" :aria-label="t('meta.trend_title')" />
            <p v-else class="adc-muted-line"><i class="bx bx-info-circle"></i>{{ insError || t('kpi.awaiting') }}</p>
          </section>

          <section class="adc-card adc-pad">
            <div class="adc-h"><div><h2>{{ t('meta.funnel_title') }}</h2><p>{{ ps('funnel_sub') }}</p></div></div>
            <ol v-if="funnel.length" class="adc-funnel">
              <li v-for="f in funnel" :key="f.key">
                <span class="lbl"><i class="bx" :class="f.icon" :style="{ color: f.color }"></i>{{ f.label }}</span>
                <span class="track"><span class="bar" :style="{ width: f.width + '%', background: f.color }"></span></span>
                <span class="num"><b>{{ f.value }}</b><small>{{ f.note }}</small></span>
              </li>
            </ol>
            <p v-else class="adc-muted-line"><i class="bx bx-info-circle"></i>{{ insLoading ? t('loading') : t('kpi.awaiting') }}</p>
            <p v-if="ins && kpiRoas !== null" class="adc-callout" v-html="roasCallout"></p>
          </section>
        </div>

        <!-- ================= BUDGET + COMPARISON + AI ================= -->
        <div class="adc-grid three">
          <section ref="budgetCard" class="adc-card adc-pad">
            <div class="adc-h"><div><h2>{{ t('meta.budget_title') }}</h2><p>{{ ps('budget_sub') }}</p></div></div>
            <div v-if="donut.length" class="adc-donut">
              <svg viewBox="0 0 120 120" width="132" height="132" role="img" :aria-label="t('meta.budget_title')">
                <circle cx="60" cy="60" r="46" fill="none" stroke="#EEF1F6" stroke-width="16" />
                <circle v-for="seg in donut" :key="seg.key" cx="60" cy="60" r="46" fill="none" :stroke="seg.color" stroke-width="16"
                        :stroke-dasharray="seg.len + ' ' + (CIRC - seg.len)" :stroke-dashoffset="-seg.offset" transform="rotate(-90 60 60)" />
                <text x="60" y="55" text-anchor="middle" class="t1">{{ t('meta.spent') }}</text>
                <text x="60" y="72" text-anchor="middle" class="t2">{{ moneyCompact(ins.totals.current.spend) }}</text>
              </svg>
              <ul class="adc-donut-legend">
                <li v-for="seg in donut" :key="seg.key"><i :style="{ background: seg.color }"></i><bdi dir="auto">{{ seg.label }}</bdi><b>{{ seg.share }}%</b></li>
              </ul>
            </div>
            <p v-else class="adc-muted-line"><i class="bx bx-info-circle"></i>{{ insLoading ? t('loading') : ps('no_spend') }}</p>
            <div class="adc-pacing">
              <div class="row"><span>{{ t('meta.today_pacing') }}</span><span v-if="todaySpend !== null"><b>{{ money(todaySpend) }}</b> {{ t('meta.of_daily', { amount: money(activeDaily) }) }}</span><span v-else>—</span></div>
              <div class="adc-track"><span :style="{ width: Math.min(100, pacingPct || 0) + '%' }"></span></div>
              <div class="row sub"><small>{{ pacingPct === null ? t('meta.pacing_unavailable') : ps('pacing_note', { pct: Math.round(pacingPct), time: nowTime }) }}</small><small v-if="pacingState" class="adc-pacing-state" :class="pacingState">{{ t('meta.pacing.' + pacingState) }}</small></div>
            </div>
            <div class="adc-mini-stats">
              <div><small>{{ t('meta.daily_budgets') }}</small><b>{{ activeDaily ? money(activeDaily) : '—' }}</b></div>
              <div><small>{{ t('meta.avg_daily_spend') }}</small><b>{{ ins ? money(ins.all_placements_spend / days) : '—' }}</b></div>
              <div><small>{{ t('meta.lifetime_budgets') }}</small><b :title="s.lifetime_campaigns ? money(s.lifetime_budget_total) : null">{{ s.lifetime_campaigns ? moneyCompact(s.lifetime_budget_total) : t('meta.none_set') }}</b></div>
            </div>
          </section>

          <section ref="compareCard" class="adc-card adc-pad">
            <div class="adc-h">
              <div><h2>{{ t('meta.compare_title') }}</h2><p>{{ ps('compare_sub') }}</p></div>
              <div class="adc-seg sm" role="tablist" :aria-label="t('meta.compare_title')">
                <button v-for="m in ['roas', 'cpr', 'ctr']" :key="m" type="button" role="tab" :aria-selected="compareBy === m ? 'true' : 'false'" :class="{ on: compareBy === m }" @click="compareBy = m">{{ t('meta.compare_by.' + m) }}</button>
              </div>
            </div>
            <ul v-if="compareRows.length" class="adc-compare">
              <li v-for="r in compareRows" :key="r.id">
                <div class="h">
                  <span class="nm"><i :style="{ background: r.color }"></i><bdi dir="auto">{{ r.name }}</bdi><span v-if="r.top" class="adc-badge ok">{{ t('meta.top_performer') }}</span></span>
                  <small>{{ r.hint }}</small>
                </div>
                <div class="v"><div class="adc-track"><span :style="{ width: r.width + '%', background: r.color }"></span></div><b>{{ r.display }}</b></div>
              </li>
            </ul>
            <p v-else class="adc-muted-line"><i class="bx bx-info-circle"></i>{{ insLoading ? t('loading') : ps('compare_none') }}</p>
            <p class="adc-foot">{{ t('meta.compare_foot') }}</p>
          </section>

          <section class="adc-card adc-pad adc-ai" :aria-busy="aiLoading ? 'true' : 'false'">
            <div class="adc-h light"><div><h2><span class="spark"><i class="bx bxs-magic-wand"></i></span>{{ t('meta.ai_title') }}</h2><p>{{ ps('ai_sub') }}</p></div></div>
            <div v-if="aiLoading" class="adc-ai-list"><div v-for="n in 3" :key="n" class="adc-ai-item"><span class="adc-skel dark" style="width:40%"></span><span class="adc-skel dark"></span><span class="adc-skel dark" style="width:75%"></span></div></div>
            <div v-else-if="ai && ai.insights && ai.insights.length" class="adc-ai-list">
              <article v-for="(item, i) in ai.insights" :key="i" class="adc-ai-item">
                <span class="tag" :class="item.action">{{ t('meta.ai_tags.' + item.action) }}</span>
                <strong dir="auto">{{ item.title }}</strong>
                <p dir="auto">{{ item.text }}</p>
                <button v-if="item.action !== 'none'" type="button" class="adc-ai-go" @click="aiAction(item.action)">{{ t('ai.actions.' + item.action) }}<i class="bx" :class="i18n.dir === 'rtl' ? 'bx-left-arrow-alt' : 'bx-right-arrow-alt'"></i></button>
              </article>
            </div>
            <p v-else class="adc-ai-empty">{{ hasDelivery ? t('ai.empty') : ps('ai_no_data') }}</p>
            <p v-if="aiError" class="adc-ai-error" role="alert">{{ aiError }} <a v-if="aiCreditIssue" :href="data.urls.usage">{{ t('ai.view_usage') }}</a></p>
            <div class="adc-ai-foot">
              <button type="button" class="adc-btn light sm" :disabled="aiLoading || !hasDelivery" @click="generateAi"><i class="bx bxs-magic-wand"></i>{{ ai ? t('ai.regenerate') : t('ai.generate') }}</button>
              <a v-if="data.urls.ai_copilot" :href="data.urls.ai_copilot" class="adc-btn light-ghost sm"><i class="bx bx-bot"></i>{{ t('meta.ask_copilot') }}</a>
              <small>{{ ai ? t('ai.generated', { when: dateTime(ai.generated_at) }) : t('ai.credit_note') }} · {{ t('meta.ai_note') }}</small>
            </div>
          </section>
        </div>
      </template>

      <!-- ================= CAMPAIGNS (overview + campaigns tab) ================= -->
      <section v-if="tab === 'overview' || tab === 'campaigns'" ref="tableCard" class="adc-card adc-table-card">
        <div class="adc-table-h">
          <div class="ttl">
            <h2>{{ t('tabs.campaigns') }}</h2><span class="adc-count">{{ campaigns.length }}</span>
            <div v-if="campaigns.length" class="adc-chips" role="tablist" :aria-label="t('status_filter')">
              <button v-for="f in statusChips" :key="f.key" type="button" role="tab" :aria-selected="statusChip === f.key ? 'true' : 'false'" :class="{ on: statusChip === f.key }" @click="statusChip = f.key; page = 1">{{ t('chips.' + f.key) }} {{ f.count }}</button>
            </div>
          </div>
          <div v-if="campaigns.length" class="adc-tools">
            <label class="adc-search"><i class="bx bx-search" aria-hidden="true"></i><input v-model.trim="query" type="search" :placeholder="t('search.campaigns')" :aria-label="t('search.campaigns')" @input="page = 1"></label>
            <label class="adc-select sm"><i class="bx bx-sort-alt-2" aria-hidden="true"></i><span class="adc-sr">{{ t('sort') }}</span>
              <select v-model="sort" @change="page = 1"><option v-for="o in sortOptions" :key="o" :value="o">{{ t('meta.sorts.' + o) }}</option></select>
            </label>
            <div ref="filtersMenu" class="adc-dd">
              <button type="button" class="adc-btn ghost" :aria-expanded="filtersOpen ? 'true' : 'false'" @click="filtersOpen = !filtersOpen"><i class="bx bx-filter-alt"></i>{{ t('filters') }}<span v-if="activeFilters" class="adc-dotn">{{ activeFilters }}</span></button>
              <div v-if="filtersOpen" class="adc-menu end form">
                <label>{{ t('objective') }}
                  <select v-model="objectiveFilter" @change="page = 1"><option value="">{{ t('any') }}</option><option v-for="o in objectives" :key="o" :value="o">{{ objectiveLabel(o) }}</option></select>
                </label>
                <label>{{ t('schedule') }}
                  <select v-model="scheduleFilter" @change="page = 1"><option value="">{{ t('any') }}</option><option v-for="o in ['ongoing', 'scheduled', 'ended']" :key="o" :value="o">{{ t('schedules.' + o) }}</option></select>
                </label>
                <button type="button" class="adc-link" @click="objectiveFilter = ''; scheduleFilter = ''; page = 1">{{ t('clear_filters') }}</button>
              </div>
            </div>
            <div ref="columnsMenu" class="adc-dd">
              <button type="button" class="adc-btn ghost" :aria-expanded="columnsOpen ? 'true' : 'false'" @click="columnsOpen = !columnsOpen"><i class="bx bx-columns"></i>{{ t('columns') }}</button>
              <div v-if="columnsOpen" class="adc-menu end form">
                <label v-for="c in OPTIONAL_COLUMNS" :key="c" class="check"><input v-model="visibleCols" type="checkbox" :value="c" @change="saveCols">{{ t('meta.cols.' + c) }}</label>
              </div>
            </div>
          </div>
        </div>

        <div v-if="selected.length && data.can_manage" class="adc-bulk" role="region" :aria-label="t('bulk')">
          <strong>{{ t('selected_n', { n: selected.length }) }}</strong>
          <button type="button" class="adc-btn ghost sm" :disabled="busy" @click="askBulk('PAUSED')"><i class="bx bx-pause"></i>{{ t('pause') }}</button>
          <button type="button" class="adc-btn ghost sm" :disabled="busy" @click="askBulk('ACTIVE')"><i class="bx bx-play"></i>{{ t('activate') }}</button>
          <button type="button" class="adc-link" @click="selected = []">{{ t('clear') }}</button>
        </div>

        <div v-if="!campaigns.length" class="adc-empty sm">
          <span class="adc-empty-ic"><i class="bx bx-rocket"></i></span><h3>{{ t('empty.campaigns') }}</h3><p>{{ t('empty.campaigns_text') }}</p>
          <div class="adc-empty-a">
            <a v-if="data.can_manage" :href="data.urls.create" class="adc-btn primary"><i class="bx bx-plus"></i>{{ t('create_campaign') }}</a>
            <button v-if="account.healthy" type="button" class="adc-btn ghost" :disabled="syncing" @click="syncNow"><i class="bx bx-sync"></i>{{ t('sync_now') }}</button>
          </div>
        </div>
        <div v-else-if="!filtered.length" class="adc-empty sm"><p>{{ t('no_matches') }}</p><button type="button" class="adc-btn ghost" @click="resetFilters">{{ t('clear_filters') }}</button></div>
        <div v-else class="adc-table-wrap">
          <table class="adc-table">
            <thead><tr>
              <th class="cb"><input type="checkbox" :checked="allOnPage" :disabled="!data.can_manage" :aria-label="t('select_all')" @change="togglePage($event.target.checked)"></th>
              <th>{{ t('cols.campaign') }}</th><th>{{ t('cols.delivery') }}</th>
              <th v-if="col('schedule')">{{ t('meta.cols.schedule') }}</th>
              <th v-if="col('budget')" class="num">{{ t('meta.cols.budget') }}</th>
              <th v-if="col('spent')" class="num">{{ t('cols.spent') }}</th>
              <th v-if="col('impressions')" class="num">{{ t('meta.cols.impressions') }}</th>
              <th v-if="col('link_clicks')" class="num">{{ t('meta.cols.link_clicks') }}</th>
              <th v-if="col('ctr')" class="num">{{ t('meta.cols.ctr') }}</th>
              <th v-if="col('cpc')" class="num">{{ t('meta.cols.cpc') }}</th>
              <th v-if="col('cpm')" class="num">{{ t('meta.cols.cpm') }}</th>
              <th v-if="col('results')" class="num">{{ t('cols.results') }}</th>
              <th v-if="col('cpr')" class="num">{{ t('meta.cols.cpr') }}</th>
              <th v-if="col('roas')" class="num">{{ t('meta.cols.roas') }}</th>
              <th class="num">{{ t('cols.actions') }}</th>
            </tr></thead>
            <tbody>
              <tr v-for="c in pageRows" :key="c.id" :class="{ sel: selected.includes(c.id) }">
                <td class="cb"><input v-model="selected" type="checkbox" :value="c.id" :disabled="!data.can_manage" :aria-label="t('select_row', { name: c.name })"></td>
                <td>
                  <div class="adc-c-cell">
                    <span class="adc-c-ic" :style="{ background: objColor(c.objective) }" aria-hidden="true"><i class="bx" :class="objIcon(c.objective)"></i></span>
                    <div class="adc-c-txt">
                      <bdi dir="auto" class="adc-c-nm" :title="c.name">{{ c.name || t('untitled') }}</bdi>
                      <span class="adc-c-meta"><span v-if="c.objective" class="adc-c-tag">{{ objectiveLabel(c.objective) }}</span>{{ placementsLabel(c) }}</span>
                    </div>
                  </div>
                </td>
                <td>
                  <div class="adc-c-delivery">
                    <button type="button" class="adc-switch" role="switch" :aria-checked="isActive(c) ? 'true' : 'false'" :aria-label="isActive(c) ? t('pause_name', { name: c.name }) : t('activate_name', { name: c.name })"
                            :disabled="!data.can_manage || !c.remote_id || busyId === c.id || isArchived(c)" :title="toggleHint(c)" @click="askToggle(c)"><span></span></button>
                    <span class="adc-pill" :class="statusTone(c.status)"><span class="d"></span>{{ statusLabel(c.status) }}</span>
                  </div>
                </td>
                <td v-if="col('schedule')"><span class="adc-c-sub">{{ scheduleLabel(c) }}</span></td>
                <td v-if="col('budget')" class="num">
                  <b class="adc-c-money">{{ c.daily_budget !== null ? money(c.daily_budget) : (c.lifetime_budget !== null ? money(c.lifetime_budget) : '—') }}</b>
                  <span v-if="m(c) && budgetFor(c)" class="adc-c-bar" :title="ps('budget_bar_hint')"><span :style="{ width: Math.min(100, (m(c).spend / budgetFor(c)) * 100) + '%' }"></span></span>
                  <small v-else-if="c.lifetime_budget !== null && c.daily_budget === null" class="adc-c-sub">{{ t('lifetime') }}</small>
                </td>
                <td v-if="col('spent')" class="num"><b v-if="m(c)" class="adc-c-money">{{ money(m(c).spend) }}</b><span v-else class="adc-c-dash" :title="metricHint">—</span></td>
                <td v-if="col('impressions')" class="num">{{ m(c) ? compact(m(c).impressions) : '—' }}</td>
                <td v-if="col('link_clicks')" class="num">{{ m(c) ? int(m(c).link_clicks) : '—' }}</td>
                <td v-if="col('ctr')" class="num">{{ m(c) && m(c).link_ctr !== null ? pct(m(c).link_ctr) : '—' }}</td>
                <td v-if="col('cpc')" class="num">{{ m(c) && m(c).link_cpc !== null ? money(m(c).link_cpc) : '—' }}</td>
                <td v-if="col('cpm')" class="num">{{ m(c) && m(c).cpm !== null ? money(m(c).cpm) : '—' }}</td>
                <td v-if="col('results')" class="num">
                  <template v-if="m(c) && m(c).result_type"><b>{{ m(c).result_type === 'reach' ? compact(m(c).results) : int(m(c).results) }}</b><small class="adc-c-sub">{{ resultLabel(m(c).result_type) }}</small></template>
                  <span v-else class="adc-c-dash" :title="metricHint">—</span>
                </td>
                <td v-if="col('cpr')" class="num">
                  <template v-if="m(c) && m(c).cost_per_result !== null">{{ money(m(c).cost_per_result) }}<small v-if="m(c).result_type === 'reach'" class="adc-c-sub">{{ t('meta.per_reached') }}</small></template><template v-else>—</template>
                </td>
                <td v-if="col('roas')" class="num"><span v-if="m(c) && m(c).roas !== null" class="adc-c-roas" :class="m(c).roas >= 1 ? 'up' : 'down'"><i class="bx" :class="m(c).roas >= 1 ? 'bx-trending-up' : 'bx-trending-down'"></i>{{ dec(m(c).roas, 2) }}×</span><span v-else class="adc-c-dash">—</span></td>
                <td class="num">
                  <div class="adc-c-actions">
                    <a v-if="data.can_manage" :href="url('edit', c.id)" class="adc-c-act" :title="t('edit')" :aria-label="t('edit') + ' · ' + c.name"><i class="bx bx-pencil"></i></a>
                    <button v-if="data.can_manage" type="button" class="adc-c-act" :disabled="!c.remote_id || busyId === c.id" :title="t('duplicate')" :aria-label="t('duplicate') + ' · ' + c.name" @click="askDuplicate(c)"><i class="bx bx-copy"></i></button>
                    <div class="adc-dd">
                      <button type="button" class="adc-c-act" :aria-label="t('more') + ' · ' + c.name" @click.stop="rowMenu = rowMenu === c.id ? null : c.id"><i class="bx bx-dots-horizontal-rounded"></i></button>
                      <div v-if="rowMenu === c.id" class="adc-menu end" role="menu" @click.stop>
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
        <div v-if="filtered.length" class="adc-pager">
          <span>{{ t('meta.showing', { from: (page - 1) * PAGE + 1, to: Math.min(page * PAGE, filtered.length), total: filtered.length }) }}</span>
          <div v-if="pages > 1" class="pg">
            <button type="button" class="adc-c-act" :disabled="page === 1" :aria-label="t('prev')" @click="page--"><i class="bx bx-chevron-left"></i></button>
            <button v-for="n in pages" :key="n" type="button" class="adc-c-act" :class="{ on: n === page }" :aria-current="n === page ? 'page' : null" @click="page = n">{{ n }}</button>
            <button type="button" class="adc-c-act" :disabled="page === pages" :aria-label="t('next')" @click="page++"><i class="bx bx-chevron-right"></i></button>
          </div>
        </div>
      </section>

      <!-- ================= AD SETS / ADS / CREATIVES ================= -->
      <section v-if="['adsets', 'ads', 'creatives'].includes(tab)" ref="tableCard" class="adc-card adc-table-card">
        <div class="adc-table-h">
          <div class="ttl"><h2>{{ t('tabs.' + tab) }}</h2><span class="adc-count">{{ data[tab].length }}</span></div>
          <label v-if="data[tab].length" class="adc-search"><i class="bx bx-search" aria-hidden="true"></i><input v-model.trim="query" type="search" :placeholder="t('search.' + tab)" :aria-label="t('search.' + tab)" @input="page = 1"></label>
        </div>
        <div v-if="!data[tab].length" class="adc-empty sm"><span class="adc-empty-ic"><i class="bx bx-layer"></i></span><h3>{{ t('empty.' + tab) }}</h3><p>{{ t('empty.' + tab + '_text') }}</p></div>
        <div v-else class="adc-table-wrap">
          <table class="adc-table">
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
                <td><bdi dir="auto" class="adc-c-nm">{{ r.name || t('untitled') }}</bdi><span class="adc-c-meta">{{ t('id_n', { id: r.id }) }}</span></td>
                <td><bdi dir="auto">{{ (tab === 'ads' ? r.adset : r.campaign) || '—' }}</bdi></td>
                <td v-if="tab === 'ads'"><bdi dir="auto">{{ r.campaign || '—' }}</bdi></td>
                <td v-if="tab !== 'creatives'"><span class="adc-pill" :class="statusTone(r.status)"><span class="d"></span>{{ statusLabel(r.status) }}</span></td>
                <td v-if="tab === 'adsets'" class="num">{{ r.budget !== null ? money(r.budget) : '—' }}</td>
                <td v-if="tab === 'adsets'">{{ r.optimization_goal ? objectiveLabel(r.optimization_goal) : '—' }}</td>
                <td v-if="tab === 'creatives'" class="adc-c-copy"><strong v-if="r.headline" dir="auto">{{ r.headline }}</strong><span dir="auto">{{ r.message || '—' }}</span></td>
                <td v-if="tab === 'creatives'">{{ r.call_to_action ? objectiveLabel(r.call_to_action) : '—' }}</td>
              </tr>
            </tbody>
          </table>
        </div>
        <div v-if="filtered.length > PAGE" class="adc-pager"><span>{{ t('meta.showing', { from: (page - 1) * PAGE + 1, to: Math.min(page * PAGE, filtered.length), total: filtered.length }) }}</span>
          <div class="pg"><button type="button" class="adc-c-act" :disabled="page === 1" :aria-label="t('prev')" @click="page--"><i class="bx bx-chevron-left"></i></button><button type="button" class="adc-c-act" :disabled="page === pages" :aria-label="t('next')" @click="page++"><i class="bx bx-chevron-right"></i></button></div>
        </div>
      </section>

      <!-- ================= PLACEMENTS + REPORTS + SYNC ================= -->
      <div v-if="tab === 'overview'" class="adc-grid" :class="isIg ? 'three' : 'two'">
        <section ref="placementsCard" class="adc-card adc-pad">
          <div class="adc-h"><div><h2>{{ t('meta.placement_title') }}</h2><p>{{ ps('placement_sub') }}</p></div></div>
          <template v-if="placements.length">
            <div class="adc-stack" role="img" :aria-label="t('meta.placement_title')"><span v-for="p in placements" :key="p.key" :style="{ width: p.share + '%', background: p.color }" :title="p.label + ' · ' + p.share + '%'"></span></div>
            <ul class="adc-placements">
              <li v-for="p in placements" :key="p.key"><i :style="{ background: p.color }"></i><b>{{ p.label }}</b><span class="mut">{{ t('meta.of_spend', { pct: p.share }) }}</span><span>{{ money(p.spend) }}</span><b class="roas">{{ p.roas === null ? '—' : dec(p.roas, 2) + '×' }}</b></li>
            </ul>
          </template>
          <p v-else class="adc-muted-line"><i class="bx bx-info-circle"></i>{{ insLoading ? t('loading') : (ins && details.placements === null ? t('perf.part_unavailable') : t('kpi.awaiting')) }}</p>
        </section>

        <section v-if="!isIg" ref="regionsCard" class="adc-card adc-pad">
          <div class="adc-h"><div><h2>{{ t('meta.audience_title') }}</h2><p>{{ resultsComparable ? t('meta.audience_by_results', { result: resultName.toLowerCase() }) : t('meta.audience_by_spend') }}</p></div></div>
          <ul v-if="regionRows.length" class="adc-regions">
            <li v-for="r in regionRows" :key="r.key"><bdi dir="auto">{{ r.label }}</bdi><span class="adc-track"><span :style="{ width: r.width + '%' }"></span></span><b>{{ r.display }}</b></li>
          </ul>
          <p v-else class="adc-muted-line"><i class="bx bx-info-circle"></i>{{ insLoading ? t('loading') : (!ins ? t('kpi.awaiting') : (details.regions === null ? t('perf.part_unavailable') : t('perf.no_rows'))) }}</p>
          <div v-if="ageRows.length" class="adc-ages">
            <div v-for="r in ageRows" :key="r.key" :class="{ top: r.top }"><small>{{ r.label }}</small><strong>{{ r.display }}</strong></div>
          </div>
        </section>

        <ad-reports ref="reportsCard" :i18n="i18n" :reports="reports" :urls="data.urls" :recipient-email="data.recipient_email || ''" :csrf="csrf" :ask="ask"
                    :title="t('meta.reports_title')" :sub="ps('reports_sub')" :empty="ps('reports_none', { email: data.recipient_email || '—' })"
                    :default-name="ps('default_report')" :format-note="ps('format_note')" @toast="toast" />

        <section class="adc-card adc-pad">
          <div class="adc-h"><div><h2>{{ t('meta.sync_title') }}</h2><p>{{ t('meta.sync_sub') }}</p></div><span class="adc-badge" :class="stepsDone === steps.length ? 'ok' : 'warn'"><span class="d"></span>{{ stepsDone === steps.length ? t('meta.healthy') : t('meta.needs_attention') }}</span></div>
          <ol class="adc-steps">
            <li v-for="st in steps" :key="st.key" :class="{ done: st.done }"><span class="ic"><i v-if="st.done" class="bx bx-check"></i></span><div><strong>{{ t('meta.steps.' + st.key) }}</strong><small>{{ st.text }}</small></div></li>
          </ol>
          <button v-if="account.healthy" type="button" class="adc-btn ghost block" :disabled="insLoading" @click="loadInsights(true)"><i class="bx bx-refresh" :class="{ 'bx-spin': insLoading }"></i>{{ t('refresh_insights') }}</button>
        </section>
      </div>
    </template>


    <!-- ================= CONFIRM ================= -->
    <div v-if="confirm" class="adc-modal" role="dialog" aria-modal="true" aria-labelledby="adc-confirm-t" @click.self="confirm = null">
      <div class="adc-dialog">
        <h3 id="adc-confirm-t">{{ confirm.title }}</h3>
        <p>{{ confirm.text }}</p>
        <div class="adc-dialog-a">
          <button ref="cancelBtn" type="button" class="adc-btn ghost" @click="confirm = null">{{ t('cancel') }}</button>
          <button type="button" class="adc-btn" :class="confirm.danger ? 'danger' : 'primary'" @click="runConfirm">{{ confirm.ok }}</button>
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
import AdReports from './AdReports.vue';
import { downloadCsv } from './adExport';

/*
 * Facebook and Instagram Ads campaigns pages: the seller's Meta ad account.
 * On Instagram every number is narrowed to Instagram placements by
 * InstagramAdService, and campaign controls still act on the whole Meta
 * campaign. Facebook counts all placements and adds regions and ages.
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
const pf = props.data.platform === 'instagram' ? 'instagram' : 'facebook';
const isIg = pf === 'instagram';
const BRAND = {
  instagram: { name: 'Instagram', icon: 'bxl-instagram', accent: '#E1306C', soft: '#FFEAF2', ink: '#BE185D', glow: 'rgba(225, 48, 108, .07)', hover: '#FDF7FA' },
  facebook: { name: 'Facebook', icon: 'bxl-facebook-circle', accent: '#1877F2', soft: '#E7F0FE', ink: '#0B5CD5', glow: 'rgba(24, 119, 242, .07)', hover: '#F5F9FF' },
}[pf];
const brandVars = { '--pf': BRAND.accent, '--pf-soft': BRAND.soft, '--pf-ink': BRAND.ink, '--pf-glow': BRAND.glow, '--pf-hover': BRAND.hover };
function ps(key, params) { return t('meta.scope.' + pf + '.' + key, params); }

const TABS = ['overview', 'campaigns', 'adsets', 'ads', 'creatives'];
const OPTIONAL_COLUMNS = ['schedule', 'budget', 'spent', 'impressions', 'link_clicks', 'ctr', 'cpc', 'cpm', 'results', 'cpr', 'roas'];
const PAGE = 6;
const COLS_KEY = 'adc.columns.' + pf;
const CIRC = 2 * Math.PI * 46;
const PALETTE = [BRAND.accent, '#4F46E5', '#7C3AED', '#F97316', '#0EA5E9', '#14B8A6', '#A3A9BC'];

/* ---------------- state ---------------- */
const tab = ref('overview');
const days = ref(30);
const grouping = ref('daily');
const compareBy = ref('roas');
const query = ref(''); const sort = ref('spend'); const statusChip = ref('all'); const objectiveFilter = ref(''); const scheduleFilter = ref('');
const page = ref(1); const selected = ref([]); const visibleCols = ref(loadCols());
const switchOpen = ref(false); const moreOpen = ref(false); const filtersOpen = ref(false); const columnsOpen = ref(false); const rowMenu = ref(null);
const budgetCard = ref(null); const compareCard = ref(null); const placementsCard = ref(null); const regionsCard = ref(null);
const switcher = ref(null); const moreMenu = ref(null); const filtersMenu = ref(null); const columnsMenu = ref(null); const cancelBtn = ref(null); const tableCard = ref(null);
const syncing = ref(false); const busy = ref(false); const busyId = ref(null);
const ins = ref(null); const insLoading = ref(false); const insError = ref('');
const ai = ref(null); const aiLoading = ref(false); const aiError = ref(''); const aiCreditIssue = ref(false);
const toastMsg = ref(''); const toastOk = ref(true); const confirm = ref(null); const exporting = ref(false);
const reportsCard = ref(null);
const reports = ref((props.data.reports || []).map((r) => ({ ...r })));
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
  return isIg ? t('meta.toggle_hint') : null;
}
const OBJ = { OUTCOME_SALES: ['#F97316', 'bx-trending-up'], OUTCOME_LEADS: ['#14B8A6', 'bx-id-card'], OUTCOME_TRAFFIC: ['#0EA5E9', 'bx-pointer'], OUTCOME_ENGAGEMENT: ['#D946EF', 'bx-heart'], OUTCOME_AWARENESS: ['#F59E0B', 'bx-show'], OUTCOME_APP_PROMOTION: ['#6366F1', 'bx-mobile'] };
function objColor(o) { return (OBJ[String(o || '').toUpperCase()] || [BRAND.accent])[0]; }
function objIcon(o) { return (OBJ[String(o || '').toUpperCase()] || ['', BRAND.icon])[1]; }
function placementsLabel(c) { return [t('id_n', { id: c.id }), tc('adsets_n', c.adsets_count, { count: c.adsets_count })].join(' · '); }
function shortDate(iso) { return new Date(iso).toLocaleDateString(i18n.intl, { month: 'short', day: 'numeric' }); }
function scheduleState(r) {
  const now = Date.now();
  if (r.start_time && new Date(r.start_time).getTime() > now) return 'scheduled';
  if (r.end_time && new Date(r.end_time).getTime() < now) return 'ended';
  return 'ongoing';
}
function scheduleLabel(r) {
  if (scheduleState(r) === 'scheduled') return t('starts', { date: shortDate(r.start_time) });
  if (r.end_time) return (r.start_time ? shortDate(r.start_time) + ' – ' : '') + shortDate(r.end_time);
  return t('schedules.ongoing');
}
function m(c) { return ins.value && ins.value.campaigns ? ins.value.campaigns[c.id] || null : null; }
function budgetFor(c) { return c.daily_budget ? c.daily_budget * days.value : (c.lifetime_budget || 0); }
const metricHint = computed(() => (insLoading.value ? t('loading') : (insError.value || (ins.value ? ps('no_delivery') : t('kpi.awaiting')))));

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
    { key: 'spend', icon: 'bx-wallet', tint: ['#4F46E5', '#EEF0FF'], label: t('meta.kpi.spend'), prefix: c ? cur.value : '', value: c ? nf(c.spend, { maximumFractionDigits: 0 }) : dash, delta: c && p ? delta(c.spend, p.spend) : null,
      bar: c ? util : undefined, barLabel: activeDaily.value ? t('meta.kpi.budget_util') : t('meta.kpi.no_daily'), barHint: t('meta.kpi.budget_util_hint') },
    { key: 'results', icon: 'bx-target-lock', tint: [BRAND.accent, BRAND.soft], label: t('meta.kpi.results'),
      value: c ? (resultsComparable.value ? int(c.results) : t('kpi.mixed')) : dash,
      suffix: c && resultsComparable.value && c.cost_per_result !== null ? ' ' + t('meta.kpi.per_result', { amount: money(c.cost_per_result) }) : '',
      delta: c && p && resultsComparable.value ? delta(c.results, p.results) : null,
      subDelta: c && p && resultsComparable.value ? delta(c.cost_per_result, p.cost_per_result, true) : null,
      sub: c ? (resultsComparable.value ? t('meta.kpi.cpr_vs_prev') : t('kpi.mixed_text')) : (insError.value || t('kpi.awaiting')),
      spark: resultsComparable.value ? dailyRows.value.map((d) => d.results || 0) : null, sparkColor: BRAND.accent },
    { key: 'roas', icon: 'bx-line-chart', tint: ['#16A34A', '#E7F7EE'], label: t('meta.kpi.roas'), value: c && c.roas !== null ? dec(c.roas, 2) : dash, suffix: c && c.roas !== null ? '×' : '',
      delta: c && p && c.roas !== null ? delta(c.roas, p.roas) : null,
      sub: c ? (c.roas !== null ? t('meta.kpi.purchase_value', { amount: money(c.purchase_value) }) : t('meta.kpi.no_value')) : t('kpi.awaiting'),
      spark: c && c.roas !== null ? dailyRows.value.map((d) => (d.spend ? (d.purchase_value || 0) / d.spend : 0)) : null, sparkColor: '#16A34A' },
    { key: 'conversions', icon: 'bx-cart', tint: ['#7C3AED', '#F2EDFF'], label: t('meta.kpi.conversions'), value: c ? int(c.conversions) : dash,
      suffix: c && c.cvr !== null ? ' ' + t('meta.kpi.cvr', { rate: pct(c.cvr) }) : '', delta: c && p ? delta(c.conversions, p.conversions) : null,
      sub: c ? ps('conv_hint') : t('kpi.awaiting'), spark: c ? dailyRows.value.map((d) => d.conversions || 0) : null, sparkColor: '#7C3AED' },
  ];
});
const strip = computed(() => {
  const c = ins.value ? ins.value.totals.current : null; const p = ins.value ? ins.value.totals.previous : null;
  const acc = details.value.account || null;
  const v = (val, f) => (c && val !== null && val !== undefined ? f(val) : '—');
  const vs = t('meta.vs_prev');
  return [
    { key: 'campaigns', icon: 'bx-bullseye', label: t('meta.mini.campaigns'), value: int(s.value.total), sub: t('meta.mini.active_n', { n: s.value.active }) },
    { key: 'impressions', icon: 'bx-show', label: t('meta.mini.impressions'), value: v(c && c.impressions, compact), delta: c && p ? delta(c.impressions, p.impressions) : null, sub: c && p ? vs : '' },
    { key: 'reach', icon: 'bx-group', label: t('meta.mini.reach'), value: acc ? compact(acc.reach) : '—', sub: acc ? t('meta.mini.unique') : '' },
    { key: 'frequency', icon: 'bx-repeat', label: t('meta.mini.frequency'), value: acc && acc.reach ? dec(acc.impressions / acc.reach, 2) : '—', sub: t('meta.mini.frequency_sub') },
    { key: 'link_clicks', icon: 'bx-link', label: t('meta.mini.link_clicks'), value: v(c && c.link_clicks, compact), delta: c && p ? delta(c.link_clicks, p.link_clicks) : null, sub: c && p ? vs : '' },
    { key: 'ctr', icon: 'bx-pointer', label: t('meta.mini.ctr'), value: v(c && c.link_ctr, (x) => pct(x)), delta: c && p ? delta(c.link_ctr, p.link_ctr) : null, sub: c && p ? vs : '' },
    { key: 'cpc', icon: 'bx-coin', label: t('meta.mini.cpc'), value: v(c && c.link_cpc, money), delta: c && p ? delta(c.link_cpc, p.link_cpc, true) : null, sub: c && p ? vs : '' },
    { key: 'cpm', icon: 'bx-receipt', label: t('meta.mini.cpm'), value: v(c && c.cpm, money), delta: c && p ? delta(c.cpm, p.cpm, true) : null, sub: c && p ? vs : '' },
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
    { key: 'impressions', icon: 'bx-show', color: '#4F6BFF', label: t('meta.funnel.impressions'), raw: a.impressions, value: compact(a.impressions), note: t('meta.funnel.top') },
    { key: 'reach', icon: 'bx-group', color: '#6366F1', label: t('meta.funnel.reach'), raw: a.reach, value: compact(a.reach), note: t('meta.funnel.of_impressions', { pct: pct(a.reach / a.impressions, 1) }) },
    { key: 'link_clicks', icon: 'bx-pointer', color: '#8B5CF6', label: t('meta.funnel.link_clicks'), raw: a.link_clicks, value: compact(a.link_clicks), note: a.reach ? t('meta.funnel.ctr_from_reach', { pct: pct(a.link_clicks / a.reach, 1) }) : '' },
    { key: 'conversions', icon: 'bx-cart', color: '#C026D3', label: t('meta.funnel.conversions'), raw: a.conversions, value: int(a.conversions), note: a.link_clicks ? t('meta.funnel.cvr', { pct: pct(a.conversions / a.link_clicks, 1) }) : '' },
  ];
  if (a.purchase_value > 0) rows.push({ key: 'revenue', icon: 'bx-dollar-circle', color: BRAND.accent, label: t('meta.funnel.revenue'), raw: a.purchase_value, value: moneyCompact(a.purchase_value), note: a.spend ? t('meta.funnel.roas_on_sales', { roas: dec(a.purchase_value / a.spend, 2) }) : '' });
  // Square-root scale keeps small stages visible; revenue is money, so it sits just under conversions.
  rows.forEach((r, i) => { r.width = r.key === 'revenue' ? Math.max(4, rows[i - 1].width - 6) : w(r.raw); });
  return rows;
});
const esc = (v) => String(v).replace(/[&<>"]/g, (ch) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' })[ch]);
const roasCallout = computed(() => ps('roas_callout', { one: '<b>' + esc(money(1)) + '</b>', value: '<b>' + esc(money(kpiRoas.value)) + '</b>' }));

/* ---------------- budget utilization ---------------- */
const donut = computed(() => {
  if (!ins.value) return [];
  const rows = campaigns.value.map((c) => ({ c, mm: m(c) })).filter((r) => r.mm && r.mm.spend > 0).sort((a, b) => b.mm.spend - a.mm.spend);
  const total = rows.reduce((n, r) => n + r.mm.spend, 0);
  if (!total) return [];
  const top = rows.slice(0, 5).map((r, i) => ({ key: r.c.id, label: r.c.name || t('untitled'), spend: r.mm.spend, color: PALETTE[i] }));
  const rest = rows.slice(5).reduce((n, r) => n + r.mm.spend, 0);
  if (rest > 0) top.push({ key: 'other', label: t('meta.other_campaigns'), spend: rest, color: PALETTE[6] });
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
      hint: r.mm.cost_per_result !== null && r.mm.result_type ? t('meta.cost_per', { amount: money(r.mm.cost_per_result), result: resultOne(r.mm.result_type).toLowerCase() }) : '',
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
    return { key: r.key, label: known('placements.' + r.key.replace('|', '_'), pos.replace(/_/g, ' ')), spend: r.spend, share: total ? Math.round((r.spend / total) * 100) : 0, roas: r.purchase_value > 0 && r.spend ? r.purchase_value / r.spend : null, color: PALETTE[i] };
  });
});

/* ---------------- regions + ages (Facebook; Meta has no per-platform split) ---------------- */
// Ranked by results when they are comparable, otherwise by spend.
function rankRows(list, limit) {
  const cmp = resultsComparable.value;
  const val = (r) => (cmp ? Number(r.results || 0) : r.spend);
  const sorted = [...(list || [])].filter((r) => val(r) > 0).sort((a, b) => val(b) - val(a));
  const top = sorted.slice(0, limit);
  const rest = sorted.slice(limit);
  if (rest.length) top.push({ key: '__other', spend: rest.reduce((n, r) => n + r.spend, 0), results: rest.reduce((n, r) => n + Number(r.results || 0), 0) });
  const max = Math.max(1e-9, ...top.map(val));
  return top.map((r) => ({ ...r, width: Math.max(3, (val(r) / max) * 100), display: cmp ? int(val(r)) : money(val(r)) }));
}
const regionRows = computed(() => rankRows(details.value.regions, 5).map((r) => ({ ...r, label: r.key === '__other' ? t('perf.other_regions') : r.key })));
const ageRows = computed(() => {
  const cmp = resultsComparable.value;
  const rows = (details.value.ages || []).filter((r) => (cmp ? r.results > 0 : r.spend > 0));
  const vals = rows.map((r) => (cmp ? r.results : r.spend));
  const top = Math.max(...vals, 0);
  return rows.map((r, i) => ({ key: r.key, label: r.key, display: cmp ? int(vals[i]) : moneyCompact(vals[i]), top: vals[i] === top }));
});

/* ---------------- AI "go to" links ---------------- */
function scrollTo(el) { if (el) el.scrollIntoView({ behavior: 'smooth', block: 'start' }); }
function aiAction(action) {
  if (action === 'compare_campaigns') scrollTo(compareCard.value);
  else if (action === 'review_placements') scrollTo(placementsCard.value);
  else if (action === 'review_audience') scrollTo(regionsCard.value || placementsCard.value);
  else if (action === 'review_budget') scrollTo(budgetCard.value);
  else if (action === 'refresh_creative') { setTab('creatives'); nextTick(() => scrollTo(tableCard.value)); }
  else scrollTo(tableCard.value);
}

/* ---------------- sync checklist ---------------- */
const steps = computed(() => {
  const counts = props.data.counts;
  const synced = !!(ins.value || (account.value && account.value.insights_synced_at));
  return [
    isIg && { key: 'instagram', done: !!ig.value, text: ig.value ? (ig.value.username ? '@' + ig.value.username : ig.value.name) : t('meta.no_identity') },
    { key: 'account', done: !!account.value, text: account.value ? [account.value.name, account.value.masked_id, account.value.currency].filter(Boolean).join(' · ') : '' },
    { key: 'imported', done: counts.campaigns > 0, text: counts.campaigns ? t('imported_text', { campaigns: counts.campaigns, adsets: counts.adsets, ads: counts.ads }) : t('imported_none') },
    { key: 'insights', done: synced, text: synced ? t('insights_done', { when: dateTime((ins.value && ins.value.synced_at) || account.value.insights_synced_at) }) : (insError.value || t('insights_pending')) },
  ].filter(Boolean);
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
  rows = rows.filter((c) => (statusChip.value === 'all' || norm(c.status) === statusChip.value) && (!objectiveFilter.value || c.objective === objectiveFilter.value)
    && (!scheduleFilter.value || scheduleState(c) === scheduleFilter.value));
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
const activeFilters = computed(() => (objectiveFilter.value ? 1 : 0) + (scheduleFilter.value ? 1 : 0));
function resetFilters() { query.value = ''; statusChip.value = 'all'; objectiveFilter.value = ''; scheduleFilter.value = ''; page.value = 1; }
function loadCols() { try { const v = JSON.parse(window.localStorage.getItem(COLS_KEY)); if (Array.isArray(v)) return v.filter((c) => OPTIONAL_COLUMNS.includes(c)); } catch (e) { /* storage unavailable */ } return OPTIONAL_COLUMNS.filter((c) => c !== 'schedule'); }
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
  ask({ title: activate ? t('confirm_activate_title') : t('confirm_pause_title'), text: (activate ? t('confirm_activate_text', { name: c.name }) : t('confirm_pause_text', { name: c.name })) + (isIg ? ' ' + t('meta.whole_campaign') : ''), ok: activate ? t('activate') : t('pause'),
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
async function exportCsv() {
  exporting.value = true;
  const error = await downloadCsv(props.data.urls.export, { days: days.value }, pf + '-ads.csv', t('reports.export_failed'));
  exporting.value = false;
  if (error) toast(error, false);
}
// The reports card lives on the overview, so the form opens there.
function openReportForm() { if (tab.value !== 'overview') setTab('overview'); nextTick(() => reportsCard.value && reportsCard.value.open()); }

/* ---------------- menus ---------------- */
function closeMenus(e) {
  const outside = (el) => !el || !el.contains(e.target);
  if (switchOpen.value && outside(switcher.value)) switchOpen.value = false;
  if (moreOpen.value && outside(moreMenu.value)) moreOpen.value = false;
  if (filtersOpen.value && outside(filtersMenu.value)) filtersOpen.value = false;
  if (columnsOpen.value && outside(columnsMenu.value)) columnsOpen.value = false;
  rowMenu.value = null;
}
function onKey(e) { if (e.key === 'Escape') { switchOpen.value = moreOpen.value = filtersOpen.value = columnsOpen.value = false; rowMenu.value = null; confirm.value = null; if (reportsCard.value) reportsCard.value.close(); } }
onMounted(() => { document.addEventListener('click', closeMenus); document.addEventListener('keydown', onKey); loadInsights(false); loadAiCached(); });
onBeforeUnmount(() => { document.removeEventListener('click', closeMenus); document.removeEventListener('keydown', onKey); });
</script>

<style src="./ads-campaigns.css"></style>
