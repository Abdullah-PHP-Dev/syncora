<template>
  <div class="fb sb" :dir="i18n.dir">

    <!-- ===== Header ===== -->
    <header class="fb-head">
      <div class="fb-head-main">
        <span class="fb-head-icon sb-head-icon"><i class="bx bxl-snapchat"></i></span>
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
      <div v-if="!boot.account.active" class="fb-alert is-warn"><i class="bx bx-error"></i> {{ t('boot.inactive', { status: boot.account.status || '-' }) }}</div>

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
                  <span class="fb-pick-ic"><i class="bx" :class="c.supported ? boot.objectives[c.objective].icon : 'bx-folder'"></i></span>
                  <span class="fb-pick-main">
                    <strong>{{ c.name }}</strong>
                    <small>{{ c.supported ? t('objectives.' + c.objective + '.label') : t('campaign.unsupported') }} · {{ known('entity_status', c.status, humanize(c.status)) }}<template v-if="c.daily_budget"> · {{ money(c.daily_budget) }}{{ t('steps.per_day') }}</template></small>
                  </span>
                  <i v-if="campaign.id === c.id" class="bx bxs-check-circle fb-pick-check"></i>
                </button>
                <div v-if="!filteredCampaigns.length" class="fb-mini-empty">
                  <i class="bx bx-folder"></i><span>{{ campaignQuery ? t('campaign.no_match') : t('campaign.none_yet') }}</span>
                  <button type="button" class="fb-link" @click="setCampaignMode('new')">{{ t('campaign.create_link') }}</button>
                </div>
              </div>
              <p v-if="selectedCampaign && selectedCampaign.status !== 'ACTIVE'" class="fb-hint is-warn"><i class="bx bx-pause-circle"></i> {{ t('campaign.existing_paused') }}</p>
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

            <template v-if="spec">
              <label class="fb-label mt-3">{{ t('campaign.destination') }}</label>
              <div class="sb-dests">
                <button v-for="d in destinationKeys" :key="d" type="button" class="sb-dest" :class="{ on: destination === d }" :disabled="destinationLocked && destination !== d" @click="setDestination(d)">
                  <i class="bx" :class="destinationIcon(d)"></i>
                  <span><strong>{{ t('destinations.' + d + '.label') }}</strong><small>{{ t('destinations.' + d + '.summary') }}</small></span>
                </button>
              </div>
              <div class="fb-guide"><i class="bx bx-bulb"></i><span><strong>{{ t('objectives.' + objective + '.label') }}:</strong> {{ t('objectives.' + objective + '.guidance') }}</span></div>
            </template>

            <template v-if="campaign.mode === 'new'">
              <label class="fb-label" for="sbCampName">{{ t('campaign.name') }}</label>
              <input id="sbCampName" v-model.trim="campaign.name" class="form-control" maxlength="375" :placeholder="objective ? t('objectives.' + objective + '.label') + ' - ' + today : t('campaign.name_placeholder')">
              <details class="fb-group sb-details">
                <summary><i class="bx bx-shield-quarter"></i> {{ t('campaign.caps_title') }} <span class="fb-opt">{{ t('common.optional') }}</span></summary>
                <div class="fb-grid-2 mt-2">
                  <div><label class="fb-label" for="sbDailyCap">{{ t('campaign.daily_cap', { currency }) }}</label><input id="sbDailyCap" v-model.number="campaign.daily_cap" type="number" :min="opt.min_campaign_cap" step="0.01" class="form-control"></div>
                  <div><label class="fb-label" for="sbLifeCap">{{ t('campaign.lifetime_cap', { currency }) }}</label><input id="sbLifeCap" v-model.number="campaign.lifetime_cap" type="number" :min="opt.min_campaign_cap" step="0.01" class="form-control"></div>
                </div>
                <p class="fb-hint"><i class="bx bx-info-circle"></i> {{ t('campaign.caps_hint', { amount: money(opt.min_campaign_cap) }) }}</p>
              </details>
            </template>
            <p v-if="fieldErrors.campaign" class="fb-field-err">{{ fieldErrors.campaign }}</p>
          </div>

          <!-- ============ 2. Ad squad (delivery) ============ -->
          <div v-show="current === 'adsquad'" class="fb-card">
            <div class="fb-card-h"><h5>{{ t('adsquad.title') }}</h5></div>
            <p class="fb-sub">{{ t('adsquad.sub') }}</p>

            <div class="fb-grid-2">
              <div>
                <label class="fb-label" for="sbSqName">{{ t('adsquad.name') }}</label>
                <input id="sbSqName" v-model.trim="adsquad.name" class="form-control" maxlength="375" :placeholder="t('defaults.adsquad', { date: today })">
              </div>
              <div v-if="goals.length">
                <label class="fb-label" for="sbGoal">{{ t('adsquad.goal') }} <i class="bx bx-help-circle fb-tip" :title="t('adsquad.goal_tip')"></i></label>
                <select id="sbGoal" v-model="adsquad.goal" class="form-control">
                  <option v-for="g in goals" :key="g" :value="g">{{ t('goals.' + g + '.label') }}</option>
                </select>
              </div>
            </div>
            <p v-if="adsquad.goal" class="fb-hint"><i class="bx bx-info-circle"></i> {{ t('goals.' + adsquad.goal + '.hint') }}</p>

            <!-- Conversion tracking -->
            <div class="fb-group">
              <h6><i class="bx bx-target-lock"></i> {{ t('adsquad.tracking_title') }} <span v-if="!needsPixel" class="fb-opt">{{ t('common.optional') }}</span></h6>
              <div v-if="!boot.pixels.length" class="fb-hint" :class="{ 'is-warn': needsPixel }"><i class="bx bx-info-circle"></i> {{ t('adsquad.no_pixels') }}</div>
              <div v-else class="fb-grid-2">
                <div>
                  <label class="fb-label" for="sbPixel">{{ t('adsquad.pixel') }}</label>
                  <select id="sbPixel" v-model="adsquad.pixel_id" class="form-control">
                    <option :value="null">{{ needsPixel ? t('adsquad.choose_pixel') : t('common.none') }}</option>
                    <option v-for="p in boot.pixels" :key="p.id" :value="p.id">{{ p.name }}{{ p.status && p.status !== 'ACTIVE' ? ' (' + humanize(p.status) + ')' : '' }}</option>
                  </select>
                </div>
                <div v-if="needsPixel">
                  <label class="fb-label" for="sbWindow">{{ t('adsquad.conversion_window') }}</label>
                  <select id="sbWindow" v-model="adsquad.conversion_window" class="form-control">
                    <option v-for="w in opt.conversion_windows" :key="w" :value="w">{{ t('windows.' + w) }}</option>
                  </select>
                </div>
              </div>
              <p v-if="needsPixel" class="fb-hint"><i class="bx bx-info-circle"></i> {{ t('adsquad.pixel_hint') }}</p>
            </div>

            <!-- Placements -->
            <div class="fb-group">
              <h6><i class="bx bx-layout"></i> {{ t('adsquad.placements') }}</h6>
              <div class="fb-seg">
                <button type="button" :class="{ on: adsquad.placement_mode === 'auto' }" @click="adsquad.placement_mode = 'auto'">{{ t('adsquad.auto_placements') }}</button>
                <button type="button" :class="{ on: adsquad.placement_mode === 'custom' }" @click="adsquad.placement_mode = 'custom'">{{ t('adsquad.custom_placements') }}</button>
              </div>
              <p class="fb-hint"><i class="bx bx-info-circle"></i> {{ adsquad.placement_mode === 'auto' ? t('adsquad.auto_hint') : t('adsquad.custom_hint') }}</p>
              <template v-if="adsquad.placement_mode === 'custom'">
                <div class="fb-place-grid">
                  <label v-for="p in opt.positions" :key="p" class="fb-check sb-pos"><input v-model="adsquad.positions" type="checkbox" :value="p"><i class="bx" :class="positionIcon(p)"></i><span><strong>{{ t('positions.' + p + '.label') }}</strong><small>{{ t('positions.' + p + '.hint') }}</small></span></label>
                </div>
                <template v-if="adsquad.positions.includes('INSTREAM')">
                  <label class="fb-label mt-2">{{ t('adsquad.content_types') }} <span class="fb-opt">{{ t('common.optional') }}</span></label>
                  <div class="fb-seg mb-2">
                    <button type="button" :class="{ on: adsquad.content_mode === 'exclude' }" @click="adsquad.content_mode = 'exclude'"><i class="bx bx-block"></i> {{ t('adsquad.content_exclude') }}</button>
                    <button type="button" :class="{ on: adsquad.content_mode === 'include' }" @click="adsquad.content_mode = 'include'"><i class="bx bx-check"></i> {{ t('adsquad.content_include') }}</button>
                  </div>
                  <div class="sb-chipset">
                    <button v-for="c in opt.content_types" :key="c" type="button" class="fb-chip-btn" :class="{ 'is-on': adsquad.content_types.includes(c), 'is-ex': adsquad.content_mode === 'exclude' }" @click="toggle(adsquad.content_types, c)">{{ t('content_types.' + c) }}</button>
                  </div>
                </template>
              </template>
            </div>

            <!-- Budget & schedule -->
            <div class="fb-group">
              <h6><i class="bx bx-wallet"></i> {{ t('adsquad.budget_title') }}</h6>
              <div class="fb-seg">
                <button type="button" :class="{ on: adsquad.budget_type === 'daily' }" @click="adsquad.budget_type = 'daily'">{{ t('adsquad.daily') }}</button>
                <button type="button" :class="{ on: adsquad.budget_type === 'lifetime' }" @click="adsquad.budget_type = 'lifetime'">{{ t('adsquad.lifetime') }}</button>
              </div>
              <div class="fb-grid-2 mt-2">
                <div>
                  <label class="fb-label" for="sbBudget">{{ adsquad.budget_type === 'daily' ? t('adsquad.daily_budget', { currency }) : t('adsquad.lifetime_budget', { currency }) }}</label>
                  <input id="sbBudget" v-model.number="adsquad.budget" type="number" :min="opt.min_squad_daily" step="0.01" class="form-control">
                </div>
                <div class="sb-budget-note"><i class="bx bx-info-circle"></i> {{ t('adsquad.min_budget', { amount: money(opt.min_squad_daily) }) }}</div>
              </div>
              <div class="fb-grid-2 mt-2">
                <div><label class="fb-label" for="sbStart">{{ t('adsquad.start') }}</label><input id="sbStart" v-model="adsquad.start_time" type="datetime-local" class="form-control"></div>
                <div><label class="fb-label" for="sbEnd">{{ t('adsquad.end') }} <span v-if="adsquad.budget_type === 'daily'" class="fb-opt">{{ t('common.optional') }}</span></label><input id="sbEnd" v-model="adsquad.end_time" type="datetime-local" class="form-control" :min="adsquad.start_time"></div>
              </div>
              <p class="fb-hint"><i class="bx bx-time-five"></i> {{ t('adsquad.timezone', { tz: boot.account.timezone }) }}</p>
              <p v-if="adsquad.budget_type === 'lifetime' && adsquad.budget && flightDays" class="fb-hint"><i class="bx bx-calculator"></i> {{ t('adsquad.per_day_estimate', { amount: money(adsquad.budget / flightDays), days: flightDays }) }}</p>

              <label class="fb-toggle mt-2" :class="{ 'is-off': adsquad.budget_type !== 'lifetime' }">
                <input v-model="adsquad.dayparting" type="checkbox" :disabled="adsquad.budget_type !== 'lifetime'">
                <span><strong>{{ t('adsquad.dayparting') }}</strong><small>{{ adsquad.budget_type === 'lifetime' ? t('adsquad.dayparting_desc') : t('adsquad.dayparting_lifetime') }}</small></span>
              </label>
              <div v-if="adsquad.dayparting && adsquad.budget_type === 'lifetime'" class="sb-daypart" role="grid">
                <div class="sb-dp-row sb-dp-head"><span></span><span v-for="h in 24" :key="h" class="sb-dp-h">{{ (h - 1) % 6 === 0 ? h - 1 : '' }}</span></div>
                <div v-for="d in opt.days" :key="d" class="sb-dp-row">
                  <button type="button" class="sb-dp-day" @click="toggleDay(d)">{{ t('days.' + d) }}</button>
                  <button v-for="h in 24" :key="h" type="button" class="sb-dp-cell" :class="{ on: (adsquad.schedule[d] || []).includes(h - 1) }" :aria-label="t('days.' + d) + ' ' + (h - 1) + ':00'" @click="toggleHour(d, h - 1)"></button>
                </div>
                <div class="sb-dp-actions">
                  <button type="button" class="fb-link" @click="presetSchedule('business')">{{ t('adsquad.preset_business') }}</button>
                  <button type="button" class="fb-link" @click="presetSchedule('evenings')">{{ t('adsquad.preset_evenings') }}</button>
                  <button type="button" class="fb-link" @click="presetSchedule('clear')">{{ t('adsquad.preset_clear') }}</button>
                </div>
                <p class="fb-hint"><i class="bx bx-info-circle"></i> {{ t('adsquad.dayparting_hint') }}</p>
              </div>
            </div>

            <!-- Bidding -->
            <div v-if="adsquad.goal" class="fb-group">
              <h6><i class="bx bx-trending-up"></i> {{ t('adsquad.bidding') }}</h6>
              <div class="fb-seg fb-seg-wrap">
                <button v-for="b in bidStrategies" :key="b" type="button" :class="{ on: adsquad.bid_strategy === b }" @click="adsquad.bid_strategy = b">{{ t('bids.' + b + '.label') }}</button>
              </div>
              <p class="fb-hint"><i class="bx bx-info-circle"></i> {{ t('bids.' + adsquad.bid_strategy + '.hint') }}</p>
              <div v-if="adsquad.bid_strategy !== 'AUTO_BID'" class="fb-grid-2">
                <div>
                  <label class="fb-label" for="sbBid">{{ adsquad.bid_strategy === 'TARGET_COST' ? t('adsquad.target_cost', { currency }) : t('adsquad.max_bid', { currency }) }}</label>
                  <input id="sbBid" v-model.number="adsquad.bid_amount" type="number" min="0.01" step="0.01" class="form-control">
                </div>
                <div class="sb-bid-est">
                  <button type="button" class="btn btn-sm fb-btn-ghost" :disabled="!targeting.countries.length || bidEstimating" @click="runBidEstimate">
                    <span v-if="bidEstimating" class="spinner-border spinner-border-sm"></span><i v-else class="bx bx-bulb"></i> {{ t('adsquad.suggest_bid') }}
                  </button>
                  <small v-if="bidEstimate && bidEstimate.available" class="sb-bid-range">{{ t('adsquad.suggested', { min: money(bidEstimate.min), max: money(bidEstimate.max) }) }}</small>
                  <small v-else-if="bidEstimate && bidEstimate.error" class="fb-field-err">{{ bidEstimate.error }}</small>
                  <small v-else-if="bidEstimate" class="fb-muted">{{ t('adsquad.no_suggestion') }}</small>
                  <small v-else-if="!targeting.countries.length" class="fb-muted">{{ t('adsquad.suggest_needs_country') }}</small>
                </div>
              </div>
              <template v-if="adsquad.bid_strategy === 'LOWEST_COST_WITH_MAX_BID'">
                <label class="fb-label mt-2">{{ t('adsquad.pacing') }}</label>
                <div class="fb-seg">
                  <button type="button" :class="{ on: adsquad.pacing === 'STANDARD' }" @click="adsquad.pacing = 'STANDARD'">{{ t('adsquad.standard') }}</button>
                  <button type="button" :class="{ on: adsquad.pacing === 'ACCELERATED' }" @click="adsquad.pacing = 'ACCELERATED'">{{ t('adsquad.accelerated') }}</button>
                </div>
                <p class="fb-hint"><i class="bx bx-info-circle"></i> {{ adsquad.pacing === 'STANDARD' ? t('adsquad.standard_hint') : t('adsquad.accelerated_hint') }}</p>
              </template>
            </div>

            <!-- Frequency cap -->
            <div v-if="frequencyAllowed" class="fb-group">
              <h6><i class="bx bx-repeat"></i> {{ t('adsquad.frequency_title') }} <span class="fb-opt">{{ t('common.optional') }}</span></h6>
              <div class="sb-freq">
                <input v-model.number="adsquad.frequency_cap" type="number" min="1" max="100" class="form-control" :aria-label="t('adsquad.frequency_count')" :placeholder="t('adsquad.frequency_count')">
                <span>{{ t('adsquad.frequency_every') }}</span>
                <input v-model.number="adsquad.frequency_interval" type="number" min="1" :max="adsquad.frequency_unit === 'HOURS' ? 720 : 30" class="form-control" :aria-label="t('adsquad.frequency_interval')" :disabled="!adsquad.frequency_cap">
                <select v-model="adsquad.frequency_unit" class="form-control" :aria-label="t('adsquad.frequency_unit')" :disabled="!adsquad.frequency_cap">
                  <option v-for="u in opt.frequency_units" :key="u" :value="u">{{ t('units.' + u) }}</option>
                </select>
              </div>
              <p class="fb-hint"><i class="bx bx-info-circle"></i> {{ t('adsquad.frequency_hint', { goal: t('goals.' + adsquad.goal + '.label') }) }}</p>
            </div>
            <p v-if="fieldErrors.adsquad" class="fb-field-err">{{ fieldErrors.adsquad }}</p>
          </div>

          <!-- ============ 3. Audience ============ -->
          <div v-show="current === 'audience'" class="fb-card">
            <div class="fb-card-h"><h5>{{ t('audience.title') }}</h5></div>
            <p class="fb-sub">{{ t('audience.sub') }}</p>

            <!-- Locations -->
            <div class="fb-group mt-0">
              <h6><i class="bx bx-map"></i> {{ t('audience.locations_title') }}</h6>
              <label class="fb-label">{{ t('audience.countries') }}</label>
              <search-picker v-model="targeting.countries" :options="lists.countries" :min-chars="0" :max="100" :placeholder="lists.loading.countries ? t('common.loading') : t('audience.search_countries')" />
              <p v-if="lists.errors.countries" class="fb-field-err">{{ lists.errors.countries }}</p>

              <template v-if="targeting.countries.length">
                <div class="fb-grid-2 mt-2">
                  <div>
                    <label class="fb-label">{{ t('audience.regions') }} <span class="fb-opt">{{ t('common.optional') }}</span></label>
                    <search-picker v-model="targeting.regions" :options="regionOptions" :min-chars="0" :max="500" :placeholder="lists.loading.regions ? t('common.loading') : t('audience.search_regions')">
                      <template #option="{ item }"><span class="sp-opt-main"><strong>{{ item.name }}</strong></span><span class="fb-tag">{{ item.country.toUpperCase() }}</span></template>
                    </search-picker>
                  </div>
                  <div>
                    <label class="fb-label">{{ t('audience.excluded_regions') }} <span class="fb-opt">{{ t('common.optional') }}</span></label>
                    <search-picker v-model="targeting.excluded_regions" :options="regionOptions" :min-chars="0" :max="500" chip-tone="exclude" :placeholder="t('audience.search_regions')">
                      <template #option="{ item }"><span class="sp-opt-main"><strong>{{ item.name }}</strong></span><span class="fb-tag">{{ item.country.toUpperCase() }}</span></template>
                    </search-picker>
                  </div>
                </div>
                <p v-if="lists.errors.regions" class="fb-field-err">{{ lists.errors.regions }}</p>
                <p class="fb-hint"><i class="bx bx-info-circle"></i> {{ t('audience.regions_hint') }}</p>
                <div class="fb-grid-2 mt-2">
                  <div>
                    <label class="fb-label">{{ t('audience.metros') }} <span class="fb-opt">{{ t('common.optional') }}</span></label>
                    <search-picker v-model="targeting.metros" :options="metroOptions" :min-chars="0" :max="500" :placeholder="lists.loading.metros ? t('common.loading') : (metroOptions.length ? t('audience.search_metros') : t('audience.no_metros'))">
                      <template #option="{ item }"><span class="sp-opt-main"><strong>{{ item.name }}</strong></span><span class="fb-tag">{{ item.country.toUpperCase() }}</span></template>
                    </search-picker>
                  </div>
                  <div>
                    <label class="fb-label">{{ t('audience.excluded_metros') }} <span class="fb-opt">{{ t('common.optional') }}</span></label>
                    <search-picker v-model="targeting.excluded_metros" :options="metroOptions" :min-chars="0" :max="500" chip-tone="exclude" :placeholder="t('audience.search_metros')" />
                  </div>
                </div>
                <p v-if="lists.errors.metros" class="fb-field-err">{{ lists.errors.metros }}</p>

                <details class="sb-details mt-2">
                  <summary><i class="bx bx-envelope"></i> {{ t('audience.postal_title') }} <span v-if="targeting.postal_codes.length" class="fb-tag">{{ targeting.postal_codes.length }}</span></summary>
                  <div class="sb-kw mt-2">
                    <textarea v-model="postalDraft" class="form-control" rows="2" dir="ltr" :placeholder="t('audience.postal_placeholder')"></textarea>
                    <div class="sb-kw-row">
                      <select v-model="postalCountry" class="form-control" :aria-label="t('audience.postal_country')">
                        <option v-for="c in targeting.countries" :key="c.id" :value="c.id">{{ c.name }}</option>
                      </select>
                      <button type="button" class="btn btn-sm btn-primary" :disabled="!postalDraft.trim() || !postalCountry" @click="addPostal"><i class="bx bx-plus"></i> {{ t('audience.add') }}</button>
                    </div>
                  </div>
                  <div v-if="targeting.postal_codes.length" class="fb-chips mt-2">
                    <span v-for="(p, i) in targeting.postal_codes" :key="p.country + p.code" class="fb-chip">{{ p.code }} <small class="sb-chip-type">{{ p.country.toUpperCase() }}</small><button type="button" :aria-label="t('common.remove')" @click="targeting.postal_codes.splice(i, 1)"><i class="bx bx-x"></i></button></span>
                  </div>
                </details>

                <details class="sb-details mt-2">
                  <summary><i class="bx bx-current-location"></i> {{ t('audience.radius_title') }} <span v-if="targeting.circles.length" class="fb-tag">{{ targeting.circles.length }}</span></summary>
                  <div class="sb-radius mt-2">
                    <input v-model.number="circleDraft.latitude" type="number" step="0.000001" min="-90" max="90" class="form-control" dir="ltr" :placeholder="t('audience.latitude')" :aria-label="t('audience.latitude')">
                    <input v-model.number="circleDraft.longitude" type="number" step="0.000001" min="-180" max="180" class="form-control" dir="ltr" :placeholder="t('audience.longitude')" :aria-label="t('audience.longitude')">
                    <input v-model.number="circleDraft.radius" type="number" step="0.1" min="0.1" max="100" class="form-control" dir="ltr" :placeholder="t('audience.radius_km')" :aria-label="t('audience.radius_km')">
                    <button type="button" class="btn btn-sm btn-primary" :disabled="!circleValid" @click="addCircle"><i class="bx bx-plus"></i> {{ t('audience.add') }}</button>
                  </div>
                  <p class="fb-hint"><i class="bx bx-info-circle"></i> {{ t('audience.radius_hint') }}</p>
                  <div v-if="targeting.circles.length" class="fb-chips mt-2">
                    <span v-for="(c, i) in targeting.circles" :key="i" class="fb-chip" dir="ltr">{{ c.latitude.toFixed(4) }}, {{ c.longitude.toFixed(4) }} · {{ c.radius }} km<button type="button" :aria-label="t('common.remove')" @click="targeting.circles.splice(i, 1)"><i class="bx bx-x"></i></button></span>
                  </div>
                </details>

                <details class="sb-details mt-2" @toggle="loadOption('location_categories')">
                  <summary><i class="bx bx-store"></i> {{ t('audience.loi_title') }} <span v-if="targeting.location_categories.length" class="fb-tag">{{ targeting.location_categories.length }}</span></summary>
                  <search-picker v-model="targeting.location_categories" class="mt-2" :options="lists.location_categories || []" :min-chars="0" :max="200" :placeholder="lists.loading.location_categories ? t('common.loading') : t('audience.search_loi')">
                    <template #option="{ item }"><span class="sp-opt-main"><strong>{{ item.name }}</strong> <small v-if="item.path && item.path !== item.name" class="fb-muted">{{ item.path }}</small></span></template>
                  </search-picker>
                  <p v-if="lists.errors.location_categories" class="fb-field-err">{{ lists.errors.location_categories }}</p>
                  <p class="fb-hint"><i class="bx bx-info-circle"></i> {{ t('audience.loi_hint') }}</p>
                </details>
              </template>
            </div>

            <!-- Demographics -->
            <div class="fb-group">
              <h6><i class="bx bx-user"></i> {{ t('audience.demographics_title') }}</h6>
              <div class="fb-grid-2">
                <div>
                  <label class="fb-label">{{ t('audience.gender') }}</label>
                  <div class="fb-seg fb-seg-full">
                    <button v-for="g in opt.genders" :key="g" type="button" :class="{ on: targeting.gender === g }" @click="targeting.gender = g">{{ t('genders.' + g) }}</button>
                  </div>
                </div>
                <div>
                  <label class="fb-label">{{ t('audience.age') }}</label>
                  <div class="sb-age">
                    <select v-model.number="targeting.min_age" class="form-control" :aria-label="t('audience.min_age')">
                      <option v-for="a in minAges" :key="a" :value="a">{{ a }}</option>
                    </select>
                    <span>–</span>
                    <select v-model="targeting.max_age" class="form-control" :aria-label="t('audience.max_age')">
                      <option :value="null">{{ t('audience.no_max') }}</option>
                      <option v-for="a in maxAges" :key="a" :value="a">{{ a }}</option>
                    </select>
                  </div>
                </div>
              </div>
              <label class="fb-label mt-2">{{ t('audience.languages') }} <span class="fb-opt">{{ targeting.countries.length > 1 ? t('audience.required_multi') : t('common.optional') }}</span></label>
              <search-picker v-model="targeting.languages" :options="lists.languages" :min-chars="0" :max="50" :placeholder="lists.loading.languages ? t('common.loading') : t('audience.search_languages')" />
              <p v-if="lists.errors.languages" class="fb-field-err">{{ lists.errors.languages }}</p>
              <label class="fb-label mt-2">{{ t('audience.advanced_demographics') }} <span class="fb-opt">{{ t('common.optional') }}</span></label>
              <search-picker v-model="targeting.advanced_demographics" :options="lists.advanced" :min-chars="0" :max="100" :placeholder="lists.loading.advanced ? t('common.loading') : t('audience.search_advanced')" />
              <p v-if="lists.errors.advanced" class="fb-field-err">{{ lists.errors.advanced }}</p>
              <p class="fb-hint"><i class="bx bx-info-circle"></i> {{ t('audience.advanced_hint') }}</p>
            </div>

            <!-- Interests -->
            <div class="fb-group">
              <h6><i class="bx bx-heart"></i> {{ t('audience.interests_title') }}</h6>
              <p v-if="!interestCountry" class="fb-muted">{{ t('audience.interests_need_country') }}</p>
              <template v-else>
                <p class="fb-muted">{{ t('audience.interests_sub', { country: interestCountry.name }) }}</p>
                <label class="fb-label">{{ t('audience.include') }}</label>
                <search-picker v-model="targeting.interests" :options="lists.interests" :min-chars="0" :max="200" :placeholder="lists.loading.interests ? t('common.loading') : t('audience.search_interests')">
                  <template #option="{ item }"><span class="sp-opt-main"><strong>{{ item.name }}</strong> <small v-if="item.path && item.path !== item.name" class="fb-muted">{{ item.path }}</small></span></template>
                </search-picker>
                <label class="fb-label mt-2">{{ t('audience.exclude') }}</label>
                <search-picker v-model="targeting.excluded_interests" :options="lists.interests" :min-chars="0" :max="200" chip-tone="exclude" :placeholder="t('audience.search_interests')" />
                <p v-if="lists.errors.interests" class="fb-field-err">{{ lists.errors.interests }}</p>
                <label class="fb-label mt-2">{{ t('audience.visitation') }} <span class="fb-opt">{{ t('common.optional') }}</span></label>
                <search-picker v-model="targeting.visitation" :options="lists.visitation || []" :min-chars="0" :max="200" :placeholder="lists.loading.visitation ? t('common.loading') : t('audience.search_visitation')">
                  <template #option="{ item }"><span class="sp-opt-main"><strong>{{ item.name }}</strong> <small v-if="item.path && item.path !== item.name" class="fb-muted">{{ item.path }}</small></span></template>
                </search-picker>
                <p v-if="lists.errors.visitation" class="fb-field-err">{{ lists.errors.visitation }}</p>
                <label class="fb-label mt-2">{{ t('audience.nielsen') }} <span class="fb-opt">{{ t('common.optional') }}</span></label>
                <search-picker v-model="targeting.nielsen" :options="lists.nielsen || []" :min-chars="0" :max="200" :placeholder="lists.loading.nielsen ? t('common.loading') : t('audience.search_nielsen')" />
                <p v-if="lists.errors.nielsen" class="fb-field-err">{{ lists.errors.nielsen }}</p>
                <p class="fb-hint"><i class="bx bx-info-circle"></i> {{ t('audience.partner_hint') }}</p>
              </template>
            </div>

            <!-- Custom audiences -->
            <div class="fb-group">
              <h6><i class="bx bx-group"></i> {{ t('audience.custom_title') }}</h6>
              <p class="fb-muted">{{ t('audience.custom_sub') }}</p>
              <div v-if="lists.loading.audiences" class="fb-inline-load"><span class="spinner-border spinner-border-sm"></span> {{ t('common.loading') }}</div>
              <p v-else-if="lists.errors.audiences" class="fb-field-err">{{ lists.errors.audiences }}</p>
              <p v-else-if="!lists.audiences.length" class="fb-muted">{{ t('audience.no_custom') }}</p>
              <template v-else>
                <label class="fb-label">{{ t('audience.include') }}</label>
                <search-picker v-model="targeting.audiences" :options="usableAudiences" :min-chars="0" :max="100" :placeholder="t('audience.search_audiences')">
                  <template #option="{ item }"><span class="sp-opt-main"><strong>{{ item.name }}</strong> <small v-if="item.size" class="fb-muted">~{{ compact(item.size) }}</small></span><span class="fb-tag">{{ known('audience_types', item.type, humanize(item.type)) }}</span></template>
                </search-picker>
                <label class="fb-label mt-2">{{ t('audience.exclude') }}</label>
                <search-picker v-model="targeting.excluded_audiences" :options="usableAudiences" :min-chars="0" :max="100" chip-tone="exclude" :placeholder="t('audience.search_audiences')">
                  <template #option="{ item }"><span class="sp-opt-main"><strong>{{ item.name }}</strong></span><span class="fb-tag">{{ known('audience_types', item.type, humanize(item.type)) }}</span></template>
                </search-picker>
                <p v-if="notReadyAudiences" class="fb-hint is-warn"><i class="bx bx-error"></i> {{ t('audience.not_ready', { count: notReadyAudiences }) }}</p>
                <p class="fb-hint"><i class="bx bx-info-circle"></i> {{ t('audience.custom_hint') }}</p>
              </template>
            </div>

            <!-- Devices -->
            <div class="fb-group">
              <h6><i class="bx bx-mobile"></i> {{ t('audience.devices_title') }}</h6>
              <label class="fb-label">{{ t('audience.os') }}</label>
              <div class="fb-seg">
                <button type="button" :class="{ on: !targeting.os_types.length }" @click="targeting.os_types = []">{{ t('audience.all_os') }}</button>
                <button type="button" :class="{ on: targeting.os_types.includes('iOS') }" @click="toggle(targeting.os_types, 'iOS')"><i class="bx bxl-apple"></i> iOS</button>
                <button type="button" :class="{ on: targeting.os_types.includes('ANDROID') }" @click="toggle(targeting.os_types, 'ANDROID')"><i class="bx bxl-android"></i> Android</button>
              </div>
              <div v-for="os in targeting.os_types" :key="os" class="sb-os mt-2">
                <span class="sb-os-l">{{ os === 'iOS' ? 'iOS' : 'Android' }}</span>
                <select v-model="targeting.os_versions[os].min" class="form-control" :aria-label="t('audience.os_min')">
                  <option :value="null">{{ t('audience.os_any_min') }}</option>
                  <option v-for="v in lists.osVersions[os] || []" :key="v" :value="v">{{ v }}</option>
                </select>
                <span>–</span>
                <select v-model="targeting.os_versions[os].max" class="form-control" :aria-label="t('audience.os_max')">
                  <option :value="null">{{ t('audience.os_any_max') }}</option>
                  <option v-for="v in lists.osVersions[os] || []" :key="v" :value="v">{{ v }}</option>
                </select>
              </div>
              <p v-if="lists.errors.osVersions" class="fb-field-err">{{ lists.errors.osVersions }}</p>
              <label class="fb-label mt-2" for="sbConn">{{ t('audience.connection') }}</label>
              <select id="sbConn" v-model="targeting.connection_type" class="form-control sb-narrow">
                <option :value="null">{{ t('audience.any_connection') }}</option>
                <option v-for="c in lists.connectionTypes" :key="c.id" :value="c.id">{{ known('connection_types', c.id, c.name) }}</option>
              </select>
              <div class="fb-grid-2 mt-2">
                <div>
                  <label class="fb-label">{{ t('audience.carriers') }} <span class="fb-opt">{{ t('common.optional') }}</span></label>
                  <search-picker v-model="targeting.carriers" :options="carrierOptions" :min-chars="0" :max="100" :placeholder="lists.loading.carriers ? t('common.loading') : t('audience.search_carriers')">
                    <template #option="{ item }"><span class="sp-opt-main"><strong>{{ item.name }}</strong></span><span v-if="item.country" class="fb-tag">{{ item.country.toUpperCase() }}</span></template>
                  </search-picker>
                </div>
                <div>
                  <label class="fb-label">{{ t('audience.device_makes') }} <span class="fb-opt">{{ t('common.optional') }}</span></label>
                  <search-picker v-model="targeting.device_makes" :options="lists.device_makes || []" :min-chars="0" :max="200" :placeholder="lists.loading.device_makes ? t('common.loading') : t('audience.search_makes')" />
                </div>
              </div>
              <p v-if="lists.errors.carriers || lists.errors.device_makes" class="fb-field-err">{{ lists.errors.carriers || lists.errors.device_makes }}</p>
              <p v-if="spec && destination === 'app_install' && !targeting.os_types.length" class="fb-hint"><i class="bx bx-bulb"></i> {{ t('audience.app_os_tip') }}</p>
            </div>

            <!-- Expansion & compliance -->
            <div class="fb-group">
              <h6><i class="bx bx-expand-alt"></i> {{ t('audience.expansion_title') }}</h6>
              <label class="fb-toggle"><input v-model="targeting.interest_expansion" type="checkbox"><span><strong>{{ t('audience.interest_expansion') }}</strong><small>{{ t('audience.interest_expansion_desc') }}</small></span></label>
              <label class="fb-toggle mt-2"><input v-model="targeting.audience_expansion" type="checkbox"><span><strong>{{ t('audience.audience_expansion') }}</strong><small>{{ t('audience.audience_expansion_desc') }}</small></span></label>
              <label class="fb-toggle mt-2"><input v-model="targeting.regulated_content" type="checkbox"><span><strong>{{ t('audience.regulated') }}</strong><small>{{ t('audience.regulated_desc') }}</small></span></label>
            </div>
            <p v-if="fieldErrors.audience" class="fb-field-err">{{ fieldErrors.audience }}</p>
          </div>

          <!-- ============ 4. Ad ============ -->
          <div v-show="current === 'ad'" class="fb-card">
            <div class="fb-card-h"><h5>{{ t('ad.title') }}</h5></div>

            <label class="fb-label">{{ t('ad.profile') }} <i class="bx bx-help-circle fb-tip" :title="t('ad.profile_tip')"></i></label>
            <div v-if="boot.profiles.length" class="fb-pick-list">
              <button v-for="p in boot.profiles" :key="p.id" type="button" class="fb-pick" :class="{ on: ad.profile_id === p.id }" @click="ad.profile_id = p.id">
                <span class="fb-pick-ic sb-pp"><img v-if="p.logo" :src="p.logo" alt=""><i v-else class="bx bxl-snapchat"></i></span>
                <span class="fb-pick-main"><strong>{{ p.name }}</strong><small>{{ t('ad.public_profile') }}</small></span>
                <i v-if="ad.profile_id === p.id" class="bx bxs-check-circle fb-pick-check"></i>
              </button>
            </div>
            <template v-else>
              <p class="fb-hint is-warn"><i class="bx bx-error"></i> {{ t('ad.profiles_unavailable') }}<template v-if="boot.profiles_error"> ({{ boot.profiles_error }})</template></p>
              <input v-model.trim="ad.profile_id" class="form-control" dir="ltr" maxlength="36" :placeholder="t('ad.profile_id_placeholder')" :aria-label="t('ad.profile')">
            </template>

            <label class="fb-label mt-3">{{ t('ad.media') }} <span class="fb-opt">{{ t('ad.media_rule', { n: opt.max_ads }) }}</span></label>
            <div class="fb-media-row">
              <div v-for="(m, i) in ad.media" :key="m.id" class="fb-media">
                <img v-if="m.type === 'image' || m.thumbnail_url" :src="m.thumbnail_url || m.url" alt="">
                <span v-else class="fb-media-ph"><i class="bx bx-play"></i></span>
                <div><strong>{{ m.original_name }}</strong><small>{{ m.type === 'video' ? t('ad.video') : t('ad.image') }}<template v-if="m.width"> · {{ m.width }}×{{ m.height }}</template><template v-if="m.width && !isVertical(m)"> · <span class="sb-warn-t">{{ t('ad.not_vertical') }}</span></template></small></div>
                <button type="button" class="fb-icon-btn" :aria-label="t('common.remove')" @click="ad.media.splice(i, 1)"><i class="bx bx-trash"></i></button>
              </div>
              <div v-if="ad.media.length < opt.max_ads" class="fb-media-actions">
                <button type="button" class="btn btn-sm fb-btn-ghost" @click="openGallery('top')"><i class="bx bx-images"></i> {{ t('ad.from_gallery') }}</button>
              </div>
            </div>
            <p class="fb-hint"><i class="bx bx-info-circle"></i> {{ t('ad.media_specs', { mb: opt.max_video_mb }) }}</p>
            <p v-if="ad.media.length > 1" class="fb-hint"><i class="bx bx-copy-alt"></i> {{ t('ad.multi_hint', { n: ad.media.length }) }}</p>
            <p v-if="mediaError" class="fb-field-err">{{ mediaError }}</p>

            <div class="fb-grid-2 mt-2">
              <div>
                <label class="fb-label" for="sbHeadline">{{ t('ad.headline') }}</label>
                <input id="sbHeadline" v-model="ad.headline" class="form-control" maxlength="34">
                <small class="fb-count" :class="{ 'is-warn': ad.headline.length > 30 }">{{ ad.headline.length }} / 34</small>
              </div>
              <div>
                <label class="fb-label" for="sbBrand">{{ t('ad.brand_name') }} <span class="fb-opt">{{ t('common.optional') }}</span></label>
                <input id="sbBrand" v-model="ad.brand_name" class="form-control" maxlength="32" :placeholder="profileName">
                <small class="fb-count">{{ ad.brand_name.length }} / 32</small>
              </div>
            </div>

            <!-- Destination -->
            <template v-if="destination === 'web'">
              <div class="fb-grid-2 mt-2">
                <div>
                  <label class="fb-label" for="sbUrl">{{ t('ad.url') }}</label>
                  <input id="sbUrl" v-model.trim="ad.url" type="url" class="form-control" placeholder="https://" dir="ltr">
                </div>
                <div>
                  <label class="fb-label" for="sbCta">{{ t('ad.cta') }}</label>
                  <select id="sbCta" v-model="ad.cta" class="form-control"><option v-for="c in ctaOptions" :key="c" :value="c">{{ t('ctas.' + c) }}</option></select>
                </div>
              </div>
              <label class="fb-check is-sm mt-2"><input v-model="ad.block_preload" type="checkbox"> {{ t('ad.block_preload') }}</label>
            </template>

            <div v-if="destination === 'app_install' || destination === 'deep_link'" class="fb-group">
              <h6><i class="bx bx-mobile-alt"></i> {{ t('ad.app_title') }}</h6>
              <div class="fb-grid-2">
                <div><label class="fb-label" for="sbAppName">{{ t('ad.app_name') }}</label><input id="sbAppName" v-model="app.app_name" class="form-control" maxlength="30"></div>
                <div>
                  <label class="fb-label" for="sbCtaApp">{{ t('ad.cta') }}</label>
                  <select id="sbCtaApp" v-model="ad.cta" class="form-control"><option v-for="c in ctaOptions" :key="c" :value="c">{{ t('ctas.' + c) }}</option></select>
                </div>
                <div><label class="fb-label" for="sbIos"><i class="bx bxl-apple"></i> {{ t('ad.ios_id') }}</label><input id="sbIos" v-model.trim="app.ios_app_id" class="form-control" dir="ltr" placeholder="447188370"></div>
                <div><label class="fb-label" for="sbAnd"><i class="bx bxl-play-store"></i> {{ t('ad.android_id') }}</label><input id="sbAnd" v-model.trim="app.android_app_url" class="form-control" dir="ltr" placeholder="com.example.app"></div>
              </div>
              <p class="fb-hint"><i class="bx bx-info-circle"></i> {{ t('ad.app_hint') }}</p>
              <template v-if="destination === 'deep_link'">
                <div class="fb-grid-2">
                  <div><label class="fb-label" for="sbDeep">{{ t('ad.deep_link') }}</label><input id="sbDeep" v-model.trim="app.deep_link_uri" class="form-control" dir="ltr" placeholder="myapp://product/42"></div>
                  <div><label class="fb-label" for="sbFallback">{{ t('ad.web_fallback') }} <span class="fb-opt">{{ t('common.optional') }}</span></label><input id="sbFallback" v-model.trim="app.web_fallback_url" type="url" class="form-control" dir="ltr" placeholder="https://"></div>
                </div>
                <p class="fb-hint"><i class="bx bx-info-circle"></i> {{ app.web_fallback_url ? t('ad.fallback_web') : t('ad.fallback_store') }}</p>
              </template>
              <label class="fb-label mt-2">{{ t('ad.icon') }}</label>
              <div class="fb-media-row">
                <div v-if="app.icon" class="fb-media">
                  <img :src="app.icon.thumbnail_url || app.icon.url" alt="">
                  <div><strong>{{ app.icon.original_name }}</strong><small><template v-if="app.icon.width">{{ app.icon.width }}×{{ app.icon.height }}</template></small></div>
                  <button type="button" class="fb-icon-btn" :aria-label="t('common.remove')" @click="app.icon = null"><i class="bx bx-trash"></i></button>
                </div>
                <div v-else class="fb-media-actions"><button type="button" class="btn btn-sm fb-btn-ghost" @click="openGallery('icon')"><i class="bx bx-image-add"></i> {{ t('ad.pick_icon') }}</button></div>
              </div>
              <p class="fb-hint"><i class="bx bx-info-circle"></i> {{ t('ad.icon_specs') }}</p>
            </div>

            <div class="fb-grid-2 mt-2">
              <div>
                <label class="fb-label" for="sbAdName">{{ t('ad.name') }} <span class="fb-opt">{{ t('common.optional') }}</span></label>
                <input id="sbAdName" v-model.trim="ad.name" class="form-control" maxlength="300">
              </div>
              <div class="sb-pt">
                <label class="fb-check is-sm"><input v-model="ad.shareable" type="checkbox"> {{ t('ad.shareable') }}</label>
              </div>
            </div>
            <p class="fb-hint"><i class="bx bx-shield"></i> {{ t('ad.review_hint') }}</p>
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
            <p class="fb-hint"><i class="bx bx-info-circle"></i> {{ status === 'ACTIVE' ? (campaign.mode === 'existing' ? t('review.publish_existing_hint') : t('review.publish_hint')) : t('review.paused_hint') }}</p>

            <div v-if="blockers.length" class="fb-blockers">
              <strong><i class="bx bx-error-circle"></i> {{ t('review.fix_before') }}</strong>
              <ul><li v-for="b in blockers" :key="b.text"><button type="button" class="fb-link" @click="goTo(b.step)">{{ b.text }}</button></li></ul>
            </div>

            <div v-if="result && result.success" class="fb-result is-ok">
              <i class="bx bx-check-circle"></i>
              <div>
                <strong>{{ result.message }}</strong>
                <p>{{ result.status === 'ACTIVE' ? t('result.live') : t('result.saved_paused') }} {{ t('result.adsquad_id', { id: result.adsquad_id }) }}</p>
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
            <div class="sb-phone">
              <img v-if="previewImage" :src="previewImage" alt="" class="sb-phone-media">
              <div v-else class="sb-phone-ph"><i class="bx bx-image"></i><small>{{ t('side.media_placeholder') }}</small></div>
              <span v-if="ad.media[0] && ad.media[0].type === 'video'" class="sb-phone-play"><i class="bx bx-play"></i></span>
              <div class="sb-phone-top">
                <span class="sb-phone-av"><img v-if="selectedProfile && selectedProfile.logo" :src="selectedProfile.logo" alt=""><i v-else class="bx bxl-snapchat"></i></span>
                <span class="sb-phone-id"><strong>{{ ad.brand_name || profileName || boot.account.name }}</strong><small>{{ ad.headline || t('side.headline_placeholder') }}</small></span>
                <span class="sb-phone-ad">{{ t('side.ad') }}</span>
              </div>
              <div v-if="destination && destination !== 'none'" class="sb-phone-cta">
                <span class="sb-phone-cta-btn"><i class="bx" :class="destination === 'web' ? 'bx-link-external' : 'bx-download'"></i> {{ ad.cta ? t('ctas.' + ad.cta) : t('side.cta_placeholder') }}</span>
                <small v-if="destination === 'web'" dir="ltr">{{ domain || 'example.com' }}</small>
                <small v-else>{{ app.app_name || t('side.app_placeholder') }}</small>
              </div>
            </div>
            <p v-if="ad.media.length > 1" class="fb-muted mt-2 mb-0">{{ t('side.more_media', { n: ad.media.length - 1 }) }}</p>
          </div>
          <div class="fb-card">
            <div class="fb-side-h"><strong><i class="bx bx-target-lock"></i> {{ t('side.summary') }}</strong></div>
            <ul class="sb-sum">
              <li v-for="s in summaryLines" :key="s.icon + s.text"><i class="bx" :class="s.icon"></i> {{ s.text }}</li>
            </ul>
            <div class="sb-estimate">
              <button type="button" class="btn btn-sm fb-btn-ghost w-100" :disabled="!canEstimate || estimating" @click="runEstimate">
                <span v-if="estimating" class="spinner-border spinner-border-sm"></span><i v-else class="bx bx-group"></i> {{ t('side.estimate') }}
              </button>
              <p v-if="estimate && estimate.available" class="sb-est-v">{{ compact(estimate.min) }} – {{ compact(estimate.max) }} <small>{{ t('side.snapchatters') }}</small></p>
              <p v-else-if="estimate && estimate.error" class="fb-field-err">{{ estimate.error }}</p>
              <p v-else-if="estimate" class="fb-muted">{{ t('side.estimate_unavailable') }}</p>
              <small v-if="!canEstimate" class="fb-muted">{{ t('side.estimate_needs') }}</small>
            </div>
          </div>
        </aside>
      </div>

      <media-picker ref="picker" :urls="galleryUrls" :multiple="pickerFor === 'top'" :max="pickerFor === 'icon' ? 1 : Math.max(1, opt.max_ads - ad.media.length)" :type-lock="pickerTypeLock" :platforms="['snapchat']" @select="onPicked" />
    </template>
  </div>
</template>

<script setup>
// Snapchat campaign builder (Snapchat Marketing API through
// SnapchatAdBuilderController). Same building blocks as the Facebook, TikTok
// and X builders: shared styles, SearchPicker, MediaPicker and localStorage
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
const postalDraft = ref('');
const postalCountry = ref(null);
const circleDraft = reactive({ latitude: null, longitude: null, radius: 5 });
const mediaError = ref('');
const estimate = ref(null);
const estimating = ref(false);
const bidEstimate = ref(null);
const bidEstimating = ref(false);
const picker = ref(null);
const pickerFor = ref('top');
const stepErrors = reactive({});
const fieldErrors = reactive({});

const objective = ref(null);
const destination = ref(null);
const status = ref('PAUSED');
const campaign = reactive({ mode: 'new', id: null, name: '', daily_cap: null, lifetime_cap: null });
const adsquad = reactive({
  name: '', goal: null, bid_strategy: 'AUTO_BID', bid_amount: null, pacing: 'STANDARD', budget_type: 'daily', budget: 20,
  start_time: '', end_time: '', placement_mode: 'auto', positions: [], content_mode: 'exclude', content_types: [],
  pixel_id: null, conversion_window: 'SWIPE_28DAY_VIEW_1DAY', frequency_cap: null, frequency_interval: 1, frequency_unit: 'DAYS',
  dayparting: false, schedule: {},
});
const targeting = reactive({
  countries: [], regions: [], excluded_regions: [], metros: [], excluded_metros: [], postal_codes: [], location_categories: [], visitation: [], nielsen: [], carriers: [], device_makes: [], circles: [], gender: 'ALL', min_age: 13, max_age: null,
  languages: [], advanced_demographics: [], interests: [], excluded_interests: [], os_types: [], os_versions: { iOS: { min: null, max: null }, ANDROID: { min: null, max: null } },
  connection_type: null, audiences: [], excluded_audiences: [], regulated_content: false, interest_expansion: false, audience_expansion: false,
});
const app = reactive({ app_name: '', ios_app_id: '', android_app_url: '', icon: null, deep_link_uri: '', web_fallback_url: '' });
const ad = reactive({ name: '', profile_id: null, headline: '', brand_name: '', media: [], cta: null, url: '', shareable: true, block_preload: false });

const lists = reactive({ campaigns: [], audiences: [], countries: [], languages: [], advanced: [], interests: [], regions: {}, metros: {}, osVersions: {}, connectionTypes: [], loading: {}, errors: {} });

// ---------------------------------------------------------------- derived
const opt = computed(() => (boot.value ? boot.value.options : {}));
const spec = computed(() => (objective.value && boot.value ? boot.value.objectives[objective.value] : null));
const destinationKeys = computed(() => (spec.value ? Object.keys(spec.value.destinations) : []));
const goals = computed(() => (spec.value && destination.value ? spec.value.destinations[destination.value] || [] : []));
const selectedCampaign = computed(() => lists.campaigns.find((c) => c.id === campaign.id) || null);
const currency = computed(() => (boot.value ? boot.value.account.currency : 'USD'));
const objectiveLocked = computed(() => campaign.mode === 'existing' && !!selectedCampaign.value);
const destinationLocked = computed(() => objectiveLocked.value && objective.value === 'APP_PROMOTION' && !!(selectedCampaign.value && selectedCampaign.value.promotion_type));
const filteredCampaigns = computed(() => { const q = campaignQuery.value.toLowerCase(); return lists.campaigns.filter((c) => !q || c.name.toLowerCase().includes(q)); });
const needsPixel = computed(() => !!(boot.value && adsquad.goal && opt.value.pixel_goals.includes(adsquad.goal)));
const frequencyAllowed = computed(() => !!(boot.value && adsquad.goal && opt.value.frequency_goals.includes(adsquad.goal)));
const bidStrategies = computed(() => (boot.value ? opt.value.bid_strategies.filter((b) => !(b === 'TARGET_COST' && adsquad.goal === 'IMPRESSIONS')) : []));
const ctaOptions = computed(() => (boot.value && destination.value ? opt.value.ctas[destination.value] || [] : []));
const minAges = computed(() => (boot.value ? range(opt.value.min_age[0], opt.value.min_age[1]) : []));
const maxAges = computed(() => (boot.value ? range(Math.max(opt.value.max_age[0], targeting.min_age || 13), opt.value.max_age[1]) : []));
const usableAudiences = computed(() => lists.audiences.filter((a) => a.usable));
const notReadyAudiences = computed(() => lists.audiences.filter((a) => !a.usable).length);
const metroOptions = computed(() => targeting.countries.flatMap((c) => lists.metros[c.id] || []));
const carrierOptions = computed(() => { const cc = targeting.countries.map((c) => c.id); return (lists.carriers || []).filter((c) => !c.country || cc.includes(c.country)); });
const regionOptions = computed(() => targeting.countries.flatMap((c) => lists.regions[c.id] || []));
const interestCountry = computed(() => targeting.countries[0] || null);
const selectedProfile = computed(() => (boot.value ? boot.value.profiles.find((p) => p.id === ad.profile_id) || null : null));
const profileName = computed(() => (selectedProfile.value ? selectedProfile.value.name : ''));
const pickerTypeLock = computed(() => (pickerFor.value === 'icon' ? 'image' : (adsquad.goal && opt.value.video_goals.includes(adsquad.goal) ? 'video' : '')));
const previewImage = computed(() => { const m = ad.media[0]; return m ? (m.type === 'image' ? m.url : m.thumbnail_url) : null; });
const domain = computed(() => { try { return new URL(ad.url).hostname.replace(/^www\./, ''); } catch (e) { return ''; } });
const circleValid = computed(() => Number.isFinite(circleDraft.latitude) && Math.abs(circleDraft.latitude) <= 90 && Number.isFinite(circleDraft.longitude) && Math.abs(circleDraft.longitude) <= 180 && circleDraft.radius >= 0.1 && circleDraft.radius <= 100);
const canEstimate = computed(() => !!(targeting.countries.length && adsquad.goal));
const flightDays = computed(() => {
  if (!adsquad.start_time || !adsquad.end_time) return 0;
  const days = Math.ceil((new Date(adsquad.end_time) - new Date(adsquad.start_time)) / 86400000);
  return days > 0 ? days : 0;
});
const scheduleHours = computed(() => Object.values(adsquad.schedule).reduce((n, hours) => n + hours.length, 0));
const today = new Date().toISOString().slice(0, 10);

const steps = computed(() => [
  { key: 'campaign', label: t('steps.campaign'), summary: objective.value ? t('objectives.' + objective.value + '.label') : t('steps.pick_objective') },
  { key: 'adsquad', label: t('steps.adsquad'), summary: adsquad.goal ? t('goals.' + adsquad.goal + '.label') : t('steps.delivery') },
  { key: 'audience', label: t('steps.audience'), summary: targeting.countries.length ? t('steps.countries', { count: targeting.countries.length }) : t('steps.who') },
  { key: 'ad', label: t('steps.ad'), summary: ad.media.length ? t('steps.creatives', { count: ad.media.length }) : t('steps.creative') },
  { key: 'review', label: t('steps.review'), summary: t('steps.review_sub') },
]);
const current = computed(() => steps.value[step.value].key);

const budgetLine = computed(() => {
  if (!adsquad.budget) return '';
  return money(adsquad.budget) + (adsquad.budget_type === 'daily' ? t('steps.per_day') : ' ' + t('summary.lifetime'));
});
const summaryLines = computed(() => {
  const out = [];
  const where = targeting.countries.map((c) => c.name).slice(0, 3).join(', ') + (targeting.countries.length > 3 ? ' +' + (targeting.countries.length - 3) : '');
  out.push({ icon: 'bx-map', text: where || t('summary.no_location') });
  const narrow = [];
  if (targeting.regions.length) narrow.push(t('summary.regions', { count: targeting.regions.length }));
  if (targeting.metros.length) narrow.push(t('summary.metros', { count: targeting.metros.length }));
  if (targeting.location_categories.length) narrow.push(t('summary.loi', { count: targeting.location_categories.length }));
  if (targeting.postal_codes.length) narrow.push(t('summary.postal', { count: targeting.postal_codes.length }));
  if (targeting.circles.length) narrow.push(t('summary.radius', { count: targeting.circles.length }));
  if (narrow.length) out.push({ icon: 'bx-current-location', text: narrow.join(' ∪ ') });
  out.push({ icon: 'bx-user', text: [t('genders.' + targeting.gender), ageText()].join(' · ') + (targeting.languages.length ? ' · ' + targeting.languages.map((l) => l.name).join(', ') : '') });
  const include = [];
  if (targeting.interests.length) include.push(t('summary.interests', { count: targeting.interests.length }));
  if (targeting.visitation.length || targeting.nielsen.length) include.push(t('summary.partner', { count: targeting.visitation.length + targeting.nielsen.length }));
  if (targeting.advanced_demographics.length) include.push(t('summary.advanced', { count: targeting.advanced_demographics.length }));
  if (targeting.audiences.length) include.push(t('summary.custom', { count: targeting.audiences.length }));
  if (include.length) out.push({ icon: 'bx-heart', text: include.join(' · ') });
  const exclude = [];
  if (targeting.excluded_regions.length) exclude.push(t('summary.regions', { count: targeting.excluded_regions.length }));
  if (targeting.excluded_metros.length) exclude.push(t('summary.metros', { count: targeting.excluded_metros.length }));
  if (targeting.excluded_interests.length) exclude.push(t('summary.interests', { count: targeting.excluded_interests.length }));
  if (targeting.excluded_audiences.length) exclude.push(t('summary.custom', { count: targeting.excluded_audiences.length }));
  if (exclude.length) out.push({ icon: 'bx-block', text: t('summary.excluding', { list: exclude.join(', ') }) });
  const devices = targeting.os_types.map((os) => (os === 'iOS' ? 'iOS' : 'Android') + osRange(os));
  if (targeting.carriers.length) devices.push(t('summary.carriers', { count: targeting.carriers.length }));
  if (targeting.device_makes.length) devices.push(t('summary.makes', { count: targeting.device_makes.length }));
  if (targeting.connection_type) devices.push(known('connection_types', targeting.connection_type, targeting.connection_type));
  if (devices.length) out.push({ icon: 'bx-mobile', text: devices.join(', ') });
  const exp = [];
  if (targeting.interest_expansion) exp.push(t('summary.interest_expansion'));
  if (targeting.audience_expansion) exp.push(t('summary.audience_expansion'));
  if (exp.length) out.push({ icon: 'bx-expand-alt', text: exp.join(', ') });
  if (budgetLine.value) out.push({ icon: 'bx-wallet', text: budgetLine.value });
  return out;
});

const reviewRows = computed(() => [
  { key: 'objective', step: 0, icon: 'bx-flag', label: t('review.objective'), value: objective.value ? t('objectives.' + objective.value + '.label') + (destination.value ? ' · ' + t('destinations.' + destination.value + '.label') : '') : '-' },
  { key: 'campaign', step: 0, icon: 'bx-folder', label: t('review.campaign'), value: campaign.mode === 'existing' ? (selectedCampaign.value ? selectedCampaign.value.name : '-') : (campaign.name || t('review.auto_name')) },
  { key: 'goal', step: 1, icon: 'bx-target-lock', label: t('review.goal'), value: adsquad.goal ? t('goals.' + adsquad.goal + '.label') + ' · ' + t('bids.' + adsquad.bid_strategy + '.label') + (adsquad.bid_strategy !== 'AUTO_BID' ? ' ' + money(adsquad.bid_amount) : '') : '-' },
  { key: 'budget', step: 1, icon: 'bx-wallet', label: t('review.budget'), value: budgetLine.value || '-' },
  { key: 'schedule', step: 1, icon: 'bx-calendar', label: t('review.schedule'), value: (adsquad.start_time || t('review.now')).replace('T', ' ') + ' → ' + (adsquad.end_time ? adsquad.end_time.replace('T', ' ') : t('review.ongoing')) + (adsquad.dayparting && scheduleHours.value ? ' · ' + t('review.dayparted', { count: scheduleHours.value }) : '') },
  { key: 'placements', step: 1, icon: 'bx-layout', label: t('review.placements'), value: adsquad.placement_mode === 'auto' ? t('adsquad.auto_placements') : adsquad.positions.map((p) => t('positions.' + p + '.label')).join(', ') || '-' },
  { key: 'tracking', step: 1, icon: 'bx-line-chart', label: t('review.tracking'), value: adsquad.pixel_id ? (boot.value.pixels.find((p) => p.id === adsquad.pixel_id) || {}).name || adsquad.pixel_id : t('common.none') },
  { key: 'audience', step: 2, icon: 'bx-group', label: t('review.audience'), value: summaryLines.value.filter((s) => s.icon !== 'bx-wallet').map((s) => s.text).join(' · ') },
  { key: 'creative', step: 3, icon: 'bx-image', label: t('review.creative'), value: (ad.headline || '-') + ' · ' + t('review.media', { count: ad.media.length }) },
  { key: 'destination', step: 3, icon: 'bx-link', label: t('review.destination'), value: destinationValue() },
]);

// ---------------------------------------------------------------- validation
function validate() {
  const p = [];
  const add = (s, key, params) => p.push({ step: s, text: t('errors.' + key, params) });
  const o = opt.value;
  if (!objective.value) add(0, 'objective');
  if (objective.value && !destination.value) add(0, 'destination');
  if (campaign.mode === 'existing' && !campaign.id) add(0, 'campaign');
  if (campaign.mode === 'new') {
    if (campaign.daily_cap && campaign.daily_cap < o.min_campaign_cap) add(0, 'campaign_cap', { amount: money(o.min_campaign_cap) });
    if (campaign.lifetime_cap && campaign.lifetime_cap < o.min_campaign_cap) add(0, 'campaign_cap', { amount: money(o.min_campaign_cap) });
    if (campaign.daily_cap && adsquad.budget_type === 'daily' && adsquad.budget && campaign.daily_cap < 0.25 * adsquad.budget) add(0, 'campaign_cap_low');
  }
  if (destination.value && !adsquad.goal) add(1, 'goal');
  if (needsPixel.value && !adsquad.pixel_id) add(1, 'pixel');
  if (!(adsquad.budget >= o.min_squad_daily)) add(1, 'budget', { amount: money(o.min_squad_daily) });
  if (adsquad.budget_type === 'lifetime' && !adsquad.end_time) add(1, 'lifetime_end');
  if (adsquad.end_time && adsquad.start_time && adsquad.end_time <= adsquad.start_time) add(1, 'end_after');
  if (adsquad.bid_strategy !== 'AUTO_BID' && !(adsquad.bid_amount > 0)) add(1, 'bid');
  if (adsquad.placement_mode === 'custom') {
    if (!adsquad.positions.length) add(1, 'positions');
    else if (adsquad.positions.length === 1 && adsquad.positions[0] === 'CHAT_FEED') add(1, 'chat_only');
  }
  if (adsquad.dayparting && adsquad.budget_type === 'lifetime' && !scheduleHours.value) add(1, 'schedule');
  if (frequencyAllowed.value && adsquad.frequency_cap) {
    const maxI = adsquad.frequency_unit === 'HOURS' ? 720 : 30;
    if (!(adsquad.frequency_cap >= 1 && adsquad.frequency_cap <= 100) || !(adsquad.frequency_interval >= 1 && adsquad.frequency_interval <= maxI)) add(1, 'frequency', { max: maxI });
  }
  if (!targeting.countries.length) add(2, 'countries');
  if (targeting.countries.length > 1 && !targeting.languages.length) add(2, 'multi_country_language');
  if (targeting.max_age && targeting.max_age < targeting.min_age) add(2, 'age_range');
  if (!ad.profile_id) add(3, 'profile');
  else if (!/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i.test(ad.profile_id)) add(3, 'profile_format');
  if (!ad.media.length) add(3, 'media');
  if (adsquad.goal && o.video_goals.includes(adsquad.goal) && ad.media.some((m) => m.type !== 'video')) add(3, 'needs_video');
  if (ad.media.some((m) => m.type === 'video' && m.size > o.max_video_mb * 1048576)) add(3, 'video_size', { mb: o.max_video_mb });
  if (!ad.headline.trim()) add(3, 'headline');
  if (destination.value && destination.value !== 'none' && !ad.cta) add(3, 'cta');
  if (destination.value === 'web' && !/^https:\/\/\S+\.\S+/.test(ad.url)) add(3, 'url');
  if (destination.value === 'app_install' || destination.value === 'deep_link') {
    if (!app.app_name.trim()) add(3, 'app_name');
    if (!app.ios_app_id && !app.android_app_url) add(3, 'app_id');
    if (app.ios_app_id && !/^\d{5,12}$/.test(app.ios_app_id)) add(3, 'ios_id');
    if (app.android_app_url && !/^[A-Za-z][A-Za-z0-9_]*(\.[A-Za-z0-9_]+)+$/.test(app.android_app_url)) add(3, 'android_id');
    if (!app.icon) add(3, 'icon');
    else if (app.icon.width && (app.icon.width !== app.icon.height || app.icon.width < 200 || app.icon.width > 2000)) add(3, 'icon_square');
  }
  if (destination.value === 'deep_link') {
    if (!/^[A-Za-z][A-Za-z0-9+.-]*:\/\/\S+$/.test(app.deep_link_uri)) add(3, 'deep_link');
    if (app.web_fallback_url && !/^https:\/\/\S+\.\S+/.test(app.web_fallback_url)) add(3, 'fallback_url');
  }
  return p;
}
const blockers = computed(() => validate());

// ---------------------------------------------------------------- helpers
function newKey() {
  return (window.crypto && window.crypto.randomUUID) ? window.crypto.randomUUID() : Date.now().toString(36) + Math.random().toString(36).slice(2) + Math.random().toString(36).slice(2);
}
function range(a, b) { const out = []; for (let i = a; i <= b; i++) out.push(i); return out; }
function money(v) {
  if (v === null || v === undefined || v === '') return '-';
  try { return new Intl.NumberFormat(i18n.intl, { style: 'currency', currency: currency.value || 'USD', maximumFractionDigits: 2 }).format(v); } catch (e) { return v; }
}
function compact(n) { return n === null || n === undefined ? '-' : new Intl.NumberFormat(i18n.intl, { notation: 'compact', maximumFractionDigits: 1 }).format(n); }
const humanize = (v) => (v || '').replace(/_/g, ' ').toLowerCase().replace(/^./, (x) => x.toUpperCase());
const known = (key, code, fallback) => { const v = t(key + '.' + code); return v === key + '.' + code ? fallback : v; };
function toggle(list, value) { const i = list.indexOf(value); if (i >= 0) list.splice(i, 1); else list.push(value); }
function ageText() { return targeting.max_age ? targeting.min_age + '–' + targeting.max_age : targeting.min_age + '+'; }
function osRange(os) { const v = targeting.os_versions[os] || {}; return v.min || v.max ? ' ' + (v.min || '…') + '–' + (v.max || '…') : ''; }
function isVertical(m) { return !m.width || !m.height || Math.abs(m.width / m.height - 9 / 16) < 0.02; }
function destinationIcon(d) { return ({ none: 'bx-image', web: 'bx-globe', app_install: 'bx-download', deep_link: 'bx-link-alt' })[d] || 'bx-target-lock'; }
function positionIcon(p) { return ({ INTERSTITIAL_USER: 'bx-user-circle', INTERSTITIAL_CONTENT: 'bx-news', INTERSTITIAL_SPOTLIGHT: 'bx-bolt-circle', INSTREAM: 'bx-play-circle', FEED: 'bx-grid-alt', CHAT_FEED: 'bx-chat' })[p] || 'bx-layout'; }
function destinationValue() {
  if (destination.value === 'web') return ad.url || '-';
  if (destination.value === 'app_install' || destination.value === 'deep_link') return [app.app_name, app.ios_app_id, app.android_app_url].filter(Boolean).join(' · ') || '-';
  return destination.value ? t('destinations.none.label') : '-';
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
    if (!data.success) throw new Error(data.message || t('client.snap_error'));
    return data.data;
  } catch (e) {
    throw new Error(errorMessage(e));
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
function ensure(name, url, params = {}, assign = null) {
  if (loadedOnce.has(name)) return;
  loadedOnce.add(name);
  load(name, url, params, assign);
}
const loadCampaigns = () => ensure('campaigns', props.urls.campaigns);
function loadRegions(cc) {
  if (lists.regions[cc]) return;
  lists.regions = { ...lists.regions, [cc]: [] };
  load('regions', props.urls.regions, { country_code: cc }, (data) => { lists.regions = { ...lists.regions, [cc]: data }; });
}
function loadOption(type, cc = null) {
  const key = cc ? type + ':' + cc : type;
  if (loadedOnce.has(key)) return;
  loadedOnce.add(key);
  const url = props.urls.options.replace('TYPE', type);
  if (type === 'metros') load('metros', url, { country_code: cc }, (data) => { lists.metros = { ...lists.metros, [cc]: data }; });
  else load(type, url, cc ? { country_code: cc } : {}, (data) => { lists[type] = data; });
}
let interestsFor = null;
function loadInterests() {
  const cc = interestCountry.value ? interestCountry.value.id : null;
  if (!cc || cc === interestsFor) return;
  interestsFor = cc;
  lists.interests = [];
  load('interests', props.urls.interests, { country_code: cc });
  loadedOnce.delete('visitation');
  loadedOnce.add('visitation');
  load('visitation', props.urls.options.replace('TYPE', 'visitation'), { country_code: cc }, (data) => { lists.visitation = data; });
}
function loadOsVersions(os) {
  if (lists.osVersions[os]) return;
  lists.osVersions = { ...lists.osVersions, [os]: [] };
  load('osVersions', props.urls.osVersions, { os }, (data) => { lists.osVersions = { ...lists.osVersions, [os]: data }; });
}

// ---------------------------------------------------------------- actions
function setCampaignMode(mode) {
  campaign.mode = mode;
  if (mode === 'existing') loadCampaigns();
  else campaign.id = null;
}
function chooseCampaign(c) {
  campaign.id = c.id;
  setObjective(c.objective, true);
  if (c.objective === 'APP_PROMOTION' && c.promotion_type) setDestination(c.promotion_type === 'APP_REENGAGEMENT' ? 'deep_link' : 'app_install', true);
}
function setObjective(key, fromCampaign = false) {
  if (objectiveLocked.value && !fromCampaign) return;
  if (objective.value === key) return;
  objective.value = key;
  const dests = Object.keys(boot.value.objectives[key].destinations);
  setDestination(dests.includes('web') ? 'web' : dests[0], true);
}
function setDestination(d, force = false) {
  if (destinationLocked.value && !force) return;
  destination.value = d;
  const list = spec.value.destinations[d] || [];
  if (!list.includes(adsquad.goal)) adsquad.goal = list[0] || null;
  const ctas = opt.value.ctas[d] || [];
  if (!ctas.includes(ad.cta)) ad.cta = ctas[0] || null;
}
function addPostal() {
  const codes = postalDraft.value.split(/[\s,;]+/).map((c) => c.trim()).filter((c) => /^[A-Za-z0-9-]{2,12}$/.test(c));
  codes.forEach((code) => { if (!targeting.postal_codes.some((p) => p.code === code && p.country === postalCountry.value)) targeting.postal_codes.push({ code, country: postalCountry.value }); });
  postalDraft.value = '';
}
function addCircle() {
  if (!circleValid.value || targeting.circles.length >= 500) return;
  targeting.circles.push({ latitude: circleDraft.latitude, longitude: circleDraft.longitude, radius: circleDraft.radius });
  circleDraft.latitude = null;
  circleDraft.longitude = null;
}
function toggleHour(day, hour) {
  const hours = [...(adsquad.schedule[day] || [])];
  const i = hours.indexOf(hour);
  if (i >= 0) hours.splice(i, 1); else hours.push(hour);
  adsquad.schedule = { ...adsquad.schedule, [day]: hours.sort((a, b) => a - b) };
}
function toggleDay(day) {
  const full = (adsquad.schedule[day] || []).length === 24;
  adsquad.schedule = { ...adsquad.schedule, [day]: full ? [] : range(0, 23) };
}
function presetSchedule(kind) {
  const out = {};
  opt.value.days.forEach((d, i) => {
    if (kind === 'business') out[d] = i < 5 ? range(9, 17) : [];
    else if (kind === 'evenings') out[d] = range(17, 23);
    else out[d] = [];
  });
  adsquad.schedule = out;
}
function openGallery(kind) {
  mediaError.value = '';
  pickerFor.value = kind;
  nextTick(() => picker.value && picker.value.open());
}
function onPicked(assets) {
  if (pickerFor.value === 'icon') {
    const icon = (assets || [])[0];
    if (icon && icon.type !== 'image') { mediaError.value = t('errors.icon'); return; }
    app.icon = icon || null;
    return;
  }
  for (const asset of assets || []) {
    if (!['image', 'video'].includes(asset.type)) { mediaError.value = t('errors.media_type'); continue; }
    if (asset.type === 'video' && asset.size > opt.value.max_video_mb * 1048576) { mediaError.value = t('errors.video_size', { mb: opt.value.max_video_mb }); continue; }
    if (ad.media.length >= opt.value.max_ads) break;
    if (!ad.media.some((m) => m.id === asset.id)) ad.media.push(asset);
  }
}
function squadForEstimate() {
  return {
    goal: adsquad.goal, bid_strategy: adsquad.bid_strategy, bid_amount: adsquad.bid_strategy !== 'AUTO_BID' ? adsquad.bid_amount || null : null,
    budget_type: adsquad.budget_type, budget: adsquad.budget >= opt.value.min_squad_daily ? adsquad.budget : opt.value.min_squad_daily,
    placement_mode: adsquad.placement_mode, positions: adsquad.placement_mode === 'custom' ? adsquad.positions : [],
  };
}
async function runEstimate() {
  estimating.value = true;
  try {
    estimate.value = await api(props.urls.estimate, { targeting: targetingPayload(), adsquad: squadForEstimate() }, 'post');
  } catch (e) {
    estimate.value = { error: e.message };
  } finally {
    estimating.value = false;
  }
}
async function runBidEstimate() {
  bidEstimating.value = true;
  try {
    bidEstimate.value = await api(props.urls.bidEstimate, { goal: adsquad.goal, targeting: targetingPayload() }, 'post');
  } catch (e) {
    bidEstimate.value = { error: e.message };
  } finally {
    bidEstimating.value = false;
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
  const countries = ids(targeting.countries);
  const inCountries = (g) => countries.includes(g.country);
  const versions = {};
  targeting.os_types.forEach((os) => { versions[os] = { min: targeting.os_versions[os].min || null, max: targeting.os_versions[os].max || null }; });
  return {
    countries,
    regions: targeting.regions.filter(inCountries).map((r) => ({ id: r.id, country: r.country })),
    excluded_regions: targeting.excluded_regions.filter(inCountries).map((r) => ({ id: r.id, country: r.country })),
    metros: targeting.metros.filter(inCountries).map((m) => ({ id: m.id, country: m.country })),
    excluded_metros: targeting.excluded_metros.filter(inCountries).map((m) => ({ id: m.id, country: m.country })),
    postal_codes: targeting.postal_codes.filter(inCountries),
    location_categories: ids(targeting.location_categories), visitation: ids(targeting.visitation), nielsen: ids(targeting.nielsen),
    carriers: ids(targeting.carriers), device_makes: ids(targeting.device_makes),
    circles: targeting.circles,
    gender: targeting.gender, min_age: targeting.min_age, max_age: targeting.max_age || null,
    languages: ids(targeting.languages), advanced_demographics: ids(targeting.advanced_demographics),
    interests: ids(targeting.interests), excluded_interests: ids(targeting.excluded_interests),
    os_types: targeting.os_types, os_versions: versions, connection_type: targeting.connection_type,
    audiences: ids(targeting.audiences), excluded_audiences: ids(targeting.excluded_audiences),
    regulated_content: targeting.regulated_content, interest_expansion: targeting.interest_expansion, audience_expansion: targeting.audience_expansion,
  };
}
function payload() {
  const sq = adsquad;
  const isApp = destination.value === 'app_install' || destination.value === 'deep_link';
  const lifetime = sq.budget_type === 'lifetime';
  return {
    idempotency_key: idempotencyKey.value,
    objective: objective.value,
    destination: destination.value,
    status: status.value,
    campaign: campaign.mode === 'existing'
      ? { mode: 'existing', id: campaign.id }
      : { mode: 'new', name: campaign.name || null, daily_cap: campaign.daily_cap || null, lifetime_cap: campaign.lifetime_cap || null },
    adsquad: {
      name: sq.name || null, goal: sq.goal, bid_strategy: sq.bid_strategy, bid_amount: sq.bid_strategy !== 'AUTO_BID' ? sq.bid_amount : null,
      pacing: sq.bid_strategy === 'LOWEST_COST_WITH_MAX_BID' ? sq.pacing : 'STANDARD',
      budget_type: sq.budget_type, budget: sq.budget, start_time: sq.start_time || null, end_time: sq.end_time || null,
      placement_mode: sq.placement_mode, positions: sq.placement_mode === 'custom' ? sq.positions : [],
      content_mode: sq.content_mode, content_types: sq.placement_mode === 'custom' && sq.positions.includes('INSTREAM') ? sq.content_types : [],
      pixel_id: sq.pixel_id || null, conversion_window: needsPixel.value ? sq.conversion_window : null,
      frequency_cap: frequencyAllowed.value ? (sq.frequency_cap || null) : null,
      frequency_interval: frequencyAllowed.value && sq.frequency_cap ? sq.frequency_interval : null,
      frequency_unit: frequencyAllowed.value && sq.frequency_cap ? sq.frequency_unit : null,
      schedule: lifetime && sq.dayparting ? Object.fromEntries(Object.entries(sq.schedule).filter(([, h]) => h.length)) : null,
      targeting: targetingPayload(),
    },
    app: isApp ? {
      app_name: app.app_name.trim(), ios_app_id: app.ios_app_id || null, android_app_url: app.android_app_url || null, icon_media_id: app.icon ? app.icon.id : null,
      deep_link_uri: destination.value === 'deep_link' ? app.deep_link_uri : null, web_fallback_url: destination.value === 'deep_link' ? (app.web_fallback_url || null) : null,
    } : {},
    ad: {
      name: ad.name || null, profile_id: ad.profile_id, headline: ad.headline.trim(), brand_name: ad.brand_name.trim() || null,
      media_ids: ad.media.map((m) => m.id), cta: destination.value !== 'none' ? ad.cta : null,
      url: destination.value === 'web' ? ad.url : null, shareable: ad.shareable, block_preload: destination.value === 'web' ? ad.block_preload : false,
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
      const stepFor = (f) => (f.startsWith('campaign') || f === 'objective' || f === 'destination' ? 0 : f.startsWith('adsquad.targeting') ? 2 : f.startsWith('adsquad') ? 1 : 3);
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
  ad.headline = '';
  ad.name = '';
  step.value = 3;
}

// ---------------------------------------------------------------- drafts
const draftKey = computed(() => `snapchat-ad-builder:${props.accountId}`);
function snapshot() {
  return JSON.parse(JSON.stringify({ step: step.value, maxReached: maxReached.value, objective: objective.value, destination: destination.value, status: status.value, campaign, adsquad, targeting, app, ad, key: idempotencyKey.value }));
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
  const obj = d.objective && boot.value.objectives[d.objective] ? d.objective : null;
  objective.value = obj;
  destination.value = obj && boot.value.objectives[obj].destinations[d.destination] ? d.destination : null;
  status.value = d.status === 'ACTIVE' ? 'ACTIVE' : 'PAUSED';
  Object.assign(campaign, d.campaign);
  Object.assign(adsquad, d.adsquad);
  Object.assign(targeting, d.targeting);
  ['metros', 'excluded_metros', 'location_categories', 'visitation', 'nielsen', 'carriers', 'device_makes'].forEach((k) => { if (!Array.isArray(targeting[k])) targeting[k] = []; });
  Object.assign(app, d.app || {});
  Object.assign(ad, d.ad);
  if (!goals.value.includes(adsquad.goal)) adsquad.goal = goals.value[0] || null;
  if (adsquad.pixel_id && !boot.value.pixels.some((p) => p.id === adsquad.pixel_id)) adsquad.pixel_id = null;
  if (boot.value.profiles.length && !boot.value.profiles.some((p) => p.id === ad.profile_id)) ad.profile_id = null;
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
    ensure('advanced', props.urls.advancedDemographics);
    ensure('audiences', props.urls.audiences);
    ensure('connectionTypes', props.urls.connectionTypes);
    loadInterests();
    targeting.os_types.forEach(loadOsVersions);
    ['nielsen', 'carriers', 'device_makes'].forEach((type) => loadOption(type));
    targeting.countries.forEach((c) => loadOption('metros', c.id));
  }
});
watch(() => targeting.countries.map((c) => c.id).join(','), () => {
  targeting.countries.forEach((c) => { loadRegions(c.id); if (current.value === 'audience') loadOption('metros', c.id); });
  if (!targeting.countries.some((c) => c.id === postalCountry.value)) postalCountry.value = targeting.countries[0] ? targeting.countries[0].id : null;
  if (current.value === 'audience') loadInterests();
});
watch(() => targeting.os_types.join(','), () => targeting.os_types.forEach(loadOsVersions));
watch(() => targeting.min_age, (v) => { if (targeting.max_age && targeting.max_age < v) targeting.max_age = null; });
watch(() => adsquad.goal, (g) => {
  if (g === 'IMPRESSIONS' && adsquad.bid_strategy === 'TARGET_COST') adsquad.bid_strategy = 'AUTO_BID';
  bidEstimate.value = null;
});
watch(() => adsquad.budget_type, (v) => { if (v !== 'lifetime') adsquad.dayparting = false; });
watch(() => JSON.stringify(targetingPayload()), () => { estimate.value = null; bidEstimate.value = null; });
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
  adsquad.start_time = nowInAccountTz();
  if (boot.value.profiles.length === 1) ad.profile_id = boot.value.profiles[0].id;
  if (boot.value.pixels.length === 1) adsquad.pixel_id = boot.value.pixels[0].id;
  load('countries', props.urls.countries);
  const draft = readDraft();
  if (draft) draftOffer.value = { savedAt: new Date(draft.savedAt).toLocaleString(i18n.intl, { dateStyle: 'medium', timeStyle: 'short' }) };
});
</script>

<style scoped src="../facebook/builder.css"></style>
<style scoped>
.sb-head-icon { background: #FFFC00; color: #111; font-size: 22px; }
.sb-dests { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 8px; margin-bottom: 10px; }
.sb-dest { display: flex; align-items: flex-start; gap: 10px; padding: 11px 12px; border: 1px solid var(--line); border-radius: 12px; background: #fff; text-align: start; transition: border-color .15s ease, background .15s ease, box-shadow .15s ease; }
.sb-dest > .bx { font-size: 20px; color: var(--ink); margin-top: 1px; }
.sb-dest span { display: flex; flex-direction: column; gap: 2px; min-width: 0; }
.sb-dest strong { font-size: .86rem; color: var(--ink); }
.sb-dest small { font-size: .74rem; color: var(--muted, #6b7280); line-height: 1.3; }
.sb-dest:hover:not(:disabled) { border-color: #c9ced8; }
.sb-dest.on { border-color: var(--brand-2); background: var(--brand-soft); box-shadow: 0 0 0 3px rgba(0, 76, 255, .12); }
.sb-dest:disabled { opacity: .45; cursor: not-allowed; }
.sb-details summary { cursor: pointer; font-weight: 600; color: var(--ink); display: flex; align-items: center; gap: 6px; }
.sb-pos { align-items: flex-start; }
.sb-pos span { display: flex; flex-direction: column; gap: 1px; }
.sb-pos small { font-weight: 400; font-size: .72rem; color: var(--muted, #6b7280); }
.sb-chipset { display: flex; flex-wrap: wrap; gap: 6px; }
.fb-chip-btn.is-on { background: var(--ink); color: #fff; border-color: var(--ink); }
.fb-chip-btn.is-on.is-ex { background: #B91C1C; border-color: #B91C1C; }
.sb-budget-note { display: flex; align-items: flex-end; gap: 6px; padding-bottom: 9px; font-size: .78rem; color: var(--muted, #6b7280); }
.sb-daypart { margin-top: 10px; padding: 10px; border: 1px solid var(--line); border-radius: 12px; overflow-x: auto; }
.sb-dp-row { display: grid; grid-template-columns: 84px repeat(24, minmax(14px, 1fr)); gap: 2px; align-items: center; min-width: 470px; }
.sb-dp-row + .sb-dp-row { margin-top: 2px; }
.sb-dp-h { font-size: .64rem; color: var(--muted, #6b7280); }
.sb-dp-day { border: 0; background: transparent; text-align: start; font-size: .76rem; font-weight: 600; color: var(--ink); padding: 0; }
.sb-dp-cell { height: 18px; border: 0; border-radius: 3px; background: #eef0f4; padding: 0; }
.sb-dp-cell.on { background: var(--brand-2); }
.sb-dp-cell:hover { outline: 1px solid #b9c0cc; }
.sb-dp-actions { display: flex; gap: 14px; margin-top: 8px; }
.sb-bid-est { display: flex; flex-direction: column; justify-content: flex-end; gap: 4px; padding-bottom: 2px; }
.sb-bid-est .btn { align-self: flex-start; }
.sb-bid-range { font-weight: 700; color: var(--ink); }
.sb-freq { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; }
.sb-freq input { width: 110px; }
.sb-freq select { width: auto; min-width: 110px; }
.sb-age, .sb-os { display: flex; align-items: center; gap: 8px; }
.sb-age select, .sb-os select { flex: 1; min-width: 0; }
.sb-os-l { width: 64px; font-weight: 600; font-size: .82rem; color: var(--ink); }
.sb-narrow { max-width: 280px; }
.sb-radius { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)) auto; gap: 8px; }
.sb-kw { display: grid; gap: 8px; }
.sb-kw-row { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; }
.sb-kw-row select { width: auto; min-width: 180px; }
.fb-chips { display: flex; flex-wrap: wrap; gap: 6px; }
.fb-chips .fb-chip { display: inline-flex; align-items: center; gap: 4px; max-width: 100%; padding: 3px 4px 3px 9px; border-radius: 999px; background: #EEF3FF; color: #0039CC; font-size: .78rem; font-weight: 600; }
.fb-chips .fb-chip button { border: 0; background: transparent; color: inherit; padding: 0 2px; line-height: 1; font-size: 15px; opacity: .7; }
.sb-chip-type { opacity: .65; font-size: .7rem; margin-inline-start: 4px; }
.sb-pt { padding-top: 28px; }
.sb-pp img { width: 100%; height: 100%; object-fit: cover; border-radius: inherit; }
.sb-warn-t { color: #B45309; }
.sb-phone { position: relative; width: 100%; max-width: 240px; margin: 0 auto; aspect-ratio: 9 / 16; border-radius: 22px; overflow: hidden; background: #111; color: #fff; box-shadow: 0 12px 30px rgba(16, 24, 40, .22); }
.sb-phone-media { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; }
.sb-phone-ph { position: absolute; inset: 0; display: grid; place-content: center; justify-items: center; gap: 6px; color: #9aa0aa; background: linear-gradient(160deg, #2a2a2a, #111); }
.sb-phone-ph i { font-size: 40px; }
.sb-phone-ph small { font-size: .72rem; padding: 0 18px; text-align: center; }
.sb-phone-play { position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); width: 52px; height: 52px; border-radius: 50%; display: grid; place-items: center; background: rgba(0, 0, 0, .45); font-size: 28px; }
.sb-phone-top { position: absolute; top: 0; left: 0; right: 0; display: flex; align-items: center; gap: 8px; padding: 12px 10px 24px; background: linear-gradient(rgba(0, 0, 0, .55), transparent); }
.sb-phone-av { width: 30px; height: 30px; border-radius: 50%; flex-shrink: 0; display: grid; place-items: center; overflow: hidden; background: #FFFC00; color: #111; font-size: 18px; }
.sb-phone-av img { width: 100%; height: 100%; object-fit: cover; }
.sb-phone-id { display: flex; flex-direction: column; min-width: 0; flex: 1; line-height: 1.2; }
.sb-phone-id strong { font-size: .78rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.sb-phone-id small { font-size: .7rem; opacity: .9; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.sb-phone-ad { flex-shrink: 0; padding: 1px 6px; border-radius: 4px; background: rgba(255, 255, 255, .25); font-size: .64rem; font-weight: 700; }
.sb-phone-cta { position: absolute; left: 10px; right: 10px; bottom: 14px; display: flex; flex-direction: column; align-items: center; gap: 4px; }
.sb-phone-cta-btn { display: inline-flex; align-items: center; gap: 6px; padding: 8px 16px; border-radius: 999px; background: #fff; color: #111; font-weight: 700; font-size: .78rem; box-shadow: 0 4px 12px rgba(0, 0, 0, .25); }
.sb-phone-cta small { font-size: .66rem; opacity: .85; }
.sb-sum { list-style: none; margin: 0; padding: 0; display: grid; gap: 7px; font-size: .82rem; color: var(--text); }
.sb-sum li { display: flex; gap: 7px; align-items: flex-start; line-height: 1.35; }
.sb-sum li i { color: var(--brand-2); font-size: 15px; margin-top: 1px; }
.sb-estimate { margin-top: 12px; padding-top: 12px; border-top: 1px dashed var(--line); }
.sb-est-v { margin: 8px 0 0; font-size: 1.1rem; font-weight: 800; color: var(--ink); text-align: center; }
.sb-est-v small { font-size: .75rem; font-weight: 500; color: #6b7280; }
@media (max-width: 575.98px) {
  .sb-radius { grid-template-columns: 1fr 1fr; }
  .sb-freq input { width: 90px; }
}
</style>
