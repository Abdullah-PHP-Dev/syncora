<template>
  <div class="fb xb" :dir="i18n.dir">

    <!-- ===== Header ===== -->
    <header class="fb-head">
      <div class="fb-head-main">
        <span class="fb-head-icon xb-head-icon"><svg viewBox="0 0 24 24"><path fill="currentColor" d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg></span>
        <div>
          <h4>{{ t('header.title') }}</h4>
          <p>{{ t('header.subtitle') }}</p>
        </div>
      </div>
      <div class="fb-head-actions">
        <span v-if="boot" class="fb-acct" :title="boot.account.id"><i class="bx bx-briefcase-alt-2"></i> {{ boot.account.name }} · {{ currency }}</span>
        <a :href="urls.campaignsList" class="btn btn-sm fb-btn-ghost"><i class="bx bx-list-ul"></i> {{ t('header.campaigns') }}</a>
      </div>
    </header>

    <div v-if="draftOffer" class="fb-banner">
      <i class="bx bx-history"></i>
      <span>{{ t('draft.unfinished', { date: draftOffer.savedAt }) }}</span>
      <button type="button" class="btn btn-primary btn-sm" @click="resumeDraft">{{ t('draft.continue') }}</button>
      <button type="button" class="btn btn-sm fb-btn-ghost" @click="discardDraft">{{ t('draft.fresh') }}</button>
    </div>

    <div v-if="bootError" class="fb-card fb-empty">
      <span class="fb-empty-ic"><i class="bx bx-plug"></i></span>
      <h5>{{ t('boot.error_title') }}</h5>
      <p>{{ bootError }}</p>
      <a :href="urls.connections" class="btn btn-primary btn-sm"><i class="bx bx-link"></i> {{ t('boot.go_connections') }}</a>
    </div>
    <div v-else-if="!boot" class="fb-card fb-loading"><span class="spinner-border"></span> {{ t('boot.loading') }}</div>

    <template v-else>
      <div v-if="!boot.account.active" class="fb-alert is-warn"><i class="bx bx-error"></i> {{ t('boot.inactive', { status: boot.account.approval_status || '-' }) }}</div>
      <div v-if="!usableFunding.length" class="fb-alert is-warn"><i class="bx bx-credit-card"></i> {{ t('boot.no_funding') }}</div>

      <!-- ===== Stepper ===== -->
      <nav class="fb-steps" :aria-label="t('steps.aria')">
        <button v-for="(s, i) in steps" :key="s.key" type="button" class="fb-step"
                :class="{ 'is-current': step === i, 'is-done': i < step && !stepErrors[s.key], 'has-error': stepErrors[s.key] }"
                :disabled="i > maxReached" :aria-current="step === i ? 'step' : null" @click="goTo(i)">
          <span class="fb-step-n"><i v-if="stepErrors[s.key]" class="bx bx-error"></i><i v-else-if="i < step" class="bx bx-check"></i><template v-else>{{ i + 1 }}</template></span>
          <span class="fb-step-t"><strong>{{ s.label }}</strong><small>{{ s.summary }}</small></span>
        </button>
      </nav>

      <div class="fb-layout">
        <section class="fb-main">

          <!-- ============ 1. Campaign ============ -->
          <div v-show="current === 'campaign'" class="fb-card">
            <div class="fb-card-h">
              <h5>{{ t('campaign.title') }}</h5>
              <div class="fb-seg" role="tablist">
                <button type="button" role="tab" :class="{ on: campaign.mode === 'new' }" @click="setCampaignMode('new')"><i class="bx bx-plus"></i> {{ t('common.create_new') }}</button>
                <button type="button" role="tab" :class="{ on: campaign.mode === 'existing' }" @click="setCampaignMode('existing')"><i class="bx bx-folder-open"></i> {{ t('common.use_existing') }}</button>
              </div>
            </div>

            <template v-if="campaign.mode === 'existing'">
              <label class="fb-label">{{ t('campaign.choose') }}</label>
              <div class="fb-search"><i class="bx bx-search"></i><input v-model="campaignQuery" type="search" :placeholder="t('campaign.search')" :aria-label="t('campaign.search')"></div>
              <div v-if="lists.loading.campaigns" class="fb-inline-load"><span class="spinner-border spinner-border-sm"></span> {{ t('common.loading') }}</div>
              <p v-else-if="lists.errors.campaigns" class="fb-field-err">{{ lists.errors.campaigns }}</p>
              <div v-else class="fb-pick-list">
                <button v-for="c in filteredCampaigns" :key="c.id" type="button" class="fb-pick" :class="{ on: campaign.id === c.id, 'is-off': !c.supported }" :disabled="!c.supported" @click="chooseCampaign(c)">
                  <span class="fb-pick-ic"><i class="bx" :class="c.objective ? boot.objectives[c.objective].icon : 'bx-folder'"></i></span>
                  <span class="fb-pick-main">
                    <strong>{{ c.name }}</strong>
                    <small>{{ c.supported ? (c.objective ? t('objectives.' + c.objective + '.label') : t('campaign.no_objective')) : t('campaign.unsupported') }} · {{ known('entity_status', c.status, humanize(c.status)) }}<template v-if="c.daily_budget"> · {{ money(c.daily_budget, c.currency) }}{{ t('steps.per_day') }}</template></small>
                  </span>
                  <i v-if="campaign.id === c.id" class="bx bxs-check-circle fb-pick-check"></i>
                </button>
                <div v-if="!filteredCampaigns.length" class="fb-mini-empty">
                  <i class="bx bx-folder"></i><span>{{ campaignQuery ? t('campaign.no_match') : t('campaign.none_yet') }}</span>
                  <button type="button" class="fb-link" @click="setCampaignMode('new')">{{ t('campaign.create_link') }}</button>
                </div>
              </div>
            </template>

            <label class="fb-label" :class="{ 'mt-3': campaign.mode === 'existing' }">{{ t('campaign.objective') }}</label>
            <div class="fb-goals">
              <button v-for="(o, key) in boot.objectives" :key="key" type="button" class="fb-goal" :class="{ on: objective === key }" :disabled="objectiveLocked && objective !== key" @click="setObjective(key)">
                <span class="fb-goal-ic"><i class="bx" :class="o.icon"></i></span>
                <strong>{{ t('objectives.' + key + '.label') }}</strong>
                <small>{{ t('objectives.' + key + '.summary') }}</small>
              </button>
            </div>
            <p v-if="objectiveLocked" class="fb-hint"><i class="bx bx-lock-alt"></i> {{ t('campaign.objective_locked') }}</p>
            <div v-if="objective" class="fb-guide"><i class="bx bx-bulb"></i><span><strong>{{ t('objectives.' + objective + '.label') }}:</strong> {{ t('objectives.' + objective + '.guidance') }}</span></div>

            <template v-if="campaign.mode === 'new'">
              <div class="fb-grid-2">
                <div>
                  <label class="fb-label" for="xbCampName">{{ t('campaign.name') }}</label>
                  <input id="xbCampName" v-model.trim="campaign.name" class="form-control" maxlength="255" :placeholder="objective ? t('objectives.' + objective + '.label') + ' - ' + today : t('campaign.name_placeholder')">
                </div>
                <div>
                  <label class="fb-label" for="xbFunding">{{ t('campaign.funding') }} <i class="bx bx-help-circle fb-tip" :title="t('campaign.funding_tip')"></i></label>
                  <select id="xbFunding" v-model="campaign.funding_instrument_id" class="form-control">
                    <option :value="null">{{ t('campaign.choose_funding') }}</option>
                    <option v-for="f in boot.funding_instruments" :key="f.id" :value="f.id" :disabled="!f.usable">{{ f.name }} · {{ f.currency }}{{ f.usable ? '' : ' (' + t('campaign.unusable') + ')' }}</option>
                  </select>
                </div>
              </div>
              <div class="fb-group">
                <h6><i class="bx bx-wallet"></i> {{ t('campaign.budget_title') }}</h6>
                <div class="fb-grid-2">
                  <div>
                    <label class="fb-label" for="xbDaily">{{ t('campaign.daily_budget', { currency }) }}</label>
                    <input id="xbDaily" v-model.number="campaign.daily_budget" type="number" min="1" step="0.01" class="form-control">
                  </div>
                  <div>
                    <label class="fb-label" for="xbTotal">{{ t('campaign.total_budget', { currency }) }} <span class="fb-opt">{{ t('common.optional') }}</span></label>
                    <input id="xbTotal" v-model.number="campaign.total_budget" type="number" min="1" step="0.01" class="form-control">
                  </div>
                </div>
                <p class="fb-hint"><i class="bx bx-info-circle"></i> {{ t('campaign.budget_hint') }}</p>
                <details class="xb-details">
                  <summary>{{ t('campaign.more') }}</summary>
                  <label class="fb-label mt-2" for="xbPo">{{ t('campaign.po') }} <span class="fb-opt">{{ t('common.optional') }}</span></label>
                  <input id="xbPo" v-model.trim="campaign.purchase_order_number" class="form-control" maxlength="50">
                </details>
              </div>
            </template>
            <p v-if="fieldErrors.campaign" class="fb-field-err">{{ fieldErrors.campaign }}</p>
          </div>

          <!-- ============ 2. Ad group (line item) ============ -->
          <div v-show="current === 'lineitem'" class="fb-card">
            <div class="fb-card-h"><h5>{{ t('lineitem.title') }}</h5></div>
            <p class="fb-sub">{{ t('lineitem.sub') }}</p>

            <div class="fb-grid-2">
              <div>
                <label class="fb-label" for="xbLiName">{{ t('lineitem.name') }}</label>
                <input id="xbLiName" v-model.trim="lineitem.name" class="form-control" maxlength="255" :placeholder="t('defaults.lineitem', { date: today })">
              </div>
              <div v-if="spec">
                <label class="fb-label" for="xbGoal">{{ t('lineitem.goal') }} <i class="bx bx-help-circle fb-tip" :title="t('lineitem.goal_tip')"></i></label>
                <select id="xbGoal" v-model="lineitem.goal" class="form-control">
                  <option v-for="g in spec.goals" :key="g" :value="g">{{ t('goals.' + g) }}</option>
                </select>
              </div>
            </div>

            <!-- Conversion tracking / app -->
            <div v-if="lineitem.goal === 'WEBSITE_CONVERSIONS'" class="fb-group">
              <h6><i class="bx bx-target-lock"></i> {{ t('lineitem.conversion_title') }}</h6>
              <label class="fb-label" for="xbTag">{{ t('lineitem.web_event_tag') }}</label>
              <input id="xbTag" v-model.trim="lineitem.web_event_tag" class="form-control" maxlength="40" dir="ltr" :placeholder="t('lineitem.web_event_placeholder')">
              <p class="fb-hint"><i class="bx bx-info-circle"></i> {{ t('lineitem.web_event_hint') }}</p>
            </div>
            <div v-if="spec && spec.needs_app" class="fb-group">
              <h6><i class="bx bx-mobile-alt"></i> {{ t('lineitem.app_title') }}</h6>
              <div class="fb-grid-2">
                <div><label class="fb-label" for="xbIos"><i class="bx bxl-apple"></i> {{ t('lineitem.ios_id') }}</label><input id="xbIos" v-model.trim="app.ios_id" class="form-control" dir="ltr" placeholder="333903271"></div>
                <div><label class="fb-label" for="xbAnd"><i class="bx bxl-play-store"></i> {{ t('lineitem.android_id') }}</label><input id="xbAnd" v-model.trim="app.android_id" class="form-control" dir="ltr" placeholder="com.example.app"></div>
                <div><label class="fb-label" for="xbAppCc">{{ t('lineitem.app_country') }}</label><input id="xbAppCc" v-model.trim="app.country_code" class="form-control" maxlength="2" dir="ltr" placeholder="US" @input="app.country_code = app.country_code.toUpperCase()"></div>
              </div>
              <p class="fb-hint"><i class="bx bx-info-circle"></i> {{ t('lineitem.app_hint') }}</p>
            </div>

            <!-- Placements -->
            <div class="fb-group">
              <h6><i class="bx bx-layout"></i> {{ t('lineitem.placements') }}</h6>
              <div class="fb-seg">
                <button type="button" :class="{ on: lineitem.placement_mode === 'all' }" @click="lineitem.placement_mode = 'all'">{{ t('lineitem.all_placements') }}</button>
                <button type="button" :class="{ on: lineitem.placement_mode === 'custom' }" :disabled="!boot.options.placements.single.length" @click="lineitem.placement_mode = 'custom'">{{ t('lineitem.custom_placements') }}</button>
              </div>
              <p class="fb-hint"><i class="bx bx-info-circle"></i> {{ lineitem.placement_mode === 'all' ? t('lineitem.all_hint') : t('lineitem.custom_hint') }}</p>
              <div v-if="lineitem.placement_mode === 'custom'" class="fb-place-grid">
                <label v-for="p in boot.options.placements.single" :key="p" class="fb-check"><input v-model="lineitem.placements" type="checkbox" :value="p"><i class="bx" :class="placementIcon(p)"></i> {{ t('placements.' + p) }}</label>
              </div>
            </div>

            <!-- Schedule -->
            <div class="fb-group">
              <h6><i class="bx bx-calendar"></i> {{ t('lineitem.schedule') }}</h6>
              <div class="fb-grid-2">
                <div><label class="fb-label" for="xbStart">{{ t('lineitem.start') }}</label><input id="xbStart" v-model="lineitem.start_time" type="datetime-local" class="form-control"></div>
                <div><label class="fb-label" for="xbEnd">{{ t('lineitem.end') }} <span class="fb-opt">{{ t('common.optional') }}</span></label><input id="xbEnd" v-model="lineitem.end_time" type="datetime-local" class="form-control" :min="lineitem.start_time"></div>
              </div>
              <p class="fb-hint"><i class="bx bx-time-five"></i> {{ t('lineitem.timezone', { tz: boot.account.timezone }) }}</p>
            </div>

            <!-- Budget & pacing -->
            <div class="fb-group">
              <h6><i class="bx bx-wallet"></i> {{ t('lineitem.budget_title') }} <span class="fb-opt">{{ t('common.optional') }}</span></h6>
              <div class="fb-grid-2">
                <div><label class="fb-label" for="xbLiDaily">{{ t('lineitem.daily_budget', { currency }) }}</label><input id="xbLiDaily" v-model.number="lineitem.daily_budget" type="number" min="1" step="0.01" class="form-control"></div>
                <div><label class="fb-label" for="xbLiTotal">{{ t('lineitem.total_budget', { currency }) }}</label><input id="xbLiTotal" v-model.number="lineitem.total_budget" type="number" min="1" step="0.01" class="form-control"></div>
              </div>
              <label class="fb-label mt-2">{{ t('lineitem.pacing') }}</label>
              <div class="fb-seg">
                <button type="button" :class="{ on: lineitem.standard_delivery }" @click="lineitem.standard_delivery = true">{{ t('lineitem.standard') }}</button>
                <button type="button" :class="{ on: !lineitem.standard_delivery }" @click="lineitem.standard_delivery = false">{{ t('lineitem.accelerated') }}</button>
              </div>
              <p class="fb-hint"><i class="bx bx-info-circle"></i> {{ lineitem.standard_delivery ? t('lineitem.standard_hint') : t('lineitem.accelerated_hint') }}</p>
            </div>

            <!-- Bidding -->
            <div v-if="spec" class="fb-group">
              <h6><i class="bx bx-trending-up"></i> {{ t('lineitem.bidding') }}</h6>
              <div class="fb-seg">
                <button v-for="b in spec.bids" :key="b" type="button" :class="{ on: lineitem.bid_strategy === b }" @click="lineitem.bid_strategy = b">{{ t('bids.' + b + '.label') }}</button>
              </div>
              <p class="fb-hint"><i class="bx bx-info-circle"></i> {{ t('bids.' + lineitem.bid_strategy + '.hint') }}</p>
              <div class="fb-grid-2">
                <div v-if="lineitem.bid_strategy !== 'AUTO'">
                  <label class="fb-label" for="xbBid">{{ t('lineitem.bid_amount', { currency }) }}</label>
                  <input id="xbBid" v-model.number="lineitem.bid_amount" type="number" min="0.01" step="0.01" class="form-control">
                </div>
                <div v-if="payByOptions.length">
                  <label class="fb-label">{{ t('lineitem.pay_by') }}</label>
                  <div class="fb-seg fb-seg-full">
                    <button v-for="p in payByOptions" :key="p" type="button" :class="{ on: effectivePayBy === p }" :disabled="forcedPayBy && forcedPayBy !== p" @click="lineitem.pay_by = p">{{ t('pay_by.' + p) }}</button>
                  </div>
                </div>
              </div>
              <div v-if="spec.frequency" class="fb-grid-2 mt-2">
                <div><label class="fb-label" for="xbFreq">{{ t('lineitem.frequency') }} <span class="fb-opt">{{ t('common.optional') }}</span></label><input id="xbFreq" v-model.number="lineitem.frequency_cap" type="number" min="1" max="100" class="form-control"></div>
                <div><label class="fb-label" for="xbFreqD">{{ t('lineitem.frequency_days') }}</label><select id="xbFreqD" v-model.number="lineitem.frequency_days" class="form-control" :disabled="!lineitem.frequency_cap"><option v-for="d in boot.options.frequency_days" :key="d" :value="d">{{ t('lineitem.days', { n: d }) }}</option></select></div>
              </div>
            </div>

            <details class="fb-group xb-details">
              <summary><i class="bx bx-line-chart"></i> {{ t('lineitem.tracking') }}</summary>
              <div class="fb-grid-2 mt-2">
                <div><label class="fb-label" for="xbImp">{{ t('lineitem.impression_tag') }}</label><input id="xbImp" v-model.trim="lineitem.impression_tag" type="url" class="form-control" placeholder="https://" dir="ltr"></div>
                <div><label class="fb-label" for="xbClk">{{ t('lineitem.click_tracker') }}</label><input id="xbClk" v-model.trim="lineitem.click_tracker" type="url" class="form-control" placeholder="https://" dir="ltr"></div>
              </div>
            </details>
            <p v-if="fieldErrors.lineitem" class="fb-field-err">{{ fieldErrors.lineitem }}</p>
          </div>

          <!-- ============ 3. Audience ============ -->
          <div v-show="current === 'audience'" class="fb-card">
            <div class="fb-card-h"><h5>{{ t('audience.title') }}</h5></div>
            <p class="fb-sub">{{ t('audience.sub') }}</p>

            <!-- Location & demographics -->
            <div class="fb-group mt-0">
              <h6><i class="bx bx-map"></i> {{ t('audience.where_who') }}</h6>
              <label class="fb-label">{{ t('audience.locations') }}</label>
              <div class="xb-types">
                <button v-for="lt in locationTypes" :key="lt || 'any'" type="button" class="fb-chip-btn" :class="{ 'is-on': locationType === lt }" @click="locationType = lt">{{ t('location_types.' + (lt || 'ANY')) }}</button>
              </div>
              <search-picker v-model="targeting.locations" :fetcher="searchLocations" :max="2000" :placeholder="t('audience.search_locations')">
                <template #option="{ item }"><span class="sp-opt-main"><strong>{{ item.name }}</strong></span><span class="fb-tag">{{ known('location_types', item.type, humanize(item.type)) }}</span></template>
              </search-picker>
              <p class="fb-hint"><i class="bx bx-info-circle"></i> {{ t('audience.locations_hint') }}</p>

              <div class="fb-grid-2 mt-2">
                <div>
                  <label class="fb-label">{{ t('audience.gender') }}</label>
                  <div class="fb-seg fb-seg-full">
                    <button v-for="g in boot.options.genders" :key="g" type="button" :class="{ on: targeting.gender === g }" @click="targeting.gender = g">{{ t('genders.' + g) }}</button>
                  </div>
                </div>
                <div>
                  <label class="fb-label" for="xbAge">{{ t('audience.age') }} <i class="bx bx-help-circle fb-tip" :title="t('audience.age_tip')"></i></label>
                  <select id="xbAge" v-model="targeting.age" class="form-control">
                    <option :value="null">{{ t('audience.all_ages') }}</option>
                    <option v-for="a in boot.options.age_buckets" :key="a" :value="a">{{ ageLabel(a) }}</option>
                  </select>
                </div>
              </div>
              <label class="fb-label mt-2">{{ t('audience.languages') }} <span class="fb-opt">{{ t('common.optional') }}</span></label>
              <search-picker v-model="targeting.languages" :options="lists.languages" :min-chars="1" :placeholder="lists.loading.languages ? t('common.loading') : t('audience.search_languages')" />
              <p v-if="lists.errors.languages" class="fb-field-err">{{ lists.errors.languages }}</p>
            </div>

            <!-- Interests, conversations, events, keywords -->
            <div class="fb-group">
              <h6><i class="bx bx-heart"></i> {{ t('audience.interests_title') }}</h6>
              <p class="fb-muted">{{ t('audience.primary_hint') }}</p>
              <label class="fb-label">{{ t('audience.interests') }} <span class="fb-opt">{{ t('audience.max', { n: 100 }) }}</span></label>
              <search-picker v-model="targeting.interests" :fetcher="searchInterests" :max="100" :placeholder="t('audience.search_interests')" />

              <label class="fb-label mt-2">{{ t('audience.conversations') }}</label>
              <div class="fb-grid-2 xb-inline">
                <select v-model="conversationType" class="form-control" :aria-label="t('audience.conversation_type')">
                  <option :value="null">{{ t('audience.all_topics') }}</option>
                  <option v-for="c in boot.options.conversation_types" :key="c" :value="c">{{ humanize(c) }}</option>
                </select>
              </div>
              <search-picker v-model="targeting.conversations" :fetcher="searchConversations" :max="500" :placeholder="t('audience.search_conversations')">
                <template #option="{ item }"><span class="sp-opt-main"><strong>{{ item.name }}</strong></span><span v-if="item.type" class="fb-tag">{{ humanize(item.type) }}</span></template>
              </search-picker>

              <label class="fb-label mt-2">{{ t('audience.event') }} <span class="fb-opt">{{ t('audience.one_event') }}</span></label>
              <div v-if="targeting.event" class="fb-chips"><span class="fb-chip">{{ targeting.event.name }} <button type="button" :aria-label="t('common.remove')" @click="targeting.event = null"><i class="bx bx-x"></i></button></span></div>
              <div v-else class="fb-grid-2 xb-inline">
                <select v-model="eventType" class="form-control" :aria-label="t('audience.event_type')" @change="loadEvents">
                  <option :value="null">{{ t('audience.choose_event_type') }}</option>
                  <option v-for="e in boot.options.event_types" :key="e" :value="e">{{ humanize(e) }}</option>
                </select>
                <select v-model="eventPick" class="form-control" :disabled="!eventType || lists.loading.events" @change="targeting.event = eventPick; eventPick = null">
                  <option :value="null">{{ lists.loading.events ? t('common.loading') : (lists.events.length ? t('audience.choose_event') : t('audience.no_events')) }}</option>
                  <option v-for="e in lists.events" :key="e.id" :value="e">{{ e.name }}{{ e.start_time ? ' · ' + e.start_time.slice(0, 10) : '' }}</option>
                </select>
              </div>
              <p v-if="lists.errors.events" class="fb-field-err">{{ lists.errors.events }}</p>

              <label class="fb-label mt-3">{{ t('audience.keywords') }}</label>
              <div class="xb-kw">
                <textarea v-model="keywordDraft" class="form-control" rows="2" :placeholder="t('audience.keywords_placeholder')"></textarea>
                <div class="xb-kw-row">
                  <select v-model="keywordType" class="form-control" :aria-label="t('audience.match_type')">
                    <option v-for="k in boot.options.keyword_types" :key="k" :value="k">{{ t('keyword_types.' + k) }}</option>
                  </select>
                  <button type="button" class="btn btn-sm btn-primary" :disabled="!keywordDraft.trim()" @click="addKeywords(false)"><i class="bx bx-plus"></i> {{ t('audience.include') }}</button>
                  <button type="button" class="btn btn-sm fb-btn-ghost" :disabled="!keywordDraft.trim()" @click="addKeywords(true)"><i class="bx bx-block"></i> {{ t('audience.exclude') }}</button>
                </div>
              </div>
              <div v-if="targeting.keywords.length || targeting.excluded_keywords.length" class="fb-chips mt-2">
                <span v-for="(k, i) in targeting.keywords" :key="'i' + i" class="fb-chip">{{ k.value }} <small class="xb-chip-type">{{ t('keyword_types.' + k.type) }}</small><button type="button" :aria-label="t('common.remove')" @click="targeting.keywords.splice(i, 1)"><i class="bx bx-x"></i></button></span>
                <span v-for="(k, i) in targeting.excluded_keywords" :key="'e' + i" class="fb-chip is-exclude">− {{ k.value }} <small class="xb-chip-type">{{ t('keyword_types.' + k.type) }}</small><button type="button" :aria-label="t('common.remove')" @click="targeting.excluded_keywords.splice(i, 1)"><i class="bx bx-x"></i></button></span>
              </div>
              <p v-if="hasExactOffSearch" class="fb-hint is-warn"><i class="bx bx-error"></i> {{ t('audience.exact_search_only') }}</p>
            </div>

            <!-- Followers -->
            <div class="fb-group">
              <h6><i class="bx bx-user-check"></i> {{ t('audience.followers_title') }}</h6>
              <label class="fb-toggle">
                <input v-model="targeting.own_followers" type="checkbox">
                <span><strong>{{ t('audience.own_followers') }}</strong><small>{{ t('audience.own_followers_desc') }}</small></span>
              </label>
              <label class="fb-label mt-2">{{ t('audience.similar_followers') }} <span class="fb-opt">{{ t('audience.max', { n: 100 }) }}</span></label>
              <div class="xb-kw">
                <textarea v-model="handlesDraft" class="form-control" rows="2" dir="ltr" :placeholder="t('audience.handles_placeholder')"></textarea>
                <div class="xb-kw-row"><button type="button" class="btn btn-sm btn-primary" :disabled="!handlesDraft.trim() || lists.loading.users" @click="addHandles"><span v-if="lists.loading.users" class="spinner-border spinner-border-sm"></span><i v-else class="bx bx-search"></i> {{ t('audience.find_accounts') }}</button></div>
              </div>
              <p v-if="lists.errors.users" class="fb-field-err">{{ lists.errors.users }}</p>
              <p v-if="missingHandles.length" class="fb-hint is-warn"><i class="bx bx-error"></i> {{ t('audience.handles_missing', { handles: missingHandles.map((h) => '@' + h).join(', ') }) }}</p>
              <div v-if="targeting.similar_followers.length" class="fb-chips mt-2">
                <span v-for="(u, i) in targeting.similar_followers" :key="u.id" class="fb-chip">@{{ u.handle }}<small v-if="u.followers" class="xb-chip-type">{{ compact(u.followers) }}</small><button type="button" :aria-label="t('common.remove')" @click="targeting.similar_followers.splice(i, 1)"><i class="bx bx-x"></i></button></span>
              </div>
            </div>

            <!-- Custom audiences -->
            <div class="fb-group">
              <h6><i class="bx bx-group"></i> {{ t('audience.custom_title') }}</h6>
              <div v-if="lists.loading.audiences" class="fb-inline-load"><span class="spinner-border spinner-border-sm"></span> {{ t('common.loading') }}</div>
              <p v-else-if="lists.errors.audiences" class="fb-field-err">{{ lists.errors.audiences }}</p>
              <p v-else-if="!usableAudiences.length" class="fb-muted">{{ t('audience.no_custom') }}</p>
              <template v-else>
                <label class="fb-label">{{ t('audience.include') }}</label>
                <search-picker v-model="targeting.audiences" :options="usableAudiences" :min-chars="0" :max="100" :placeholder="t('audience.search_audiences')">
                  <template #option="{ item }"><span class="sp-opt-main"><strong>{{ item.name }}</strong> <small v-if="item.size" class="fb-muted">~{{ compact(item.size) }}</small></span><span class="fb-tag">{{ known('audience_types', item.type, humanize(item.type)) }}</span></template>
                </search-picker>
                <label v-if="targeting.audiences.length" class="fb-check is-sm mt-1"><input v-model="targeting.audience_lookalike" type="checkbox"> {{ t('audience.audience_lookalike') }}</label>
                <label class="fb-label mt-2">{{ t('audience.exclude') }}</label>
                <search-picker v-model="targeting.excluded_audiences" :options="usableAudiences" :min-chars="0" :max="100" chip-tone="exclude" :placeholder="t('audience.search_audiences')">
                  <template #option="{ item }"><span class="sp-opt-main"><strong>{{ item.name }}</strong></span><span class="fb-tag">{{ known('audience_types', item.type, humanize(item.type)) }}</span></template>
                </search-picker>
                <p class="fb-hint"><i class="bx bx-info-circle"></i> {{ t('audience.custom_hint') }}</p>
              </template>
            </div>

            <!-- Engagement retargeting -->
            <div class="fb-group">
              <h6><i class="bx bx-revision"></i> {{ t('audience.engagement_title') }}</h6>
              <p class="fb-muted">{{ t('audience.engagement_sub') }}</p>
              <div class="fb-grid-2">
                <div>
                  <label class="fb-label" for="xbEng">{{ t('audience.engagement_type') }}</label>
                  <select id="xbEng" v-model="targeting.engagement.type" class="form-control">
                    <option :value="null">{{ t('common.none') }}</option>
                    <option v-for="e in boot.options.engagement_types" :key="e" :value="e">{{ t('engagement_types.' + e) }}</option>
                  </select>
                </div>
                <div v-if="targeting.engagement.type" class="xb-pt">
                  <label class="fb-check is-sm"><input v-model="targeting.engagement.organic" type="checkbox"> {{ t('audience.engagement_organic') }}</label>
                </div>
              </div>
              <template v-if="targeting.engagement.type">
                <label class="fb-label mt-2">{{ t('audience.engagement_campaigns') }}</label>
                <search-picker v-model="targeting.engagement.campaigns" :options="lists.campaigns" :min-chars="0" :max="50" :placeholder="lists.loading.campaigns ? t('common.loading') : t('audience.search_campaigns')" />
              </template>
            </div>

            <!-- Devices -->
            <div class="fb-group">
              <h6><i class="bx bx-mobile"></i> {{ t('audience.devices_title') }}</h6>
              <p v-if="lists.errors.platforms" class="fb-field-err">{{ lists.errors.platforms }}</p>
              <label class="fb-label">{{ t('audience.platforms') }}</label>
              <div class="fb-place-grid">
                <label v-for="p in lists.platforms" :key="p.id" class="fb-check is-sm"><input v-model="targeting.platforms" type="checkbox" :value="p.id"> {{ p.name }}</label>
                <span v-if="lists.loading.platforms" class="fb-inline-load"><span class="spinner-border spinner-border-sm"></span></span>
              </div>
              <small class="fb-muted">{{ targeting.platforms.length ? '' : t('audience.all_platforms') }}</small>
              <label class="fb-label mt-2">{{ t('audience.os_versions') }} <span class="fb-opt">{{ t('common.optional') }}</span></label>
              <search-picker v-model="targeting.platform_versions" :options="lists.versions" :min-chars="0" :max="200" :placeholder="t('audience.search_versions')" />
              <label class="fb-label mt-2">{{ t('audience.devices') }} <span class="fb-opt">{{ t('common.optional') }}</span></label>
              <search-picker v-model="targeting.devices" :fetcher="searchDevices" :max="500" :placeholder="t('audience.search_devices')">
                <template #option="{ item }"><span class="sp-opt-main"><strong>{{ item.name }}</strong> <small class="fb-muted">{{ item.manufacturer }}</small></span><span v-if="item.os" class="fb-tag">{{ item.os }}</span></template>
              </search-picker>
              <p v-if="platformDeviceConflict" class="fb-hint is-warn"><i class="bx bx-error"></i> {{ t('audience.platform_device_conflict') }}</p>
              <div class="fb-grid-2 mt-2">
                <div>
                  <label class="fb-label" for="xbNetCc">{{ t('audience.carrier_country') }}</label>
                  <input id="xbNetCc" v-model.trim="networkCountry" class="form-control" maxlength="2" dir="ltr" placeholder="US" @input="networkCountry = networkCountry.toUpperCase()">
                </div>
                <div>
                  <label class="fb-label">{{ t('audience.carriers') }} <span class="fb-opt">{{ t('common.optional') }}</span></label>
                  <search-picker v-model="targeting.network_operators" :fetcher="searchOperators" :min-chars="0" :max="200" :placeholder="networkCountry.length === 2 ? t('audience.search_carriers') : t('audience.carrier_country_first')" />
                </div>
              </div>
              <label class="fb-label mt-2">{{ t('audience.app_categories') }} <span class="fb-opt">{{ t('common.optional') }}</span></label>
              <search-picker v-model="targeting.app_categories" :fetcher="searchAppCategories" :max="100" :placeholder="t('audience.search_app_categories')">
                <template #option="{ item }"><span class="sp-opt-main"><strong>{{ item.name }}</strong></span><span v-if="item.os" class="fb-tag">{{ item.os }}</span></template>
              </search-picker>
            </div>

            <!-- Expansion -->
            <div class="fb-group">
              <h6><i class="bx bx-expand-alt"></i> {{ t('audience.expansion_title') }}</h6>
              <div class="fb-seg">
                <button type="button" :class="{ on: !targeting.audience_expansion }" @click="targeting.audience_expansion = null">{{ t('common.off') }}</button>
                <button v-for="e in boot.options.audience_expansion" :key="e" type="button" :class="{ on: targeting.audience_expansion === e }" @click="targeting.audience_expansion = e">{{ t('expansion.' + e) }}</button>
              </div>
              <p class="fb-hint"><i class="bx bx-info-circle"></i> {{ t('audience.expansion_hint') }}</p>
            </div>
            <p v-for="w in searchConflicts" :key="w" class="fb-hint is-warn"><i class="bx bx-error"></i> {{ w }}</p>
            <p v-if="fieldErrors.audience" class="fb-field-err">{{ fieldErrors.audience }}</p>
          </div>

          <!-- ============ 4. Ad ============ -->
          <div v-show="current === 'ad'" class="fb-card">
            <div class="fb-card-h"><h5>{{ t('ad.title') }}</h5></div>

            <label class="fb-label">{{ t('ad.identity') }}</label>
            <div class="fb-pick-list">
              <button v-for="u in boot.promotable_users" :key="u.id" type="button" class="fb-pick" :class="{ on: ad.as_user_id === u.id }" @click="ad.as_user_id = u.id">
                <span class="fb-pick-ic"><i class="bx bx-user"></i></span>
                <span class="fb-pick-main"><strong dir="ltr">{{ u.handle ? '@' + u.handle : u.id }}</strong><small>{{ t('user_types.' + u.type) }}</small></span>
                <i v-if="ad.as_user_id === u.id" class="bx bxs-check-circle fb-pick-check"></i>
              </button>
              <p v-if="!boot.promotable_users.length" class="fb-hint is-warn"><i class="bx bx-error"></i> {{ t('ad.no_users') }}</p>
            </div>

            <label class="fb-label mt-3">{{ t('ad.source') }}</label>
            <div class="fb-seg">
              <button type="button" :class="{ on: ad.source === 'new' }" @click="ad.source = 'new'"><i class="bx bx-edit"></i> {{ t('ad.new_post') }}</button>
              <button type="button" :class="{ on: ad.source === 'existing' }" @click="ad.source = 'existing'"><i class="bx bx-repost"></i> {{ t('ad.existing_post') }}</button>
            </div>

            <!-- Existing posts -->
            <template v-if="ad.source === 'existing'">
              <p class="fb-hint"><i class="bx bx-info-circle"></i> {{ t('ad.existing_hint') }}</p>
              <p v-if="!ad.as_user_id" class="fb-muted">{{ t('ad.pick_identity_first') }}</p>
              <div v-else-if="lists.loading.tweets && !lists.tweets.length" class="fb-inline-load"><span class="spinner-border spinner-border-sm"></span> {{ t('common.loading') }}</div>
              <p v-else-if="lists.errors.tweets" class="fb-field-err">{{ lists.errors.tweets }}</p>
              <template v-else>
                <div class="xb-posts">
                  <button v-for="p in lists.tweets" :key="p.id" type="button" class="xb-post" :class="{ on: ad.tweets.some((x) => x.id === p.id) }" @click="toggleTweet(p)">
                    <img v-if="p.thumbnail" :src="p.thumbnail" alt="">
                    <span class="xb-post-body"><span>{{ (p.text || '').slice(0, 140) || t('ad.no_text') }}</span><small>{{ p.created_at ? new Date(p.created_at).toLocaleDateString(i18n.intl) : '' }}<template v-if="p.media_type"> · {{ known('media_types', p.media_type, p.media_type) }}</template><template v-if="p.nullcast"> · {{ t('ad.promoted_only') }}</template></small></span>
                    <i class="bx" :class="ad.tweets.some((x) => x.id === p.id) ? 'bxs-check-circle' : 'bx-circle'"></i>
                  </button>
                </div>
                <p v-if="!lists.tweets.length" class="fb-muted">{{ t('ad.no_posts') }}</p>
                <button v-if="tweetsCursor" type="button" class="btn btn-sm fb-btn-ghost mt-2" :disabled="lists.loading.tweets" @click="loadTweets(true)">{{ t('ad.more_posts') }}</button>
              </template>
            </template>

            <!-- New promoted-only post -->
            <template v-else>
              <label class="fb-label mt-3" for="xbText">{{ t('ad.text') }}</label>
              <textarea id="xbText" v-model="ad.text" class="form-control" rows="3" maxlength="280" :placeholder="t('ad.text_placeholder')"></textarea>
              <small class="fb-count" :class="{ 'is-warn': ad.text.length > 260 }">{{ ad.text.length }} / 280</small>

              <label class="fb-label mt-2">{{ t('ad.media') }} <span class="fb-opt">{{ ad.card === 'none' ? t('ad.media_rule') : t('ad.media_card_rule') }}</span></label>
              <div class="fb-media-row">
                <div v-for="(m, i) in ad.media" :key="m.id" class="fb-media">
                  <img v-if="m.type === 'image' || m.thumbnail_url" :src="m.thumbnail_url || m.url" alt="">
                  <span v-else class="fb-media-ph"><i class="bx bx-play"></i></span>
                  <div><strong>{{ m.original_name }}</strong><small>{{ m.type === 'video' ? t('ad.video') : t('ad.image') }}<template v-if="m.width"> · {{ m.width }}×{{ m.height }}</template></small></div>
                  <button type="button" class="fb-icon-btn" :aria-label="t('common.remove')" @click="ad.media.splice(i, 1)"><i class="bx bx-trash"></i></button>
                </div>
                <div v-if="ad.media.length < mediaMax" class="fb-media-actions">
                  <button type="button" class="btn btn-sm fb-btn-ghost" @click="openGallery"><i class="bx bx-images"></i> {{ t('ad.from_gallery') }}</button>
                </div>
              </div>
              <p class="fb-hint"><i class="bx bx-info-circle"></i> {{ t('ad.media_specs') }}</p>
              <p v-if="mediaError" class="fb-field-err">{{ mediaError }}</p>

              <label class="fb-label mt-3">{{ t('ad.card') }}</label>
              <div class="fb-seg">
                <button type="button" :class="{ on: ad.card === 'none' }" :disabled="spec && spec.needs_app" @click="ad.card = 'none'">{{ t('cards.none') }}</button>
                <button type="button" :class="{ on: ad.card === 'website' }" :disabled="spec && spec.needs_app" @click="ad.card = 'website'"><i class="bx bx-globe"></i> {{ t('cards.website') }}</button>
                <button type="button" :class="{ on: ad.card === 'app' }" :disabled="!(spec && spec.needs_app)" @click="ad.card = 'app'"><i class="bx bx-mobile-alt"></i> {{ t('cards.app') }}</button>
              </div>
              <p class="fb-hint"><i class="bx bx-info-circle"></i> {{ t('cards.' + ad.card + '_hint') }}</p>
              <div class="fb-grid-2">
                <div v-if="ad.card === 'website'">
                  <label class="fb-label" for="xbHead">{{ t('ad.headline') }}</label>
                  <input id="xbHead" v-model.trim="ad.headline" class="form-control" maxlength="70">
                </div>
                <div v-if="ad.card !== 'app'">
                  <label class="fb-label" for="xbUrl">{{ t('ad.url') }} <span v-if="!(spec && spec.needs_url) && ad.card !== 'website'" class="fb-opt">{{ t('common.optional') }}</span></label>
                  <input id="xbUrl" v-model.trim="ad.url" type="url" class="form-control" placeholder="https://" dir="ltr">
                </div>
                <div v-if="ad.card === 'app'">
                  <label class="fb-label" for="xbLabel">{{ t('ad.app_label') }}</label>
                  <select id="xbLabel" v-model="ad.app_label" class="form-control"><option v-for="l in boot.options.app_card_labels" :key="l" :value="l">{{ t('app_labels.' + l) }}</option></select>
                </div>
                <div>
                  <label class="fb-label" for="xbReplies">{{ t('ad.replies') }}</label>
                  <select id="xbReplies" v-model="ad.conversation_settings" class="form-control"><option v-for="c in boot.options.conversation_settings" :key="c" :value="c">{{ t('replies.' + c) }}</option></select>
                </div>
              </div>
              <p class="fb-hint"><i class="bx bx-hide"></i> {{ t('ad.nullcast_hint') }}</p>
            </template>

            <label class="fb-label mt-2" for="xbAdName">{{ t('ad.name') }} <span class="fb-opt">{{ t('common.optional') }}</span></label>
            <input id="xbAdName" v-model.trim="ad.name" class="form-control" maxlength="80">
            <p v-if="fieldErrors.ad" class="fb-field-err">{{ fieldErrors.ad }}</p>
          </div>

          <!-- ============ 5. Review ============ -->
          <div v-show="current === 'review'" class="fb-card">
            <div class="fb-card-h"><h5>{{ t('review.title') }}</h5></div>
            <div class="fb-review">
              <div v-for="row in reviewRows" :key="row.key" class="fb-review-row">
                <span class="fb-review-l"><i class="bx" :class="row.icon"></i> {{ row.label }}</span>
                <span class="fb-review-v">{{ row.value }}</span>
                <button type="button" class="fb-link" @click="goTo(row.step)">{{ t('common.edit') }}</button>
              </div>
            </div>

            <label class="fb-label mt-3">{{ t('review.when_ready') }}</label>
            <div class="fb-seg">
              <button type="button" :class="{ on: status === 'PAUSED' }" @click="status = 'PAUSED'"><i class="bx bx-pause-circle"></i> {{ t('review.save_paused') }}</button>
              <button type="button" :class="{ on: status === 'ACTIVE' }" @click="status = 'ACTIVE'"><i class="bx bx-play-circle"></i> {{ t('review.publish') }}</button>
            </div>
            <p class="fb-hint"><i class="bx bx-info-circle"></i> {{ status === 'ACTIVE' ? t('review.publish_hint') : t('review.paused_hint') }}</p>

            <div v-if="blockers.length" class="fb-blockers">
              <strong><i class="bx bx-error-circle"></i> {{ t('review.fix_before') }}</strong>
              <ul><li v-for="b in blockers" :key="b.text"><button type="button" class="fb-link" @click="goTo(b.step)">{{ b.text }}</button></li></ul>
            </div>

            <div v-if="result && result.success" class="fb-result is-ok">
              <i class="bx bx-check-circle"></i>
              <div>
                <strong>{{ result.message }}</strong>
                <p>{{ result.status === 'ACTIVE' ? t('result.live') : t('result.saved_paused') }} {{ t('result.line_item_id', { id: result.line_item_id }) }}</p>
                <a :href="result.redirect_url || urls.campaignsList" class="btn btn-primary btn-sm">{{ t('result.go_campaigns') }}</a>
                <button type="button" class="btn btn-sm fb-btn-ghost" @click="startAnother">{{ t('result.another') }}</button>
              </div>
            </div>
            <div v-else-if="result" class="fb-result is-err">
              <i class="bx bx-error-circle"></i>
              <div>
                <strong>{{ result.step ? t('result.step_failed', { step: t('result_steps.' + result.step) }) : t('result.failed') }}</strong>
                <p>{{ result.message }}</p>
                <ul v-if="result.created && result.created.length" class="fb-created">
                  <li v-for="c in result.created" :key="c.type"><i class="bx bx-check"></i> {{ t('result.created_item', { step: t('result_steps.' + c.type), action: c.reused ? t('result.selected') : t('result.created'), id: c.id }) }}</li>
                </ul>
                <p class="fb-muted">{{ t('result.fix_retry') }}</p>
                <a v-if="result.reconnect" :href="urls.connections" class="btn btn-sm btn-primary">{{ t('boot.go_connections') }}</a>
              </div>
            </div>
          </div>

          <div class="fb-foot">
            <button type="button" class="btn btn-sm fb-btn-ghost" :disabled="step === 0 || submitting" @click="back"><i class="bx bx-chevron-left"></i> {{ t('common.back') }}</button>
            <span class="fb-saved" :class="{ on: savedAt }"><i class="bx bx-cloud-upload"></i> {{ savedAt ? t('draft.saved', { time: savedAt }) : t('draft.auto') }}</span>
            <button v-if="current !== 'review'" type="button" class="btn btn-primary btn-sm" @click="next">{{ t('common.continue') }} <i class="bx bx-chevron-right"></i></button>
            <button v-else type="button" class="btn btn-primary btn-sm" :disabled="submitting || blockers.length > 0 || (result && result.success)" @click="submit">
              <span v-if="submitting" class="spinner-border spinner-border-sm"></span><i v-else class="bx bx-rocket"></i>
              {{ submitting ? t('foot.creating') : (result && !result.success ? t('foot.try_again') : (status === 'ACTIVE' ? t('foot.publish') : t('foot.create'))) }}
            </button>
          </div>
        </section>

        <!-- ===== Side: preview & summary ===== -->
        <aside class="fb-side">
          <div class="fb-card">
            <div class="fb-side-h"><strong><i class="bx bx-show"></i> {{ t('side.preview') }}</strong><small>{{ t('side.approximate') }}</small></div>
            <div class="xb-tweet">
              <span class="xb-av"><svg viewBox="0 0 24 24"><path fill="currentColor" d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg></span>
              <div class="xb-tweet-main">
                <div class="xb-tweet-h"><strong>{{ boot.account.name }}</strong> <span dir="ltr">{{ previewHandle }}</span></div>
                <p class="xb-tweet-text">{{ previewText || t('side.text_placeholder') }}</p>
                <div v-if="previewImage || ad.card !== 'none'" class="xb-tweet-media" :class="{ 'is-card': ad.source === 'new' && ad.card !== 'none' }">
                  <img v-if="previewImage" :src="previewImage" alt="">
                  <div v-else class="xb-tweet-ph"><i class="bx bx-image"></i></div>
                  <div v-if="ad.source === 'new' && ad.card === 'website'" class="xb-card-foot"><small dir="ltr">{{ domain || 'example.com' }}</small><strong>{{ ad.headline || t('side.headline_placeholder') }}</strong></div>
                  <div v-if="ad.source === 'new' && ad.card === 'app'" class="xb-card-foot is-app"><span>{{ t('side.app_store') }}</span><b>{{ t('app_labels.' + ad.app_label) }}</b></div>
                </div>
                <div class="xb-tweet-acts"><i class="bx bx-message-rounded"></i><i class="bx bx-repost"></i><i class="bx bx-heart"></i><i class="bx bx-bar-chart"></i></div>
                <small class="xb-ad-label"><i class="bx bxs-megaphone"></i> {{ t('side.ad') }}</small>
              </div>
            </div>
            <p v-if="ad.source === 'existing' && ad.tweets.length > 1" class="fb-muted mt-2 mb-0">{{ t('side.more_posts', { n: ad.tweets.length - 1 }) }}</p>
          </div>
          <div class="fb-card">
            <div class="fb-side-h"><strong><i class="bx bx-target-lock"></i> {{ t('side.summary') }}</strong></div>
            <ul class="xb-sum">
              <li v-for="s in summaryLines" :key="s.icon + s.text"><i class="bx" :class="s.icon"></i> {{ s.text }}</li>
            </ul>
            <div class="xb-estimate">
              <button type="button" class="btn btn-sm fb-btn-ghost w-100" :disabled="!hasPrimary || estimating" :title="hasPrimary ? '' : t('side.estimate_needs_primary')" @click="runEstimate">
                <span v-if="estimating" class="spinner-border spinner-border-sm"></span><i v-else class="bx bx-group"></i> {{ t('side.estimate') }}
              </button>
              <p v-if="estimate && estimate.available" class="xb-est-v">{{ compact(estimate.min) }} – {{ compact(estimate.max) }} <small>{{ t('side.people') }}</small></p>
              <p v-else-if="estimate && estimate.error" class="fb-field-err">{{ estimate.error }}</p>
              <p v-else-if="estimate" class="fb-muted">{{ t('side.estimate_unavailable') }}</p>
              <small v-if="!hasPrimary" class="fb-muted">{{ t('side.estimate_needs_primary') }}</small>
            </div>
          </div>
        </aside>
      </div>

      <media-picker ref="picker" :urls="galleryUrls" :multiple="ad.card === 'none'" :max="Math.max(1, mediaMax - ad.media.length)" :type-lock="pickerTypeLock" :platforms="['x']" @select="onPicked" />
    </template>
  </div>
</template>

<script setup>
// X campaign builder (X Ads API v12 through XAdBuilderController). Same
// building blocks as the Facebook and TikTok builders: shared styles,
// SearchPicker, MediaPicker and localStorage drafts per ad account; every
// submission carries an idempotency key.
import { ref, reactive, computed, watch, onMounted, nextTick, provide } from 'vue';
import SearchPicker from '../facebook/SearchPicker.vue';
import MediaPicker from '../../media/MediaPicker.vue';
import { createTranslator } from '../facebook/i18n';
import { setMediaTranslator } from '../../media/mediaShared';

const props = defineProps({
  urls: { type: Object, required: true },
  galleryUrls: { type: Object, required: true },
  accountId: { type: Number, required: true },
  messages: { type: Object, default: () => ({}) },
  mediaMessages: { type: Object, default: () => ({}) },
  locale: { type: String, default: 'en' },
});

const i18n = createTranslator(props.messages, props.locale);
const { t } = i18n;
provide('fbI18n', i18n);
setMediaTranslator(createTranslator(props.mediaMessages, props.locale).t);

const boot = ref(null);
const bootError = ref('');
const step = ref(0);
const maxReached = ref(0);
const submitting = ref(false);
const result = ref(null);
const savedAt = ref('');
const draftOffer = ref(null);
const idempotencyKey = ref(newKey());
const campaignQuery = ref('');
const locationType = ref(null);
const conversationType = ref(null);
const eventType = ref(null);
const eventPick = ref(null);
const keywordDraft = ref('');
const keywordType = ref('BROAD_KEYWORD');
const handlesDraft = ref('');
const missingHandles = ref([]);
const networkCountry = ref('');
const tweetsCursor = ref(null);
const mediaError = ref('');
const estimate = ref(null);
const estimating = ref(false);
const picker = ref(null);
const stepErrors = reactive({});
const fieldErrors = reactive({});
const locationTypes = [null, 'COUNTRIES', 'REGIONS', 'METROS', 'CITIES', 'POSTAL_CODES'];

const objective = ref(null);
const status = ref('PAUSED');
const campaign = reactive({ mode: 'new', id: null, name: '', funding_instrument_id: null, daily_budget: null, total_budget: null, purchase_order_number: '' });
const lineitem = reactive({
  name: '', goal: null, bid_strategy: 'AUTO', bid_amount: null, pay_by: null, placement_mode: 'all', placements: [],
  daily_budget: null, total_budget: null, standard_delivery: true, start_time: '', end_time: '', frequency_cap: null, frequency_days: 7,
  web_event_tag: '', impression_tag: '', click_tracker: '',
});
const targeting = reactive({
  locations: [], languages: [], gender: 'ANY', age: null, platforms: [], platform_versions: [], devices: [], network_operators: [], app_categories: [],
  interests: [], conversations: [], event: null, keywords: [], excluded_keywords: [], own_followers: false, similar_followers: [],
  audiences: [], excluded_audiences: [], audience_lookalike: false, audience_expansion: null, engagement: { type: null, campaigns: [], organic: false },
});
const app = reactive({ ios_id: '', android_id: '', country_code: '' });
const ad = reactive({ name: '', as_user_id: null, source: 'new', tweets: [], text: '', media: [], card: 'none', headline: '', url: '', app_label: 'INSTALL', conversation_settings: 'EVERYONE' });

const lists = reactive({ campaigns: [], audiences: [], languages: [], platforms: [], versions: [], events: [], tweets: [], loading: {}, errors: {} });

// ---------------------------------------------------------------- derived
const spec = computed(() => (objective.value && boot.value ? boot.value.objectives[objective.value] : null));
const usableFunding = computed(() => (boot.value ? boot.value.funding_instruments.filter((f) => f.usable) : []));
const selectedFunding = computed(() => (boot.value ? boot.value.funding_instruments.find((f) => f.id === campaign.funding_instrument_id) : null));
const selectedCampaign = computed(() => lists.campaigns.find((c) => c.id === campaign.id) || null);
const currency = computed(() => (campaign.mode === 'existing' && selectedCampaign.value && selectedCampaign.value.currency) || (selectedFunding.value && selectedFunding.value.currency) || (boot.value ? boot.value.account.currency : 'USD'));
const objectiveLocked = computed(() => campaign.mode === 'existing' && !!(selectedCampaign.value && selectedCampaign.value.objective));
const filteredCampaigns = computed(() => { const q = campaignQuery.value.toLowerCase(); return lists.campaigns.filter((c) => !q || c.name.toLowerCase().includes(q)); });
const usableAudiences = computed(() => lists.audiences.filter((a) => a.usable));
const placementsList = computed(() => (lineitem.placement_mode === 'all' || !lineitem.placements.length ? ['ALL_ON_TWITTER'] : lineitem.placements));
const searchOnly = computed(() => placementsList.value.length === 1 && placementsList.value[0] === 'TWITTER_SEARCH');
const includesSearch = computed(() => placementsList.value.includes('ALL_ON_TWITTER') || placementsList.value.includes('TWITTER_SEARCH'));
const payByOptions = computed(() => (boot.value && lineitem.goal ? boot.value.options.pay_by[lineitem.goal] || [] : []));
const forcedPayBy = computed(() => (lineitem.bid_strategy === 'TARGET' && lineitem.goal === 'LINK_CLICKS' ? 'LINK_CLICK' : null));
const effectivePayBy = computed(() => forcedPayBy.value || lineitem.pay_by || (payByOptions.value.length ? 'IMPRESSION' : null));
const mediaMax = computed(() => (ad.card !== 'none' ? 1 : (ad.media.some((m) => m.type === 'video') ? 1 : 4)));
const pickerTypeLock = computed(() => (spec.value && spec.value.needs_video ? 'video' : (ad.media.length && ad.media[0].type === 'image' ? 'image' : '')));
const hasExactOffSearch = computed(() => !includesSearch.value && targeting.keywords.concat(targeting.excluded_keywords).some((k) => k.type === 'EXACT_KEYWORD'));
const platformDeviceConflict = computed(() => {
  const names = lists.platforms.filter((p) => targeting.platforms.includes(p.id)).map((p) => p.name.toLowerCase());
  return targeting.devices.some((d) => d.os && names.some((n) => n.startsWith(String(d.os).toLowerCase())));
});
const searchConflicts = computed(() => {
  if (!searchOnly.value) return [];
  const used = [];
  if (targeting.interests.length) used.push(t('audience.interests'));
  if (targeting.conversations.length) used.push(t('audience.conversations'));
  if (targeting.own_followers || targeting.similar_followers.length) used.push(t('audience.followers_title'));
  if (targeting.engagement.type) used.push(t('audience.engagement_title'));
  return used.length ? [t('audience.search_conflict', { types: used.join(', ') })] : [];
});
const hasPrimary = computed(() => !!(targeting.interests.length || targeting.conversations.length || targeting.event || targeting.keywords.length || targeting.own_followers || targeting.similar_followers.length || targeting.audiences.length));
const previewTweet = computed(() => (ad.source === 'existing' ? ad.tweets[0] || null : null));
const previewHandle = computed(() => { const u = boot.value && boot.value.promotable_users.find((x) => x.id === ad.as_user_id); return u && u.handle ? '@' + u.handle : ''; });
const previewText = computed(() => (ad.source === 'existing' ? (previewTweet.value ? previewTweet.value.text : '') : ad.text + (ad.card === 'none' && ad.url && !ad.text.includes(ad.url) ? ' ' + ad.url : '')));
const previewImage = computed(() => {
  if (ad.source === 'existing') return previewTweet.value ? previewTweet.value.thumbnail : null;
  const m = ad.media[0];
  return m ? (m.type === 'image' ? m.url : m.thumbnail_url) : null;
});
const domain = computed(() => { try { return new URL(ad.url).hostname.replace(/^www\./, ''); } catch (e) { return ''; } });
const today = new Date().toISOString().slice(0, 10);

const steps = computed(() => [
  { key: 'campaign', label: t('steps.campaign'), summary: objective.value ? t('objectives.' + objective.value + '.label') : t('steps.pick_objective') },
  { key: 'lineitem', label: t('steps.lineitem'), summary: lineitem.goal ? t('goals.' + lineitem.goal) : t('steps.delivery') },
  { key: 'audience', label: t('steps.audience'), summary: targeting.locations.length ? t('steps.locations', { count: targeting.locations.length }) : t('steps.who') },
  { key: 'ad', label: t('steps.ad'), summary: ad.source === 'existing' ? t('steps.posts', { count: ad.tweets.length }) : t('steps.new_post') },
  { key: 'review', label: t('steps.review'), summary: t('steps.review_sub') },
]);
const current = computed(() => steps.value[step.value].key);

const summaryLines = computed(() => {
  const out = [];
  out.push({ icon: 'bx-map', text: targeting.locations.length ? targeting.locations.map((l) => l.name).slice(0, 3).join(', ') + (targeting.locations.length > 3 ? ' +' + (targeting.locations.length - 3) : '') : t('summary.worldwide') });
  out.push({ icon: 'bx-user', text: [t('genders.' + targeting.gender), targeting.age ? ageLabel(targeting.age) : t('audience.all_ages')].join(' · ') + (targeting.languages.length ? ' · ' + targeting.languages.map((l) => l.name).join(', ') : '') });
  const primary = [];
  if (targeting.interests.length) primary.push(t('summary.interests', { count: targeting.interests.length }));
  if (targeting.conversations.length) primary.push(t('summary.conversations', { count: targeting.conversations.length }));
  if (targeting.keywords.length) primary.push(t('summary.keywords', { count: targeting.keywords.length }));
  if (targeting.event) primary.push(targeting.event.name);
  if (targeting.own_followers) primary.push(t('summary.own_followers'));
  if (targeting.similar_followers.length) primary.push(t('summary.lookalikes', { count: targeting.similar_followers.length }));
  if (targeting.audiences.length) primary.push(t('summary.custom', { count: targeting.audiences.length }));
  if (primary.length) out.push({ icon: 'bx-heart', text: primary.join(' ∪ ') });
  const exclusions = [];
  if (targeting.excluded_keywords.length) exclusions.push(t('summary.keywords', { count: targeting.excluded_keywords.length }));
  if (targeting.excluded_audiences.length) exclusions.push(t('summary.custom', { count: targeting.excluded_audiences.length }));
  if (exclusions.length) out.push({ icon: 'bx-block', text: t('summary.excluding', { list: exclusions.join(', ') }) });
  if (targeting.engagement.type) out.push({ icon: 'bx-revision', text: t('engagement_types.' + targeting.engagement.type) });
  const devices = lists.platforms.filter((p) => targeting.platforms.includes(p.id)).map((p) => p.name);
  if (targeting.devices.length) devices.push(t('summary.devices', { count: targeting.devices.length }));
  if (devices.length) out.push({ icon: 'bx-mobile', text: devices.join(', ') });
  if (targeting.audience_expansion) out.push({ icon: 'bx-expand-alt', text: t('expansion.' + targeting.audience_expansion) });
  if (budgetLine.value) out.push({ icon: 'bx-wallet', text: budgetLine.value });
  return out;
});
const budgetLine = computed(() => {
  if (campaign.mode === 'existing') return selectedCampaign.value && selectedCampaign.value.daily_budget ? money(selectedCampaign.value.daily_budget) + t('steps.per_day') : '';
  if (!campaign.daily_budget) return '';
  return money(campaign.daily_budget) + t('steps.per_day') + (campaign.total_budget ? ' · ' + t('summary.total', { amount: money(campaign.total_budget) }) : '');
});

const reviewRows = computed(() => [
  { key: 'objective', step: 0, icon: 'bx-flag', label: t('review.objective'), value: objective.value ? t('objectives.' + objective.value + '.label') : '-' },
  { key: 'campaign', step: 0, icon: 'bx-folder', label: t('review.campaign'), value: campaign.mode === 'existing' ? (selectedCampaign.value ? selectedCampaign.value.name : '-') : (campaign.name || t('review.auto_name')) },
  { key: 'funding', step: 0, icon: 'bx-credit-card', label: t('review.funding'), value: campaign.mode === 'existing' ? t('review.from_campaign') : (selectedFunding.value ? selectedFunding.value.name : '-') },
  { key: 'budget', step: 0, icon: 'bx-wallet', label: t('review.budget'), value: budgetLine.value || '-' },
  { key: 'goal', step: 1, icon: 'bx-target-lock', label: t('review.goal'), value: lineitem.goal ? t('goals.' + lineitem.goal) + ' · ' + t('bids.' + lineitem.bid_strategy + '.label') + (lineitem.bid_strategy !== 'AUTO' ? ' ' + money(lineitem.bid_amount) : '') : '-' },
  { key: 'placements', step: 1, icon: 'bx-layout', label: t('review.placements'), value: placementsList.value.map((p) => t('placements.' + p)).join(', ') },
  { key: 'schedule', step: 1, icon: 'bx-calendar', label: t('review.schedule'), value: (lineitem.start_time || t('review.now')).replace('T', ' ') + ' → ' + (lineitem.end_time ? lineitem.end_time.replace('T', ' ') : t('review.ongoing')) },
  { key: 'audience', step: 2, icon: 'bx-group', label: t('review.audience'), value: summaryLines.value.filter((s) => s.icon !== 'bx-wallet').map((s) => s.text).join(' · ') },
  { key: 'creative', step: 3, icon: 'bx-message-square-detail', label: t('review.creative'), value: ad.source === 'existing' ? t('steps.posts', { count: ad.tweets.length }) : t('cards.' + ad.card) + (ad.media.length ? ' · ' + t('review.media', { count: ad.media.length }) : '') },
  { key: 'destination', step: 3, icon: 'bx-link', label: t('review.destination'), value: ad.source === 'existing' ? t('review.in_post') : (ad.card === 'app' ? [app.ios_id, app.android_id].filter(Boolean).join(' / ') : (ad.url || '-')) },
]);

// ---------------------------------------------------------------- validation
function validate() {
  const p = [];
  const add = (s, key, params) => p.push({ step: s, text: t('errors.' + key, params) });
  if (!objective.value) add(0, 'objective');
  if (campaign.mode === 'existing' && !campaign.id) add(0, 'campaign');
  if (campaign.mode === 'new') {
    if (!campaign.funding_instrument_id) add(0, 'funding');
    if (!(campaign.daily_budget > 0)) add(0, 'daily_budget');
    if (campaign.total_budget && campaign.daily_budget && campaign.total_budget < campaign.daily_budget) add(0, 'total_vs_daily');
  }
  if (objective.value && !lineitem.goal) add(1, 'goal');
  if (lineitem.goal === 'WEBSITE_CONVERSIONS' && !/^[A-Za-z0-9_-]+$/.test(lineitem.web_event_tag)) add(1, 'web_event_tag');
  if (spec.value && spec.value.needs_app) {
    if (!app.ios_id && !app.android_id) add(1, 'app_id');
    if (app.ios_id && !/^\d{5,12}$/.test(app.ios_id)) add(1, 'ios_id');
    if (app.android_id && !/^[A-Za-z][A-Za-z0-9_]*(\.[A-Za-z0-9_]+)+$/.test(app.android_id)) add(1, 'android_id');
    if (!/^[A-Z]{2}$/.test(app.country_code)) add(1, 'app_country');
  }
  if (lineitem.placement_mode === 'custom' && !lineitem.placements.length) add(1, 'placements');
  if (lineitem.placement_mode === 'custom' && lineitem.placements.length && !validCombo()) add(1, 'placement_combo');
  if (lineitem.end_time && lineitem.start_time && lineitem.end_time <= lineitem.start_time) add(1, 'end_after');
  if (lineitem.bid_strategy !== 'AUTO' && !(lineitem.bid_amount > 0)) add(1, 'bid');
  if (/\S/.test(lineitem.impression_tag) && !/^https:\/\/\S+$/.test(lineitem.impression_tag)) add(1, 'tracking_url');
  if (/\S/.test(lineitem.click_tracker) && !/^https:\/\/\S+$/.test(lineitem.click_tracker)) add(1, 'tracking_url');
  if (searchConflicts.value.length) add(2, 'search_targeting');
  if (platformDeviceConflict.value) add(2, 'platform_device');
  if (targeting.engagement.type && !targeting.engagement.campaigns.length && !targeting.engagement.organic) add(2, 'engagement_source');
  if (!ad.as_user_id) add(3, 'identity');
  if (ad.source === 'existing') {
    if (!ad.tweets.length) add(3, 'posts');
    if (spec.value && spec.value.needs_video && ad.tweets.some((x) => !['video', 'animated_gif'].includes(x.media_type))) add(3, 'posts_video');
  } else {
    if (!ad.text.trim() && !ad.media.length) add(3, 'text');
    if (ad.card !== 'none' && ad.media.length !== 1) add(3, 'card_media');
    if (spec.value && spec.value.needs_video && !ad.media.some((m) => m.type === 'video')) add(3, 'needs_video');
    if (ad.card === 'website' && !ad.headline) add(3, 'headline');
    if ((ad.card === 'website' || (spec.value && spec.value.needs_url)) && ad.card !== 'app' && !ad.url) add(3, 'url');
    if (ad.url && !/^https?:\/\/\S+\.\S+/.test(ad.url)) add(3, 'url_format');
    if (spec.value && spec.value.needs_app && ad.card !== 'app') add(3, 'app_card');
  }
  return p;
}
const blockers = computed(() => validate());

// ---------------------------------------------------------------- helpers
function newKey() {
  return (window.crypto && window.crypto.randomUUID) ? window.crypto.randomUUID() : Date.now().toString(36) + Math.random().toString(36).slice(2) + Math.random().toString(36).slice(2);
}
function money(v, cur) {
  if (v === null || v === undefined || v === '') return '-';
  try { return new Intl.NumberFormat(i18n.intl, { style: 'currency', currency: cur || currency.value || 'USD', maximumFractionDigits: 2 }).format(v); } catch (e) { return v; }
}
function compact(n) { return n === null || n === undefined ? '-' : new Intl.NumberFormat(i18n.intl, { notation: 'compact', maximumFractionDigits: 1 }).format(n); }
const humanize = (v) => (v || '').replace(/_/g, ' ').toLowerCase().replace(/^./, (x) => x.toUpperCase());
const known = (key, code, fallback) => { const v = t(key + '.' + code); return v === key + '.' + code ? fallback : v; };
function ageLabel(a) { const m = a.match(/^AGE_(\d+)_TO_(\d+)$/); if (m) return m[1] + '–' + m[2]; const o = a.match(/^AGE_OVER_(\d+)$/); return o ? o[1] + '+' : a; }
function placementIcon(p) { return ({ TWITTER_TIMELINE: 'bx-home', TWITTER_SEARCH: 'bx-search', TWITTER_PROFILE: 'bx-user-circle', TWITTER_REPLIES: 'bx-message-rounded-dots' })[p] || 'bx-layout'; }
function validCombo() {
  const combos = boot.value.options.placements.combinations;
  const want = [...placementsList.value].sort().join(',');
  return !combos.length || combos.some((c) => [...c].sort().join(',') === want);
}
function nowInAccountTz(addMinutes = 15) {
  const d = new Date(Date.now() + addMinutes * 60000);
  try {
    const parts = Object.fromEntries(new Intl.DateTimeFormat('en-CA', { timeZone: boot.value.account.timezone, year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit', hourCycle: 'h23' }).formatToParts(d).map((x) => [x.type, x.value]));
    return `${parts.year}-${parts.month}-${parts.day}T${parts.hour}:${parts.minute}`;
  } catch (e) {
    return d.toISOString().slice(0, 16);
  }
}

function errorMessage(e) {
  return (e.response && e.response.data && (e.response.data.message || Object.values(e.response.data.errors || {}).flat()[0])) || e.message || t('client.request_failed');
}
async function api(url, params = {}, method = 'get') {
  try {
    const { data } = method === 'post' ? await window.axios.post(url, params) : await window.axios.get(url, { params });
    if (!data.success) throw new Error(data.message || t('client.x_error'));
    return data.data;
  } catch (e) {
    throw new Error(errorMessage(e));
  }
}
async function load(name, url, params = {}, assign = null, method = 'get') {
  lists.loading = { ...lists.loading, [name]: true };
  lists.errors = { ...lists.errors, [name]: '' };
  try {
    const data = await api(url, params, method);
    if (assign) assign(data); else lists[name] = data;
  } catch (e) {
    lists.errors = { ...lists.errors, [name]: e.message };
  } finally {
    lists.loading = { ...lists.loading, [name]: false };
  }
}
const loadedOnce = new Set();
function ensure(name, url, params = {}, assign = null) {
  if (loadedOnce.has(name)) return;
  loadedOnce.add(name);
  load(name, url, params, assign);
}
const loadCampaigns = () => ensure('campaigns', props.urls.campaigns);

const searchLocations = (q) => api(props.urls.locations, { q, type: locationType.value || undefined });
const searchInterests = (q) => api(props.urls.interests, { q });
const searchConversations = (q) => api(props.urls.conversations, { q, type: conversationType.value || undefined });
const searchDevices = (q) => api(props.urls.devices, { q });
const searchOperators = (q) => (networkCountry.value.length === 2 ? api(props.urls.networkOperators, { country_code: networkCountry.value, q: q || undefined }) : Promise.resolve([]));
const searchAppCategories = (q) => api(props.urls.appCategories, { q });

function loadEvents() {
  lists.events = [];
  if (eventType.value) load('events', props.urls.events, { type: eventType.value, country_code: (targeting.locations.find((l) => l.country_code) || {}).country_code || undefined });
}
function loadTweets(more = false) {
  if (!ad.as_user_id) return;
  const params = { user_id: ad.as_user_id };
  if (more && tweetsCursor.value) params.cursor = tweetsCursor.value;
  load('tweets', props.urls.tweets, params, (data) => {
    lists.tweets = more ? lists.tweets.concat(data.tweets) : data.tweets;
    tweetsCursor.value = data.cursor || null;
  });
}

// ---------------------------------------------------------------- actions
function setCampaignMode(mode) {
  campaign.mode = mode;
  if (mode === 'existing') loadCampaigns();
}
function chooseCampaign(c) {
  campaign.id = c.id;
  if (c.objective) setObjective(c.objective, true);
}
function setObjective(key, fromCampaign = false) {
  if (objectiveLocked.value && !fromCampaign) return;
  if (objective.value === key) return;
  objective.value = key;
  const s = boot.value.objectives[key];
  lineitem.goal = s.goals[0];
  if (!s.bids.includes(lineitem.bid_strategy)) lineitem.bid_strategy = 'AUTO';
  if (!s.frequency) lineitem.frequency_cap = null;
  ad.card = s.needs_app ? 'app' : (ad.card === 'app' ? 'none' : ad.card);
  if (s.needs_url && ad.card === 'none' && ad.media.length === 1) ad.card = 'website';
}
function addKeywords(exclude) {
  const target = exclude ? targeting.excluded_keywords : targeting.keywords;
  keywordDraft.value.split(/[\n,]+/).map((k) => k.trim()).filter(Boolean).slice(0, 1000 - target.length).forEach((value) => {
    if (!target.some((k) => k.value.toLowerCase() === value.toLowerCase() && k.type === keywordType.value)) target.push({ value: value.slice(0, 100), type: keywordType.value });
  });
  keywordDraft.value = '';
}
async function addHandles() {
  const handles = handlesDraft.value.split(/[\s,]+/).map((h) => h.replace(/^@/, '').trim()).filter(Boolean).slice(0, 100);
  if (!handles.length) return;
  await load('users', props.urls.users, { handles }, (data) => {
    data.users.forEach((u) => { if (targeting.similar_followers.length < 100 && !targeting.similar_followers.some((x) => x.id === u.id)) targeting.similar_followers.push(u); });
    missingHandles.value = data.missing;
    handlesDraft.value = data.missing.join(' ');
  }, 'post');
}
function toggleTweet(p) {
  const i = ad.tweets.findIndex((x) => x.id === p.id);
  if (i >= 0) ad.tweets.splice(i, 1); else if (ad.tweets.length < 50) ad.tweets.push(p);
}
function openGallery() {
  mediaError.value = '';
  nextTick(() => picker.value && picker.value.open());
}
function onPicked(assets) {
  for (const asset of assets || []) {
    if (!['image', 'video'].includes(asset.type)) { mediaError.value = t('errors.media_type'); continue; }
    if (asset.type === 'video' && ad.media.length) { mediaError.value = t('errors.one_video'); continue; }
    if (ad.media.some((m) => m.type === 'video')) { mediaError.value = t('errors.one_video'); continue; }
    if (ad.media.length >= mediaMax.value) break;
    if (!ad.media.some((m) => m.id === asset.id)) ad.media.push(asset);
  }
}
async function runEstimate() {
  estimating.value = true;
  try {
    estimate.value = await api(props.urls.estimate, { objective: objective.value || 'ENGAGEMENTS', targeting: targetingPayload() }, 'post');
  } catch (e) {
    estimate.value = { error: e.message };
  } finally {
    estimating.value = false;
  }
}

function goTo(i) {
  if (i > maxReached.value) return;
  step.value = i;
  window.scrollTo({ top: 0, behavior: 'smooth' });
}
function showStepErrors(i) {
  const key = steps.value[i].key;
  const msgs = validate().filter((v) => v.step === i).map((v) => v.text);
  stepErrors[key] = msgs.length > 0;
  fieldErrors[key] = msgs.join(' ');
}
function next() {
  showStepErrors(step.value);
  if (stepErrors[current.value]) return;
  step.value = Math.min(step.value + 1, steps.value.length - 1);
  maxReached.value = Math.max(maxReached.value, step.value);
  window.scrollTo({ top: 0, behavior: 'smooth' });
}
function back() { step.value = Math.max(0, step.value - 1); }

function targetingPayload() {
  const ids = (list) => list.map((x) => x.id);
  return {
    locations: targeting.locations.map((l) => ({ id: l.id })), languages: ids(targeting.languages), gender: targeting.gender, age: targeting.age,
    platforms: targeting.platforms, platform_versions: ids(targeting.platform_versions), devices: ids(targeting.devices),
    network_operators: ids(targeting.network_operators), app_categories: ids(targeting.app_categories),
    interests: ids(targeting.interests), conversations: ids(targeting.conversations), event: targeting.event ? targeting.event.id : null,
    keywords: targeting.keywords, excluded_keywords: targeting.excluded_keywords,
    own_followers: targeting.own_followers, similar_followers: ids(targeting.similar_followers),
    audiences: ids(targeting.audiences), excluded_audiences: ids(targeting.excluded_audiences), audience_lookalike: targeting.audience_lookalike,
    audience_expansion: targeting.audience_expansion,
    engagement: { type: targeting.engagement.type, campaign_ids: targeting.engagement.type ? ids(targeting.engagement.campaigns) : [], organic: !!targeting.engagement.type && targeting.engagement.organic },
  };
}
function payload() {
  const li = lineitem;
  const s = spec.value;
  return {
    idempotency_key: idempotencyKey.value,
    objective: objective.value,
    status: status.value,
    campaign: campaign.mode === 'existing'
      ? { mode: 'existing', id: campaign.id }
      : { mode: 'new', name: campaign.name || null, funding_instrument_id: campaign.funding_instrument_id, daily_budget: campaign.daily_budget, total_budget: campaign.total_budget || null, purchase_order_number: campaign.purchase_order_number || null },
    lineitem: {
      name: li.name || null, goal: li.goal, bid_strategy: li.bid_strategy, bid_amount: li.bid_strategy !== 'AUTO' ? li.bid_amount : null,
      pay_by: payByOptions.value.length ? effectivePayBy.value : null, placement_mode: li.placement_mode, placements: li.placement_mode === 'custom' ? li.placements : [],
      daily_budget: li.daily_budget || null, total_budget: li.total_budget || null, standard_delivery: li.standard_delivery,
      start_time: li.start_time || null, end_time: li.end_time || null,
      frequency_cap: s.frequency ? (li.frequency_cap || null) : null, frequency_days: s.frequency && li.frequency_cap ? li.frequency_days : null,
      web_event_tag: li.goal === 'WEBSITE_CONVERSIONS' ? li.web_event_tag : null,
      impression_tag: li.impression_tag || null, click_tracker: li.click_tracker || null,
      targeting: targetingPayload(),
    },
    app: s.needs_app ? { ios_id: app.ios_id || null, android_id: app.android_id || null, country_code: app.country_code || null } : {},
    ad: ad.source === 'existing'
      ? { name: ad.name || null, as_user_id: ad.as_user_id, source: 'existing', tweet_ids: ad.tweets.map((x) => x.id) }
      : {
        name: ad.name || null, as_user_id: ad.as_user_id, source: 'new', text: ad.text.trim() || null, media_ids: ad.media.map((m) => m.id), card: ad.card,
        headline: ad.card === 'website' ? ad.headline : null, url: ad.card !== 'app' ? (ad.url || null) : null,
        app_label: ad.card === 'app' ? ad.app_label : null, conversation_settings: ad.conversation_settings,
      },
  };
}

async function submit() {
  if (submitting.value) return;
  const problems = validate();
  if (problems.length) {
    problems.forEach((p) => showStepErrors(p.step));
    return goTo(problems[0].step);
  }
  submitting.value = true;
  result.value = null;
  try {
    const { data } = await window.axios.post(props.urls.submit, payload());
    result.value = data;
    if (data.success) clearDraft();
  } catch (e) {
    const data = (e.response && e.response.data) || {};
    if (data.errors && !Array.isArray(data.errors)) {
      const stepFor = (f) => (f.startsWith('campaign') || f === 'objective' ? 0 : f.startsWith('lineitem.targeting') ? 2 : f.startsWith('lineitem') || f.startsWith('app') ? 1 : 3);
      Object.entries(data.errors).forEach(([field, msgs]) => {
        const key = steps.value[stepFor(field)].key;
        fieldErrors[key] = [fieldErrors[key], ...msgs].filter(Boolean).join(' ');
        stepErrors[key] = true;
      });
      result.value = { success: false, message: Object.values(data.errors).flat().join(' ') };
    } else {
      result.value = { success: false, ...data, message: data.message || (e.response ? t('client.server_error') : t('client.network')) };
    }
  } finally {
    submitting.value = false;
  }
}
function startAnother() {
  result.value = null;
  idempotencyKey.value = newKey();
  ad.media = [];
  ad.tweets = [];
  ad.text = '';
  ad.name = '';
  step.value = 3;
}

// ---------------------------------------------------------------- drafts
const draftKey = computed(() => `x-ad-builder:${props.accountId}`);
function snapshot() {
  return JSON.parse(JSON.stringify({ step: step.value, maxReached: maxReached.value, objective: objective.value, status: status.value, campaign, lineitem, targeting, app, ad, key: idempotencyKey.value }));
}
let saveTimer = null;
function saveDraft() {
  clearTimeout(saveTimer);
  saveTimer = setTimeout(() => {
    try {
      localStorage.setItem(draftKey.value, JSON.stringify({ ...snapshot(), savedAt: new Date().toISOString() }));
      savedAt.value = new Date().toLocaleTimeString(i18n.intl, { hour: '2-digit', minute: '2-digit' });
    } catch (e) { /* private mode - drafts just aren't kept */ }
  }, 800);
}
function clearDraft() {
  try { localStorage.removeItem(draftKey.value); } catch (e) { /* ignore */ }
  savedAt.value = '';
}
function readDraft() {
  try { return JSON.parse(localStorage.getItem(draftKey.value) || 'null'); } catch (e) { return null; }
}
function resumeDraft() {
  const d = readDraft();
  draftOffer.value = null;
  if (!d) return;
  objective.value = d.objective && boot.value.objectives[d.objective] ? d.objective : null;
  status.value = d.status === 'ACTIVE' ? 'ACTIVE' : 'PAUSED';
  Object.assign(campaign, d.campaign);
  Object.assign(lineitem, d.lineitem);
  Object.assign(targeting, d.targeting);
  Object.assign(app, d.app || {});
  Object.assign(ad, d.ad);
  if (ad.as_user_id && !boot.value.promotable_users.some((u) => u.id === ad.as_user_id)) ad.as_user_id = null;
  idempotencyKey.value = d.key || newKey();
  step.value = d.step || 0;
  maxReached.value = d.maxReached || 0;
  if (campaign.mode === 'existing') loadCampaigns();
}
function discardDraft() {
  clearDraft();
  draftOffer.value = null;
}

// ---------------------------------------------------------------- watchers
watch(current, (c) => {
  if (c === 'audience') {
    ensure('languages', props.urls.languages);
    ensure('audiences', props.urls.audiences);
    ensure('platforms', props.urls.platforms, {}, (data) => { lists.platforms = data.platforms; lists.versions = data.versions; });
  }
});
watch(() => targeting.engagement.type, (v) => { if (v) loadCampaigns(); });
watch(() => [ad.source, ad.as_user_id], () => { lists.tweets = []; tweetsCursor.value = null; if (ad.source === 'existing') loadTweets(); });
watch(() => ad.card, (c) => { if (c !== 'none' && ad.media.length > 1) ad.media.splice(1); });
watch(() => JSON.stringify(targetingPayload()), () => { estimate.value = null; });
watch(() => JSON.stringify(snapshot()), () => { if (boot.value && !(result.value && result.value.success)) saveDraft(); });
watch(() => JSON.stringify(validate()), () => {
  steps.value.forEach((s, i) => { if (stepErrors[s.key] && !validate().some((v) => v.step === i)) { stepErrors[s.key] = false; fieldErrors[s.key] = ''; } });
});

onMounted(async () => {
  try {
    boot.value = await api(props.urls.bootstrap);
  } catch (e) {
    bootError.value = e.message;
    return;
  }
  lineitem.start_time = nowInAccountTz();
  if (usableFunding.value.length === 1) campaign.funding_instrument_id = usableFunding.value[0].id;
  const full = boot.value.promotable_users.filter((u) => u.type === 'FULL');
  if (full.length === 1 || boot.value.promotable_users.length === 1) ad.as_user_id = (full[0] || boot.value.promotable_users[0]).id;
  const draft = readDraft();
  if (draft) draftOffer.value = { savedAt: new Date(draft.savedAt).toLocaleString(i18n.intl, { dateStyle: 'medium', timeStyle: 'short' }) };
});
</script>

<style scoped src="../facebook/builder.css"></style>
<style scoped>
.xb { --brand: #0f1419; --brand-2: #1d9bf0; --brand-soft: #e8f5fd; }
.xb-head-icon { background: #0f1419; color: #fff; }
.xb-head-icon svg { width: 20px; height: 20px; }
.xb-types { display: flex; flex-wrap: wrap; gap: 6px; margin-bottom: 8px; }
.fb-chip-btn.is-on { background: var(--ink); color: #fff; border-color: var(--ink); }
.xb-inline { margin-bottom: 8px; }
.xb-pt { padding-top: 28px; }
.fb-chips { display: flex; flex-wrap: wrap; gap: 6px; }
.fb-chips .fb-chip { display: inline-flex; align-items: center; gap: 4px; max-width: 100%; padding: 3px 4px 3px 9px; border-radius: 999px; background: #EEF3FF; color: #0039CC; font-size: .78rem; font-weight: 600; }
.fb-chips .fb-chip.is-exclude { background: #FEF2F2; color: #B91C1C; }
.fb-chips .fb-chip button { border: 0; background: transparent; color: inherit; padding: 0 2px; line-height: 1; font-size: 15px; opacity: .7; }
.xb-chip-type { opacity: .65; font-size: .7rem; margin-inline-start: 4px; }
.xb-kw { display: grid; gap: 8px; }
.xb-kw-row { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; }
.xb-kw-row select { width: auto; min-width: 160px; }
.xb-details summary { cursor: pointer; font-weight: 600; color: var(--ink); }
.xb-posts { display: grid; gap: 8px; margin-top: 8px; max-height: 420px; overflow: auto; }
.xb-post { display: flex; align-items: center; gap: 10px; padding: 10px; border: 1px solid var(--line); border-radius: 12px; background: #fff; text-align: start; transition: border-color .15s ease, background .15s ease; }
.xb-post:hover { border-color: #b9c5cf; }
.xb-post.on { border-color: var(--brand-2); background: var(--brand-soft); }
.xb-post img { width: 54px; height: 54px; border-radius: 8px; object-fit: cover; flex-shrink: 0; }
.xb-post-body { display: flex; flex-direction: column; gap: 3px; min-width: 0; flex: 1; font-size: .84rem; color: var(--text); }
.xb-post-body small { color: var(--muted, #6b7280); font-size: .72rem; }
.xb-post > .bx { font-size: 20px; color: var(--brand-2); flex-shrink: 0; }
.xb-tweet { display: flex; gap: 10px; padding: 12px; border: 1px solid #eff3f4; border-radius: 16px; background: #fff; color: #0f1419; }
.xb-av { flex-shrink: 0; width: 40px; height: 40px; border-radius: 50%; display: grid; place-items: center; background: #0f1419; color: #fff; }
.xb-av svg { width: 18px; height: 18px; }
.xb-tweet-main { min-width: 0; flex: 1; }
.xb-tweet-h { font-size: .85rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.xb-tweet-h span { color: #536471; }
.xb-tweet-text { margin: 2px 0 8px; font-size: .86rem; line-height: 1.35; white-space: pre-wrap; word-break: break-word; max-height: 8em; overflow: hidden; }
.xb-tweet-media { border: 1px solid #cfd9de; border-radius: 14px; overflow: hidden; }
.xb-tweet-media img, .xb-tweet-ph { display: block; width: 100%; aspect-ratio: 16 / 9; object-fit: cover; }
.xb-tweet-ph { display: grid; place-items: center; font-size: 34px; color: #8b98a5; background: #f7f9f9; }
.xb-card-foot { display: flex; flex-direction: column; padding: 8px 10px; border-top: 1px solid #cfd9de; font-size: .8rem; }
.xb-card-foot small { color: #536471; }
.xb-card-foot.is-app { flex-direction: row; justify-content: space-between; align-items: center; }
.xb-card-foot.is-app b { padding: 4px 12px; border-radius: 999px; border: 1px solid #cfd9de; font-size: .76rem; }
.xb-tweet-acts { display: flex; justify-content: space-between; max-width: 260px; margin-top: 8px; color: #536471; font-size: 17px; }
.xb-ad-label { display: inline-flex; align-items: center; gap: 4px; margin-top: 6px; font-size: .72rem; color: #536471; }
.xb-sum { list-style: none; margin: 0; padding: 0; display: grid; gap: 7px; font-size: .82rem; color: var(--text); }
.xb-sum li { display: flex; gap: 7px; align-items: flex-start; line-height: 1.35; }
.xb-sum li i { color: var(--brand-2); font-size: 15px; margin-top: 1px; }
.xb-estimate { margin-top: 12px; padding-top: 12px; border-top: 1px dashed var(--line); }
.xb-est-v { margin: 8px 0 0; font-size: 1.1rem; font-weight: 800; color: var(--ink); text-align: center; }
.xb-est-v small { font-size: .75rem; font-weight: 500; color: #536471; }
</style>
