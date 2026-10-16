<template>
  <div class="fb tt" :dir="i18n.dir">

    <!-- ===== Header (TikTok-style hero) ===== -->
    <header class="tt-hero">
      <span class="tt-hero-glow is-cyan" aria-hidden="true"></span>
      <span class="tt-hero-glow is-pink" aria-hidden="true"></span>
      <div class="tt-hero-main">
        <span class="tt-logo" aria-hidden="true"><i class="bx bxl-tiktok"></i></span>
        <div class="tt-hero-text">
          <span class="tt-eyebrow"><i class="bx bxs-zap"></i> {{ t('header.eyebrow') }}</span>
          <h4>{{ t('header.title') }}</h4>
          <p>{{ t('header.subtitle') }}</p>
        </div>
      </div>
      <div class="tt-hero-side">
        <div v-if="boot" class="tt-hero-acct" :title="boot.account.id">
          <span class="tt-hero-acct-ic"><i class="bx bx-briefcase-alt-2"></i></span>
          <span><small>{{ t('header.ad_account') }}</small><strong>{{ boot.account.name }}</strong><small dir="ltr">{{ boot.account.currency }} · {{ boot.account.timezone }}</small></span>
        </div>
        <a :href="urls.campaignsList" class="tt-hero-btn"><i class="bx bx-list-ul"></i> {{ t('header.campaigns') }}</a>
      </div>
      <ul class="tt-hero-flow">
        <li><i class="bx bx-flag"></i> {{ t('header.flow_campaign') }}</li>
        <li><i class="bx bx-target-lock"></i> {{ t('header.flow_targeting') }}</li>
        <li><i class="bx bx-video"></i> {{ t('header.flow_creative') }}</li>
        <li><i class="bx bx-cloud-upload"></i> {{ t('header.flow_drafts') }}</li>
      </ul>
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
      <div v-if="!boot.account.active" class="fb-alert is-warn"><i class="bx bx-error"></i> {{ t('boot.inactive') }}</div>
      <div v-if="!boot.identities.length" class="fb-alert is-warn"><i class="bx bx-user-x"></i> {{ t('boot.no_identity') }}</div>

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
                <button v-for="c in filteredCampaigns" :key="c.id" type="button" class="fb-pick" :class="{ on: campaign.id === c.id, 'is-off': !c.supported }"
                        :disabled="!c.supported" @click="chooseCampaign(c)">
                  <span class="fb-pick-ic"><i class="bx" :class="c.supported ? objectives[c.objective].icon : 'bx-block'"></i></span>
                  <span class="fb-pick-main">
                    <strong>{{ c.name }}</strong>
                    <small>{{ c.supported ? objectives[c.objective].label : t('campaign.unsupported', { objective: c.objective }) }} · {{ known('status', c.status, humanize(c.status)) }}<template v-if="c.cbo"> · {{ t('campaign.cbo_short') }}</template></small>
                  </span>
                  <i v-if="campaign.id === c.id" class="bx bxs-check-circle fb-pick-check"></i>
                </button>
                <div v-if="!filteredCampaigns.length" class="fb-mini-empty">
                  <i class="bx bx-folder"></i><span>{{ campaignQuery ? t('campaign.no_match') : t('campaign.none_yet') }}</span>
                  <button type="button" class="fb-link" @click="setCampaignMode('new')">{{ t('campaign.create_link') }}</button>
                </div>
              </div>
            </template>

            <template v-else>
              <label class="fb-label">{{ t('campaign.objective') }}</label>
              <div class="fb-goals">
                <button v-for="(o, key) in objectives" :key="key" type="button" class="fb-goal" :class="{ on: objective === key }" @click="setObjective(key)">
                  <span class="fb-goal-ic"><i class="bx" :class="o.icon"></i></span>
                  <strong>{{ o.label }}</strong>
                  <small>{{ o.summary }}</small>
                </button>
              </div>
              <div v-if="objective" class="fb-guide"><i class="bx bx-bulb"></i><span><strong>{{ objectives[objective].label }}:</strong> {{ t('objectives.' + objective + '.guidance') }}</span></div>

              <div class="fb-grid-2">
                <div>
                  <label class="fb-label" for="ttCampName">{{ t('campaign.name') }}</label>
                  <input id="ttCampName" v-model.trim="campaign.name" class="form-control" maxlength="512" :placeholder="objective ? objectives[objective].label + ' - ' + today : t('campaign.name_placeholder')">
                </div>
                <div v-if="objective === 'APP_PROMOTION'">
                  <label class="fb-label" for="ttAppType">{{ t('campaign.app_promotion_type') }}</label>
                  <select id="ttAppType" v-model="campaign.app_promotion_type" class="form-control">
                    <option v-for="a in boot.options.app_promotion_types" :key="a" :value="a">{{ t('campaign.app_types.' + a) }}</option>
                  </select>
                </div>
              </div>

              <label class="fb-label mt-3">{{ t('campaign.special_label') }} <span class="fb-opt">{{ t('campaign.if_applies') }}</span></label>
              <div class="fb-place-grid">
                <label v-for="s in boot.options.special_industries" :key="s" class="fb-check"><input v-model="campaign.special_industries" type="checkbox" :value="s"> {{ t('campaign.special.' + s) }}</label>
              </div>
              <p v-if="campaign.special_industries.length" class="fb-hint is-warn"><i class="bx bx-shield-quarter"></i> {{ t('campaign.special_warn') }}</p>

              <label class="fb-toggle">
                <input v-model="campaign.budget_optimize_on" type="checkbox">
                <span><strong>{{ t('campaign.cbo_title') }}</strong><small>{{ t('campaign.cbo_desc') }}</small></span>
              </label>
              <div v-if="campaign.budget_optimize_on" class="fb-grid-2">
                <div>
                  <label class="fb-label">{{ t('common.budget_type') }}</label>
                  <div class="fb-seg fb-seg-full">
                    <button type="button" :class="{ on: campaign.budget_mode === 'BUDGET_MODE_DAY' }" @click="campaign.budget_mode = 'BUDGET_MODE_DAY'">{{ t('common.daily') }}</button>
                    <button type="button" :class="{ on: campaign.budget_mode === 'BUDGET_MODE_TOTAL' }" @click="campaign.budget_mode = 'BUDGET_MODE_TOTAL'">{{ t('common.lifetime') }}</button>
                  </div>
                </div>
                <div>
                  <label class="fb-label" for="ttCampBudget">{{ t('common.budget', { currency: currency }) }}</label>
                  <input id="ttCampBudget" v-model.number="campaign.budget" type="number" min="1" step="0.01" class="form-control">
                </div>
              </div>
            </template>
            <p v-if="fieldErrors.campaign" class="fb-field-err">{{ fieldErrors.campaign }}</p>
          </div>

          <!-- ============ 2. Ad group: delivery ============ -->
          <div v-show="current === 'adgroup'" class="fb-card">
            <div class="fb-card-h"><h5>{{ t('adgroup.title') }}</h5></div>
            <p class="fb-sub">{{ t('adgroup.sub') }}</p>

            <div class="fb-grid-2">
              <div>
                <label class="fb-label" for="ttAgName">{{ t('adgroup.name') }}</label>
                <input id="ttAgName" v-model.trim="adgroup.name" class="form-control" maxlength="512" :placeholder="t('adgroup.name_placeholder', { date: today })">
              </div>
              <div v-if="objective">
                <label class="fb-label" for="ttGoal">{{ t('adgroup.optimize_for') }} <i class="bx bx-help-circle fb-tip" :title="t('adgroup.optimize_tip')"></i></label>
                <select id="ttGoal" v-model="adgroup.optimization_goal" class="form-control">
                  <option v-for="(g, key) in objectives[objective].goals" :key="key" :value="key">{{ t('goals.' + key) }}</option>
                </select>
              </div>
            </div>

            <!-- Tracking / promoted object -->
            <div v-if="spec && (spec.needs_pixel || spec.needs_app)" class="fb-group">
              <h6><i class="bx bx-target-lock"></i> {{ spec.needs_pixel ? t('adgroup.pixel_title') : t('adgroup.app_title') }}</h6>
              <div v-if="spec.needs_pixel" class="fb-grid-2">
                <div>
                  <label class="fb-label" for="ttPixel">{{ t('adgroup.pixel') }}</label>
                  <select id="ttPixel" v-model="adgroup.pixel_id" class="form-control" :disabled="lists.loading.pixels">
                    <option :value="null">{{ lists.loading.pixels ? t('common.loading') : t('adgroup.choose_pixel') }}</option>
                    <option v-for="p in lists.pixels" :key="p.id" :value="p.id">{{ p.name }}</option>
                  </select>
                  <p v-if="lists.errors.pixels" class="fb-field-err">{{ lists.errors.pixels }}</p>
                  <p v-else-if="!lists.loading.pixels && !lists.pixels.length" class="fb-hint is-warn"><i class="bx bx-error"></i> {{ t('adgroup.no_pixels') }}</p>
                </div>
                <div>
                  <label class="fb-label" for="ttEvent">{{ t('adgroup.event') }}</label>
                  <select id="ttEvent" v-model="adgroup.optimization_event" class="form-control" :disabled="!selectedPixel">
                    <option :value="null">{{ t('adgroup.choose_event') }}</option>
                    <option v-for="e in (selectedPixel ? selectedPixel.events : [])" :key="e.code" :value="e.code">{{ known('events', e.code, e.name || humanize(e.code)) }}</option>
                  </select>
                  <p v-if="selectedPixel && !selectedPixel.events.length" class="fb-hint is-warn"><i class="bx bx-error"></i> {{ t('adgroup.no_events') }}</p>
                </div>
              </div>
              <div v-if="spec.needs_app">
                <label class="fb-label" for="ttApp">{{ t('adgroup.app') }}</label>
                <select id="ttApp" v-model="adgroup.app_id" class="form-control" :disabled="lists.loading.apps">
                  <option :value="null">{{ lists.loading.apps ? t('common.loading') : t('adgroup.choose_app') }}</option>
                  <option v-for="a in lists.apps" :key="a.id" :value="a.id">{{ a.name }} ({{ a.platform || '-' }})</option>
                </select>
                <p v-if="lists.errors.apps" class="fb-field-err">{{ lists.errors.apps }}</p>
                <p v-else-if="!lists.loading.apps && !lists.apps.length" class="fb-hint is-warn"><i class="bx bx-error"></i> {{ t('adgroup.no_apps') }}</p>
              </div>
            </div>

            <!-- Placements -->
            <div class="fb-group">
              <h6><i class="bx bx-layout"></i> {{ t('adgroup.placements') }}</h6>
              <div class="fb-seg">
                <button type="button" :class="{ on: adgroup.placement_type === 'AUTOMATIC' }" @click="adgroup.placement_type = 'AUTOMATIC'">{{ t('adgroup.automatic') }}</button>
                <button type="button" :class="{ on: adgroup.placement_type === 'NORMAL' }" @click="adgroup.placement_type = 'NORMAL'">{{ t('adgroup.manual') }}</button>
              </div>
              <p class="fb-hint"><i class="bx bx-info-circle"></i> {{ adgroup.placement_type === 'AUTOMATIC' ? t('adgroup.automatic_hint') : t('adgroup.manual_hint') }}</p>
              <div v-if="adgroup.placement_type === 'NORMAL'" class="fb-place-grid">
                <label v-for="p in boot.options.placements" :key="p" class="fb-check"><input v-model="adgroup.placements" type="checkbox" :value="p"><i class="bx" :class="placementIcon(p)"></i> {{ t('placements.' + p) }}</label>
              </div>
              <label v-if="searchEligible" class="fb-check mt-2"><input v-model="adgroup.search_result_enabled" type="checkbox"> {{ t('adgroup.search_results') }}</label>
              <div v-if="tiktokPlacement" class="fb-place-grid mt-2">
                <label class="fb-check is-sm"><input v-model="adgroup.comment_disabled" type="checkbox"> {{ t('adgroup.comment_disabled') }}</label>
                <label class="fb-check is-sm"><input v-model="adgroup.video_download_disabled" type="checkbox"> {{ t('adgroup.download_disabled') }}</label>
                <label class="fb-check is-sm"><input v-model="adgroup.share_disabled" type="checkbox"> {{ t('adgroup.share_disabled') }}</label>
              </div>
              <div v-if="tiktokPlacement" class="mt-2">
                <label class="fb-label" for="ttSafety">{{ t('adgroup.brand_safety') }}</label>
                <select id="ttSafety" v-model="adgroup.brand_safety_type" class="form-control">
                  <option v-for="b in boot.options.brand_safety" :key="b" :value="b">{{ t('brand_safety.' + b) }}</option>
                </select>
              </div>
            </div>

            <!-- Budget & schedule -->
            <div class="fb-group">
              <h6><i class="bx bx-wallet"></i> {{ t('adgroup.budget_schedule') }}</h6>
              <p v-if="campaignCbo" class="fb-hint"><i class="bx bx-info-circle"></i> {{ t('adgroup.cbo_note') }}</p>
              <div v-else class="fb-grid-2">
                <div>
                  <label class="fb-label">{{ t('common.budget_type') }}</label>
                  <div class="fb-seg fb-seg-full">
                    <button type="button" :class="{ on: adgroup.budget_mode === 'BUDGET_MODE_DAY' }" @click="adgroup.budget_mode = 'BUDGET_MODE_DAY'">{{ t('common.daily') }}</button>
                    <button type="button" :class="{ on: adgroup.budget_mode === 'BUDGET_MODE_TOTAL' }" @click="adgroup.budget_mode = 'BUDGET_MODE_TOTAL'">{{ t('common.lifetime') }}</button>
                  </div>
                </div>
                <div>
                  <label class="fb-label" for="ttAgBudget">{{ t('common.budget', { currency: currency }) }}</label>
                  <input id="ttAgBudget" v-model.number="adgroup.budget" type="number" min="1" step="0.01" class="form-control">
                </div>
              </div>
              <div class="fb-grid-2 mt-2">
                <div>
                  <label class="fb-label" for="ttStart">{{ t('adgroup.start') }}</label>
                  <input id="ttStart" v-model="adgroup.schedule_start" type="datetime-local" class="form-control">
                </div>
                <div>
                  <label class="fb-label" for="ttEnd">{{ t('adgroup.end') }} <span v-if="!needsEnd" class="fb-opt">{{ t('common.optional') }}</span></label>
                  <input id="ttEnd" v-model="adgroup.schedule_end" type="datetime-local" class="form-control" :min="adgroup.schedule_start">
                </div>
              </div>
              <p class="fb-hint"><i class="bx bx-time-five"></i> {{ t('adgroup.timezone', { tz: boot.account.timezone }) }}</p>

              <label class="fb-switch mt-2"><input v-model="adgroup.dayparting.enabled" type="checkbox"><span>{{ t('adgroup.dayparting') }}</span></label>
              <div v-if="adgroup.dayparting.enabled" class="tt-daypart">
                <div class="tt-days">
                  <button v-for="d in 7" :key="d" type="button" class="fb-chip-btn" :class="{ 'is-on': adgroup.dayparting.days.includes(d - 1) }" @click="toggleDay(d - 1)">{{ t('days.' + (d - 1)) }}</button>
                </div>
                <div class="fb-grid-2">
                  <div><label class="fb-label">{{ t('adgroup.from') }}</label><select v-model.number="adgroup.dayparting.from" class="form-control"><option v-for="h in 24" :key="h" :value="h - 1">{{ hourLabel(h - 1) }}</option></select></div>
                  <div><label class="fb-label">{{ t('adgroup.to') }}</label><select v-model.number="adgroup.dayparting.to" class="form-control"><option v-for="h in 24" :key="h" :value="h" :disabled="h <= adgroup.dayparting.from">{{ hourLabel(h) }}</option></select></div>
                </div>
              </div>
            </div>

            <!-- Bidding -->
            <div v-if="adgroup.optimization_goal" class="fb-group">
              <h6><i class="bx bx-trending-up"></i> {{ t('adgroup.bidding') }} <small class="fb-muted">· {{ t('adgroup.billed_by', { event: t('billing.' + billing) }) }}</small></h6>
              <div class="fb-seg">
                <button type="button" :class="{ on: adgroup.bid_type === 'BID_TYPE_NO_BID' }" @click="adgroup.bid_type = 'BID_TYPE_NO_BID'">{{ t('adgroup.max_delivery') }}</button>
                <button type="button" :class="{ on: adgroup.bid_type === 'BID_TYPE_CUSTOM' }" @click="adgroup.bid_type = 'BID_TYPE_CUSTOM'">{{ billing === 'OCPM' ? t('adgroup.cost_cap') : t('adgroup.bid_cap') }}</button>
              </div>
              <p class="fb-hint"><i class="bx bx-info-circle"></i> {{ adgroup.bid_type === 'BID_TYPE_NO_BID' ? t('adgroup.max_delivery_hint') : (billing === 'OCPM' ? t('adgroup.cost_cap_hint') : t('adgroup.bid_cap_hint', { event: t('billing.' + billing) })) }}</p>
              <div v-if="adgroup.bid_type === 'BID_TYPE_CUSTOM'" class="fb-grid-2">
                <div>
                  <label class="fb-label" for="ttBid">{{ t('adgroup.bid', { currency: currency }) }}</label>
                  <input id="ttBid" v-model.number="adgroup.bid" type="number" min="0.01" step="0.01" class="form-control">
                </div>
                <div>
                  <label class="fb-label">{{ t('adgroup.pacing') }}</label>
                  <div class="fb-seg fb-seg-full">
                    <button type="button" :class="{ on: adgroup.pacing === 'PACING_MODE_SMOOTH' }" @click="adgroup.pacing = 'PACING_MODE_SMOOTH'">{{ t('adgroup.standard') }}</button>
                    <button type="button" :class="{ on: adgroup.pacing === 'PACING_MODE_FAST' }" @click="adgroup.pacing = 'PACING_MODE_FAST'">{{ t('adgroup.accelerated') }}</button>
                  </div>
                </div>
              </div>
              <div v-if="objective === 'REACH'" class="fb-grid-2 mt-2">
                <div><label class="fb-label" for="ttFreq">{{ t('adgroup.frequency') }}</label><input id="ttFreq" v-model.number="adgroup.frequency" type="number" min="1" max="1000" class="form-control"></div>
                <div><label class="fb-label" for="ttFreqDays">{{ t('adgroup.frequency_days') }}</label><input id="ttFreqDays" v-model.number="adgroup.frequency_schedule" type="number" min="1" max="30" class="form-control"></div>
              </div>
            </div>
            <p v-if="fieldErrors.adgroup" class="fb-field-err">{{ fieldErrors.adgroup }}</p>
          </div>

          <!-- ============ 3. Audience ============ -->
          <div v-show="current === 'audience'" class="fb-card">
            <div class="fb-card-h"><h5>{{ t('audience.title') }}</h5></div>
            <p class="fb-sub">{{ t('audience.sub') }}</p>

            <label class="fb-label">{{ t('audience.locations') }}</label>
            <search-picker v-model="targeting.locations" :fetcher="searchLocations" item-key="id" :max="3000" :placeholder="t('audience.search_locations')">
              <template #chip="{ item }">{{ item.name }}<template v-if="item.parent">, {{ item.parent }}</template></template>
              <template #option="{ item }">
                <span class="sp-opt-main"><strong>{{ item.name }}</strong> <small class="fb-muted">{{ [item.parent, item.region_code].filter(Boolean).join(' · ') }}</small></span>
                <span class="fb-tag">{{ known('geo', item.type, humanize(item.type)) }}</span>
              </template>
            </search-picker>
            <p class="fb-hint"><i class="bx bx-info-circle"></i> {{ t('audience.locations_hint') }}</p>

            <div class="fb-grid-2 mt-3">
              <div>
                <label class="fb-label">{{ t('audience.age') }}</label>
                <div class="fb-place-grid">
                  <label v-for="a in boot.options.age_groups" :key="a" class="fb-check is-sm"><input v-model="targeting.age_groups" type="checkbox" :value="a"> {{ t('ages.' + a) }}</label>
                </div>
                <small class="fb-muted">{{ targeting.age_groups.length ? '' : t('audience.all_ages') }}</small>
              </div>
              <div>
                <label class="fb-label">{{ t('audience.gender') }}</label>
                <div class="fb-seg fb-seg-full">
                  <button v-for="g in boot.options.genders" :key="g" type="button" :class="{ on: targeting.gender === g }" @click="targeting.gender = g">{{ t('genders.' + g) }}</button>
                </div>
              </div>
            </div>

            <label class="fb-label mt-3">{{ t('audience.languages') }} <span class="fb-opt">{{ t('common.optional') }}</span></label>
            <search-picker v-model="targeting.languages" :options="lists.languages" item-key="code" :min-chars="1" :placeholder="lists.loading.languages ? t('common.loading') : t('audience.search_languages')" />
            <p v-if="lists.errors.languages" class="fb-field-err">{{ lists.errors.languages }}</p>

            <!-- Interests & behaviors -->
            <div class="fb-group">
              <h6><i class="bx bx-heart"></i> {{ t('audience.interests_title') }}</h6>
              <p class="fb-muted">{{ t('audience.interests_sub') }}</p>
              <div class="tt-types">
                <button type="button" class="fb-chip-btn" :class="{ 'is-on': !interestType }" @click="interestType = null">{{ t('audience.all_types') }}</button>
                <button v-for="ty in boot.options.interest_types" :key="ty" type="button" class="fb-chip-btn" :class="{ 'is-on': interestType === ty }" :disabled="!interestTypeAllowed(ty)" :title="interestTypeAllowed(ty) ? '' : t('interest_types_blocked.' + ty)" @click="interestType = ty">{{ t('interest_types.' + ty) }}</button>
              </div>
              <search-picker v-model="targeting.interests" :fetcher="searchInterests" item-key="key" :max="500" :placeholder="t('audience.search_interests')">
                <template #chip="{ item }">{{ item.name }} <small class="tt-chip-type">{{ t('interest_types.' + item.type) }}</small></template>
                <template #option="{ item }">
                  <span class="sp-opt-main"><strong>{{ item.name }}</strong></span>
                  <span class="fb-tag">{{ t('interest_types.' + item.type) }}</span>
                </template>
              </search-picker>
              <p v-for="w in interestWarnings" :key="w" class="fb-hint is-warn"><i class="bx bx-error"></i> {{ w }}</p>
              <div v-if="hasType('VIDEO_INTERACTION')" class="fb-grid-2 mt-2">
                <div>
                  <label class="fb-label">{{ t('audience.video_actions') }}</label>
                  <div class="fb-place-grid">
                    <label v-for="va in boot.options.video_actions" :key="va" class="fb-check is-sm"><input v-model="targeting.video_actions" type="checkbox" :value="va"> {{ t('video_actions.' + va) }}</label>
                  </div>
                </div>
                <div>
                  <label class="fb-label">{{ t('audience.action_period') }}</label>
                  <select v-model.number="targeting.action_period" class="form-control">
                    <option v-for="p in boot.options.action_periods" :key="p" :value="p">{{ p ? t('audience.days', { n: p }) : t('audience.any_time') }}</option>
                  </select>
                </div>
              </div>
            </div>

            <!-- Custom & lookalike audiences -->
            <div class="fb-group">
              <h6><i class="bx bx-group"></i> {{ t('audience.custom_title') }}</h6>
              <div v-if="lists.loading.audiences" class="fb-inline-load"><span class="spinner-border spinner-border-sm"></span> {{ t('common.loading') }}</div>
              <p v-else-if="lists.errors.audiences" class="fb-field-err">{{ lists.errors.audiences }}</p>
              <p v-else-if="!usableAudiences.length" class="fb-muted">{{ t('audience.no_custom') }}</p>
              <template v-else>
                <label class="fb-label">{{ t('audience.include') }}</label>
                <search-picker v-model="targeting.include" :options="usableAudiences" :min-chars="0" :placeholder="t('audience.search_audiences')">
                  <template #option="{ item }"><span class="sp-opt-main"><strong>{{ item.name }}</strong> <small v-if="item.size" class="fb-muted">~{{ compact(item.size) }}</small></span><span class="fb-tag">{{ item.type || '-' }}</span></template>
                </search-picker>
                <label class="fb-label mt-2">{{ t('audience.exclude') }}</label>
                <search-picker v-model="targeting.exclude" :options="usableAudiences" :min-chars="0" chip-tone="exclude" :placeholder="t('audience.search_audiences')">
                  <template #option="{ item }"><span class="sp-opt-main"><strong>{{ item.name }}</strong></span><span class="fb-tag">{{ item.type || '-' }}</span></template>
                </search-picker>
              </template>
            </div>

            <!-- Devices -->
            <div class="fb-group">
              <h6><i class="bx bx-mobile"></i> {{ t('audience.devices_title') }}</h6>
              <div class="fb-grid-2">
                <div>
                  <label class="fb-label">{{ t('audience.os') }}</label>
                  <div class="fb-seg fb-seg-full">
                    <button type="button" :class="{ on: !osChoice }" :disabled="!!appPlatform" @click="setOs(null)">{{ t('audience.any') }}</button>
                    <button type="button" :class="{ on: osChoice === 'ANDROID' }" :disabled="!!appPlatform && appPlatform !== 'ANDROID'" @click="setOs('ANDROID')"><i class="bx bxl-android"></i> Android</button>
                    <button type="button" :class="{ on: osChoice === 'IOS' }" :disabled="!!appPlatform && appPlatform !== 'IOS'" @click="setOs('IOS')"><i class="bx bxl-apple"></i> iOS</button>
                  </div>
                  <small v-if="appPlatform" class="fb-muted">{{ t('audience.os_from_app') }}</small>
                </div>
                <div v-if="osChoice">
                  <label class="fb-label" for="ttOsv">{{ t('audience.min_os') }}</label>
                  <select id="ttOsv" v-model="osVersionModel" class="form-control" :disabled="lists.loading.osVersions">
                    <option value="">{{ lists.loading.osVersions ? t('common.loading') : t('audience.any_version') }}</option>
                    <option v-for="v in lists.osVersions" :key="v.version" :value="v.version">{{ v.name }}</option>
                  </select>
                </div>
              </div>
              <label class="fb-label mt-2">{{ t('audience.connection') }}</label>
              <div class="fb-place-grid">
                <label v-for="n in boot.options.network_types" :key="n" class="fb-check is-sm"><input v-model="targeting.network_types" type="checkbox" :value="n"> {{ n === 'WIFI' ? 'Wi-Fi' : n }}</label>
              </div>
              <label class="fb-label mt-2">{{ t('audience.device_models') }} <span class="fb-opt">{{ t('common.optional') }}</span></label>
              <search-picker v-model="targeting.device_models" :fetcher="searchDevices" :placeholder="t('audience.search_devices')" @input="v => v.length && (targeting.device_price.enabled = false)">
                <template #option="{ item }"><span class="sp-opt-main"><strong>{{ item.name }}</strong></span><span v-if="item.os" class="fb-tag">{{ item.os }}</span></template>
              </search-picker>
              <label class="fb-switch mt-2"><input v-model="targeting.device_price.enabled" type="checkbox" :disabled="targeting.device_models.length > 0"><span>{{ t('audience.device_price') }}</span></label>
              <div v-if="targeting.device_price.enabled" class="fb-grid-2">
                <div><label class="fb-label">{{ t('audience.price_min') }}</label><input v-model.number="targeting.device_price.min" type="number" min="0" max="950" step="50" class="form-control"></div>
                <div><label class="fb-label">{{ t('audience.price_max') }}</label><input v-model.number="targeting.device_price.max" type="number" min="50" max="1000" step="50" class="form-control"><small class="fb-muted">{{ t('audience.price_hint') }}</small></div>
              </div>
            </div>

            <!-- Spending power, income, expansion -->
            <div class="fb-group">
              <h6><i class="bx bx-slider-alt"></i> {{ t('audience.more_title') }}</h6>
              <div class="fb-grid-2">
                <div>
                  <label class="fb-label">{{ t('audience.spending_power') }}</label>
                  <div class="fb-seg fb-seg-full">
                    <button type="button" :class="{ on: targeting.spending_power === 'ALL' }" @click="targeting.spending_power = 'ALL'">{{ t('audience.everyone') }}</button>
                    <button type="button" :class="{ on: targeting.spending_power === 'HIGH' }" :disabled="!tiktokPlacement" @click="targeting.spending_power = 'HIGH'">{{ t('audience.high_spenders') }}</button>
                  </div>
                </div>
                <div v-if="targetsUs">
                  <label class="fb-label">{{ t('audience.household_income') }}</label>
                  <div class="fb-place-grid">
                    <label v-for="h in boot.options.household_income" :key="h" class="fb-check is-sm"><input v-model="targeting.household_income" type="checkbox" :value="h"> {{ t('income.' + h) }}</label>
                  </div>
                </div>
              </div>
              <label class="fb-toggle">
                <input v-model="targeting.smart_audience" type="checkbox">
                <span><strong>{{ t('audience.smart_audience') }}</strong><small>{{ t('audience.smart_audience_desc') }}</small></span>
              </label>
              <label class="fb-toggle">
                <input v-model="targeting.smart_interest_behavior" type="checkbox">
                <span><strong>{{ t('audience.smart_interests') }}</strong><small>{{ t('audience.smart_interests_desc') }}</small></span>
              </label>
            </div>
            <p v-if="fieldErrors.audience" class="fb-field-err">{{ fieldErrors.audience }}</p>
          </div>

          <!-- ============ 4. Ad ============ -->
          <div v-show="current === 'ad'" class="fb-card">
            <div class="fb-card-h"><h5>{{ t('ad.title') }}</h5></div>

            <label class="fb-label">{{ t('ad.identity') }}</label>
            <div class="fb-pick-list">
              <button v-for="idt in boot.identities" :key="idt.type + idt.id" type="button" class="fb-pick" :class="{ on: ad.identity_key === idt.type + ':' + idt.id, 'is-off': !idt.available }" :disabled="!idt.available" @click="chooseIdentity(idt)">
                <span class="fb-pick-ic tt-avatar"><img v-if="idt.picture" :src="idt.picture" alt=""><i v-else class="bx bxl-tiktok"></i></span>
                <span class="fb-pick-main"><strong>{{ idt.name }}</strong><small><template v-if="idt.username">@{{ idt.username }} · </template>{{ t('identity_types.' + idt.type) }}<template v-if="!idt.available"> · {{ t('ad.unavailable') }}</template></small></span>
                <i v-if="ad.identity_key === idt.type + ':' + idt.id" class="bx bxs-check-circle fb-pick-check"></i>
              </button>
            </div>

            <label class="fb-label mt-3">{{ t('ad.source') }}</label>
            <div class="fb-seg">
              <button type="button" :class="{ on: ad.source === 'upload' }" @click="ad.source = 'upload'"><i class="bx bx-upload"></i> {{ t('ad.upload') }}</button>
              <button type="button" :class="{ on: ad.source === 'spark' }" :disabled="identity && !identity.can_pull_video" :title="identity && !identity.can_pull_video ? t('ad.spark_blocked') : ''" @click="ad.source = 'spark'"><i class="bx bxl-tiktok"></i> {{ t('ad.spark') }}</button>
            </div>

            <!-- Spark Ad: an existing post -->
            <template v-if="ad.source === 'spark'">
              <p class="fb-hint"><i class="bx bx-info-circle"></i> {{ t('ad.spark_hint') }}</p>
              <p v-if="!identity" class="fb-muted">{{ t('ad.pick_identity_first') }}</p>
              <div v-else-if="lists.loading.posts && !lists.posts.length" class="fb-inline-load"><span class="spinner-border spinner-border-sm"></span> {{ t('common.loading') }}</div>
              <p v-else-if="lists.errors.posts" class="fb-field-err">{{ lists.errors.posts }}</p>
              <template v-else>
                <div class="tt-posts">
                  <button v-for="p in lists.posts" :key="p.id" type="button" class="tt-post" :class="{ on: ad.post && ad.post.id === p.id }" @click="ad.post = p">
                    <img v-if="p.poster" :src="p.poster" alt=""><span v-else class="fb-media-ph"><i class="bx bx-video"></i></span>
                    <small>{{ (p.text || '').slice(0, 60) || t('ad.no_caption') }}</small>
                    <i v-if="ad.post && ad.post.id === p.id" class="bx bxs-check-circle"></i>
                  </button>
                </div>
                <p v-if="!lists.posts.length" class="fb-muted">{{ t('ad.no_posts') }}</p>
                <button v-if="postsCursor" type="button" class="btn btn-sm fb-btn-ghost mt-2" :disabled="lists.loading.posts" @click="loadPosts(true)">{{ t('ad.more_posts') }}</button>
              </template>
            </template>

            <!-- Upload: gallery video / image -->
            <template v-else>
              <div class="fb-seg mt-2">
                <button type="button" :class="{ on: ad.format === 'SINGLE_VIDEO' }" @click="setFormat('SINGLE_VIDEO')"><i class="bx bx-video"></i> {{ t('ad.video') }}</button>
                <button type="button" :class="{ on: ad.format === 'SINGLE_IMAGE' }" @click="setFormat('SINGLE_IMAGE')"><i class="bx bx-image"></i> {{ t('ad.image') }}</button>
              </div>
              <p class="fb-hint"><i class="bx bx-info-circle"></i> {{ ad.format === 'SINGLE_VIDEO' ? t('ad.video_specs') : t('ad.image_specs') }}</p>
              <div class="fb-media-row">
                <div v-if="ad.media" class="fb-media">
                  <img v-if="ad.media.type === 'image' || ad.media.thumbnail_url" :src="ad.media.thumbnail_url || ad.media.url" alt="">
                  <span v-else class="fb-media-ph"><i class="bx bx-play"></i></span>
                  <div><strong>{{ ad.media.original_name }}</strong><small>{{ ad.media.type === 'video' ? t('ad.video') : t('ad.image') }}<template v-if="ad.media.width"> · {{ ad.media.width }}×{{ ad.media.height }}</template></small></div>
                  <button type="button" class="fb-icon-btn" :aria-label="t('common.remove')" @click="ad.media = null"><i class="bx bx-trash"></i></button>
                </div>
                <div v-else class="fb-media-actions">
                  <button type="button" class="btn btn-sm fb-btn-ghost" @click="openGallery('media')"><i class="bx bx-images"></i> {{ t('ad.from_gallery') }}</button>
                </div>
              </div>
              <div v-if="ad.format === 'SINGLE_VIDEO' && ad.media" class="fb-media-row">
                <div v-if="ad.cover" class="fb-media is-sm">
                  <img :src="ad.cover.url" alt="">
                  <div><strong>{{ t('ad.cover') }}</strong><small>{{ ad.cover.original_name }}</small></div>
                  <button type="button" class="fb-icon-btn" :aria-label="t('common.remove')" @click="ad.cover = null"><i class="bx bx-trash"></i></button>
                </div>
                <div v-else class="fb-hint"><i class="bx bx-image"></i> {{ t('ad.cover_auto') }} <button type="button" class="fb-link" @click="openGallery('cover')">{{ t('ad.choose_cover') }}</button></div>
              </div>
              <p v-if="mediaError" class="fb-field-err">{{ mediaError }}</p>
            </template>

            <label class="fb-label mt-3" for="ttText">{{ t('ad.text') }} <span v-if="ad.source === 'spark'" class="fb-opt">{{ t('ad.text_spark') }}</span></label>
            <textarea id="ttText" v-model="ad.text" class="form-control" rows="2" maxlength="100" :placeholder="t('ad.text_placeholder')"></textarea>
            <small class="fb-count" :class="{ 'is-warn': hasEmoji }">{{ hasEmoji ? t('ad.no_emoji') : (ad.text.length + ' / 100') }}</small>

            <div class="fb-grid-2 mt-2">
              <div v-if="spec && spec.url !== 'none'">
                <label class="fb-label" for="ttUrl">{{ t('ad.url') }} <span v-if="spec.url === 'optional'" class="fb-opt">{{ t('common.optional') }}</span></label>
                <input id="ttUrl" v-model.trim="ad.url" type="url" class="form-control" placeholder="https://" dir="ltr">
              </div>
              <div v-if="spec && spec.needs_form">
                <label class="fb-label" for="ttForm">{{ t('ad.lead_form') }}</label>
                <select id="ttForm" v-model="ad.lead_form_id" class="form-control" :disabled="lists.loading.leadForms">
                  <option :value="null">{{ lists.loading.leadForms ? t('common.loading') : t('ad.choose_form') }}</option>
                  <option v-for="f in lists.leadForms" :key="f.id" :value="f.id">{{ f.name }}</option>
                </select>
                <p v-if="lists.errors.leadForms" class="fb-field-err">{{ lists.errors.leadForms }}</p>
                <p v-else-if="!lists.loading.leadForms && !lists.leadForms.length" class="fb-hint is-warn"><i class="bx bx-error"></i> {{ t('ad.no_forms') }}</p>
              </div>
              <div v-if="showCta">
                <label class="fb-label" for="ttCta">{{ t('ad.cta') }}</label>
                <select id="ttCta" v-model="ad.cta" class="form-control">
                  <option v-for="c in boot.options.ctas" :key="c" :value="c">{{ ctaLabel(c) }}</option>
                </select>
              </div>
            </div>
            <p v-if="objective === 'ENGAGEMENT' && adgroup.optimization_goal === 'PAGE_VISIT'" class="fb-hint"><i class="bx bx-user"></i> {{ t('ad.profile_dest') }}</p>

            <details class="fb-group tt-details">
              <summary><i class="bx bx-line-chart"></i> {{ t('ad.tracking') }}</summary>
              <div class="fb-grid-2 mt-2">
                <div>
                  <label class="fb-label" for="ttTrackPixel">{{ t('ad.tracking_pixel') }}</label>
                  <select id="ttTrackPixel" v-model="ad.tracking_pixel_id" class="form-control" :disabled="spec && spec.needs_pixel" @focus="ensurePixels">
                    <option :value="null">{{ spec && spec.needs_pixel ? t('ad.same_pixel') : t('common.none') }}</option>
                    <option v-for="p in lists.pixels" :key="p.id" :value="p.id">{{ p.name }}</option>
                  </select>
                </div>
                <div>
                  <label class="fb-label" for="ttImpUrl">{{ t('ad.impression_url') }}</label>
                  <input id="ttImpUrl" v-model.trim="ad.impression_tracking_url" type="url" class="form-control" placeholder="https://" dir="ltr">
                </div>
                <div>
                  <label class="fb-label" for="ttClickUrl">{{ t('ad.click_url') }}</label>
                  <input id="ttClickUrl" v-model.trim="ad.click_tracking_url" type="url" class="form-control" placeholder="https://" dir="ltr">
                </div>
              </div>
              <p class="fb-hint"><i class="bx bx-info-circle"></i> {{ t('ad.utm_hint') }}</p>
            </details>

            <label class="fb-label mt-2" for="ttAdName">{{ t('ad.name') }} <span class="fb-opt">{{ t('common.optional') }}</span></label>
            <input id="ttAdName" v-model.trim="ad.name" class="form-control" maxlength="512">
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
              <button type="button" :class="{ on: ad.status === 'DISABLE' }" @click="ad.status = 'DISABLE'"><i class="bx bx-pause-circle"></i> {{ t('review.save_paused') }}</button>
              <button type="button" :class="{ on: ad.status === 'ENABLE' }" @click="ad.status = 'ENABLE'"><i class="bx bx-play-circle"></i> {{ t('review.publish') }}</button>
            </div>
            <p class="fb-hint"><i class="bx bx-info-circle"></i> {{ ad.status === 'ENABLE' ? t('review.publish_hint') : t('review.paused_hint') }}</p>

            <div v-if="blockers.length" class="fb-blockers">
              <strong><i class="bx bx-error-circle"></i> {{ t('review.fix_before') }}</strong>
              <ul><li v-for="b in blockers" :key="b.text"><button type="button" class="fb-link" @click="goTo(b.step)">{{ b.text }}</button></li></ul>
            </div>

            <div v-if="result && result.success" class="fb-result is-ok">
              <i class="bx bx-check-circle"></i>
              <div>
                <strong>{{ result.message }}</strong>
                <p>{{ result.status === 'ENABLE' ? t('result.in_review') : t('result.saved_paused') }} {{ t('result.ad_id', { id: result.ad_id }) }}</p>
                <a :href="result.redirect_url || urls.campaignsList" class="btn btn-primary btn-sm">{{ t('result.go_campaigns') }}</a>
                <button type="button" class="btn btn-sm fb-btn-ghost" @click="startAnother">{{ t('result.another') }}</button>
              </div>
            </div>
            <div v-else-if="result" class="fb-result is-err">
              <i class="bx bx-error-circle"></i>
              <div>
                <strong>{{ result.step ? t('result.step_failed', { step: t('steps.' + result.step) }) : t('result.failed') }}</strong>
                <p>{{ result.message }}</p>
                <ul v-if="result.created && result.created.length" class="fb-created">
                  <li v-for="c in result.created" :key="c.type"><i class="bx bx-check"></i> {{ t('result.created_item', { step: t('steps.' + c.type), action: c.reused ? t('result.selected') : t('result.created'), id: c.id }) }}</li>
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
              {{ submitting ? t('foot.creating') : (result && !result.success ? t('foot.try_again') : (ad.status === 'ENABLE' ? t('foot.publish') : t('foot.create'))) }}
            </button>
          </div>
        </section>

        <!-- ===== Side: preview ===== -->
        <aside class="fb-side">
          <div class="fb-card">
            <div class="fb-side-h"><strong><i class="bx bx-show"></i> {{ t('side.preview') }}</strong><small>{{ t('side.approximate') }}</small></div>
            <div class="tt-phone">
              <div class="tt-screen">
                <img v-if="previewImage" :src="previewImage" alt="" class="tt-bg">
                <div v-else class="tt-bg tt-bg-empty"><i class="bx" :class="ad.format === 'SINGLE_IMAGE' && ad.source !== 'spark' ? 'bx-image' : 'bx-video'"></i></div>
                <span v-if="ad.source === 'spark' || ad.format === 'SINGLE_VIDEO'" class="tt-play"><i class="bx bx-play"></i></span>
                <div class="tt-rail">
                  <span class="tt-av"><img v-if="identity && identity.picture" :src="identity.picture" alt=""><i v-else class="bx bxl-tiktok"></i></span>
                  <span><i class="bx bxs-heart"></i></span><span><i class="bx bxs-comment-dots"></i></span><span><i class="bx bxs-share"></i></span>
                </div>
                <div class="tt-cap">
                  <strong dir="ltr">@{{ identity ? (identity.username || identity.name) : t('side.your_account') }}</strong>
                  <p>{{ previewText || t('side.text_placeholder') }}</p>
                  <small>{{ t('side.sponsored') }}</small>
                  <span v-if="showCta" class="tt-cta">{{ ctaLabel(ad.cta) }} <i class="bx bx-chevron-right"></i></span>
                </div>
              </div>
            </div>
          </div>
          <div class="fb-card">
            <div class="fb-side-h"><strong><i class="bx bx-target-lock"></i> {{ t('side.summary') }}</strong></div>
            <p class="fb-muted mb-1">{{ audienceSummary }}</p>
            <p v-if="budgetLine" class="fb-muted mb-0"><i class="bx bx-wallet"></i> {{ budgetLine }}</p>
          </div>
        </aside>
      </div>

      <media-picker ref="picker" :urls="galleryUrls" :multiple="false" :type-lock="pickerTypeLock" :platforms="['tiktok']" @select="onPicked" />
    </template>
  </div>
</template>

<script setup>
// TikTok campaign builder (TikTok Marketing API through
// TiktokAdBuilderController). Same building blocks as the Facebook
// builder: shared styles, SearchPicker, MediaPicker and localStorage
// drafts per ad account; every submission carries an idempotency key.
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
// The Media picker on this page reads media_gallery.php.
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
const interestType = ref(null);
const postsCursor = ref(null);
const mediaError = ref('');
const picker = ref(null);
const pickerTarget = ref('media');
const stepErrors = reactive({});
const fieldErrors = reactive({});

const objective = ref(null);
const campaign = reactive({ mode: 'new', id: null, name: '', budget_optimize_on: false, budget_mode: 'BUDGET_MODE_DAY', budget: null, app_promotion_type: 'APP_INSTALL', special_industries: [] });
const adgroup = reactive({
  name: '', optimization_goal: null, placement_type: 'AUTOMATIC', placements: ['PLACEMENT_TIKTOK'], search_result_enabled: true,
  comment_disabled: false, video_download_disabled: false, share_disabled: false, brand_safety_type: 'NO_BRAND_SAFETY',
  budget_mode: 'BUDGET_MODE_DAY', budget: null, schedule_start: '', schedule_end: '',
  dayparting: { enabled: false, days: [0, 1, 2, 3, 4], from: 9, to: 21 },
  bid_type: 'BID_TYPE_NO_BID', bid: null, pacing: 'PACING_MODE_SMOOTH', pixel_id: null, optimization_event: null, app_id: null, frequency: 3, frequency_schedule: 7,
});
const targeting = reactive({
  locations: [], languages: [], gender: 'GENDER_UNLIMITED', age_groups: [], spending_power: 'ALL', household_income: [],
  include: [], exclude: [], interests: [], action_period: 15, video_actions: ['WATCHED_TO_END', 'LIKED', 'COMMENTED', 'SHARED'],
  operating_systems: [], min_android_version: '', min_ios_version: '', device_models: [], network_types: [],
  device_price: { enabled: false, min: 0, max: 1000 }, smart_audience: false, smart_interest_behavior: false,
});
const ad = reactive({ name: '', identity_key: null, source: 'upload', format: 'SINGLE_VIDEO', media: null, cover: null, post: null, text: '', cta: 'LEARN_MORE', url: '', lead_form_id: null, tracking_pixel_id: null, impression_tracking_url: '', click_tracking_url: '', status: 'DISABLE' });

const lists = reactive({
  campaigns: [], pixels: [], apps: [], audiences: [], leadForms: [], languages: [], osVersions: [], posts: [],
  loading: {}, errors: {},
});

// ---------------------------------------------------------------- derived
const objectives = computed(() => (boot.value ? boot.value.objectives : {}));
const spec = computed(() => (objective.value ? objectives.value[objective.value] : null));
const currency = computed(() => (boot.value ? boot.value.account.currency : ''));
const billing = computed(() => (spec.value && adgroup.optimization_goal ? (spec.value.goals[adgroup.optimization_goal] || {}).billing : null));
const selectedCampaign = computed(() => lists.campaigns.find((c) => c.id === campaign.id) || null);
const campaignCbo = computed(() => (campaign.mode === 'existing' ? !!(selectedCampaign.value && selectedCampaign.value.cbo) : campaign.budget_optimize_on));
const filteredCampaigns = computed(() => { const q = campaignQuery.value.toLowerCase(); return lists.campaigns.filter((c) => !q || c.name.toLowerCase().includes(q)); });
const selectedPixel = computed(() => lists.pixels.find((p) => p.id === adgroup.pixel_id) || null);
const selectedApp = computed(() => lists.apps.find((a) => a.id === adgroup.app_id) || null);
const appPlatform = computed(() => (spec.value && spec.value.needs_app && selectedApp.value && ['ANDROID', 'IOS'].includes(selectedApp.value.platform) ? selectedApp.value.platform : null));
const placementsList = computed(() => (adgroup.placement_type === 'NORMAL' ? (adgroup.placements.length ? adgroup.placements : ['PLACEMENT_TIKTOK']) : []));
const tiktokPlacement = computed(() => adgroup.placement_type === 'AUTOMATIC' || adgroup.placements.includes('PLACEMENT_TIKTOK'));
const tiktokOnly = computed(() => adgroup.placement_type === 'NORMAL' && placementsList.value.length === 1 && placementsList.value[0] === 'PLACEMENT_TIKTOK');
const searchEligible = computed(() => ['APP_PROMOTION', 'WEB_CONVERSIONS', 'TRAFFIC', 'LEAD_GENERATION'].includes(objective.value) && tiktokPlacement.value);
const needsEnd = computed(() => !campaignCbo.value && adgroup.budget_mode === 'BUDGET_MODE_TOTAL');
const usableAudiences = computed(() => lists.audiences.filter((a) => a.usable));
const osChoice = computed(() => appPlatform.value || (targeting.operating_systems.length === 1 ? targeting.operating_systems[0] : null));
const osVersionModel = computed({
  get: () => (osChoice.value === 'IOS' ? targeting.min_ios_version : targeting.min_android_version),
  set: (v) => { if (osChoice.value === 'IOS') targeting.min_ios_version = v; else targeting.min_android_version = v; },
});
const targetsUs = computed(() => targeting.locations.some((l) => l.region_code === 'US'));
const identity = computed(() => (boot.value ? boot.value.identities.find((i) => i.type + ':' + i.id === ad.identity_key) || null : null));
const hasEmoji = computed(() => /[\u{1F000}-\u{1FAFF}\u{2600}-\u{27BF}]/u.test(ad.text));
const showCta = computed(() => !!spec.value && !(objective.value === 'ENGAGEMENT') && (spec.value.url !== 'none' ? !!ad.url : true));
const previewImage = computed(() => {
  if (ad.source === 'spark') return ad.post ? ad.post.poster : null;
  if (!ad.media) return null;
  if (ad.media.type === 'image') return ad.media.url;
  return ad.cover ? ad.cover.url : ad.media.thumbnail_url;
});
const previewText = computed(() => ad.text || (ad.source === 'spark' && ad.post ? ad.post.text : ''));
const pickerTypeLock = computed(() => (pickerTarget.value === 'cover' || ad.format === 'SINGLE_IMAGE' ? 'image' : 'video'));
const today = new Date().toISOString().slice(0, 10);

const steps = computed(() => [
  { key: 'campaign', label: t('steps.campaign'), summary: objective.value ? objectives.value[objective.value].label : t('steps.pick_objective') },
  { key: 'adgroup', label: t('steps.adgroup'), summary: !campaignCbo.value && adgroup.budget ? money(adgroup.budget) + (adgroup.budget_mode === 'BUDGET_MODE_DAY' ? t('steps.per_day') : '') : t('steps.budget_schedule') },
  { key: 'audience', label: t('steps.audience'), summary: targeting.locations.length ? t('steps.locations', { count: targeting.locations.length }) : t('steps.who') },
  { key: 'ad', label: t('steps.ad'), summary: ad.source === 'spark' ? t('steps.spark') : t(ad.format === 'SINGLE_IMAGE' ? 'steps.image' : 'steps.video') },
  { key: 'review', label: t('steps.review'), summary: t('steps.review_sub') },
]);
const current = computed(() => steps.value[step.value].key);

const interestWarnings = computed(() => {
  const out = [];
  if (hasType('PURCHASE_INTENTION') && hasType('ADDITIONAL_INTEREST')) out.push(t('audience.warn_purchase_conflict'));
  if (hasBehaviors() && !tiktokOnly.value) out.push(t('audience.warn_actions_placement'));
  if (hasType('PURCHASE_INTENTION') && !boot.value.options.purchase_intent_objectives.includes(objective.value)) out.push(t('audience.warn_purchase_objective'));
  return out;
});

const audienceSummary = computed(() => {
  const parts = [targeting.locations.length ? targeting.locations.map((l) => l.name).slice(0, 3).join(', ') + (targeting.locations.length > 3 ? ' +' + (targeting.locations.length - 3) : '') : t('summary.no_location')];
  parts.push(targeting.age_groups.length ? targeting.age_groups.map((a) => t('ages.' + a)).join(', ') : t('audience.all_ages'));
  parts.push(t('genders.' + targeting.gender));
  if (targeting.languages.length) parts.push(targeting.languages.map((l) => l.name).join(', '));
  if (targeting.interests.length) parts.push(t('summary.interests', { count: targeting.interests.length }));
  if (targeting.include.length) parts.push(t('summary.custom', { count: targeting.include.length }));
  if (targeting.exclude.length) parts.push(t('summary.excluded', { count: targeting.exclude.length }));
  if (osChoice.value) parts.push(osChoice.value === 'IOS' ? 'iOS' : 'Android');
  if (targeting.smart_audience || targeting.smart_interest_behavior) parts.push(t('summary.expansion'));
  return parts.join(' · ');
});
const budgetLine = computed(() => {
  const amount = campaignCbo.value ? (campaign.mode === 'new' ? campaign.budget : (selectedCampaign.value || {}).budget) : adgroup.budget;
  const mode = campaignCbo.value ? (campaign.mode === 'new' ? campaign.budget_mode : (selectedCampaign.value || {}).budget_mode) : adgroup.budget_mode;
  if (!amount) return '';
  return money(amount) + (mode === 'BUDGET_MODE_DAY' ? t('steps.per_day') : ' ' + t('common.lifetime').toLowerCase()) + (campaignCbo.value ? ' · ' + t('campaign.cbo_short') : '');
});

const reviewRows = computed(() => [
  { key: 'objective', step: 0, icon: 'bx-flag', label: t('review.objective'), value: objective.value ? objectives.value[objective.value].label : '-' },
  { key: 'campaign', step: 0, icon: 'bx-folder', label: t('review.campaign'), value: campaign.mode === 'existing' ? (selectedCampaign.value ? selectedCampaign.value.name : '-') : (campaign.name || t('review.auto_name')) },
  { key: 'goal', step: 1, icon: 'bx-target-lock', label: t('review.goal'), value: adgroup.optimization_goal ? t('goals.' + adgroup.optimization_goal) + ' · ' + (adgroup.bid_type === 'BID_TYPE_CUSTOM' ? t(billing.value === 'OCPM' ? 'adgroup.cost_cap' : 'adgroup.bid_cap') + ' ' + money(adgroup.bid) : t('adgroup.max_delivery')) : '-' },
  { key: 'placements', step: 1, icon: 'bx-layout', label: t('review.placements'), value: adgroup.placement_type === 'AUTOMATIC' ? t('adgroup.automatic') : placementsList.value.map((p) => t('placements.' + p)).join(', ') },
  { key: 'budget', step: 1, icon: 'bx-wallet', label: t('review.budget'), value: budgetLine.value || '-' },
  { key: 'schedule', step: 1, icon: 'bx-calendar', label: t('review.schedule'), value: (adgroup.schedule_start || t('review.now')).replace('T', ' ') + ' → ' + (adgroup.schedule_end ? adgroup.schedule_end.replace('T', ' ') : t('review.ongoing')) + (adgroup.dayparting.enabled ? ' · ' + t('review.dayparted') : '') },
  { key: 'audience', step: 2, icon: 'bx-group', label: t('review.audience'), value: audienceSummary.value },
  { key: 'identity', step: 3, icon: 'bx-user', label: t('review.identity'), value: identity.value ? identity.value.name : '-' },
  { key: 'creative', step: 3, icon: 'bx-video', label: t('review.creative'), value: ad.source === 'spark' ? t('ad.spark') + (ad.post ? ' · ' + ad.post.id : '') : (ad.media ? ad.media.original_name : '-') },
  { key: 'destination', step: 3, icon: 'bx-link', label: t('review.destination'), value: spec.value && spec.value.needs_form ? (lists.leadForms.find((f) => f.id === ad.lead_form_id) || {}).name || '-' : (objective.value === 'ENGAGEMENT' ? t('ad.profile') : (ad.url || '-')) },
]);

// ---------------------------------------------------------------- validation
function validate() {
  const p = [];
  const add = (s, key, params) => p.push({ step: s, text: t('errors.' + key, params) });
  if (!objective.value) add(0, 'objective');
  if (campaign.mode === 'existing' && !campaign.id) add(0, 'campaign');
  if (campaign.mode === 'new' && campaign.budget_optimize_on && !(campaign.budget > 0)) add(0, 'campaign_budget');
  if (objective.value && !adgroup.optimization_goal) add(1, 'goal');
  if (spec.value && spec.value.needs_pixel && (!adgroup.pixel_id || !adgroup.optimization_event)) add(1, 'pixel');
  if (spec.value && spec.value.needs_app && !adgroup.app_id) add(1, 'app');
  if (adgroup.placement_type === 'NORMAL' && !adgroup.placements.length) add(1, 'placements');
  if (!campaignCbo.value && !(adgroup.budget > 0)) add(1, 'budget');
  if (needsEnd.value && !adgroup.schedule_end) add(1, 'end');
  if (adgroup.schedule_end && adgroup.schedule_start && adgroup.schedule_end <= adgroup.schedule_start) add(1, 'end_after');
  if (adgroup.bid_type === 'BID_TYPE_CUSTOM' && !(adgroup.bid > 0)) add(1, 'bid');
  if (adgroup.dayparting.enabled && !adgroup.dayparting.days.length) add(1, 'days');
  if (!targeting.locations.length) add(2, 'locations');
  if (interestWarnings.value.length) add(2, 'interests');
  if (targeting.device_price.enabled && !(targeting.device_price.max > targeting.device_price.min)) add(2, 'device_price');
  if (!identity.value) add(3, 'identity');
  if (ad.source === 'spark' && !ad.post) add(3, 'post');
  if (ad.source === 'upload' && !ad.media) add(3, 'media');
  if (ad.source === 'upload' && !ad.text.trim()) add(3, 'text');
  if (hasEmoji.value) add(3, 'emoji');
  if (spec.value && spec.value.url === 'required' && !ad.url) add(3, 'url');
  if (ad.url && !/^https?:\/\/\S+\.\S+/.test(ad.url)) add(3, 'url_format');
  if (spec.value && spec.value.needs_form && !ad.lead_form_id) add(3, 'form');
  return p;
}
const blockers = computed(() => validate());

// ---------------------------------------------------------------- helpers
function newKey() {
  return (window.crypto && window.crypto.randomUUID) ? window.crypto.randomUUID() : Date.now().toString(36) + Math.random().toString(36).slice(2) + Math.random().toString(36).slice(2);
}
function money(v) {
  if (v === null || v === undefined || v === '') return '-';
  try { return new Intl.NumberFormat(i18n.intl, { style: 'currency', currency: currency.value || 'USD', maximumFractionDigits: 2 }).format(v); } catch (e) { return v; }
}
function compact(n) { return new Intl.NumberFormat(i18n.intl, { notation: 'compact', maximumFractionDigits: 1 }).format(n); }
const humanize = (v) => (v || '').replace(/_/g, ' ').toLowerCase().replace(/^./, (x) => x.toUpperCase());
const known = (key, code, fallback) => { const v = t(key + '.' + code); return v === key + '.' + code ? fallback : v; };
function ctaLabel(c) { return known('cta', c, humanize(c)); }
function placementIcon(p) { return ({ PLACEMENT_TIKTOK: 'bxl-tiktok', PLACEMENT_PANGLE: 'bx-grid-alt', PLACEMENT_GLOBAL_APP_BUNDLE: 'bx-package' })[p] || 'bx-layout'; }
function hourLabel(h) { return String(h).padStart(2, '0') + ':00'; }
function hasType(type) { return targeting.interests.some((i) => i.type === type); }
function hasBehaviors() { return targeting.interests.some((i) => ['VIDEO_INTERACTION', 'CREATOR_INTERACTION', 'HASHTAG_INTERACTION'].includes(i.type)); }
function interestTypeAllowed(type) {
  if (type === 'PURCHASE_INTENTION') return !objective.value || boot.value.options.purchase_intent_objectives.includes(objective.value);
  return true;
}
/** "Now" in the ad account's time zone, as a datetime-local value. */
function nowInAccountTz(addMinutes = 15) {
  const d = new Date(Date.now() + addMinutes * 60000);
  try {
    const parts = Object.fromEntries(new Intl.DateTimeFormat('en-CA', { timeZone: boot.value.account.timezone, year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit', hourCycle: 'h23' }).formatToParts(d).map((x) => [x.type, x.value]));
    return `${parts.year}-${parts.month}-${parts.day}T${parts.hour}:${parts.minute}`;
  } catch (e) {
    return d.toISOString().slice(0, 16);
  }
}

async function api(url, params = {}) {
  try {
    const { data } = await window.axios.get(url, { params });
    if (!data.success) throw new Error(data.message || t('client.tiktok_error'));
    return data.data;
  } catch (e) {
    throw new Error((e.response && e.response.data && (e.response.data.message || Object.values(e.response.data.errors || {}).flat()[0])) || e.message || t('client.request_failed'));
  }
}
async function load(name, url, params = {}, assign = null) {
  lists.loading = { ...lists.loading, [name]: true };
  lists.errors = { ...lists.errors, [name]: '' };
  try {
    const data = await api(url, params);
    if (assign) assign(data); else lists[name] = data;
  } catch (e) {
    lists.errors = { ...lists.errors, [name]: e.message };
  } finally {
    lists.loading = { ...lists.loading, [name]: false };
  }
}
const loadedOnce = new Set();
function ensure(name, url, params = {}) {
  if (loadedOnce.has(name)) return;
  loadedOnce.add(name);
  load(name, url, params);
}
function ensurePixels() { ensure('pixels', props.urls.pixels); }

const searchLocations = (q) => api(props.urls.locations, { q, objective: objective.value || 'TRAFFIC' });
const searchInterests = (q) => api(props.urls.interests, { q, types: interestType.value ? [interestType.value] : undefined });
const searchDevices = (q) => api(props.urls.deviceModels, { q });

function loadPosts(more = false) {
  if (!identity.value) return;
  const params = { identity_id: identity.value.id, identity_type: identity.value.type };
  if (more && postsCursor.value) params.cursor = postsCursor.value;
  load('posts', props.urls.sparkPosts, params, (data) => {
    lists.posts = more ? lists.posts.concat(data.posts) : data.posts;
    postsCursor.value = data.has_more ? data.cursor : null;
  });
}

// ---------------------------------------------------------------- actions
function setCampaignMode(mode) {
  campaign.mode = mode;
  if (mode === 'existing') ensure('campaigns', props.urls.campaigns);
}
function chooseCampaign(c) {
  campaign.id = c.id;
  setObjective(c.objective, true);
  if (c.app_promotion_type) campaign.app_promotion_type = c.app_promotion_type;
}
function setObjective(key, keepCampaign = false) {
  if (objective.value === key) return;
  objective.value = key;
  if (!keepCampaign && campaign.mode === 'existing' && selectedCampaign.value && selectedCampaign.value.objective !== key) campaign.id = null;
  adgroup.optimization_goal = Object.keys(objectives.value[key].goals)[0];
  if (!objectives.value[key].needs_pixel) { adgroup.pixel_id = null; adgroup.optimization_event = null; }
  if (!objectives.value[key].needs_app) adgroup.app_id = null;
  ad.cta = key === 'APP_PROMOTION' ? 'DOWNLOAD_NOW' : (key === 'LEAD_GENERATION' ? 'SIGN_UP' : (key === 'WEB_CONVERSIONS' ? 'SHOP_NOW' : 'LEARN_MORE'));
  if (!interestTypeAllowed('PURCHASE_INTENTION')) targeting.interests = targeting.interests.filter((i) => i.type !== 'PURCHASE_INTENTION');
}
function toggleDay(d) {
  const days = adgroup.dayparting.days;
  adgroup.dayparting.days = days.includes(d) ? days.filter((x) => x !== d) : days.concat(d).sort();
}
function setOs(os) {
  targeting.operating_systems = os ? [os] : [];
  targeting.min_android_version = '';
  targeting.min_ios_version = '';
}
function chooseIdentity(idt) {
  ad.identity_key = idt.type + ':' + idt.id;
  if (!idt.can_pull_video && ad.source === 'spark') ad.source = 'upload';
}
function setFormat(f) {
  if (ad.format === f) return;
  ad.format = f;
  ad.media = null;
  ad.cover = null;
}
function openGallery(target) {
  pickerTarget.value = target;
  mediaError.value = '';
  nextTick(() => picker.value && picker.value.open());
}
function onPicked(assets) {
  const asset = assets && assets[0];
  if (!asset) return;
  if (pickerTarget.value === 'cover') {
    if (asset.type !== 'image') { mediaError.value = t('errors.cover_image'); return; }
    ad.cover = asset;
    return;
  }
  const want = ad.format === 'SINGLE_IMAGE' ? 'image' : 'video';
  if (asset.type !== want) { mediaError.value = t(want === 'video' ? 'errors.need_video' : 'errors.need_image'); return; }
  ad.media = asset;
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

function payload() {
  const g = adgroup;
  return {
    idempotency_key: idempotencyKey.value,
    objective: objective.value,
    campaign: campaign.mode === 'existing'
      ? { mode: 'existing', id: campaign.id, cbo: campaignCbo.value }
      : { mode: 'new', name: campaign.name || null, budget_optimize_on: campaign.budget_optimize_on, budget_mode: campaign.budget_optimize_on ? campaign.budget_mode : null, budget: campaign.budget_optimize_on ? campaign.budget : null, app_promotion_type: objective.value === 'APP_PROMOTION' ? campaign.app_promotion_type : null, special_industries: campaign.special_industries },
    adgroup: {
      name: g.name || null, optimization_goal: g.optimization_goal, placement_type: g.placement_type, placements: placementsList.value,
      search_result_enabled: searchEligible.value ? g.search_result_enabled : null,
      comment_disabled: g.comment_disabled, video_download_disabled: g.video_download_disabled, share_disabled: g.share_disabled, brand_safety_type: tiktokPlacement.value ? g.brand_safety_type : null,
      budget_mode: campaignCbo.value ? null : g.budget_mode, budget: campaignCbo.value ? null : g.budget,
      schedule_start: g.schedule_start || null, schedule_end: g.schedule_end || null,
      dayparting: g.dayparting.enabled ? g.dayparting : null,
      bid_type: g.bid_type, bid: g.bid_type === 'BID_TYPE_CUSTOM' ? g.bid : null, pacing: g.pacing,
      pixel_id: spec.value.needs_pixel ? g.pixel_id : null, optimization_event: spec.value.needs_pixel ? g.optimization_event : null,
      app_id: spec.value.needs_app ? g.app_id : null,
      frequency: objective.value === 'REACH' ? g.frequency : null, frequency_schedule: objective.value === 'REACH' ? g.frequency_schedule : null,
      targeting: {
        locations: targeting.locations.map((l) => ({ id: l.id, type: l.type })),
        languages: targeting.languages.map((l) => l.code),
        gender: targeting.gender, age_groups: targeting.age_groups, spending_power: targeting.spending_power,
        household_income: targetsUs.value ? targeting.household_income : [],
        audience_ids: targeting.include.map((a) => a.id), excluded_audience_ids: targeting.exclude.map((a) => a.id),
        interests: targeting.interests.map((i) => ({ id: i.id, type: i.type })),
        action_period: targeting.action_period, video_actions: targeting.video_actions,
        operating_systems: osChoice.value ? [osChoice.value] : [], min_android_version: targeting.min_android_version || null, min_ios_version: targeting.min_ios_version || null,
        device_model_ids: targeting.device_models.map((d) => d.id), network_types: targeting.network_types,
        device_price: targeting.device_price.enabled && !targeting.device_models.length ? { min: targeting.device_price.min, max: targeting.device_price.max } : null,
        smart_audience: targeting.smart_audience, smart_interest_behavior: targeting.smart_interest_behavior,
      },
    },
    ad: {
      name: ad.name || null, identity_id: identity.value ? identity.value.id : null, identity_type: identity.value ? identity.value.type : null,
      source: ad.source, tiktok_item_id: ad.source === 'spark' && ad.post ? ad.post.id : null,
      format: ad.source === 'upload' ? ad.format : null, media_id: ad.source === 'upload' && ad.media ? ad.media.id : null,
      cover_id: ad.source === 'upload' && ad.format === 'SINGLE_VIDEO' && ad.cover ? ad.cover.id : null,
      text: ad.text.trim() || null, cta: showCta.value ? ad.cta : null, url: spec.value.url !== 'none' ? (ad.url || null) : null,
      lead_form_id: spec.value.needs_form ? ad.lead_form_id : null, tracking_pixel_id: spec.value.needs_pixel ? null : ad.tracking_pixel_id,
      impression_tracking_url: ad.impression_tracking_url || null, click_tracking_url: ad.click_tracking_url || null, status: ad.status,
    },
  };
}

async function submit() {
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
      const stepFor = (f) => (f.startsWith('campaign') || f === 'objective' ? 0 : f.startsWith('adgroup.targeting') ? 2 : f.startsWith('adgroup') ? 1 : 3);
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
  ad.media = null;
  ad.cover = null;
  ad.post = null;
  ad.name = '';
  step.value = 3;
}

// ---------------------------------------------------------------- drafts
const draftKey = computed(() => `tt-ad-builder:${props.accountId}`);
function snapshot() {
  return JSON.parse(JSON.stringify({ step: step.value, maxReached: maxReached.value, objective: objective.value, campaign, adgroup, targeting, ad, key: idempotencyKey.value }));
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
  objective.value = d.objective && objectives.value[d.objective] ? d.objective : null;
  Object.assign(campaign, d.campaign);
  Object.assign(adgroup, d.adgroup);
  Object.assign(targeting, d.targeting);
  Object.assign(ad, d.ad);
  if (ad.identity_key && !identity.value) ad.identity_key = null;
  idempotencyKey.value = d.key || newKey();
  step.value = d.step || 0;
  maxReached.value = d.maxReached || 0;
  if (campaign.mode === 'existing') ensure('campaigns', props.urls.campaigns);
}
function discardDraft() {
  clearDraft();
  draftOffer.value = null;
}

// ---------------------------------------------------------------- watchers
watch(() => [spec.value && spec.value.needs_pixel, current.value], ([needsPixel]) => { if (needsPixel || current.value === 'ad') ensurePixels(); });
watch(() => spec.value && spec.value.needs_app, (needs) => { if (needs) ensure('apps', props.urls.apps); });
watch(() => spec.value && spec.value.needs_form, (needs) => { if (needs) ensure('leadForms', props.urls.leadForms); });
watch(current, (c) => {
  if (c === 'audience') { ensure('languages', props.urls.languages); ensure('audiences', props.urls.audiences); }
});
watch(() => adgroup.pixel_id, () => {
  if (selectedPixel.value && !selectedPixel.value.events.some((e) => e.code === adgroup.optimization_event)) adgroup.optimization_event = null;
});
watch(osChoice, (os) => { lists.osVersions = []; if (os) load('osVersions', props.urls.osVersions, { os }); });
watch(() => [ad.source, ad.identity_key], () => { lists.posts = []; postsCursor.value = null; if (ad.source === 'spark') loadPosts(); else ad.post = null; });
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
  adgroup.schedule_start = nowInAccountTz();
  const available = boot.value.identities.filter((i) => i.available);
  if (available.length === 1) chooseIdentity(available[0]);
  const draft = readDraft();
  if (draft) draftOffer.value = { savedAt: new Date(draft.savedAt).toLocaleString(i18n.intl, { dateStyle: 'medium', timeStyle: 'short' }) };
});
</script>

<style scoped src="../facebook/builder.css"></style>
<style scoped>
.tt-hero { position: relative; overflow: hidden; display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 18px; padding: 26px 26px 20px; margin-bottom: 16px; border-radius: 22px; color: #fff; background: radial-gradient(120% 140% at 0% 0%, #1c1c24 0%, #0b0b10 55%, #050507 100%); box-shadow: 0 18px 40px rgba(5, 5, 7, .28); isolation: isolate; }
.tt-hero-glow { position: absolute; width: 280px; height: 280px; border-radius: 50%; filter: blur(70px); opacity: .45; z-index: -1; pointer-events: none; }
.tt-hero-glow.is-cyan { background: #25F4EE; top: -140px; inset-inline-start: -60px; }
.tt-hero-glow.is-pink { background: #FE2C55; bottom: -170px; inset-inline-end: -40px; }
.tt-hero-main { display: flex; align-items: center; gap: 16px; min-width: 0; flex: 1 1 380px; }
.tt-logo { flex-shrink: 0; width: 60px; height: 60px; border-radius: 18px; display: grid; place-items: center; font-size: 34px; color: #fff; background: #000; border: 1px solid rgba(255, 255, 255, .12); text-shadow: -2px -2px 0 #25F4EE, 2px 2px 0 #FE2C55; box-shadow: 0 10px 24px rgba(0, 0, 0, .45), inset 0 0 0 1px rgba(255, 255, 255, .04); }
.tt-hero-text { min-width: 0; }
.tt-eyebrow { display: inline-flex; align-items: center; gap: 5px; padding: 3px 10px; margin-bottom: 6px; border-radius: 999px; font-size: .7rem; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; color: #25F4EE; background: rgba(37, 244, 238, .1); border: 1px solid rgba(37, 244, 238, .25); }
.tt-hero h4 { margin: 0; font-size: 1.55rem; font-weight: 800; letter-spacing: -.02em; color: #fff; }
.tt-hero p { margin: 4px 0 0; max-width: 560px; font-size: .9rem; line-height: 1.5; color: rgba(255, 255, 255, .7); }
.tt-hero-side { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
.tt-hero-acct { display: flex; align-items: center; gap: 10px; padding: 8px 14px 8px 8px; border-radius: 14px; background: rgba(255, 255, 255, .06); border: 1px solid rgba(255, 255, 255, .1); backdrop-filter: blur(6px); }
[dir="rtl"] .tt-hero-acct { padding: 8px 8px 8px 14px; }
.tt-hero-acct-ic { width: 36px; height: 36px; border-radius: 10px; display: grid; place-items: center; font-size: 18px; background: linear-gradient(135deg, #25F4EE, #FE2C55); color: #0b0b10; }
.tt-hero-acct > span:last-child { display: flex; flex-direction: column; line-height: 1.2; min-width: 0; }
.tt-hero-acct small { font-size: .68rem; color: rgba(255, 255, 255, .55); }
.tt-hero-acct strong { font-size: .86rem; color: #fff; max-width: 220px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.tt-hero-btn { display: inline-flex; align-items: center; gap: 6px; height: 40px; padding: 0 16px; border-radius: 12px; font-size: .84rem; font-weight: 600; color: #0b0b10; background: #fff; text-decoration: none; transition: transform .15s ease, box-shadow .15s ease; }
.tt-hero-btn:hover { color: #0b0b10; transform: translateY(-1px); box-shadow: -3px 3px 0 #25F4EE, 3px -3px 0 #FE2C55; }
.tt-hero-flow { display: flex; flex-wrap: wrap; gap: 8px; width: 100%; margin: 4px 0 0; padding: 14px 0 0; list-style: none; border-top: 1px solid rgba(255, 255, 255, .08); }
.tt-hero-flow li { display: inline-flex; align-items: center; gap: 6px; padding: 5px 11px; border-radius: 999px; font-size: .76rem; font-weight: 500; color: rgba(255, 255, 255, .82); background: rgba(255, 255, 255, .05); border: 1px solid rgba(255, 255, 255, .08); }
.tt-hero-flow li i { color: #25F4EE; font-size: 14px; }
.tt-hero-flow li:nth-child(even) i { color: #FE2C55; }
@media (max-width: 767.98px) {
  .tt-hero { padding: 20px 16px 16px; border-radius: 18px; }
  .tt-hero h4 { font-size: 1.25rem; }
  .tt-logo { width: 50px; height: 50px; font-size: 28px; border-radius: 15px; }
  .tt-hero-side, .tt-hero-acct { width: 100%; }
  .tt-hero-btn { flex: 1; justify-content: center; }
}
.tt-daypart { margin-top: 10px; display: grid; gap: 10px; }
.tt-days, .tt-types { display: flex; flex-wrap: wrap; gap: 6px; margin-bottom: 8px; }
.fb-chip-btn.is-on { background: var(--ink); color: #fff; border-color: var(--ink); }
.tt-chip-type { opacity: .65; font-size: .7rem; margin-inline-start: 4px; }
.tt-avatar { overflow: hidden; }
.tt-avatar img { width: 100%; height: 100%; object-fit: cover; }
.tt-posts { display: grid; grid-template-columns: repeat(auto-fill, minmax(110px, 1fr)); gap: 10px; margin-top: 8px; }
.tt-post { position: relative; display: flex; flex-direction: column; gap: 4px; padding: 0; border: 2px solid var(--line); border-radius: 12px; background: #fff; overflow: hidden; text-align: start; }
.tt-post img, .tt-post .fb-media-ph { width: 100%; aspect-ratio: 9 / 16; object-fit: cover; }
.tt-post small { padding: 0 6px 6px; font-size: .72rem; color: var(--text); line-height: 1.25; }
.tt-post.on { border-color: var(--brand-2); }
.tt-post > .bx { position: absolute; top: 6px; inset-inline-end: 6px; color: var(--brand-2); font-size: 22px; background: #fff; border-radius: 50%; }
.tt-details summary { cursor: pointer; font-weight: 600; color: var(--ink); }
.tt-phone { margin: 4px auto 0; max-width: 260px; padding: 8px; border-radius: 30px; background: #0b0b0f; box-shadow: 0 12px 30px rgba(17, 24, 39, .25); }
.tt-screen { position: relative; aspect-ratio: 9 / 16; border-radius: 22px; overflow: hidden; background: #1f2937; color: #fff; }
.tt-bg { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; }
.tt-bg-empty { display: grid; place-items: center; font-size: 46px; color: rgba(255, 255, 255, .35); }
.tt-play { position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); font-size: 44px; color: rgba(255, 255, 255, .8); }
.tt-rail { position: absolute; inset-inline-end: 8px; bottom: 120px; display: flex; flex-direction: column; align-items: center; gap: 14px; font-size: 24px; text-shadow: 0 1px 4px rgba(0, 0, 0, .5); }
.tt-av { width: 38px; height: 38px; border-radius: 50%; border: 2px solid #fff; overflow: hidden; display: grid; place-items: center; background: #111; font-size: 20px; }
.tt-av img { width: 100%; height: 100%; object-fit: cover; }
.tt-cap { position: absolute; inset-inline: 0; bottom: 0; padding: 40px 56px 12px 12px; background: linear-gradient(transparent, rgba(0, 0, 0, .75)); }
[dir="rtl"] .tt-cap { padding: 40px 12px 12px 56px; }
.tt-cap strong { font-size: .85rem; }
.tt-cap p { margin: 4px 0 2px; font-size: .78rem; line-height: 1.3; max-height: 3.9em; overflow: hidden; }
.tt-cap small { font-size: .68rem; opacity: .8; }
.tt-cta { display: flex; justify-content: space-between; align-items: center; margin-top: 8px; padding: 7px 10px; border-radius: 6px; background: #FE2C55; font-size: .78rem; font-weight: 600; }
</style>
