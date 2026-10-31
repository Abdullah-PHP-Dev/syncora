<template>
  <div class="adc" :class="'adc-' + data.platform" :dir="i18n.dir" :style="brandVars">
    <nav class="adc-crumbs" :aria-label="t('breadcrumb')">
      <a :href="data.urls.dashboard">{{ t('ads_manager') }}</a><span aria-hidden="true">/</span><span>{{ name }}</span>
    </nav>

    <!-- ================= HEADER ================= -->
    <header class="adc-card adc-head">
      <span class="adc-mark brand-solid" aria-hidden="true"><i class="bx" :class="data.icon"></i></span>
      <div class="adc-head-main">
        <div class="adc-head-title">
          <h1>{{ t('platform.title', { name }) }}</h1>
          <span v-if="!data.connected" class="adc-pill mut"><span class="d"></span>{{ t('not_connected') }}</span>
          <span v-else-if="!data.healthy" class="adc-pill bad"><span class="d"></span>{{ t('reconnect_needed') }}</span>
          <span v-else class="adc-pill ok"><span class="d"></span>{{ t('connected') }}</span>
          <span class="adc-tagline">{{ t('platform.tagline', { name }) }}</span>
        </div>
        <div v-if="data.connected" class="adc-head-meta">
          <div v-if="data.accounts.length" ref="acctMenu" class="adc-dd">
            <button type="button" class="adc-chip btn" :aria-expanded="acctOpen ? 'true' : 'false'" @click="acctOpen = !acctOpen">
              <span class="lbl">{{ tc('platform.accounts_n', data.accounts.length, { count: data.accounts.length }) }}</span><bdi dir="auto" class="nm">{{ data.accounts[0].name }}</bdi>
              <span v-if="data.currency" class="mut">· {{ data.currency }}</span><i class="bx bx-chevron-down"></i>
            </button>
            <div v-if="acctOpen" class="adc-menu wide" :aria-label="t('ad_accounts')">
              <div v-for="a in data.accounts" :key="a.id" class="adc-menu-acct">
                <span class="adc-av">{{ initials(a.name) }}</span>
                <span class="txt"><bdi dir="auto">{{ a.name }}</bdi><small dir="ltr">{{ [a.currency, a.external_id].filter(Boolean).join(' · ') }}</small></span>
                <span class="adc-pill" :class="a.healthy ? 'ok' : 'warn'"><span class="d"></span>{{ a.healthy ? t('active') : t('reconnect') }}</span>
              </div>
              <a :href="data.urls.connect" class="adc-menu-foot"><i class="bx bx-refresh"></i>{{ t('platform.reconnect_or_add') }}</a>
            </div>
          </div>
          <span class="adc-meta-txt"><i class="bx bx-time-five"></i> {{ data.last_synced_at ? t('synced_at', { when: dateTime(data.last_synced_at) }) : t('never_synced') }}</span>
        </div>
        <p v-else class="adc-meta-txt">{{ t('platform.connect_hint', { name }) }}</p>
      </div>
      <div class="adc-head-actions">
        <button v-if="data.connected && data.can_sync" type="button" class="adc-btn ghost" :disabled="syncing" @click="syncNow"><i class="bx bx-sync" :class="{ 'bx-spin': syncing }"></i>{{ syncing ? t('syncing') : t('sync_now') }}</button>
        <a v-if="data.connected" :href="data.urls.create" class="adc-btn primary"><i class="bx bx-plus"></i>{{ t('create_campaign') }}</a>
        <a v-else :href="data.urls.connect" class="adc-btn primary"><i class="bx bx-plug"></i>{{ t('platform.connect', { name }) }}</a>
        <div ref="moreMenu" class="adc-dd">
          <button type="button" class="adc-btn ghost icon" :aria-label="t('more')" :aria-expanded="moreOpen ? 'true' : 'false'" @click="moreOpen = !moreOpen"><i class="bx bx-dots-horizontal-rounded"></i></button>
          <div v-if="moreOpen" class="adc-menu end" role="menu">
            <a v-if="data.connected" :href="data.urls.connect" role="menuitem"><i class="bx bx-refresh"></i>{{ t('reconnect') }}</a>
            <a :href="data.urls.connections" role="menuitem"><i class="bx bx-link"></i>{{ t('manage_connections') }}</a>
            <a :href="data.urls.dashboard" role="menuitem"><i class="bx bx-grid-alt"></i>{{ t('all_platforms') }}</a>
          </div>
        </div>
      </div>
    </header>

    <div v-if="toastMsg" class="adc-toast" :class="toastOk ? 'ok' : 'bad'" role="status">
      <i class="bx" :class="toastOk ? 'bx-check-circle' : 'bx-error-circle'"></i><span>{{ toastMsg }}</span>
      <button type="button" :aria-label="t('dismiss')" @click="toastMsg = ''"><i class="bx bx-x"></i></button>
    </div>

    <!-- ================= NOT CONNECTED ================= -->
    <section v-if="!data.connected" class="adc-card adc-empty">
      <span class="adc-empty-ic"><i class="bx" :class="data.icon"></i></span>
      <h2>{{ t('platform.empty_title', { name }) }}</h2>
      <p>{{ t('platform.empty_text', { name }) }}</p>
      <div class="adc-empty-a">
        <a :href="data.urls.connect" class="adc-btn primary"><i class="bx bx-plug"></i>{{ t('platform.connect', { name }) }}</a>
        <a :href="data.urls.dashboard" class="adc-btn ghost"><i class="bx bx-grid-alt"></i>{{ t('all_platforms') }}</a>
      </div>
      <ol class="adc-howto">
        <li v-for="(k, i) in ['sign_in', 'choose', 'launch']" :key="k"><span class="n">{{ i + 1 }}</span><div><strong>{{ t('platform.howto.' + k, { name }) }}</strong><small>{{ t('platform.howto.' + k + '_text', { name }) }}</small></div></li>
      </ol>
      <ul class="adc-trust">
        <li><i class="bx bx-lock-alt"></i>{{ t('platform.trust.secure', { name }) }}</li>
        <li><i class="bx bx-shield-quarter"></i>{{ t('platform.trust.password') }}</li>
        <li><i class="bx bx-reset"></i>{{ t('platform.trust.disconnect') }}</li>
      </ul>
    </section>

    <template v-else>
      <!-- ================= TABS + RANGE ================= -->
      <div class="adc-bar">
        <div class="adc-tabs" role="tablist" :aria-label="t('objects')">
          <button v-for="tb in ['overview', 'campaigns']" :key="tb" type="button" role="tab" :aria-selected="tab === tb ? 'true' : 'false'" :class="{ on: tab === tb }" @click="tab = tb; page = 1">
            {{ tb === 'overview' ? t('meta.overview') : t('tabs.campaigns') }}<span v-if="tb === 'campaigns'" class="n">{{ int(data.campaigns.length) }}</span>
          </button>
        </div>
        <div class="adc-bar-end">
          <label v-if="data.urls.performance" class="adc-select">
            <i class="bx bx-calendar" aria-hidden="true"></i><span class="adc-sr">{{ t('range_label') }}</span>
            <select v-model.number="days" @change="loadPerformance">
              <option v-for="d in [7, 30, 90]" :key="d" :value="d">{{ data.platform === 'google' ? t('range_option', { days: d }) : t('platform.range_plain', { days: d }) }}</option>
            </select>
          </label>
          <button v-if="data.can_export" type="button" class="adc-btn ghost" :disabled="exporting" @click="exportCsv"><i class="bx" :class="exporting ? 'bx-loader-alt bx-spin' : 'bx-download'"></i>{{ exporting ? t('reports.exporting') : t('reports.export') }}</button>
          <a v-else :href="data.urls.upgrade" class="adc-btn ghost" :title="t('reports.export_locked')"><i class="bx bx-lock-alt"></i>{{ t('reports.export') }}</a>
          <button type="button" class="adc-btn primary" @click="openReportForm"><i class="bx bx-envelope"></i>{{ t('reports.schedule') }}</button>
        </div>
      </div>

      <template v-if="tab === 'overview'">
        <div v-if="!data.urls.performance" class="adc-note" role="note">
          <i class="bx bx-bar-chart-alt-2"></i>
          <div><strong>{{ t('platform.no_reporting_title', { name }) }}</strong><p>{{ t('platform.no_reporting_text') }}</p></div>
        </div>
        <div v-else-if="perfError" class="adc-note bad" role="alert">
          <i class="bx bx-error-circle"></i>
          <div><strong>{{ t('platform.perf_failed') }}</strong><p>{{ perfError }}</p></div>
          <button type="button" class="adc-btn ghost sm" @click="loadPerformance">{{ t('platform.retry') }}</button>
        </div>

        <!-- ================= KPI CARDS ================= -->
        <div class="adc-kpis">
          <div v-for="k in kpis" :key="k.key" class="adc-card adc-kpi">
            <div class="top">
              <span class="ic" :style="{ background: k.tint[1], color: k.tint[0] }" aria-hidden="true"><i class="bx" :class="k.icon"></i></span>
              <span class="lbl">{{ k.label }}</span>
              <span v-if="k.delta" class="adc-delta" :class="k.delta.tone">{{ k.delta.text }}</span>
            </div>
            <div v-if="k.loading" class="adc-skel lg"></div>
            <div v-else class="val"><span v-if="k.prefix" class="pre">{{ k.prefix }}</span>{{ k.value }}<span v-if="k.suffix" class="suf">{{ k.suffix }}</span></div>
            <div v-if="k.split" class="adc-split">
              <div v-for="sp in k.split" :key="sp.label"><strong>{{ sp.value }}</strong><small>{{ sp.label }}</small></div>
            </div>
            <div v-else-if="k.bar !== undefined" class="bar-row">
              <div class="lbl-row"><small :title="k.barHint">{{ k.barLabel }}</small><b>{{ k.bar === null ? '—' : Math.round(k.bar) + '%' }}</b></div>
              <div class="adc-track"><span :style="{ width: Math.min(100, k.bar || 0) + '%' }"></span></div>
            </div>
            <div v-else-if="!k.loading" class="foot">
              <small>{{ k.sub }}</small>
              <ad-sparkline v-if="k.spark && k.spark.length > 1" :values="k.spark" :color="k.sparkColor" :w="92" :h="34" />
            </div>
          </div>
        </div>

        <div v-if="perfOk" class="adc-strip">
          <div v-for="m in strip" :key="m.key" class="adc-card adc-mini">
            <span class="lbl"><i class="bx" :class="m.icon" aria-hidden="true"></i>{{ m.label }}</span>
            <strong>{{ m.value }}</strong>
            <small><span v-if="m.delta" class="adc-delta" :class="m.delta.tone">{{ m.delta.text }}</span> {{ m.sub }}</small>
          </div>
        </div>

        <!-- ================= TREND / RANKING + BUDGET ================= -->
        <div class="adc-grid trend">
          <section v-if="trend" class="adc-card adc-pad">
            <div class="adc-h">
              <div><h2>{{ t('meta.trend_title') }}</h2><p>{{ t('platform.trend_sub', { days }) }}</p></div>
              <div class="adc-h-side">
                <div class="adc-legend"><span><i class="sq"></i>{{ t('meta.amount_spent') }}</span><span><i class="ln"></i>{{ trendLineLabel }}</span></div>
                <div class="adc-seg sm" role="tablist" :aria-label="t('meta.grouping')">
                  <button v-for="g in ['daily', 'weekly']" :key="g" type="button" role="tab" :aria-selected="grouping === g ? 'true' : 'false'" :class="{ on: grouping === g }" @click="grouping = g">{{ t('meta.groupings.' + g) }}</button>
                </div>
              </div>
            </div>
            <spend-results-chart :spend="trend.spend" :results="trend.line" :labels="trend.labels" :height="250" :color="brandInk"
                                 :format-spend="moneyAxis" :format-count="int" :spend-label="t('meta.amount_spent')" :result-label="trendLineLabel" :aria-label="t('meta.trend_title')" />
          </section>
          <bar-list-card v-else-if="perfOk || perfLoading" :title="t('platform.top_title')" :sub="perfScopeNote" :groups="topGroups" :empty="perfLoading ? t('loading') : t('platform.top_none')" />
          <bar-list-card v-else :title="t('platform.mix_title')" :sub="t('platform.mix_sub')" :groups="mixGroups" :empty="t('platform.mix_none')" />

          <section class="adc-card adc-pad">
            <div class="adc-h"><div><h2>{{ t('platform.budget_title') }}</h2><p>{{ t('platform.budget_sub') }}</p></div></div>
            <div v-if="donut.length" class="adc-donut">
              <svg viewBox="0 0 120 120" width="132" height="132" role="img" :aria-label="t('platform.budget_title')">
                <circle cx="60" cy="60" r="46" fill="none" stroke="#EEF1F6" stroke-width="16" />
                <circle v-for="seg in donut" :key="seg.key" cx="60" cy="60" r="46" fill="none" :stroke="seg.color" stroke-width="16"
                        :stroke-dasharray="seg.len + ' ' + (CIRC - seg.len)" :stroke-dashoffset="-seg.offset" transform="rotate(-90 60 60)" />
                <text x="60" y="55" text-anchor="middle" class="t1">{{ t('platform.per_day') }}</text>
                <text x="60" y="72" text-anchor="middle" class="t2">{{ moneyCompact(s.daily_budget_total) }}</text>
              </svg>
              <ul class="adc-donut-legend">
                <li v-for="seg in donut" :key="seg.key"><i :style="{ background: seg.color }"></i><bdi dir="auto">{{ seg.label }}</bdi><b>{{ seg.share }}%</b></li>
              </ul>
            </div>
            <p v-else class="adc-muted-line"><i class="bx bx-info-circle"></i>{{ t('platform.no_daily') }}</p>
            <div class="adc-mini-stats">
              <div><small>{{ t('meta.daily_budgets') }}</small><b>{{ s.daily_budget_total ? money(s.daily_budget_total) : '—' }}</b></div>
              <div><small>{{ t('platform.active_daily') }}</small><b>{{ activeDaily ? money(activeDaily) : '—' }}</b></div>
              <div><small>{{ t('meta.lifetime_budgets') }}</small><b :title="s.lifetime_budget_total ? money(s.lifetime_budget_total) : null">{{ s.lifetime_budget_total ? moneyCompact(s.lifetime_budget_total) : t('meta.none_set') }}</b></div>
            </div>
          </section>
        </div>
      </template>

      <!-- ================= CAMPAIGNS ================= -->
      <section class="adc-card adc-table-card">
        <div class="adc-table-h">
          <div class="ttl">
            <h2>{{ t('tabs.campaigns') }}</h2><span class="adc-count">{{ data.campaigns.length }}</span>
            <div v-if="data.campaigns.length" class="adc-chips" role="tablist" :aria-label="t('status_filter')">
              <button v-for="f in statusChips" :key="f.key" type="button" role="tab" :aria-selected="statusChip === f.key ? 'true' : 'false'" :class="{ on: statusChip === f.key }" @click="statusChip = f.key; page = 1">{{ t('platform.chips.' + f.key) }} {{ f.count }}</button>
            </div>
          </div>
          <div v-if="data.campaigns.length" class="adc-tools">
            <label class="adc-search"><i class="bx bx-search" aria-hidden="true"></i><input v-model.trim="query" type="search" :placeholder="t('search.campaigns')" :aria-label="t('search.campaigns')" @input="page = 1"></label>
            <label class="adc-select sm"><i class="bx bx-sort-alt-2" aria-hidden="true"></i><span class="adc-sr">{{ t('sort') }}</span>
              <select v-model="sort" @change="page = 1"><option v-for="o in sortOptions" :key="o" :value="o">{{ t('meta.sorts.' + o) }}</option></select>
            </label>
          </div>
        </div>

        <div v-if="!data.campaigns.length" class="adc-empty sm">
          <span class="adc-empty-ic"><i class="bx bx-rocket"></i></span><h3>{{ t('empty.campaigns') }}</h3>
          <p>{{ data.can_sync ? t('platform.empty_sync', { name }) : t('platform.empty_create', { name }) }}</p>
          <div class="adc-empty-a">
            <a :href="data.urls.create" class="adc-btn primary"><i class="bx bx-plus"></i>{{ t('create_campaign') }}</a>
            <button v-if="data.can_sync" type="button" class="adc-btn ghost" :disabled="syncing" @click="syncNow"><i class="bx bx-sync"></i>{{ t('sync_now') }}</button>
          </div>
        </div>
        <div v-else-if="!filtered.length" class="adc-empty sm"><p>{{ t('no_matches') }}</p><button type="button" class="adc-btn ghost" @click="query = ''; statusChip = 'all'; page = 1">{{ t('clear_filters') }}</button></div>
        <div v-else class="adc-table-wrap">
          <table class="adc-table" :class="{ narrow: !data.urls.performance }">
            <thead><tr>
              <th>{{ t('cols.campaign') }}</th><th>{{ t('cols.delivery') }}</th>
              <th class="num">{{ t('cols.budget') }}</th>
              <template v-if="data.urls.performance">
                <th class="num">{{ t('cols.spent') }}</th><th class="num">{{ t('meta.cols.impressions') }}</th><th class="num">{{ t('platform.cols.clicks') }}</th>
                <th class="num">{{ t('meta.cols.ctr') }}</th><th class="num">{{ t('meta.cols.cpc') }}</th>
                <th v-if="hasConversions" class="num">{{ t('platform.cols.conversions') }}</th>
              </template>
              <th>{{ t('cols.schedule') }}</th>
              <th class="num">{{ t('cols.actions') }}</th>
            </tr></thead>
            <tbody>
              <tr v-for="c in pageRows" :key="c.id">
                <td>
                  <div class="adc-c-cell">
                    <span class="adc-c-ic" aria-hidden="true"><i class="bx" :class="data.icon"></i></span>
                    <div class="adc-c-txt">
                      <bdi dir="auto" class="adc-c-nm" :title="c.name">{{ c.name || t('untitled') }}</bdi>
                      <span class="adc-c-meta"><span v-if="c.channel_type" class="adc-c-tag">{{ channelLabel(c.channel_type) }}</span><span v-if="c.objective">{{ objectiveLabel(c.objective) }}</span><span>{{ t('id_n', { id: c.id }) }}</span></span>
                    </div>
                  </div>
                </td>
                <td>
                  <div class="adc-c-delivery">
                    <button v-if="data.urls.status && norm(c.status) !== 'archived'" type="button" class="adc-switch" role="switch" :aria-checked="norm(c.status) === 'active' ? 'true' : 'false'"
                            :aria-label="norm(c.status) === 'active' ? t('pause_name', { name: c.name }) : t('activate_name', { name: c.name })" :disabled="busyId === c.id" @click="askToggle(c)"><span></span></button>
                    <span class="adc-pill" :class="statusTone(c.status)"><span class="d"></span>{{ statusLabel(c.status) }}</span>
                  </div>
                </td>
                <td class="num">
                  <b class="adc-c-money">{{ c.daily_budget ? money(c.daily_budget) : (c.lifetime_budget ? money(c.lifetime_budget) : '—') }}</b>
                  <small v-if="c.daily_budget || c.lifetime_budget" class="adc-c-sub">{{ c.daily_budget ? t('platform.per_day') : t('lifetime') }}</small>
                </td>
                <template v-if="data.urls.performance">
                  <td class="num"><b v-if="m(c)" class="adc-c-money">{{ money(m(c).spend) }}</b><span v-else class="adc-c-dash" :title="metricHint">—</span></td>
                  <td class="num">{{ m(c) ? compact(m(c).impressions) : '—' }}</td>
                  <td class="num">{{ m(c) ? int(m(c).clicks) : '—' }}</td>
                  <td class="num">{{ m(c) && m(c).impressions ? pct(m(c).clicks / m(c).impressions) : '—' }}</td>
                  <td class="num">{{ m(c) && m(c).clicks ? money(m(c).spend / m(c).clicks) : '—' }}</td>
                  <td v-if="hasConversions" class="num">{{ m(c) && m(c).conversions !== null ? dec(m(c).conversions, m(c).conversions % 1 ? 1 : 0) : '—' }}</td>
                </template>
                <td><span class="adc-c-sub">{{ scheduleLabel(c) }}</span></td>
                <td class="num">
                  <div class="adc-c-actions">
                    <a v-if="data.urls.edit" :href="data.urls.edit.replace('CAMPAIGN_ID', c.id)" class="adc-c-act" :title="t('edit')" :aria-label="t('edit') + ' · ' + c.name"><i class="bx bx-pencil"></i></a>
                    <button type="button" class="adc-c-act danger" :title="t('delete')" :aria-label="t('delete') + ' · ' + c.name" @click="askDelete(c)"><i class="bx bx-trash"></i></button>
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

      <!-- ================= ACCOUNTS + MIX + REPORTING ================= -->
      <div v-if="tab === 'overview'" class="adc-grid" :class="data.urls.performance ? 'two' : 'three'">
        <section class="adc-card adc-pad">
          <div class="adc-h"><div><h2>{{ t('ad_accounts') }} <span class="adc-count">{{ data.accounts.length }}</span></h2><p>{{ t('platform.accounts_sub', { name }) }}</p></div><a :href="data.urls.connect" class="adc-link"><i class="bx bx-plus"></i>{{ t('platform.add') }}</a></div>
          <ul class="adc-accts">
            <li v-for="a in data.accounts" :key="a.id">
              <account-avatar-badge :avatar-url="a.avatar_url" :icon="'bx ' + data.icon" :color="data.color" :size="34" />
              <span class="txt"><bdi dir="auto">{{ a.name }}</bdi><small dir="ltr">{{ [a.currency, a.account_status, a.external_id].filter(Boolean).join(' · ') }}</small></span>
              <span class="adc-pill" :class="a.healthy ? 'ok' : 'warn'"><span class="d"></span>{{ a.healthy ? t('active') : t('reconnect') }}</span>
            </li>
          </ul>
        </section>

        <bar-list-card v-if="data.urls.performance" :title="t('platform.mix_title')" :sub="t('platform.mix_sub')" :groups="mixGroups" :empty="t('platform.mix_none')" />

        <ad-reports ref="reportsCard" :i18n="i18n" :reports="reports" :urls="data.urls" :recipient-email="data.recipient_email || ''" :csrf="csrf" :ask="ask"
                    :title="t('meta.reports_title')" :sub="t('platform.reports_sub', { name })" :empty="t('platform.reports_none', { email: data.recipient_email || '—' })"
                    :default-name="t('platform.default_report', { name })" :format-note="data.urls.performance ? t('platform.format_note') : t('platform.format_note_campaigns')" @toast="toast" />

        <section class="adc-card adc-pad">
          <div class="adc-h"><div><h2>{{ t('meta.sync_title') }}</h2><p>{{ t('meta.sync_sub') }}</p></div><span class="adc-badge" :class="stepsHealthy ? 'ok' : 'warn'"><span class="d"></span>{{ stepsHealthy ? t('meta.healthy') : t('meta.needs_attention') }}</span></div>
          <ol class="adc-steps">
            <li v-for="st in steps" :key="st.key" :class="{ done: st.done, na: st.na }"><span class="ic"><i v-if="st.done" class="bx bx-check"></i><i v-else-if="st.na" class="bx bx-minus"></i></span><div><strong>{{ t('platform.steps.' + st.key) }}</strong><small>{{ st.text }}</small></div></li>
          </ol>
          <button v-if="data.urls.performance" type="button" class="adc-btn ghost block" :disabled="perfLoading" @click="loadPerformance"><i class="bx bx-refresh" :class="{ 'bx-spin': perfLoading }"></i>{{ t('platform.refresh') }}</button>
          <button v-else-if="data.can_sync" type="button" class="adc-btn ghost block" :disabled="syncing" @click="syncNow"><i class="bx bx-sync" :class="{ 'bx-spin': syncing }"></i>{{ t('sync_now') }}</button>
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
import BarListCard from './BarListCard.vue';
import AdSparkline from '../dashboard/AdSparkline.vue';
import AccountAvatarBadge from '../../posts/AccountAvatarBadge.vue';
import AdReports from './AdReports.vue';
import { downloadCsv } from './adExport';

/*
 * Campaigns page for Google, YouTube, TikTok, Snapchat, LinkedIn, X and
 * ChatGPT Ads, in the same design as the Meta pages (ads-campaigns.css).
 * Budgets and structure are the stored campaigns; spend and results load
 * from urls.performance only where the platform reports them
 * (AdsPerformanceService) - nothing is estimated.
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

const PAGE = 6;
const CIRC = 2 * Math.PI * 46;
const s = computed(() => props.data.summary);
const name = String(props.data.label || props.data.platform).replace(/ Ads$/, '');

// Platform colour as theme variables. Light brand colours (Snapchat yellow) keep
// their tint for backgrounds but use dark ink for text, icons and lines.
const hex = /^#[0-9a-f]{6}$/i.test(props.data.color || '') ? props.data.color : '#004CFF';
const [r0, g0, b0] = [1, 3, 5].map((i) => parseInt(hex.slice(i, i + 2), 16));
const light = (0.299 * r0 + 0.587 * g0 + 0.114 * b0) / 255 > 0.7;
const brandInk = light ? '#3F3A00' : hex;
const brandVars = { '--pf': brandInk, '--pf-bg': hex, '--pf-on': light ? '#111827' : '#fff', '--pf-soft': hex + (light ? '33' : '14'), '--pf-ink': brandInk, '--pf-glow': hex + '12', '--pf-hover': hex + (light ? '14' : '08') };
const PALETTE = [hex, '#4F46E5', '#7C3AED', '#F97316', '#0EA5E9', '#14B8A6', '#A3A9BC'];

/* ---------------- state ---------------- */
const tab = ref('overview');
const days = ref(30);
const grouping = ref('daily');
const query = ref(''); const sort = ref('newest'); const statusChip = ref('all'); const page = ref(1);
const acctOpen = ref(false); const moreOpen = ref(false); const acctMenu = ref(null); const moreMenu = ref(null); const cancelBtn = ref(null);
const syncing = ref(false); const busyId = ref(null);
const perf = ref(null); const perfLoading = ref(false); const perfError = ref('');
const toastMsg = ref(''); const toastOk = ref(true); const confirm = ref(null);
const campaigns = ref(props.data.campaigns.map((c) => ({ ...c })));
const exporting = ref(false); const reportsCard = ref(null);
const reports = ref((props.data.reports || []).map((r) => ({ ...r })));

/* ---------------- formatting ---------------- */
const cur = computed(() => (perf.value && perf.value.currency) || props.data.currency || '');
const nf = (v, o) => Number(v || 0).toLocaleString(i18n.intl, o);
function int(v) { return nf(v, { maximumFractionDigits: 0 }); }
function dec(v, d = 2) { return nf(v, { minimumFractionDigits: d, maximumFractionDigits: d }); }
function compact(v) { return nf(v, { notation: 'compact', maximumFractionDigits: 1 }); }
function pct(v, d = 2) { return dec(Number(v || 0) * 100, d) + '%'; }
function money(v) { return (cur.value ? cur.value + ' ' : '') + nf(v, { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }
function moneyCompact(v) { return (cur.value ? cur.value + ' ' : '') + (Math.abs(v) >= 10000 ? compact(v) : nf(v, { maximumFractionDigits: 0 })); }
function moneyAxis(v) { return nf(v, { maximumFractionDigits: 0 }); }
function dateTime(iso) { return new Date(iso).toLocaleString(i18n.intl, { month: 'short', day: 'numeric', year: 'numeric', hour: '2-digit', minute: '2-digit' }); }
function shortDate(iso) { return new Date(iso).toLocaleDateString(i18n.intl, { month: 'short', day: 'numeric', year: 'numeric' }); }
function initials(n) { return String(n || '?').trim().split(/\s+/).slice(0, 2).map((w) => w.charAt(0)).join('').toUpperCase() || '?'; }
function prettify(v) { const p = String(v || '').replace(/^OUTCOME_/, '').replace(/_/g, ' ').toLowerCase(); return p.charAt(0).toUpperCase() + p.slice(1); }
function objectiveLabel(v) { return known('objectives.' + String(v || '').toUpperCase(), prettify(v)); }
function channelLabel(v) { return known('platform.channels.' + String(v || '').toUpperCase(), prettify(v)); }
// Positive change is good, except for costs (inverse).
function delta(c, p, inverse = false) {
  if (c === null || c === undefined || !p) return null;
  const ch = (c - p) / Math.abs(p);
  const up = ch > 0;
  return { tone: Math.abs(ch) < 0.0005 ? 'flat' : (up !== inverse ? 'up' : 'down'), text: (up ? '▲ ' : '▼ ') + dec(Math.abs(ch) * 100, 1) + '%' };
}
function scheduleLabel(c) {
  if (c.start_time && new Date(c.start_time).getTime() > Date.now()) return t('starts', { date: shortDate(c.start_time) });
  if (c.end_time) return (c.start_time ? shortDate(c.start_time) + ' – ' : '') + shortDate(c.end_time);
  return t('schedules.ongoing');
}

/* ---------------- status ---------------- */
// Local rows store status as a boolean (true = running) or a platform string.
function norm(st) {
  if (st === true || st === 1) return 'active';
  if (st === false || st === 0 || st === null || st === undefined || st === '') return 'paused';
  const v = String(st).toLowerCase();
  if (['active', 'enable', 'enabled', 'running', '1', 'true'].includes(v)) return 'active';
  if (['paused', 'disable', 'disabled', '0', 'false', 'campaign_status_disable'].includes(v)) return 'paused';
  if (['pending', 'review', 'in_review', 'draft', 'scheduled', 'in_process', 'pending_review'].includes(v)) return 'pending';
  if (['archived', 'deleted', 'removed'].includes(v)) return 'archived';
  if (['error', 'rejected', 'failed', 'with_issues', 'disapproved'].includes(v)) return 'issue';
  return 'other';
}
function statusLabel(st) { return t('statuses.' + norm(st)); }
function statusTone(st) { return { active: 'ok', paused: 'warn', archived: 'mut', issue: 'bad', pending: 'info', other: 'mut' }[norm(st)]; }
const activeDaily = computed(() => campaigns.value.filter((c) => norm(c.status) === 'active').reduce((n, c) => n + Number(c.daily_budget || 0), 0));

/* ---------------- performance ---------------- */
const perfOk = computed(() => !!perf.value && perf.value.status === 'ok');
const totals = computed(() => (perfOk.value ? perf.value.totals.current : null));
const prev = computed(() => (perfOk.value ? perf.value.totals.previous : null));
const hasConversions = computed(() => perfOk.value && !!perf.value.has_conversions);
const perfScopeNote = computed(() => (perfOk.value && perf.value.scope === 'campaigns' ? t('platform.scope_campaigns', { name }) : t('platform.top_sub')));
function m(c) { return perfOk.value ? perf.value.campaigns[c.id] || perf.value.campaigns[String(c.id)] || null : null; }
const metricHint = computed(() => (perfLoading.value ? t('loading') : perfError.value || (perfOk.value ? t('platform.no_delivery') : t('kpi.awaiting'))));
async function loadPerformance() {
  if (!props.data.urls.performance) return;
  perfLoading.value = true; perfError.value = '';
  try {
    const { data } = await window.axios.get(props.data.urls.performance, { params: { days: days.value } });
    perf.value = data;
    if (data.status === 'error') perfError.value = data.error || t('platform.perf_failed');
  } catch (e) {
    perf.value = null; perfError.value = errorText(e, t('platform.perf_failed'));
  } finally { perfLoading.value = false; }
}

/* ---------------- KPIs ---------------- */
const kpis = computed(() => {
  if (!props.data.urls.performance) {
    const total = s.value.campaigns_total;
    return [
      { key: 'campaigns', icon: 'bxs-megaphone', tint: [brandInk, hex + (light ? '33' : '14')], label: t('meta.mini.campaigns'), value: int(total), bar: total ? (s.value.campaigns_active / total) * 100 : null, barLabel: t('meta.mini.active_n', { n: s.value.campaigns_active }) },
      { key: 'daily', icon: 'bx-wallet', tint: ['#4F46E5', '#EEF0FF'], label: t('meta.daily_budgets'), prefix: cur.value, value: nf(s.value.daily_budget_total, { maximumFractionDigits: 0 }), sub: t('platform.active_daily_n', { amount: money(activeDaily.value) }) },
      { key: 'lifetime', icon: 'bx-coin-stack', tint: ['#16A34A', '#E7F7EE'], label: t('meta.lifetime_budgets'), prefix: cur.value, value: nf(s.value.lifetime_budget_total, { maximumFractionDigits: 0 }), sub: t('platform.lifetime_sub') },
      { key: 'structure', icon: 'bx-sitemap', tint: ['#7C3AED', '#F2EDFF'], label: t('platform.structure_title'), value: int(s.value.ads), suffix: ' ' + t('platform.ads_word'),
        split: [{ label: t('platform.ad_groups'), value: int(s.value.ad_groups) }, { label: t('tabs.ads'), value: int(s.value.ads) }, { label: t('tabs.creatives'), value: int(s.value.creatives) }] },
    ];
  }
  const c = totals.value; const p = prev.value; const loading = perfLoading.value && !c; const dash = '—';
  const tr = perfOk.value ? perf.value.trend : null;
  const util = c && activeDaily.value ? (c.spend / (activeDaily.value * days.value)) * 100 : null;
  const cards = [
    { key: 'spend', icon: 'bx-wallet', tint: ['#4F46E5', '#EEF0FF'], label: t('meta.kpi.spend'), loading, prefix: c ? cur.value : '', value: c ? nf(c.spend, { maximumFractionDigits: 0 }) : dash, delta: c && p ? delta(c.spend, p.spend) : null,
      bar: c ? util : undefined, barLabel: activeDaily.value ? t('meta.kpi.budget_util') : t('meta.kpi.no_daily'), barHint: t('platform.util_hint'), sub: perfError.value || t('kpi.awaiting') },
    { key: 'clicks', icon: 'bx-pointer', tint: [brandInk, hex + (light ? '33' : '14')], label: t('platform.kpi.clicks'), loading, value: c ? int(c.clicks) : dash,
      suffix: c && c.clicks ? ' · ' + t('platform.kpi.cpc', { amount: money(c.spend / c.clicks) }) : '', delta: c && p ? delta(c.clicks, p.clicks) : null,
      sub: c ? t('platform.kpi.clicks_sub') : t('kpi.awaiting'), spark: tr ? tr.clicks : null, sparkColor: brandInk },
    { key: 'impressions', icon: 'bx-show', tint: ['#0EA5E9', '#E6F6FE'], label: t('platform.kpi.impressions'), loading, value: c ? compact(c.impressions) : dash,
      suffix: c && c.impressions ? ' · ' + t('platform.kpi.ctr', { rate: pct(c.clicks / c.impressions) }) : '', delta: c && p ? delta(c.impressions, p.impressions) : null,
      sub: c ? t('platform.kpi.impressions_sub') : t('kpi.awaiting') },
  ];
  cards.push(hasConversions.value || (loading && props.data.platform !== 'openai')
    ? { key: 'conversions', icon: 'bx-cart', tint: ['#16A34A', '#E7F7EE'], label: t('platform.kpi.conversions'), loading, value: c ? dec(c.conversions, c.conversions % 1 ? 1 : 0) : dash,
      suffix: c && c.conversions ? ' · ' + t('platform.kpi.cost_per', { amount: money(c.spend / c.conversions) }) : '', delta: c && p ? delta(c.conversions, p.conversions) : null,
      sub: c ? (c.value ? t('platform.kpi.value', { amount: money(c.value), roas: dec(c.value / (c.spend || 1), 2) }) : t('platform.kpi.no_value')) : t('kpi.awaiting'), spark: tr ? tr.conversions : null, sparkColor: '#16A34A' }
    : { key: 'campaigns', icon: 'bxs-megaphone', tint: ['#7C3AED', '#F2EDFF'], label: t('meta.mini.campaigns'), value: int(s.value.campaigns_total), bar: s.value.campaigns_total ? (s.value.campaigns_active / s.value.campaigns_total) * 100 : null, barLabel: t('meta.mini.active_n', { n: s.value.campaigns_active }) });
  return cards;
});
const strip = computed(() => {
  const c = totals.value; const p = prev.value; const vs = p ? t('meta.vs_prev') : '';
  const cpm = c && c.impressions ? (c.spend / c.impressions) * 1000 : null;
  const pcpm = p && p.impressions ? (p.spend / p.impressions) * 1000 : null;
  const rows = [
    { key: 'campaigns', icon: 'bx-bullseye', label: t('meta.mini.campaigns'), value: int(s.value.campaigns_total), sub: t('meta.mini.active_n', { n: s.value.campaigns_active }) },
    { key: 'impressions', icon: 'bx-show', label: t('meta.mini.impressions'), value: compact(c.impressions), delta: p ? delta(c.impressions, p.impressions) : null, sub: vs },
    { key: 'clicks', icon: 'bx-pointer', label: t('platform.cols.clicks'), value: compact(c.clicks), delta: p ? delta(c.clicks, p.clicks) : null, sub: vs },
    { key: 'ctr', icon: 'bx-trending-up', label: t('platform.mini.ctr'), value: c.impressions ? pct(c.clicks / c.impressions) : '—', delta: p && p.impressions && c.impressions ? delta(c.clicks / c.impressions, p.clicks / p.impressions) : null, sub: vs },
    { key: 'cpc', icon: 'bx-coin', label: t('platform.mini.cpc'), value: c.clicks ? money(c.spend / c.clicks) : '—', delta: p && p.clicks && c.clicks ? delta(c.spend / c.clicks, p.spend / p.clicks, true) : null, sub: vs },
    { key: 'cpm', icon: 'bx-receipt', label: t('meta.mini.cpm'), value: cpm === null ? '—' : money(cpm), delta: pcpm && cpm !== null ? delta(cpm, pcpm, true) : null, sub: vs },
  ];
  if (hasConversions.value) {
    rows.push({ key: 'cvr', icon: 'bx-cart', label: t('platform.mini.cvr'), value: c.clicks ? pct(c.conversions / c.clicks) : '—', sub: t('platform.mini.cvr_sub') });
    rows.push({ key: 'value', icon: 'bx-dollar-circle', label: t('platform.mini.value'), value: c.value ? moneyCompact(c.value) : '—', delta: p ? delta(c.value, p.value) : null, sub: vs });
  } else {
    rows.push({ key: 'daily', icon: 'bx-wallet', label: t('meta.daily_budgets'), value: s.value.daily_budget_total ? moneyCompact(s.value.daily_budget_total) : '—', sub: t('platform.per_day') });
    rows.push({ key: 'ads', icon: 'bx-layer', label: t('tabs.ads'), value: int(s.value.ads), sub: t('platform.ad_groups_n', { n: s.value.ad_groups }) });
  }
  return rows;
});

/* ---------------- trend (Google Ads: daily series of the customer) ---------------- */
const trendLineLabel = computed(() => (hasConversions.value ? t('platform.cols.conversions') : t('platform.cols.clicks')));
const trend = computed(() => {
  const tr = perfOk.value ? perf.value.trend : null;
  if (!tr || !tr.dates || tr.dates.length < 2) return null;
  const line = hasConversions.value ? tr.conversions : tr.clicks;
  const label = (iso) => new Date(iso + 'T00:00:00').toLocaleDateString(i18n.intl, { month: 'short', day: 'numeric' });
  if (grouping.value === 'daily') return { spend: tr.spend, line, labels: tr.dates.map(label) };
  const weeks = [];
  tr.dates.forEach((d, i) => { if (i % 7 === 0) weeks.push({ spend: 0, line: 0, start: d }); const w = weeks[weeks.length - 1]; w.spend += Number(tr.spend[i] || 0); w.line += Number(line[i] || 0); });
  return { spend: weeks.map((w) => w.spend), line: weeks.map((w) => w.line), labels: weeks.map((w) => label(w.start)) };
});

/* ---------------- ranked cards ---------------- */
const topGroups = computed(() => {
  if (!perfOk.value) return [];
  const rows = campaigns.value.map((c) => ({ c, mm: m(c) })).filter((r) => r.mm && r.mm.spend > 0).sort((a, b) => b.mm.spend - a.mm.spend).slice(0, 6);
  const max = Math.max(1e-9, ...rows.map((r) => r.mm.spend));
  return [{ key: 'top', rows: rows.map((r, i) => ({
    key: r.c.id, label: r.c.name || t('untitled'), display: money(r.mm.spend), width: Math.max(4, (r.mm.spend / max) * 100), color: PALETTE[i],
    hint: r.mm.conversions ? t('platform.conv_n', { n: dec(r.mm.conversions, r.mm.conversions % 1 ? 1 : 0) }) : t('platform.clicks_n', { n: int(r.mm.clicks) }),
  })) }];
});
function countRows(list, labelOf) {
  const counts = {};
  list.forEach((v) => { if (v) counts[v] = (counts[v] || 0) + 1; });
  const entries = Object.entries(counts).sort((a, b) => b[1] - a[1]).slice(0, 5);
  const max = Math.max(1, ...entries.map((e) => e[1]));
  return entries.map(([k, n], i) => ({ key: k, label: labelOf(k), display: int(n), width: Math.max(4, (n / max) * 100), color: PALETTE[i + 1] }));
}
const mixGroups = computed(() => [
  { key: 'status', label: t('cols.status'), rows: countRows(campaigns.value.map((c) => norm(c.status)), (k) => t('statuses.' + k)).map((r) => ({ ...r, color: { active: '#16A34A', paused: '#F59E0B', pending: '#0EA5E9', issue: '#DC2626' }[r.key] || '#A3A9BC' })) },
  { key: 'type', label: t('platform.by_type'), rows: countRows(campaigns.value.map((c) => c.channel_type || c.objective), (k) => (campaigns.value.some((c) => c.channel_type === k) ? channelLabel(k) : objectiveLabel(k))) },
]);

/* ---------------- budget allocation (stored daily budgets) ---------------- */
const donut = computed(() => {
  const rows = campaigns.value.filter((c) => Number(c.daily_budget) > 0).sort((a, b) => b.daily_budget - a.daily_budget);
  const total = rows.reduce((n, c) => n + Number(c.daily_budget), 0);
  if (!total) return [];
  const top = rows.slice(0, 5).map((c, i) => ({ key: c.id, label: c.name || t('untitled'), value: Number(c.daily_budget), color: PALETTE[i] }));
  const rest = rows.slice(5).reduce((n, c) => n + Number(c.daily_budget), 0);
  if (rest > 0) top.push({ key: 'other', label: t('meta.other_campaigns'), value: rest, color: PALETTE[6] });
  let offset = 0;
  return top.map((x) => { const len = (x.value / total) * CIRC; const seg = { ...x, share: Math.round((x.value / total) * 100), len, offset }; offset += len; return seg; });
});

/* ---------------- reporting checklist ---------------- */
const steps = computed(() => {
  const list = [
    { key: 'account', done: props.data.healthy, text: props.data.healthy ? (props.data.account_names || []).join(', ') : t('platform.reconnect_text', { name }) },
    { key: 'campaigns', done: s.value.campaigns_total > 0, text: s.value.campaigns_total ? t('platform.campaigns_text', { campaigns: s.value.campaigns_total, groups: s.value.ad_groups, ads: s.value.ads }) : t('platform.campaigns_none') },
  ];
  let text = t('platform.reporting_none', { name });
  if (props.data.urls.performance) text = perfLoading.value ? t('loading') : perfOk.value ? t('platform.reporting_ok', { when: dateTime(perf.value.fetched_at || new Date().toISOString()) }) : (perfError.value || t('kpi.awaiting'));
  // Platforms without a reporting connection aren't unhealthy - the step just doesn't apply.
  list.push({ key: 'reporting', done: perfOk.value, na: !props.data.urls.performance, text });
  return list;
});
const stepsHealthy = computed(() => steps.value.every((x) => x.done || x.na));

/* ---------------- table ---------------- */
const statusChips = computed(() => {
  const n = (k) => campaigns.value.filter((c) => norm(c.status) === k).length;
  const all = campaigns.value.length; const active = n('active'); const paused = n('paused');
  return [{ key: 'all', count: all }, { key: 'active', count: active }, { key: 'paused', count: paused }, { key: 'other', count: all - active - paused }].filter((f) => f.key === 'all' || f.count > 0);
});
const sortOptions = computed(() => (perfOk.value ? ['spend', 'newest', 'name', 'budget'] : ['newest', 'name', 'budget']));
const filtered = computed(() => {
  const q = query.value.toLowerCase();
  const rows = campaigns.value.filter((c) => {
    const st = norm(c.status);
    if (statusChip.value === 'other' ? ['active', 'paused'].includes(st) : statusChip.value !== 'all' && st !== statusChip.value) return false;
    return !q || [c.name, c.objective, c.channel_type, String(c.id)].some((v) => String(v || '').toLowerCase().includes(q));
  });
  const by = sortOptions.value.includes(sort.value) ? sort.value : 'newest';
  const budget = (c) => Number(c.daily_budget || c.lifetime_budget || 0);
  const spend = (c) => (m(c) ? m(c).spend : -1);
  return [...rows].sort((a, b) => {
    if (by === 'name') return String(a.name || '').localeCompare(String(b.name || ''), i18n.intl);
    if (by === 'budget') return budget(b) - budget(a);
    if (by === 'spend') return spend(b) - spend(a);
    return String(b.created_at || '').localeCompare(String(a.created_at || '')) || Number(b.id) - Number(a.id);
  });
});
const pages = computed(() => Math.max(1, Math.ceil(filtered.value.length / PAGE)));
const pageRows = computed(() => filtered.value.slice((page.value - 1) * PAGE, page.value * PAGE));

/* ---------------- actions ---------------- */
function toast(msg, ok) { toastMsg.value = msg; toastOk.value = ok; }
function errorText(e, fallback) { return (e.response && e.response.data && (e.response.data.message || e.response.data.error)) || fallback; }
function ask(opts) { confirm.value = opts; nextTick(() => cancelBtn.value && cancelBtn.value.focus()); }
async function runConfirm() { const c = confirm.value; confirm.value = null; if (c) await c.run(); }
function askToggle(c) {
  const activate = norm(c.status) !== 'active';
  ask({ title: activate ? t('confirm_activate_title') : t('confirm_pause_title'), text: t(activate ? 'platform.confirm_activate' : 'platform.confirm_pause', { name: c.name, platform: name }), ok: activate ? t('activate') : t('pause'),
    run: async () => {
      busyId.value = c.id;
      try {
        await window.axios.patch(props.data.urls.status.replace('CAMPAIGN_ID', c.id), { status: activate ? 'ACTIVE' : 'PAUSED' }, { headers: { 'X-CSRF-TOKEN': props.csrf, Accept: 'application/json' } });
        c.status = activate ? 'ACTIVE' : 'PAUSED';
        toast(activate ? t('activated') : t('paused'), true);
      } catch (e) { toast(errorText(e, t('platform.status_failed', { name })), false); } finally { busyId.value = null; }
    } });
}
function askDelete(c) {
  ask({ title: t('confirm_delete_title'), text: t('platform.confirm_delete', { name: c.name, platform: name }), ok: t('delete'), danger: true,
    run: async () => {
      const form = document.createElement('form'); form.method = 'POST'; form.action = props.data.urls.destroy.replace('CAMPAIGN_ID', c.id);
      form.innerHTML = `<input type="hidden" name="_method" value="DELETE"><input type="hidden" name="_token" value="${props.csrf}">`;
      document.body.appendChild(form); form.submit();
    } });
}
// The reports card lives on the overview, so the form opens there.
function openReportForm() { tab.value = 'overview'; nextTick(() => reportsCard.value && reportsCard.value.open()); }
async function exportCsv() {
  exporting.value = true;
  const error = await downloadCsv(props.data.urls.export, { days: days.value }, props.data.platform + '-ads.csv', t('reports.export_failed'));
  exporting.value = false;
  if (error) toast(error, false);
}
async function syncNow() {
  if (syncing.value) return;
  syncing.value = true;
  try {
    const { data } = await window.axios.post(props.data.urls.sync, {}, { headers: { 'X-CSRF-TOKEN': props.csrf } });
    const n = data.synced ?? 0;
    toast(n ? t('platform.synced', { n, created: data.created ?? 0, updated: data.updated ?? 0, name }) : t('platform.synced_none', { name }), true);
    setTimeout(() => window.location.reload(), 1200);
  } catch (e) { toast(errorText(e, t('sync_failed')), false); syncing.value = false; }
}

/* ---------------- menus ---------------- */
function closeMenus(e) {
  const outside = (el) => !el || !el.contains(e.target);
  if (acctOpen.value && outside(acctMenu.value)) acctOpen.value = false;
  if (moreOpen.value && outside(moreMenu.value)) moreOpen.value = false;
}
function onKey(e) { if (e.key === 'Escape') { acctOpen.value = moreOpen.value = false; confirm.value = null; if (reportsCard.value) reportsCard.value.close(); } }
onMounted(() => { document.addEventListener('click', closeMenus); document.addEventListener('keydown', onKey); if (props.data.connected) loadPerformance(); });
onBeforeUnmount(() => { document.removeEventListener('click', closeMenus); document.removeEventListener('keydown', onKey); });
</script>

<style src="./ads-campaigns.css"></style>
