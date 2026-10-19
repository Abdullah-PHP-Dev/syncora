<template>
  <div class="fb gb" :dir="i18n.dir">

    <!-- ===== Header ===== -->
    <header class="fb-head">
      <div class="fb-head-main">
        <span class="fb-head-icon gb-head-icon"><i class="bx bxl-google"></i></span>
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
      <div v-if="boot.account.manager" class="fb-alert is-warn"><i class="bx bx-error"></i> {{ t('boot.manager') }}</div>
      <div v-else-if="!boot.account.active" class="fb-alert is-warn"><i class="bx bx-error"></i> {{ t('boot.inactive', { status: boot.account.status || '-' }) }}</div>
      <div v-if="boot.account.test" class="fb-alert"><i class="bx bx-test-tube"></i> {{ t('boot.test_account') }}</div>

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
            <div class="fb-card-h"><h5>{{ t('campaign.title') }}</h5></div>

            <label class="fb-label">{{ t('campaign.goal') }} <span class="fb-opt">{{ t('common.optional') }}</span></label>
            <div class="fb-goals">
              <button v-for="(g, key) in boot.goals" :key="key" type="button" class="fb-goal" :class="{ on: goal === key }" @click="setGoal(key)">
                <span class="fb-goal-ic"><i class="bx" :class="g.icon"></i></span>
                <strong>{{ t('goals.' + key + '.label') }}</strong>
                <small>{{ t('goals.' + key + '.summary') }}</small>
              </button>
            </div>

            <label class="fb-label mt-3">{{ t('campaign.type') }}</label>
            <div class="gb-types">
              <button v-for="(spec, key) in boot.types" :key="key" type="button" class="gb-type" :class="{ on: type === key }" @click="setType(key)">
                <i class="bx" :class="spec.icon"></i>
                <span><strong>{{ t('types.' + key + '.label') }}</strong><small>{{ t('types.' + key + '.summary') }}</small></span>
                <span v-if="goal && boot.goals[goal].type === key" class="fb-tag">{{ t('campaign.recommended') }}</span>
              </button>
              <a :href="urls.youtube" class="gb-type gb-type-link">
                <i class="bx bxl-youtube"></i>
                <span><strong>{{ t('types.VIDEO.label') }}</strong><small>{{ t('types.VIDEO.summary') }}</small></span>
                <i class="bx bx-link-external gb-ext"></i>
              </a>
            </div>
            <div v-if="type" class="fb-guide"><i class="bx bx-bulb"></i><span><strong>{{ t('types.' + type + '.label') }}:</strong> {{ t('types.' + type + '.guidance') }}</span></div>

            <template v-if="type">
              <div class="fb-grid-2">
                <div>
                  <label class="fb-label" for="gbName">{{ t('campaign.name') }}</label>
                  <input id="gbName" v-model.trim="campaign.name" class="form-control" maxlength="255" :placeholder="defaultName">
                </div>
                <div>
                  <label class="fb-label" for="gbBudget">{{ t('campaign.budget', { currency }) }}</label>
                  <input id="gbBudget" v-model.number="campaign.budget" type="number" :min="opt.min_daily_budget" step="0.01" class="form-control">
                  <small class="fb-muted">{{ t('campaign.budget_hint', { month: money((campaign.budget || 0) * 30.4) }) }}</small>
                </div>
              </div>

              <div class="fb-group">
                <h6><i class="bx bx-trending-up"></i> {{ t('campaign.bidding') }}</h6>
                <div class="gb-bids">
                  <label v-for="b in spec.bidding" :key="b" class="fb-check gb-bid" :class="{ on: campaign.bidding === b }">
                    <input v-model="campaign.bidding" type="radio" :value="b">
                    <span><strong>{{ t('bidding.' + b + '.label') }}</strong><small>{{ t('bidding.' + b + '.hint') }}</small></span>
                  </label>
                </div>
                <div class="fb-grid-2 mt-2">
                  <div v-if="campaign.bidding === 'MAXIMIZE_CONVERSIONS'">
                    <label class="fb-label" for="gbCpa">{{ t('campaign.target_cpa', { currency }) }} <span class="fb-opt">{{ t('common.optional') }}</span></label>
                    <input id="gbCpa" v-model.number="campaign.target_cpa" type="number" min="0.01" step="0.01" class="form-control">
                  </div>
                  <div v-if="campaign.bidding === 'MAXIMIZE_CONVERSION_VALUE'">
                    <label class="fb-label" for="gbRoas">{{ t('campaign.target_roas') }} <span class="fb-opt">{{ t('common.optional') }}</span></label>
                    <input id="gbRoas" v-model.number="campaign.target_roas" type="number" min="1" step="1" class="form-control" placeholder="400">
                  </div>
                  <div v-if="campaign.bidding === 'MANUAL_CPC'">
                    <label class="fb-label" for="gbCpc">{{ t('campaign.max_cpc', { currency }) }}</label>
                    <input id="gbCpc" v-model.number="campaign.max_cpc" type="number" min="0.01" step="0.01" class="form-control">
                  </div>
                  <template v-if="campaign.bidding === 'TARGET_IMPRESSION_SHARE'">
                    <div>
                      <label class="fb-label" for="gbIsLoc">{{ t('campaign.is_location') }}</label>
                      <select id="gbIsLoc" v-model="campaign.is_location" class="form-control">
                        <option v-for="l in opt.impression_share_locations" :key="l" :value="l">{{ t('is_locations.' + l) }}</option>
                      </select>
                    </div>
                    <div>
                      <label class="fb-label" for="gbIsPct">{{ t('campaign.is_fraction') }}</label>
                      <input id="gbIsPct" v-model.number="campaign.is_fraction" type="number" min="1" max="100" class="form-control">
                    </div>
                  </template>
                  <div v-if="campaign.bidding === 'MAXIMIZE_CLICKS' || campaign.bidding === 'TARGET_IMPRESSION_SHARE'">
                    <label class="fb-label" for="gbCeil">{{ t('campaign.cpc_ceiling', { currency }) }} <span v-if="campaign.bidding === 'MAXIMIZE_CLICKS'" class="fb-opt">{{ t('common.optional') }}</span></label>
                    <input id="gbCeil" v-model.number="campaign.cpc_ceiling" type="number" min="0.01" step="0.01" class="form-control">
                  </div>
                </div>
              </div>

              <div v-if="conversionBidding" class="fb-group">
                <h6><i class="bx bx-target-lock"></i> {{ t('campaign.conversion_goals') }}</h6>
                <div v-if="!boot.conversion_goals.length" class="fb-hint is-warn"><i class="bx bx-info-circle"></i> {{ t('campaign.no_goals') }}</div>
                <template v-else>
                  <div class="fb-seg">
                    <button type="button" :class="{ on: campaign.goal_mode === 'account' }" @click="campaign.goal_mode = 'account'">{{ t('campaign.goals_account') }}</button>
                    <button type="button" :class="{ on: campaign.goal_mode === 'custom' }" @click="useCustomGoals">{{ t('campaign.goals_custom') }}</button>
                  </div>
                  <div v-if="campaign.goal_mode === 'custom'" class="gb-goal-list">
                    <label v-for="g in campaign.goals" :key="g.category + g.origin" class="fb-check">
                      <input v-model="g.biddable" type="checkbox">
                      <span>{{ known('conversion_categories', g.category, humanize(g.category)) }} <small class="fb-muted">· {{ known('origins', g.origin, humanize(g.origin)) }}</small></span>
                    </label>
                  </div>
                  <p class="fb-hint"><i class="bx bx-info-circle"></i> {{ campaign.goal_mode === 'account' ? t('campaign.goals_account_hint') : t('campaign.goals_custom_hint') }}</p>
                </template>
              </div>

              <div class="fb-group">
                <h6><i class="bx bx-calendar"></i> {{ t('campaign.schedule') }}</h6>
                <div class="fb-grid-2">
                  <div><label class="fb-label" for="gbStart">{{ t('campaign.start_date') }}</label><input id="gbStart" v-model="campaign.start_date" type="date" :min="today" class="form-control"></div>
                  <div><label class="fb-label" for="gbEnd">{{ t('campaign.end_date') }} <span class="fb-opt">{{ t('common.optional') }}</span></label><input id="gbEnd" v-model="campaign.end_date" type="date" :min="campaign.start_date || today" class="form-control"></div>
                </div>
                <p class="fb-hint"><i class="bx bx-time"></i> {{ t('campaign.timezone_hint', { tz: boot.account.timezone }) }}</p>
              </div>

              <div v-if="has('networks')" class="fb-group">
                <h6><i class="bx bx-network-chart"></i> {{ t('campaign.networks') }}</h6>
                <label class="fb-check"><input type="checkbox" checked disabled><span><strong>{{ t('campaign.google_search') }}</strong><small>{{ t('campaign.google_search_hint') }}</small></span></label>
                <label class="fb-check"><input v-model="campaign.search_partners" type="checkbox"><span><strong>{{ t('campaign.search_partners') }}</strong><small>{{ t('campaign.search_partners_hint') }}</small></span></label>
                <label class="fb-check"><input v-model="campaign.display_expansion" type="checkbox"><span><strong>{{ t('campaign.display_expansion') }}</strong><small>{{ t('campaign.display_expansion_hint') }}</small></span></label>
              </div>
            </template>
            <p v-if="fieldErrors.campaign" class="fb-field-err">{{ fieldErrors.campaign }}</p>
          </div>

          <!-- ============ 2. Locations, languages, schedule, devices ============ -->
          <div v-show="current === 'targeting'" class="fb-card">
            <div class="fb-card-h"><h5>{{ t('targeting.title') }}</h5></div>
            <p class="fb-sub">{{ t('targeting.sub') }}</p>

            <div class="fb-group">
              <h6><i class="bx bx-map"></i> {{ t('targeting.locations') }}</h6>
              <label class="fb-label">{{ t('targeting.include') }}</label>
              <search-picker v-model="targeting.locations" :fetcher="searchLocations" :max="1000" :placeholder="t('targeting.search_locations')">
                <template #option="{ item }"><span class="sp-opt-main"><strong>{{ item.name }}</strong></span><span class="fb-tag">{{ known('geo_types', item.type, item.type) }}</span></template>
              </search-picker>
              <label class="fb-label mt-2">{{ t('targeting.exclude') }} <span class="fb-opt">{{ t('common.optional') }}</span></label>
              <search-picker v-model="targeting.excluded_locations" :fetcher="searchLocations" :max="1000" chip-tone="exclude" :placeholder="t('targeting.search_locations')">
                <template #option="{ item }"><span class="sp-opt-main"><strong>{{ item.name }}</strong></span><span class="fb-tag">{{ known('geo_types', item.type, item.type) }}</span></template>
              </search-picker>

              <details class="fb-group gb-details" :open="targeting.radius.length > 0">
                <summary><i class="bx bx-current-location"></i> {{ t('targeting.radius') }} <span class="fb-opt">{{ t('common.optional') }}</span></summary>
                <div class="gb-radius mt-2">
                  <input v-model.number="radiusDraft.latitude" type="number" step="0.000001" min="-90" max="90" class="form-control" :placeholder="t('targeting.latitude')" :aria-label="t('targeting.latitude')">
                  <input v-model.number="radiusDraft.longitude" type="number" step="0.000001" min="-180" max="180" class="form-control" :placeholder="t('targeting.longitude')" :aria-label="t('targeting.longitude')">
                  <input v-model.number="radiusDraft.radius" type="number" min="1" max="800" class="form-control" :aria-label="t('targeting.radius_value')">
                  <select v-model="radiusDraft.unit" class="form-control" :aria-label="t('targeting.unit')"><option value="KILOMETERS">km</option><option value="MILES">mi</option></select>
                  <button type="button" class="btn btn-sm fb-btn-ghost" @click="addRadius"><i class="bx bx-plus"></i> {{ t('common.add') }}</button>
                </div>
                <div v-if="targeting.radius.length" class="fb-chips mt-2">
                  <span v-for="(r, i) in targeting.radius" :key="i" class="fb-chip" dir="ltr">{{ r.latitude }}, {{ r.longitude }} · {{ r.radius }} {{ r.unit === 'MILES' ? 'mi' : 'km' }}<button type="button" :aria-label="t('common.remove')" @click="targeting.radius.splice(i, 1)">×</button></span>
                </div>
                <p v-if="radiusError" class="fb-field-err">{{ radiusError }}</p>
              </details>

              <label class="fb-label mt-2">{{ t('targeting.geo_mode') }}</label>
              <div class="fb-seg">
                <button v-for="m in opt.geo_modes" :key="m" type="button" :class="{ on: campaign.geo_mode === m }" @click="campaign.geo_mode = m">{{ t('geo_modes.' + m + '.label') }}</button>
              </div>
              <p class="fb-hint"><i class="bx bx-info-circle"></i> {{ t('geo_modes.' + campaign.geo_mode + '.hint') }}</p>
              <p v-if="fieldErrors.targeting" class="fb-field-err">{{ fieldErrors.targeting }}</p>
            </div>

            <div class="fb-group">
              <h6><i class="bx bx-globe"></i> {{ t('targeting.languages') }}</h6>
              <search-picker v-model="targeting.languages" :options="lists.languages" :min-chars="0" :max="100" :placeholder="lists.loading.languages ? t('common.loading') : t('targeting.search_languages')" />
              <p v-if="lists.errors.languages" class="fb-field-err">{{ lists.errors.languages }}</p>
              <p class="fb-hint"><i class="bx bx-info-circle"></i> {{ t('targeting.languages_hint') }}</p>
            </div>

            <div class="fb-group">
              <h6><i class="bx bx-time-five"></i> {{ t('targeting.ad_schedule') }} <span class="fb-opt">{{ t('common.optional') }}</span></h6>
              <div class="gb-quick">
                <button type="button" class="fb-chip-btn" @click="presetSchedule('weekdays')">{{ t('targeting.preset_weekdays') }}</button>
                <button type="button" class="fb-chip-btn" @click="presetSchedule('business')">{{ t('targeting.preset_business') }}</button>
                <button type="button" class="fb-chip-btn" @click="presetSchedule('weekend')">{{ t('targeting.preset_weekend') }}</button>
                <button v-if="targeting.schedule.length" type="button" class="fb-link" @click="targeting.schedule = []">{{ t('targeting.all_day') }}</button>
              </div>
              <div v-for="(s, i) in targeting.schedule" :key="i" class="gb-sched-row">
                <select v-model="s.day" class="form-control" :aria-label="t('targeting.day')"><option v-for="d in opt.days" :key="d" :value="d">{{ t('days.' + d) }}</option></select>
                <select v-model.number="s.start_hour" class="form-control" :aria-label="t('targeting.from')"><option v-for="h in range(0, 23)" :key="h" :value="h">{{ hour(h) }}</option></select>
                <select v-model.number="s.end_hour" class="form-control" :aria-label="t('targeting.to')"><option v-for="h in range(s.start_hour + 1, 24)" :key="h" :value="h">{{ hour(h) }}</option></select>
                <div v-if="has('schedule_bids')" class="gb-adj"><input v-model.number="s.bid_adjustment" type="number" min="-90" max="900" class="form-control" :aria-label="t('targeting.bid_adjustment')" placeholder="0"><span>%</span></div>
                <button type="button" class="fb-icon-btn" :aria-label="t('common.remove')" @click="targeting.schedule.splice(i, 1)"><i class="bx bx-trash"></i></button>
              </div>
              <button type="button" class="btn btn-sm fb-btn-ghost mt-2" @click="targeting.schedule.push({ day: 'MONDAY', start_hour: 9, end_hour: 17, bid_adjustment: null })"><i class="bx bx-plus"></i> {{ t('targeting.add_slot') }}</button>
              <p class="fb-hint"><i class="bx bx-info-circle"></i> {{ targeting.schedule.length ? t('targeting.schedule_hint', { tz: boot.account.timezone }) : t('targeting.schedule_all') }}</p>
            </div>

            <div v-if="spec && spec.devices.length" class="fb-group">
              <h6><i class="bx bx-devices"></i> {{ t('targeting.devices') }}</h6>
              <div v-for="d in spec.devices" :key="d" class="gb-device">
                <span><i class="bx" :class="deviceIcon(d)"></i> {{ t('devices.' + d) }}</span>
                <div class="gb-adj"><input v-model.number="targeting.devices[d]" type="number" min="-100" max="900" class="form-control" placeholder="0" :aria-label="t('targeting.bid_adjustment')"><span>%</span></div>
                <button type="button" class="fb-chip-btn" :class="{ 'is-on is-ex': targeting.devices[d] === -100 }" @click="targeting.devices[d] = targeting.devices[d] === -100 ? null : -100">{{ targeting.devices[d] === -100 ? t('targeting.excluded') : t('targeting.exclude_device') }}</button>
              </div>
              <p class="fb-hint"><i class="bx bx-info-circle"></i> {{ t('targeting.devices_hint') }}</p>
            </div>
          </div>

          <!-- ============ 3. Audiences & demographics ============ -->
          <div v-show="current === 'audience'" class="fb-card">
            <div class="fb-card-h"><h5>{{ t('audience.title') }}</h5></div>
            <p class="fb-sub">{{ t('audience.sub') }}</p>

            <div class="fb-group">
              <h6><i class="bx bx-group"></i> {{ t('audience.segments') }} <span class="fb-opt">{{ t('common.optional') }}</span></h6>
              <search-picker v-model="targeting.audiences" :options="audienceOptions" :min-chars="0" :max="200" :placeholder="lists.loading.audiences || lists.loading.interests ? t('common.loading') : t('audience.search_segments')">
                <template #option="{ item }"><span class="sp-opt-main"><strong>{{ item.name }}</strong></span><span class="fb-tag">{{ known('segment_types', item.type, humanize(item.type)) }}</span></template>
              </search-picker>
              <p v-if="lists.errors.audiences || lists.errors.interests" class="fb-field-err">{{ lists.errors.audiences || lists.errors.interests }}</p>
              <template v-if="targeting.audiences.length">
                <label class="fb-label mt-2">{{ t('audience.mode') }}</label>
                <div class="fb-seg">
                  <button type="button" :class="{ on: targeting.audience_mode === 'observation' }" @click="targeting.audience_mode = 'observation'"><i class="bx bx-show"></i> {{ t('audience.observation') }}</button>
                  <button type="button" :class="{ on: targeting.audience_mode === 'targeting' }" @click="targeting.audience_mode = 'targeting'"><i class="bx bx-target-lock"></i> {{ t('audience.targeting') }}</button>
                </div>
                <p class="fb-hint"><i class="bx bx-info-circle"></i> {{ t('audience.' + targeting.audience_mode + '_hint') }}</p>
              </template>
              <p class="fb-hint"><i class="bx bx-info-circle"></i> {{ type === 'DISPLAY' ? t('audience.display_hint') : t('audience.search_hint') }}</p>
            </div>

            <div class="fb-group">
              <h6><i class="bx bx-user-check"></i> {{ t('audience.demographics') }}</h6>
              <p class="fb-hint"><i class="bx bx-info-circle"></i> {{ t('audience.demographics_hint') }}</p>
              <div v-for="d in demographicGroups" :key="d.key" class="gb-demo">
                <label class="fb-label">{{ t('audience.' + d.key) }}</label>
                <div class="sb-chipset">
                  <button v-for="v in d.values" :key="v" type="button" class="fb-chip-btn" :class="{ 'is-on': !targeting[d.key].includes(v) }" :aria-pressed="!targeting[d.key].includes(v)" @click="toggle(targeting[d.key], v)">{{ t('demographics.' + v) }}</button>
                </div>
              </div>
            </div>
          </div>

          <!-- ============ 4. Keywords & placements ============ -->
          <div v-show="current === 'keywords'" class="fb-card">
            <div class="fb-card-h"><h5>{{ type === 'DISPLAY' ? t('keywords.title_display') : t('keywords.title') }}</h5></div>
            <p class="fb-sub">{{ type === 'DISPLAY' ? t('keywords.sub_display') : t('keywords.sub') }}</p>

            <div class="fb-group">
              <h6><i class="bx bx-key"></i> {{ t('keywords.keywords') }} <span v-if="type === 'DISPLAY'" class="fb-opt">{{ t('common.optional') }}</span></h6>
              <div v-if="type === 'SEARCH'" class="fb-seg mb-2">
                <button v-for="m in opt.match_types" :key="m" type="button" :class="{ on: keywordMatch === m }" @click="keywordMatch = m">{{ t('match_types.' + m + '.label') }}</button>
              </div>
              <p v-if="type === 'SEARCH'" class="fb-hint"><i class="bx bx-info-circle"></i> {{ t('match_types.' + keywordMatch + '.hint') }}</p>
              <textarea v-model="keywordDraft" class="form-control" rows="4" :placeholder="t('keywords.placeholder')" @keydown.enter.ctrl.prevent="addKeywords('keywords')"></textarea>
              <div class="gb-row-actions">
                <button type="button" class="btn btn-sm fb-btn-ghost" :disabled="!keywordDraft.trim()" @click="addKeywords('keywords')"><i class="bx bx-plus"></i> {{ t('keywords.add') }}</button>
                <button v-if="type === 'SEARCH'" type="button" class="btn btn-sm fb-btn-ghost" :disabled="ideas.loading || (!keywordDraft.trim() && !adGroup.keywords.length && !ad.final_url)" @click="loadIdeas">
                  <span v-if="ideas.loading" class="spinner-border spinner-border-sm"></span><i v-else class="bx bx-bulb"></i> {{ t('keywords.ideas') }}
                </button>
              </div>
              <p v-if="ideas.error" class="fb-field-err">{{ ideas.error }}</p>
              <div v-if="ideas.items.length" class="gb-ideas">
                <button v-for="k in ideas.items" :key="k.text" type="button" class="gb-idea" :class="{ on: hasKeyword(k.text) }" @click="addIdea(k)">
                  <strong>{{ k.text }}</strong>
                  <small>{{ k.searches !== null ? t('keywords.searches', { n: compact(k.searches) }) : '' }}<template v-if="k.competition"> · {{ known('competition', k.competition, humanize(k.competition)) }}</template><template v-if="k.high_bid"> · {{ money(k.low_bid) }}–{{ money(k.high_bid) }}</template></small>
                </button>
              </div>
              <div v-if="adGroup.keywords.length" class="fb-chips mt-2">
                <span v-for="(k, i) in adGroup.keywords" :key="k.text + k.match_type" class="fb-chip">{{ keywordLabel(k) }}<button type="button" :aria-label="t('common.remove')" @click="adGroup.keywords.splice(i, 1)">×</button></span>
              </div>
              <p class="fb-muted mt-1">{{ t('keywords.count', { n: adGroup.keywords.length }) }}</p>
            </div>

            <div class="fb-group">
              <h6><i class="bx bx-block"></i> {{ t('keywords.negative') }} <span class="fb-opt">{{ t('common.optional') }}</span></h6>
              <textarea v-model="negativeDraft" class="form-control" rows="3" :placeholder="t('keywords.negative_placeholder')"></textarea>
              <button type="button" class="btn btn-sm fb-btn-ghost mt-2" :disabled="!negativeDraft.trim()" @click="addKeywords('negative')"><i class="bx bx-plus"></i> {{ t('keywords.add_negative') }}</button>
              <div v-if="targeting.negative_keywords.length" class="fb-chips mt-2">
                <span v-for="(k, i) in targeting.negative_keywords" :key="k.text + k.match_type" class="fb-chip gb-chip-ex">{{ keywordLabel(k) }}<button type="button" :aria-label="t('common.remove')" @click="targeting.negative_keywords.splice(i, 1)">×</button></span>
              </div>
            </div>

            <template v-if="type === 'DISPLAY'">
              <div class="fb-group">
                <h6><i class="bx bx-category"></i> {{ t('keywords.topics') }} <span class="fb-opt">{{ t('common.optional') }}</span></h6>
                <search-picker v-model="targeting.topics" :options="lists.topics" :min-chars="0" :max="200" :placeholder="lists.loading.topics ? t('common.loading') : t('keywords.search_topics')" />
                <p v-if="lists.errors.topics" class="fb-field-err">{{ lists.errors.topics }}</p>
              </div>
              <div class="fb-group">
                <h6><i class="bx bx-window-alt"></i> {{ t('keywords.placements') }} <span class="fb-opt">{{ t('common.optional') }}</span></h6>
                <textarea v-model="placementText" class="form-control" rows="3" dir="ltr" :placeholder="t('keywords.placements_placeholder')"></textarea>
                <label class="fb-label mt-2">{{ t('keywords.excluded_placements') }} <span class="fb-opt">{{ t('common.optional') }}</span></label>
                <textarea v-model="excludedPlacementText" class="form-control" rows="2" dir="ltr" :placeholder="t('keywords.placements_placeholder')"></textarea>
                <p class="fb-hint"><i class="bx bx-info-circle"></i> {{ t('keywords.placements_hint') }}</p>
              </div>
              <div class="fb-group">
                <h6><i class="bx bx-shield-quarter"></i> {{ t('keywords.content_exclusions') }} <span class="fb-opt">{{ t('common.optional') }}</span></h6>
                <div class="sb-chipset">
                  <button v-for="l in opt.content_labels" :key="l" type="button" class="fb-chip-btn" :class="{ 'is-on is-ex': targeting.content_labels.includes(l) }" @click="toggle(targeting.content_labels, l)">{{ t('content_labels.' + l) }}</button>
                </div>
              </div>
            </template>
            <p v-if="fieldErrors.keywords" class="fb-field-err">{{ fieldErrors.keywords }}</p>
          </div>

          <!-- ============ 5. Ad / asset group ============ -->
          <div v-show="current === 'ad'" class="fb-card">
            <div class="fb-card-h"><h5>{{ t('ad.title_' + (type || 'SEARCH')) }}</h5></div>
            <p class="fb-sub">{{ t('ad.sub_' + (type || 'SEARCH')) }}</p>

            <div class="fb-grid-2">
              <div :class="{ 'fb-span-2': type !== 'SEARCH' }">
                <label class="fb-label" for="gbUrl">{{ t('ad.final_url') }}</label>
                <input id="gbUrl" v-model.trim="ad.final_url" type="url" class="form-control" dir="ltr" placeholder="https://example.com/offer">
              </div>
              <template v-if="type === 'SEARCH'">
                <div class="gb-paths">
                  <div><label class="fb-label" for="gbP1">{{ t('ad.path1') }}</label><input id="gbP1" v-model.trim="ad.path1" class="form-control" dir="ltr" :maxlength="limits.path"></div>
                  <div><label class="fb-label" for="gbP2">{{ t('ad.path2') }}</label><input id="gbP2" v-model.trim="ad.path2" class="form-control" dir="ltr" :maxlength="limits.path" :disabled="!ad.path1"></div>
                </div>
              </template>
              <div v-else>
                <label class="fb-label" for="gbBiz">{{ t('ad.business_name') }}</label>
                <input id="gbBiz" v-model.trim="ad.business_name" class="form-control" :maxlength="limits.business_name">
              </div>
              <div v-if="type !== 'SEARCH'">
                <label class="fb-label" for="gbAg">{{ type === 'PERFORMANCE_MAX' ? t('ad.asset_group_name') : t('ad.ad_group_name') }} <span class="fb-opt">{{ t('common.optional') }}</span></label>
                <input id="gbAg" v-model.trim="adGroup.name" class="form-control" maxlength="255">
              </div>
            </div>

            <div v-for="list in textLists" :key="list.key" class="fb-group">
              <h6>{{ t('ad.' + list.key) }} <small class="fb-muted">{{ t('ad.count_range', { min: list.min, max: list.max, n: list.len }) }}</small></h6>
              <div v-for="(v, i) in ad[list.key]" :key="i" class="gb-text-row">
                <input v-model="ad[list.key][i]" class="form-control" :maxlength="list.len" :aria-label="t('ad.' + list.key) + ' ' + (i + 1)">
                <span class="gb-count" :class="{ 'is-full': (v || '').length >= list.len }">{{ (v || '').length }}/{{ list.len }}</span>
                <button type="button" class="fb-icon-btn" :disabled="ad[list.key].length <= Math.max(1, list.min)" :aria-label="t('common.remove')" @click="ad[list.key].splice(i, 1)"><i class="bx bx-x"></i></button>
              </div>
              <button v-if="ad[list.key].length < list.max" type="button" class="btn btn-sm fb-btn-ghost" @click="ad[list.key].push('')"><i class="bx bx-plus"></i> {{ t('ad.add_' + list.key) }}</button>
            </div>

            <template v-if="type !== 'SEARCH'">
              <div v-for="slot in imageSlots" :key="slot.key" class="fb-group">
                <h6><i class="bx bx-image"></i> {{ t('ad.images_' + slot.key) }} <small class="fb-muted">{{ slot.ratio }} · {{ t('ad.count_short', { min: slot.min, max: slot.max }) }}</small></h6>
                <div class="gb-images">
                  <div v-for="(m, i) in ad.images[slot.key]" :key="m.id" class="gb-img" :class="{ 'is-bad': !ratioOk(m, slot) }">
                    <img :src="m.url" :alt="m.original_name || ''">
                    <button type="button" class="gb-img-x" :aria-label="t('common.remove')" @click="ad.images[slot.key].splice(i, 1)"><i class="bx bx-x"></i></button>
                    <small v-if="!ratioOk(m, slot)">{{ t('ad.wrong_ratio', { ratio: slot.ratio }) }}</small>
                  </div>
                  <button v-if="ad.images[slot.key].length < slot.max" type="button" class="gb-img gb-img-add" @click="openPicker(slot.key)"><i class="bx bx-plus"></i><small>{{ t('ad.add_image') }}</small></button>
                </div>
                <p class="fb-hint"><i class="bx bx-info-circle"></i> {{ t('ad.images_hint_' + slot.key) }}</p>
              </div>
              <p v-if="mediaError" class="fb-field-err">{{ mediaError }}</p>
            </template>
            <p v-if="fieldErrors.ad" class="fb-field-err">{{ fieldErrors.ad }}</p>
          </div>

          <!-- ============ 6. Review ============ -->
          <div v-show="current === 'review'" class="fb-card">
            <div class="fb-card-h"><h5>{{ t('review.title') }}</h5></div>
            <div class="fb-review">
              <div v-for="row in reviewRows" :key="row.label" class="fb-review-row">
                <span class="fb-review-l">{{ row.label }}</span>
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
              <ul><li v-for="b in blockers" :key="b.text"><button type="button" class="fb-link" @click="goTo(stepIndex(b.step))">{{ b.text }}</button></li></ul>
            </div>

            <div v-if="result && result.success" class="fb-result is-ok">
              <i class="bx bx-check-circle"></i>
              <div>
                <strong>{{ result.message }}</strong>
                <p>{{ result.status === 'ACTIVE' ? t('result.live') : t('result.saved_paused') }} {{ t('result.campaign_id', { id: result.campaign_id }) }}</p>
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
                  <li v-for="c in result.created" :key="c.type"><i class="bx bx-check"></i> {{ t('result.created_item', { step: t('result_steps.' + c.type), id: c.id }) }}</li>
                </ul>
                <p class="fb-muted">{{ t('result.fix_retry') }}</p>
                <a v-if="result.reconnect" :href="urls.connections" class="btn btn-sm btn-primary">{{ t('boot.go_connections') }}</a>
              </div>
            </div>
          </div>

          <div class="fb-foot">
            <button type="button" class="btn btn-sm fb-btn-ghost" :disabled="step === 0 || submitting" @click="back"><i class="bx bx-chevron-left"></i> {{ t('common.back') }}</button>
            <span class="fb-saved" :class="{ on: savedAt }"><i class="bx bx-cloud-upload"></i> {{ savedAt ? t('draft.saved', { time: savedAt }) : t('draft.auto') }}</span>
            <button v-if="current !== 'review'" type="button" class="btn btn-primary btn-sm" :disabled="!type" @click="next">{{ t('common.continue') }} <i class="bx bx-chevron-right"></i></button>
            <button v-else type="button" class="btn btn-primary btn-sm" :disabled="submitting || blockers.length > 0 || (result && result.success) || boot.account.manager" @click="submit">
              <span v-if="submitting" class="spinner-border spinner-border-sm"></span><i v-else class="bx bx-rocket"></i>
              {{ submitting ? t('foot.creating') : (result && !result.success ? t('foot.try_again') : (status === 'ACTIVE' ? t('foot.publish') : t('foot.create'))) }}
            </button>
          </div>
        </section>

        <!-- ===== Side: preview & summary ===== -->
        <aside class="fb-side">
          <div class="fb-card">
            <div class="fb-side-h"><strong><i class="bx bx-show"></i> {{ t('side.preview') }}</strong><small>{{ t('side.approximate') }}</small></div>
            <div v-if="type !== 'SEARCH' && type" class="gb-dpreview">
              <img v-if="ad.images.marketing[0]" :src="ad.images.marketing[0].url" alt="" class="gb-dpreview-img">
              <div v-else class="gb-dpreview-ph"><i class="bx bx-image"></i></div>
              <div class="gb-dpreview-body">
                <span class="gb-dpreview-logo"><img v-if="ad.images.logo[0]" :src="ad.images.logo[0].url" alt=""><i v-else class="bx bxl-google"></i></span>
                <div>
                  <strong>{{ firstText('headlines') || t('side.headline_placeholder') }}</strong>
                  <p>{{ firstText('descriptions') || t('side.description_placeholder') }}</p>
                  <small>{{ ad.business_name || boot.account.name }}</small>
                </div>
                <span class="gb-dpreview-cta"><i class="bx bx-chevron-right"></i></span>
              </div>
            </div>
            <div v-else class="gb-serp">
              <small class="gb-serp-sp">{{ t('side.sponsored') }}</small>
              <div class="gb-serp-site"><span class="gb-serp-fav"><i class="bx bx-globe"></i></span><span dir="ltr">{{ displayUrl }}</span></div>
              <h6 class="gb-serp-h">{{ serpHeadline || t('side.headline_placeholder') }}</h6>
              <p class="gb-serp-d">{{ serpDescription || t('side.description_placeholder') }}</p>
            </div>
          </div>
          <div class="fb-card">
            <div class="fb-side-h"><strong><i class="bx bx-target-lock"></i> {{ t('side.summary') }}</strong></div>
            <ul class="sb-sum">
              <li v-for="s in summaryLines" :key="s.icon + s.text"><i class="bx" :class="s.icon"></i> {{ s.text }}</li>
            </ul>
          </div>
        </aside>
      </div>

      <media-picker ref="picker" :urls="galleryUrls" :multiple="true" :max="pickerMax" type-lock="image" :platforms="['google']" @select="onPicked" />
    </template>
  </div>
</template>

<script setup>
// Google campaign builder (Google Ads API through GoogleAdBuilderController).
// Same building blocks as the Facebook, TikTok, X and Snapchat builders:
// shared styles, SearchPicker, MediaPicker and localStorage drafts per ad
// account; every submission carries an idempotency key. Only the targeting
// a campaign type supports (boot.types[type].features) is shown.
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
const stepErrors = reactive({});
const fieldErrors = reactive({});
const picker = ref(null);
const pickerFor = ref('marketing');
const mediaError = ref('');
const radiusError = ref('');
const keywordDraft = ref('');
const negativeDraft = ref('');
const keywordMatch = ref('PHRASE');
const placementText = ref('');
const excludedPlacementText = ref('');
const ideas = reactive({ items: [], loading: false, error: '' });
const radiusDraft = reactive({ latitude: null, longitude: null, radius: 10, unit: 'KILOMETERS' });

const goal = ref(null);
const type = ref(null);
const status = ref('PAUSED');
const campaign = reactive({
  name: '', budget: 20, bidding: null, target_cpa: null, target_roas: null, max_cpc: null, cpc_ceiling: null,
  is_location: 'TOP_OF_PAGE', is_fraction: 50, start_date: '', end_date: '', geo_mode: 'PRESENCE_OR_INTEREST',
  search_partners: false, display_expansion: false, goal_mode: 'account', goals: [],
});
const targeting = reactive({
  locations: [], excluded_locations: [], radius: [], languages: [], schedule: [],
  devices: { DESKTOP: null, MOBILE: null, TABLET: null, CONNECTED_TV: null },
  ages: [], genders: [], parental: [], incomes: [], audiences: [], audience_mode: 'observation',
  negative_keywords: [], topics: [], content_labels: [],
});
const adGroup = reactive({ name: '', keywords: [] });
const ad = reactive({ final_url: '', path1: '', path2: '', headlines: ['', '', ''], descriptions: ['', ''], long_headlines: [''], business_name: '', images: { marketing: [], square: [], logo: [] } });
const lists = reactive({ languages: [], audiences: [], interests: [], topics: [], loading: {}, errors: {} });

const today = new Date().toISOString().slice(0, 10);
const opt = computed(() => (boot.value ? boot.value.options : {}));
const currency = computed(() => (boot.value ? boot.value.account.currency : 'USD'));
const spec = computed(() => (boot.value && type.value ? boot.value.types[type.value] : null));
const has = (f) => !!(spec.value && spec.value.features.includes(f));
const conversionBidding = computed(() => ['MAXIMIZE_CONVERSIONS', 'MAXIMIZE_CONVERSION_VALUE'].includes(campaign.bidding));
const limitKey = computed(() => ({ DISPLAY: 'rda', PERFORMANCE_MAX: 'pmax' })[type.value] || 'rsa');
const limits = computed(() => (opt.value.limits ? opt.value.limits[limitKey.value] : { headlines: [3, 15, 30], descriptions: [2, 4, 90], path: 15 }));
const defaultName = computed(() => (type.value ? t('types.' + type.value + '.label') + ' - ' + today : ''));

const steps = computed(() => {
  const all = [
    { key: 'campaign', label: t('steps.campaign'), summary: type.value ? t('types.' + type.value + '.label') : t('steps.campaign_hint') },
    { key: 'targeting', label: t('steps.targeting'), summary: t('steps.locations_n', { n: targeting.locations.length + targeting.radius.length }) },
    { key: 'audience', label: t('steps.audience'), summary: t('steps.segments_n', { n: targeting.audiences.length }), show: type.value !== 'PERFORMANCE_MAX' },
    { key: 'keywords', label: type.value === 'DISPLAY' ? t('steps.keywords_display') : t('steps.keywords'), summary: t('steps.keywords_n', { n: adGroup.keywords.length }), show: type.value !== 'PERFORMANCE_MAX' },
    { key: 'ad', label: type.value === 'PERFORMANCE_MAX' ? t('steps.assets') : t('steps.ad'), summary: ad.final_url ? domain(ad.final_url) : t('steps.ad_hint') },
    { key: 'review', label: t('steps.review'), summary: status.value === 'ACTIVE' ? t('review.publish') : t('review.save_paused') },
  ];
  return all.filter((s) => s.show !== false);
});
const current = computed(() => (steps.value[step.value] || steps.value[0]).key);
const stepIndex = (key) => Math.max(0, steps.value.findIndex((s) => s.key === key));

const textLists = computed(() => {
  const l = limits.value;
  const out = [{ key: 'headlines', min: l.headlines[0], max: l.headlines[1], len: l.headlines[2] }];
  if (type.value && type.value !== 'SEARCH') out.push({ key: 'long_headlines', min: l.long_headlines[0], max: l.long_headlines[1], len: l.long_headlines[2] });
  out.push({ key: 'descriptions', min: l.descriptions[0], max: l.descriptions[1], len: l.descriptions[2] });
  return out;
});
const imageSlots = computed(() => {
  const l = limits.value;
  if (!l.marketing) return [];
  return [
    { key: 'marketing', ratio: '1.91:1', value: 1.91, min: l.marketing[0], max: l.marketing[1] },
    { key: 'square', ratio: '1:1', value: 1, min: l.square[0], max: l.square[1] },
    { key: 'logo', ratio: '1:1', value: 1, min: l.logo[0], max: l.logo[1] },
  ];
});
const pickerMax = computed(() => {
  const slot = imageSlots.value.find((s) => s.key === pickerFor.value);
  return slot ? Math.max(1, slot.max - ad.images[slot.key].length) : 1;
});
const demographicGroups = computed(() => [
  { key: 'ages', values: opt.value.ages || [] },
  { key: 'genders', values: opt.value.genders || [] },
  { key: 'parental', values: opt.value.parental || [] },
  { key: 'incomes', values: opt.value.incomes || [] },
]);
const audienceOptions = computed(() => {
  const own = lists.audiences.filter((a) => (type.value === 'DISPLAY' ? a.display : a.kind === 'user_list' && a.search));
  return [...own, ...lists.interests];
});

// ---------------------------------------------------------------- helpers
function newKey() {
  return (window.crypto && window.crypto.randomUUID) ? window.crypto.randomUUID() : Date.now().toString(36) + Math.random().toString(36).slice(2) + Math.random().toString(36).slice(2);
}
function range(a, b) { const out = []; for (let i = a; i <= b; i++) out.push(i); return out; }
function hour(h) { return String(h).padStart(2, '0') + ':00'; }
function money(v) {
  if (v === null || v === undefined || v === '') return '-';
  try { return new Intl.NumberFormat(i18n.intl, { style: 'currency', currency: currency.value || 'USD', maximumFractionDigits: 2 }).format(v); } catch (e) { return v; }
}
function compact(n) { return n === null || n === undefined ? '-' : new Intl.NumberFormat(i18n.intl, { notation: 'compact', maximumFractionDigits: 1 }).format(n); }
const humanize = (v) => (v || '').replace(/_/g, ' ').toLowerCase().replace(/^./, (x) => x.toUpperCase());
const known = (key, code, fallback) => { const v = t(key + '.' + code); return v === key + '.' + code ? fallback : v; };
function toggle(list, value) { const i = list.indexOf(value); if (i >= 0) list.splice(i, 1); else list.push(value); }
function domain(url) { try { return new URL(url).hostname.replace(/^www\./, ''); } catch (e) { return ''; } }
function deviceIcon(d) { return ({ DESKTOP: 'bx-desktop', MOBILE: 'bx-mobile-alt', TABLET: 'bx-tab', CONNECTED_TV: 'bx-tv' })[d] || 'bx-devices'; }
function firstText(key) { return (ad[key] || []).map((v) => (v || '').trim()).find(Boolean) || ''; }
function lines(text) { return text.split(/[\n,]+/).map((v) => v.trim()).filter(Boolean); }
function ratioOk(m, slot) { return !m.width || !m.height || Math.abs(m.width / m.height - slot.value) <= slot.value * 0.02; }
function keywordLabel(k) { return k.match_type === 'EXACT' ? '[' + k.text + ']' : k.match_type === 'PHRASE' ? '"' + k.text + '"' : k.text; }
function hasKeyword(text) { return adGroup.keywords.some((k) => k.text.toLowerCase() === text.toLowerCase()); }

const displayUrl = computed(() => {
  const host = domain(ad.final_url) || 'example.com';
  return [host, ad.path1, ad.path1 && ad.path2].filter(Boolean).join(' › ');
});
const serpHeadline = computed(() => ad.headlines.map((v) => (v || '').trim()).filter(Boolean).slice(0, 3).join(' | '));
const serpDescription = computed(() => ad.descriptions.map((v) => (v || '').trim()).filter(Boolean).slice(0, 2).join(' '));

// ---------------------------------------------------------------- API
function errorMessage(e) {
  return (e.response && e.response.data && (e.response.data.message || Object.values(e.response.data.errors || {}).flat()[0])) || e.message || t('client.request_failed');
}
async function api(url, params = {}, method = 'get') {
  try {
    const { data } = method === 'post' ? await window.axios.post(url, params) : await window.axios.get(url, { params });
    if (!data.success) throw new Error(data.message || t('client.google_error'));
    return data.data;
  } catch (e) {
    throw new Error(errorMessage(e));
  }
}
const loadedOnce = new Set();
async function ensure(name, url) {
  if (loadedOnce.has(name)) return;
  loadedOnce.add(name);
  lists.loading = { ...lists.loading, [name]: true };
  lists.errors = { ...lists.errors, [name]: '' };
  try {
    lists[name] = await api(url);
  } catch (e) {
    loadedOnce.delete(name);
    lists.errors = { ...lists.errors, [name]: e.message };
  } finally {
    lists.loading = { ...lists.loading, [name]: false };
  }
}
async function searchLocations(q) {
  return api(props.urls.locations, { q });
}

// ---------------------------------------------------------------- campaign
function setGoal(key) {
  goal.value = goal.value === key ? null : key;
  if (goal.value) {
    setType(boot.value.goals[key].type);
    if (spec.value.bidding.includes(boot.value.goals[key].bidding)) campaign.bidding = boot.value.goals[key].bidding;
  }
}
function setType(key) {
  type.value = key;
  if (!spec.value.bidding.includes(campaign.bidding)) campaign.bidding = spec.value.bidding[0];
  Object.keys(targeting.devices).forEach((d) => { if (!spec.value.devices.includes(d)) targeting.devices[d] = null; });
  if (key !== 'SEARCH' && ad.long_headlines.length === 0) ad.long_headlines.push('');
  const l = limits.value;
  ['headlines', 'descriptions'].forEach((k) => {
    while (ad[k].length < l[k][0]) ad[k].push('');
    if (ad[k].length > l[k][1]) ad[k].splice(l[k][1]);
  });
  if (l.long_headlines && ad.long_headlines.length > l.long_headlines[1]) ad.long_headlines.splice(l.long_headlines[1]);
  if (key === 'PERFORMANCE_MAX') targeting.audiences = targeting.audiences.filter(() => false);
  if (key === 'SEARCH') targeting.audiences = targeting.audiences.filter((a) => a.kind !== 'custom_audience');
  if (step.value >= steps.value.length) step.value = steps.value.length - 1;
}
function useCustomGoals() {
  campaign.goal_mode = 'custom';
  if (!campaign.goals.length) campaign.goals = boot.value.conversion_goals.map((g) => ({ ...g }));
}

// ---------------------------------------------------------------- targeting
function addRadius() {
  radiusError.value = '';
  const r = radiusDraft;
  if (r.latitude === null || r.latitude === '' || Math.abs(r.latitude) > 90 || r.longitude === null || r.longitude === '' || Math.abs(r.longitude) > 180 || !(r.radius >= 1 && r.radius <= 800)) {
    radiusError.value = t('errors.radius');
    return;
  }
  targeting.radius.push({ latitude: Number(r.latitude), longitude: Number(r.longitude), radius: Number(r.radius), unit: r.unit });
  r.latitude = null;
  r.longitude = null;
}
function presetSchedule(kind) {
  const days = kind === 'weekend' ? ['SATURDAY', 'SUNDAY'] : ['MONDAY', 'TUESDAY', 'WEDNESDAY', 'THURSDAY', 'FRIDAY'];
  const [start, end] = kind === 'business' ? [9, 18] : [0, 24];
  targeting.schedule = days.map((day) => ({ day, start_hour: start, end_hour: end, bid_adjustment: null }));
}

// ---------------------------------------------------------------- keywords
function parseKeyword(raw, fallback) {
  const s = raw.trim();
  if (/^\[.+\]$/.test(s)) return { text: s.slice(1, -1).trim(), match_type: 'EXACT' };
  if (/^".+"$/.test(s)) return { text: s.slice(1, -1).trim(), match_type: 'PHRASE' };
  return { text: s, match_type: fallback };
}
function addKeywords(which) {
  const isNeg = which === 'negative';
  const fallback = isNeg ? 'PHRASE' : (type.value === 'DISPLAY' ? 'BROAD' : keywordMatch.value);
  const target = isNeg ? targeting.negative_keywords : adGroup.keywords;
  lines(isNeg ? negativeDraft.value : keywordDraft.value).map((l) => parseKeyword(l, fallback)).forEach((k) => {
    if (type.value === 'DISPLAY' && !isNeg) k.match_type = 'BROAD';
    k.text = k.text.slice(0, 80);
    if (k.text && !target.some((x) => x.text.toLowerCase() === k.text.toLowerCase() && x.match_type === k.match_type)) target.push(k);
  });
  if (isNeg) negativeDraft.value = ''; else keywordDraft.value = '';
}
function addIdea(k) {
  if (!hasKeyword(k.text)) adGroup.keywords.push({ text: k.text, match_type: keywordMatch.value });
}
async function loadIdeas() {
  ideas.loading = true;
  ideas.error = '';
  try {
    const seeds = [...lines(keywordDraft.value), ...adGroup.keywords.map((k) => k.text)].slice(0, 20);
    ideas.items = await api(props.urls.keywordIdeas, {
      keywords: seeds,
      url: ad.final_url || null,
      locations: targeting.locations.slice(0, 10).map((l) => l.id),
      language: targeting.languages[0] ? targeting.languages[0].id : null,
    }, 'post');
    if (!ideas.items.length) ideas.error = t('keywords.no_ideas');
  } catch (e) {
    ideas.error = e.message;
  } finally {
    ideas.loading = false;
  }
}

// ---------------------------------------------------------------- media
function openPicker(slot) {
  pickerFor.value = slot;
  mediaError.value = '';
  nextTick(() => picker.value && picker.value.open());
}
function onPicked(assets) {
  const slot = imageSlots.value.find((s) => s.key === pickerFor.value);
  if (!slot) return;
  for (const asset of assets || []) {
    if (asset.type !== 'image') { mediaError.value = t('errors.image_only'); continue; }
    if (asset.size && asset.size > opt.value.max_image_mb * 1048576) { mediaError.value = t('errors.image_size', { mb: opt.value.max_image_mb }); continue; }
    if (!ratioOk(asset, slot)) mediaError.value = t('ad.wrong_ratio', { ratio: slot.ratio });
    if (ad.images[slot.key].length >= slot.max) break;
    if (!ad.images[slot.key].some((m) => m.id === asset.id)) ad.images[slot.key].push(asset);
  }
}

// ---------------------------------------------------------------- validation
const isUrl = (v) => /^https?:\/\/[^\s.]+\.[^\s]+$/i.test(v || '');
function filled(key) { return (ad[key] || []).map((v) => (v || '').trim()).filter(Boolean); }
function validate() {
  const out = [];
  const add = (s, text) => out.push({ step: s, text });
  if (!type.value) { add('campaign', t('errors.type')); return out; }
  if (!(campaign.budget >= opt.value.min_daily_budget)) add('campaign', t('errors.budget', { amount: money(opt.value.min_daily_budget) }));
  if (campaign.bidding === 'MANUAL_CPC' && !(campaign.max_cpc > 0)) add('campaign', t('errors.max_cpc'));
  if (campaign.bidding === 'TARGET_IMPRESSION_SHARE' && !(campaign.cpc_ceiling > 0)) add('campaign', t('errors.cpc_ceiling'));
  if (campaign.end_date && campaign.start_date && campaign.end_date < campaign.start_date) add('campaign', t('errors.dates'));
  if (conversionBidding.value && campaign.goal_mode === 'custom' && !campaign.goals.some((g) => g.biddable)) add('campaign', t('errors.goals'));
  if (!targeting.locations.length && !targeting.radius.length) add('targeting', t('errors.locations'));
  if (targeting.locations.some((l) => targeting.excluded_locations.some((x) => x.id === l.id))) add('targeting', t('errors.location_both'));
  if (targeting.schedule.some((s) => s.end_hour <= s.start_hour)) add('targeting', t('errors.schedule'));
  Object.entries(targeting.devices).forEach(([d, v]) => { if (v !== null && v !== '' && v !== -100 && (v < -90 || v > 900)) add('targeting', t('errors.device', { device: t('devices.' + d) })); });
  if (type.value !== 'PERFORMANCE_MAX') {
    ['ages', 'genders'].forEach((k) => { if (targeting[k].length >= (opt.value[k] || []).length) add('audience', t('errors.demographic_all', { group: t('audience.' + k) })); });
  }
  if (type.value === 'SEARCH' && !adGroup.keywords.length) add('keywords', t('errors.keywords'));
  if (!isUrl(ad.final_url)) add('ad', t('errors.url'));
  textLists.value.forEach((l) => {
    const v = filled(l.key);
    if (v.length < l.min) add('ad', t('errors.text_min', { field: t('ad.' + l.key), n: l.min }));
    if (v.some((x) => x.length > l.len)) add('ad', t('errors.text_len', { field: t('ad.' + l.key), n: l.len }));
    if (new Set(v.map((x) => x.toLowerCase())).size !== v.length) add('ad', t('errors.text_dupe', { field: t('ad.' + l.key) }));
  });
  if (type.value === 'PERFORMANCE_MAX' && !filled('descriptions').some((d) => d.length <= 60)) add('ad', t('errors.pmax_short'));
  if (type.value !== 'SEARCH') {
    if (!ad.business_name.trim()) add('ad', t('errors.business_name'));
    imageSlots.value.forEach((s) => {
      if (ad.images[s.key].length < s.min) add('ad', t('errors.images', { slot: t('ad.images_' + s.key), n: s.min }));
      if (ad.images[s.key].some((m) => !ratioOk(m, s))) add('ad', t('ad.wrong_ratio', { ratio: s.ratio }) + ' (' + t('ad.images_' + s.key) + ')');
    });
  }
  return out;
}
const blockers = computed(() => validate().filter((b) => steps.value.some((s) => s.key === b.step)));
function showStepErrors(key) {
  const msgs = blockers.value.filter((b) => b.step === key).map((b) => b.text);
  stepErrors[key] = msgs.length > 0;
  fieldErrors[key] = msgs.join(' ');
  return msgs.length === 0;
}

// ---------------------------------------------------------------- navigation
function goTo(i) {
  if (i < 0 || i >= steps.value.length || i > maxReached.value) return;
  step.value = i;
  window.scrollTo({ top: 0, behavior: 'smooth' });
}
function next() {
  if (!showStepErrors(current.value)) return;
  step.value = Math.min(step.value + 1, steps.value.length - 1);
  maxReached.value = Math.max(maxReached.value, step.value);
  window.scrollTo({ top: 0, behavior: 'smooth' });
}
function back() { goTo(step.value - 1); }

// ---------------------------------------------------------------- review & summary
const locationText = computed(() => {
  const names = targeting.locations.map((l) => l.name.split(',')[0]);
  const r = targeting.radius.length ? [t('summary.radius_n', { n: targeting.radius.length })] : [];
  return [...names.slice(0, 4), ...r].join(', ') + (names.length > 4 ? ' +' + (names.length - 4) : '') || '-';
});
const reviewRows = computed(() => {
  const rows = [
    { step: stepIndex('campaign'), label: t('review.type'), value: type.value ? t('types.' + type.value + '.label') : '-' },
    { step: stepIndex('campaign'), label: t('review.name'), value: campaign.name || defaultName.value },
    { step: stepIndex('campaign'), label: t('review.budget'), value: money(campaign.budget) + t('steps.per_day') },
    { step: stepIndex('campaign'), label: t('review.bidding'), value: campaign.bidding ? t('bidding.' + campaign.bidding + '.label') : '-' },
    { step: stepIndex('campaign'), label: t('review.dates'), value: (campaign.start_date || t('review.asap')) + ' → ' + (campaign.end_date || t('review.no_end')) },
    { step: stepIndex('targeting'), label: t('review.locations'), value: locationText.value },
    { step: stepIndex('targeting'), label: t('review.languages'), value: targeting.languages.map((l) => l.name).join(', ') || t('summary.all_languages') },
  ];
  if (type.value !== 'PERFORMANCE_MAX') {
    rows.push({ step: stepIndex('audience'), label: t('review.audiences'), value: targeting.audiences.length ? t('summary.segments_n', { n: targeting.audiences.length }) + ' · ' + t('audience.' + targeting.audience_mode) : t('summary.no_segments') });
    rows.push({ step: stepIndex('keywords'), label: t('review.keywords'), value: t('steps.keywords_n', { n: adGroup.keywords.length }) + (targeting.negative_keywords.length ? ' · ' + t('summary.negatives_n', { n: targeting.negative_keywords.length }) : '') });
  }
  rows.push({ step: stepIndex('ad'), label: t('review.final_url'), value: ad.final_url || '-' });
  return rows;
});
const summaryLines = computed(() => {
  const out = [];
  if (type.value) out.push({ icon: spec.value.icon, text: t('types.' + type.value + '.label') });
  if (campaign.budget) out.push({ icon: 'bx-wallet', text: money(campaign.budget) + t('steps.per_day') + ' · ' + (campaign.bidding ? t('bidding.' + campaign.bidding + '.label') : '') });
  out.push({ icon: 'bx-map', text: locationText.value });
  if (targeting.excluded_locations.length) out.push({ icon: 'bx-block', text: t('summary.excluded_n', { n: targeting.excluded_locations.length }) });
  out.push({ icon: 'bx-globe', text: targeting.languages.map((l) => l.name).join(', ') || t('summary.all_languages') });
  if (targeting.schedule.length) out.push({ icon: 'bx-time-five', text: t('summary.slots_n', { n: targeting.schedule.length }) });
  if (type.value && type.value !== 'PERFORMANCE_MAX') {
    if (targeting.audiences.length) out.push({ icon: 'bx-group', text: t('summary.segments_n', { n: targeting.audiences.length }) });
    const ex = targeting.ages.length + targeting.genders.length + targeting.parental.length + targeting.incomes.length;
    if (ex) out.push({ icon: 'bx-user-x', text: t('summary.demo_excluded_n', { n: ex }) });
    if (adGroup.keywords.length) out.push({ icon: 'bx-key', text: t('steps.keywords_n', { n: adGroup.keywords.length }) });
  }
  return out;
});

// ---------------------------------------------------------------- submit
function urlList(text) { return lines(text).map((u) => u.replace(/^https?:\/\//i, '').replace(/\/$/, '')); }
function payload() {
  const nonPmax = type.value !== 'PERFORMANCE_MAX';
  const devices = {};
  (spec.value.devices || []).forEach((d) => { const v = targeting.devices[d]; if (v !== null && v !== '' && v !== 0) devices[d] = v; });
  return {
    idempotency_key: idempotencyKey.value,
    status: status.value,
    goal: goal.value,
    campaign_type: type.value,
    campaign: {
      name: campaign.name || defaultName.value,
      budget: campaign.budget,
      bidding: campaign.bidding,
      target_cpa: campaign.bidding === 'MAXIMIZE_CONVERSIONS' ? campaign.target_cpa || null : null,
      target_roas: campaign.bidding === 'MAXIMIZE_CONVERSION_VALUE' ? campaign.target_roas || null : null,
      max_cpc: campaign.bidding === 'MANUAL_CPC' ? campaign.max_cpc : null,
      cpc_ceiling: ['MAXIMIZE_CLICKS', 'TARGET_IMPRESSION_SHARE'].includes(campaign.bidding) ? campaign.cpc_ceiling || null : null,
      is_location: campaign.bidding === 'TARGET_IMPRESSION_SHARE' ? campaign.is_location : null,
      is_fraction: campaign.bidding === 'TARGET_IMPRESSION_SHARE' ? campaign.is_fraction : null,
      start_date: campaign.start_date || null,
      end_date: campaign.end_date || null,
      geo_mode: campaign.geo_mode,
      search_partners: type.value === 'SEARCH' ? campaign.search_partners : false,
      display_expansion: type.value === 'SEARCH' ? campaign.display_expansion : false,
      goals: conversionBidding.value && campaign.goal_mode === 'custom' ? campaign.goals.map((g) => ({ category: g.category, origin: g.origin, biddable: !!g.biddable })) : null,
    },
    targeting: {
      locations: [...targeting.locations.map((l) => ({ id: l.id, name: l.name, negative: false })), ...targeting.excluded_locations.map((l) => ({ id: l.id, name: l.name, negative: true }))],
      radius: targeting.radius,
      languages: targeting.languages.map((l) => l.id),
      schedule: targeting.schedule.map((s) => ({ ...s, bid_adjustment: has('schedule_bids') && s.bid_adjustment ? s.bid_adjustment : null })),
      devices,
      ...(nonPmax ? {
        ages: targeting.ages, genders: targeting.genders, parental: targeting.parental, incomes: targeting.incomes,
        audiences: targeting.audiences.map((a) => ({ id: a.id, kind: a.kind, name: a.name })), audience_mode: targeting.audience_mode,
        negative_keywords: targeting.negative_keywords,
      } : {}),
      ...(type.value === 'DISPLAY' ? {
        topics: targeting.topics.map((x) => ({ id: x.id })), content_labels: targeting.content_labels,
        placements: urlList(placementText.value), excluded_placements: urlList(excludedPlacementText.value),
      } : {}),
    },
    ad_group: { name: adGroup.name || null, ...(nonPmax ? { keywords: adGroup.keywords } : {}) },
    ad: {
      final_url: ad.final_url,
      headlines: filled('headlines'),
      descriptions: filled('descriptions'),
      ...(type.value === 'SEARCH' ? { path1: ad.path1 || null, path2: ad.path1 ? ad.path2 || null : null } : {
        long_headlines: filled('long_headlines'), business_name: ad.business_name,
        images: { marketing: ad.images.marketing.map((m) => m.id), square: ad.images.square.map((m) => m.id), logo: ad.images.logo.map((m) => m.id) },
      }),
    },
  };
}
function stepForField(f) {
  if (f.startsWith('campaign')) return 'campaign';
  if (/^targeting\.(ages|genders|parental|incomes|audiences|audience_mode)/.test(f)) return type.value === 'PERFORMANCE_MAX' ? 'targeting' : 'audience';
  if (/^targeting\.(negative_keywords|placements|excluded_placements|topics|content_labels)/.test(f) || f.startsWith('ad_group')) return type.value === 'PERFORMANCE_MAX' ? 'ad' : 'keywords';
  if (f.startsWith('targeting')) return 'targeting';
  return 'ad';
}
async function submit() {
  if (submitting.value) return;
  const problems = validate();
  if (problems.length) {
    problems.forEach((p) => showStepErrors(p.step));
    return goTo(stepIndex(problems[0].step));
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
      Object.entries(data.errors).forEach(([field, msgs]) => {
        const key = stepForField(field);
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
  campaign.name = '';
  step.value = 0;
}

// ---------------------------------------------------------------- drafts
const draftKey = computed(() => `google-ad-builder:${props.accountId}`);
function snapshot() {
  return JSON.parse(JSON.stringify({
    step: step.value, maxReached: maxReached.value, goal: goal.value, type: type.value, status: status.value,
    campaign, targeting, adGroup, ad, placementText: placementText.value, excludedPlacementText: excludedPlacementText.value, key: idempotencyKey.value,
  }));
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
  goal.value = d.goal && boot.value.goals[d.goal] ? d.goal : null;
  if (d.type && boot.value.types[d.type]) type.value = d.type;
  status.value = d.status === 'ACTIVE' ? 'ACTIVE' : 'PAUSED';
  Object.assign(campaign, d.campaign || {});
  Object.assign(targeting, d.targeting || {}, { devices: { ...targeting.devices, ...((d.targeting || {}).devices || {}) } });
  Object.assign(adGroup, d.adGroup || {});
  Object.assign(ad, d.ad || {}, { images: { marketing: [], square: [], logo: [], ...((d.ad || {}).images || {}) } });
  placementText.value = d.placementText || '';
  excludedPlacementText.value = d.excludedPlacementText || '';
  if (type.value) setType(type.value);
  idempotencyKey.value = d.key || newKey();
  step.value = Math.min(d.step || 0, steps.value.length - 1);
  maxReached.value = Math.min(d.maxReached || 0, steps.value.length - 1);
}
function discardDraft() {
  clearDraft();
  draftOffer.value = null;
}

// ---------------------------------------------------------------- watchers & boot
watch(current, (c) => {
  if (c === 'targeting') ensure('languages', props.urls.languages);
  if (c === 'audience') { ensure('audiences', props.urls.audiences); ensure('interests', props.urls.interests); }
  if (c === 'keywords' && type.value === 'DISPLAY') ensure('topics', props.urls.topics);
});
watch([goal, type, status, campaign, targeting, adGroup, ad, placementText, excludedPlacementText], () => {
  if (boot.value && !draftOffer.value && !(result.value && result.value.success)) saveDraft();
  if (result.value && !result.value.success) result.value = null;
}, { deep: true });

onMounted(async () => {
  try {
    boot.value = await api(props.urls.bootstrap);
    const d = readDraft();
    if (d && d.type) draftOffer.value = { savedAt: new Date(d.savedAt).toLocaleString(i18n.intl) };
  } catch (e) {
    bootError.value = e.message;
  }
});
</script>

<style scoped src="../facebook/builder.css"></style>
<style scoped>
.gb-head-icon { background: #fff; color: #4285F4; font-size: 24px; border: 1px solid var(--line); }
.gb-types { display: grid; grid-template-columns: repeat(auto-fill, minmax(210px, 1fr)); gap: 8px; margin-bottom: 10px; }
.gb-type { position: relative; display: flex; align-items: flex-start; gap: 10px; padding: 12px; border: 1px solid var(--line); border-radius: 12px; background: #fff; text-align: start; color: inherit; text-decoration: none; transition: border-color .15s ease, background .15s ease, box-shadow .15s ease; }
.gb-type > .bx:first-child { font-size: 21px; color: var(--ink); margin-top: 1px; }
.gb-type span:not(.fb-tag) { display: flex; flex-direction: column; gap: 2px; min-width: 0; }
.gb-type strong { font-size: .86rem; color: var(--ink); }
.gb-type small { font-size: .74rem; color: var(--muted, #6b7280); line-height: 1.3; }
.gb-type .fb-tag { position: absolute; top: 8px; inset-inline-end: 8px; }
.gb-type:hover { border-color: #c9ced8; }
.gb-type.on { border-color: var(--brand-2); background: var(--brand-soft); box-shadow: 0 0 0 3px rgba(0, 76, 255, .12); }
.gb-type-link { border-style: dashed; }
.gb-type-link .bxl-youtube { color: #FF0000 !important; }
.gb-ext { margin-inline-start: auto; color: var(--muted, #6b7280); }
.gb-bids { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 8px; }
.gb-bid { align-items: flex-start; padding: 10px 12px; border: 1px solid var(--line); border-radius: 12px; }
.gb-bid.on { border-color: var(--brand-2); background: var(--brand-soft); }
.gb-bid span, .fb-check span { display: flex; flex-direction: column; gap: 2px; }
.gb-bid small, .fb-check small { color: var(--muted, #6b7280); font-size: .74rem; line-height: 1.3; }
.gb-goal-list { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 4px 12px; margin-top: 10px; }
.gb-details summary { cursor: pointer; font-weight: 600; color: var(--ink); display: flex; align-items: center; gap: 6px; }
.gb-radius { display: grid; grid-template-columns: 1fr 1fr 90px 80px auto; gap: 8px; }
.gb-quick, .gb-row-actions { display: flex; flex-wrap: wrap; gap: 6px; align-items: center; margin: 8px 0; }
.gb-sched-row { display: grid; grid-template-columns: minmax(110px, 1.4fr) 1fr 1fr 110px auto; gap: 8px; align-items: center; margin-top: 8px; }
.gb-adj { display: flex; align-items: center; gap: 4px; }
.gb-adj span { color: var(--muted, #6b7280); font-size: .8rem; }
.gb-device { display: grid; grid-template-columns: 1fr 120px auto; gap: 10px; align-items: center; padding: 8px 0; border-bottom: 1px dashed var(--line); }
.gb-device > span { display: flex; align-items: center; gap: 8px; font-weight: 600; color: var(--ink); font-size: .86rem; }
.gb-device > span .bx { font-size: 18px; color: var(--brand-2); }
.gb-demo + .gb-demo { margin-top: 10px; }
.sb-chipset { display: flex; flex-wrap: wrap; gap: 6px; }
.fb-chip-btn.is-on { background: var(--ink); color: #fff; border-color: var(--ink); }
.fb-chip-btn.is-on.is-ex { background: #B91C1C; border-color: #B91C1C; }
.fb-chips { display: flex; flex-wrap: wrap; gap: 6px; }
.fb-chips .fb-chip { display: inline-flex; align-items: center; gap: 4px; max-width: 100%; padding: 3px 4px 3px 9px; border-radius: 999px; background: #EEF3FF; color: #0039CC; font-size: .78rem; font-weight: 600; }
.fb-chips .fb-chip button { border: 0; background: transparent; color: inherit; padding: 0 2px; line-height: 1; font-size: 15px; opacity: .7; }
.fb-chips .gb-chip-ex { background: #FEECEC; color: #B91C1C; }
.gb-ideas { display: grid; grid-template-columns: repeat(auto-fill, minmax(190px, 1fr)); gap: 6px; max-height: 260px; overflow-y: auto; margin-top: 8px; padding: 2px; }
.gb-idea { display: flex; flex-direction: column; gap: 2px; padding: 8px 10px; border: 1px solid var(--line); border-radius: 10px; background: #fff; text-align: start; }
.gb-idea strong { font-size: .82rem; color: var(--ink); }
.gb-idea small { font-size: .7rem; color: var(--muted, #6b7280); }
.gb-idea.on { border-color: var(--brand-2); background: var(--brand-soft); }
.gb-paths { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; }
.gb-text-row { display: grid; grid-template-columns: 1fr auto auto; gap: 8px; align-items: center; margin-bottom: 6px; }
.gb-count { font-size: .72rem; color: var(--muted, #6b7280); min-width: 42px; text-align: end; }
.gb-count.is-full { color: #B45309; font-weight: 700; }
.gb-images { display: flex; flex-wrap: wrap; gap: 8px; }
.gb-img { position: relative; width: 112px; height: 84px; border: 1px solid var(--line); border-radius: 10px; overflow: hidden; background: #f6f7f9; }
.gb-img img { width: 100%; height: 100%; object-fit: cover; }
.gb-img.is-bad { border-color: #B91C1C; }
.gb-img small { position: absolute; inset-inline: 0; bottom: 0; background: rgba(185, 28, 28, .9); color: #fff; font-size: .62rem; padding: 2px 4px; }
.gb-img-x { position: absolute; top: 4px; inset-inline-end: 4px; width: 22px; height: 22px; border: 0; border-radius: 50%; background: rgba(0, 0, 0, .55); color: #fff; display: grid; place-items: center; padding: 0; }
.gb-img-add { display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 2px; border-style: dashed; color: var(--muted, #6b7280); }
.gb-img-add .bx { font-size: 22px; }
.gb-serp { padding: 12px; border: 1px solid var(--line); border-radius: 12px; background: #fff; font-family: Arial, sans-serif; }
.gb-serp-sp { font-weight: 700; font-size: .72rem; color: #202124; }
.gb-serp-site { display: flex; align-items: center; gap: 8px; margin: 6px 0 4px; font-size: .78rem; color: #202124; overflow-wrap: anywhere; }
.gb-serp-fav { width: 24px; height: 24px; flex: 0 0 24px; border-radius: 50%; background: #f1f3f4; display: grid; place-items: center; color: #5f6368; }
.gb-serp-h { margin: 0 0 4px; color: #1a0dab; font-size: 1.02rem; font-weight: 400; line-height: 1.3; overflow-wrap: anywhere; }
.gb-serp-d { margin: 0; color: #4d5156; font-size: .8rem; line-height: 1.45; overflow-wrap: anywhere; }
.gb-dpreview { border: 1px solid var(--line); border-radius: 12px; overflow: hidden; background: #fff; }
.gb-dpreview-img, .gb-dpreview-ph { width: 100%; aspect-ratio: 1.91 / 1; object-fit: cover; display: block; }
.gb-dpreview-ph { display: grid; place-items: center; background: #f1f3f4; color: #9aa0a6; font-size: 34px; }
.gb-dpreview-body { display: grid; grid-template-columns: 34px 1fr 28px; gap: 8px; padding: 10px; align-items: center; }
.gb-dpreview-body strong { display: block; font-size: .86rem; color: #202124; overflow-wrap: anywhere; }
.gb-dpreview-body p { margin: 2px 0; font-size: .74rem; color: #5f6368; overflow-wrap: anywhere; }
.gb-dpreview-body small { font-size: .7rem; color: #80868b; }
.gb-dpreview-logo { width: 34px; height: 34px; border-radius: 50%; overflow: hidden; background: #f1f3f4; display: grid; place-items: center; color: #4285F4; }
.gb-dpreview-logo img { width: 100%; height: 100%; object-fit: cover; }
.gb-dpreview-cta { width: 28px; height: 28px; border-radius: 50%; background: #1a73e8; color: #fff; display: grid; place-items: center; }
[dir="rtl"] .gb-dpreview-cta .bx, [dir="rtl"] .fb-foot .bx-chevron-right, [dir="rtl"] .fb-foot .bx-chevron-left { transform: scaleX(-1); }
.sb-sum { list-style: none; margin: 0; padding: 0; display: grid; gap: 7px; font-size: .82rem; color: var(--text); }
.sb-sum li { display: flex; gap: 7px; align-items: flex-start; line-height: 1.35; }
.sb-sum li i { color: var(--brand-2); font-size: 15px; margin-top: 1px; }
@media (max-width: 767.98px) {
  .gb-radius { grid-template-columns: 1fr 1fr; }
  .gb-sched-row { grid-template-columns: 1fr 1fr; }
  .gb-device { grid-template-columns: 1fr 100px; }
  .gb-device .fb-chip-btn { grid-column: 1 / -1; justify-self: start; }
  .gb-paths { grid-template-columns: 1fr; }
}
</style>
