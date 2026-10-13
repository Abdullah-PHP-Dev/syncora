<template>
  <div class="fb">

    <!-- ===== Header ===== -->
    <header class="fb-head">
      <div class="fb-head-main">
        <span class="fb-head-icon"><i class="bx bxl-meta"></i></span>
        <div>
          <h4>Create a Facebook &amp; Instagram ad</h4>
          <p>Use an existing campaign, ad set or creative, or create new ones - mix and match as you go.</p>
        </div>
      </div>
      <div class="fb-head-actions">
        <span v-if="boot" class="fb-acct" :title="boot.account.id"><i class="bx bx-briefcase-alt-2"></i> {{ boot.account.name }} · {{ boot.account.currency }}</span>
        <a :href="urls.campaignsList" class="fb-btn fb-btn-ghost"><i class="bx bx-list-ul"></i> Campaigns</a>
      </div>
    </header>

    <!-- ===== Draft banner ===== -->
    <div v-if="draftOffer" class="fb-banner">
      <i class="bx bx-history"></i>
      <span>You have an unfinished ad from {{ draftOffer.savedAt }}.</span>
      <button type="button" class="fb-btn fb-btn-primary fb-btn-sm" @click="resumeDraft">Continue it</button>
      <button type="button" class="fb-btn fb-btn-ghost fb-btn-sm" @click="discardDraft">Start fresh</button>
    </div>

    <!-- ===== Loading / blocked states ===== -->
    <div v-if="bootError" class="fb-card fb-empty">
      <span class="fb-empty-ic"><i class="bx bx-plug"></i></span>
      <h5>We couldn't open your ad account</h5>
      <p>{{ bootError }}</p>
      <a :href="urls.connections" class="fb-btn fb-btn-primary"><i class="bx bx-link"></i> Go to Connections</a>
    </div>
    <div v-else-if="!boot" class="fb-card fb-loading"><span class="spinner-border"></span> Loading your ad account from Meta…</div>

    <template v-else>
      <div v-if="!boot.account.active" class="fb-alert is-warn"><i class="bx bx-error"></i> Meta reports this ad account as not active. Ads can't run until it is fixed in Meta Business Settings.</div>

      <!-- ===== Stepper ===== -->
      <nav class="fb-steps" aria-label="Steps">
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
              <h5>Campaign</h5>
              <div class="fb-seg" role="tablist">
                <button type="button" role="tab" :class="{ on: campaign.mode === 'new' }" @click="setMode('campaign', 'new')"><i class="bx bx-plus"></i> Create new</button>
                <button type="button" role="tab" :class="{ on: campaign.mode === 'existing' }" @click="setMode('campaign', 'existing')"><i class="bx bx-folder-open"></i> Use existing</button>
              </div>
            </div>

            <template v-if="campaign.mode === 'existing'">
              <label class="fb-label">Choose a campaign</label>
              <div class="fb-search"><i class="bx bx-search"></i><input v-model="campaignQuery" type="search" placeholder="Search campaigns by name…" aria-label="Search campaigns"></div>
              <div v-if="lists.loading.campaigns" class="fb-inline-load"><span class="spinner-border spinner-border-sm"></span> Loading campaigns…</div>
              <p v-else-if="lists.errors.campaigns" class="fb-field-err">{{ lists.errors.campaigns }}</p>
              <div v-else class="fb-pick-list">
                <button v-for="c in filteredCampaigns" :key="c.id" type="button" class="fb-pick" :class="{ on: campaign.id === c.id, 'is-off': !c.goals.length }"
                        :disabled="!c.goals.length" @click="chooseCampaign(c)">
                  <span class="fb-pick-ic"><i class="bx" :class="goalIcon(c.goals[0])"></i></span>
                  <span class="fb-pick-main">
                    <strong>{{ c.name }}</strong>
                    <small>{{ objectiveLabel(c) }} · {{ statusLabel(c.status) }}<template v-if="c.campaign_budget"> · Campaign budget {{ money(c.daily_budget || c.lifetime_budget) }}{{ c.daily_budget ? '/day' : '' }}</template><template v-if="c.special_ad_categories.length"> · Special Ad Category</template></small>
                  </span>
                  <i v-if="campaign.id === c.id" class="bx bxs-check-circle fb-pick-check"></i>
                </button>
                <div v-if="!filteredCampaigns.length" class="fb-mini-empty">
                  <i class="bx bx-folder"></i>
                  <span>{{ campaignQuery ? 'No campaign matches that name.' : 'This ad account has no active or paused campaigns yet.' }}</span>
                  <button type="button" class="fb-link" @click="setMode('campaign', 'new')">Create a new campaign</button>
                </div>
              </div>
              <p v-if="selectedCampaign && selectedCampaign.goals.length > 1" class="fb-hint"><i class="bx bx-info-circle"></i> This campaign's objective covers more than one goal - pick the one you want below.</p>
              <div v-if="selectedCampaign && selectedCampaign.goals.length > 1" class="fb-goal-mini">
                <button v-for="g in selectedCampaign.goals" :key="g" type="button" :class="{ on: goal === g }" @click="setGoal(g)"><i class="bx" :class="goalIcon(g)"></i> {{ goals[g].label }}</button>
              </div>
            </template>

            <template v-else>
              <label class="fb-label">What do you want this campaign to achieve?</label>
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
                  <label class="fb-label" for="fbCampaignName">Campaign name</label>
                  <input id="fbCampaignName" v-model.trim="campaign.name" class="form-control" maxlength="255" :placeholder="goal ? goals[goal].label + ' - ' + today : 'Campaign name'">
                </div>
                <div>
                  <label class="fb-label" for="fbSpecial">Special Ad Category <span class="fb-opt">if it applies</span></label>
                  <select id="fbSpecial" v-model="campaign.special_ad_category" class="form-control">
                    <option :value="null">None</option>
                    <option value="HOUSING">Housing</option>
                    <option value="EMPLOYMENT">Employment</option>
                    <option value="CREDIT">Credit</option>
                    <option value="FINANCIAL_PRODUCTS_SERVICES">Financial products &amp; services</option>
                    <option value="ISSUES_ELECTIONS_POLITICS">Social issues, elections or politics</option>
                  </select>
                </div>
              </div>
              <p v-if="campaign.special_ad_category" class="fb-hint is-warn"><i class="bx bx-shield-quarter"></i> Meta requires this category for these ads. It limits targeting: no age or gender narrowing, no detailed targeting or lookalikes - we'll apply that automatically. Social-issue ads also need Meta's ad authorization on your account.</p>

              <label class="fb-toggle">
                <input v-model="campaign.campaign_budget" type="checkbox">
                <span><strong>Advantage campaign budget</strong><small>Set one budget for the whole campaign and let Meta share it across ad sets. Leave off to set the budget on the ad set.</small></span>
              </label>
              <div v-if="campaign.campaign_budget" class="fb-grid-3">
                <div>
                  <label class="fb-label">Budget type</label>
                  <div class="fb-seg fb-seg-full">
                    <button type="button" :class="{ on: campaign.budget_type === 'daily' }" @click="campaign.budget_type = 'daily'">Daily</button>
                    <button type="button" :class="{ on: campaign.budget_type === 'lifetime' }" @click="campaign.budget_type = 'lifetime'">Lifetime</button>
                  </div>
                </div>
                <div>
                  <label class="fb-label" for="fbCampBudget">Budget ({{ boot.account.currency }})</label>
                  <input id="fbCampBudget" v-model.number="campaign.budget" type="number" min="1" step="0.01" class="form-control">
                </div>
                <div>
                  <label class="fb-label" for="fbCampBid">Bid strategy</label>
                  <select id="fbCampBid" v-model="campaign.bid_strategy" class="form-control">
                    <option value="LOWEST_COST_WITHOUT_CAP">Highest volume (recommended)</option>
                    <option value="COST_CAP">Cost per result goal</option>
                    <option value="LOWEST_COST_WITH_BID_CAP">Bid cap</option>
                  </select>
                </div>
              </div>
            </template>

            <template v-if="goal">
              <label class="fb-label mt-3">Where should people go? <span class="fb-opt">conversion location</span></label>
              <div class="fb-dests">
                <button v-for="(d, key) in goals[goal].destinations" :key="key" type="button" class="fb-dest" :class="{ on: destination === key }" @click="setDestination(key)">
                  <i class="bx" :class="destIcon(key)"></i> {{ d.label }}
                </button>
              </div>
              <p v-if="destRules && destRules.needs_pixel" class="fb-hint"><i class="bx bx-target-lock"></i> This needs the Meta Pixel (dataset) on your website - you'll choose it on the next step.</p>
            </template>
            <p v-if="fieldErrors.campaign" class="fb-field-err">{{ fieldErrors.campaign }}</p>
          </div>

          <!-- ============ 2. Ad set ============ -->
          <div v-show="current === 'adset'" class="fb-card">
            <div class="fb-card-h">
              <h5>Ad set</h5>
              <div class="fb-seg">
                <button type="button" :class="{ on: adset.mode === 'new' }" @click="setMode('adset', 'new')"><i class="bx bx-plus"></i> Create new</button>
                <button type="button" :class="{ on: adset.mode === 'existing' }" :disabled="campaign.mode !== 'existing'" :title="campaign.mode !== 'existing' ? 'A new campaign has no ad sets yet' : ''" @click="setMode('adset', 'existing')"><i class="bx bx-folder-open"></i> Use existing</button>
              </div>
            </div>
            <p class="fb-sub">The ad set decides who sees your ads, when, with what budget and what Meta optimizes for.</p>

            <template v-if="adset.mode === 'existing'">
              <div v-if="lists.loading.adsets" class="fb-inline-load"><span class="spinner-border spinner-border-sm"></span> Loading ad sets…</div>
              <p v-else-if="lists.errors.adsets" class="fb-field-err">{{ lists.errors.adsets }}</p>
              <div v-else class="fb-pick-list">
                <button v-for="s in lists.adsets" :key="s.id" type="button" class="fb-pick" :class="{ on: adset.id === s.id }" @click="adset.id = s.id">
                  <span class="fb-pick-ic"><i class="bx bx-target-lock"></i></span>
                  <span class="fb-pick-main">
                    <strong>{{ s.name }}</strong>
                    <small>{{ statusLabel(s.status) }} · {{ optimizationLabel(s.optimization_goal) }}<template v-if="s.daily_budget || s.lifetime_budget"> · {{ money(s.daily_budget || s.lifetime_budget) }}{{ s.daily_budget ? '/day' : ' lifetime' }}</template></small>
                    <small class="fb-pick-aud"><i class="bx bx-group"></i> {{ s.targeting_summary }}</small>
                  </span>
                  <i v-if="adset.id === s.id" class="bx bxs-check-circle fb-pick-check"></i>
                </button>
                <div v-if="!lists.adsets.length" class="fb-mini-empty"><i class="bx bx-target-lock"></i><span>This campaign has no active or paused ad sets.</span><button type="button" class="fb-link" @click="setMode('adset', 'new')">Create a new ad set</button></div>
              </div>
              <p class="fb-hint"><i class="bx bx-info-circle"></i> The ad uses this ad set's audience, budget and schedule as they are in Meta.</p>
            </template>

            <template v-else>
              <div class="fb-grid-2">
                <div>
                  <label class="fb-label" for="fbAdsetName">Ad set name</label>
                  <input id="fbAdsetName" v-model.trim="adset.name" class="form-control" maxlength="255" :placeholder="'Audience - ' + today">
                </div>
                <div v-if="destRules">
                  <label class="fb-label" for="fbOptGoal">Optimize for <i class="bx bx-help-circle fb-tip" title="What Meta tries to get the most of within your budget."></i></label>
                  <select id="fbOptGoal" v-model="adset.optimization_goal" class="form-control">
                    <option v-for="(label, key) in destRules.optimization_goals" :key="key" :value="key">{{ label }}</option>
                  </select>
                </div>
              </div>

              <!-- Tracking / promoted object -->
              <div v-if="destRules && (destRules.needs_pixel || destRules.needs_app || needsPage)" class="fb-group">
                <h6><i class="bx bx-target-lock"></i> Tracking &amp; promoted object</h6>
                <div class="fb-grid-2">
                  <div v-if="needsPage">
                    <label class="fb-label" for="fbPage">Facebook Page</label>
                    <select id="fbPage" v-model="adset.page_id" class="form-control">
                      <option :value="null" disabled>Choose a Page</option>
                      <option v-for="p in boot.pages" :key="p.id" :value="p.id">{{ p.name }}</option>
                    </select>
                    <p v-if="!boot.pages.length" class="fb-field-err">No Pages found - reconnect your ad account in Connections to load your Pages.</p>
                  </div>
                  <template v-if="destRules.needs_pixel">
                    <div>
                      <label class="fb-label" for="fbPixel">Meta Pixel / dataset</label>
                      <select id="fbPixel" v-model="adset.pixel_id" class="form-control" :disabled="lists.loading.pixels">
                        <option :value="null" disabled>{{ lists.loading.pixels ? 'Loading…' : 'Choose a Pixel' }}</option>
                        <option v-for="p in lists.pixels" :key="p.id" :value="p.id">{{ p.name }}{{ p.last_fired ? '' : ' (no events yet)' }}</option>
                      </select>
                      <p v-if="!lists.loading.pixels && !lists.pixels.length" class="fb-field-err">This ad account has no Pixel. Create one in Meta Events Manager and install it on your website first.</p>
                      <p v-else-if="selectedPixel && !selectedPixel.last_fired" class="fb-hint is-warn"><i class="bx bx-error"></i> Meta hasn't received events from this Pixel yet - conversions can't be optimized until it fires on your site.</p>
                    </div>
                    <div>
                      <label class="fb-label" for="fbEvent">Conversion event</label>
                      <select id="fbEvent" v-model="adset.custom_event_type" class="form-control">
                        <option v-for="(label, key) in boot.conversion_events" :key="key" :value="key">{{ label }}</option>
                      </select>
                    </div>
                  </template>
                  <template v-if="destRules.needs_app">
                    <div>
                      <label class="fb-label" for="fbApp">App</label>
                      <select id="fbApp" v-model="adset.application_id" class="form-control" @change="adset.object_store_url = (selectedApp && selectedApp.store_urls[0]) || null">
                        <option :value="null" disabled>{{ lists.loading.apps ? 'Loading…' : 'Choose an app' }}</option>
                        <option v-for="a in lists.apps" :key="a.id" :value="a.id">{{ a.name }}</option>
                      </select>
                      <p v-if="!lists.loading.apps && !lists.apps.length" class="fb-field-err">No apps are available to this ad account. Register the app on developers.facebook.com and add it to your Business first.</p>
                    </div>
                    <div>
                      <label class="fb-label" for="fbStoreUrl">App store URL</label>
                      <input id="fbStoreUrl" v-model.trim="adset.object_store_url" type="url" class="form-control" placeholder="https://apps.apple.com/...">
                    </div>
                  </template>
                </div>
              </div>

              <!-- Budget & schedule -->
              <div class="fb-group">
                <h6><i class="bx bx-wallet"></i> Budget &amp; schedule</h6>
                <div v-if="campaignHasBudget" class="fb-hint"><i class="bx bx-info-circle"></i> This campaign uses an Advantage campaign budget, so the budget is set on the campaign.</div>
                <div v-else class="fb-grid-3">
                  <div>
                    <label class="fb-label">Budget type</label>
                    <div class="fb-seg fb-seg-full">
                      <button type="button" :class="{ on: adset.budget_type === 'daily' }" @click="adset.budget_type = 'daily'">Daily</button>
                      <button type="button" :class="{ on: adset.budget_type === 'lifetime' }" @click="adset.budget_type = 'lifetime'">Lifetime</button>
                    </div>
                  </div>
                  <div>
                    <label class="fb-label" for="fbBudget">{{ adset.budget_type === 'daily' ? 'Daily budget' : 'Lifetime budget' }} ({{ boot.account.currency }})</label>
                    <input id="fbBudget" v-model.number="adset.budget" type="number" min="1" step="0.01" class="form-control">
                    <p v-if="boot.account.min_daily_budget && adset.budget_type === 'daily' && adset.budget && adset.budget < boot.account.min_daily_budget" class="fb-field-err">Meta's minimum daily budget for this account is {{ money(boot.account.min_daily_budget) }}.</p>
                  </div>
                  <div>
                    <label class="fb-label" for="fbBid">Bid strategy</label>
                    <select id="fbBid" v-model="adset.bid_strategy" class="form-control">
                      <option value="LOWEST_COST_WITHOUT_CAP">Highest volume (recommended)</option>
                      <option value="COST_CAP">Cost per result goal</option>
                      <option value="LOWEST_COST_WITH_BID_CAP">Bid cap</option>
                    </select>
                  </div>
                </div>
                <p v-if="!campaignHasBudget" class="fb-hint"><i class="bx bx-bulb"></i> <span><strong>Daily</strong> spends about this much each day (some days up to 25% more, balanced over the week). <strong>Lifetime</strong> spreads the total over the schedule and needs an end date.</span></p>
                <div v-if="!campaignHasBudget && adset.bid_strategy !== 'LOWEST_COST_WITHOUT_CAP'" class="fb-grid-3">
                  <div>
                    <label class="fb-label" for="fbBidAmount">{{ adset.bid_strategy === 'COST_CAP' ? 'Cost per result goal' : 'Bid cap' }} ({{ boot.account.currency }})</label>
                    <input id="fbBidAmount" v-model.number="adset.bid_amount" type="number" min="0.01" step="0.01" class="form-control">
                  </div>
                  <p class="fb-hint fb-span-2"><i class="bx bx-info-circle"></i> Caps can limit delivery: if Meta can't get results at or below this amount, your ads may spend less than the budget.</p>
                </div>
                <div class="fb-grid-2">
                  <div>
                    <label class="fb-label" for="fbStart">Start</label>
                    <input id="fbStart" v-model="adset.start_time" type="datetime-local" class="form-control" :min="nowLocal">
                  </div>
                  <div>
                    <label class="fb-label" for="fbEnd">End <span class="fb-opt">{{ adset.budget_type === 'lifetime' && !campaignHasBudget ? 'required for lifetime budget' : 'optional - runs until you stop it' }}</span></label>
                    <input id="fbEnd" v-model="adset.end_time" type="datetime-local" class="form-control" :min="adset.start_time || nowLocal">
                  </div>
                </div>
                <p v-if="boot.account.timezone" class="fb-hint"><i class="bx bx-time-five"></i> Times are in the ad account's time zone ({{ boot.account.timezone }}).</p>
              </div>
            </template>
            <p v-if="fieldErrors.adset" class="fb-field-err">{{ fieldErrors.adset }}</p>
          </div>

          <!-- ============ 3. Audience ============ -->
          <div v-show="current === 'audience'" class="fb-card">
            <div class="fb-card-h">
              <h5>Audience &amp; placements</h5>
              <label class="fb-switch"><input v-model="ui.advanced" type="checkbox"><span>Advanced</span></label>
            </div>

            <template v-if="adset.mode === 'existing'">
              <div class="fb-mini-empty is-info"><i class="bx bx-group"></i><span>You're using an existing ad set, so its audience and placements are already set in Meta.</span></div>
            </template>

            <template v-else>
              <div v-if="specialCategory" class="fb-hint is-warn"><i class="bx bx-shield-quarter"></i> Special Ad Category: age 18-65+, all genders, no detailed targeting or lookalikes - Meta's rules.</div>

              <div v-if="ui.advanced && lists.audiences.saved.length" class="fb-group">
                <h6><i class="bx bx-bookmark"></i> Saved audience <span class="fb-opt">optional</span></h6>
                <select v-model="targeting.saved_audience_id" class="form-control">
                  <option :value="null">Build the audience here</option>
                  <option v-for="a in lists.audiences.saved" :key="a.id" :value="a.id">{{ a.name }} - {{ a.summary }}</option>
                </select>
                <p v-if="targeting.saved_audience_id" class="fb-hint"><i class="bx bx-info-circle"></i> The saved audience's targeting (defined in Ads Manager) is used as-is; placements below still apply.</p>
              </div>

              <template v-if="!targeting.saved_audience_id">
                <div class="fb-group">
                  <h6><i class="bx bx-map"></i> Locations</h6>
                  <search-picker v-model="targeting.locations" :fetcher="searchLocations" item-key="key" placeholder="Search countries, regions or cities…">
                    <template #chip="{ item }">{{ locationLabel(item) }}</template>
                    <template #option="{ item }">
                      <span class="sp-opt-main"><strong>{{ item.name }}</strong> <small class="fb-muted">{{ [item.region, item.country_name].filter(Boolean).join(', ') }}</small></span>
                      <span class="fb-tag">{{ item.type }}</span>
                    </template>
                  </search-picker>
                  <div v-for="city in targeting.locations.filter(l => l.type === 'city')" :key="'r' + city.key" class="fb-radius">
                    <span><i class="bx bx-current-location"></i> {{ city.name }}</span>
                    <select :value="city.radius || ''" class="form-control form-control-sm" @change="setRadius(city, $event.target.value)">
                      <option value="">City only</option>
                      <option v-for="r in radiusOptions" :key="r" :value="r">+{{ r }} km</option>
                    </select>
                  </div>
                  <template v-if="ui.advanced">
                    <label class="fb-label mt-2">Exclude locations</label>
                    <search-picker v-model="targeting.excluded_locations" :fetcher="searchLocations" item-key="key" chip-tone="exclude" placeholder="Search locations to exclude…">
                      <template #chip="{ item }">{{ locationLabel(item) }}</template>
                      <template #option="{ item }"><span class="sp-opt-main"><strong>{{ item.name }}</strong> <small class="fb-muted">{{ item.country_name }}</small></span><span class="fb-tag">{{ item.type }}</span></template>
                    </search-picker>
                  </template>
                </div>

                <div class="fb-group">
                  <h6><i class="bx bx-user"></i> People</h6>
                  <div class="fb-grid-3">
                    <div>
                      <label class="fb-label">Age</label>
                      <div class="fb-age">
                        <select v-model.number="targeting.age_min" class="form-control" :disabled="specialCategory">
                          <option v-for="a in ages" :key="'min' + a" :value="a">{{ a }}</option>
                        </select>
                        <span>to</span>
                        <select v-model.number="targeting.age_max" class="form-control" :disabled="specialCategory || targeting.advantage_audience">
                          <option v-for="a in ages" :key="'max' + a" :value="a">{{ a === 65 ? '65+' : a }}</option>
                        </select>
                      </div>
                    </div>
                    <div>
                      <label class="fb-label">Gender</label>
                      <div class="fb-seg fb-seg-full">
                        <button type="button" :class="{ on: !targeting.genders.length }" :disabled="specialCategory" @click="targeting.genders = []">All</button>
                        <button type="button" :class="{ on: targeting.genders[0] === 1 }" :disabled="specialCategory" @click="targeting.genders = [1]">Men</button>
                        <button type="button" :class="{ on: targeting.genders[0] === 2 }" :disabled="specialCategory" @click="targeting.genders = [2]">Women</button>
                      </div>
                    </div>
                    <div v-if="ui.advanced">
                      <label class="fb-label">Languages <span class="fb-opt">optional</span></label>
                      <search-picker v-model="targeting.locales" :fetcher="searchLocales" item-key="key" :min-chars="1" placeholder="eg. Arabic, English…" />
                    </div>
                  </div>
                </div>

                <div v-if="!specialCategory" class="fb-group">
                  <h6><i class="bx bx-purchase-tag"></i> Detailed targeting <span class="fb-opt">interests, behaviors, demographics</span></h6>
                  <search-picker v-model="targeting.interests" :fetcher="searchTargeting" placeholder="Search interests, behaviors or demographics (eg. Online shopping, Small business owners)…">
                    <template #option="{ item }">
                      <span class="sp-opt-main"><strong>{{ item.name }}</strong> <small class="fb-muted">{{ (item.path || []).slice(0, -1).join(' › ') }}</small></span>
                      <span class="fb-tag">{{ typeLabel(item.type) }}</span>
                      <small v-if="item.size_min" class="fb-muted">{{ compact(item.size_min) }}–{{ compact(item.size_max) }}</small>
                    </template>
                  </search-picker>
                  <p class="fb-hint"><i class="bx bx-info-circle"></i> Options come straight from Meta for your ad account. People matching <strong>any</strong> of these are included.</p>
                  <template v-if="ui.advanced">
                    <div v-for="(group, gi) in targeting.narrow" :key="'n' + gi" class="fb-narrow">
                      <div class="fb-narrow-h"><strong>And must also match</strong><button type="button" class="fb-link" @click="targeting.narrow.splice(gi, 1)">Remove</button></div>
                      <search-picker :value="group" :fetcher="searchTargeting" placeholder="Narrow further…" @input="v => targeting.narrow.splice(gi, 1, v)" />
                    </div>
                    <button v-if="targeting.interests.length && targeting.narrow.length < 3" type="button" class="fb-link" @click="targeting.narrow.push([])"><i class="bx bx-plus"></i> Narrow audience</button>
                  </template>
                </div>

                <div v-if="ui.advanced" class="fb-group">
                  <h6><i class="bx bx-user-check"></i> Custom &amp; lookalike audiences</h6>
                  <div v-if="lists.loading.audiences" class="fb-inline-load"><span class="spinner-border spinner-border-sm"></span> Loading audiences…</div>
                  <template v-else-if="usableAudiences.length">
                    <div class="fb-grid-2">
                      <div>
                        <label class="fb-label">Include</label>
                        <search-picker v-model="ui.includeAudiences" :options="usableAudiences" placeholder="Choose audiences…">
                          <template #option="{ item }"><span class="sp-opt-main">{{ item.name }} <small v-if="!item.ready" class="fb-muted">({{ item.status || 'not ready' }})</small></span><span class="fb-tag">{{ item.kind }}</span></template>
                        </search-picker>
                      </div>
                      <div>
                        <label class="fb-label">Exclude</label>
                        <search-picker v-model="ui.excludeAudiences" :options="lists.audiences.custom" chip-tone="exclude" placeholder="Exclude audiences (eg. existing customers)…" />
                      </div>
                    </div>
                  </template>
                  <p v-else class="fb-hint"><i class="bx bx-info-circle"></i> No custom audiences in this ad account yet. Create them in Meta Ads Manager (eg. website visitors or a customer list) and they'll appear here.</p>
                </div>

                <label v-if="!specialCategory" class="fb-toggle">
                  <input v-model="targeting.advantage_audience" type="checkbox">
                  <span><strong>Advantage+ audience</strong><small>Let Meta find people beyond your settings when it expects better results. Your age and interests become suggestions; the maximum age stays 65+.</small></span>
                </label>
              </template>

              <div class="fb-group">
                <h6><i class="bx bx-layout"></i> Placements</h6>
                <div class="fb-seg">
                  <button type="button" :class="{ on: placements.mode === 'advantage' }" @click="placements.mode = 'advantage'">Advantage+ placements</button>
                  <button type="button" :class="{ on: placements.mode === 'manual' }" @click="placements.mode = 'manual'">Manual</button>
                </div>
                <p class="fb-hint"><i class="bx bx-bulb"></i> {{ placements.mode === 'advantage' ? 'Meta shows your ad where it is likely to perform best across Facebook, Instagram, Messenger and Audience Network - usually the best value for your budget.' : 'Only the places you pick. Fewer placements can raise costs.' }}</p>
                <div v-if="placements.mode === 'manual'" class="fb-place-grid">
                  <label v-for="p in platformOptions" :key="p.key" class="fb-check"><input v-model="placements.publisher_platforms" type="checkbox" :value="p.key"><i class="bx" :class="p.icon"></i> {{ p.label }}</label>
                  <div v-if="placements.publisher_platforms.includes('facebook')" class="fb-positions">
                    <small>Facebook positions</small>
                    <label v-for="pos in fbPositions" :key="pos.key" class="fb-check is-sm"><input v-model="placements.facebook_positions" type="checkbox" :value="pos.key"> {{ pos.label }}</label>
                  </div>
                  <div v-if="placements.publisher_platforms.includes('instagram')" class="fb-positions">
                    <small>Instagram positions</small>
                    <label v-for="pos in igPositions" :key="pos.key" class="fb-check is-sm"><input v-model="placements.instagram_positions" type="checkbox" :value="pos.key"> {{ pos.label }}</label>
                  </div>
                  <div class="fb-positions">
                    <small>Devices</small>
                    <label class="fb-check is-sm"><input v-model="placements.device_platforms" type="checkbox" value="mobile"> Mobile</label>
                    <label class="fb-check is-sm"><input v-model="placements.device_platforms" type="checkbox" value="desktop"> Desktop</label>
                  </div>
                </div>
              </div>
            </template>
            <p v-if="fieldErrors.audience" class="fb-field-err">{{ fieldErrors.audience }}</p>
          </div>

          <!-- ============ 4. Creative ============ -->
          <div v-show="current === 'creative'" class="fb-card">
            <div class="fb-card-h">
              <h5>Creative</h5>
              <div class="fb-seg">
                <button type="button" :class="{ on: creative.mode === 'new' }" @click="setMode('creative', 'new')"><i class="bx bx-plus"></i> Create new</button>
                <button type="button" :class="{ on: creative.mode === 'existing' }" @click="setMode('creative', 'existing')"><i class="bx bx-folder-open"></i> Use existing</button>
              </div>
            </div>

            <template v-if="creative.mode === 'existing'">
              <div class="fb-search"><i class="bx bx-search"></i><input v-model="creativeQuery" type="search" placeholder="Search creatives by name or text…"></div>
              <div v-if="lists.loading.creatives" class="fb-inline-load"><span class="spinner-border spinner-border-sm"></span> Loading creatives…</div>
              <p v-else-if="lists.errors.creatives" class="fb-field-err">{{ lists.errors.creatives }}</p>
              <div v-else class="fb-creative-grid">
                <button v-for="c in filteredCreatives" :key="c.id" type="button" class="fb-creative" :class="{ on: creative.id === c.id }" @click="chooseCreative(c)">
                  <span class="fb-creative-img"><img v-if="c.thumbnail" :src="c.thumbnail" alt="" loading="lazy"><i v-else class="bx bx-image"></i></span>
                  <strong>{{ c.name }}</strong>
                  <small>{{ c.headline || c.text || c.type }}</small>
                </button>
                <div v-if="!filteredCreatives.length" class="fb-mini-empty"><i class="bx bx-image"></i><span>No creatives found.</span><button type="button" class="fb-link" @click="setMode('creative', 'new')">Create a new creative</button></div>
              </div>
              <p v-if="creative.id" class="fb-hint"><i class="bx bx-info-circle"></i> Meta checks the creative fits this ad set's goal when the ad is created (eg. an Instant form ad needs a lead form creative).</p>
            </template>

            <template v-else>
              <div class="fb-grid-2">
                <div>
                  <label class="fb-label" for="fbCrPage">Facebook Page</label>
                  <select id="fbCrPage" v-model="creative.page_id" class="form-control">
                    <option :value="null" disabled>Choose a Page</option>
                    <option v-for="p in boot.pages" :key="p.id" :value="p.id">{{ p.name }}</option>
                  </select>
                </div>
                <div>
                  <label class="fb-label" for="fbIg">Instagram account <span class="fb-opt">for Instagram placements</span></label>
                  <select id="fbIg" v-model="creative.instagram_user_id" class="form-control">
                    <option :value="null">{{ lists.loading.instagram ? 'Loading…' : 'Use the Facebook Page' }}</option>
                    <option v-for="a in lists.instagram" :key="a.id" :value="a.id">@{{ a.username || a.id }}</option>
                  </select>
                </div>
              </div>

              <div v-if="destRules && destRules.needs_lead_form" class="fb-group">
                <h6><i class="bx bx-id-card"></i> Instant form</h6>
                <select v-model="creative.lead_form_id" class="form-control" :disabled="lists.loading.leadForms || !creative.page_id">
                  <option :value="null" disabled>{{ !creative.page_id ? 'Choose the Page first' : (lists.loading.leadForms ? 'Loading forms…' : 'Choose a form') }}</option>
                  <option v-for="f in lists.leadForms" :key="f.id" :value="f.id">{{ f.name }}</option>
                </select>
                <p v-if="lists.errors.leadForms" class="fb-field-err">{{ lists.errors.leadForms }}</p>
                <p v-else-if="creative.page_id && !lists.loading.leadForms && !lists.leadForms.length" class="fb-hint is-warn"><i class="bx bx-error"></i> This Page has no active Instant forms. Create one in Meta Business Suite (Page &gt; Instant forms) first.</p>
              </div>

              <label class="fb-label">Format</label>
              <div class="fb-seg">
                <button type="button" :class="{ on: creative.format === 'IMAGE' }" @click="setFormat('IMAGE')"><i class="bx bx-image"></i> Single image</button>
                <button type="button" :class="{ on: creative.format === 'VIDEO' }" @click="setFormat('VIDEO')"><i class="bx bx-video"></i> Video</button>
                <button type="button" :class="{ on: creative.format === 'CAROUSEL' }" :disabled="destRules && (destRules.message_app || destRules.needs_lead_form)" @click="setFormat('CAROUSEL')"><i class="bx bx-carousel"></i> Carousel</button>
              </div>

              <!-- Media -->
              <div class="fb-group">
                <h6><i class="bx bx-photo-album"></i> {{ creative.format === 'CAROUSEL' ? 'Cards (2-10)' : 'Media' }}</h6>
                <div v-if="creative.format !== 'CAROUSEL'" class="fb-media-row">
                  <div v-if="creative.media[0]" class="fb-media">
                    <img v-if="creative.media[0].type === 'image' || creative.media[0].thumbnail_url" :src="creative.media[0].thumbnail_url || creative.media[0].url" alt="">
                    <span v-else class="fb-media-ph"><i class="bx bx-play"></i></span>
                    <div><strong>{{ creative.media[0].original_name }}</strong><small>{{ creative.media[0].type === 'video' ? 'Video' : 'Image' }}<template v-if="creative.media[0].width"> · {{ creative.media[0].width }}×{{ creative.media[0].height }}</template></small></div>
                    <button type="button" class="fb-icon-btn" aria-label="Remove" @click="creative.media = []"><i class="bx bx-trash"></i></button>
                  </div>
                  <div v-else class="fb-media-actions">
                    <button type="button" class="fb-btn fb-btn-ghost" @click="openGallery('media')"><i class="bx bx-images"></i> Media Gallery</button>
                    <label class="fb-btn fb-btn-ghost"><i class="bx bx-cloud-upload"></i> Upload new<input type="file" hidden :accept="creative.format === 'VIDEO' ? 'video/mp4,video/quicktime' : 'image/jpeg,image/png'" @change="uploadNew($event, 'media')"></label>
                  </div>
                </div>
                <div v-if="creative.format === 'VIDEO' && creative.media[0]" class="fb-media-row">
                  <div v-if="creative.thumbnail" class="fb-media is-sm">
                    <img :src="creative.thumbnail.url" alt="">
                    <div><strong>Thumbnail</strong><small>{{ creative.thumbnail.original_name }}</small></div>
                    <button type="button" class="fb-icon-btn" aria-label="Remove thumbnail" @click="creative.thumbnail = null"><i class="bx bx-trash"></i></button>
                  </div>
                  <div v-else class="fb-hint"><i class="bx bx-image"></i> {{ creative.media[0].thumbnail_url ? 'The video\'s own thumbnail is used.' : 'Add a thumbnail image for the video.' }}
                    <button type="button" class="fb-link" @click="openGallery('thumbnail')">Choose thumbnail</button>
                  </div>
                </div>
                <div v-if="creative.format === 'CAROUSEL'" class="fb-cards">
                  <div v-for="(card, ci) in creative.cards" :key="'c' + ci" class="fb-ccard">
                    <img :src="card.asset.url" alt="">
                    <input v-model.trim="card.headline" class="form-control form-control-sm" placeholder="Headline" maxlength="255">
                    <input v-model.trim="card.description" class="form-control form-control-sm" placeholder="Description (optional)" maxlength="255">
                    <input v-model.trim="card.link" type="url" class="form-control form-control-sm" placeholder="Card link (optional)">
                    <button type="button" class="fb-icon-btn" aria-label="Remove card" @click="creative.cards.splice(ci, 1)"><i class="bx bx-x"></i></button>
                  </div>
                  <div v-if="creative.cards.length < 10" class="fb-ccard is-add">
                    <button type="button" class="fb-btn fb-btn-ghost fb-btn-sm" @click="openGallery('card')"><i class="bx bx-images"></i> Gallery</button>
                    <label class="fb-btn fb-btn-ghost fb-btn-sm"><i class="bx bx-cloud-upload"></i> Upload<input type="file" hidden accept="image/jpeg,image/png" @change="uploadNew($event, 'card')"></label>
                  </div>
                </div>
                <p v-if="uploading" class="fb-inline-load"><span class="spinner-border spinner-border-sm"></span> Uploading to your Media Gallery… {{ uploading }}%</p>
                <p v-if="mediaError" class="fb-field-err">{{ mediaError }}</p>
                <p class="fb-hint"><i class="bx bx-info-circle"></i> {{ creative.format === 'VIDEO' ? 'MP4 or MOV. Vertical 4:5 or 9:16 works best for Reels and Stories.' : 'JPG or PNG, at least 1080×1080 px. Square (1:1) or 4:5 works across most placements.' }}</p>
              </div>

              <!-- Text -->
              <div class="fb-group">
                <h6><i class="bx bx-text"></i> Text</h6>
                <label class="fb-label" for="fbPrimary">Primary text <span class="fb-count">{{ (creative.primary_text || '').length }}/2200</span></label>
                <textarea id="fbPrimary" v-model="creative.primary_text" class="form-control" rows="3" maxlength="2200" placeholder="Tell people what you offer. The first ~125 characters show before 'See more'."></textarea>
                <div class="fb-grid-2">
                  <div>
                    <label class="fb-label" for="fbHeadline">Headline <span class="fb-count">{{ (creative.headline || '').length }}/40 recommended</span></label>
                    <input id="fbHeadline" v-model="creative.headline" class="form-control" maxlength="255">
                  </div>
                  <div>
                    <label class="fb-label" for="fbDesc">Description <span class="fb-opt">optional</span></label>
                    <input id="fbDesc" v-model="creative.description" class="form-control" maxlength="255">
                  </div>
                </div>
                <div class="fb-grid-2">
                  <div v-if="destRules && destRules.needs_link && !destRules.needs_app">
                    <label class="fb-label" for="fbLink">Website URL</label>
                    <input id="fbLink" v-model.trim="creative.link" type="url" class="form-control" placeholder="https://yourstore.com/offer">
                  </div>
                  <div v-if="destRules">
                    <label class="fb-label" for="fbCta">Call to action</label>
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
            <div class="fb-card-h"><h5>Ad &amp; review</h5></div>
            <div class="fb-grid-2">
              <div>
                <label class="fb-label" for="fbAdName">Ad name</label>
                <input id="fbAdName" v-model.trim="ad.name" class="form-control" maxlength="255">
              </div>
              <div>
                <label class="fb-label">When it's created</label>
                <div class="fb-seg fb-seg-full">
                  <button type="button" :class="{ on: ad.status === 'PAUSED' }" @click="ad.status = 'PAUSED'"><i class="bx bx-pause-circle"></i> Save paused</button>
                  <button type="button" :class="{ on: ad.status === 'ACTIVE' }" @click="ad.status = 'ACTIVE'"><i class="bx bx-play-circle"></i> Publish</button>
                </div>
              </div>
            </div>
            <p class="fb-hint"><i class="bx bx-info-circle"></i> {{ ad.status === 'ACTIVE' ? 'The ad goes to Meta\'s review and starts delivering once approved (new campaign/ad set are switched on too; existing ones are left as they are).' : 'Everything is created paused so you can check it in Ads Manager first - turn it on from Campaigns when ready.' }}</p>

            <div class="fb-review">
              <div v-for="row in reviewRows" :key="row.label" class="fb-review-row">
                <span class="fb-review-l"><i class="bx" :class="row.icon"></i> {{ row.label }}</span>
                <span class="fb-review-v">{{ row.value }}</span>
                <button type="button" class="fb-link" @click="goTo(row.step)">Edit</button>
              </div>
            </div>

            <div v-if="blockers.length" class="fb-blockers">
              <strong><i class="bx bx-error-circle"></i> Fix before creating the ad</strong>
              <ul><li v-for="b in blockers" :key="b.text"><button type="button" class="fb-link" @click="goTo(b.step)">{{ b.text }}</button></li></ul>
            </div>

            <!-- Result -->
            <div v-if="result && result.success" class="fb-result is-ok">
              <i class="bx bx-check-circle"></i>
              <div>
                <strong>{{ result.message }}</strong>
                <p>{{ result.status === 'ACTIVE' ? 'It is in Meta\'s review now.' : 'It is saved paused.' }} Ad ID {{ result.ad_id }}.</p>
                <a :href="result.redirect_url || urls.campaignsList" class="fb-btn fb-btn-primary fb-btn-sm">Go to campaigns</a>
                <button type="button" class="fb-btn fb-btn-ghost fb-btn-sm" @click="startAnother">Create another ad</button>
              </div>
            </div>
            <div v-else-if="result" class="fb-result is-err">
              <i class="bx bx-error-circle"></i>
              <div>
                <strong>{{ result.step ? stepTitle(result.step) + ' couldn\'t be created' : 'The ad couldn\'t be created' }}</strong>
                <p>{{ result.message }}</p>
                <ul v-if="result.created && result.created.length" class="fb-created">
                  <li v-for="c in result.created" :key="c.type"><i class="bx bx-check"></i> {{ stepTitle(c.type) }} {{ c.reused ? 'selected' : 'created' }} on Meta ({{ c.id }}) - kept, it won't be created again.</li>
                </ul>
                <p class="fb-muted">Fix the problem and press "Create ad" again - it continues from this step.</p>
              </div>
            </div>
          </div>

          <!-- ===== Footer nav ===== -->
          <div class="fb-foot">
            <button type="button" class="fb-btn fb-btn-ghost" :disabled="step === 0 || submitting" @click="back"><i class="bx bx-chevron-left"></i> Back</button>
            <span class="fb-saved" :class="{ on: savedAt }"><i class="bx bx-cloud-upload"></i> {{ savedAt ? 'Draft saved ' + savedAt : 'Drafts save automatically' }}</span>
            <button v-if="current !== 'review'" type="button" class="fb-btn fb-btn-primary" @click="next">Continue <i class="bx bx-chevron-right"></i></button>
            <button v-else type="button" class="fb-btn fb-btn-primary" :disabled="submitting || blockers.length > 0 || (result && result.success)" @click="submit">
              <span v-if="submitting" class="spinner-border spinner-border-sm"></span><i v-else class="bx bx-rocket"></i>
              {{ submitting ? 'Creating on Meta…' : (result && !result.success ? 'Try again' : 'Create ad') }}
            </button>
          </div>
        </section>

        <!-- ===== Side: preview + estimate ===== -->
        <aside class="fb-side">
          <div v-if="adset.mode === 'new' && current !== 'campaign'" class="fb-card fb-estimate">
            <div class="fb-side-h"><strong><i class="bx bx-group"></i> Audience size</strong><small>Meta estimate</small></div>
            <div v-if="estimate.loading" class="fb-inline-load"><span class="spinner-border spinner-border-sm"></span> Asking Meta…</div>
            <template v-else-if="estimate.data">
              <div class="fb-est-num">{{ compact(estimate.data.audience_min) }} – {{ compact(estimate.data.audience_max) }}</div>
              <p class="fb-muted">Monthly active people matching your settings. An estimate from Meta, not a guarantee of reach or results.</p>
            </template>
            <p v-else class="fb-muted">{{ estimate.error || 'Add a location to see Meta\'s audience estimate.' }}</p>
          </div>

          <div class="fb-card fb-preview">
            <div class="fb-side-h"><strong><i class="bx bx-show"></i> Preview</strong><small>{{ creative.mode === 'existing' ? 'Rendered by Meta' : 'Approximate' }}</small></div>
            <template v-if="creative.mode === 'existing'">
              <div v-if="preview.loading" class="fb-inline-load"><span class="spinner-border spinner-border-sm"></span> Loading Meta's preview…</div>
              <div v-else-if="preview.html" class="fb-meta-preview" v-html="preview.html"></div>
              <p v-else class="fb-muted">{{ preview.error || 'Choose a creative to preview it.' }}</p>
            </template>
            <div v-else class="fb-mock">
              <div class="fb-mock-h">
                <span class="fb-mock-av"><img v-if="selectedPage && selectedPage.picture" :src="selectedPage.picture" alt=""><i v-else class="bx bxl-facebook"></i></span>
                <div><strong>{{ selectedPage ? selectedPage.name : 'Your Page' }}</strong><small>Sponsored · <i class="bx bx-world"></i></small></div>
              </div>
              <p class="fb-mock-text">{{ creative.primary_text || 'Your primary text appears here.' }}</p>
              <div v-if="creative.format === 'CAROUSEL'" class="fb-mock-carousel">
                <div v-for="(card, ci) in creative.cards.slice(0, 3)" :key="'p' + ci" class="fb-mock-card">
                  <img :src="card.asset.url" alt="">
                  <strong>{{ card.headline || 'Headline' }}</strong>
                </div>
                <div v-if="!creative.cards.length" class="fb-mock-media"><i class="bx bx-carousel"></i></div>
              </div>
              <div v-else class="fb-mock-media">
                <img v-if="creative.media[0] && (creative.media[0].type === 'image' || creative.media[0].thumbnail_url)" :src="creative.media[0].type === 'image' ? creative.media[0].url : (creative.thumbnail ? creative.thumbnail.url : creative.media[0].thumbnail_url)" alt="">
                <i v-else class="bx" :class="creative.format === 'VIDEO' ? 'bx-video' : 'bx-image'"></i>
                <span v-if="creative.format === 'VIDEO' && creative.media[0]" class="fb-mock-play"><i class="bx bx-play"></i></span>
              </div>
              <div v-if="creative.format !== 'CAROUSEL'" class="fb-mock-foot">
                <div><small>{{ linkHost }}</small><strong>{{ creative.headline || 'Your headline' }}</strong><small v-if="creative.description">{{ creative.description }}</small></div>
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
import { ref, reactive, computed, watch, onMounted, nextTick } from 'vue';
import SearchPicker from './SearchPicker.vue';
import MediaPicker from '../../media/MediaPicker.vue';
import { uploadToGallery, errorMessage, precheck } from '../../media/mediaShared';

const props = defineProps({
  urls: { type: Object, required: true },
  galleryUrls: { type: Object, required: true },
  accountId: { type: [Number, String], required: true },
});

const today = new Date().toISOString().slice(0, 10);
const nowLocal = new Date(Date.now() - new Date().getTimezoneOffset() * 60000).toISOString().slice(0, 16);
const ages = Array.from({ length: 48 }, (_, i) => i + 18);
const radiusOptions = [5, 10, 17, 25, 40, 80];
const platformOptions = [
  { key: 'facebook', label: 'Facebook', icon: 'bxl-facebook-circle' },
  { key: 'instagram', label: 'Instagram', icon: 'bxl-instagram' },
  { key: 'messenger', label: 'Messenger', icon: 'bxl-messenger' },
  { key: 'audience_network', label: 'Audience Network', icon: 'bx-grid-alt' },
];
const fbPositions = [{ key: 'feed', label: 'Feed' }, { key: 'story', label: 'Stories' }, { key: 'facebook_reels', label: 'Reels' }, { key: 'marketplace', label: 'Marketplace' }, { key: 'video_feeds', label: 'Video feeds' }, { key: 'search', label: 'Search results' }];
const igPositions = [{ key: 'stream', label: 'Feed' }, { key: 'story', label: 'Stories' }, { key: 'reels', label: 'Reels' }, { key: 'explore', label: 'Explore' }];
const CTA_LABELS = { LEARN_MORE: 'Learn more', SHOP_NOW: 'Shop now', SIGN_UP: 'Sign up', BOOK_NOW: 'Book now', CONTACT_US: 'Contact us', GET_OFFER: 'Get offer', ORDER_NOW: 'Order now', SUBSCRIBE: 'Subscribe', APPLY_NOW: 'Apply now', DOWNLOAD: 'Download', BUY_NOW: 'Buy now', GET_QUOTE: 'Get quote', MESSAGE_PAGE: 'Send message', WHATSAPP_MESSAGE: 'Send WhatsApp message', INSTAGRAM_MESSAGE: 'Send Instagram message', INSTALL_MOBILE_APP: 'Install now', USE_APP: 'Use app', PLAY_GAME: 'Play game', NO_BUTTON: 'No button' };
const OPT_LABELS = { LANDING_PAGE_VIEWS: 'Landing page views', LINK_CLICKS: 'Link clicks', REACH: 'Reach', IMPRESSIONS: 'Impressions', AD_RECALL_LIFT: 'Ad recall lift', THRUPLAY: 'ThruPlay', LEAD_GENERATION: 'Leads', QUALITY_LEAD: 'Conversion leads', OFFSITE_CONVERSIONS: 'Conversions', CONVERSATIONS: 'Conversations', POST_ENGAGEMENT: 'Post engagement', APP_INSTALLS: 'App installs' };

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
const placements = reactive({ mode: 'advantage', publisher_platforms: ['facebook', 'instagram'], facebook_positions: [], instagram_positions: [], device_platforms: ['mobile', 'desktop'] });
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
  try { return new URL(creative.link).hostname.replace(/^www\./, '').toUpperCase(); } catch (e) { return 'YOURWEBSITE.COM'; }
});
const pickerTypeLock = computed(() => (pickerTarget.value === 'media' ? (creative.format === 'VIDEO' ? 'video' : 'image') : 'image'));

const steps = computed(() => [
  { key: 'campaign', label: 'Campaign', summary: campaign.mode === 'existing' ? (selectedCampaign.value ? selectedCampaign.value.name : 'Choose one') : (goal.value ? goals.value[goal.value].label : 'Pick a goal') },
  { key: 'adset', label: 'Ad set', summary: adset.mode === 'existing' ? 'Existing' : (adset.budget && !campaignHasBudget.value ? money(adset.budget) + (adset.budget_type === 'daily' ? '/day' : '') : 'Budget & schedule') },
  { key: 'audience', label: 'Audience', summary: adset.mode === 'existing' ? 'From ad set' : (targeting.locations.length ? targeting.locations.length + ' location' + (targeting.locations.length > 1 ? 's' : '') : 'Who sees it'), skipped: adset.mode === 'existing' },
  { key: 'creative', label: 'Creative', summary: creative.mode === 'existing' ? 'Existing' : (creative.format === 'CAROUSEL' ? 'Carousel' : creative.format === 'VIDEO' ? 'Video' : 'Image') },
  { key: 'review', label: 'Ad & review', summary: ad.status === 'ACTIVE' ? 'Publish' : 'Save paused' },
]);
const current = computed(() => steps.value[step.value].key);

const reviewRows = computed(() => {
  if (!boot.value) return [];
  const g = goal.value ? goals.value[goal.value] : null;
  const locs = targeting.locations.map(locationLabel).join(', ');
  return [
    { step: 0, icon: 'bx-flag', label: 'Campaign', value: campaign.mode === 'existing' ? `${selectedCampaign.value ? selectedCampaign.value.name : '-'} (existing)` : `${campaign.name || '(no name)'} · new` },
    { step: 0, icon: 'bx-bullseye', label: 'Goal', value: g ? `${g.label} · ${destRules.value ? destRules.value.label : ''}` : '-' },
    { step: 1, icon: 'bx-target-lock', label: 'Ad set', value: adset.mode === 'existing' ? `${(lists.adsets.find((s) => s.id === adset.id) || {}).name || '-'} (existing)` : `${adset.name || '(no name)'} · new · ${destRules.value ? OPT_LABELS[adset.optimization_goal] || adset.optimization_goal : ''}` },
    { step: 1, icon: 'bx-wallet', label: 'Budget', value: campaignHasBudget.value ? (campaign.mode === 'new' ? `${money(campaign.budget)} ${campaign.budget_type === 'daily' ? 'per day' : 'lifetime'} (campaign)` : 'Campaign budget') : (adset.mode === 'existing' ? 'From ad set' : `${money(adset.budget)} ${adset.budget_type === 'daily' ? 'per day' : 'lifetime'}`) },
    { step: 1, icon: 'bx-calendar', label: 'Schedule', value: adset.mode === 'existing' ? 'From ad set' : `${adset.start_time ? fmtDate(adset.start_time) : 'Starts right away'} → ${adset.end_time ? fmtDate(adset.end_time) : 'No end date'}` },
    { step: 2, icon: 'bx-map', label: 'Locations', value: adset.mode === 'existing' ? 'From ad set' : (targeting.saved_audience_id ? 'Saved audience' : (locs || '-')) },
    { step: 2, icon: 'bx-group', label: 'Audience', value: adset.mode === 'existing' ? 'From ad set' : audienceSummary() },
    { step: 2, icon: 'bx-layout', label: 'Placements', value: adset.mode === 'existing' ? 'From ad set' : (placements.mode === 'advantage' ? 'Advantage+ placements' : placements.publisher_platforms.join(', ')) },
    { step: 1, icon: 'bx-line-chart', label: 'Tracking', value: adset.mode === 'existing' ? 'From ad set' : destRules.value && destRules.value.needs_pixel ? `Pixel ${selectedPixel.value ? selectedPixel.value.name : '-'} · ${adset.custom_event_type}` : (destRules.value && destRules.value.needs_app ? `App ${selectedApp.value ? selectedApp.value.name : '-'}` : 'Not needed for this goal') },
    { step: 3, icon: 'bx-image', label: 'Creative', value: creative.mode === 'existing' ? `${(lists.creatives.find((c) => c.id === creative.id) || {}).name || '-'} (existing)` : `${creative.format.toLowerCase()} · ${ctaLabel(creative.cta)}${creative.link ? ' · ' + creative.link : ''}` },
  ];
});

const blockers = computed(() => validate().flatMap((v) => v.errors.map((text) => ({ step: v.step, text }))));

// ---------------------------------------------------------------- helpers
function newKey() {
  return (window.crypto && window.crypto.randomUUID) ? window.crypto.randomUUID() : Date.now().toString(36) + Math.random().toString(36).slice(2) + Math.random().toString(36).slice(2);
}
function money(v) {
  if (v === null || v === undefined || v === '') return '-';
  try { return new Intl.NumberFormat(undefined, { style: 'currency', currency: boot.value ? boot.value.account.currency : 'USD', maximumFractionDigits: 2 }).format(v); } catch (e) { return v; }
}
function compact(n) {
  return n === null || n === undefined ? '-' : new Intl.NumberFormat(undefined, { notation: 'compact', maximumFractionDigits: 1 }).format(n);
}
function fmtDate(v) {
  return new Date(v).toLocaleString(undefined, { dateStyle: 'medium', timeStyle: 'short' });
}
function ctaLabel(c) { return CTA_LABELS[c] || (c || '').replace(/_/g, ' ').toLowerCase().replace(/^./, (x) => x.toUpperCase()); }
function optimizationLabel(o) { return OPT_LABELS[o] || o || '-'; }
function statusLabel(s) { return (s || '').replace(/_/g, ' ').toLowerCase().replace(/^./, (x) => x.toUpperCase()); }
function typeLabel(t) { return ({ interests: 'Interest', behaviors: 'Behavior', demographics: 'Demographic', life_events: 'Life event', industries: 'Industry', income: 'Income', family_statuses: 'Family', education_statuses: 'Education', relationship_statuses: 'Relationship' })[t] || t; }
function goalIcon(g) { return (goals.value[g] || {}).icon || 'bx-flag'; }
function objectiveLabel(c) { return c.goals.length ? c.goals.map((g) => goals.value[g].label).join(' / ') : `${c.objective} (not supported here)`; }
function destIcon(key) { return ({ WEBSITE: 'bx-globe', ON_AD: 'bx-id-card', MESSENGER: 'bxl-messenger', WHATSAPP: 'bxl-whatsapp', INSTAGRAM_DIRECT: 'bxl-instagram', APP: 'bx-mobile-alt', ON_POST: 'bx-like', NONE: 'bx-broadcast' })[key] || 'bx-right-arrow-alt'; }
function locationLabel(l) { return l.type === 'city' && l.radius ? `${l.name} +${l.radius} km` : (l.type === 'country' ? l.name : `${l.name}${l.country_code ? ', ' + l.country_code : ''}`); }
function stepTitle(s) { return ({ validation: 'Settings', campaign: 'Campaign', adset: 'Ad set', creative: 'Creative', ad: 'Ad' })[s] || s; }
function audienceSummary() {
  if (targeting.saved_audience_id) return 'Saved audience';
  const parts = [`${targeting.age_min}-${targeting.age_max === 65 ? '65+' : targeting.age_max}`, targeting.genders[0] === 1 ? 'men' : targeting.genders[0] === 2 ? 'women' : 'all genders'];
  if (targeting.interests.length) parts.push(`${targeting.interests.length} detailed targeting`);
  if (ui.includeAudiences.length) parts.push(`${ui.includeAudiences.length} custom audience(s)`);
  if (targeting.advantage_audience && !specialCategory.value) parts.push('Advantage+ audience');
  return parts.join(' · ');
}

async function api(url, params = {}, method = 'get') {
  try {
    const { data } = method === 'get' ? await window.axios.get(url, { params }) : await window.axios.post(url, params);
    if (!data.success) throw new Error(data.message || 'Meta returned an error.');
    return data.data;
  } catch (e) {
    throw new Error((e.response && e.response.data && (e.response.data.message || Object.values(e.response.data.errors || {}).flat()[0])) || e.message || 'Request failed.');
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
const searchTargeting = (q) => api(props.urls.targeting, { q });

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
    mediaError.value = errorMessage(e, 'Upload failed.');
  } finally {
    uploading.value = 0;
  }
}

// ---------------------------------------------------------------- validation
function validate() {
  const out = [];
  const add = (stepIndex, errors) => { if (errors.length) out.push({ step: stepIndex, errors }); };
  const c = [];
  if (!goal.value) c.push('Choose a goal.');
  if (!destination.value) c.push('Choose where people should go.');
  if (campaign.mode === 'existing' && !campaign.id) c.push('Choose an existing campaign.');
  if (campaign.mode === 'new' && !campaign.name) c.push('Name the campaign.');
  if (campaign.mode === 'new' && campaign.campaign_budget && !(campaign.budget > 0)) c.push('Enter the campaign budget.');
  add(0, c);

  const s = [];
  if (adset.mode === 'existing' && !adset.id) s.push('Choose an existing ad set.');
  if (adset.mode === 'new') {
    if (!adset.name) s.push('Name the ad set.');
    if (!campaignHasBudget.value && !(adset.budget > 0)) s.push('Enter a budget.');
    if (!campaignHasBudget.value && adset.budget_type === 'lifetime' && !adset.end_time) s.push('A lifetime budget needs an end date.');
    if (adset.end_time && adset.start_time && adset.end_time <= adset.start_time) s.push('The end must be after the start.');
    if (!campaignHasBudget.value && adset.bid_strategy !== 'LOWEST_COST_WITHOUT_CAP' && !(adset.bid_amount > 0)) s.push('Enter the bid or cost amount.');
    if (needsPage.value && !adset.page_id) s.push('Choose the Facebook Page.');
    if (destRules.value && destRules.value.needs_pixel && !adset.pixel_id) s.push('Choose the Meta Pixel.');
    if (destRules.value && destRules.value.needs_app && (!adset.application_id || !adset.object_store_url)) s.push('Choose the app and its store URL.');
  }
  add(1, s);

  const a = [];
  if (adset.mode === 'new' && !targeting.saved_audience_id && !targeting.locations.length) a.push('Add at least one location.');
  if (adset.mode === 'new' && placements.mode === 'manual' && !placements.publisher_platforms.length) a.push('Pick at least one placement platform.');
  add(2, a);

  const cr = [];
  if (creative.mode === 'existing' && !creative.id) cr.push('Choose an existing creative.');
  if (creative.mode === 'new') {
    if (!creative.page_id) cr.push('Choose the Page the ad runs from.');
    if (!creative.primary_text.trim()) cr.push('Write the primary text.');
    if (creative.format === 'CAROUSEL' && creative.cards.length < 2) cr.push('Add at least 2 carousel cards.');
    if (creative.format !== 'CAROUSEL' && !creative.media.length) cr.push(creative.format === 'VIDEO' ? 'Choose a video.' : 'Choose an image.');
    if (creative.format === 'VIDEO' && creative.media[0] && !creative.media[0].thumbnail_url && !creative.thumbnail) cr.push('Add a thumbnail for the video.');
    if (destRules.value && destRules.value.needs_link && !destRules.value.needs_app && !/^https?:\/\/\S+\.\S+/.test(creative.link)) cr.push('Add your website URL (https://…).');
    if (destRules.value && destRules.value.needs_lead_form && !creative.lead_form_id) cr.push('Choose the Instant form.');
  }
  add(3, cr);

  if (!ad.name) add(4, ['Name the ad.']);
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
      name: creative.name || `${ad.name || 'Creative'} - ${today}`,
      format: creative.format,
      page_id: creative.page_id,
      instagram_user_id: creative.instagram_user_id,
      primary_text: creative.primary_text,
      headline: creative.headline || null,
      description: creative.description || null,
      link: creative.link || null,
      cta: creative.cta,
      lead_form_id: creative.lead_form_id,
      media: creative.media.map((m) => ({ asset_id: m.id })),
      thumbnail_asset_id: creative.thumbnail ? creative.thumbnail.id : null,
      cards: creative.cards.map((c) => ({ asset_id: c.asset.id, headline: c.headline || null, description: c.description || null, link: c.link || null })),
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
      if (!estimate.data) estimate.error = 'Meta has no estimate for these settings yet.';
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
    if (!preview.html) preview.error = 'Meta returned no preview for this creative.';
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
      result.value = { success: false, ...data, message: data.message || (e.response ? 'Meta or the server returned an error. Please try again.' : "Couldn't reach the server - check your connection and try again.") };
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
const draftKey = computed(() => `fb-ad-builder:${props.accountId}`);
function snapshot() {
  return JSON.parse(JSON.stringify({ step: step.value, maxReached: maxReached.value, goal: goal.value, destination: destination.value, campaign, adset, targeting, placements, creative, ad, ui, key: idempotencyKey.value }));
}
let saveTimer = null;
function saveDraft() {
  clearTimeout(saveTimer);
  saveTimer = setTimeout(() => {
    try {
      localStorage.setItem(draftKey.value, JSON.stringify({ ...snapshot(), savedAt: new Date().toISOString() }));
      savedAt.value = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
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
  const draft = readDraft();
  if (draft) draftOffer.value = { savedAt: new Date(draft.savedAt).toLocaleString(undefined, { dateStyle: 'medium', timeStyle: 'short' }) };
});
</script>

<style scoped>
.fb { --ink: #161B2B; --text: #4B5263; --muted: #8A92A3; --line: #E7E9F0; --line-soft: #F1F3F7; --brand: #6D4AFF; --brand-2: #8F6BFF; --brand-soft: #F2EEFF; --ok: #12B76A; --warn: #B45309; --err: #B91C1C; color: var(--text); }

.fb-head { display: flex; justify-content: space-between; align-items: center; gap: 16px; flex-wrap: wrap; padding: 20px 22px; margin-bottom: 14px; border-radius: 18px; background: linear-gradient(135deg, #F6F3FF 0%, #EEF4FF 100%); border: 1px solid #E7E4F7; }
.fb-head-main { display: flex; align-items: center; gap: 14px; }
.fb-head-icon { width: 48px; height: 48px; border-radius: 14px; display: grid; place-items: center; font-size: 26px; color: #fff; background: linear-gradient(135deg, #0866FF, #6D4AFF); box-shadow: 0 8px 18px rgba(8, 102, 255, .22); }
.fb-head h4 { margin: 0; font-weight: 700; color: var(--ink); letter-spacing: -.01em; }
.fb-head p { margin: 2px 0 0; font-size: .88rem; color: #6b7385; }
.fb-head-actions { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
.fb-acct { display: inline-flex; align-items: center; gap: 6px; height: 36px; padding: 0 12px; border-radius: 999px; background: #fff; border: 1px solid #E7E4F7; font-size: .8rem; font-weight: 600; color: var(--ink); }
.fb-acct i { color: var(--brand); }

.fb-btn { display: inline-flex; align-items: center; justify-content: center; gap: 6px; height: 40px; padding: 0 16px; border-radius: 10px; border: 1px solid transparent; font-size: .85rem; font-weight: 600; text-decoration: none; cursor: pointer; white-space: nowrap; transition: transform .15s, box-shadow .15s, border-color .15s; }
.fb-btn-sm { height: 32px; padding: 0 11px; font-size: .8rem; }
.fb-btn-primary { background: linear-gradient(135deg, var(--brand), var(--brand-2)); color: #fff; box-shadow: 0 4px 12px rgba(109, 74, 255, .25); }
.fb-btn-primary:hover:not(:disabled) { color: #fff; transform: translateY(-1px); }
.fb-btn-ghost { background: #fff; border-color: var(--line); color: #3f4759; }
.fb-btn-ghost:hover:not(:disabled) { border-color: #CFC4FF; color: var(--brand); }
.fb-btn:disabled { opacity: .55; cursor: not-allowed; }
.fb-link { border: 0; background: none; padding: 0; color: var(--brand); font-weight: 600; font-size: .82rem; cursor: pointer; display: inline-flex; align-items: center; gap: 3px; }
.fb-icon-btn { border: 0; background: var(--line-soft); width: 32px; height: 32px; border-radius: 9px; color: var(--muted); display: grid; place-items: center; font-size: 17px; flex-shrink: 0; }
.fb-icon-btn:hover { background: #FEE2E2; color: var(--err); }

.fb-banner, .fb-alert { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; padding: 10px 14px; border-radius: 12px; margin-bottom: 14px; font-size: .86rem; }
.fb-banner { background: #F7F5FF; border: 1px solid #E2DAFF; color: var(--ink); }
.fb-banner > i { color: var(--brand); font-size: 20px; }
.fb-alert.is-warn { background: #FFFBEB; border: 1px solid #FDE68A; color: #92400E; }

.fb-card { background: #fff; border: 1px solid var(--line); border-radius: 16px; padding: 20px; box-shadow: 0 1px 2px rgba(16, 24, 40, .04); margin-bottom: 14px; }
.fb-card-h { display: flex; justify-content: space-between; align-items: center; gap: 12px; flex-wrap: wrap; margin-bottom: 12px; }
.fb-card-h h5 { margin: 0; font-weight: 700; color: var(--ink); }
.fb-sub { margin: -6px 0 14px; font-size: .85rem; color: var(--muted); }
.fb-loading, .fb-empty { text-align: center; padding: 48px 20px; }
.fb-loading { display: flex; align-items: center; justify-content: center; gap: 10px; color: var(--muted); }
.fb-empty-ic { width: 56px; height: 56px; margin: 0 auto 10px; border-radius: 16px; display: grid; place-items: center; font-size: 26px; background: var(--brand-soft); color: var(--brand); }
.fb-empty h5 { color: var(--ink); font-weight: 700; }

/* stepper */
.fb-steps { display: grid; grid-template-columns: repeat(5, minmax(0, 1fr)); gap: 6px; padding: 6px; margin-bottom: 14px; background: #fff; border: 1px solid var(--line); border-radius: 14px; position: sticky; top: 76px; z-index: 20; }
.fb-step { display: flex; align-items: center; gap: 10px; padding: 8px 10px; border: 0; border-radius: 10px; background: none; text-align: start; min-width: 0; cursor: pointer; }
.fb-step:disabled { cursor: not-allowed; opacity: .55; }
.fb-step:hover:not(:disabled) { background: #F8F7FF; }
.fb-step.is-current { background: var(--brand-soft); }
.fb-step-n { width: 28px; height: 28px; flex-shrink: 0; border-radius: 50%; display: grid; place-items: center; font-size: .8rem; font-weight: 700; border: 1.5px solid #D9DCE5; color: var(--muted); background: #fff; }
.fb-step.is-current .fb-step-n { border-color: var(--brand); background: var(--brand); color: #fff; }
.fb-step.is-done .fb-step-n { border-color: var(--ok); background: var(--ok); color: #fff; }
.fb-step.has-error .fb-step-n { border-color: var(--err); background: #FEF2F2; color: var(--err); }
.fb-step.is-skipped .fb-step-n { border-style: dashed; }
.fb-step-t { display: flex; flex-direction: column; min-width: 0; }
.fb-step-t strong { font-size: .84rem; color: var(--ink); }
.fb-step-t small { font-size: .72rem; color: var(--muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

.fb-layout { display: grid; grid-template-columns: minmax(0, 1fr) 340px; gap: 16px; align-items: start; }
.fb-side { position: sticky; top: 150px; }
.fb-side-h { display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; }
.fb-side-h strong { color: var(--ink); display: inline-flex; align-items: center; gap: 6px; font-size: .9rem; }
.fb-side-h small { color: var(--muted); font-size: .72rem; }

/* fields */
.fb-label { display: flex; align-items: baseline; gap: 6px; flex-wrap: wrap; font-size: .82rem; font-weight: 600; color: #374151; margin: 12px 0 6px; }
.fb-opt { font-weight: 500; color: var(--muted); font-size: .74rem; }
.fb-count { margin-left: auto; font-weight: 500; color: var(--muted); font-size: .72rem; font-variant-numeric: tabular-nums; }
.fb-tip { color: var(--muted); cursor: help; }
.fb :deep(.form-control) { border-radius: 10px; border-color: #E2E5ED; font-size: .88rem; }
.fb :deep(.form-control:focus) { border-color: #8F6BFF; box-shadow: 0 0 0 3px rgba(109, 74, 255, .12); }
.fb-grid-2 { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 0 14px; }
.fb-grid-3 { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 0 14px; align-items: start; }
.fb-span-2 { grid-column: span 2; align-self: end; }
.fb-hint { position: relative; display: block; margin: 8px 0 0; padding-left: 21px; font-size: .78rem; color: var(--muted); line-height: 1.45; }
.fb-hint > i:first-child { position: absolute; left: 0; top: 1px; font-size: 15px; color: #A3A9B8; }
.fb-hint.is-warn { color: #92400E; }
.fb-hint.is-warn i { color: #D97706; }
.fb-field-err { margin: 8px 0 0; font-size: .8rem; color: var(--err); font-weight: 500; }
.fb-muted { color: var(--muted); font-size: .78rem; }
.fb-inline-load { display: flex; align-items: center; gap: 8px; color: var(--muted); font-size: .82rem; margin: 10px 0; }
.fb-group { border-top: 1px solid var(--line-soft); margin-top: 16px; padding-top: 4px; }
.fb-group h6 { display: flex; align-items: center; gap: 6px; margin: 12px 0 4px; font-weight: 700; color: var(--ink); font-size: .9rem; }
.fb-group h6 i { color: var(--brand); }

.fb-seg { display: inline-flex; gap: 2px; padding: 3px; background: var(--line-soft); border-radius: 11px; flex-wrap: wrap; }
.fb-seg-full { display: flex; }
.fb-seg-full button { flex: 1; }
.fb-seg button { height: 34px; padding: 0 12px; border: 0; border-radius: 8px; background: none; font-size: .8rem; font-weight: 600; color: var(--text); display: inline-flex; align-items: center; justify-content: center; gap: 5px; }
.fb-seg button.on { background: #fff; color: var(--ink); box-shadow: 0 1px 3px rgba(16, 24, 40, .12); }
.fb-seg button:disabled { opacity: .45; cursor: not-allowed; }

.fb-goals { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 10px; }
.fb-goal { display: flex; flex-direction: column; align-items: flex-start; gap: 4px; padding: 14px; border: 1.5px solid var(--line); border-radius: 14px; background: #fff; text-align: start; transition: border-color .15s, box-shadow .15s, transform .15s; }
.fb-goal:hover { border-color: #CFC4FF; transform: translateY(-1px); }
.fb-goal.on { border-color: var(--brand); box-shadow: 0 0 0 3px rgba(109, 74, 255, .12); background: #FBFAFF; }
.fb-goal-ic { width: 36px; height: 36px; border-radius: 10px; display: grid; place-items: center; font-size: 19px; background: var(--brand-soft); color: var(--brand); margin-bottom: 4px; }
.fb-goal strong { font-size: .88rem; color: var(--ink); }
.fb-goal small { font-size: .76rem; color: var(--muted); line-height: 1.4; }
.fb-goal-mini { display: flex; gap: 8px; flex-wrap: wrap; margin-top: 8px; }
.fb-goal-mini button, .fb-dest { display: inline-flex; align-items: center; gap: 6px; height: 38px; padding: 0 14px; border: 1.5px solid var(--line); border-radius: 10px; background: #fff; font-size: .82rem; font-weight: 600; color: var(--text); }
.fb-goal-mini button.on, .fb-dest.on { border-color: var(--brand); color: #4F2FD6; background: #FBFAFF; }
.fb-dests { display: flex; gap: 8px; flex-wrap: wrap; }
.fb-guide { display: flex; gap: 8px; margin-top: 12px; padding: 10px 12px; border-radius: 12px; background: #F7F5FF; border: 1px solid #E8E1FF; font-size: .82rem; color: #3f3a5a; line-height: 1.5; }
.fb-guide i { color: var(--brand); font-size: 18px; flex-shrink: 0; }

.fb-toggle { display: flex; gap: 10px; align-items: flex-start; margin-top: 16px; padding: 12px; border: 1px solid var(--line); border-radius: 12px; cursor: pointer; }
.fb-toggle input { margin-top: 3px; accent-color: var(--brand); width: 16px; height: 16px; }
.fb-toggle span { display: flex; flex-direction: column; gap: 2px; }
.fb-toggle strong { font-size: .86rem; color: var(--ink); }
.fb-toggle small { font-size: .78rem; color: var(--muted); }
.fb-switch { display: inline-flex; align-items: center; gap: 6px; font-size: .82rem; font-weight: 600; color: var(--text); cursor: pointer; }
.fb-switch input { accent-color: var(--brand); width: 16px; height: 16px; }

.fb-search { position: relative; margin-bottom: 10px; }
.fb-search i { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: var(--muted); }
.fb-search input { width: 100%; height: 40px; border: 1px solid #E2E5ED; border-radius: 10px; padding: 0 12px 0 36px; font-size: .86rem; outline: none; }
.fb-search input:focus { border-color: #8F6BFF; box-shadow: 0 0 0 3px rgba(109, 74, 255, .12); }
.fb-pick-list { display: flex; flex-direction: column; gap: 8px; max-height: 420px; overflow-y: auto; padding: 2px; }
.fb-pick { display: flex; align-items: center; gap: 12px; padding: 12px; border: 1.5px solid var(--line); border-radius: 12px; background: #fff; text-align: start; }
.fb-pick:hover:not(:disabled) { border-color: #CFC4FF; }
.fb-pick.on { border-color: var(--brand); background: #FBFAFF; }
.fb-pick.is-off { opacity: .55; }
.fb-pick-ic { width: 36px; height: 36px; border-radius: 10px; display: grid; place-items: center; background: var(--brand-soft); color: var(--brand); font-size: 18px; flex-shrink: 0; }
.fb-pick-main { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 2px; }
.fb-pick-main strong { color: var(--ink); font-size: .88rem; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.fb-pick-main small { color: var(--muted); font-size: .76rem; }
.fb-pick-aud { display: inline-flex; align-items: center; gap: 4px; }
.fb-pick-check { color: var(--brand); font-size: 22px; }
.fb-mini-empty { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; padding: 14px; border: 1px dashed #D9DCE5; border-radius: 12px; color: var(--muted); font-size: .84rem; }
.fb-mini-empty > i { font-size: 20px; }
.fb-mini-empty.is-info { background: #F8FAFC; border-style: solid; border-color: var(--line); }

.fb-tag { flex-shrink: 0; padding: 1px 8px; border-radius: 999px; background: var(--line-soft); color: var(--muted); font-size: .7rem; font-weight: 600; text-transform: capitalize; }
.fb-radius { display: flex; align-items: center; justify-content: space-between; gap: 10px; margin-top: 8px; padding: 6px 10px; background: #FAFAFD; border: 1px solid var(--line-soft); border-radius: 10px; font-size: .82rem; }
.fb-radius select { width: 130px; }
.fb-age { display: flex; align-items: center; gap: 8px; }
.fb-age span { color: var(--muted); font-size: .8rem; }
.fb-narrow { margin-top: 10px; padding: 10px; border-radius: 12px; background: #FAFAFD; border: 1px solid var(--line-soft); }
.fb-narrow-h { display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px; font-size: .8rem; color: var(--ink); }
.fb-place-grid { display: flex; flex-wrap: wrap; gap: 8px 14px; margin-top: 10px; }
.fb-check { display: inline-flex; align-items: center; gap: 6px; font-size: .84rem; color: var(--ink); cursor: pointer; }
.fb-check input { accent-color: var(--brand); }
.fb-check.is-sm { font-size: .8rem; color: var(--text); }
.fb-positions { width: 100%; display: flex; flex-wrap: wrap; gap: 6px 14px; align-items: center; padding: 8px 10px; background: #FAFAFD; border-radius: 10px; }
.fb-positions small { width: 100%; color: var(--muted); font-weight: 600; font-size: .72rem; text-transform: uppercase; letter-spacing: .04em; }

/* media */
.fb-media-row { margin-top: 8px; }
.fb-media-actions { display: flex; gap: 8px; flex-wrap: wrap; }
.fb-media { display: flex; align-items: center; gap: 12px; padding: 10px; border: 1px solid var(--line); border-radius: 12px; }
.fb-media img, .fb-media-ph { width: 64px; height: 64px; border-radius: 10px; object-fit: cover; flex-shrink: 0; background: #0f1016; display: grid; place-items: center; color: #fff; font-size: 24px; }
.fb-media.is-sm img { width: 44px; height: 44px; }
.fb-media > div { flex: 1; min-width: 0; display: flex; flex-direction: column; }
.fb-media strong { color: var(--ink); font-size: .84rem; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.fb-media small { color: var(--muted); font-size: .76rem; }
.fb-cards { display: grid; grid-template-columns: repeat(auto-fill, minmax(170px, 1fr)); gap: 10px; margin-top: 8px; }
.fb-ccard { position: relative; display: flex; flex-direction: column; gap: 6px; padding: 8px; border: 1px solid var(--line); border-radius: 12px; }
.fb-ccard img { width: 100%; aspect-ratio: 1; object-fit: cover; border-radius: 8px; }
.fb-ccard .fb-icon-btn { position: absolute; top: 12px; right: 12px; width: 26px; height: 26px; background: rgba(255, 255, 255, .92); }
.fb-ccard.is-add { align-items: center; justify-content: center; gap: 8px; border-style: dashed; min-height: 170px; }
.fb-creative-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 10px; max-height: 460px; overflow-y: auto; padding: 2px; }
.fb-creative { display: flex; flex-direction: column; gap: 4px; padding: 8px; border: 1.5px solid var(--line); border-radius: 12px; background: #fff; text-align: start; }
.fb-creative.on { border-color: var(--brand); box-shadow: 0 0 0 3px rgba(109, 74, 255, .12); }
.fb-creative-img { aspect-ratio: 1; border-radius: 8px; background: var(--line-soft); display: grid; place-items: center; overflow: hidden; color: var(--muted); font-size: 24px; }
.fb-creative-img img { width: 100%; height: 100%; object-fit: cover; }
.fb-creative strong { font-size: .8rem; color: var(--ink); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.fb-creative small { font-size: .72rem; color: var(--muted); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

/* review */
.fb-review { margin-top: 16px; border: 1px solid var(--line); border-radius: 12px; overflow: hidden; }
.fb-review-row { display: grid; grid-template-columns: 150px minmax(0, 1fr) auto; gap: 12px; align-items: center; padding: 10px 14px; border-bottom: 1px solid var(--line-soft); font-size: .84rem; }
.fb-review-row:last-child { border-bottom: 0; }
.fb-review-l { color: var(--muted); display: inline-flex; align-items: center; gap: 6px; }
.fb-review-l i { color: var(--brand); }
.fb-review-v { color: var(--ink); font-weight: 500; word-break: break-word; }
.fb-blockers { margin-top: 14px; padding: 12px 14px; border-radius: 12px; background: #FEF2F2; border: 1px solid #FECACA; color: var(--err); font-size: .84rem; }
.fb-blockers ul { margin: 6px 0 0; padding-left: 18px; }
.fb-blockers .fb-link { color: var(--err); text-decoration: underline; font-weight: 500; }
.fb-result { display: flex; gap: 12px; margin-top: 14px; padding: 14px; border-radius: 12px; font-size: .86rem; }
.fb-result > i { font-size: 26px; }
.fb-result p { margin: 4px 0 8px; }
.fb-result.is-ok { background: #ECFDF5; border: 1px solid #A7F3D0; color: #065F46; }
.fb-result.is-err { background: #FEF2F2; border: 1px solid #FECACA; color: #7F1D1D; }
.fb-result .fb-btn { margin-right: 6px; }
.fb-created { list-style: none; padding: 0; margin: 4px 0 8px; font-size: .8rem; }
.fb-created i { color: var(--ok); }

.fb-foot { display: flex; justify-content: space-between; align-items: center; gap: 10px; position: sticky; bottom: 12px; z-index: 15; padding: 10px 12px; background: rgba(255, 255, 255, .95); backdrop-filter: blur(8px); border: 1px solid var(--line); border-radius: 14px; box-shadow: 0 10px 30px rgba(16, 24, 40, .1); }
.fb-saved { font-size: .76rem; color: var(--muted); display: inline-flex; align-items: center; gap: 5px; }
.fb-saved.on i { color: var(--ok); }

/* side: estimate + preview */
.fb-est-num { font-size: 1.5rem; font-weight: 700; color: var(--ink); letter-spacing: -.01em; font-variant-numeric: tabular-nums; }
.fb-mock { border: 1px solid var(--line); border-radius: 12px; overflow: hidden; background: #fff; }
.fb-mock-h { display: flex; align-items: center; gap: 8px; padding: 10px; }
.fb-mock-av { width: 34px; height: 34px; border-radius: 50%; overflow: hidden; background: #0866FF; color: #fff; display: grid; place-items: center; flex-shrink: 0; }
.fb-mock-av img { width: 100%; height: 100%; object-fit: cover; }
.fb-mock-h strong { display: block; font-size: .82rem; color: var(--ink); }
.fb-mock-h small { font-size: .7rem; color: var(--muted); }
.fb-mock-text { margin: 0; padding: 0 10px 8px; font-size: .8rem; color: var(--ink); white-space: pre-wrap; word-break: break-word; display: -webkit-box; -webkit-line-clamp: 4; -webkit-box-orient: vertical; overflow: hidden; }
.fb-mock-media { position: relative; aspect-ratio: 1; background: #F1F3F7; display: grid; place-items: center; color: #B8BECB; font-size: 34px; }
.fb-mock-media img { width: 100%; height: 100%; object-fit: cover; }
.fb-mock-play { position: absolute; width: 48px; height: 48px; border-radius: 50%; background: rgba(0, 0, 0, .55); color: #fff; display: grid; place-items: center; font-size: 26px; }
.fb-mock-foot { display: flex; align-items: center; justify-content: space-between; gap: 8px; padding: 8px 10px; background: #F7F8FA; }
.fb-mock-foot div { min-width: 0; display: flex; flex-direction: column; }
.fb-mock-foot small { font-size: .66rem; color: var(--muted); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.fb-mock-foot strong { font-size: .8rem; color: var(--ink); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.fb-mock-cta { flex-shrink: 0; padding: 6px 10px; border-radius: 6px; background: #E4E6EB; color: #050505; font-size: .74rem; font-weight: 600; }
.fb-mock-carousel { display: flex; gap: 6px; padding: 0 10px 10px; overflow: hidden; }
.fb-mock-card { width: 70%; flex-shrink: 0; border: 1px solid var(--line); border-radius: 8px; overflow: hidden; }
.fb-mock-card img { width: 100%; aspect-ratio: 1; object-fit: cover; display: block; }
.fb-mock-card strong { display: block; padding: 6px 8px; font-size: .74rem; color: var(--ink); }
.fb-meta-preview :deep(iframe) { width: 100% !important; min-height: 520px; border: 0; }

@media (max-width: 1199.98px) {
  .fb-layout { grid-template-columns: 1fr; }
  .fb-side { position: static; }
  .fb-steps { grid-template-columns: repeat(5, minmax(56px, 1fr)); }
  .fb-step-t small { display: none; }
}
@media (max-width: 767.98px) {
  .fb-grid-2, .fb-grid-3 { grid-template-columns: 1fr; }
  .fb-span-2 { grid-column: auto; }
  .fb-steps { top: 64px; }
  .fb-step { justify-content: center; padding: 8px 4px; }
  .fb-step-t { display: none; }
  .fb-review-row { grid-template-columns: 1fr auto; }
  .fb-review-l { grid-column: 1 / -1; }
  .fb-foot .fb-saved { display: none; }
}
</style>
