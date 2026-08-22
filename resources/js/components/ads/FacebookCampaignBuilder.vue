<template>
    <div class="builder-main">
        <header class="builder-topbar">
            <div>
                <span class="builder-eyebrow"><i class="bx bxl-facebook-circle"></i> Facebook Campaign</span>
                <h1>Create New Campaign</h1>
                <p class="subtitle">Build your campaign in 3 simple steps</p>
            </div>
            <div class="topbar-actions">
                <button type="button" class="btn btn-outline" @click="notImplemented('Save as Draft')"><i class="bx bx-folder"></i> Save as Draft</button>
                <a class="icon-close" :href="routes.campaignsIndex" title="Close"><i class="bx bx-x"></i></a>
            </div>
        </header>

        <div class="step-tracker">
            <div class="step-node" @click="scrollToPanel('panel-campaign')">
                <div class="step-circle">1</div>
                <div class="step-label"><strong>Campaign</strong><span>Choose your objective</span></div>
            </div>
            <div class="step-connector"></div>
            <div class="step-node" @click="scrollToPanel('panel-adset')">
                <div class="step-circle">2</div>
                <div class="step-label"><strong>Ad Set</strong><span>Define your audience and budget</span></div>
            </div>
            <div class="step-connector"></div>
            <div class="step-node" @click="scrollToPanel('panel-ad')">
                <div class="step-circle">3</div>
                <div class="step-label"><strong>Ad</strong><span>Create your ad and preview</span></div>
            </div>
        </div>

        <template v-if="!hasAdAccount">
            <div class="no-account-banner">
                <i class="bx bx-error"></i>
                No connected Facebook Ad Account was found for your account. You can still explore this builder, but publishing will fail until a Facebook Ads account is connected from the Ads dashboard.
            </div>
        </template>

        <div class="builder-grid">
            <!-- Panel 1: Campaign -->
            <section class="panel" id="panel-campaign">
                <div class="panel-head">
                    <div class="panel-num">1</div>
                    <div class="panel-title">Campaign</div>
                </div>

                <div class="mode-tabs">
                    <div class="mode-tab" :class="{active: campaign_mode === 'new'}" @click="setCampaignMode('new')">
                        <i class="bx bx-plus-circle"></i> New Campaign
                    </div>
                    <div class="mode-tab" :class="{active: campaign_mode === 'existing'}" @click="setCampaignMode('existing')">
                        <i class="bx bx-collection"></i> Existing Campaign
                    </div>
                </div>

                <div class="field-block" v-if="campaign_mode === 'new'">
                    <div class="field-label">Choose your objective <i class="bx bx-info-circle"></i></div>
                    <div class="objective-card" v-for="obj in objectives" :key="obj.key" :class="{selected: objective === obj.key}" @click="selectObjective(obj.key)">
                        <div class="objective-icon"><i :class="obj.icon"></i></div>
                        <div class="objective-text">
                            <strong>{{ obj.label }}</strong>
                            <span>{{ obj.description }}</span>
                        </div>
                        <div class="objective-radio"></div>
                    </div>
                </div>

                <div class="field-block" v-else>
                    <div class="field-label">Select existing campaign</div>
                    <select class="field-input" v-model.number="existing_campaign_id">
                        <option value="" disabled>Choose a campaign</option>
                        <option v-for="c in existingCampaigns" :key="c.id" :value="c.id">{{ c.name }}<template v-if="c.objective"> &middot; {{ objectiveShort(c.objective) }}</template></option>
                    </select>
                    <p class="field-hint" v-if="!existingCampaigns.length" style="margin-top:8px">You have no campaigns yet — create a new one first.</p>
                    <div class="error-text">{{ errors.existing_campaign_id }}</div>
                </div>

                <div class="map-illustration">
                    <svg viewBox="0 0 300 130" preserveAspectRatio="none">
                        <path d="M20,90 Q90,20 160,60 T280,40" fill="none" stroke="#1877f2" stroke-width="2" stroke-dasharray="5,6" opacity=".55" />
                        <circle cx="20" cy="90" r="6" fill="#1877f2" />
                        <circle cx="280" cy="40" r="6" fill="#42a5f5" />
                    </svg>
                    <div class="map-card"><div class="bar"></div><div class="bar"></div></div>
                </div>
            </section>

            <!-- Panel 2: Ad Set -->
            <section class="panel" id="panel-adset">
                <div class="panel-head">
                    <div class="panel-num">2</div>
                    <div class="panel-title">Ad Set</div>
                </div>

                <div class="mode-tabs">
                    <div class="mode-tab" :class="{active: adset_mode === 'new'}" @click="setAdsetMode('new')">
                        <i class="bx bx-plus-circle"></i> New Ad Set
                    </div>
                    <div class="mode-tab" :class="{active: adset_mode === 'existing', disabled: campaign_mode !== 'existing'}" :title="campaign_mode !== 'existing' ? 'Pick an existing campaign first to reuse its ad sets' : ''" @click="setAdsetMode('existing')">
                        <i class="bx bx-collection"></i> Existing Ad Set
                    </div>
                </div>
                <p class="field-hint" v-if="campaign_mode !== 'existing'" style="margin-top:-6px">Choose an existing campaign to reuse one of its ad sets.</p>

                <div class="field-block" v-if="adset_mode === 'existing'">
                    <div class="field-label">Select existing ad set</div>
                    <select class="field-input" v-model.number="existing_adset_id">
                        <option value="" disabled>Choose an ad set</option>
                        <option v-for="a in filteredAdsets" :key="a.id" :value="a.id">{{ a.name }}</option>
                    </select>
                    <p class="field-hint" v-if="!filteredAdsets.length" style="margin-top:8px">This campaign has no reusable ad sets — create a new one.</p>
                    <div class="error-text">{{ errors.existing_adset_id }}</div>
                </div>

                <template v-if="adset_mode === 'new'">
                <p class="section-title">Audience</p>
                <p class="section-sub">Define who you want to see your ads.</p>

                <div class="field-block dropdown-wrap">
                    <div class="field-label">Locations</div>
                    <div class="field-input dropdown-trigger" @click="showCountryDropdown = !showCountryDropdown">
                        <span>{{ countriesLabel }}</span>
                        <i class="bx bx-chevron-down"></i>
                    </div>
                    <div class="dropdown-panel" v-if="showCountryDropdown" @click.stop>
                        <label class="dropdown-option" v-for="c in countries" :key="c.id">
                            <input type="checkbox" :value="c.id" v-model="countries_selected"> {{ c.name }}
                        </label>
                    </div>
                    <div class="error-text">{{ errors.countries }}</div>
                </div>

                <div class="field-block">
                    <div class="field-label">Age</div>
                    <div class="field-row">
                        <select class="field-input" v-model.number="age_from">
                            <option v-for="a in ageOptions" :key="'f'+a" :value="a">{{ a }}</option>
                        </select>
                        <select class="field-input" v-model.number="age_to">
                            <option v-for="a in ageOptions" :key="'t'+a" :value="a">{{ a === 65 ? '65+' : a }}</option>
                        </select>
                    </div>
                </div>

                <div class="field-block">
                    <div class="field-label">Gender</div>
                    <div class="pill-group">
                        <div class="pill" :class="{active: gender === 'all'}" @click="gender = 'all'">All</div>
                        <div class="pill" :class="{active: gender === 'male'}" @click="gender = 'male'">Men</div>
                        <div class="pill" :class="{active: gender === 'female'}" @click="gender = 'female'">Women</div>
                    </div>
                </div>

                <div class="field-block">
                    <div class="field-label">Detailed Targeting</div>
                    <div class="chip-box">
                        <span class="chip" v-for="(chip, i) in detailedTargetingChips" :key="chip">
                            {{ chip }} <button type="button" @click="detailedTargetingChips.splice(i,1)">&times;</button>
                        </span>
                        <input type="text" placeholder="Add demographics, interests or behaviors" v-model="chipInput" @keydown.enter.prevent="addChip" @keydown="chipKeydown">
                    </div>
                </div>

                <p class="section-title" style="margin-top: 22px;">Budget &amp; Schedule</p>

                <div class="field-block">
                    <div class="field-label">Daily Budget</div>
                    <div class="input-suffix">
                        <input type="number" min="1" step="0.01" class="field-input" v-model.number="budget">
                        <span>{{ currency }}</span>
                    </div>
                </div>

                <div class="field-block">
                    <div class="field-label">Schedule</div>
                    <div class="field-row">
                        <input type="date" class="field-input" v-model="start_time">
                        <input type="date" class="field-input" v-model="end_time">
                    </div>
                    <div class="error-text">{{ errors.start_time || errors.end_time }}</div>
                </div>

                <div class="vat-box">
                    <div class="vat-row"><span>Budget</span><span>{{ currency }} {{ allocatedBudget.toFixed(2) }}</span></div>
                    <div class="vat-row"><span>VAT (15%)</span><span>{{ currency }} {{ vatAmount.toFixed(2) }}</span></div>
                    <div class="vat-row total"><span>Total Budget</span><span>{{ currency }} {{ totalBudget.toFixed(2) }}</span></div>
                </div>

                <div class="show-more" :class="{open: showMore}" @click="showMore = !showMore">
                    Show More Options <i class="bx bx-chevron-down"></i>
                </div>
                <div class="more-options" :class="{open: showMore}">
                    <div class="field-block">
                        <div class="field-label">Budget Type</div>
                        <div class="pill-group">
                            <div class="pill" :class="{active: budget_mode === 'daily_budget'}" @click="budget_mode = 'daily_budget'">Daily</div>
                            <div class="pill" :class="{active: budget_mode === 'lifetime_budget'}" @click="budget_mode = 'lifetime_budget'">Lifetime</div>
                        </div>
                    </div>
                    <div class="field-block">
                        <div class="field-label">Optimization Goal</div>
                        <select class="field-input" v-model="optimization_goal">
                            <option v-for="g in optimizationGoalOptions" :key="g" :value="g">{{ g }}</option>
                        </select>
                    </div>
                    <div class="field-block">
                        <div class="field-label">Call To Action</div>
                        <select class="field-input" v-model="call_to_action">
                            <option v-for="c in ctaOptions" :key="c" :value="c">{{ c }}</option>
                        </select>
                    </div>
                    <div class="field-block">
                        <div class="field-label">Languages</div>
                        <div class="pill-group">
                            <div class="pill" :class="{active: languages.includes('english')}" @click="toggleLanguage('english')">English</div>
                            <div class="pill" :class="{active: languages.includes('arabic')}" @click="toggleLanguage('arabic')">Arabic</div>
                        </div>
                    </div>
                    <div class="field-block">
                        <div class="field-label">Bid Amount <span style="font-weight:400;color:var(--text-2)">(0 = automatic)</span></div>
                        <input type="number" min="0" step="0.01" class="field-input" v-model.number="bid_amount">
                    </div>
                    <template v-if="optimization_goal === 'OFFSITE_CONVERSIONS'">
                        <div class="field-block">
                            <div class="field-label">Pixel ID</div>
                            <input type="text" class="field-input" v-model="pixel_id" placeholder="Meta Pixel ID">
                        </div>
                        <div class="field-block">
                            <div class="field-label">Custom Event Type</div>
                            <input type="text" class="field-input" v-model="custom_event_type" placeholder="e.g. PURCHASE">
                        </div>
                    </template>
                    <template v-if="objective === 'OUTCOME_APP_PROMOTION'">
                        <div class="field-block">
                            <div class="field-label">App Store URL</div>
                            <input type="url" class="field-input" v-model="object_store_url">
                        </div>
                        <div class="field-block">
                            <div class="field-label">Application ID</div>
                            <input type="text" class="field-input" v-model="application_id">
                        </div>
                    </template>
                </div>
                </template>
            </section>

            <!-- Panel 3: Ad / Creative -->
            <section class="panel" id="panel-ad">
                <div class="panel-head">
                    <div class="panel-num">3</div>
                    <div class="panel-title">Ad / Creative</div>
                </div>

                <div class="field-block">
                    <div class="field-label">Ad Name</div>
                    <input type="text" class="field-input" v-model="name" :placeholder="defaultName">
                </div>

                <p class="section-title">Identity</p>

                <div class="field-block">
                    <div class="field-label">Facebook Page</div>
                    <div class="page-select" :class="{disabled: !pages.length}">
                        <img class="page-avatar" :src="selectedPage ? selectedPage.picture : ''" onerror="this.style.visibility='hidden'" v-if="selectedPage && selectedPage.picture">
                        <i class="bx bxl-facebook-square" style="color:#1877f2;font-size:20px" v-else></i>
                        <select v-model="page_id" :disabled="!pages.length">
                            <option v-if="!pages.length" value="">No connected Pages found</option>
                            <option v-for="p in pages" :key="p.id" :value="p.id">{{ p.name }}</option>
                        </select>
                    </div>
                    <div class="error-text">{{ errors.page_id }}</div>
                </div>

                <div class="field-block">
                    <div class="field-label">Instagram Account</div>
                    <div class="page-select" :class="{disabled: !instagram_account}">
                        <i class="bx bxl-instagram" style="color:#d6249f;font-size:20px"></i>
                        <template v-if="instagram_account">
                            <span style="flex:1;font-size:.85rem;">{{ instagram_account.username ? '@' + instagram_account.username : instagram_account.name }}</span>
                            <div class="switch" :class="{off: !instagram_enabled}" @click="instagram_enabled = !instagram_enabled" style="margin-left:auto"><div class="thumb"></div></div>
                        </template>
                        <span v-else style="flex:1;font-size:.82rem;color:var(--text-2)">Not connected - connect Instagram to enable this placement</span>
                    </div>
                </div>

                <p class="section-title" style="margin-top: 22px;">Ad Setup</p>
                <div class="field-block">
                    <div class="field-label">Format</div>
                    <div class="pill-group">
                        <div class="pill" :class="{active: media_type === 'IMAGE'}" @click="setFormat('IMAGE')">Single Image</div>
                        <div class="pill" :class="{active: media_type === 'VIDEO'}" @click="setFormat('VIDEO')">Video</div>
                        <div class="pill" :class="{active: media_type === 'CAROUSEL'}" @click="setFormat('CAROUSEL')">Carousel</div>
                        <div class="pill disabled" title="Not yet supported by this ad module">Collection</div>
                    </div>
                </div>

                <div class="field-block">
                    <div class="field-label">Media</div>
                    <div class="media-box" v-if="media_type !== 'CAROUSEL'">
                        <div class="media-thumb">
                            <img v-if="media_type === 'IMAGE' && mediaPreviews[0]" :src="mediaPreviews[0]">
                            <video v-else-if="media_type === 'VIDEO' && mediaPreviews[0]" :src="mediaPreviews[0]" muted></video>
                            <i class="bx bx-image" v-else></i>
                        </div>
                        <div>
                            <button type="button" class="btn btn-outline" @click="$refs.mediaInput.click()"><i class="bx bx-upload"></i> {{ mediaFiles.length ? 'Change ' + (media_type === 'VIDEO' ? 'Video' : 'Image') : 'Upload ' + (media_type === 'VIDEO' ? 'Video' : 'Image') }}</button>
                            <p class="field-hint" style="margin:8px 0 0">{{ media_type === 'VIDEO' ? 'Recommended: MP4/MOV, up to 500MB' : 'Recommended: 1080 x 1080 px, up to 30MB' }}</p>
                        </div>
                    </div>
                    <div class="media-box" v-else>
                        <div class="carousel-strip">
                            <div class="media-thumb" v-for="(src, i) in mediaPreviews" :key="i"><img :src="src"></div>
                            <div class="media-thumb" style="cursor:pointer" @click="$refs.mediaInput.click()"><i class="bx bx-plus"></i></div>
                        </div>
                    </div>
                    <input type="file" ref="mediaInput" hidden :multiple="media_type === 'CAROUSEL'" :accept="media_type === 'VIDEO' ? 'video/mp4,video/quicktime' : 'image/jpeg,image/png,image/gif,image/bmp'" @change="onMediaChange">
                    <div class="error-text">{{ errors.media }}</div>

                    <template v-if="media_type === 'VIDEO'">
                        <div class="field-label" style="margin-top:12px">Thumbnail</div>
                        <button type="button" class="btn btn-outline" @click="$refs.thumbInput.click()"><i class="bx bx-image"></i> {{ thumbnailFile ? 'Change Thumbnail' : 'Upload Thumbnail' }}</button>
                        <input type="file" ref="thumbInput" hidden accept="image/*" @change="onThumbnailChange">
                    </template>
                </div>

                <div class="field-block">
                    <div class="field-label">Primary Text <i class="bx bx-smile"></i></div>
                    <textarea class="field-input" rows="3" v-model="primary_text" placeholder="Tell people what you're promoting..."></textarea>
                </div>

                <div class="field-block">
                    <div class="field-label">Headline</div>
                    <input type="text" class="field-input" v-model="headline" placeholder="e.g. New Collection">
                </div>

                <div class="field-block">
                    <div class="field-label">Description <span style="font-weight:400;color:var(--text-2)">- Optional</span></div>
                    <input type="text" class="field-input" v-model="description" placeholder="e.g. Shop now and get special offer!">
                </div>

                <div class="field-block">
                    <div class="field-label">Website URL</div>
                    <input type="url" class="field-input" v-model="target_link" placeholder="https://yourwebsite.com">
                    <div class="error-text">{{ errors.target_link }}</div>
                </div>
            </section>

            <!-- Ad Preview -->
            <aside class="panel preview-panel">
                <div class="preview-head">
                    <div class="panel-title" style="font-size:1.02rem">Ad Preview</div>
                    <div class="switch" :class="{off: !previewEnabled}" @click="previewEnabled = !previewEnabled"><div class="thumb"></div></div>
                </div>

                <template v-if="previewEnabled">
                    <div class="platform-tabs">
                        <div class="platform-tab" :class="{active: previewTab === 'facebook'}" @click="previewTab = 'facebook'"><i class="bx bxl-facebook-square"></i></div>
                        <div class="platform-tab" :class="{active: previewTab === 'instagram'}" @click="previewTab = 'instagram'"><i class="bx bxl-instagram"></i></div>
                        <div class="platform-tab" :class="{active: previewTab === 'messenger'}" @click="previewTab = 'messenger'"><i class="bx bxl-messenger"></i></div>
                        <div class="platform-tab" :class="{active: previewTab === 'audience_network'}" @click="previewTab = 'audience_network'"><i class="bx bx-globe"></i></div>
                    </div>

                    <div class="field-hint" style="margin-top:-8px">{{ previewSurfaceLabel }}</div>

                    <div class="fb-card">
                        <div class="fb-card-head">
                            <div class="fb-avatar"></div>
                            <div>
                                <div class="fb-name">{{ selectedPage ? selectedPage.name : 'Your Page' }}</div>
                                <div class="fb-sponsored">Sponsored &middot; <i class="bx bx-globe"></i></div>
                            </div>
                            <i class="bx bx-dots-horizontal-rounded fb-dots"></i>
                        </div>
                        <div class="fb-body-text">{{ primary_text || 'Your primary text will appear here.' }}</div>
                        <div class="fb-media">
                            <img v-if="media_type !== 'VIDEO' && mediaPreviews[0]" :src="mediaPreviews[0]">
                            <video v-else-if="media_type === 'VIDEO' && mediaPreviews[0]" :src="mediaPreviews[0]" muted></video>
                            <i class="bx bx-image-alt" v-else></i>
                        </div>
                        <div class="fb-link-bar">
                            <div>
                                <div class="fb-link-domain">{{ linkDomain }}</div>
                                <div class="fb-link-headline">{{ headline || 'Your headline' }}</div>
                                <div class="fb-link-desc" v-if="description">{{ description }}</div>
                            </div>
                            <button class="fb-cta-btn">{{ ctaLabel }}</button>
                        </div>
                    </div>

                    <div class="audience-box">
                        <svg width="46" height="26" viewBox="0 0 46 26">
                            <path d="M3,23 A20,20 0 0,1 43,23" fill="none" stroke="#e7f0fb" stroke-width="5" />
                            <path d="M3,23 A20,20 0 0,1 43,23" fill="none" stroke="url(#g)" stroke-width="5" stroke-dasharray="63" :stroke-dashoffset="63 - (audienceScore * 63)" stroke-linecap="round" />
                            <defs><linearGradient id="g"><stop offset="0%" stop-color="#dc2626"/><stop offset="50%" stop-color="#f5a623"/><stop offset="100%" stop-color="#16a34a"/></linearGradient></defs>
                        </svg>
                        <div class="audience-text">
                            <strong>{{ audienceLabel }}</strong>
                            <span>Potential Reach: {{ potentialReach.toLocaleString() }} people</span>
                        </div>
                    </div>

                    <div class="estimate-card">
                        <div class="estimate-title">Estimated Results</div>
                        <div class="estimate-row"><span>Reach</span><span>{{ reachRange }}</span></div>
                        <div class="estimate-bar"><div class="estimate-bar-fill" style="width:70%"></div></div>
                        <div class="estimate-row"><span>Link Clicks</span><span>{{ clicksRange }}</span></div>
                        <div class="estimate-bar"><div class="estimate-bar-fill" style="width:42%"></div></div>
                        <p class="field-hint" style="margin:0">Illustrative estimate for this preview only, not a live delivery forecast from Meta.</p>
                    </div>
                </template>
            </aside>

            <div class="review-panel" v-if="showReview">
                <p class="section-title">Review</p>
                <dl>
                    <dt>Objective</dt><dd>{{ objectiveLabel }}</dd>
                    <dt>Campaign / Ad Name</dt><dd>{{ name || defaultName }}</dd>
                    <dt>Locations</dt><dd>{{ countriesLabel }}</dd>
                    <dt>Age / Gender</dt><dd>{{ age_from }} - {{ age_to === 65 ? '65+' : age_to }}, {{ gender }}</dd>
                    <dt>Daily Budget</dt><dd>{{ currency }} {{ budget }} ({{ currency }} {{ totalBudget.toFixed(2) }} incl. VAT)</dd>
                    <dt>Schedule</dt><dd>{{ start_time }} to {{ end_time }}</dd>
                    <dt>Placement</dt><dd>{{ selectedPage ? selectedPage.name : 'No Page selected' }}<template v-if="instagram_account && instagram_enabled"> + Instagram</template></dd>
                    <dt>Format</dt><dd>{{ media_type }}</dd>
                </dl>
            </div>
        </div>

        <footer class="builder-footer">
            <a class="btn btn-outline" :href="routes.campaignsIndex"><i class="bx bx-arrow-back"></i> Back</a>
            <div style="display:flex;gap:12px">
                <button type="button" class="btn btn-outline" @click="showReview = !showReview">{{ showReview ? 'Hide Review' : 'Review' }}</button>
                <button type="button" class="btn btn-primary" :disabled="submitting || !hasAdAccount" @click="submit">
                    <i class="bx bx-loader-alt bx-spin" v-if="submitting"></i> {{ submitting ? 'Publishing...' : 'Publish' }}
                </button>
            </div>
        </footer>
    </div>
</template>

<script>
const objectives = [
    { key: 'OUTCOME_TRAFFIC', label: 'Traffic', icon: 'bx bx-pointer', description: 'Send people to a destination, like your website.', optimization_goal: 'LINK_CLICKS', call_to_action: 'LEARN_MORE' },
    { key: 'OUTCOME_ENGAGEMENT', label: 'Engagement', icon: 'bx bx-heart', description: 'Get more messages, video views, post engagements.', optimization_goal: 'POST_ENGAGEMENT', call_to_action: 'MESSAGE_PAGE' },
    { key: 'OUTCOME_LEADS', label: 'Leads', icon: 'bx bx-user-plus', description: 'Collect leads for your business.', optimization_goal: 'LEAD_GENERATION', call_to_action: 'SIGN_UP' },
    { key: 'OUTCOME_APP_PROMOTION', label: 'App Promotion', icon: 'bx bx-mobile-alt', description: 'Get more installs, engagement and pre-registration.', optimization_goal: 'APP_INSTALLS', call_to_action: 'DOWNLOAD' },
    { key: 'OUTCOME_SALES', label: 'Sales', icon: 'bx bx-cart', description: 'Find people who are likely to purchase your product or service.', optimization_goal: 'OFFSITE_CONVERSIONS', call_to_action: 'SHOP_NOW' },
    { key: 'OUTCOME_AWARENESS', label: 'Awareness', icon: 'bx bx-broadcast', description: 'Reach more people and build brand awareness.', optimization_goal: 'REACH', call_to_action: 'LEARN_MORE' },
];

const optimizationGoalsByObjective = {
    OUTCOME_TRAFFIC: ['LINK_CLICKS', 'LANDING_PAGE_VIEWS'],
    OUTCOME_ENGAGEMENT: ['POST_ENGAGEMENT', 'THRUPLAY', 'CONVERSATIONS'],
    OUTCOME_LEADS: ['LEAD_GENERATION', 'QUALITY_LEAD', 'OFFSITE_CONVERSIONS'],
    OUTCOME_APP_PROMOTION: ['APP_INSTALLS', 'OFFSITE_CONVERSIONS'],
    OUTCOME_SALES: ['OFFSITE_CONVERSIONS'],
    OUTCOME_AWARENESS: ['REACH', 'IMPRESSIONS', 'AD_RECALL_LIFT'],
};

const ctasByObjective = {
    OUTCOME_TRAFFIC: ['LEARN_MORE', 'SHOP_NOW', 'CONTACT_US'],
    OUTCOME_ENGAGEMENT: ['MESSAGE_PAGE', 'LIKE_PAGE'],
    OUTCOME_LEADS: ['SIGN_UP', 'BOOK_NOW', 'GET_QUOTE'],
    OUTCOME_APP_PROMOTION: ['DOWNLOAD', 'INSTALL_APP', 'USE_MOBILE_APP'],
    OUTCOME_SALES: ['SHOP_NOW', 'BUY_NOW', 'ADD_TO_CART'],
    OUTCOME_AWARENESS: ['LEARN_MORE', 'GET_PROMOTIONS'],
};

export default {
    name: 'FacebookCampaignBuilder',
    data() {
        // Server-rendered data + route URLs are handed to the component
        // through globals set by the Blade view (create.blade.php pushes
        // them to @stack('scripts'), which runs before Vite's app.js
        // mounts this component), keeping the component free of large
        // inline-JSON props on an in-DOM element.
        const data = window.__BUILDER_DATA__ || {};
        const routes = window.__ROUTES__ || {};

        const today = new Date();
        const inTwoWeeks = new Date(today.getTime() + 14 * 86400000);
        const fmt = (d) => d.toISOString().slice(0, 10);

        return {
            objectives,
            countries: data.countries || [],
            pages: data.pages || [],
            instagram_account: data.instagramAccount || null,
            hasAdAccount: !!data.hasAdAccount,
            routes,
            currency: data.currency || 'USD',
            storeUrl: data.storeUrl,
            indexUrl: data.indexUrl,

            // New vs existing selection for each step. Existing ad sets are
            // only reusable under an existing campaign, so adset_mode is
            // forced back to 'new' whenever campaign_mode is 'new'.
            existingCampaigns: data.existingCampaigns || [],
            existingAdsets: data.existingAdsets || [],
            campaign_mode: 'new',
            adset_mode: 'new',
            existing_campaign_id: '',
            existing_adset_id: '',

            objective: 'OUTCOME_TRAFFIC',
            name: '',
            countries_selected: (data.countries && data.countries.length) ? [data.countries[0].id] : [],
            showCountryDropdown: false,
            age_from: 18,
            age_to: 65,
            gender: 'all',
            detailedTargetingChips: ['E-commerce', 'Online Shopping'],
            chipInput: '',

            budget: 100,
            budget_mode: 'daily_budget',
            start_time: fmt(today),
            end_time: fmt(inTwoWeeks),
            bid_amount: 0,
            optimization_goal: 'LINK_CLICKS',
            call_to_action: 'LEARN_MORE',
            billing_event: 'IMPRESSIONS',
            destination_type: 'WEBSITE',
            languages: ['english'],
            pixel_id: '',
            custom_event_type: '',
            object_store_url: '',
            application_id: '',
            showMore: false,

            page_id: (data.pages && data.pages.length) ? data.pages[0].id : '',
            instagram_enabled: !!data.instagramAccount,
            media_type: 'IMAGE',
            mediaFiles: [],
            mediaPreviews: [],
            thumbnailFile: null,
            primary_text: 'Discover our new collection. Stylish, comfortable and made for you.',
            headline: 'New Collection',
            description: 'Shop now and get special offer!',
            target_link: '',

            previewEnabled: true,
            previewTab: 'facebook',
            showReview: false,
            submitting: false,
            errors: {},
        };
    },
    computed: {
        objectiveLabel() {
            const found = this.objectives.find(o => o.key === this.objective);
            return found ? found.label : this.objective;
        },
        selectedExistingCampaign() {
            return this.existingCampaigns.find(c => c.id === this.existing_campaign_id) || null;
        },
        // Ad sets belonging to the chosen existing campaign (matched on the
        // ad set's local campaign FK), so "Use existing ad set" only lists
        // ad sets that actually live under the selected campaign.
        filteredAdsets() {
            if (this.campaign_mode !== 'existing' || !this.existing_campaign_id) return [];
            return this.existingAdsets.filter(a => a.campaign_id === this.existing_campaign_id);
        },
        defaultName() {
            const d = new Date(this.start_time || Date.now());
            const label = d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
            return `${this.objectiveLabel} - ${label}`;
        },
        countriesLabel() {
            if (!this.countries_selected.length) return 'Select locations';
            const first = this.countries.find(c => c.id === this.countries_selected[0]);
            const extra = this.countries_selected.length - 1;
            return extra > 0 ? `${first ? first.name : ''} +${extra} more` : (first ? first.name : 'Select locations');
        },
        ageOptions() {
            const arr = [];
            for (let a = 13; a <= 65; a++) arr.push(a);
            return arr;
        },
        selectedPage() {
            return this.pages.find(p => p.id === this.page_id) || null;
        },
        optimizationGoalOptions() {
            return optimizationGoalsByObjective[this.objective] || ['LINK_CLICKS'];
        },
        ctaOptions() {
            return ctasByObjective[this.objective] || ['LEARN_MORE'];
        },
        scheduleDays() {
            const start = new Date(this.start_time);
            const end = new Date(this.end_time);
            const days = Math.ceil((end - start) / 86400000) + 1;
            return days > 0 ? days : 0;
        },
        allocatedBudget() {
            const b = Number(this.budget) || 0;
            return this.budget_mode === 'daily_budget' ? b * this.scheduleDays : b;
        },
        vatAmount() {
            return this.allocatedBudget * 0.15;
        },
        totalBudget() {
            return this.allocatedBudget + this.vatAmount;
        },
        linkDomain() {
            try { return new URL(this.target_link).hostname.replace('www.', '').toUpperCase(); }
            catch (e) { return 'YOURWEBSITE.COM'; }
        },
        ctaLabel() {
            return this.call_to_action.replace(/_/g, ' ').replace(/\w\S*/g, t => t[0] + t.slice(1).toLowerCase());
        },
        previewSurfaceLabel() {
            return { facebook: 'Facebook Feed', instagram: 'Instagram Feed', messenger: 'Messenger Inbox', audience_network: 'Audience Network' }[this.previewTab];
        },
        // Illustrative, client-side-only heuristic for this builder - not a
        // call to Meta's real reach/delivery estimate endpoints, which need
        // a live ad account and targeting spec round-trip well beyond this
        // page's scope.
        audienceScore() {
            let score = 0.3;
            score += Math.min(this.countries_selected.length, 5) * 0.04;
            score += (this.age_to - this.age_from) / 52 * 0.25;
            score += this.gender === 'all' ? 0.15 : 0;
            score += Math.max(0, 0.15 - this.detailedTargetingChips.length * 0.03);
            return Math.max(0.08, Math.min(0.95, score));
        },
        audienceLabel() {
            if (this.audienceScore > 0.6) return 'Your audience is broad.';
            if (this.audienceScore > 0.3) return 'Your audience is defined.';
            return 'Your audience is specific.';
        },
        potentialReach() {
            const base = 800000 * Math.max(this.countries_selected.length, 1);
            return Math.round(base * this.audienceScore / 100) * 100;
        },
        reachRange() {
            const low = Math.round(this.totalBudget * 21);
            const high = Math.round(this.totalBudget * 56);
            return `${this.formatShort(low)} - ${this.formatShort(high)}`;
        },
        clicksRange() {
            const low = Math.round(this.totalBudget * 1.2);
            const high = Math.round(this.totalBudget * 3.2);
            return `${low} - ${high}`;
        },
    },
    methods: {
        selectObjective(key) {
            this.objective = key;
            const defaults = objectives.find(o => o.key === key);
            if (defaults) {
                this.optimization_goal = defaults.optimization_goal;
                this.call_to_action = defaults.call_to_action;
            }
        },
        setCampaignMode(mode) {
            this.campaign_mode = mode;
            if (mode === 'new') {
                // An existing ad set can only live under an existing
                // campaign, so creating a new campaign forces a new ad set.
                this.adset_mode = 'new';
                this.existing_campaign_id = '';
                this.existing_adset_id = '';
            } else if (!this.existing_campaign_id && this.existingCampaigns.length) {
                this.existing_campaign_id = this.existingCampaigns[0].id;
                this.syncObjectiveFromExisting();
            }
        },
        setAdsetMode(mode) {
            if (mode === 'existing' && this.campaign_mode !== 'existing') return; // gated
            this.adset_mode = mode;
            if (mode === 'existing') {
                if (!this.existing_adset_id && this.filteredAdsets.length) {
                    this.existing_adset_id = this.filteredAdsets[0].id;
                }
            } else {
                this.existing_adset_id = '';
            }
        },
        // When reusing a campaign, mirror its objective locally so the
        // preview, CTA options and optimization goals stay coherent.
        syncObjectiveFromExisting() {
            const c = this.selectedExistingCampaign;
            if (c && c.objective) this.selectObjective(c.objective);
        },
        objectiveShort(key) {
            const found = objectives.find(o => o.key === key);
            return found ? found.label : key;
        },
        scrollToPanel(id) {
            const el = document.getElementById(id);
            if (el) el.scrollIntoView({ behavior: 'smooth', block: 'start' });
        },
        addChip() {
            const v = this.chipInput.trim();
            if (v && !this.detailedTargetingChips.includes(v)) this.detailedTargetingChips.push(v);
            this.chipInput = '';
        },
        chipKeydown(e) {
            if (e.key === ',') { e.preventDefault(); this.addChip(); }
        },
        toggleLanguage(lang) {
            const i = this.languages.indexOf(lang);
            if (i > -1) { if (this.languages.length > 1) this.languages.splice(i, 1); }
            else this.languages.push(lang);
        },
        setFormat(type) {
            this.media_type = type;
            this.mediaFiles = [];
            this.mediaPreviews.forEach(u => URL.revokeObjectURL(u));
            this.mediaPreviews = [];
        },
        onMediaChange(e) {
            const files = Array.from(e.target.files || []);
            if (!files.length) return;

            if (this.media_type === 'CAROUSEL') {
                this.mediaFiles.push(...files);
            } else {
                this.mediaFiles = [files[0]];
            }

            this.mediaPreviews.forEach(u => URL.revokeObjectURL(u));
            this.mediaPreviews = this.mediaFiles.map(f => URL.createObjectURL(f));
            e.target.value = '';
        },
        onThumbnailChange(e) {
            this.thumbnailFile = (e.target.files && e.target.files[0]) || null;
        },
        formatShort(n) {
            if (n >= 1000) return (n / 1000).toFixed(1).replace(/\.0$/, '') + 'K';
            return String(n);
        },
        notImplemented(label) {
            alert(label + ' is not wired up in this builder yet - only Publish submits a real campaign.');
        },
        validate() {
            const errs = {};
            // Campaign step
            if (this.campaign_mode === 'existing' && !this.existing_campaign_id) {
                errs.existing_campaign_id = 'Select an existing campaign.';
            }
            // Ad Set step
            if (this.adset_mode === 'existing') {
                if (!this.existing_adset_id) errs.existing_adset_id = 'Select an existing ad set.';
            } else {
                if (!this.countries_selected.length) errs.countries = 'Select at least one location.';
                if (!this.start_time || !this.end_time || this.start_time >= this.end_time) errs.start_time = 'End date must be after the start date.';
            }
            // Ad / creative (always)
            if (!this.target_link) errs.target_link = 'A website URL is required.';
            if (!this.page_id) errs.page_id = 'Select a Facebook Page.';
            if (!this.mediaFiles.length) errs.media = 'Upload at least one media file.';
            if (this.media_type === 'CAROUSEL' && this.mediaFiles.length < 2) errs.media = 'A carousel ad needs at least 2 images.';
            this.errors = errs;
            return Object.keys(errs).length === 0;
        },
        async submit() {
            if (!this.hasAdAccount) return;
            if (!this.validate()) {
                this.showReview = false;
                alert('Please fix the highlighted fields before publishing.');
                return;
            }

            this.submitting = true;

            const fd = new FormData();
            fd.append('name', this.name || this.defaultName);
            fd.append('campaign_mode', this.campaign_mode);
            fd.append('adset_mode', this.adset_mode);

            // Campaign step: send the objective when creating a new
            // campaign, or the chosen campaign id when reusing one.
            if (this.campaign_mode === 'new') {
                fd.append('objective', this.objective);
            } else {
                fd.append('existing_campaign_id', this.existing_campaign_id);
            }

            // Ad Set step: send the full audience/budget/schedule only when
            // creating a new ad set; otherwise just the chosen ad set id.
            if (this.adset_mode === 'new') {
                fd.append('start_time', this.start_time);
                fd.append('end_time', this.end_time);
                fd.append('budget_mode', this.budget_mode);
                fd.append('budget', this.budget);
                fd.append('final_budget', this.totalBudget.toFixed(2));
                fd.append('bid_amount', this.bid_amount);
                this.countries_selected.forEach(id => fd.append('countries[]', id));
                fd.append('age_from', this.age_from);
                fd.append('age_to', this.age_to);
                fd.append('gender', this.gender);
                this.languages.forEach(l => fd.append('languages[]', l));
                fd.append('detailed_targeting', this.detailedTargetingChips.join(', '));
                fd.append('optimization_goal', this.optimization_goal);
                fd.append('billing_event', this.billing_event);
                fd.append('destination_type', this.destination_type);
                if (this.pixel_id) fd.append('pixel_id', this.pixel_id);
                if (this.custom_event_type) fd.append('custom_event_type', this.custom_event_type);
                if (this.object_store_url) fd.append('object_store_url', this.object_store_url);
                if (this.application_id) fd.append('application_id', this.application_id);
            } else {
                fd.append('existing_adset_id', this.existing_adset_id);
            }

            // Creative + ad fields are always sent - a new ad is created
            // in every case, whether the campaign/ad set are new or reused.
            fd.append('call_to_action', this.call_to_action);
            fd.append('page_id', this.page_id);
            fd.append('facebook', '1');
            if (this.instagram_account && this.instagram_enabled) fd.append('instagram', '1');
            fd.append('media_type', this.media_type);
            this.mediaFiles.forEach(f => fd.append('media[]', f));
            if (this.thumbnailFile) fd.append('thumbnail', this.thumbnailFile);
            if (this.media_type === 'CAROUSEL') {
                const cards = this.mediaFiles.map(() => ({ title: this.headline, description: this.description || this.primary_text, link: this.target_link }));
                fd.append('carousel_cards', JSON.stringify(cards));
            }
            fd.append('primary_text', this.primary_text);
            fd.append('headline', this.headline);
            fd.append('description', this.description || this.primary_text);
            fd.append('target_link', this.target_link);

            try {
                const res = await fetch(this.storeUrl, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json',
                    },
                    body: fd,
                });

                const json = await res.json().catch(() => ({}));

                if (res.status === 422) {
                    const flat = {};
                    Object.entries(json.errors || json.data || {}).forEach(([k, v]) => flat[k] = Array.isArray(v) ? v[0] : v);
                    this.errors = flat;
                    alert('Please fix the highlighted fields: ' + Object.values(flat).join(' '));
                    return;
                }

                if (json.success === false) {
                    alert(json.message || json.error || 'Something went wrong while publishing this campaign.');
                    return;
                }

                alert('Campaign published successfully.');
                window.location.href = this.indexUrl;
            } catch (err) {
                alert('Network error while publishing: ' + err.message);
            } finally {
                this.submitting = false;
            }
        },
    },
    watch: {
        existing_campaign_id() {
            this.syncObjectiveFromExisting();
            // Drop an ad-set choice that no longer belongs to the campaign.
            if (this.existing_adset_id && !this.filteredAdsets.some(a => a.id === this.existing_adset_id)) {
                this.existing_adset_id = this.filteredAdsets.length ? this.filteredAdsets[0].id : '';
            }
        },
    },
    mounted() {
        this._onDocClick = (e) => {
            if (!e.target.closest('.dropdown-wrap')) this.showCountryDropdown = false;
        };
        document.addEventListener('click', this._onDocClick);
    },
    beforeDestroy() {
        document.removeEventListener('click', this._onDocClick);
    },
};
</script>

<style scoped>
.builder-main {
    --page-bg: #f5f5f8;
    --surface: #ffffff;
    --border: #e8e8ec;
    --border-strong: #dcdce3;
    --text-1: #111113;
    --text-2: #6b6b76;
    --text-3: #9a9aa6;
    --brand: #1877f2;
    --brand-hover: #166fe0;
    --brand-press: #0e5fc9;
    --brand-light: #e7f3ff;
    --brand-tint: #f5faff;
    --brand-ring: rgba(24,119,242,.16);
    --grad-accent: linear-gradient(90deg,#1877f2,#42a5f5);
    --grad-connector: linear-gradient(90deg,#1877f2,#4a9eff);
    --radius-lg: 20px;
    --radius-md: 12px;
    --shadow-card: 0 1px 2px rgba(17,17,19,.04), 0 12px 30px rgba(17,17,19,.05);
    --shadow-pop: 0 10px 34px rgba(17,17,19,.14);
    color: var(--text-1);
    font-family: 'Public Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
    -webkit-font-smoothing: antialiased;
}
.builder-main * { box-sizing: border-box; }
.builder-main button { font-family: inherit; }
.builder-main a { color: inherit; text-decoration: none; }

/* Topbar */
.builder-topbar { display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; padding: 4px 0 0; }
.builder-eyebrow { display: inline-flex; align-items: center; gap: 7px; font-size: .7rem; font-weight: 700; letter-spacing: .12em; text-transform: uppercase; color: var(--brand); background: var(--brand-light); padding: 5px 11px; border-radius: 999px; margin-bottom: 12px; }
.builder-topbar h1 { font-size: 1.7rem; font-weight: 800; letter-spacing: -.02em; margin: 0; }
.builder-topbar .subtitle { color: var(--text-2); margin: 5px 0 0; font-size: .92rem; }
.topbar-actions { display: flex; align-items: center; gap: 12px; flex-shrink: 0; }

.btn { border-radius: 10px; padding: 10px 16px; font-size: .85rem; font-weight: 600; cursor: pointer; border: 1px solid transparent; display: inline-flex; align-items: center; gap: 7px; transition: .15s ease; text-decoration: none; line-height: 1.1; }
.btn-outline { background: var(--surface); border-color: var(--border-strong); color: var(--text-1); }
.btn-outline:hover { background: #f4f4f6; border-color: #cfcfd8; }
.btn-primary { background: var(--brand); color: #fff; box-shadow: 0 1px 2px rgba(24,119,242,.35), 0 8px 18px rgba(24,119,242,.24); }
.btn-primary:hover { background: var(--brand-hover); }
.btn-primary:active { background: var(--brand-press); }
.btn-primary:disabled { background: #c9b8ee; box-shadow: none; cursor: not-allowed; }
.icon-close { width: 38px; height: 38px; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: var(--text-2); font-size: 21px; cursor: pointer; border: 1px solid var(--border); background: var(--surface); }
.icon-close:hover { background: #f4f4f6; color: var(--text-1); }

/* Step tracker */
.step-tracker { display: flex; align-items: center; justify-content: center; padding: 30px 0 6px; flex-wrap: wrap; gap: 6px 0; }
.step-node { display: flex; align-items: center; gap: 11px; cursor: pointer; padding: 6px; border-radius: 12px; transition: .15s; }
.step-node:hover { background: rgba(24,119,242,.05); }
.step-circle { width: 34px; height: 34px; border-radius: 50%; background: var(--brand); color: #fff; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: .9rem; flex-shrink: 0; box-shadow: 0 4px 12px rgba(24,119,242,.28); }
.step-label strong { display: block; color: var(--text-1); font-size: .9rem; font-weight: 700; }
.step-label span { display: block; color: var(--text-2); font-size: .76rem; }
.step-connector { width: 96px; height: 3px; background: var(--grad-connector); border-radius: 3px; margin: 0 18px; opacity: .85; }
@media (max-width: 1300px) { .step-connector { width: 48px; margin: 0 10px; } }
@media (max-width: 720px) { .step-label span { display: none; } .step-connector { width: 26px; } }

/* Grid */
.builder-grid { display: grid; grid-template-columns: repeat(3, 1fr) 340px; gap: 22px; padding: 24px 0 30px; align-items: start; }
@media (max-width: 1480px) { .builder-grid { grid-template-columns: repeat(3, 1fr); } .preview-panel { grid-column: 1 / -1; position: static; } }
@media (max-width: 980px) { .builder-grid { grid-template-columns: 1fr; } }

.panel { background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius-lg); padding: 24px; box-shadow: var(--shadow-card); }
.panel-head { display: flex; align-items: center; gap: 11px; margin-bottom: 20px; }
.panel-num { width: 28px; height: 28px; border-radius: 9px; background: var(--brand); color: #fff; font-size: .82rem; font-weight: 700; display: flex; align-items: center; justify-content: center; flex-shrink: 0; box-shadow: 0 4px 10px rgba(24,119,242,.26); }
.panel-title { font-weight: 800; letter-spacing: -.01em; color: var(--text-1); font-size: 1.08rem; }

.field-block { margin-bottom: 20px; }
.field-label { font-size: .82rem; font-weight: 600; color: #26262c; margin-bottom: 6px; display: flex; align-items: center; gap: 5px; }
.field-label i { color: var(--text-3); font-size: .95rem; cursor: help; }
.field-hint { color: var(--text-2); font-size: .78rem; margin: 2px 0 10px; }
.section-title { font-weight: 700; font-size: .96rem; margin: 0 0 2px; letter-spacing: -.01em; }
.section-sub { color: var(--text-2); font-size: .8rem; margin: 0 0 16px; }

.field-input, select.field-input, textarea.field-input { width: 100%; padding: 11px 14px; border: 1px solid var(--border-strong); border-radius: 11px; font-size: .86rem; background: var(--surface); color: var(--text-1); transition: border-color .15s, box-shadow .15s; }
.field-input::placeholder { color: var(--text-3); }
.field-input:focus { outline: none; border-color: var(--brand); box-shadow: 0 0 0 4px var(--brand-ring); }
select.field-input { appearance: none; background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' fill='none' stroke='%236b6b76' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M4 6l4 4 4-4'/%3E%3C/svg%3E"); background-repeat: no-repeat; background-position: right 12px center; padding-right: 34px; }
textarea.field-input { resize: vertical; font-family: inherit; line-height: 1.5; }
.field-row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.error-text { color: #dc2626; font-size: .74rem; margin-top: 5px; min-height: 14px; }

/* New / Existing segmented control */
.mode-tabs { display: flex; gap: 8px; background: #f4f4f6; padding: 4px; border-radius: 12px; margin-bottom: 18px; }
.mode-tab { flex: 1; display: flex; align-items: center; justify-content: center; gap: 6px; padding: 10px 8px; border-radius: 9px; cursor: pointer; font-size: .82rem; font-weight: 700; color: #55555f; transition: .15s; user-select: none; text-align: center; }
.mode-tab:hover:not(.active):not(.disabled) { color: var(--text-1); }
.mode-tab.active { background: var(--surface); color: var(--brand); box-shadow: 0 1px 3px rgba(17,17,19,.12); }
.mode-tab.disabled { opacity: .45; cursor: not-allowed; }
.mode-tab i { font-size: 1rem; }

/* Objective cards */
.objective-card { display: flex; gap: 12px; padding: 14px; border: 1.5px solid var(--border); border-radius: 14px; cursor: pointer; margin-bottom: 10px; position: relative; overflow: hidden; transition: border-color .15s, background .15s, transform .05s; }
.objective-card:hover { border-color: #a9cbf5; }
.objective-card:active { transform: scale(.995); }
.objective-card.selected { border-color: var(--brand); background: var(--brand-tint); }
.objective-icon { width: 40px; height: 40px; border-radius: 11px; background: var(--brand-light); display: flex; align-items: center; justify-content: center; color: var(--brand); font-size: 19px; flex-shrink: 0; transition: .15s; }
.objective-card.selected .objective-icon { background: var(--brand); color: #fff; }
.objective-text strong { display: block; font-size: .88rem; font-weight: 700; }
.objective-text span { color: var(--text-2); font-size: .76rem; line-height: 1.35; }
.objective-radio { position: absolute; top: 15px; right: 15px; width: 18px; height: 18px; border-radius: 50%; border: 2px solid #ccc; transition: .15s; }
.objective-card.selected .objective-radio { border-color: var(--brand); }
.objective-card.selected .objective-radio::after { content: ''; position: absolute; inset: 3px; border-radius: 50%; background: var(--brand); }

.map-illustration { margin-top: 18px; border-radius: 16px; background: linear-gradient(135deg,#eaf3ff,#e1eefb); padding: 24px; position: relative; overflow: hidden; height: 130px; }
.map-illustration svg { position: absolute; inset: 0; width: 100%; height: 100%; }
.map-card { position: absolute; right: 14px; bottom: 14px; background: #fff; border-radius: 10px; padding: 9px 11px; box-shadow: 0 6px 16px rgba(17,17,19,.1); width: 92px; }
.map-card .bar { height: 5px; border-radius: 3px; background: #dbe7f7; margin-bottom: 5px; }
.map-card .bar:nth-child(1) { width: 80%; background: var(--brand-light); }
.map-card .bar:nth-child(2) { width: 55%; }

/* Pills / toggle groups */
.pill-group { display: flex; gap: 8px; background: #f4f4f6; padding: 4px; border-radius: 12px; }
.pill { flex: 1; text-align: center; padding: 9px 6px; border-radius: 9px; cursor: pointer; font-size: .82rem; font-weight: 600; color: #55555f; transition: .15s; user-select: none; }
.pill:hover:not(.active):not(.disabled) { color: var(--text-1); }
.pill.active { background: var(--surface); color: var(--brand); box-shadow: 0 1px 3px rgba(17,17,19,.12); }
.pill.disabled { opacity: .4; cursor: not-allowed; }

/* Chips */
.chip-box { display: flex; flex-wrap: wrap; gap: 8px; padding: 9px; border: 1px solid var(--border-strong); border-radius: 11px; transition: border-color .15s, box-shadow .15s; }
.chip-box:focus-within { border-color: var(--brand); box-shadow: 0 0 0 4px var(--brand-ring); }
.chip { background: var(--brand-light); color: var(--brand-press); padding: 5px 6px 5px 12px; border-radius: 20px; font-size: .78rem; font-weight: 600; display: flex; align-items: center; gap: 4px; white-space: nowrap; }
.chip button { border: 0; background: none; cursor: pointer; color: var(--brand); font-size: 1rem; line-height: 1; padding: 2px 4px; }
.chip-box input { flex: 1; min-width: 150px; border: 0; outline: none; font-size: .84rem; padding: 4px; background: transparent; }

/* Locations dropdown */
.dropdown-wrap { position: relative; }
.dropdown-trigger { display: flex; align-items: center; justify-content: space-between; cursor: pointer; }
.dropdown-trigger i { color: var(--text-2); }
.dropdown-panel { position: absolute; top: calc(100% + 6px); left: 0; right: 0; background: var(--surface); border: 1px solid var(--border); border-radius: 12px; box-shadow: var(--shadow-pop); max-height: 244px; overflow-y: auto; z-index: 40; padding: 6px; }
.dropdown-option { display: flex; align-items: center; gap: 9px; padding: 9px 10px; border-radius: 8px; cursor: pointer; font-size: .84rem; }
.dropdown-option:hover { background: var(--brand-tint); }
.dropdown-option input { accent-color: var(--brand); }

/* Budget input with currency suffix */
.input-suffix { position: relative; }
.input-suffix input { padding-right: 58px; }
.input-suffix span { position: absolute; right: 14px; top: 50%; transform: translateY(-50%); color: var(--text-2); font-size: .78rem; font-weight: 700; }

.vat-box { background: linear-gradient(180deg,#f7fbff,#eff6fe); border: 1px solid #e2edfb; border-radius: 13px; padding: 13px 15px; margin-top: 12px; font-size: .82rem; }
.vat-row { display: flex; justify-content: space-between; padding: 3px 0; color: var(--text-2); }
.vat-row.total { color: var(--text-1); font-weight: 700; border-top: 1px dashed #cadcf5; margin-top: 5px; padding-top: 8px; }

.show-more { color: var(--brand); font-size: .84rem; font-weight: 700; cursor: pointer; display: inline-flex; align-items: center; gap: 4px; margin-top: 4px; user-select: none; }
.show-more i { transition: .2s; }
.show-more.open i { transform: rotate(180deg); }
.more-options { overflow: hidden; max-height: 0; transition: max-height .28s ease; }
.more-options.open { max-height: 1200px; margin-top: 16px; }

/* Page select */
.page-select { display: flex; align-items: center; gap: 10px; border: 1px solid var(--border-strong); border-radius: 11px; padding: 9px 12px; cursor: pointer; transition: border-color .15s; }
.page-select:hover { border-color: #cfcfd8; }
.page-avatar { width: 30px; height: 30px; border-radius: 8px; object-fit: cover; background: #dbe7f7; flex-shrink: 0; }
.page-select select { border: 0; outline: none; flex: 1; font-size: .85rem; background: transparent; appearance: none; cursor: pointer; }
.page-select.disabled { opacity: .6; }

/* Media box */
.media-box { border: 1.5px dashed var(--border-strong); border-radius: 14px; padding: 14px; display: flex; align-items: center; gap: 14px; flex-wrap: wrap; transition: border-color .15s, background .15s; }
.media-box:hover { border-color: #a9cbf5; background: var(--brand-tint); }
.media-thumb { width: 74px; height: 74px; border-radius: 11px; background: #eef4fc; object-fit: cover; display: flex; align-items: center; justify-content: center; color: #9db4d0; font-size: 24px; overflow: hidden; flex-shrink: 0; }
.media-thumb img, .media-thumb video { width: 100%; height: 100%; object-fit: contain; }
.carousel-strip { display: flex; gap: 8px; flex-wrap: wrap; }

/* Footer */
.builder-footer { position: sticky; bottom: 0; background: rgba(255,255,255,.9); backdrop-filter: saturate(180%) blur(8px); border-top: 1px solid var(--border); padding: 16px 0; display: flex; justify-content: space-between; align-items: center; gap: 12px; z-index: 30; margin-top: 4px; }

/* Preview panel */
.preview-panel { position: sticky; top: 20px; }
.preview-head { display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px; }
.switch { width: 42px; height: 24px; border-radius: 20px; background: var(--brand); position: relative; cursor: pointer; flex-shrink: 0; transition: background .15s; }
.switch.off { background: #ccccd6; }
.switch .thumb { position: absolute; top: 3px; left: 21px; width: 18px; height: 18px; border-radius: 50%; background: #fff; transition: left .15s; box-shadow: 0 1px 2px rgba(0,0,0,.2); }
.switch.off .thumb { left: 3px; }

.platform-tabs { display: flex; gap: 6px; background: #f4f4f6; padding: 4px; border-radius: 11px; margin-bottom: 16px; }
.platform-tab { flex: 1; display: flex; align-items: center; justify-content: center; padding: 8px 0; border-radius: 8px; cursor: pointer; font-size: 1.05rem; color: #77777f; transition: .15s; }
.platform-tab.active { background: var(--surface); box-shadow: 0 1px 3px rgba(17,17,19,.14); }
.platform-tab i.bxl-facebook-square { color: #1877f2; }
.platform-tab i.bxl-instagram { color: #d6249f; }
.platform-tab i.bxl-messenger { color: #a238ff; }

.fb-card { border: 1px solid var(--border); border-radius: 14px; overflow: hidden; box-shadow: 0 6px 20px rgba(17,17,19,.05); }
.fb-card-head { display: flex; align-items: center; gap: 9px; padding: 12px; }
.fb-avatar { width: 38px; height: 38px; border-radius: 50%; background: var(--grad-connector); flex-shrink: 0; }
.fb-name { font-size: .84rem; font-weight: 700; }
.fb-sponsored { font-size: .74rem; color: var(--text-2); }
.fb-dots { margin-left: auto; color: var(--text-2); }
.fb-body-text { padding: 0 12px 10px; font-size: .83rem; color: #1c1c22; white-space: pre-wrap; word-break: break-word; line-height: 1.45; }
.fb-media { width: 100%; aspect-ratio: 1/1; background: #eaf1fb; display: flex; align-items: center; justify-content: center; color: #a7b8d1; font-size: 2rem; overflow: hidden; }
.fb-media img, .fb-media video { width: 100%; height: 100%; object-fit: contain; }
.fb-link-bar { display: flex; align-items: center; justify-content: space-between; gap: 10px; padding: 11px 12px; background: #f6f5f9; }
.fb-link-domain { font-size: .68rem; letter-spacing: .04em; color: var(--text-2); text-transform: uppercase; }
.fb-link-headline { font-size: .85rem; font-weight: 700; margin: 2px 0; }
.fb-link-desc { font-size: .76rem; color: var(--text-2); }
.fb-cta-btn { background: var(--brand-light); color: var(--brand-press); padding: 8px 14px; border-radius: 8px; font-weight: 700; font-size: .78rem; border: 0; white-space: nowrap; flex-shrink: 0; }

.audience-box { display: flex; align-items: center; gap: 12px; margin-top: 18px; padding: 14px; border: 1px solid var(--border); border-radius: 13px; }
.audience-text strong { display: block; font-size: .84rem; }
.audience-text span { font-size: .78rem; color: var(--text-2); }

.estimate-card { margin-top: 16px; padding: 16px; border: 1px solid var(--border); border-radius: 13px; }
.estimate-title { font-weight: 800; font-size: .92rem; margin: 0 0 12px; letter-spacing: -.01em; }
.estimate-row { display: flex; justify-content: space-between; font-size: .82rem; margin-bottom: 6px; font-weight: 600; }
.estimate-row span:last-child { color: var(--brand-press); }
.estimate-bar { height: 7px; border-radius: 4px; background: #e7f0fb; overflow: hidden; margin-bottom: 15px; }
.estimate-bar-fill { height: 100%; background: var(--grad-accent); border-radius: 4px; }

.review-panel { grid-column: 1 / -1; background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius-lg); padding: 22px 24px; box-shadow: var(--shadow-card); font-size: .85rem; }
.review-panel dl { display: grid; grid-template-columns: 170px 1fr; gap: 8px 14px; margin: 12px 0 0; }
.review-panel dt { color: var(--text-2); }
.review-panel dd { margin: 0; font-weight: 600; }

.no-account-banner { display: flex; align-items: center; gap: 8px; background: #fff8e1; border: 1px solid #ffe08a; color: #7a5b00; padding: 14px 18px; border-radius: 13px; margin-top: 14px; font-size: .85rem; }

@media (prefers-reduced-motion: reduce) { .builder-main * { transition: none !important; } }
</style>
