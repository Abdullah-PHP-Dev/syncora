<template>
  <div class="fbc" :dir="i18n.dir">
    <nav class="fbc-crumbs" :aria-label="t('breadcrumb')">
      <a :href="data.urls.dashboard">{{ t('ads_manager') }}</a><span aria-hidden="true">/</span><span>Facebook</span>
    </nav>

    <!-- ================= HEADER ================= -->
    <header class="fbc-card fbc-head">
      <span class="fbc-mark" aria-hidden="true"><i class="bx bxl-facebook-circle"></i></span>
      <div class="fbc-head-main">
        <div class="fbc-head-title">
          <h1>{{ t('title') }}</h1>
          <span v-if="!account" class="fbc-pill mut"><span class="d"></span>{{ t('not_connected') }}</span>
          <span v-else-if="!account.healthy" class="fbc-pill bad"><span class="d"></span>{{ t('reconnect_needed') }}</span>
          <span v-else class="fbc-pill ok"><span class="d"></span>{{ t('connected') }}</span>
        </div>
        <div v-if="account" class="fbc-head-meta">
          <div ref="switcher" class="fbc-dd">
            <button type="button" class="fbc-acct" :aria-expanded="switchOpen ? 'true' : 'false'" aria-haspopup="listbox" @click="switchOpen = !switchOpen">
              <span class="lbl">{{ t('ad_account') }}</span>
              <bdi dir="auto" class="nm">{{ account.name }}</bdi>
              <span class="mut">· {{ account.masked_id }}<template v-if="account.currency"> · {{ account.currency }}</template></span>
              <i class="bx bx-chevron-down"></i>
            </button>
            <div v-if="switchOpen" class="fbc-menu wide" role="listbox" :aria-label="t('ad_accounts')">
              <a v-for="a in data.accounts" :key="a.id" :href="switchUrl(a)" role="option" :aria-selected="a.selected ? 'true' : 'false'" class="fbc-menu-acct" :class="{ on: a.selected }">
                <span class="fbc-av">{{ initials(a.name) }}</span>
                <span class="txt"><bdi dir="auto">{{ a.name }}</bdi><small>{{ a.currency || '—' }} · {{ a.masked_id }}</small></span>
                <i v-if="a.selected" class="bx bx-check"></i>
              </a>
              <a :href="data.urls.connections" class="fbc-menu-foot"><i class="bx bx-plus"></i>{{ t('connect_another') }}</a>
            </div>
          </div>
          <span class="fbc-meta-txt">
            <span :title="account.business_name ? null : t('business_unknown')">{{ t('business') }}: <bdi dir="auto">{{ account.business_name || '—' }}</bdi></span>
            <span class="sep" aria-hidden="true">·</span>
            <span>{{ account.last_synced_at ? t('synced_at', { when: dateTime(account.last_synced_at) }) : t('never_synced') }}</span>
          </span>
        </div>
        <p v-else class="fbc-meta-txt">{{ t('connect_hint') }}</p>
      </div>
      <div class="fbc-head-actions">
        <button v-if="account" type="button" class="fbc-btn ghost" :disabled="syncing || !account.healthy" :title="!account.healthy ? t('blockers.token_expired') : null" @click="syncNow">
          <i class="bx bx-sync" :class="{ 'bx-spin': syncing }"></i>{{ syncing ? t('syncing') : t('sync_now') }}
        </button>
        <a v-if="data.can_manage" :href="data.urls.create" class="fbc-btn primary"><i class="bx bx-plus"></i>{{ t('create_campaign') }}</a>
        <a v-else-if="!account" :href="data.urls.connections" class="fbc-btn primary"><i class="bx bx-link"></i>{{ t('connect') }}</a>
        <div ref="moreMenu" class="fbc-dd">
          <button type="button" class="fbc-btn ghost icon" :aria-label="t('more')" :aria-expanded="moreOpen ? 'true' : 'false'" @click="moreOpen = !moreOpen"><i class="bx bx-dots-horizontal-rounded"></i></button>
          <div v-if="moreOpen" class="fbc-menu end" role="menu">
            <a v-if="account" :href="metaUrl" target="_blank" rel="noopener" role="menuitem"><i class="bx bx-link-external"></i>{{ t('open_in_meta') }}</a>
            <a :href="data.urls.connections" role="menuitem"><i class="bx bx-link"></i>{{ t('manage_connections') }}</a>
            <a :href="data.urls.dashboard" role="menuitem"><i class="bx bx-grid-alt"></i>{{ t('all_platforms') }}</a>
          </div>
        </div>
      </div>
    </header>

    <div v-if="account && data.manage_blocker" class="fbc-note" :class="data.manage_blocker === 'token_expired' ? 'bad' : ''" role="note">
      <i class="bx" :class="data.manage_blocker === 'token_expired' ? 'bx-error-circle' : 'bx-lock-alt'"></i>
      <div><strong>{{ t('blockers.' + data.manage_blocker) }}</strong><p>{{ t('blockers.' + data.manage_blocker + '_text') }}</p></div>
      <a v-if="data.manage_blocker === 'token_expired'" :href="data.urls.connections" class="fbc-btn ghost sm">{{ t('reconnect') }}</a>
    </div>

    <div v-if="toastMsg" class="fbc-toast" :class="toastOk ? 'ok' : 'bad'" role="status">
      <i class="bx" :class="toastOk ? 'bx-check-circle' : 'bx-error-circle'"></i><span>{{ toastMsg }}</span>
      <button type="button" :aria-label="t('dismiss')" @click="toastMsg = ''"><i class="bx bx-x"></i></button>
    </div>

    <!-- ================= NO ACCOUNT ================= -->
    <section v-if="!account" class="fbc-card fbc-empty">
      <span class="fbc-empty-ic"><i class="bx bxl-facebook-circle"></i></span>
      <h2>{{ t('empty_account_title') }}</h2>
      <p>{{ t('empty_account_text') }}</p>
      <a :href="data.urls.connections" class="fbc-btn primary"><i class="bx bx-link"></i>{{ t('connect') }}</a>
    </section>

    <template v-else>
      <!-- ================= TABS + RANGE ================= -->
      <div class="fbc-bar">
        <div class="fbc-tabs" role="tablist" :aria-label="t('objects')">
          <button v-for="tb in TABS" :key="tb" type="button" role="tab" :aria-selected="tab === tb ? 'true' : 'false'" :class="{ on: tab === tb }" @click="setTab(tb)">
            {{ t('tabs.' + tb) }} <span class="n">{{ int(data.counts[tb]) }}</span>
          </button>
        </div>
        <label class="fbc-select">
          <i class="bx bx-calendar" aria-hidden="true"></i><span class="fbc-sr">{{ t('range_label') }}</span>
          <select v-model.number="days" @change="loadInsights(false)">
            <option v-for="d in [7, 14, 30, 90]" :key="d" :value="d">{{ t('range_option', { days: d }) }}</option>
          </select>
        </label>
      </div>

      <!-- ================= KPIs ================= -->
      <div class="fbc-kpis">
        <div class="fbc-card fbc-kpi">
          <span class="lbl">{{ t('kpi.campaigns') }}</span>
          <strong class="val">{{ int(s.total) }}</strong>
          <div class="fbc-statusbar" role="img" :aria-label="t('kpi.status_aria', { active: s.active, paused: s.paused })">
            <span class="a" :style="{ width: share(s.active) + '%' }"></span><span class="p" :style="{ width: share(s.paused) + '%' }"></span><span class="o" :style="{ width: share(s.other) + '%' }"></span>
          </div>
          <small><span class="ok-t">{{ t('kpi.active_n', { n: int(s.active) }) }}</span> · <span class="warn-t">{{ t('kpi.paused_n', { n: int(s.paused) }) }}</span></small>
        </div>
        <div class="fbc-card fbc-kpi">
          <span class="lbl">{{ t('kpi.daily_budget') }}</span>
          <strong class="val"><span class="cur">{{ cur }}</span>{{ num(s.daily_budget_total) }}</strong>
          <small>{{ t('kpi.daily_across', { n: s.daily_campaigns }) }} · {{ s.lifetime_campaigns ? t('kpi.lifetime_across', { amount: money(s.lifetime_budget_total), n: s.lifetime_campaigns }) : t('kpi.no_lifetime') }}</small>
        </div>
        <div class="fbc-card fbc-kpi" :class="{ pending: !ins }">
          <span class="lbl">{{ t('kpi.spent') }}</span>
          <div v-if="insLoading" class="fbc-skel lg"></div>
          <template v-else-if="ins">
            <strong class="val"><span class="cur">{{ cur }}</span>{{ num(ins.totals.current.spend) }}</strong>
            <small><span v-if="spendDelta" class="fbc-delta" :class="spendDelta.tone"><i class="bx" :class="spendDelta.icon"></i>{{ spendDelta.text }}</span> {{ t('vs_previous_days', { days }) }}</small>
          </template>
          <template v-else>
            <strong class="val dash" :title="insError || t('kpi.awaiting')">—</strong>
            <span class="fbc-dashline" aria-hidden="true"></span>
            <small>{{ insError || t('kpi.awaiting') }}</small>
          </template>
        </div>
        <div class="fbc-card fbc-kpi" :class="{ pending: !ins }">
          <span class="lbl">{{ t('kpi.results') }}</span>
          <div v-if="insLoading" class="fbc-skel lg"></div>
          <template v-else-if="ins && ins.totals.current.result_type && ins.totals.current.result_type !== 'mixed'">
            <strong class="val">{{ int(ins.totals.current.results) }} <span class="unit">{{ resultLabel(ins.totals.current.result_type, ins.totals.current.results) }}</span></strong>
            <small>{{ ins.totals.current.cost_per_result !== null ? t('kpi.cost_per', { amount: money(ins.totals.current.cost_per_result), result: resultOne(ins.totals.current.result_type).toLowerCase() }) : t('kpi.no_results') }}</small>
          </template>
          <template v-else-if="ins && ins.totals.current.result_type === 'mixed'">
            <strong class="val sm">{{ t('kpi.mixed') }}</strong>
            <small>{{ t('kpi.mixed_text') }}</small>
          </template>
          <template v-else>
            <strong class="val dash" :title="t('kpi.results_hint')">—</strong>
            <span class="fbc-dashline" aria-hidden="true"></span>
            <small>{{ ins ? t('kpi.no_activity') : t('kpi.results_hint') }}</small>
          </template>
        </div>
      </div>

      <!-- ================= PERFORMANCE (live Meta insights) ================= -->
      <section v-if="tab === 'campaigns' && (insLoading || (ins && ins.details))" class="fbc-perf" :aria-label="t('perf.title')">
        <div v-if="insLoading" class="fbc-card fbc-pad"><div class="fbc-skel chart"></div></div>
        <div v-else-if="!hasDelivery" class="fbc-note"><i class="bx bx-bar-chart-alt-2"></i><div><strong>{{ t('perf.no_delivery') }}</strong><p>{{ t('perf.no_delivery_text', { days }) }}</p></div></div>
        <template v-else>
          <div class="fbc-card fbc-strip">
            <div v-for="m in perfStrip" :key="m.key" class="fbc-strip-item">
              <span>{{ m.label }}</span><strong>{{ m.value }}</strong>
            </div>
          </div>

          <div class="fbc-perf-grid">
            <section class="fbc-card fbc-pad">
              <div class="fbc-perf-h">
                <div><h2>{{ perfHeadline }}</h2><p>{{ resultsPerDay ? t('perf.chart_sub', { result: resultName.toLowerCase() }) : t('perf.chart_sub_spend') }}</p></div>
                <div class="fbc-legend"><span><i class="sq"></i>{{ t('perf.spend') }}</span><span v-if="resultsPerDay"><i class="ln"></i>{{ resultName }}</span></div>
              </div>
              <spend-results-chart v-if="details.daily" :spend="details.daily.map((d) => d.spend)" :results="resultsPerDay ? details.daily.map((d) => d.results) : []"
                                   :labels="details.daily.map((d) => shortDay(d.date))" :format-spend="moneyAxis" :format-count="int"
                                   :spend-label="t('perf.spend')" :result-label="resultName" :aria-label="perfHeadline" />
              <p v-else class="fbc-muted-line"><i class="bx bx-info-circle"></i>{{ t('perf.part_unavailable') }}</p>
            </section>

            <section class="fbc-card fbc-pad">
              <h2 class="fbc-h">{{ t('perf.path') }}</h2>
              <ol v-if="pathSteps.length" class="fbc-path">
                <template v-for="(st, i) in pathSteps">
                  <li :key="st.key" :class="{ last: i === pathSteps.length - 1 }"><span>{{ st.label }}</span><b>{{ int(st.value) }}</b></li>
                  <li v-if="st.rate !== null" :key="st.key + '-r'" class="rate"><i class="bx bx-down-arrow-alt"></i>{{ st.rateLabel }}</li>
                </template>
              </ol>
              <p v-else class="fbc-muted-line"><i class="bx bx-info-circle"></i>{{ t('perf.part_unavailable') }}</p>
            </section>
          </div>

          <div class="fbc-perf-grid two">
            <section class="fbc-card fbc-pad">
              <div class="fbc-perf-h"><h2 class="fbc-h">{{ breakdownTitle('placement') }}</h2><span class="fbc-unit">{{ breakdownUnit }}</span></div>
              <ul v-if="placementRows.length" class="fbc-bk">
                <li v-for="r in placementRows" :key="r.key" :class="{ best: r.best }">
                  <div class="h"><span>{{ r.label }}<small v-if="r.sub"> · {{ r.sub }}</small></span><b>{{ r.display }}</b></div>
                  <div class="fbc-track"><span :style="{ width: r.width + '%' }"></span></div>
                </li>
              </ul>
              <p v-else class="fbc-muted-line"><i class="bx bx-info-circle"></i>{{ details.placements === null ? t('perf.part_unavailable') : t('perf.no_rows') }}</p>
            </section>

            <section class="fbc-card fbc-pad">
              <div class="fbc-perf-h"><h2 class="fbc-h">{{ breakdownTitle('region') }}</h2><span v-if="regionTotal" class="fbc-unit">{{ t('perf.of_total', { total: regionTotal }) }}</span></div>
              <ul v-if="regionRows.length" class="fbc-bk compact">
                <li v-for="r in regionRows" :key="r.key">
                  <div class="row"><span dir="auto">{{ r.label }}</span><div class="fbc-track"><span :style="{ width: r.width + '%' }"></span></div><b>{{ r.display }}</b></div>
                </li>
              </ul>
              <p v-else class="fbc-muted-line"><i class="bx bx-info-circle"></i>{{ details.regions === null ? t('perf.part_unavailable') : t('perf.no_rows') }}</p>
              <div v-if="ageRows.length" class="fbc-ages">
                <div v-for="r in ageRows" :key="r.key" :class="{ top: r.top }"><small>{{ r.label }}</small><strong>{{ r.display }}</strong></div>
              </div>
            </section>
          </div>

          <section class="fbc-card fbc-compare">
            <div class="fbc-perf-h pad"><h2 class="fbc-h">{{ t('perf.comparison') }}</h2><span class="fbc-unit">{{ resultsComparable ? t('perf.sorted_cpr', { result: resultSingle.toLowerCase() }) : t('perf.sorted_spend') }}</span></div>
            <div class="fbc-table-wrap">
              <table class="fbc-table">
                <thead><tr>
                  <th>{{ t('cols.campaign') }}</th><th class="num">{{ t('perf.spent_cur', { currency: cur }) }}</th><th class="num">{{ t('perf.impressions') }}</th><th class="num">{{ t('perf.ctr') }}</th>
                  <th class="num">{{ resultsComparable ? resultName : t('cols.results') }}</th><th class="num">{{ t('perf.cost_per_result') }}</th><th>{{ resultsComparable ? t('perf.share_results') : t('perf.share_spend') }}</th>
                </tr></thead>
                <tbody>
                  <tr v-for="r in comparisonRows" :key="r.id">
                    <td><bdi dir="auto" class="c-nm">{{ r.name || t('untitled') }}</bdi><span class="c-meta">{{ t('id_n', { id: r.id }) }}<template v-if="r.objective"> · {{ objectiveLabel(r.objective) }}</template></span></td>
                    <td class="num">{{ num(r.m.spend) }}</td>
                    <td class="num">{{ int(r.m.impressions) }}</td>
                    <td class="num">{{ r.m.ctr === null ? '—' : pct(r.m.ctr) }}</td>
                    <td class="num"><b>{{ r.m.results === null ? '—' : int(r.m.results) }}</b><small v-if="!resultsComparable && r.m.result_type" class="c-sub">{{ resultLabel(r.m.result_type) }}</small></td>
                    <td class="num">{{ r.m.cost_per_result === null ? '—' : num(r.m.cost_per_result) }}</td>
                    <td><div class="c-share"><div class="fbc-track"><span :style="{ width: r.share + '%' }"></span></div><small>{{ r.share }}%</small></div></td>
                  </tr>
                </tbody>
                <tfoot v-if="comparisonRows.length > 1">
                  <tr>
                    <td>{{ t('perf.total') }}</td><td class="num">{{ num(ins.totals.current.spend) }}</td><td class="num">{{ int(ins.totals.current.impressions) }}</td>
                    <td class="num">{{ ins.totals.current.ctr === null ? '—' : pct(ins.totals.current.ctr) }}</td>
                    <td class="num">{{ resultsComparable ? int(ins.totals.current.results) : '—' }}</td>
                    <td class="num">{{ resultsComparable && ins.totals.current.cost_per_result !== null ? num(ins.totals.current.cost_per_result) : '—' }}</td><td></td>
                  </tr>
                </tfoot>
              </table>
            </div>
          </section>
          <p class="fbc-source"><i class="bx bx-info-circle"></i>{{ t('perf.source', { since: shortDay(ins.since), until: shortDay(ins.until) }) }}</p>
        </template>
      </section>

      <div class="fbc-grid">
        <!-- ================= TABLES ================= -->
        <section class="fbc-card fbc-main">
          <div class="fbc-main-h">
            <div class="fbc-main-title">
              <h2>{{ t('tabs.' + tab) }}</h2>
              <div v-if="tab === 'campaigns' && data.campaigns.length" class="fbc-chips" role="tablist" :aria-label="t('status_filter')">
                <button v-for="f in statusChips" :key="f.key" type="button" role="tab" :aria-selected="statusChip === f.key ? 'true' : 'false'" :class="{ on: statusChip === f.key }" @click="statusChip = f.key; page = 1">{{ t('chips.' + f.key) }} <span>{{ f.count }}</span></button>
              </div>
            </div>
            <div v-if="currentRows.length" class="fbc-tools">
              <label class="fbc-search"><i class="bx bx-search" aria-hidden="true"></i><input v-model.trim="query" type="search" :placeholder="t('search.' + tab)" :aria-label="t('search.' + tab)" @input="page = 1"></label>
              <template v-if="tab === 'campaigns'">
                <label class="fbc-select sm"><span class="fbc-sr">{{ t('sort') }}</span>
                  <select v-model="sort" @change="page = 1">
                    <option v-for="o in sortOptions" :key="o" :value="o">{{ t('sorts.' + o) }}</option>
                  </select>
                </label>
                <div ref="filtersMenu" class="fbc-dd">
                  <button type="button" class="fbc-btn ghost" :aria-expanded="filtersOpen ? 'true' : 'false'" @click="filtersOpen = !filtersOpen"><i class="bx bx-filter-alt"></i>{{ t('filters') }}<span v-if="activeFilters" class="fbc-dotn">{{ activeFilters }}</span></button>
                  <div v-if="filtersOpen" class="fbc-menu end form">
                    <label>{{ t('objective') }}
                      <select v-model="objectiveFilter" @change="page = 1">
                        <option value="">{{ t('any') }}</option>
                        <option v-for="o in objectives" :key="o" :value="o">{{ objectiveLabel(o) }}</option>
                      </select>
                    </label>
                    <label>{{ t('schedule') }}
                      <select v-model="scheduleFilter" @change="page = 1">
                        <option value="">{{ t('any') }}</option>
                        <option v-for="o in ['ongoing', 'scheduled', 'ended']" :key="o" :value="o">{{ t('schedules.' + o) }}</option>
                      </select>
                    </label>
                    <button type="button" class="fbc-link" @click="objectiveFilter = ''; scheduleFilter = ''; page = 1">{{ t('clear_filters') }}</button>
                  </div>
                </div>
                <div ref="columnsMenu" class="fbc-dd">
                  <button type="button" class="fbc-btn ghost" :aria-expanded="columnsOpen ? 'true' : 'false'" @click="columnsOpen = !columnsOpen"><i class="bx bx-columns"></i>{{ t('columns') }}</button>
                  <div v-if="columnsOpen" class="fbc-menu end form">
                    <label v-for="c in OPTIONAL_COLUMNS" :key="c" class="check"><input v-model="visibleCols" type="checkbox" :value="c" @change="saveCols">{{ t('cols.' + c) }}</label>
                  </div>
                </div>
              </template>
            </div>
          </div>

          <div v-if="tab === 'campaigns' && selected.length && data.can_manage" class="fbc-bulk" role="region" :aria-label="t('bulk')">
            <strong>{{ t('selected_n', { n: selected.length }) }}</strong>
            <button type="button" class="fbc-btn ghost sm" :disabled="busy" @click="askBulk('PAUSED')"><i class="bx bx-pause"></i>{{ t('pause') }}</button>
            <button type="button" class="fbc-btn ghost sm" :disabled="busy" @click="askBulk('ACTIVE')"><i class="bx bx-play"></i>{{ t('activate') }}</button>
            <button type="button" class="fbc-link" @click="selected = []">{{ t('clear') }}</button>
          </div>

          <!-- empty states -->
          <div v-if="!currentRows.length" class="fbc-empty sm">
            <span class="fbc-empty-ic"><i class="bx" :class="tab === 'campaigns' ? 'bx-rocket' : 'bx-layer'"></i></span>
            <h3>{{ t('empty.' + tab) }}</h3>
            <p>{{ t('empty.' + tab + '_text') }}</p>
            <div class="fbc-empty-a">
              <a v-if="tab === 'campaigns' && data.can_manage" :href="data.urls.create" class="fbc-btn primary"><i class="bx bx-plus"></i>{{ t('create_campaign') }}</a>
              <button v-if="account.healthy" type="button" class="fbc-btn ghost" :disabled="syncing" @click="syncNow"><i class="bx bx-sync"></i>{{ t('sync_now') }}</button>
            </div>
          </div>
          <div v-else-if="!filtered.length" class="fbc-empty sm">
            <p>{{ t('no_matches') }}</p>
            <button type="button" class="fbc-btn ghost" @click="resetFilters">{{ t('clear_filters') }}</button>
          </div>

          <!-- campaigns -->
          <div v-else-if="tab === 'campaigns'" class="fbc-table-wrap">
            <table class="fbc-table">
              <thead>
                <tr>
                  <th class="cb"><input type="checkbox" :checked="allOnPage" :disabled="!data.can_manage" :aria-label="t('select_all')" @change="togglePage($event.target.checked)"></th>
                  <th>{{ t('cols.campaign') }}</th>
                  <th>{{ t('cols.delivery') }}</th>
                  <th v-if="col('budget')" class="num">{{ t('cols.budget') }}</th>
                  <th v-if="col('spent')" class="num">{{ t('cols.spent') }}</th>
                  <th v-if="col('results')" class="num">{{ t('cols.results') }}</th>
                  <th v-if="col('schedule')">{{ t('cols.schedule') }}</th>
                  <th class="num"><span class="fbc-sr">{{ t('cols.actions') }}</span></th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="c in pageRows" :key="c.id" :class="{ sel: selected.includes(c.id) }">
                  <td class="cb"><input v-model="selected" type="checkbox" :value="c.id" :disabled="!data.can_manage" :aria-label="t('select_row', { name: c.name })"></td>
                  <td>
                    <bdi dir="auto" class="c-nm" :title="c.name">{{ c.name || t('untitled') }}</bdi>
                    <span class="c-meta">
                      <span v-if="c.objective" class="c-tag">{{ objectiveLabel(c.objective) }}</span>
                      <span>{{ t('id_n', { id: c.id }) }} · {{ tc('adsets_n', c.adsets_count, { count: c.adsets_count }) }}</span>
                    </span>
                  </td>
                  <td>
                    <div class="c-delivery">
                      <button type="button" class="fbc-switch" role="switch" :aria-checked="isActive(c) ? 'true' : 'false'" :aria-label="isActive(c) ? t('pause_name', { name: c.name }) : t('activate_name', { name: c.name })"
                              :disabled="!data.can_manage || !c.remote_id || busyId === c.id || isArchived(c)" :title="toggleHint(c)" @click="askToggle(c)"><span></span></button>
                      <span class="fbc-pill" :class="statusTone(c.status)"><span class="d"></span>{{ statusLabel(c.status) }}</span>
                    </div>
                  </td>
                  <td v-if="col('budget')" class="num">
                    <span class="c-money">{{ c.daily_budget !== null ? money(c.daily_budget) : (c.lifetime_budget !== null ? money(c.lifetime_budget) : '—') }}</span>
                    <small v-if="c.daily_budget !== null || c.lifetime_budget !== null" class="c-sub">{{ c.daily_budget !== null ? t('per_day') : t('lifetime') }}</small>
                  </td>
                  <td v-if="col('spent')" class="num">
                    <span v-if="metric(c)" class="c-money">{{ money(metric(c).spend) }}</span>
                    <span v-else class="c-dash" :title="metricHint">—</span>
                  </td>
                  <td v-if="col('results')" class="num">
                    <template v-if="metric(c) && metric(c).result_type">
                      <span class="c-money">{{ int(metric(c).results) }}</span>
                      <small class="c-sub">{{ resultLabel(metric(c).result_type, metric(c).results) }}<template v-if="metric(c).cost_per_result !== null"> · {{ money(metric(c).cost_per_result) }}</template></small>
                    </template>
                    <span v-else class="c-dash" :title="metric(c) ? t('kpi.results_hint') : metricHint">—</span>
                  </td>
                  <td v-if="col('schedule')"><span class="c-sched">{{ scheduleLabel(c) }}</span></td>
                  <td class="num">
                    <div class="c-actions">
                      <a v-if="data.can_manage" :href="url('edit', c.id)" class="c-act" :title="t('edit')" :aria-label="t('edit') + ' · ' + c.name"><i class="bx bx-pencil"></i></a>
                      <button v-if="data.can_manage" type="button" class="c-act" :disabled="!c.remote_id || busyId === c.id" :title="t('duplicate')" :aria-label="t('duplicate') + ' · ' + c.name" @click="askDuplicate(c)"><i class="bx bx-copy"></i></button>
                      <div class="fbc-dd">
                        <button type="button" class="c-act" :aria-label="t('more') + ' · ' + c.name" :aria-expanded="rowMenu === c.id ? 'true' : 'false'" @click.stop="rowMenu = rowMenu === c.id ? null : c.id"><i class="bx bx-dots-horizontal-rounded"></i></button>
                        <div v-if="rowMenu === c.id" class="fbc-menu end" role="menu" @click.stop>
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

          <!-- ad sets / ads / creatives -->
          <div v-else class="fbc-table-wrap">
            <table class="fbc-table">
              <thead>
                <tr>
                  <th>{{ t('cols.name') }}</th>
                  <th v-if="tab !== 'campaigns'">{{ tab === 'ads' ? t('cols.adset') : t('cols.campaign') }}</th>
                  <th v-if="tab === 'ads'">{{ t('cols.campaign') }}</th>
                  <th v-if="tab !== 'creatives'">{{ t('cols.status') }}</th>
                  <th v-if="tab === 'adsets'" class="num">{{ t('cols.budget') }}</th>
                  <th v-if="tab === 'adsets'">{{ t('cols.optimization') }}</th>
                  <th v-if="tab === 'adsets'">{{ t('cols.schedule') }}</th>
                  <th v-if="tab === 'creatives'">{{ t('cols.copy') }}</th>
                  <th v-if="tab === 'creatives'">{{ t('cols.cta') }}</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="r in pageRows" :key="r.id">
                  <td><bdi dir="auto" class="c-nm" :title="r.name">{{ r.name || t('untitled') }}</bdi><span class="c-meta">{{ t('id_n', { id: r.id }) }}</span></td>
                  <td><bdi dir="auto">{{ (tab === 'ads' ? r.adset : r.campaign) || '—' }}</bdi></td>
                  <td v-if="tab === 'ads'"><bdi dir="auto">{{ r.campaign || '—' }}</bdi></td>
                  <td v-if="tab !== 'creatives'"><span class="fbc-pill" :class="statusTone(r.status)"><span class="d"></span>{{ statusLabel(r.status) }}</span></td>
                  <td v-if="tab === 'adsets'" class="num"><span class="c-money">{{ r.budget !== null ? money(r.budget) : '—' }}</span><small v-if="r.budget !== null" class="c-sub">{{ r.budget_mode === 'lifetime_budget' ? t('lifetime') : t('per_day') }}</small></td>
                  <td v-if="tab === 'adsets'">{{ r.optimization_goal ? objectiveLabel(r.optimization_goal) : '—' }}</td>
                  <td v-if="tab === 'adsets'"><span class="c-sched">{{ scheduleLabel(r) }}</span></td>
                  <td v-if="tab === 'creatives'" class="c-copy"><strong v-if="r.headline" dir="auto">{{ r.headline }}</strong><span dir="auto">{{ r.message || (r.headline ? '' : '—') }}</span></td>
                  <td v-if="tab === 'creatives'">{{ r.call_to_action ? objectiveLabel(r.call_to_action) : '—' }}</td>
                </tr>
              </tbody>
            </table>
          </div>

          <div v-if="filtered.length" class="fbc-pager">
            <span>{{ t('showing', { from: (page - 1) * PAGE + 1, to: Math.min(page * PAGE, filtered.length), total: filtered.length, what: t('tabs.' + tab).toLowerCase() }) }}</span>
            <div v-if="pages > 1" class="pg">
              <button type="button" class="c-act" :disabled="page === 1" :aria-label="t('prev')" @click="page--"><i class="bx bx-chevron-left"></i></button>
              <button v-for="n in pages" :key="n" type="button" class="c-act" :class="{ on: n === page }" :aria-current="n === page ? 'page' : null" @click="page = n">{{ n }}</button>
              <button type="button" class="c-act" :disabled="page === pages" :aria-label="t('next')" @click="page++"><i class="bx bx-chevron-right"></i></button>
            </div>
          </div>
        </section>

        <!-- ================= RIGHT RAIL ================= -->
        <aside class="fbc-rail">
          <section class="fbc-card fbc-pad">
            <div class="fbc-rail-h"><h2>{{ t('reporting') }}</h2><span class="fbc-progress-n">{{ t('n_of_3', { n: stepsDone }) }}</span></div>
            <div class="fbc-progress" aria-hidden="true"><span v-for="n in 3" :key="n" :class="{ on: n <= stepsDone }"></span></div>
            <ol class="fbc-steps">
              <li v-for="st in steps" :key="st.key" :class="{ done: st.done }">
                <span class="ic"><i v-if="st.done" class="bx bx-check"></i></span>
                <div><strong>{{ t('steps.' + st.key) }}</strong><small>{{ st.text }}</small></div>
              </li>
            </ol>
            <button v-if="account.healthy" type="button" class="fbc-btn soft block" :disabled="insLoading" @click="loadInsights(true)">
              <i class="bx bx-refresh" :class="{ 'bx-spin': insLoading }"></i>{{ ins ? t('refresh_insights') : t('sync_insights') }}
            </button>
          </section>

          <section class="fbc-card fbc-pad">
            <div class="fbc-rail-h"><h2>{{ t('ad_accounts') }} <span class="fbc-count">{{ data.accounts.length }}</span></h2><a :href="data.urls.connections" class="fbc-link">{{ t('manage') }}</a></div>
            <ul class="fbc-accts">
              <li v-for="a in data.accounts" :key="a.id">
                <component :is="a.selected ? 'div' : 'a'" :href="a.selected ? null : switchUrl(a)" class="fbc-acct-row" :class="{ on: a.selected }">
                  <span class="fbc-av">{{ initials(a.name) }}</span>
                  <span class="txt"><bdi dir="auto">{{ a.name }}</bdi><small>{{ a.currency || '—' }} · {{ a.masked_id }}</small></span>
                  <span v-if="a.selected" class="fbc-badge brand">{{ t('selected') }}</span>
                  <span v-else-if="!a.healthy" class="fbc-badge bad">{{ t('reconnect') }}</span>
                  <span v-else-if="a.read_only" class="fbc-badge mut" :title="t('read_only_hint')">{{ t('read_only') }}</span>
                  <span v-else class="fbc-badge ok"><span class="d"></span>{{ t('active') }}</span>
                </component>
              </li>
            </ul>
          </section>
        </aside>
      </div>
    </template>

    <!-- ================= CONFIRM ================= -->
    <div v-if="confirm" class="fbc-modal" role="dialog" aria-modal="true" :aria-labelledby="'fbc-confirm-t'" @keydown.esc="confirm = null" @click.self="confirm = null">
      <div class="fbc-dialog">
        <h3 id="fbc-confirm-t">{{ confirm.title }}</h3>
        <p>{{ confirm.text }}</p>
        <div class="fbc-dialog-a">
          <button ref="cancelBtn" type="button" class="fbc-btn ghost" @click="confirm = null">{{ t('cancel') }}</button>
          <button type="button" class="fbc-btn" :class="confirm.danger ? 'danger' : 'primary'" @click="runConfirm">{{ confirm.ok }}</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onBeforeUnmount, nextTick } from 'vue';
import { createTranslator } from '../facebook/i18n';
import SpendResultsChart from './SpendResultsChart.vue';

const props = defineProps({
  data: { type: Object, required: true },
  // __('ads_campaigns') for the current locale (lang/{en,ar}/ads_campaigns.php).
  messages: { type: Object, default: () => ({}) },
  locale: { type: String, default: 'en' },
  csrf: { type: String, default: '' },
});

const i18n = createTranslator(props.messages, props.locale);
const { t, tc } = i18n;
const known = (key, fallback) => { const v = t(key); return v === key ? fallback : v; };

const TABS = ['campaigns', 'adsets', 'ads', 'creatives'];
const OPTIONAL_COLUMNS = ['budget', 'spent', 'results', 'schedule'];
const PAGE = 10;
const COLS_KEY = 'fbc.columns';

/* ---------------- state ---------------- */
const tab = ref('campaigns');
const days = ref(7);
const query = ref('');
const sort = ref('newest');
const statusChip = ref('all');
const objectiveFilter = ref('');
const scheduleFilter = ref('');
const page = ref(1);
const selected = ref([]);
const visibleCols = ref(loadCols());
const switchOpen = ref(false); const moreOpen = ref(false); const filtersOpen = ref(false); const columnsOpen = ref(false);
const rowMenu = ref(null);
const switcher = ref(null); const moreMenu = ref(null); const filtersMenu = ref(null); const columnsMenu = ref(null); const cancelBtn = ref(null);
const syncing = ref(false); const busy = ref(false); const busyId = ref(null);
const ins = ref(null); const insLoading = ref(false); const insError = ref('');
const toastMsg = ref(''); const toastOk = ref(true);
const confirm = ref(null);
const campaigns = ref(props.data.campaigns.map((c) => ({ ...c })));

const account = computed(() => props.data.account);
const s = computed(() => props.data.summary);
const cur = computed(() => (account.value && account.value.currency) || '');

/* ---------------- formatting ---------------- */
function int(v) { return Number(v || 0).toLocaleString(i18n.intl, { maximumFractionDigits: 0 }); }
function num(v) { return Number(v || 0).toLocaleString(i18n.intl, { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }
function money(v) { return (cur.value ? cur.value + ' ' : '') + num(v); }
function dateTime(iso) { return new Date(iso).toLocaleString(i18n.intl, { month: 'short', day: 'numeric', year: 'numeric', hour: '2-digit', minute: '2-digit' }); }
function shortDate(iso) { return new Date(iso).toLocaleDateString(i18n.intl, { month: 'short', day: 'numeric', year: 'numeric' }); }
function initials(name) { return String(name || '?').trim().split(/\s+/).slice(0, 2).map((w) => w.charAt(0)).join('').toUpperCase() || '?'; }
function share(n) { return s.value.total ? (n / s.value.total) * 100 : 0; }
function objectiveLabel(v) {
  const key = String(v || '').toUpperCase();
  const plain = key.replace(/^OUTCOME_/, '').replace(/_/g, ' ').toLowerCase();
  return known('objectives.' + key, plain.charAt(0).toUpperCase() + plain.slice(1));
}
// Meta action type → label ("lead" → Leads); unknown types read as "Results".
function resultLabel(type) { return known('result_types.' + String(type || '').replace(/\./g, '_'), t('result_types.results')); }
// Singular for "cost per lead" phrases.
function resultOne(type) { return known('result_one.' + String(type || '').replace(/\./g, '_'), t('result_one.results')); }
function url(kind, id) { return props.data.urls[kind].replace('CAMPAIGN_ID', id); }
function switchUrl(a) { return props.data.urls.switch.replace('ACCOUNT_ID', a.id); }
const metaUrl = computed(() => props.data.urls.meta + (account.value ? String(account.value.external_id).replace(/^act_/, '') : ''));

/* ---------------- status ---------------- */
function norm(status) {
  const v = String(status || '').toLowerCase();
  if (['active', 'enable', 'enabled', '1', 'true'].includes(v)) return 'active';
  if (['paused', 'disable', 'disabled', '0', 'false', ''].includes(v)) return 'paused';
  if (['archived', 'deleted'].includes(v)) return 'archived';
  if (['with_issues', 'disapproved', 'error'].includes(v)) return 'issue';
  if (['in_process', 'pending_review', 'campaign_paused', 'adset_paused'].includes(v)) return 'pending';
  return 'other';
}
function isActive(c) { return norm(c.status) === 'active'; }
function isArchived(c) { return norm(c.status) === 'archived'; }
function statusLabel(status) { return t('statuses.' + norm(status)); }
function statusTone(status) { return { active: 'ok', paused: 'warn', archived: 'mut', issue: 'bad', pending: 'info', other: 'mut' }[norm(status)]; }
function toggleHint(c) {
  if (!props.data.can_manage) return t('blockers.' + (props.data.manage_blocker || 'member_read_only'));
  if (!c.remote_id) return t('not_on_meta');
  if (isArchived(c)) return t('archived_hint');
  return null;
}

/* ---------------- insights ---------------- */
function metric(c) { return ins.value && ins.value.campaigns ? ins.value.campaigns[c.id] || null : null; }
const metricHint = computed(() => (insLoading.value ? t('loading') : (insError.value || (ins.value ? t('no_delivery') : t('kpi.awaiting')))));
const spendDelta = computed(() => {
  if (!ins.value || !ins.value.totals.previous) return null;
  const c = ins.value.totals.current.spend; const p = ins.value.totals.previous.spend;
  if (!p) return null;
  const ch = (c - p) / p;
  return { tone: Math.abs(ch) < 0.0005 ? 'flat' : ch > 0 ? 'up' : 'down', icon: ch > 0 ? 'bx-up-arrow-alt' : 'bx-down-arrow-alt', text: (Math.abs(ch) * 100).toLocaleString(i18n.intl, { maximumFractionDigits: 1 }) + '%' };
});
async function loadInsights(fresh) {
  if (!account.value || !account.value.healthy) return;
  insLoading.value = true; insError.value = '';
  try {
    const { data } = await window.axios.get(props.data.urls.insights, { params: { days: days.value, fresh: fresh ? 1 : 0 } });
    if (data.success) { ins.value = data; if (fresh) toast(t('insights_updated'), true); } else { ins.value = null; insError.value = data.error || t('insights_failed'); }
  } catch (e) {
    ins.value = null;
    insError.value = (e.response && e.response.data && (e.response.data.error || e.response.data.message)) || t('insights_failed');
  } finally {
    insLoading.value = false;
  }
}

/* ---------------- performance section ---------------- */
const details = computed(() => (ins.value && ins.value.details) || {});
const hasDelivery = computed(() => !!ins.value && (ins.value.totals.current.spend > 0 || ins.value.totals.current.impressions > 0));
// Results add up only when every campaign counts the same thing (all leads, all purchases...).
const resultsComparable = computed(() => { const rt = ins.value && ins.value.totals.current.result_type; return !!rt && rt !== 'mixed'; });
const resultName = computed(() => (resultsComparable.value ? resultLabel(ins.value.totals.current.result_type) : t('cols.results')));
const resultSingle = computed(() => (resultsComparable.value ? resultOne(ins.value.totals.current.result_type) : t('result_one.results')));
const resultsPerDay = computed(() => resultsComparable.value && Array.isArray(details.value.daily) && details.value.daily.some((d) => d.results !== null));
function pct(v) { return (Number(v || 0) * 100).toLocaleString(i18n.intl, { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + '%'; }
function moneyAxis(v) { return Number(v || 0).toLocaleString(i18n.intl, { maximumFractionDigits: 0 }); }
function shortDay(iso) { return iso ? new Date(iso + 'T00:00:00').toLocaleDateString(i18n.intl, { month: 'short', day: 'numeric' }) : ''; }
const perfHeadline = computed(() => {
  const c = ins.value.totals.current;
  return resultsComparable.value
    ? t('perf.headline_results', { spend: money(c.spend), results: int(c.results), result: resultName.value.toLowerCase(), days: days.value })
    : t('perf.headline_spend', { spend: money(c.spend), days: days.value });
});
const perfStrip = computed(() => {
  const c = ins.value.totals.current;
  const linkClicks = Object.values(ins.value.campaigns || {}).reduce((n, r) => n + Number(r.link_clicks || 0), 0);
  const fmt = (v, f) => (v === null || v === undefined || Number.isNaN(v) ? '—' : f(v));
  return [
    { key: 'impressions', label: t('perf.impressions'), value: int(c.impressions) },
    { key: 'reach', label: t('perf.reach'), value: fmt(c.reach || null, int) },
    { key: 'frequency', label: t('perf.frequency'), value: fmt(c.reach ? c.impressions / c.reach : null, (v) => v.toLocaleString(i18n.intl, { maximumFractionDigits: 2, minimumFractionDigits: 2 })) },
    { key: 'link_clicks', label: t('perf.link_clicks'), value: int(linkClicks) },
    { key: 'cpc', label: t('perf.cpc'), value: fmt(c.cpc, money) },
    { key: 'cpm', label: t('perf.cpm'), value: fmt(c.impressions ? (c.spend / c.impressions) * 1000 : null, money) },
  ];
});
const pathSteps = computed(() => {
  const f = details.value.funnel;
  if (!f || !f.impressions) return [];
  const steps = [{ key: 'impressions', label: t('perf.impressions'), value: f.impressions }, { key: 'link_clicks', label: t('perf.link_clicks'), value: f.link_clicks }];
  if (f.landing_page_views > 0) steps.push({ key: 'lpv', label: t('perf.landing_page_views'), value: f.landing_page_views });
  if (f.results !== null) steps.push({ key: 'results', label: resultName.value, value: f.results });
  const rates = { link_clicks: 'perf.rate_click', lpv: 'perf.rate_load', results: 'perf.rate_result' };
  return steps.map((st, i) => {
    const next = steps[i + 1];
    const rate = next && st.value ? next.value / st.value : null;
    return { ...st, rate, rateLabel: rate === null ? '' : t(rates[next.key], { rate: pct(rate) }) };
  });
});
// Breakdowns rank by cost per result when results are comparable, otherwise by spend.
const breakdownUnit = computed(() => (resultsComparable.value ? t('perf.unit_cpr', { currency: cur.value }) : t('perf.unit_spend', { currency: cur.value })));
function breakdownTitle(kind) {
  if (!resultsComparable.value) return t('perf.' + kind + '_spend');
  return kind === 'placement' ? t('perf.placement_results', { result: resultSingle.value.toLowerCase() }) : t('perf.region_results', { result: resultName.value });
}
function prettify(v) { const s2 = String(v || '').replace(/_/g, ' '); return s2.charAt(0).toUpperCase() + s2.slice(1); }
const placementRows = computed(() => {
  const rows = (details.value.placements || []).filter((r) => r.spend > 0);
  const cmp = resultsComparable.value;
  const metricOf = (r) => (cmp ? (r.results ? r.spend / r.results : null) : r.spend);
  const values = rows.map(metricOf).filter((v) => v !== null);
  const max = Math.max(1e-9, ...values);
  const best = cmp && values.length ? Math.min(...values) : null;
  return rows.slice(0, 6).map((r) => {
    const [pub, pos] = r.key.split('|');
    const v = metricOf(r);
    return {
      key: r.key,
      label: known('placements.' + (pub + '_' + pos).toLowerCase(), prettify(pub) + ' · ' + prettify(pos)),
      sub: cmp ? int(r.results || 0) + ' ' + resultName.value.toLowerCase() : null,
      display: v === null ? '—' : num(v),
      width: v === null ? 0 : Math.max(3, (v / max) * 100),
      best: best !== null && v === best,
    };
  });
});
const regionTotal = computed(() => (resultsComparable.value && ins.value ? int(ins.value.totals.current.results) : null));
function rankRows(list, limit) {
  const cmp = resultsComparable.value;
  const val = (r) => (cmp ? Number(r.results || 0) : r.spend);
  const sorted = [...(list || [])].filter((r) => val(r) > 0).sort((a, b) => val(b) - val(a));
  const top = sorted.slice(0, limit);
  const rest = sorted.slice(limit);
  if (rest.length) top.push({ key: '__other', spend: rest.reduce((n, r) => n + r.spend, 0), results: rest.reduce((n, r) => n + Number(r.results || 0), 0) });
  const max = Math.max(1e-9, ...top.map(val));
  return top.map((r) => ({ ...r, value: val(r), width: Math.max(3, (val(r) / max) * 100), display: cmp ? int(val(r)) : num(val(r)) }));
}
const regionRows = computed(() => rankRows(details.value.regions, 4).map((r) => ({ ...r, label: r.key === '__other' ? t('perf.other_regions') : r.key })));
const ageRows = computed(() => {
  const cmp = resultsComparable.value;
  const rows = (details.value.ages || []).filter((r) => (cmp ? r.results > 0 : r.spend > 0));
  const vals = rows.map((r) => (cmp ? r.results : r.spend));
  const top = Math.max(...vals, 0);
  return rows.map((r, i) => ({ key: r.key, label: r.key, display: cmp ? int(vals[i]) : num(vals[i]), top: vals[i] === top }));
});
const comparisonRows = computed(() => {
  const cmp = resultsComparable.value;
  const rows = campaigns.value.map((c) => ({ ...c, m: metric(c) })).filter((r) => r.m && (r.m.spend > 0 || r.m.impressions > 0));
  const total = rows.reduce((n, r) => n + Number(cmp ? r.m.results || 0 : r.m.spend), 0);
  rows.sort((a, b) => (cmp ? ((a.m.cost_per_result ?? Infinity) - (b.m.cost_per_result ?? Infinity)) : b.m.spend - a.m.spend));
  return rows.map((r) => ({ ...r, share: total ? Math.round((Number(cmp ? r.m.results || 0 : r.m.spend) / total) * 100) : 0 }));
});

/* ---------------- checklist ---------------- */
const steps = computed(() => {
  const counts = props.data.counts;
  const synced = !!(ins.value || (account.value && account.value.insights_synced_at));
  return [
    { key: 'account', done: !!account.value, text: tc('accounts_available', props.data.accounts.length, { count: props.data.accounts.length }) },
    { key: 'imported', done: counts.campaigns > 0, text: counts.campaigns ? t('imported_text', { campaigns: counts.campaigns, adsets: counts.adsets, ads: counts.ads }) : t('imported_none') },
    { key: 'insights', done: synced, text: synced ? t('insights_done', { when: dateTime((ins.value && ins.value.synced_at) || account.value.insights_synced_at) }) : (insError.value || t('insights_pending')) },
  ];
});
const stepsDone = computed(() => steps.value.filter((x) => x.done).length);

/* ---------------- filtering ---------------- */
const objectives = computed(() => [...new Set(campaigns.value.map((c) => c.objective).filter(Boolean))]);
const statusChips = computed(() => {
  const n = (k) => campaigns.value.filter((c) => norm(c.status) === k).length;
  return [{ key: 'all', count: campaigns.value.length }, { key: 'active', count: n('active') }, { key: 'paused', count: n('paused') }];
});
const sortOptions = computed(() => (ins.value ? ['newest', 'name', 'budget', 'spend'] : ['newest', 'name', 'budget']));
const activeFilters = computed(() => (objectiveFilter.value ? 1 : 0) + (scheduleFilter.value ? 1 : 0));
const currentRows = computed(() => (tab.value === 'campaigns' ? campaigns.value : props.data[tab.value]) || []);
function scheduleState(r) {
  const now = Date.now();
  if (r.start_time && new Date(r.start_time).getTime() > now) return 'scheduled';
  if (r.end_time && new Date(r.end_time).getTime() < now) return 'ended';
  return 'ongoing';
}
function scheduleLabel(r) {
  const st = scheduleState(r);
  if (st === 'scheduled') return t('starts', { date: shortDate(r.start_time) });
  if (r.end_time) return (r.start_time ? shortDate(r.start_time) + ' – ' : '') + shortDate(r.end_time);
  return t('schedules.ongoing');
}
const filtered = computed(() => {
  const q = query.value.toLowerCase();
  let rows = currentRows.value.filter((r) => !q || [r.name, r.objective, r.campaign, r.adset, r.headline, r.remote_id, String(r.id)].some((v) => String(v || '').toLowerCase().includes(q)));
  if (tab.value !== 'campaigns') return rows;
  rows = rows.filter((c) => (statusChip.value === 'all' || norm(c.status) === statusChip.value)
    && (!objectiveFilter.value || c.objective === objectiveFilter.value)
    && (!scheduleFilter.value || scheduleState(c) === scheduleFilter.value));
  const budget = (c) => Number(c.daily_budget ?? c.lifetime_budget ?? -1);
  return [...rows].sort((a, b) => {
    if (sort.value === 'name') return String(a.name || '').localeCompare(String(b.name || ''), i18n.intl);
    if (sort.value === 'budget') return budget(b) - budget(a);
    if (sort.value === 'spend') return ((metric(b) || {}).spend || -1) - ((metric(a) || {}).spend || -1);
    return String(b.created_at || '').localeCompare(String(a.created_at || '')) || b.id - a.id;
  });
});
const pages = computed(() => Math.max(1, Math.ceil(filtered.value.length / PAGE)));
const pageRows = computed(() => filtered.value.slice((page.value - 1) * PAGE, page.value * PAGE));
const allOnPage = computed(() => pageRows.value.length > 0 && pageRows.value.every((c) => selected.value.includes(c.id)));
function togglePage(on) {
  const ids = pageRows.value.map((c) => c.id);
  selected.value = on ? [...new Set([...selected.value, ...ids])] : selected.value.filter((id) => !ids.includes(id));
}
function setTab(tb) { tab.value = tb; page.value = 1; query.value = ''; selected.value = []; }
function resetFilters() { query.value = ''; statusChip.value = 'all'; objectiveFilter.value = ''; scheduleFilter.value = ''; page.value = 1; }

/* Columns choice is a per-browser convenience. */
function loadCols() {
  try { const v = JSON.parse(window.localStorage.getItem(COLS_KEY)); if (Array.isArray(v)) return v.filter((c) => OPTIONAL_COLUMNS.includes(c)); } catch (e) { /* storage unavailable */ }
  return [...OPTIONAL_COLUMNS];
}
function saveCols() { try { window.localStorage.setItem(COLS_KEY, JSON.stringify(visibleCols.value)); } catch (e) { /* storage unavailable */ } }
function col(name) { return visibleCols.value.includes(name); }

/* ---------------- actions ---------------- */
function toast(msg, ok) { toastMsg.value = msg; toastOk.value = ok; }
function errorText(e, fallback) { return (e.response && e.response.data && (e.response.data.message || e.response.data.error)) || fallback; }
function ask(opts) { confirm.value = opts; nextTick(() => cancelBtn.value && cancelBtn.value.focus()); }
async function runConfirm() { const c = confirm.value; confirm.value = null; if (c) await c.run(); }

async function setStatus(c, status) {
  busyId.value = c.id;
  try {
    const { data } = await window.axios.patch(url('status', c.id), { status }, { headers: { 'X-CSRF-TOKEN': props.csrf, Accept: 'application/json' } });
    c.status = data.status || status.toLowerCase();
    return true;
  } catch (e) {
    toast(errorText(e, t('status_failed')), false);
    return false;
  } finally {
    busyId.value = null;
  }
}
function askToggle(c) {
  const activate = !isActive(c);
  ask({
    title: activate ? t('confirm_activate_title') : t('confirm_pause_title'),
    text: activate ? t('confirm_activate_text', { name: c.name }) : t('confirm_pause_text', { name: c.name }),
    ok: activate ? t('activate') : t('pause'),
    run: async () => { if (await setStatus(c, activate ? 'ACTIVE' : 'PAUSED')) toast(activate ? t('activated') : t('paused'), true); },
  });
}
function askBulk(status) {
  const rows = campaigns.value.filter((c) => selected.value.includes(c.id) && c.remote_id && !isArchived(c) && (status === 'ACTIVE') !== isActive(c));
  if (!rows.length) { toast(t('bulk_nothing'), false); return; }
  ask({
    title: status === 'ACTIVE' ? t('confirm_activate_title') : t('confirm_pause_title'),
    text: tc('confirm_bulk_text', rows.length, { count: rows.length }),
    ok: status === 'ACTIVE' ? t('activate') : t('pause'),
    run: async () => {
      busy.value = true;
      let done = 0;
      for (const c of rows) { if (await setStatus(c, status)) done++; }
      busy.value = false;
      selected.value = [];
      if (done) toast(tc('bulk_done', done, { count: done }), done === rows.length);
    },
  });
}
function askDuplicate(c) {
  ask({
    title: t('confirm_duplicate_title'), text: t('confirm_duplicate_text', { name: c.name }), ok: t('duplicate'),
    run: async () => {
      busyId.value = c.id;
      try {
        const { data } = await window.axios.post(url('duplicate', c.id), {}, { headers: { 'X-CSRF-TOKEN': props.csrf, Accept: 'application/json' } });
        toast(data.message || t('duplicated'), true);
        setTimeout(() => window.location.reload(), 1200);
      } catch (e) {
        toast(errorText(e, t('duplicate_failed')), false);
      } finally {
        busyId.value = null;
      }
    },
  });
}
function askDelete(c) {
  rowMenu.value = null;
  ask({
    title: t('confirm_delete_title'), text: t('confirm_delete_text', { name: c.name }), ok: t('delete'), danger: true,
    run: async () => {
      const form = document.createElement('form');
      form.method = 'POST';
      form.action = url('destroy', c.id);
      form.innerHTML = `<input type="hidden" name="_method" value="DELETE"><input type="hidden" name="_token" value="${props.csrf}">`;
      document.body.appendChild(form);
      form.submit();
    },
  });
}
async function syncNow() {
  if (syncing.value) return;
  syncing.value = true;
  try {
    const { data } = await window.axios.post(props.data.urls.sync, {}, { headers: { 'X-CSRF-TOKEN': props.csrf } });
    const n = (data.data && data.data.synced) || 0;
    toast(t('synced_msg', { campaigns: n, adsets: (data.data && data.data.ad_sets) || 0, ads: (data.data && data.data.ads) || 0 }), true);
    setTimeout(() => window.location.reload(), 1200);
  } catch (e) {
    toast(errorText(e, t('sync_failed')), false);
    syncing.value = false;
  }
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
function onKey(e) { if (e.key === 'Escape') { switchOpen.value = moreOpen.value = filtersOpen.value = columnsOpen.value = false; rowMenu.value = null; confirm.value = null; } }
onMounted(() => { document.addEventListener('click', closeMenus); document.addEventListener('keydown', onKey); loadInsights(false); });
onBeforeUnmount(() => { document.removeEventListener('click', closeMenus); document.removeEventListener('keydown', onKey); });
</script>

<style scoped>
.fbc { --ink: #161B2B; --text: #4B5263; --muted: #8A92A3; --line: #E7E9F0; --line-soft: #F1F3F7; --wash: #F8F9FC;
  --brand: #004CFF; --brand-soft: #EEF3FF; --brand-line: #CFDCFF; --fb: #1877F2;
  --shadow: 0 1px 2px rgba(16, 24, 40, .04), 0 8px 24px rgba(16, 24, 40, .03);
  display: flex; flex-direction: column; gap: 16px; min-width: 0; color: var(--text); container-type: inline-size; container-name: fbc; }
.fbc-sr { position: absolute !important; width: 1px; height: 1px; overflow: hidden; clip: rect(0 0 0 0); white-space: nowrap; }
.fbc-card { background: #fff; border: 1px solid var(--line); border-radius: 16px; box-shadow: var(--shadow); min-width: 0; }
.fbc-pad { padding: 1.15rem 1.2rem; }
.fbc-crumbs { display: flex; gap: .5rem; font-size: .76rem; font-weight: 700; letter-spacing: .1em; text-transform: uppercase; color: var(--muted); }
.fbc-crumbs a { color: var(--muted); text-decoration: none; }
.fbc-crumbs a:hover { color: var(--brand); }
.fbc-crumbs span:last-child { color: var(--ink); }

/* header */
.fbc-head { display: flex; flex-wrap: wrap; align-items: center; gap: 1rem 1.25rem; padding: 1.15rem 1.3rem; }
.fbc-mark { width: 54px; height: 54px; border-radius: 15px; display: grid; place-items: center; background: linear-gradient(145deg, #1877F2, #0E5BD8); color: #fff; font-size: 1.9rem; box-shadow: 0 8px 18px rgba(24, 119, 242, .28); flex-shrink: 0; }
.fbc-head-main { flex: 1 1 380px; min-width: 0; display: flex; flex-direction: column; gap: .5rem; }
.fbc-head-title { display: flex; flex-wrap: wrap; align-items: center; gap: .6rem; }
.fbc-head h1 { margin: 0; font-size: 1.55rem; font-weight: 700; letter-spacing: -.02em; line-height: 1.25; color: var(--ink); }
.fbc-head-meta { display: flex; flex-wrap: wrap; align-items: center; gap: .5rem .9rem; }
.fbc-acct { display: inline-flex; align-items: center; gap: .4rem; height: 34px; max-width: 100%; padding: 0 .7rem; border: 1px solid var(--line); border-radius: 10px; background: #fff; color: var(--ink); font-size: .8rem; cursor: pointer; }
.fbc-acct .lbl, .fbc-acct .mut { color: var(--muted); white-space: nowrap; }
.fbc-acct .nm { font-weight: 600; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; max-width: 200px; }
.fbc-acct:hover { border-color: var(--brand-line); }
.fbc-meta-txt { display: inline-flex; flex-wrap: wrap; gap: .35rem; font-size: .8rem; color: var(--muted); margin: 0; }
.fbc-head-actions { display: flex; gap: .5rem; align-items: center; flex-wrap: wrap; }

/* buttons, pills, menus */
.fbc-btn { display: inline-flex; align-items: center; justify-content: center; gap: .4rem; height: 38px; padding: 0 .95rem; border-radius: 10px; border: 1px solid transparent; font-size: .84rem; font-weight: 600; text-decoration: none; cursor: pointer; white-space: nowrap; transition: background .15s, border-color .15s, color .15s; }
.fbc-btn.primary { background: var(--brand) linear-gradient(105deg, #004CFF, #5235FF); color: #fff; box-shadow: 0 6px 16px rgba(0, 76, 255, .2); }
.fbc-btn.primary:hover { background: #0043E0 linear-gradient(105deg, #0043E0, #4429F0); color: #fff; }
.fbc-btn.ghost { background: #fff; border-color: var(--line); color: var(--ink); }
.fbc-btn.ghost:hover:not(:disabled) { border-color: #B9CCFF; color: var(--brand); }
.fbc-btn.soft { background: var(--brand-soft); color: var(--brand); }
.fbc-btn.soft:hover:not(:disabled) { background: #E2EAFF; }
.fbc-btn.danger { background: #DC2626; color: #fff; }
.fbc-btn.icon { width: 38px; padding: 0; font-size: 1.15rem; }
.fbc-btn.sm { height: 32px; padding: 0 .7rem; font-size: .78rem; }
.fbc-btn.block { width: 100%; margin-top: .9rem; }
.fbc-btn:disabled { opacity: .55; cursor: not-allowed; }
.fbc-link { border: 0; background: none; padding: 0; color: var(--brand); font-weight: 600; font-size: .8rem; cursor: pointer; text-decoration: none; }
.fbc-pill { display: inline-flex; align-items: center; gap: 5px; height: 24px; padding: 0 9px; border-radius: 999px; font-size: .72rem; font-weight: 600; white-space: nowrap; }
.fbc-pill .d, .fbc-badge .d { width: 6px; height: 6px; border-radius: 50%; background: currentColor; }
.fbc-pill.ok { background: #E7F7EE; color: #15803D; }
.fbc-pill.warn { background: #FFF6E5; color: #B45309; }
.fbc-pill.bad { background: #FDECEC; color: #DC2626; }
.fbc-pill.info { background: #EFF8FF; color: #175CD3; }
.fbc-pill.mut { background: #F1F3F7; color: #64748B; }
.fbc-dd { position: relative; }
.fbc-menu { position: absolute; top: calc(100% + 6px); inset-inline-start: 0; z-index: 40; min-width: 220px; padding: 6px; background: #fff; border: 1px solid var(--line); border-radius: 12px; box-shadow: 0 18px 40px rgba(16, 24, 40, .16); display: flex; flex-direction: column; }
.fbc-menu.end { inset-inline-start: auto; inset-inline-end: 0; }
.fbc-menu.wide { min-width: 300px; }
.fbc-menu > a, .fbc-menu > button { display: flex; align-items: center; gap: 9px; padding: 8px 10px; border: 0; background: none; border-radius: 8px; color: var(--text); font-size: .82rem; font-weight: 500; text-decoration: none; text-align: start; cursor: pointer; }
.fbc-menu > a:hover, .fbc-menu > button:hover { background: var(--brand-soft); color: var(--brand); }
.fbc-menu > .danger { color: #DC2626; }
.fbc-menu > .danger:hover { background: #FDECEC; color: #DC2626; }
.fbc-menu .muted { padding: 8px 10px; font-size: .78rem; color: var(--muted); }
.fbc-menu.form { padding: 12px; gap: 10px; min-width: 240px; }
.fbc-menu.form label { display: flex; flex-direction: column; gap: 4px; font-size: .76rem; font-weight: 600; color: var(--text); }
.fbc-menu.form label.check { flex-direction: row; align-items: center; gap: 8px; font-weight: 500; font-size: .82rem; }
.fbc-menu.form select { height: 34px; border: 1px solid var(--line); border-radius: 8px; padding: 0 .5rem; font-size: .82rem; color: var(--ink); background: #fff; }
.fbc-menu-acct { display: flex; align-items: center; gap: 10px; padding: 8px 10px; border-radius: 8px; text-decoration: none; color: var(--text); }
.fbc-menu-acct:hover, .fbc-menu-acct.on { background: var(--brand-soft); }
.fbc-menu-acct .txt, .fbc-acct-row .txt { display: flex; flex-direction: column; min-width: 0; flex: 1; }
.fbc-menu-acct .txt bdi, .fbc-acct-row .txt bdi { font-weight: 600; color: var(--ink); font-size: .84rem; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.fbc-menu-acct small, .fbc-acct-row small { font-size: .72rem; color: var(--muted); }
.fbc-menu-acct .bx-check { color: var(--brand); font-size: 1.1rem; }
.fbc-menu-foot { margin-top: 4px; border-top: 1px solid var(--line-soft); padding-top: 10px !important; color: var(--brand) !important; font-weight: 600 !important; }
.fbc-av { width: 34px; height: 34px; border-radius: 10px; display: grid; place-items: center; flex-shrink: 0; background: #EEF3FF; color: #1D4ED8; font-size: .76rem; font-weight: 700; }

/* notes, toast */
.fbc-note { display: flex; gap: 10px; align-items: center; padding: 12px 14px; border-radius: 12px; background: #F5F8FF; border: 1px solid #DCE5FB; font-size: .8rem; line-height: 1.5; }
.fbc-note > .bx { font-size: 1.15rem; color: var(--brand); }
.fbc-note > div { flex: 1; min-width: 0; }
.fbc-note strong { color: var(--ink); display: block; }
.fbc-note p { margin: 2px 0 0; }
.fbc-note.bad { background: #FFF8F7; border-color: #F6D3CF; }
.fbc-note.bad > .bx { color: #DC2626; }
.fbc-toast { display: flex; align-items: center; gap: .6rem; padding: .7rem 1rem; border-radius: 12px; font-size: .84rem; font-weight: 500; }
.fbc-toast.ok { background: #ECFDF3; color: #067647; border: 1px solid #ABEFC6; }
.fbc-toast.bad { background: #FEF3F2; color: #B42318; border: 1px solid #FECDCA; }
.fbc-toast button { margin-inline-start: auto; border: 0; background: none; color: inherit; font-size: 1.1rem; cursor: pointer; }

/* tabs + range */
.fbc-bar { display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: .75rem; }
.fbc-tabs { display: inline-flex; gap: 2px; padding: 4px; border-radius: 12px; background: #EEF0F6; overflow-x: auto; max-width: 100%; scrollbar-width: none; }
.fbc-tabs button { display: inline-flex; align-items: center; gap: .45rem; height: 36px; padding: 0 .9rem; border: 0; border-radius: 9px; background: none; color: var(--text); font-size: .86rem; font-weight: 600; cursor: pointer; white-space: nowrap; }
.fbc-tabs button.on { background: #fff; color: var(--ink); box-shadow: 0 1px 3px rgba(16, 24, 40, .1); }
.fbc-tabs .n { font-size: .72rem; color: var(--muted); }
.fbc-tabs button.on .n { min-width: 20px; height: 20px; padding: 0 6px; border-radius: 999px; display: inline-grid; place-items: center; background: var(--brand-soft); color: var(--brand); }
.fbc-select { display: inline-flex; align-items: center; gap: .45rem; height: 38px; padding: 0 .75rem; border: 1px solid var(--line); border-radius: 10px; background: #fff; font-size: .84rem; color: var(--ink); }
.fbc-select > .bx { color: var(--muted); }
.fbc-select select { border: 0; background: none; font: inherit; font-weight: 500; color: var(--ink); outline: none; cursor: pointer; }
.fbc-select.sm { height: 36px; font-size: .8rem; }
.fbc-select:focus-within { border-color: var(--brand-line); box-shadow: 0 0 0 3px var(--brand-soft); }

/* KPIs */
.fbc-kpis { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 14px; }
.fbc-kpi { display: flex; flex-direction: column; gap: .4rem; padding: 1rem 1.1rem; }
.fbc-kpi.pending { border-style: dashed; box-shadow: none; }
.fbc-kpi .lbl { font-size: .82rem; font-weight: 500; color: var(--text); }
.fbc-kpi .val { font-size: 1.75rem; font-weight: 700; letter-spacing: -.02em; color: var(--ink); font-variant-numeric: tabular-nums; line-height: 1.2; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.fbc-kpi .val.sm { font-size: 1.15rem; }
.fbc-kpi .val.dash { color: #B4BBCB; }
.fbc-kpi .cur, .fbc-kpi .unit { font-size: .95rem; font-weight: 600; color: var(--muted); margin-inline-end: .2rem; }
.fbc-kpi small { font-size: .76rem; color: var(--muted); line-height: 1.45; }
.fbc-kpi .ok-t { color: #15803D; font-weight: 600; }
.fbc-kpi .warn-t { color: #B45309; font-weight: 600; }
.fbc-statusbar { display: flex; height: 6px; border-radius: 6px; overflow: hidden; background: #EEF1F6; }
.fbc-statusbar .a { background: #16A34A; } .fbc-statusbar .p { background: #F59E0B; } .fbc-statusbar .o { background: #C3C9D6; }
.fbc-dashline { height: 4px; background: repeating-linear-gradient(90deg, #E2E6EF 0 10px, transparent 10px 16px); border-radius: 4px; }
.fbc-delta { display: inline-flex; align-items: center; font-weight: 600; }
/* More or less spend isn't good or bad by itself: neutral colour, the arrow carries direction. */
.fbc-delta { color: var(--text); }
.fbc-skel { height: 12px; border-radius: 6px; background: linear-gradient(90deg, #EEF1F5 25%, #F6F8FB 50%, #EEF1F5 75%); background-size: 200% 100%; animation: fbc-shimmer 1.2s infinite; }
.fbc-skel.lg { height: 30px; width: 60%; }

/* layout */
.fbc-grid { display: grid; grid-template-columns: minmax(0, 1fr) 300px; gap: 16px; align-items: start; }
.fbc-main { padding: 1.1rem 0 0; overflow: hidden; }
.fbc-main-h { display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: .75rem; padding: 0 1.2rem 1rem; }
.fbc-main-title { display: flex; flex-wrap: wrap; align-items: center; gap: .75rem; }
.fbc-main-h h2 { margin: 0; font-size: 1.1rem; font-weight: 700; color: var(--ink); }
.fbc-chips { display: inline-flex; gap: 2px; padding: 3px; border-radius: 10px; background: #F1F3F7; }
.fbc-chips button { height: 30px; padding: 0 .7rem; border: 0; border-radius: 7px; background: none; color: var(--text); font-size: .8rem; font-weight: 600; cursor: pointer; }
.fbc-chips button span { color: var(--muted); font-weight: 500; }
.fbc-chips button.on { background: #fff; color: var(--ink); box-shadow: 0 1px 3px rgba(16, 24, 40, .1); }
.fbc-tools { display: flex; flex-wrap: wrap; gap: .5rem; align-items: center; }
.fbc-search { position: relative; display: inline-flex; }
.fbc-search .bx { position: absolute; inset-inline-start: .65rem; top: 50%; transform: translateY(-50%); color: var(--muted); }
.fbc-search input { height: 38px; width: 220px; border: 1px solid var(--line); border-radius: 10px; padding-inline: 2rem .75rem; font-size: .82rem; color: var(--ink); background: #fff; }
.fbc-search input:focus { outline: none; border-color: var(--brand-line); box-shadow: 0 0 0 3px var(--brand-soft); }
.fbc-dotn { min-width: 18px; height: 18px; border-radius: 999px; background: var(--brand); color: #fff; font-size: .68rem; display: inline-grid; place-items: center; }
.fbc-bulk { display: flex; flex-wrap: wrap; align-items: center; gap: .6rem; margin: 0 1.2rem .9rem; padding: .55rem .8rem; border-radius: 10px; background: var(--brand-soft); font-size: .82rem; color: var(--ink); }

/* table */
.fbc-table-wrap { overflow-x: auto; }
.fbc-table { width: 100%; border-collapse: collapse; font-size: .82rem; min-width: 700px; }
.fbc-table th { padding: .65rem .55rem; background: var(--wash); color: var(--muted); font-size: .74rem; font-weight: 600; text-align: start; border-top: 1px solid var(--line-soft); border-bottom: 1px solid var(--line-soft); white-space: nowrap; }
.fbc-table td { padding: .8rem .55rem; border-bottom: 1px solid var(--line-soft); vertical-align: middle; color: var(--text); }
.fbc-table th:first-child, .fbc-table td:first-child { padding-inline-start: 1.2rem; }
.fbc-table th:last-child, .fbc-table td:last-child { padding-inline-end: 1.2rem; }
.fbc-table tbody tr:hover td, .fbc-table tbody tr.sel td { background: #F7F9FF; }
.fbc-table .num { text-align: end; font-variant-numeric: tabular-nums; }
.fbc-table .cb { width: 1%; }
.fbc-table input[type="checkbox"] { width: 16px; height: 16px; accent-color: var(--brand); }
.c-nm { display: block; font-weight: 600; color: var(--ink); max-width: 220px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.c-meta { display: flex; flex-wrap: wrap; align-items: center; gap: .4rem; margin-top: 3px; font-size: .72rem; color: var(--muted); }
.c-tag { padding: 1px 7px; border-radius: 6px; background: #F1F3F7; color: var(--text); font-weight: 600; }
.c-delivery { display: flex; align-items: center; gap: .55rem; }
.c-money { display: block; font-weight: 600; color: var(--ink); white-space: nowrap; }
.c-sub { display: block; font-size: .72rem; color: var(--muted); white-space: nowrap; }
.c-dash { color: #B4BBCB; cursor: help; }
.c-sched { white-space: nowrap; }
.c-copy strong { display: block; color: var(--ink); max-width: 280px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.c-copy span { display: block; max-width: 280px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-size: .76rem; color: var(--muted); }
.c-actions { display: inline-flex; gap: .35rem; }
.c-act { min-width: 30px; height: 30px; padding: 0 .4rem; border-radius: 9px; border: 1px solid var(--line); background: #fff; color: var(--text); display: inline-grid; place-items: center; font-size: 1rem; text-decoration: none; cursor: pointer; }
.c-act:hover:not(:disabled) { border-color: #B9CCFF; color: var(--brand); }
.c-act.on { background: var(--brand); border-color: var(--brand); color: #fff; font-weight: 600; font-size: .8rem; }
.c-act:disabled { opacity: .45; cursor: not-allowed; }
.fbc-switch { position: relative; width: 38px; height: 22px; border-radius: 999px; border: 0; background: #D4D8E2; cursor: pointer; flex-shrink: 0; transition: background .15s; padding: 0; }
.fbc-switch span { position: absolute; top: 3px; inset-inline-start: 3px; width: 16px; height: 16px; border-radius: 50%; background: #fff; box-shadow: 0 1px 2px rgba(0, 0, 0, .2); transition: inset-inline-start .15s; }
.fbc-switch[aria-checked="true"] { background: #16A34A; }
.fbc-switch[aria-checked="true"] span { inset-inline-start: 19px; }
.fbc-switch:disabled { opacity: .5; cursor: not-allowed; }
.fbc-switch:focus-visible, .c-act:focus-visible, .fbc-btn:focus-visible { outline: 3px solid var(--brand-line); outline-offset: 2px; }
.fbc-pager { display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: .75rem; padding: .9rem 1.2rem; font-size: .8rem; color: var(--muted); }
.fbc-pager .pg { display: flex; gap: .35rem; }

/* empty */
.fbc-empty { text-align: center; padding: 2.5rem 1.5rem; }
.fbc-empty.sm { padding: 2rem 1.2rem 2.4rem; }
.fbc-empty-ic { width: 56px; height: 56px; border-radius: 16px; margin: 0 auto .8rem; display: grid; place-items: center; background: var(--brand-soft); color: var(--brand); font-size: 1.6rem; }
.fbc-empty h2, .fbc-empty h3 { margin: 0 0 .3rem; font-size: 1rem; font-weight: 700; color: var(--ink); }
.fbc-empty p { margin: 0 auto 1rem; color: var(--muted); font-size: .85rem; max-width: 48ch; }
.fbc-empty-a { display: flex; gap: .5rem; justify-content: center; flex-wrap: wrap; }

/* rail */
.fbc-rail { display: flex; flex-direction: column; gap: 16px; min-width: 0; }
.fbc-rail-h { display: flex; justify-content: space-between; align-items: center; gap: .5rem; margin-bottom: .8rem; }
.fbc-rail-h h2 { margin: 0; font-size: 1.02rem; font-weight: 700; color: var(--ink); display: flex; align-items: center; gap: .4rem; }
.fbc-progress-n { font-size: .8rem; font-weight: 700; color: var(--brand); }
.fbc-progress { display: grid; grid-template-columns: repeat(3, 1fr); gap: 5px; margin-bottom: 1rem; }
.fbc-progress span { height: 4px; border-radius: 4px; background: #E7EAF2; }
.fbc-progress span.on { background: var(--brand); }
.fbc-steps { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: .9rem; }
.fbc-steps li { display: flex; gap: .7rem; }
.fbc-steps .ic { width: 22px; height: 22px; border-radius: 50%; border: 2px solid #D4D8E2; display: grid; place-items: center; flex-shrink: 0; margin-top: 1px; }
.fbc-steps li.done .ic { border-color: #BBF7D0; background: #E7F7EE; color: #15803D; }
.fbc-steps strong { display: block; font-size: .86rem; color: var(--ink); }
.fbc-steps small { display: block; font-size: .76rem; color: var(--muted); line-height: 1.45; margin-top: 2px; }
.fbc-count { display: inline-grid; place-items: center; min-width: 22px; height: 20px; padding: 0 7px; font-size: .72rem; font-weight: 600; line-height: 1; color: var(--muted); background: #F1F3F7; border-radius: 999px; }
.fbc-accts { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 4px; }
.fbc-acct-row { display: flex; align-items: center; gap: 10px; padding: 9px 10px; border: 1px solid transparent; border-radius: 12px; text-decoration: none; color: inherit; }
a.fbc-acct-row:hover { background: var(--wash); }
.fbc-acct-row.on { border-color: var(--brand-line); background: #F7F9FF; }
.fbc-badge { display: inline-flex; align-items: center; gap: 4px; height: 22px; padding: 0 8px; border-radius: 999px; font-size: .7rem; font-weight: 600; white-space: nowrap; }
.fbc-badge.brand { background: var(--brand-soft); color: var(--brand); }
.fbc-badge.ok { color: #15803D; }
.fbc-badge.mut { background: #F1F3F7; color: #475467; }
.fbc-badge.bad { background: #FDECEC; color: #DC2626; }

/* dialog */
.fbc-modal { position: fixed; inset: 0; z-index: 2000; display: grid; place-items: center; padding: 16px; background: rgba(16, 24, 40, .45); }
.fbc-dialog { width: min(440px, 100%); padding: 22px; border-radius: 16px; background: #fff; box-shadow: 0 24px 60px rgba(16, 24, 40, .3); }
.fbc-dialog h3 { margin: 0 0 .5rem; font-size: 1.05rem; font-weight: 700; color: var(--ink); }
.fbc-dialog p { margin: 0; font-size: .86rem; color: var(--text); line-height: 1.5; overflow-wrap: anywhere; }
.fbc-dialog-a { display: flex; justify-content: flex-end; gap: .5rem; margin-top: 1.2rem; }

.fbc[dir="rtl"] .bx-chevron-left, .fbc[dir="rtl"] .bx-chevron-right { transform: scaleX(-1); }
@keyframes fbc-shimmer { to { background-position: -200% 0; } }
.fbc .bx-spin { animation: fbc-spin 1s linear infinite; }
@keyframes fbc-spin { to { transform: rotate(360deg); } }
@media (prefers-reduced-motion: reduce) { .fbc * { animation: none !important; transition: none !important; } }

/* performance section */
.fbc-perf { display: flex; flex-direction: column; gap: 16px; }
.fbc-skel.chart { height: 220px; width: 100%; border-radius: 12px; }
.fbc-strip { display: grid; grid-template-columns: repeat(6, minmax(0, 1fr)); }
.fbc-strip-item { display: flex; flex-direction: column; gap: 3px; padding: .85rem 1.1rem; min-width: 0; }
.fbc-strip-item + .fbc-strip-item { border-inline-start: 1px solid var(--line-soft); }
.fbc-strip-item span { font-size: .78rem; color: var(--muted); }
.fbc-strip-item strong { font-size: 1.1rem; font-weight: 700; color: var(--ink); font-variant-numeric: tabular-nums; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.fbc-perf-grid { display: grid; grid-template-columns: minmax(0, 1.7fr) minmax(280px, 1fr); gap: 16px; align-items: stretch; }
.fbc-perf-grid.two { grid-template-columns: repeat(2, minmax(0, 1fr)); }
.fbc-perf-h { display: flex; justify-content: space-between; align-items: flex-start; gap: .75rem; margin-bottom: .9rem; flex-wrap: wrap; }
.fbc-perf-h.pad { padding: 1.1rem 1.2rem 0; }
.fbc-perf-h h2 { margin: 0; font-size: 1rem; font-weight: 700; color: var(--ink); line-height: 1.35; }
.fbc-perf-h p { margin: 3px 0 0; font-size: .78rem; color: var(--muted); }
.fbc-h { margin: 0 0 .9rem; font-size: 1rem; font-weight: 700; color: var(--ink); }
.fbc-perf-h .fbc-h { margin: 0; }
.fbc-unit { font-size: .74rem; color: var(--muted); }
.fbc-legend { display: flex; gap: .9rem; font-size: .76rem; color: var(--text); }
.fbc-legend span { display: inline-flex; align-items: center; gap: .35rem; }
.fbc-legend .sq { width: 10px; height: 10px; border-radius: 2px; background: #CDD3E2; }
.fbc-legend .ln { width: 14px; height: 3px; border-radius: 2px; background: #4F46E5; }
.fbc-muted-line { display: flex; align-items: center; gap: .4rem; margin: 0; padding: 1.5rem 0; font-size: .82rem; color: var(--muted); }
.fbc-path { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 4px; }
.fbc-path li { display: flex; justify-content: space-between; align-items: center; padding: .7rem .85rem; border-radius: 10px; background: #EEF1FD; font-size: .84rem; color: var(--ink); }
.fbc-path li b { font-variant-numeric: tabular-nums; }
.fbc-path li.last { background: #4F46E5; color: #fff; font-weight: 600; }
.fbc-path li.rate { background: none; padding: .1rem .85rem; font-size: .74rem; color: var(--muted); justify-content: flex-start; gap: .25rem; }
.fbc-bk { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: .85rem; }
.fbc-bk .h { display: flex; justify-content: space-between; gap: .5rem; font-size: .8rem; color: var(--ink); margin-bottom: 5px; }
.fbc-bk .h small { color: var(--muted); }
.fbc-bk .h b { font-variant-numeric: tabular-nums; }
.fbc-bk.compact { gap: .6rem; }
.fbc-bk .row { display: grid; grid-template-columns: minmax(90px, 1fr) minmax(0, 1.4fr) auto; gap: .75rem; align-items: center; font-size: .8rem; color: var(--ink); }
.fbc-bk .row b { font-variant-numeric: tabular-nums; min-width: 2.5ch; text-align: end; }
.fbc-track { height: 7px; border-radius: 7px; background: #EEF1F6; overflow: hidden; }
.fbc-track span { display: block; height: 100%; border-radius: 7px; background: #B9C1D6; }
.fbc-bk li.best .fbc-track span, .fbc-bk.compact .fbc-track span { background: #4F46E5; }
.fbc-ages { display: grid; grid-template-columns: repeat(auto-fit, minmax(60px, 1fr)); gap: 6px; margin-top: 1rem; padding-top: .9rem; border-top: 1px solid var(--line-soft); }
.fbc-ages div { display: flex; flex-direction: column; align-items: center; gap: 2px; padding: .4rem .2rem; border-radius: 10px; }
.fbc-ages div.top { background: var(--brand-soft); }
.fbc-ages small { font-size: .72rem; color: var(--muted); }
.fbc-ages strong { font-size: .92rem; color: var(--ink); font-variant-numeric: tabular-nums; }
.fbc-ages div.top small, .fbc-ages div.top strong { color: var(--brand); }
.fbc-compare { overflow: hidden; }
.fbc-compare .fbc-table { margin-top: .25rem; }
.fbc-compare tfoot td { font-weight: 700; color: var(--ink); background: var(--wash); border-bottom: 0; }
.c-share { display: flex; align-items: center; gap: .5rem; min-width: 120px; }
.c-share .fbc-track { flex: 1; }
.c-share .fbc-track span { background: #4F46E5; }
.c-share small { font-size: .74rem; color: var(--muted); min-width: 3ch; text-align: end; }
.fbc-source { display: flex; align-items: center; gap: .4rem; margin: 0; font-size: .76rem; color: var(--muted); }

@container fbc (max-width: 1100px) {
  .fbc-grid { grid-template-columns: minmax(0, 1fr); }
  .fbc-rail { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); }
}
@container fbc (max-width: 860px) {
  .fbc-kpis { grid-template-columns: repeat(2, minmax(0, 1fr)); }
  .fbc-perf-grid, .fbc-perf-grid.two { grid-template-columns: minmax(0, 1fr); }
  .fbc-strip { grid-template-columns: repeat(3, minmax(0, 1fr)); }
  .fbc-strip-item:nth-child(4) { border-inline-start: 0; }
  .fbc-strip-item:nth-child(n+4) { border-top: 1px solid var(--line-soft); }
}
@container fbc (max-width: 640px) {
  .fbc-rail { grid-template-columns: minmax(0, 1fr); }
  .fbc-head-actions { width: 100%; }
  .fbc-head-actions > .fbc-btn:not(.icon) { flex: 1; }
  .fbc-tools, .fbc-search, .fbc-search input { width: 100%; }
  .fbc-bar > .fbc-select { width: 100%; }
}
@container fbc (max-width: 520px) { .fbc-strip { grid-template-columns: repeat(2, minmax(0, 1fr)); } .fbc-strip-item:nth-child(n) { border-inline-start: 0; border-top: 1px solid var(--line-soft); } .fbc-strip-item:nth-child(-n+2) { border-top: 0; } .fbc-strip-item:nth-child(even) { border-inline-start: 1px solid var(--line-soft); } }
@container fbc (max-width: 440px) { .fbc-kpis { grid-template-columns: minmax(0, 1fr); } .fbc-acct .nm { max-width: 120px; } .fbc-head h1 { font-size: 1.3rem; } }
</style>
