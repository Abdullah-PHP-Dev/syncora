<template>
  <div class="fb" :dir="i18n.dir">

    <!-- ===== Header ===== -->
    <header class="fb-head">
      <div class="fb-head-main">
        <span class="fb-head-icon"><i class="bx bxl-meta"></i></span>
        <div>
          <h4>{{ isInstagram ? t('header.title_instagram') : t('header.title') }}</h4>
          <p>{{ isInstagram ? t('header.subtitle_instagram') : t('header.subtitle') }}</p>
        </div>
      </div>
      <div class="fb-head-actions">
        <span v-if="boot" class="fb-acct" :title="boot.account.id"><i class="bx bx-briefcase-alt-2"></i> {{ boot.account.name }} · {{ boot.account.currency }}</span>
        <a :href="urls.campaignsList" class="btn btn-sm fb-btn-ghost"><i class="bx bx-list-ul"></i> {{ t('header.campaigns') }}</a>
      </div>
    </header>

    <!-- ===== Draft banner ===== -->
    <div v-if="draftOffer" class="fb-banner">
      <i class="bx bx-history"></i>
      <span>{{ t('draft.unfinished', { date: draftOffer.savedAt }) }}</span>
      <button type="button" class="btn btn-primary btn-sm" @click="resumeDraft">{{ t('draft.continue') }}</button>
      <button type="button" class="btn btn-sm fb-btn-ghost" @click="discardDraft">{{ t('draft.fresh') }}</button>
    </div>

    <!-- ===== Loading / blocked states ===== -->
    <div v-if="bootError" class="fb-card fb-empty">
      <span class="fb-empty-ic"><i class="bx bx-plug"></i></span>
      <h5>{{ t('boot.error_title') }}</h5>
      <p>{{ bootError }}</p>
      <a :href="urls.connections" class="btn btn-primary btn-sm"><i class="bx bx-link"></i> {{ t('boot.go_connections') }}</a>
    </div>
    <div v-else-if="!boot" class="fb-card fb-loading"><span class="spinner-border"></span> {{ t('boot.loading') }}</div>

    <template v-else>
      <div v-if="!boot.account.active" class="fb-alert is-warn"><i class="bx bx-error"></i> {{ t('boot.inactive') }}</div>

      <!-- ===== Stepper ===== -->
      <nav class="fb-steps" :aria-label="t('steps.aria')">
        <button v-for="(s, i) in steps" :key="s.key" type="button" class="fb-step"
                :class="{ 'is-current': step === i, 'is-done': i < step && !stepErrors[s.key], 'has-error': stepErrors[s.key], 'is-skipped': s.skipped }"
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
                <button type="button" role="tab" :class="{ on: campaign.mode === 'new' }" @click="setMode('campaign', 'new')"><i class="bx bx-plus"></i> {{ t('common.create_new') }}</button>
                <button type="button" role="tab" :class="{ on: campaign.mode === 'existing' }" @click="setMode('campaign', 'existing')"><i class="bx bx-folder-open"></i> {{ t('common.use_existing') }}</button>
              </div>
            </div>

            <template v-if="campaign.mode === 'existing'">
              <label class="fb-label">{{ t('campaign.choose') }}</label>
              <div class="fb-search"><i class="bx bx-search"></i><input v-model="campaignQuery" type="search" :placeholder="t('campaign.search')" :aria-label="t('campaign.search')"></div>
              <div v-if="lists.loading.campaigns" class="fb-inline-load"><span class="spinner-border spinner-border-sm"></span> {{ t('campaign.loading') }}</div>
              <p v-else-if="lists.errors.campaigns" class="fb-field-err">{{ lists.errors.campaigns }}</p>
              <div v-else class="fb-pick-list">
                <button v-for="c in filteredCampaigns" :key="c.id" type="button" class="fb-pick" :class="{ on: campaign.id === c.id, 'is-off': !c.goals.length }"
                        :disabled="!c.goals.length" @click="chooseCampaign(c)">
                  <span class="fb-pick-ic"><i class="bx" :class="goalIcon(c.goals[0])"></i></span>
                  <span class="fb-pick-main">
                    <strong>{{ c.name }}</strong>
                    <small>{{ objectiveLabel(c) }} · {{ statusLabel(c.status) }}<template v-if="c.campaign_budget"> · {{ t('campaign.campaign_budget', { amount: money(c.daily_budget || c.lifetime_budget) }) }}{{ c.daily_budget ? t('campaign.per_day_suffix') : '' }}</template><template v-if="c.special_ad_categories.length"> · {{ t('campaign.special') }}</template></small>
                  </span>
                  <i v-if="campaign.id === c.id" class="bx bxs-check-circle fb-pick-check"></i>
                </button>
                <div v-if="!filteredCampaigns.length" class="fb-mini-empty">
                  <i class="bx bx-folder"></i>
                  <span>{{ campaignQuery ? t('campaign.no_match') : t('campaign.none_yet') }}</span>
                  <button type="button" class="fb-link" @click="setMode('campaign', 'new')">{{ t('campaign.create_link') }}</button>
                </div>
              </div>
              <p v-if="selectedCampaign && selectedCampaign.goals.length > 1" class="fb-hint"><i class="bx bx-info-circle"></i> {{ t('campaign.multi_goal') }}</p>
              <div v-if="selectedCampaign && selectedCampaign.goals.length > 1" class="fb-goal-mini">
                <button v-for="g in selectedCampaign.goals" :key="g" type="button" :class="{ on: goal === g }" @click="setGoal(g)"><i class="bx" :class="goalIcon(g)"></i> {{ goals[g].label }}</button>
              </div>
            </template>

            <template v-else>
              <label class="fb-label">{{ t('campaign.achieve') }}</label>
              <div class="fb-goals">
                <button v-for="(g, key) in goals" :key="key" type="button" class="fb-goal" :class="{ on: goal === key }" @click="setGoal(key)">
                  <span class="fb-goal-ic"><i class="bx" :class="g.icon"></i></span>
                  <strong>{{ g.label }}</strong>
                  <small>{{ g.summary }}</small>
                </button>
              </div>
              <div v-if="goal" class="fb-guide"><i class="bx bx-bulb"></i><span><strong>{{ goals[goal].label }}:</strong> {{ goals[goal].guidance }}</span></div>

              <div class="fb-grid-2">
                <div>
                  <label class="fb-label" for="fbCampaignName">{{ t('campaign.name') }}</label>
                  <input id="fbCampaignName" v-model.trim="campaign.name" class="form-control" maxlength="255" :placeholder="goal ? goals[goal].label + ' - ' + today : t('campaign.name_placeholder')">
                </div>
                <div>
                  <label class="fb-label" for="fbSpecial">{{ t('campaign.special_label') }} <span class="fb-opt">{{ t('campaign.if_applies') }}</span></label>
                  <select id="fbSpecial" v-model="campaign.special_ad_category" class="form-control">
                    <option :value="null">{{ t('campaign.special_none') }}</option>
                    <option v-for="code in specialCategories" :key="code" :value="code">{{ t('campaign.special_options.' + code) }}</option>
                  </select>
                </div>
              </div>
              <p v-if="campaign.special_ad_category" class="fb-hint is-warn"><i class="bx bx-shield-quarter"></i> {{ t('campaign.special_warn') }}</p>

              <label class="fb-toggle">
                <input v-model="campaign.campaign_budget" type="checkbox">
                <span><strong>{{ t('campaign.cbo_title') }}</strong><small>{{ t('campaign.cbo_desc') }}</small></span>
              </label>
              <div v-if="campaign.campaign_budget" class="fb-grid-3">
                <div>
                  <label class="fb-label">{{ t('common.budget_type') }}</label>
                  <div class="fb-seg fb-seg-full">
                    <button type="button" :class="{ on: campaign.budget_type === 'daily' }" @click="campaign.budget_type = 'daily'">{{ t('common.daily') }}</button>
                    <button type="button" :class="{ on: campaign.budget_type === 'lifetime' }" @click="campaign.budget_type = 'lifetime'">{{ t('common.lifetime') }}</button>
                  </div>
                </div>
                <div>
                  <label class="fb-label" for="fbCampBudget">{{ t('campaign.budget', { currency: boot.account.currency }) }}</label>
                  <input id="fbCampBudget" v-model.number="campaign.budget" type="number" min="1" step="0.01" class="form-control">
                </div>
                <div>
                  <label class="fb-label" for="fbCampBid">{{ t('common.bid_strategy') }}</label>
                  <select id="fbCampBid" v-model="campaign.bid_strategy" class="form-control">
                    <option value="LOWEST_COST_WITHOUT_CAP">{{ t('common.highest_volume') }}</option>
                    <option value="COST_CAP">{{ t('common.cost_cap') }}</option>
                    <option value="LOWEST_COST_WITH_BID_CAP">{{ t('common.bid_cap') }}</option>
                  </select>
                </div>
              </div>
              <budget-summary v-if="campaign.campaign_budget" :summary="budgetSummary" :money="money" />
            </template>

            <template v-if="goal">
              <label class="fb-label mt-3">{{ t('campaign.where_go') }} <span class="fb-opt">{{ t('campaign.conversion_location') }}</span></label>
              <div class="fb-dests">
                <button v-for="(d, key) in goals[goal].destinations" :key="key" type="button" class="fb-dest" :class="{ on: destination === key }" @click="setDestination(key)">
                  <i class="bx" :class="destIcon(key)"></i> {{ d.label }}
                </button>
              </div>
              <p v-if="destRules && destRules.needs_pixel" class="fb-hint"><i class="bx bx-target-lock"></i> {{ t('campaign.needs_pixel') }}</p>
            </template>
            <p v-if="fieldErrors.campaign" class="fb-field-err">{{ fieldErrors.campaign }}</p>
          </div>

          <!-- ============ 2. Ad set ============ -->
          <div v-show="current === 'adset'" class="fb-card">
            <div class="fb-card-h">
              <h5>{{ t('adset.title') }}</h5>
              <div class="fb-seg">
                <button type="button" :class="{ on: adset.mode === 'new' }" @click="setMode('adset', 'new')"><i class="bx bx-plus"></i> {{ t('common.create_new') }}</button>
                <button type="button" :class="{ on: adset.mode === 'existing' }" :disabled="campaign.mode !== 'existing'" :title="campaign.mode !== 'existing' ? t('adset.no_sets_new_campaign') : ''" @click="setMode('adset', 'existing')"><i class="bx bx-folder-open"></i> {{ t('common.use_existing') }}</button>
              </div>
            </div>
            <p class="fb-sub">{{ t('adset.sub') }}</p>

            <template v-if="adset.mode === 'existing'">
              <div v-if="lists.loading.adsets" class="fb-inline-load"><span class="spinner-border spinner-border-sm"></span> {{ t('adset.loading') }}</div>
              <p v-else-if="lists.errors.adsets" class="fb-field-err">{{ lists.errors.adsets }}</p>
              <div v-else class="fb-pick-list">
                <button v-for="s in lists.adsets" :key="s.id" type="button" class="fb-pick" :class="{ on: adset.id === s.id }" @click="adset.id = s.id">
                  <span class="fb-pick-ic"><i class="bx bx-target-lock"></i></span>
                  <span class="fb-pick-main">
                    <strong>{{ s.name }}</strong>
                    <small>{{ statusLabel(s.status) }} · {{ optimizationLabel(s.optimization_goal) }}<template v-if="s.daily_budget || s.lifetime_budget"> · {{ s.daily_budget ? t('review.per_day', { amount: money(s.daily_budget) }) : t('review.lifetime', { amount: money(s.lifetime_budget) }) }}</template></small>
                    <small class="fb-pick-aud"><i class="bx bx-group"></i> {{ s.targeting_summary }}</small>
                  </span>
                  <i v-if="adset.id === s.id" class="bx bxs-check-circle fb-pick-check"></i>
                </button>
                <div v-if="!lists.adsets.length" class="fb-mini-empty"><i class="bx bx-target-lock"></i><span>{{ t('adset.none') }}</span><button type="button" class="fb-link" @click="setMode('adset', 'new')">{{ t('adset.create_link') }}</button></div>
              </div>
              <p class="fb-hint"><i class="bx bx-info-circle"></i> {{ t('adset.existing_hint') }}</p>
            </template>

            <template v-else>
              <div class="fb-grid-2">
                <div>
                  <label class="fb-label" for="fbAdsetName">{{ t('adset.name') }}</label>
                  <input id="fbAdsetName" v-model.trim="adset.name" class="form-control" maxlength="255" :placeholder="t('adset.name_placeholder', { date: today })">
                </div>
                <div v-if="destRules">
                  <label class="fb-label" for="fbOptGoal">{{ t('adset.optimize_for') }} <i class="bx bx-help-circle fb-tip" :title="t('adset.optimize_tip')"></i></label>
                  <select id="fbOptGoal" v-model="adset.optimization_goal" class="form-control">
                    <option v-for="(label, key) in destRules.optimization_goals" :key="key" :value="key">{{ label }}</option>
                  </select>
                </div>
              </div>

              <!-- Tracking / promoted object -->
              <div v-if="destRules && (destRules.needs_pixel || destRules.needs_app || needsPage)" class="fb-group">
                <h6><i class="bx bx-target-lock"></i> {{ t('adset.tracking') }}</h6>
                <div v-if="needsPage" class="fb-ident">
                  <label class="fb-label">{{ t('common.facebook_page') }}</label>
                  <identity-cards v-model="adset.page_id" :items="pageCards" :label="t('common.facebook_page')" />
                  <p v-if="!boot.pages.length" class="fb-field-err">{{ t('common.no_pages') }}</p>
                </div>
                <div class="fb-grid-2">
                  <template v-if="destRules.needs_pixel">
                    <div>
                      <label class="fb-label" for="fbPixel">{{ t('adset.pixel') }}</label>
                      <select id="fbPixel" v-model="adset.pixel_id" class="form-control" :disabled="lists.loading.pixels">
                        <option :value="null" disabled>{{ lists.loading.pixels ? t('common.loading') : t('adset.choose_pixel') }}</option>
                        <option v-for="p in lists.pixels" :key="p.id" :value="p.id">{{ p.name }}{{ p.last_fired ? '' : t('adset.no_events') }}</option>
                      </select>
                      <p v-if="!lists.loading.pixels && !lists.pixels.length" class="fb-field-err">{{ t('adset.no_pixel') }}</p>
                      <p v-else-if="selectedPixel && !selectedPixel.last_fired" class="fb-hint is-warn"><i class="bx bx-error"></i> {{ t('adset.pixel_not_fired') }}</p>
                    </div>
                    <div>
                      <label class="fb-label" for="fbEvent">{{ t('adset.event') }}</label>
                      <select id="fbEvent" v-model="adset.custom_event_type" class="form-control">
                        <option v-for="(label, key) in boot.conversion_events" :key="key" :value="key">{{ label }}</option>
                      </select>
                    </div>
                  </template>
                  <template v-if="destRules.needs_app">
                    <div>
                      <label class="fb-label" for="fbApp">{{ t('adset.app') }}</label>
                      <select id="fbApp" v-model="adset.application_id" class="form-control" @change="adset.object_store_url = (selectedApp && selectedApp.store_urls[0]) || null">
                        <option :value="null" disabled>{{ lists.loading.apps ? t('common.loading') : t('adset.choose_app') }}</option>
                        <option v-for="a in lists.apps" :key="a.id" :value="a.id">{{ a.name }}</option>
                      </select>
                      <p v-if="!lists.loading.apps && !lists.apps.length" class="fb-field-err">{{ t('adset.no_apps') }}</p>
                    </div>
                    <div>
                      <label class="fb-label" for="fbStoreUrl">{{ t('adset.store_url') }}</label>
                      <input id="fbStoreUrl" v-model.trim="adset.object_store_url" type="url" class="form-control" placeholder="https://apps.apple.com/...">
                    </div>
                  </template>
                </div>
              </div>

              <!-- Budget & schedule -->
              <div class="fb-group">
                <h6><i class="bx bx-wallet"></i> {{ t('adset.budget_schedule') }}</h6>
                <div v-if="campaignHasBudget" class="fb-hint"><i class="bx bx-info-circle"></i> {{ t('adset.cbo_hint') }}</div>
                <div v-else class="fb-grid-3">
                  <div>
                    <label class="fb-label">{{ t('common.budget_type') }}</label>
                    <div class="fb-seg fb-seg-full">
                      <button type="button" :class="{ on: adset.budget_type === 'daily' }" @click="adset.budget_type = 'daily'">{{ t('common.daily') }}</button>
                      <button type="button" :class="{ on: adset.budget_type === 'lifetime' }" @click="adset.budget_type = 'lifetime'">{{ t('common.lifetime') }}</button>
                    </div>
                  </div>
                  <div>
                    <label class="fb-label" for="fbBudget">{{ t(adset.budget_type === 'daily' ? 'adset.daily_budget' : 'adset.lifetime_budget', { currency: boot.account.currency }) }}</label>
                    <input id="fbBudget" v-model.number="adset.budget" type="number" min="1" step="0.01" class="form-control">
                    <p v-if="boot.account.min_daily_budget && adset.budget_type === 'daily' && adset.budget && adset.budget < boot.account.min_daily_budget" class="fb-field-err">{{ t('adset.min_budget', { amount: money(boot.account.min_daily_budget) }) }}</p>
                  </div>
                  <div>
                    <label class="fb-label" for="fbBid">{{ t('common.bid_strategy') }}</label>
                    <select id="fbBid" v-model="adset.bid_strategy" class="form-control">
                      <option value="LOWEST_COST_WITHOUT_CAP">{{ t('common.highest_volume') }}</option>
                      <option value="COST_CAP">{{ t('common.cost_cap') }}</option>
                      <option value="LOWEST_COST_WITH_BID_CAP">{{ t('common.bid_cap') }}</option>
                    </select>
                  </div>
                </div>
                <p v-if="!campaignHasBudget" class="fb-hint"><i class="bx bx-bulb"></i> <span v-html="t('adset.budget_help')"></span></p>
                <div v-if="!campaignHasBudget && adset.bid_strategy !== 'LOWEST_COST_WITHOUT_CAP'" class="fb-grid-3">
                  <div>
                    <label class="fb-label" for="fbBidAmount">{{ t(adset.bid_strategy === 'COST_CAP' ? 'adset.cost_goal_amount' : 'adset.bid_cap_amount', { currency: boot.account.currency }) }}</label>
                    <input id="fbBidAmount" v-model.number="adset.bid_amount" type="number" min="0.01" step="0.01" class="form-control">
                  </div>
                  <p class="fb-hint fb-span-2"><i class="bx bx-info-circle"></i> {{ t('adset.cap_hint') }}</p>
                </div>
                <div class="fb-grid-2">
                  <div>
                    <label class="fb-label" for="fbStart">{{ t('adset.start') }}</label>
                    <input id="fbStart" v-model="adset.start_time" type="datetime-local" class="form-control" :min="nowLocal">
                  </div>
                  <div>
                    <label class="fb-label" for="fbEnd">{{ t('adset.end') }} <span class="fb-opt">{{ adset.budget_type === 'lifetime' && !campaignHasBudget ? t('adset.end_required') : t('adset.end_optional') }}</span></label>
                    <input id="fbEnd" v-model="adset.end_time" type="datetime-local" class="form-control" :min="adset.start_time || nowLocal">
                  </div>
                </div>
                <budget-summary v-if="!campaignHasBudget || campaign.mode === 'new'" :summary="budgetSummary" :money="money" />
                <p v-if="boot.account.timezone" class="fb-hint"><i class="bx bx-time-five"></i> {{ t('adset.timezone', { zone: boot.account.timezone }) }}</p>
              </div>
            </template>
            <p v-if="fieldErrors.adset" class="fb-field-err">{{ fieldErrors.adset }}</p>
          </div>

          <!-- ============ 3. Audience ============ -->
          <div v-show="current === 'audience'" class="fb-card">
            <div class="fb-card-h">
              <h5>{{ t('audience.title') }}</h5>
              <label class="fb-switch"><input v-model="ui.advanced" type="checkbox"><span>{{ t('audience.advanced') }}</span></label>
            </div>

            <template v-if="adset.mode === 'existing'">
              <div class="fb-mini-empty is-info"><i class="bx bx-group"></i><span>{{ t('audience.existing_info') }}</span></div>
            </template>

            <template v-else>
              <div v-if="specialCategory" class="fb-hint is-warn"><i class="bx bx-shield-quarter"></i> {{ t('audience.special_warn') }}</div>

              <div v-if="ui.advanced && lists.audiences.saved.length" class="fb-group">
                <h6><i class="bx bx-bookmark"></i> {{ t('audience.saved') }} <span class="fb-opt">{{ t('common.optional') }}</span></h6>
                <select v-model="targeting.saved_audience_id" class="form-control">
                  <option :value="null">{{ t('audience.saved_build') }}</option>
                  <option v-for="a in lists.audiences.saved" :key="a.id" :value="a.id">{{ a.name }} - {{ a.summary }}</option>
                </select>
                <p v-if="targeting.saved_audience_id" class="fb-hint"><i class="bx bx-info-circle"></i> {{ t('audience.saved_hint') }}</p>
              </div>

              <template v-if="!targeting.saved_audience_id">
                <div class="fb-group">
                  <h6><i class="bx bx-map"></i> {{ t('audience.locations') }}</h6>
                  <search-picker v-model="targeting.locations" :fetcher="searchLocations" item-key="key" :placeholder="t('audience.search_locations')">
                    <template #chip="{ item }">{{ locationLabel(item) }}</template>
                    <template #option="{ item }">
                      <span class="sp-opt-main"><strong>{{ item.name }}</strong> <small class="fb-muted">{{ [item.region, item.country_name].filter(Boolean).join(', ') }}</small></span>
                      <span class="fb-tag">{{ locationType(item.type) }}</span>
                    </template>
                  </search-picker>
                  <div v-for="city in targeting.locations.filter(l => l.type === 'city')" :key="'r' + city.key" class="fb-radius">
                    <span><i class="bx bx-current-location"></i> {{ city.name }}</span>
                    <select :value="city.radius || ''" class="form-control form-control-sm" @change="setRadius(city, $event.target.value)">
                      <option value="">{{ t('audience.city_only') }}</option>
                      <option v-for="r in radiusOptions" :key="r" :value="r">{{ t('audience.radius', { km: r }) }}</option>
                    </select>
                  </div>
                  <template v-if="ui.advanced">
                    <label class="fb-label mt-2">{{ t('audience.exclude_locations') }}</label>
                    <search-picker v-model="targeting.excluded_locations" :fetcher="searchLocations" item-key="key" chip-tone="exclude" :placeholder="t('audience.search_exclude')">
                      <template #chip="{ item }">{{ locationLabel(item) }}</template>
                      <template #option="{ item }"><span class="sp-opt-main"><strong>{{ item.name }}</strong> <small class="fb-muted">{{ item.country_name }}</small></span><span class="fb-tag">{{ locationType(item.type) }}</span></template>
                    </search-picker>
                  </template>
                </div>

                <div class="fb-group">
                  <h6><i class="bx bx-user"></i> {{ t('audience.people') }}</h6>
                  <div class="fb-grid-3">
                    <div>
                      <label class="fb-label">{{ t('audience.age') }}</label>
                      <div class="fb-age">
                        <select v-model.number="targeting.age_min" class="form-control" :disabled="specialCategory">
                          <option v-for="a in ages" :key="'min' + a" :value="a">{{ a }}</option>
                        </select>
                        <span>{{ t('audience.to') }}</span>
                        <select v-model.number="targeting.age_max" class="form-control" :disabled="specialCategory || targeting.advantage_audience">
                          <option v-for="a in ages" :key="'max' + a" :value="a">{{ a === 65 ? '65+' : a }}</option>
                        </select>
                      </div>
                    </div>
                    <div>
                      <label class="fb-label">{{ t('audience.gender') }}</label>
                      <div class="fb-seg fb-seg-full">
                        <button type="button" :class="{ on: !targeting.genders.length }" :disabled="specialCategory" @click="targeting.genders = []">{{ t('audience.all') }}</button>
                        <button type="button" :class="{ on: targeting.genders[0] === 1 }" :disabled="specialCategory" @click="targeting.genders = [1]">{{ t('audience.men') }}</button>
                        <button type="button" :class="{ on: targeting.genders[0] === 2 }" :disabled="specialCategory" @click="targeting.genders = [2]">{{ t('audience.women') }}</button>
                      </div>
                    </div>
                  </div>
                  <label class="fb-label mt-2">{{ t('audience.languages') }} <span class="fb-opt">{{ t('audience.languages_opt') }}</span></label>
                  <search-picker v-model="targeting.locales" :fetcher="searchLocales" item-key="key" :min-chars="1" :placeholder="t('audience.search_languages')" />
                  <div class="fb-quick">
                    <span>{{ t('audience.quick_add') }}</span>
                    <button v-for="l in quickLocales" :key="l.query" type="button" class="fb-chip-btn" :disabled="quickLoading === l.query || hasLocale(l)" @click="addQuickLocale(l)">
                      <span v-if="quickLoading === l.query" class="spinner-border spinner-border-sm"></span><i v-else class="bx" :class="hasLocale(l) ? 'bx-check' : 'bx-plus'"></i> {{ t('audience.quick.' + l.id) }}
                    </button>
                  </div>
                  <p v-if="quickError" class="fb-field-err">{{ quickError }}</p>
                  <p class="fb-hint"><i class="bx bx-info-circle"></i> {{ t('audience.languages_hint') }}</p>
                </div>

                <div v-if="!specialCategory" class="fb-group">
                  <h6><i class="bx bx-purchase-tag"></i> {{ t('audience.detailed') }} <span class="fb-opt">{{ t('audience.detailed_sub') }}</span></h6>
                  <search-picker :value="detailedItems" :fetcher="searchTargeting" :placeholder="t('audience.search_detailed')" @input="setDetailedItems">
                    <template #option="{ item }">
                      <span class="sp-opt-main"><strong>{{ item.name }}</strong> <small class="fb-muted">{{ (item.path || []).slice(0, -1).join(' › ') }}</small></span>
                      <span class="fb-tag">{{ typeLabel(item.type) }}</span>
                      <small v-if="item.size_min" class="fb-muted">{{ compact(item.size_min) }}–{{ compact(item.size_max) }}</small>
                    </template>
                  </search-picker>
                  <p class="fb-hint"><i class="bx bx-info-circle"></i> <span v-html="t('audience.detailed_hint')"></span></p>

                  <div class="fb-work">
                    <label class="fb-label mt-2">{{ t('audience.work') }} <span class="fb-opt">{{ t('audience.work_sub') }}</span></label>
                    <div class="fb-seg fb-seg-wrap">
                      <button v-for="w in workTypes" :key="w" type="button" :class="{ on: workType === w }" @click="workType = w">{{ t('audience.work_types.' + w) }}</button>
                    </div>
                    <search-picker :key="'work-' + workType" :value="workItems" :fetcher="searchWork" :placeholder="t('audience.work_placeholder.' + workType)" @input="setWorkItems">
                      <template #option="{ item }">
                        <span class="sp-opt-main"><strong>{{ item.name }}</strong> <small v-if="item.description" class="fb-muted">{{ item.description }}</small></span>
                        <span class="fb-tag">{{ typeLabel(item.type) }}</span>
                      </template>
                    </search-picker>
                    <p class="fb-hint"><i class="bx bx-briefcase"></i> {{ t('audience.work_hint') }}</p>
                  </div>
                  <template v-if="ui.advanced">
                    <div v-for="(group, gi) in targeting.narrow" :key="'n' + gi" class="fb-narrow">
                      <div class="fb-narrow-h"><strong>{{ t('audience.must_match') }}</strong><button type="button" class="fb-link" @click="targeting.narrow.splice(gi, 1)">{{ t('common.remove') }}</button></div>
                      <search-picker :value="group" :fetcher="searchTargeting" :placeholder="t('audience.narrow_further')" @input="v => targeting.narrow.splice(gi, 1, v)" />
                    </div>
                    <button v-if="targeting.interests.length && targeting.narrow.length < 3" type="button" class="fb-link" @click="targeting.narrow.push([])"><i class="bx bx-plus"></i> {{ t('audience.narrow') }}</button>
                  </template>
                </div>

                <div v-if="ui.advanced" class="fb-group">
                  <h6><i class="bx bx-user-check"></i> {{ t('audience.custom') }}</h6>
                  <div v-if="lists.loading.audiences" class="fb-inline-load"><span class="spinner-border spinner-border-sm"></span> {{ t('audience.loading_audiences') }}</div>
                  <template v-else-if="usableAudiences.length">
                    <div class="fb-grid-2">
                      <div>
                        <label class="fb-label">{{ t('audience.include') }}</label>
                        <search-picker v-model="ui.includeAudiences" :options="usableAudiences" :placeholder="t('audience.choose_audiences')">
                          <template #option="{ item }"><span class="sp-opt-main">{{ item.name }} <small v-if="!item.ready" class="fb-muted">({{ item.status || t('audience.not_ready') }})</small></span><span class="fb-tag">{{ t('audience.kinds.' + item.kind) }}</span></template>
                        </search-picker>
                      </div>
                      <div>
                        <label class="fb-label">{{ t('audience.exclude') }}</label>
                        <search-picker v-model="ui.excludeAudiences" :options="lists.audiences.custom" chip-tone="exclude" :placeholder="t('audience.exclude_audiences')" />
                      </div>
                    </div>
                  </template>
                  <p v-else class="fb-hint"><i class="bx bx-info-circle"></i> {{ t('audience.no_custom') }}</p>
                </div>

                <label v-if="!specialCategory" class="fb-toggle">
                  <input v-model="targeting.advantage_audience" type="checkbox">
                  <span><strong>{{ t('audience.advantage') }}</strong><small>{{ t('audience.advantage_desc') }}</small></span>
                </label>
              </template>

              <div v-if="isInstagram" class="fb-group">
                <h6><i class="bx bxl-instagram"></i> {{ t('audience.ig_placements') }}</h6>
                <p class="fb-hint mt-0 mb-2"><i class="bx bx-bulb"></i> {{ t('audience.ig_placements_hint') }}</p>
                <div class="fb-positions">
                  <label v-for="pos in igPositions" :key="pos.key" class="fb-check is-sm"><input v-model="placements.instagram_positions" type="checkbox" :value="pos.key"> {{ t('audience.positions.' + pos.key) }}</label>
                </div>
                <p v-if="placements.instagram_positions.some(p => ['explore_home', 'ig_search'].includes(p))" class="fb-hint"><i class="bx bx-info-circle"></i> {{ t('audience.ig_dependencies') }}</p>
                <div class="fb-positions mt-2">
                  <small>{{ t('audience.devices') }}</small>
                  <label class="fb-check is-sm"><input v-model="placements.device_platforms" type="checkbox" value="mobile"> {{ t('audience.mobile') }}</label>
                  <label class="fb-check is-sm"><input v-model="placements.device_platforms" type="checkbox" value="desktop"> {{ t('audience.desktop') }}</label>
                </div>
              </div>

              <div v-else class="fb-group">
                <h6><i class="bx bx-layout"></i> {{ t('audience.placements') }}</h6>
                <div class="fb-seg">
                  <button type="button" :class="{ on: placements.mode === 'advantage' }" @click="placements.mode = 'advantage'">{{ t('audience.advantage_placements') }}</button>
                  <button type="button" :class="{ on: placements.mode === 'manual' }" @click="placements.mode = 'manual'">{{ t('audience.manual') }}</button>
                </div>
                <p class="fb-hint"><i class="bx bx-bulb"></i> {{ placements.mode === 'advantage' ? t('audience.placements_advantage_hint') : t('audience.placements_manual_hint') }}</p>
                <div v-if="placements.mode === 'manual'" class="fb-place-grid">
                  <label v-for="p in platformOptions" :key="p.key" class="fb-check"><input v-model="placements.publisher_platforms" type="checkbox" :value="p.key"><i class="bx" :class="p.icon"></i> {{ t('audience.platforms.' + p.key) }}</label>
                  <div v-if="placements.publisher_platforms.includes('facebook')" class="fb-positions">
                    <small>{{ t('audience.fb_positions') }}</small>
                    <label v-for="pos in fbPositions" :key="pos.key" class="fb-check is-sm"><input v-model="placements.facebook_positions" type="checkbox" :value="pos.key"> {{ t('audience.positions.' + pos.key) }}</label>
                  </div>
                  <div v-if="placements.publisher_platforms.includes('instagram')" class="fb-positions">
                    <small>{{ t('audience.ig_positions') }}</small>
                    <label v-for="pos in igPositions" :key="pos.key" class="fb-check is-sm"><input v-model="placements.instagram_positions" type="checkbox" :value="pos.key"> {{ t('audience.positions.' + pos.key) }}</label>
                  </div>
                  <div class="fb-positions">
                    <small>{{ t('audience.devices') }}</small>
                    <label class="fb-check is-sm"><input v-model="placements.device_platforms" type="checkbox" value="mobile"> {{ t('audience.mobile') }}</label>
                    <label class="fb-check is-sm"><input v-model="placements.device_platforms" type="checkbox" value="desktop"> {{ t('audience.desktop') }}</label>
                  </div>
                </div>
              </div>
            </template>
            <p v-if="fieldErrors.audience" class="fb-field-err">{{ fieldErrors.audience }}</p>
          </div>

          <!-- ============ 4. Creative ============ -->
          <div v-show="current === 'creative'" class="fb-card">
            <div class="fb-card-h">
              <h5>{{ t('creative.title') }}</h5>
              <div class="fb-seg">
                <button type="button" :class="{ on: creative.mode === 'new' }" @click="setMode('creative', 'new')"><i class="bx bx-plus"></i> {{ t('common.create_new') }}</button>
                <button type="button" :class="{ on: creative.mode === 'existing' }" @click="setMode('creative', 'existing')"><i class="bx bx-folder-open"></i> {{ t('common.use_existing') }}</button>
              </div>
            </div>

            <template v-if="creative.mode === 'existing'">
              <div class="fb-search"><i class="bx bx-search"></i><input v-model="creativeQuery" type="search" :placeholder="t('creative.search')"></div>
              <div v-if="lists.loading.creatives" class="fb-inline-load"><span class="spinner-border spinner-border-sm"></span> {{ t('creative.loading') }}</div>
              <p v-else-if="lists.errors.creatives" class="fb-field-err">{{ lists.errors.creatives }}</p>
              <div v-else class="fb-creative-grid">
                <button v-for="c in filteredCreatives" :key="c.id" type="button" class="fb-creative" :class="{ on: creative.id === c.id }" @click="chooseCreative(c)">
                  <span class="fb-creative-img"><img v-if="c.thumbnail" :src="c.thumbnail" alt="" loading="lazy"><i v-else class="bx bx-image"></i></span>
                  <strong>{{ c.name }}</strong>
                  <small>{{ c.headline || c.text || c.type }}</small>
                </button>
                <div v-if="!filteredCreatives.length" class="fb-mini-empty"><i class="bx bx-image"></i><span>{{ t('creative.none') }}</span><button type="button" class="fb-link" @click="setMode('creative', 'new')">{{ t('creative.create_link') }}</button></div>
              </div>
              <p v-if="creative.id" class="fb-hint"><i class="bx bx-info-circle"></i> {{ t('creative.existing_hint') }}</p>
            </template>

            <template v-else>
              <div class="fb-ident">
                <label class="fb-label">{{ t('creative.page') }} <span class="fb-opt">{{ t('creative.page_sub') }}</span></label>
                <identity-cards v-model="creative.page_id" :items="pageCards" :label="t('creative.page')" />
                <p v-if="!boot.pages.length" class="fb-field-err">{{ t('common.no_pages') }}</p>
              </div>
              <div class="fb-ident">
                <label class="fb-label">{{ t('creative.instagram') }} <span class="fb-opt">{{ isInstagram ? t('creative.instagram_required') : t('creative.instagram_sub') }}</span></label>
                <div v-if="lists.loading.instagram" class="fb-inline-load"><span class="spinner-border spinner-border-sm"></span> {{ t('creative.loading_instagram') }}</div>
                <identity-cards v-else-if="isInstagram" v-model="creative.instagram_user_id" :items="instagramCards" platform="instagram" :label="t('creative.instagram')" />
                <identity-cards v-else v-model="creative.instagram_user_id" :items="instagramCards" platform="instagram" :label="t('creative.instagram')"
                                :empty-label="t('creative.page_on_instagram')" :empty-sub="t('creative.page_on_instagram_sub')" empty-icon="bxl-facebook-circle" />
                <p v-if="isInstagram && !lists.loading.instagram && !lists.instagram.length" class="fb-field-err">{{ lists.errors.instagram || t('creative.no_instagram') }}</p>
              </div>

              <div v-if="destRules && destRules.needs_lead_form" class="fb-group">
                <h6><i class="bx bx-id-card"></i> {{ t('creative.instant_form') }}</h6>
                <select v-model="creative.lead_form_id" class="form-control" :disabled="lists.loading.leadForms || !creative.page_id">
                  <option :value="null" disabled>{{ !creative.page_id ? t('creative.choose_page_first') : (lists.loading.leadForms ? t('creative.loading_forms') : t('creative.choose_form')) }}</option>
                  <option v-for="f in lists.leadForms" :key="f.id" :value="f.id">{{ f.name }}</option>
                </select>
                <p v-if="lists.errors.leadForms" class="fb-field-err">{{ lists.errors.leadForms }}</p>
                <p v-else-if="creative.page_id && !lists.loading.leadForms && !lists.leadForms.length" class="fb-hint is-warn"><i class="bx bx-error"></i> {{ t('creative.no_forms') }}</p>
              </div>

              <label class="fb-label">{{ t('creative.format') }}</label>
              <div class="fb-seg">
                <button type="button" :class="{ on: creative.format === 'IMAGE' }" @click="setFormat('IMAGE')"><i class="bx bx-image"></i> {{ t('creative.single_image') }}</button>
                <button type="button" :class="{ on: creative.format === 'VIDEO' }" @click="setFormat('VIDEO')"><i class="bx bx-video"></i> {{ t('creative.video') }}</button>
                <button type="button" :class="{ on: creative.format === 'CAROUSEL' }" :disabled="destRules && (destRules.message_app || destRules.needs_lead_form)" @click="setFormat('CAROUSEL')"><i class="bx bx-carousel"></i> {{ t('creative.carousel') }}</button>
              </div>

              <!-- Media -->
              <div class="fb-group">
                <h6><i class="bx bx-photo-album"></i> {{ creative.format === 'CAROUSEL' ? t('creative.cards') : t('creative.media') }}</h6>
                <div v-if="creative.format !== 'CAROUSEL'" class="fb-media-row">
                  <div v-if="creative.media[0]" class="fb-media">
                    <img v-if="creative.media[0].type === 'image' || creative.media[0].thumbnail_url" :src="creative.media[0].thumbnail_url || creative.media[0].url" alt="">
                    <span v-else class="fb-media-ph"><i class="bx bx-play"></i></span>
                    <div><strong>{{ creative.media[0].original_name }}</strong><small>{{ creative.media[0].type === 'video' ? t('creative.video') : t('creative.image') }}<template v-if="creative.media[0].width"> · {{ creative.media[0].width }}×{{ creative.media[0].height }}</template></small></div>
                    <button type="button" class="fb-icon-btn" :aria-label="t('common.remove')" @click="creative.media = []"><i class="bx bx-trash"></i></button>
                  </div>
                  <div v-else class="fb-media-actions">
                    <button type="button" class="btn btn-sm fb-btn-ghost" @click="openGallery('media')"><i class="bx bx-images"></i> {{ t('creative.media_gallery') }}</button>
                    <label class="btn btn-sm fb-btn-ghost"><i class="bx bx-cloud-upload"></i> {{ t('creative.upload_new') }}<input type="file" hidden :accept="creative.format === 'VIDEO' ? 'video/mp4,video/quicktime' : 'image/jpeg,image/png'" @change="uploadNew($event, 'media')"></label>
                  </div>
                </div>
                <div v-if="creative.format === 'VIDEO' && creative.media[0]" class="fb-media-row">
                  <div v-if="creative.thumbnail" class="fb-media is-sm">
                    <img :src="creative.thumbnail.url" alt="">
                    <div><strong>{{ t('creative.thumbnail') }}</strong><small>{{ creative.thumbnail.original_name }}</small></div>
                    <button type="button" class="fb-icon-btn" :aria-label="t('creative.remove_thumbnail')" @click="creative.thumbnail = null"><i class="bx bx-trash"></i></button>
                  </div>
                  <div v-else class="fb-hint"><i class="bx bx-image"></i> {{ creative.media[0].thumbnail_url ? t('creative.own_thumbnail') : t('creative.add_thumbnail') }}
                    <button type="button" class="fb-link" @click="openGallery('thumbnail')">{{ t('creative.choose_thumbnail') }}</button>
                  </div>
                </div>
                <div v-if="creative.format === 'CAROUSEL'" class="fb-cards">
                  <div v-for="(card, ci) in creative.cards" :key="'c' + ci" class="fb-ccard">
                    <img :src="card.asset.url" alt="">
                    <input v-model.trim="card.headline" class="form-control form-control-sm" :placeholder="t('creative.card_headline')" maxlength="255">
                    <input v-model.trim="card.description" class="form-control form-control-sm" :placeholder="t('creative.card_description')" maxlength="255">
                    <input v-model.trim="card.link" type="url" class="form-control form-control-sm" :placeholder="t('creative.card_link')">
                    <button type="button" class="fb-icon-btn" :aria-label="t('creative.remove_card')" @click="creative.cards.splice(ci, 1)"><i class="bx bx-x"></i></button>
                  </div>
                  <div v-if="creative.cards.length < 10" class="fb-ccard is-add">
                    <button type="button" class="btn btn-sm fb-btn-ghost" @click="openGallery('card')"><i class="bx bx-images"></i> {{ t('creative.gallery') }}</button>
                    <label class="btn btn-sm fb-btn-ghost"><i class="bx bx-cloud-upload"></i> {{ t('creative.upload') }}<input type="file" hidden accept="image/jpeg,image/png" @change="uploadNew($event, 'card')"></label>
                  </div>
                </div>
                <p v-if="uploading" class="fb-inline-load"><span class="spinner-border spinner-border-sm"></span> {{ t('creative.uploading', { percent: uploading }) }}</p>
                <p v-if="mediaError" class="fb-field-err">{{ mediaError }}</p>
                <p class="fb-hint"><i class="bx bx-info-circle"></i> {{ creative.format === 'VIDEO' ? t('creative.hint_video') : t('creative.hint_image') }}</p>
              </div>

              <!-- Text -->
              <div class="fb-group">
                <h6><i class="bx bx-text"></i> {{ t('creative.text') }}</h6>
                <label class="fb-label" for="fbPrimary">{{ t('creative.primary') }} <span class="fb-count">{{ (creative.primary_text || '').length }}/2200</span></label>
                <textarea id="fbPrimary" v-model="creative.primary_text" class="form-control" rows="3" maxlength="2200" :placeholder="t('creative.primary_placeholder')"></textarea>
                <div class="fb-grid-2">
                  <div>
                    <label class="fb-label" for="fbHeadline">{{ t('creative.headline') }} <span class="fb-count">{{ t('creative.headline_count', { count: (creative.headline || '').length }) }}</span></label>
                    <input id="fbHeadline" v-model="creative.headline" class="form-control" maxlength="255">
                  </div>
                  <div>
                    <label class="fb-label" for="fbDesc">{{ t('creative.description') }} <span class="fb-opt">{{ t('common.optional') }}</span></label>
                    <input id="fbDesc" v-model="creative.description" class="form-control" maxlength="255">
                  </div>
                </div>
                <div class="fb-grid-2">
                  <div v-if="showLink">
                    <label class="fb-label" for="fbLink">{{ t('creative.website') }} <span class="fb-opt">{{ linkRequired ? t('creative.where_button') : t('creative.where_button_optional') }}</span></label>
                    <input id="fbLink" v-model.trim="creative.link" type="url" class="form-control" placeholder="https://yourstore.com/offer">
                    <p v-if="creative.link && !validLink" class="fb-field-err">{{ t('creative.url_invalid') }}</p>
                    <p v-else-if="!linkRequired && !creative.link" class="fb-hint"><i class="bx bx-info-circle"></i> {{ t('creative.url_fallback') }}</p>
                  </div>
                  <div v-if="destRules">
                    <label class="fb-label" for="fbCta">{{ t('creative.cta') }}</label>
                    <select id="fbCta" v-model="creative.cta" class="form-control">
                      <option v-for="c in destRules.ctas" :key="c" :value="c">{{ ctaLabel(c) }}</option>
                    </select>
                  </div>
                </div>
              </div>
            </template>
            <p v-if="fieldErrors.creative" class="fb-field-err">{{ fieldErrors.creative }}</p>
          </div>

          <!-- ============ 5. Review ============ -->
          <div v-show="current === 'review'" class="fb-card">
            <div class="fb-card-h"><h5>{{ t('review.title') }}</h5></div>
            <div class="fb-grid-2">
              <div>
                <label class="fb-label" for="fbAdName">{{ t('review.ad_name') }}</label>
                <input id="fbAdName" v-model.trim="ad.name" class="form-control" maxlength="255">
              </div>
              <div>
                <label class="fb-label">{{ t('review.when') }}</label>
                <div class="fb-seg fb-seg-full">
                  <button type="button" :class="{ on: ad.status === 'PAUSED' }" @click="ad.status = 'PAUSED'"><i class="bx bx-pause-circle"></i> {{ t('review.save_paused') }}</button>
                  <button type="button" :class="{ on: ad.status === 'ACTIVE' }" @click="ad.status = 'ACTIVE'"><i class="bx bx-play-circle"></i> {{ t('review.publish') }}</button>
                </div>
              </div>
            </div>
            <p class="fb-hint"><i class="bx bx-info-circle"></i> {{ ad.status === 'ACTIVE' ? t('review.hint_active') : t('review.hint_paused') }}</p>

            <div class="fb-review">
              <div v-for="row in reviewRows" :key="row.key" class="fb-review-row">
                <span class="fb-review-l"><i class="bx" :class="row.icon"></i> {{ row.label }}</span>
                <span class="fb-review-v">{{ row.value }}</span>
                <button type="button" class="fb-link" @click="goTo(row.step)">{{ t('common.edit') }}</button>
              </div>
            </div>

            <div v-if="blockers.length" class="fb-blockers">
              <strong><i class="bx bx-error-circle"></i> {{ t('review.fix_before') }}</strong>
              <ul><li v-for="b in blockers" :key="b.text"><button type="button" class="fb-link" @click="goTo(b.step)">{{ b.text }}</button></li></ul>
            </div>

            <!-- Result -->
            <div v-if="result && result.success" class="fb-result is-ok">
              <i class="bx bx-check-circle"></i>
              <div>
                <strong>{{ result.message }}</strong>
                <p>{{ result.status === 'ACTIVE' ? t('result.in_review') : t('result.saved_paused') }} {{ t('result.ad_id', { id: result.ad_id }) }}</p>
                <a :href="result.redirect_url || urls.campaignsList" class="btn btn-primary btn-sm">{{ t('result.go_campaigns') }}</a>
                <button type="button" class="btn btn-sm fb-btn-ghost" @click="startAnother">{{ t('result.another') }}</button>
              </div>
            </div>
            <div v-else-if="result" class="fb-result is-err">
              <i class="bx bx-error-circle"></i>
              <div>
                <strong>{{ result.step ? t('result.step_failed', { step: stepTitle(result.step) }) : t('result.failed') }}</strong>
                <p>{{ result.message }}</p>
                <ul v-if="result.created && result.created.length" class="fb-created">
                  <li v-for="c in result.created" :key="c.type"><i class="bx bx-check"></i> {{ t('result.created_item', { step: stepTitle(c.type), action: c.reused ? t('result.selected') : t('result.created'), id: c.id }) }}</li>
                </ul>
                <p class="fb-muted">{{ t('result.fix_retry') }}</p>
              </div>
            </div>
          </div>

          <!-- ===== Footer nav ===== -->
          <div class="fb-foot">
            <button type="button" class="btn btn-sm fb-btn-ghost" :disabled="step === 0 || submitting" @click="back"><i class="bx bx-chevron-left"></i> {{ t('common.back') }}</button>
            <span class="fb-saved" :class="{ on: savedAt }"><i class="bx bx-cloud-upload"></i> {{ savedAt ? t('draft.saved', { time: savedAt }) : t('draft.auto') }}</span>
            <button v-if="current !== 'review'" type="button" class="btn btn-primary btn-sm" @click="next">{{ t('common.continue') }} <i class="bx bx-chevron-right"></i></button>
            <button v-else type="button" class="btn btn-primary btn-sm" :disabled="submitting || blockers.length > 0 || (result && result.success)" @click="submit">
              <span v-if="submitting" class="spinner-border spinner-border-sm"></span><i v-else class="bx bx-rocket"></i>
              {{ submitting ? t('foot.creating') : (result && !result.success ? t('foot.try_again') : t('foot.create_ad')) }}
            </button>
          </div>
        </section>

        <!-- ===== Side: preview + estimate ===== -->
        <aside class="fb-side">
          <div v-if="adset.mode === 'new' && current !== 'campaign'" class="fb-card fb-estimate">
            <div class="fb-side-h"><strong><i class="bx bx-group"></i> {{ t('side.audience_size') }}</strong><small>{{ t('side.meta_estimate') }}</small></div>
            <div v-if="estimate.loading" class="fb-inline-load"><span class="spinner-border spinner-border-sm"></span> {{ t('side.asking') }}</div>
            <template v-else-if="estimate.data">
              <div class="fb-est-num">{{ compact(estimate.data.audience_min) }} – {{ compact(estimate.data.audience_max) }}</div>
              <p class="fb-muted">{{ t('side.estimate_desc') }}</p>
            </template>
            <p v-else class="fb-muted">{{ estimate.error || t('side.estimate_empty') }}</p>
          </div>

          <div class="fb-card fb-preview">
            <div class="fb-side-h"><strong><i class="bx bx-show"></i> {{ t('side.preview') }}</strong><small>{{ creative.mode === 'existing' ? t('side.rendered') : t('side.approximate') }}</small></div>
            <template v-if="creative.mode === 'existing'">
              <div v-if="preview.loading" class="fb-inline-load"><span class="spinner-border spinner-border-sm"></span> {{ t('side.loading_preview') }}</div>
              <div v-else-if="preview.html" class="fb-meta-preview" v-html="preview.html"></div>
              <p v-else class="fb-muted">{{ preview.error || t('side.choose_creative') }}</p>
            </template>
            <div v-else class="fb-mock">
              <div class="fb-mock-h">
                <span class="fb-mock-av">
                  <img v-if="previewPage && previewPage.picture && !previewAvatarBroken" :src="previewPage.picture" alt="" @error="previewAvatarBroken = true">
                  <span v-else-if="previewPage" class="fb-mock-initial">{{ previewPage.name.trim().charAt(0).toUpperCase() }}</span>
                  <i v-else class="bx bxl-facebook"></i>
                </span>
                <div v-if="isInstagram && previewInstagram"><strong dir="ltr">{{ previewInstagram.username ? '@' + previewInstagram.username : previewInstagram.id }}</strong><small>{{ t('side.sponsored') }} · <i class="bx bxl-instagram"></i></small></div>
                <div v-else><strong>{{ previewPage ? previewPage.name : t('side.your_page') }}</strong><small>{{ t('side.sponsored') }} · <i class="bx bx-world"></i></small></div>
              </div>
              <p class="fb-mock-text">{{ creative.primary_text || t('side.primary_placeholder') }}</p>
              <div v-if="creative.format === 'CAROUSEL'" class="fb-mock-carousel">
                <div v-for="(card, ci) in creative.cards.slice(0, 3)" :key="'p' + ci" class="fb-mock-card">
                  <img :src="card.asset.url" alt="">
                  <strong>{{ card.headline || t('side.card_headline') }}</strong>
                </div>
                <div v-if="!creative.cards.length" class="fb-mock-media"><i class="bx bx-carousel"></i></div>
              </div>
              <div v-else class="fb-mock-media">
                <img v-if="creative.media[0] && (creative.media[0].type === 'image' || creative.media[0].thumbnail_url)" :src="creative.media[0].type === 'image' ? creative.media[0].url : (creative.thumbnail ? creative.thumbnail.url : creative.media[0].thumbnail_url)" alt="">
                <i v-else class="bx" :class="creative.format === 'VIDEO' ? 'bx-video' : 'bx-image'"></i>
                <span v-if="creative.format === 'VIDEO' && creative.media[0]" class="fb-mock-play"><i class="bx bx-play"></i></span>
              </div>
              <div v-if="creative.format !== 'CAROUSEL'" class="fb-mock-foot">
                <div><small>{{ linkHost }}</small><strong>{{ creative.headline || t('side.headline_placeholder') }}</strong><small v-if="creative.description">{{ creative.description }}</small></div>
                <span v-if="creative.cta && creative.cta !== 'NO_BUTTON'" class="fb-mock-cta">{{ ctaLabel(creative.cta) }}</span>
              </div>
            </div>
          </div>
        </aside>
      </div>

      <media-picker ref="picker" :urls="galleryUrls" :multiple="pickerTarget === 'card'" :type-lock="pickerTypeLock" :platforms="['facebook', 'instagram']" @select="onPicked" />
    </template>
  </div>
</template>

<script setup>
// Facebook / Instagram campaign builder (Meta Marketing API through
// App\Services\AdServices\FacebookCampaignBuilder). Five steps - campaign,
// ad set, audience, creative, review - each "use existing" or "create
// new". Drafts are kept in localStorage per ad account; every submission
// carries an idempotency key so retries and double clicks never create
// duplicates on Meta.
import { ref, reactive, computed, watch, onMounted, nextTick, provide } from 'vue';
import SearchPicker from './SearchPicker.vue';
import IdentityCards from './IdentityCards.vue';
import BudgetSummary from './BudgetSummary.vue';
import MediaPicker from '../../media/MediaPicker.vue';
import { createTranslator } from './i18n';
import { uploadToGallery, errorMessage, precheck } from '../../media/mediaShared';

const props = defineProps({
  urls: { type: Object, required: true },
  galleryUrls: { type: Object, required: true },
  accountId: { type: [Number, String], required: true },
  // __('ads_builder') for the current locale (lang/{en,ar}/ads_builder.php).
  messages: { type: Object, default: () => ({}) },
  locale: { type: String, default: 'en' },
  // 'instagram' = the Instagram ads page (same builder, Instagram-only rules).
  channel: { type: String, default: 'facebook' },
});
const isInstagram = props.channel === 'instagram';

const i18n = createTranslator(props.messages, props.locale);
const { t, tc } = i18n;
provide('fbI18n', i18n);

const today = new Date().toISOString().slice(0, 10);
const nowLocal = new Date(Date.now() - new Date().getTimezoneOffset() * 60000).toISOString().slice(0, 16);
const ages = Array.from({ length: 48 }, (_, i) => i + 18);
const radiusOptions = [5, 10, 17, 25, 40, 80];
const platformOptions = [
  { key: 'facebook', icon: 'bxl-facebook-circle' },
  { key: 'instagram', icon: 'bxl-instagram' },
  { key: 'messenger', icon: 'bxl-messenger' },
  { key: 'audience_network', icon: 'bx-grid-alt' },
];
// No 'video_feeds': Meta removed Facebook video feeds (Reels replaces it).
const fbPositions = ['feed', 'story', 'facebook_reels', 'marketplace', 'search'].map((key) => ({ key }));
// No 'explore': Meta removed the Explore placement (Explore home remains).
const igPositions = (isInstagram ? ['stream', 'story', 'reels', 'explore_home', 'ig_search'] : ['stream', 'story', 'reels', 'explore_home']).map((key) => ({ key }));
// Work & education (B2B) - Graph search types; results come only from Meta.
const workTypes = ['adworkposition', 'adworkemployer', 'adeducationschool', 'adeducationmajor'];
const WORK_KEYS = { adworkposition: 'work_positions', adworkemployer: 'work_employers', adeducationschool: 'education_schools', adeducationmajor: 'education_majors' };
const specialCategories = ['HOUSING', 'EMPLOYMENT', 'CREDIT', 'FINANCIAL_PRODUCTS_SERVICES', 'ISSUES_ELECTIONS_POLITICS'];

// ---------------------------------------------------------------- state
const boot = ref(null);
const bootError = ref('');
const step = ref(0);
const maxReached = ref(0);
const goal = ref(null);
const destination = ref(null);
const campaign = reactive({ mode: 'new', id: null, name: '', special_ad_category: null, campaign_budget: false, budget_type: 'daily', budget: null, bid_strategy: 'LOWEST_COST_WITHOUT_CAP' });
const adset = reactive({ mode: 'new', id: null, name: '', optimization_goal: null, budget_type: 'daily', budget: null, bid_strategy: 'LOWEST_COST_WITHOUT_CAP', bid_amount: null, start_time: '', end_time: '', page_id: null, pixel_id: null, custom_event_type: 'PURCHASE', application_id: null, object_store_url: null });
const targeting = reactive({ locations: [], excluded_locations: [], age_min: 18, age_max: 65, genders: [], locales: [], interests: [], narrow: [], saved_audience_id: null, advantage_audience: true });
const placements = reactive({ mode: isInstagram ? 'manual' : 'advantage', publisher_platforms: isInstagram ? ['instagram'] : ['facebook', 'instagram'], facebook_positions: [], instagram_positions: [], device_platforms: ['mobile', 'desktop'] });
const workType = ref('adworkposition');
const creative = reactive({ mode: 'new', id: null, name: '', format: 'IMAGE', page_id: null, instagram_user_id: null, primary_text: '', headline: '', description: '', link: '', cta: 'LEARN_MORE', lead_form_id: null, media: [], thumbnail: null, cards: [] });
const ad = reactive({ name: '', status: 'PAUSED' });
const ui = reactive({ advanced: false, includeAudiences: [], excludeAudiences: [] });

const lists = reactive({
  campaigns: [], adsets: [], creatives: [], pixels: [], apps: [], instagram: [], leadForms: [], audiences: { custom: [], saved: [] },
  loading: {}, errors: {},
});
const campaignQuery = ref('');
const creativeQuery = ref('');
const fieldErrors = reactive({});
const stepErrors = reactive({});
const estimate = reactive({ loading: false, data: null, error: '' });
const preview = reactive({ loading: false, html: '', error: '' });
const submitting = ref(false);
const result = ref(null);
const idempotencyKey = ref(newKey());
const savedAt = ref('');
const draftOffer = ref(null);
const picker = ref(null);
const pickerTarget = ref('media');
const uploading = ref(0);
const mediaError = ref('');

// ---------------------------------------------------------------- derived
const goals = computed(() => (boot.value ? boot.value.goals : {}));
const destRules = computed(() => (goal.value && destination.value ? goals.value[goal.value].destinations[destination.value] : null));
const selectedCampaign = computed(() => lists.campaigns.find((c) => c.id === campaign.id) || null);
const campaignHasBudget = computed(() => (campaign.mode === 'new' ? campaign.campaign_budget : !!(selectedCampaign.value && selectedCampaign.value.campaign_budget)));
const specialCategory = computed(() => (campaign.mode === 'new' ? !!campaign.special_ad_category : !!(selectedCampaign.value && selectedCampaign.value.special_ad_categories.length)));
const needsPage = computed(() => !!destRules.value && (destRules.value.promoted_object || []).includes('page_id'));
const selectedPixel = computed(() => lists.pixels.find((p) => p.id === adset.pixel_id) || null);
const selectedApp = computed(() => lists.apps.find((a) => a.id === adset.application_id) || null);
const selectedPage = computed(() => (boot.value ? boot.value.pages.find((p) => p.id === creative.page_id) : null) || null);
// Page shown in the ad preview: the creative's Page, else the ad set's,
// else the seller's first Page - never a placeholder when Pages exist.
const previewPage = computed(() => {
  const pages = boot.value ? boot.value.pages : [];
  return pages.find((p) => p.id === creative.page_id) || pages.find((p) => p.id === adset.page_id) || pages[0] || null;
});
const previewAvatarBroken = ref(false);
watch(() => previewPage.value && previewPage.value.picture, () => { previewAvatarBroken.value = false; });
const usableAudiences = computed(() => lists.audiences.custom.filter((a) => !(specialCategory.value && a.kind === 'lookalike')));
const filteredCampaigns = computed(() => {
  const q = campaignQuery.value.trim().toLowerCase();
  return lists.campaigns.filter((c) => !q || c.name.toLowerCase().includes(q));
});
const filteredCreatives = computed(() => {
  const q = creativeQuery.value.trim().toLowerCase();
  return lists.creatives.filter((c) => !q || [c.name, c.headline, c.text].join(' ').toLowerCase().includes(q));
});
const linkHost = computed(() => {
  try { return new URL(creative.link).hostname.replace(/^www\./, '').toUpperCase(); } catch (e) { return t('side.website_placeholder'); }
});
// Website field: every destination except messaging, Instant forms and
// app installs (those links are set by Meta / the app store URL).
// Required where the goal is a website; optional for awareness and
// engagement, where the server falls back to the Page.
const showLink = computed(() => !!destRules.value && !destRules.value.message_app && !destRules.value.needs_lead_form && !destRules.value.needs_app);
const linkRequired = computed(() => showLink.value && !!destRules.value.needs_link);
const validLink = computed(() => /^https?:\/\/\S+\.\S+/.test(creative.link || ''));
const pageCards = computed(() => (boot.value ? boot.value.pages : []).map((p) => ({ id: p.id, name: p.name, picture: p.picture, sub: p.username ? '@' + p.username : t('common.facebook_page') })));
// Instagram page: pre-pick the account linked to the chosen Page, else the
// first one - the ad needs one, so never leave it empty when there's a choice.
function pickInstagram() {
  if (!isInstagram || !lists.instagram.length) return;
  if (lists.instagram.some((a) => a.id === creative.instagram_user_id)) return;
  const linked = lists.instagram.find((a) => a.page_id && a.page_id === creative.page_id);
  creative.instagram_user_id = (linked || lists.instagram[0]).id;
}
const previewInstagram = computed(() => lists.instagram.find((a) => a.id === creative.instagram_user_id) || null);
// Work items live in targeting.interests (same "any of" group); this picker shows the current type's.
const workItems = computed(() => targeting.interests.filter((i) => i.type === WORK_KEYS[workType.value]));
const isWork = (i) => Object.values(WORK_KEYS).includes(i.type);
// The detailed-targeting picker shows interests/behaviors/demographics; work items have their own picker.
const detailedItems = computed(() => targeting.interests.filter((i) => !isWork(i)));
function setDetailedItems(items) {
  targeting.interests = [...items, ...targeting.interests.filter(isWork)];
}
function setWorkItems(items) {
  const key = WORK_KEYS[workType.value];
  targeting.interests = [...targeting.interests.filter((i) => i.type !== key), ...items];
}
const instagramCards = computed(() => lists.instagram.map((a) => ({ id: a.id, name: a.username ? '@' + a.username : a.id, picture: a.picture, sub: t('creative.instagram_card_sub') })));
// What the budget comes to with VAT (shown only - Meta gets the budget as
// entered). A daily budget with an end date is multiplied by the days.
const budgetSummary = computed(() => {
  const vatRate = boot.value ? Number(boot.value.vat_rate || 0) : 0;
  let amount = null;
  let type = 'daily';
  if (campaign.mode === 'new' && campaign.campaign_budget) {
    amount = campaign.budget; type = campaign.budget_type;
  } else if (adset.mode === 'new' && !campaignHasBudget.value) {
    amount = adset.budget; type = adset.budget_type;
  }
  if (!(amount > 0)) return null;
  let days = null;
  if (type === 'daily' && adset.mode === 'new' && adset.end_time) {
    const start = adset.start_time ? new Date(adset.start_time) : new Date();
    days = Math.max(1, Math.ceil((new Date(adset.end_time) - start) / 86400000));
  }
  const budget = type === 'daily' && days ? amount * days : amount;
  const vat = Math.round(budget * vatRate) / 100;
  return { type, amount, days, budget, vatRate, vat, total: Math.round((budget + vat) * 100) / 100, perDay: type === 'daily' && !days };
});
const pickerTypeLock = computed(() => (pickerTarget.value === 'media' ? (creative.format === 'VIDEO' ? 'video' : 'image') : 'image'));

const steps = computed(() => [
  { key: 'campaign', label: t('steps.campaign'), summary: campaign.mode === 'existing' ? (selectedCampaign.value ? selectedCampaign.value.name : t('steps.choose_one')) : (goal.value ? goals.value[goal.value].label : t('steps.pick_goal')) },
  { key: 'adset', label: t('steps.adset'), summary: adset.mode === 'existing' ? t('steps.existing') : (adset.budget && !campaignHasBudget.value ? (adset.budget_type === 'daily' ? t('steps.per_day', { amount: money(adset.budget) }) : money(adset.budget)) : t('steps.budget_schedule')) },
  { key: 'audience', label: t('steps.audience'), summary: adset.mode === 'existing' ? t('steps.from_adset') : (targeting.locations.length ? tc('steps.locations', targeting.locations.length) : t('steps.who_sees')), skipped: adset.mode === 'existing' },
  { key: 'creative', label: t('steps.creative'), summary: creative.mode === 'existing' ? t('steps.existing') : t('steps.' + ({ CAROUSEL: 'carousel', VIDEO: 'video' }[creative.format] || 'image')) },
  { key: 'review', label: t('steps.review'), summary: ad.status === 'ACTIVE' ? t('steps.publish') : t('steps.save_paused') },
]);
const current = computed(() => steps.value[step.value].key);

const reviewRows = computed(() => {
  if (!boot.value) return [];
  const g = goal.value ? goals.value[goal.value] : null;
  const locs = targeting.locations.map(locationLabel).join(', ');
  const fromAdset = t('review.from_adset');
  const existing = (name) => t('review.existing', { name: name || '-' });
  const fresh = (name) => t('review.new', { name: name || t('review.no_name') });
  const amount = (value, type) => t(type === 'daily' ? 'review.per_day' : 'review.lifetime', { amount: money(value) });
  const sum = budgetSummary.value;
  const row = (key, step, icon, value) => ({ key, step, icon, label: t('review.rows.' + key, key === 'total_vat' && sum ? { rate: sum.vatRate } : {}), value });
  return [
    row('campaign', 0, 'bx-flag', campaign.mode === 'existing' ? existing(selectedCampaign.value && selectedCampaign.value.name) : fresh(campaign.name)),
    row('goal', 0, 'bx-bullseye', g ? `${g.label} · ${destRules.value ? destRules.value.label : ''}` : '-'),
    row('adset', 1, 'bx-target-lock', adset.mode === 'existing' ? existing((lists.adsets.find((s) => s.id === adset.id) || {}).name) : `${fresh(adset.name)} · ${destRules.value ? optimizationLabel(adset.optimization_goal) : ''}`),
    row('budget', 1, 'bx-wallet', campaignHasBudget.value ? (campaign.mode === 'new' ? t('review.campaign_level', { budget: amount(campaign.budget, campaign.budget_type) }) : t('review.campaign_budget')) : (adset.mode === 'existing' ? fromAdset : amount(adset.budget, adset.budget_type))),
    row('schedule', 1, 'bx-calendar', adset.mode === 'existing' ? fromAdset : `${adset.start_time ? fmtDate(adset.start_time) : t('review.starts_now')} → ${adset.end_time ? fmtDate(adset.end_time) : t('review.no_end')}`),
    row('locations', 2, 'bx-map', adset.mode === 'existing' ? fromAdset : (targeting.saved_audience_id ? t('review.saved_audience') : (locs || '-'))),
    row('audience', 2, 'bx-group', adset.mode === 'existing' ? fromAdset : audienceSummary()),
    row('placements', 2, 'bx-layout', adset.mode === 'existing' ? fromAdset : isInstagram
      ? (placements.instagram_positions.length ? t('audience.platforms.instagram') + ': ' + placements.instagram_positions.map((p) => t('audience.positions.' + p)).join(', ') : t('audience.ig_all'))
      : (placements.mode === 'advantage' ? t('audience.advantage_placements') : placements.publisher_platforms.map((p) => t('audience.platforms.' + p)).join(', '))),
    row('tracking', 1, 'bx-line-chart', adset.mode === 'existing' ? fromAdset : destRules.value && destRules.value.needs_pixel ? t('review.pixel', { name: selectedPixel.value ? selectedPixel.value.name : '-', event: (boot.value.conversion_events || {})[adset.custom_event_type] || adset.custom_event_type }) : (destRules.value && destRules.value.needs_app ? t('review.app', { name: selectedApp.value ? selectedApp.value.name : '-' }) : t('review.not_needed'))),
    ...(sum ? [row('total_vat', 1, 'bx-receipt', sum.days ? tc('review.total_for_days', sum.days, { amount: money(sum.total) }) : (sum.perDay ? t('review.total_per_day', { amount: money(sum.total) }) : money(sum.total)))] : []),
    row('creative', 3, 'bx-image', creative.mode === 'existing' ? existing((lists.creatives.find((c) => c.id === creative.id) || {}).name) : `${t('review.formats.' + creative.format)} · ${ctaLabel(creative.cta)}${creative.link ? ' · ' + creative.link : ''}`),
  ];
});

const blockers = computed(() => validate().flatMap((v) => v.errors.map((text) => ({ step: v.step, text }))));

// ---------------------------------------------------------------- helpers
function newKey() {
  return (window.crypto && window.crypto.randomUUID) ? window.crypto.randomUUID() : Date.now().toString(36) + Math.random().toString(36).slice(2) + Math.random().toString(36).slice(2);
}
function money(v) {
  if (v === null || v === undefined || v === '') return '-';
  try { return new Intl.NumberFormat(i18n.intl, { style: 'currency', currency: boot.value ? boot.value.account.currency : 'USD', maximumFractionDigits: 2 }).format(v); } catch (e) { return v; }
}
function compact(n) {
  return n === null || n === undefined ? '-' : new Intl.NumberFormat(i18n.intl, { notation: 'compact', maximumFractionDigits: 1 }).format(n);
}
function fmtDate(v) {
  return new Date(v).toLocaleString(i18n.intl, { dateStyle: 'medium', timeStyle: 'short' });
}
const humanize = (v) => (v || '').replace(/_/g, ' ').toLowerCase().replace(/^./, (x) => x.toUpperCase());
// Known codes from the lang file; anything new from Meta is shown readable.
const known = (key, code, fallback) => { const v = t(key + '.' + code); return v === key + '.' + code ? fallback : v; };
function ctaLabel(c) { return known('cta', c, humanize(c)); }
function optimizationLabel(o) { return o ? known('optimization', o, o) : '-'; }
function statusLabel(s) { return known('status', s, humanize(s)); }
function typeLabel(type) { return known('audience.types', type, type); }
function locationType(type) { return known('audience.location_types', type, humanize(type)); }
function goalIcon(g) { return (goals.value[g] || {}).icon || 'bx-flag'; }
function objectiveLabel(c) { return c.goals.length ? c.goals.map((g) => goals.value[g].label).join(' / ') : t('campaign.not_supported', { objective: c.objective }); }
function destIcon(key) { return ({ WEBSITE: 'bx-globe', ON_AD: 'bx-id-card', MESSENGER: 'bxl-messenger', WHATSAPP: 'bxl-whatsapp', INSTAGRAM_DIRECT: 'bxl-instagram', APP: 'bx-mobile-alt', ON_POST: 'bx-like', NONE: 'bx-broadcast' })[key] || 'bx-right-arrow-alt'; }
function locationLabel(l) { return l.type === 'city' && l.radius ? `${l.name} ${t('audience.radius', { km: l.radius })}` : (l.type === 'country' ? l.name : `${l.name}${l.country_code ? ', ' + l.country_code : ''}`); }
function stepTitle(s) { return known('step_titles', s, s); }
function audienceSummary() {
  if (targeting.saved_audience_id) return t('review.saved_audience');
  const parts = [`${targeting.age_min}-${targeting.age_max === 65 ? '65+' : targeting.age_max}`, t(targeting.genders[0] === 1 ? 'summary.men' : targeting.genders[0] === 2 ? 'summary.women' : 'summary.all_genders')];
  parts.push(targeting.locales.length ? targeting.locales.map((l) => l.name).join(', ') : t('summary.all_languages'));
  if (targeting.interests.length) parts.push(t('summary.detailed', { count: targeting.interests.length }));
  if (ui.includeAudiences.length) parts.push(t('summary.custom', { count: ui.includeAudiences.length }));
  if (targeting.advantage_audience && !specialCategory.value) parts.push(t('summary.advantage'));
  return parts.join(' · ');
}

async function api(url, params = {}, method = 'get') {
  params = { ...params, channel: props.channel };
  try {
    const { data } = method === 'get' ? await window.axios.get(url, { params }) : await window.axios.post(url, params);
    if (!data.success) throw new Error(data.message || t('client.meta_error'));
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

// ---------------------------------------------------------------- choices
function setMode(part, mode) {
  ({ campaign, adset, creative })[part].mode = mode;
  if (part === 'campaign' && mode === 'existing' && !lists.campaigns.length) load('campaigns', props.urls.campaigns);
  if (part === 'campaign' && mode === 'new') { campaign.id = null; adset.mode = 'new'; }
  if (part === 'creative' && mode === 'existing' && !lists.creatives.length) load('creatives', props.urls.creatives);
}
function setGoal(key) {
  goal.value = key;
  const dests = Object.keys(goals.value[key].destinations);
  if (!dests.includes(destination.value)) setDestination(dests[0]);
  if (!campaign.name) campaign.name = '';
}
function setDestination(key) {
  destination.value = key;
  const rules = goals.value[goal.value].destinations[key];
  adset.optimization_goal = Object.keys(rules.optimization_goals)[0];
  if (rules.default_event) adset.custom_event_type = rules.default_event;
  if (!rules.ctas.includes(creative.cta)) creative.cta = rules.ctas[0];
  if ((rules.message_app || rules.needs_lead_form) && creative.format === 'CAROUSEL') creative.format = 'IMAGE';
  if (rules.needs_pixel && !lists.pixels.length) load('pixels', props.urls.pixels);
  if (rules.needs_app && !lists.apps.length) load('apps', props.urls.apps);
}
function chooseCampaign(c) {
  campaign.id = c.id;
  if (c.goals.length && !c.goals.includes(goal.value)) setGoal(c.goals[0]);
  adset.id = null;
  lists.adsets = [];
}
function chooseCreative(c) {
  creative.id = c.id;
  loadPreview();
}
function setFormat(f) {
  creative.format = f;
  creative.media = [];
  creative.thumbnail = null;
}
function setRadius(city, value) {
  const idx = targeting.locations.findIndex((l) => l.key === city.key);
  targeting.locations.splice(idx, 1, { ...city, radius: value ? Number(value) : undefined, distance_unit: 'kilometer' });
}

const searchLocations = (q) => api(props.urls.locations, { q });
const searchLocales = (q) => api(props.urls.locales, { q });

// One-click languages: looked up in Meta's own locale list (no hard-coded
// keys) and the exact-name match added.
const quickLocales = [
  { id: 'arabic', query: 'Arabic', match: ['arabic'] },
  { id: 'english', query: 'English', match: ['english (all)', 'english'] },
  { id: 'urdu', query: 'Urdu', match: ['urdu'] },
  { id: 'hindi', query: 'Hindi', match: ['hindi'] },
  { id: 'french', query: 'French', match: ['french (all)', 'french'] },
];
const quickLoading = ref('');
const quickError = ref('');
const hasLocale = (l) => targeting.locales.some((x) => l.match.includes(String(x.name).toLowerCase()));
async function addQuickLocale(l) {
  quickError.value = '';
  quickLoading.value = l.query;
  try {
    const items = (await searchLocales(l.query)) || [];
    const found = l.match.map((m) => items.find((i) => String(i.name).toLowerCase() === m)).find(Boolean);
    if (!found) {
      quickError.value = t('audience.quick_missing', { name: t('audience.quick.' + l.id) });
    } else if (!targeting.locales.some((x) => x.key === found.key)) {
      targeting.locales.push(found);
    }
  } catch (e) {
    quickError.value = (e && e.message) || t('audience.quick_failed');
  } finally {
    quickLoading.value = '';
  }
}
const searchTargeting = (q) => api(props.urls.targeting, { q });
const searchWork = (q) => api(props.urls.work, { q, type: workType.value });

// ---------------------------------------------------------------- media
function openGallery(target) {
  pickerTarget.value = target;
  mediaError.value = '';
  nextTick(() => picker.value && picker.value.open());
}
function onPicked(assets) {
  if (!assets || !assets.length) return;
  placeAssets(assets);
}
function placeAssets(assets) {
  if (pickerTarget.value === 'thumbnail') {
    creative.thumbnail = assets[0];
  } else if (pickerTarget.value === 'card') {
    assets.filter((a) => a.type === 'image').slice(0, 10 - creative.cards.length).forEach((a) => creative.cards.push({ asset: a, headline: '', description: '', link: '' }));
  } else {
    creative.media = [assets[0]];
  }
}
async function uploadNew(event, target) {
  const file = event.target.files[0];
  event.target.value = '';
  if (!file) return;
  const problem = precheck(file);
  if (problem) { mediaError.value = problem; return; }
  pickerTarget.value = target;
  mediaError.value = '';
  uploading.value = 1;
  try {
    const asset = await uploadToGallery(props.galleryUrls.store, file, (p) => { uploading.value = p || 1; });
    placeAssets([asset]);
  } catch (e) {
    mediaError.value = errorMessage(e, t('client.upload_failed'));
  } finally {
    uploading.value = 0;
  }
}

// ---------------------------------------------------------------- validation
function validate() {
  const out = [];
  const add = (stepIndex, errors) => { if (errors.length) out.push({ step: stepIndex, errors }); };
  const c = [];
  if (!goal.value) c.push(t('client.goal'));
  if (!destination.value) c.push(t('client.destination'));
  if (campaign.mode === 'existing' && !campaign.id) c.push(t('client.campaign_existing'));
  if (campaign.mode === 'new' && !campaign.name) c.push(t('client.campaign_name'));
  if (campaign.mode === 'new' && campaign.campaign_budget && !(campaign.budget > 0)) c.push(t('client.campaign_budget'));
  add(0, c);

  const s = [];
  if (adset.mode === 'existing' && !adset.id) s.push(t('client.adset_existing'));
  if (adset.mode === 'new') {
    if (!adset.name) s.push(t('client.adset_name'));
    if (!campaignHasBudget.value && !(adset.budget > 0)) s.push(t('client.budget'));
    if (!campaignHasBudget.value && adset.budget_type === 'lifetime' && !adset.end_time) s.push(t('client.lifetime_end'));
    if (adset.end_time && adset.start_time && adset.end_time <= adset.start_time) s.push(t('client.end_after_start'));
    if (!campaignHasBudget.value && adset.bid_strategy !== 'LOWEST_COST_WITHOUT_CAP' && !(adset.bid_amount > 0)) s.push(t('client.bid_amount'));
    if (needsPage.value && !adset.page_id) s.push(t('client.page'));
    if (destRules.value && destRules.value.needs_pixel && !adset.pixel_id) s.push(t('client.pixel'));
    if (destRules.value && destRules.value.needs_app && (!adset.application_id || !adset.object_store_url)) s.push(t('client.app'));
  }
  add(1, s);

  const a = [];
  if (adset.mode === 'new' && !targeting.saved_audience_id && !targeting.locations.length) a.push(t('client.location'));
  if (adset.mode === 'new' && placements.mode === 'manual' && !placements.publisher_platforms.length) a.push(t('client.placement'));
  add(2, a);

  const cr = [];
  if (creative.mode === 'existing' && !creative.id) cr.push(t('client.creative_existing'));
  if (creative.mode === 'new') {
    if (!creative.page_id) cr.push(t('client.creative_page'));
    if (isInstagram && !creative.instagram_user_id) cr.push(t('client.instagram'));
    if (!creative.primary_text.trim()) cr.push(t('client.primary'));
    if (creative.format === 'CAROUSEL' && creative.cards.length < 2) cr.push(t('client.cards'));
    if (creative.format !== 'CAROUSEL' && !creative.media.length) cr.push(t(creative.format === 'VIDEO' ? 'client.video' : 'client.image'));
    if (creative.format === 'VIDEO' && creative.media[0] && !creative.media[0].thumbnail_url && !creative.thumbnail) cr.push(t('client.thumbnail'));
    if (linkRequired.value && !validLink.value) cr.push(t('client.website'));
    else if (showLink.value && creative.link && !validLink.value) cr.push(t('client.website_https'));
    if (destRules.value && destRules.value.needs_lead_form && !creative.lead_form_id) cr.push(t('client.lead_form'));
  }
  add(3, cr);

  if (!ad.name) add(4, [t('client.ad_name')]);
  return out;
}

function showStepErrors(index) {
  const key = steps.value[index].key;
  const found = validate().find((v) => v.step === index);
  fieldErrors[key] = found ? found.errors.join(' ') : '';
  stepErrors[key] = !!found;
  return !found;
}

// ---------------------------------------------------------------- navigation
function goTo(i) {
  if (i > maxReached.value) return;
  step.value = i;
  window.scrollTo({ top: 0, behavior: 'smooth' });
}
function next() {
  if (!showStepErrors(step.value)) return;
  let target = step.value + 1;
  if (steps.value[target] && steps.value[target].skipped) target += 1;
  step.value = Math.min(target, steps.value.length - 1);
  maxReached.value = Math.max(maxReached.value, step.value);
  window.scrollTo({ top: 0, behavior: 'smooth' });
}
function back() {
  let target = step.value - 1;
  if (steps.value[target] && steps.value[target].skipped) target -= 1;
  step.value = Math.max(0, target);
}

// ---------------------------------------------------------------- estimate & preview
let estimateTimer = null;
function payload() {
  return {
    idempotency_key: idempotencyKey.value,
    channel: props.channel,
    goal: goal.value,
    destination: destination.value,
    campaign: campaign.mode === 'existing' ? { mode: 'existing', id: campaign.id } : { ...campaign, id: null },
    adset: adset.mode === 'existing' ? { mode: 'existing', id: adset.id } : {
      ...adset,
      id: null,
      page_id: adset.page_id || creative.page_id,
      start_time: adset.start_time || null,
      end_time: adset.end_time || null,
      targeting: {
        ...targeting,
        locations: targeting.locations.map((l) => ({ key: l.key, type: l.type, country_code: l.country_code, radius: l.radius, distance_unit: l.radius ? 'kilometer' : undefined, name: l.name })),
        excluded_locations: targeting.excluded_locations.map((l) => ({ key: l.key, type: l.type, country_code: l.country_code, name: l.name })),
        custom_audiences: ui.includeAudiences.map((a) => a.id),
        excluded_custom_audiences: ui.excludeAudiences.map((a) => a.id),
        narrow: targeting.narrow.filter((g) => g.length),
      },
      placements: { ...placements },
    },
    creative: creative.mode === 'existing' ? { mode: 'existing', id: creative.id } : {
      mode: 'new',
      name: creative.name || `${ad.name || t('creative.default_name')} - ${today}`,
      format: creative.format,
      page_id: creative.page_id,
      instagram_user_id: creative.instagram_user_id,
      primary_text: creative.primary_text,
      headline: creative.headline || null,
      description: creative.description || null,
      link: creative.link || null,
      cta: creative.cta,
      lead_form_id: creative.lead_form_id,
      // Only the format's own media: a single image / video never sends
      // carousel cards (and the reverse), so stale picks can't fail validation.
      media: creative.format !== 'CAROUSEL' ? creative.media.map((m) => ({ asset_id: m.id })) : [],
      thumbnail_asset_id: creative.format === 'VIDEO' && creative.thumbnail ? creative.thumbnail.id : null,
      cards: creative.format === 'CAROUSEL' ? creative.cards.map((c) => ({ asset_id: c.asset.id, headline: c.headline || null, description: c.description || null, link: c.link || null })) : [],
    },
    ad: { ...ad },
  };
}
function refreshEstimate() {
  clearTimeout(estimateTimer);
  if (adset.mode !== 'new' || !goal.value || (!targeting.locations.length && !targeting.saved_audience_id)) {
    estimate.data = null;
    estimate.error = '';
    return;
  }
  estimateTimer = setTimeout(async () => {
    estimate.loading = true;
    estimate.error = '';
    try {
      estimate.data = await api(props.urls.estimate, payload(), 'post');
      if (!estimate.data) estimate.error = t('side.no_estimate');
    } catch (e) {
      estimate.data = null;
      estimate.error = e.message;
    } finally {
      estimate.loading = false;
    }
  }, 700);
}
async function loadPreview() {
  if (!creative.id) return;
  preview.loading = true;
  preview.error = '';
  try {
    preview.html = (await api(props.urls.creativePreview.replace('CREATIVE_ID', creative.id))).html || '';
    if (!preview.html) preview.error = t('side.no_preview');
  } catch (e) {
    preview.html = '';
    preview.error = e.message;
  } finally {
    preview.loading = false;
  }
}

// ---------------------------------------------------------------- submit
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
      // Laravel validation: send each message to its step.
      const messages = Object.entries(data.errors);
      const stepFor = (field) => field.startsWith('campaign') || ['goal', 'destination'].includes(field) ? 0 : field.startsWith('adset.targeting') || field.startsWith('adset.placements') ? 2 : field.startsWith('adset') ? 1 : field.startsWith('creative') ? 3 : 4;
      messages.forEach(([field, msgs]) => {
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
  creative.mode = 'new';
  creative.media = [];
  creative.cards = [];
  creative.thumbnail = null;
  ad.name = '';
  // Keep the campaign and ad set just used: the next ad goes into them.
  step.value = 3;
}

// ---------------------------------------------------------------- drafts
const draftKey = computed(() => `fb-ad-builder:${props.channel}:${props.accountId}`);
function snapshot() {
  return JSON.parse(JSON.stringify({ step: step.value, maxReached: maxReached.value, goal: goal.value, destination: destination.value, campaign, adset, targeting, placements, creative, ad, ui, key: idempotencyKey.value }));
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
  goal.value = d.goal;
  destination.value = d.destination;
  Object.assign(campaign, d.campaign);
  Object.assign(adset, d.adset);
  Object.assign(targeting, d.targeting);
  Object.assign(placements, d.placements);
  placements.instagram_positions = (placements.instagram_positions || []).filter((p) => p !== 'explore');
  placements.facebook_positions = (placements.facebook_positions || []).filter((p) => !['video_feeds', 'instant_article'].includes(p));
  Object.assign(creative, d.creative);
  Object.assign(ad, d.ad);
  Object.assign(ui, d.ui);
  idempotencyKey.value = d.key || newKey();
  step.value = d.step || 0;
  maxReached.value = d.maxReached || 0;
  if (campaign.mode === 'existing') load('campaigns', props.urls.campaigns);
  if (creative.mode === 'existing') { load('creatives', props.urls.creatives); loadPreview(); }
  if (destRules.value && destRules.value.needs_pixel) load('pixels', props.urls.pixels);
  if (destRules.value && destRules.value.needs_app) load('apps', props.urls.apps);
}
function discardDraft() {
  clearDraft();
  draftOffer.value = null;
}

// ---------------------------------------------------------------- watchers
watch(() => [campaign.mode, campaign.id], () => {
  if (campaign.mode === 'existing' && campaign.id) load('adsets', props.urls.adsets.replace('CAMPAIGN_ID', campaign.id));
});
watch(() => lists.instagram, pickInstagram);
watch(() => creative.page_id, (pageId) => {
  lists.leadForms = [];
  creative.lead_form_id = null;
  if (pageId && destRules.value && destRules.value.needs_lead_form) load('leadForms', props.urls.leadForms.replace('PAGE_ID', pageId));
  if (!adset.page_id) adset.page_id = pageId;
});
watch(() => destRules.value && destRules.value.needs_lead_form, (needs) => {
  if (needs && creative.page_id && !lists.leadForms.length) load('leadForms', props.urls.leadForms.replace('PAGE_ID', creative.page_id));
});
watch(() => [current.value, ui.advanced], () => {
  if (current.value === 'audience' && ui.advanced && !lists.loading.audiences && !lists.audiences.custom.length) load('audiences', props.urls.audiences);
  if (current.value === 'creative' && !lists.instagram.length && !lists.loading.instagram) load('instagram', props.urls.instagram);
});
watch(() => JSON.stringify([goal.value, destination.value, adset.optimization_goal, adset.mode, targeting, ui.includeAudiences, ui.excludeAudiences, placements, campaign.special_ad_category]), refreshEstimate);
watch(() => JSON.stringify(snapshot()), () => { if (boot.value && !(result.value && result.value.success)) saveDraft(); });
watch(() => JSON.stringify(validate()), () => {
  // Clear a step's error flag as soon as it's fixed.
  steps.value.forEach((s, i) => { if (stepErrors[s.key] && !validate().some((v) => v.step === i)) { stepErrors[s.key] = false; fieldErrors[s.key] = ''; } });
});

onMounted(async () => {
  try {
    boot.value = await api(props.urls.bootstrap);
  } catch (e) {
    bootError.value = e.message;
    return;
  }
  if (boot.value.pages.length === 1) creative.page_id = boot.value.pages[0].id;
  if (isInstagram) load('instagram', props.urls.instagram, {}, (data) => { lists.instagram = data; pickInstagram(); });
  const draft = readDraft();
  if (draft) draftOffer.value = { savedAt: fmtDate(draft.savedAt) };
});
</script>

<style scoped src="./builder.css"></style>
