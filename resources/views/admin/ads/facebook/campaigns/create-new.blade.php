<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Create New Campaign - {{ config('app.name') }}</title>
    <link rel="icon" type="image/x-icon" href="{{ asset('assets/img/favicon/favicon.ico') }}">
    <link href="https://cdn.jsdelivr.net/npm/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    <style>
        :root {
            --rail-bg: #0f1b2d;
            --rail-icon: #8b9bb4;
            --page-bg: #f0f2f5;
            --border: #e4e6eb;
            --text-1: #050505;
            --text-2: #65676b;
            --blue: #1877f2;
            --blue-light: #e7f3ff;
            --green: #42b72a;
            --radius-lg: 20px;
            --radius-md: 12px;
            --shadow-card: 0 1px 2px rgba(0,0,0,.06), 0 10px 28px rgba(0,0,0,.05);
        }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background: var(--page-bg); color: var(--text-1); }
        button { font-family: inherit; }

        .builder-shell { display: flex; min-height: 100vh; }

        /* Left icon rail */
        .rail { width: 72px; flex-shrink: 0; background: var(--rail-bg); display: flex; flex-direction: column; align-items: center; padding: 18px 0; }
        .rail-logo { width: 36px; height: 36px; border-radius: 10px; margin-bottom: 22px; overflow: hidden; background: #fff; display: flex; align-items: center; justify-content: center; }
        .rail-logo img { width: 100%; height: 100%; object-fit: cover; }
        .rail-icon { width: 42px; height: 42px; border-radius: 12px; display: flex; align-items: center; justify-content: center; color: var(--rail-icon); font-size: 20px; cursor: pointer; margin-bottom: 6px; transition: .15s; text-decoration: none; }
        .rail-icon:hover { background: rgba(255,255,255,.08); color: #fff; }
        .rail-icon.active { background: var(--blue); color: #fff; }
        .rail-spacer { flex: 1; }
        .rail-avatar { width: 36px; height: 36px; border-radius: 50%; background: linear-gradient(135deg,#8a96a3,#4c5768); display: flex; align-items: center; justify-content: center; color: #fff; font-size: 13px; font-weight: 700; margin-top: 8px; border: 2px solid rgba(255,255,255,.15); }

        .builder-main { flex: 1; min-width: 0; display: flex; flex-direction: column; }

        /* Topbar */
        .builder-topbar { display: flex; align-items: flex-start; justify-content: space-between; padding: 28px 40px 0; }
        .builder-topbar h1 { font-size: 1.55rem; font-weight: 700; margin: 0; }
        .builder-topbar .subtitle { color: var(--text-2); margin: 4px 0 0; font-size: .9rem; }
        .topbar-actions { display: flex; align-items: center; gap: 12px; }
        .btn { border-radius: 8px; padding: 9px 16px; font-size: .85rem; font-weight: 600; cursor: pointer; border: 1px solid transparent; display: inline-flex; align-items: center; gap: 6px; transition: .15s; }
        .btn-outline { background: #fff; border-color: var(--border); color: var(--text-1); }
        .btn-outline:hover { background: #f5f6f7; }
        .btn-primary { background: var(--blue); color: #fff; }
        .btn-primary:hover { background: #166fe0; }
        .btn-primary:disabled { background: #a6c8f5; cursor: not-allowed; }
        .icon-close { width: 36px; height: 36px; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: var(--text-2); font-size: 20px; cursor: pointer; text-decoration: none; }
        .icon-close:hover { background: #e4e6eb; }

        /* Step tracker */
        .step-tracker { display: flex; align-items: center; justify-content: center; padding: 26px 40px 8px; }
        .step-node { display: flex; align-items: center; gap: 10px; cursor: pointer; }
        .step-circle { width: 32px; height: 32px; border-radius: 50%; background: var(--blue); color: #fff; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: .88rem; flex-shrink: 0; }
        .step-label strong { display: block; color: var(--blue); font-size: .9rem; }
        .step-label span { display: block; color: var(--text-2); font-size: .76rem; }
        .step-connector { width: 110px; height: 2px; background: var(--blue); margin: 0 18px; }
        @@media (max-width: 1300px) { .step-connector { width: 50px; } }

        /* Grid */
        .builder-grid { display: grid; grid-template-columns: repeat(3, 1fr) 340px; gap: 22px; padding: 22px 40px 30px; align-items: start; }
        @@media (max-width: 1480px) { .builder-grid { grid-template-columns: repeat(3, 1fr); } .preview-panel { grid-column: 1 / -1; } }
        @@media (max-width: 980px) { .builder-grid { grid-template-columns: 1fr; } }

        .panel { background: #fff; border-radius: var(--radius-lg); padding: 24px; box-shadow: var(--shadow-card); }
        .panel-head { display: flex; align-items: center; gap: 10px; margin-bottom: 20px; }
        .panel-num { width: 26px; height: 26px; border-radius: 50%; background: var(--blue); color: #fff; font-size: .78rem; font-weight: 700; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
        .panel-title { font-weight: 700; color: var(--blue); font-size: 1.05rem; }

        .field-block { margin-bottom: 20px; }
        .field-label { font-size: .82rem; font-weight: 600; color: #1c1e21; margin-bottom: 4px; display: flex; align-items: center; gap: 5px; }
        .field-label i { color: var(--text-2); font-size: .95rem; cursor: help; }
        .field-hint { color: var(--text-2); font-size: .78rem; margin: 2px 0 10px; }
        .section-title { font-weight: 700; font-size: .95rem; margin: 0 0 2px; }
        .section-sub { color: var(--text-2); font-size: .8rem; margin: 0 0 16px; }

        .field-input, select.field-input, textarea.field-input { width: 100%; padding: 10px 14px; border: 1px solid var(--border); border-radius: 10px; font-size: .85rem; background: #fff; color: #1c1e21; }
        .field-input:focus { outline: none; border-color: var(--blue); box-shadow: 0 0 0 3px rgba(24,119,242,.12); }
        textarea.field-input { resize: vertical; font-family: inherit; }
        .field-row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
        .error-text { color: #dc3545; font-size: .74rem; margin-top: 4px; min-height: 14px; }

        /* Objective cards */
        .objective-card { display: flex; gap: 12px; padding: 13px 14px; border: 1.5px solid var(--border); border-radius: 14px; cursor: pointer; margin-bottom: 9px; position: relative; transition: .15s; }
        .objective-card:hover { border-color: #c7d3e0; }
        .objective-card.selected { border-color: var(--blue); background: var(--blue-light); }
        .objective-icon { width: 38px; height: 38px; border-radius: 10px; background: #f0f2f5; display: flex; align-items: center; justify-content: center; color: var(--blue); font-size: 18px; flex-shrink: 0; }
        .objective-card.selected .objective-icon { background: #fff; }
        .objective-text strong { display: block; font-size: .87rem; }
        .objective-text span { color: var(--text-2); font-size: .76rem; line-height: 1.3; }
        .objective-radio { position: absolute; top: 14px; right: 14px; width: 18px; height: 18px; border-radius: 50%; border: 2px solid #ccd0d5; }
        .objective-card.selected .objective-radio { border-color: var(--blue); }
        .objective-card.selected .objective-radio::after { content: ''; position: absolute; inset: 3px; border-radius: 50%; background: var(--blue); }

        .map-illustration { margin-top: 18px; border-radius: 16px; background: linear-gradient(135deg,#eef2f7,#e6ecf3); padding: 24px; position: relative; overflow: hidden; height: 130px; }
        .map-illustration svg { position: absolute; inset: 0; width: 100%; height: 100%; }
        .map-card { position: absolute; right: 14px; bottom: 14px; background: #fff; border-radius: 10px; padding: 8px 10px; box-shadow: 0 4px 14px rgba(0,0,0,.1); width: 90px; }
        .map-card .bar { height: 5px; border-radius: 3px; background: #e4e6eb; margin-bottom: 5px; }
        .map-card .bar:nth-child(1) { width: 80%; } .map-card .bar:nth-child(2) { width: 55%; }

        /* Pills / toggle groups */
        .pill-group { display: flex; gap: 8px; }
        .pill { flex: 1; text-align: center; padding: 9px 6px; border: 1px solid var(--border); border-radius: 10px; cursor: pointer; font-size: .82rem; font-weight: 600; color: #444; background: #fff; }
        .pill.active { background: var(--blue); color: #fff; border-color: var(--blue); }
        .pill.disabled { opacity: .45; cursor: not-allowed; position: relative; }

        /* Chips */
        .chip-box { display: flex; flex-wrap: wrap; gap: 8px; padding: 8px; border: 1px solid var(--border); border-radius: 10px; }
        .chip { background: #eef2f7; padding: 5px 6px 5px 12px; border-radius: 20px; font-size: .78rem; display: flex; align-items: center; gap: 4px; white-space: nowrap; }
        .chip button { border: 0; background: none; cursor: pointer; color: #8a96a3; font-size: 1rem; line-height: 1; padding: 2px 4px; }
        .chip-box input { flex: 1; min-width: 140px; border: 0; outline: none; font-size: .84rem; padding: 4px; }

        /* Locations dropdown */
        .dropdown-wrap { position: relative; }
        .dropdown-trigger { display: flex; align-items: center; justify-content: space-between; cursor: pointer; }
        .dropdown-panel { position: absolute; top: calc(100% + 6px); left: 0; right: 0; background: #fff; border: 1px solid var(--border); border-radius: 12px; box-shadow: var(--shadow-card); max-height: 240px; overflow-y: auto; z-index: 40; padding: 6px; }
        .dropdown-option { display: flex; align-items: center; gap: 8px; padding: 8px 10px; border-radius: 8px; cursor: pointer; font-size: .84rem; }
        .dropdown-option:hover { background: #f5f6f7; }

        /* Budget input with currency suffix */
        .input-suffix { position: relative; }
        .input-suffix input { padding-right: 56px; }
        .input-suffix span { position: absolute; right: 14px; top: 50%; transform: translateY(-50%); color: var(--text-2); font-size: .78rem; font-weight: 700; }

        .vat-box { background: #f7f9fb; border-radius: 12px; padding: 12px 14px; margin-top: 10px; font-size: .82rem; }
        .vat-row { display: flex; justify-content: space-between; padding: 3px 0; color: var(--text-2); }
        .vat-row.total { color: var(--text-1); font-weight: 700; border-top: 1px dashed var(--border); margin-top: 4px; padding-top: 7px; }

        .show-more { color: var(--blue); font-size: .84rem; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 4px; margin-top: 2px; user-select: none; }
        .show-more i { transition: .2s; }
        .show-more.open i { transform: rotate(180deg); }
        .more-options { overflow: hidden; max-height: 0; transition: max-height .25s ease; }
        .more-options.open { max-height: 900px; margin-top: 16px; }

        /* Page select */
        .page-select { display: flex; align-items: center; gap: 10px; border: 1px solid var(--border); border-radius: 10px; padding: 8px 12px; cursor: pointer; }
        .page-avatar { width: 30px; height: 30px; border-radius: 8px; object-fit: cover; background: #dbe2ea; flex-shrink: 0; }
        .page-select select { border: 0; outline: none; flex: 1; font-size: .85rem; background: transparent; }
        .page-select.disabled { opacity: .55; }

        /* Media box */
        .media-box { border: 1.5px dashed var(--border); border-radius: 14px; padding: 12px; display: flex; align-items: center; gap: 14px; flex-wrap: wrap; }
        .media-thumb { width: 72px; height: 72px; border-radius: 10px; background: #f0f2f5; object-fit: cover; display: flex; align-items: center; justify-content: center; color: #aab2bd; font-size: 24px; overflow: hidden; flex-shrink: 0; }
        .media-thumb img, .media-thumb video { width: 100%; height: 100%; object-fit: cover; }
        .carousel-strip { display: flex; gap: 8px; flex-wrap: wrap; }

        /* Footer */
        .builder-footer { position: sticky; bottom: 0; background: #fff; border-top: 1px solid var(--border); padding: 16px 40px; display: flex; justify-content: space-between; align-items: center; }

        /* Preview panel */
        .preview-panel { position: sticky; top: 20px; }
        .preview-head { display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px; }
        .switch { width: 42px; height: 24px; border-radius: 20px; background: var(--blue); position: relative; cursor: pointer; flex-shrink: 0; }
        .switch.off { background: #ccd0d5; }
        .switch .thumb { position: absolute; top: 3px; left: 21px; width: 18px; height: 18px; border-radius: 50%; background: #fff; transition: .15s; }
        .switch.off .thumb { left: 3px; }

        .platform-tabs { display: flex; gap: 6px; background: #f0f2f5; padding: 4px; border-radius: 10px; margin-bottom: 16px; }
        .platform-tab { flex: 1; display: flex; align-items: center; justify-content: center; padding: 8px 0; border-radius: 8px; cursor: pointer; font-size: 1.05rem; color: #65676b; }
        .platform-tab.active { background: #fff; box-shadow: 0 1px 3px rgba(0,0,0,.15); }
        .platform-tab i.bxl-facebook-square { color: #1877f2; }
        .platform-tab i.bxl-instagram { color: #d6249f; }
        .platform-tab i.bxl-messenger { color: #a238ff; }

        .fb-card { border: 1px solid var(--border); border-radius: 12px; overflow: hidden; }
        .fb-card-head { display: flex; align-items: center; gap: 9px; padding: 12px; }
        .fb-avatar { width: 36px; height: 36px; border-radius: 50%; background: linear-gradient(135deg,#8a96a3,#4c5768); flex-shrink: 0; }
        .fb-name { font-size: .84rem; font-weight: 700; }
        .fb-sponsored { font-size: .74rem; color: var(--text-2); }
        .fb-dots { margin-left: auto; color: var(--text-2); }
        .fb-body-text { padding: 0 12px 10px; font-size: .83rem; color: #1c1e21; white-space: pre-wrap; word-break: break-word; }
        .fb-media { width: 100%; aspect-ratio: 1/1; background: #eef1f5; display: flex; align-items: center; justify-content: center; color: #b0b8c1; font-size: 2rem; overflow: hidden; }
        .fb-media img, .fb-media video { width: 100%; height: 100%; object-fit: cover; }
        .fb-link-bar { display: flex; align-items: center; justify-content: space-between; gap: 10px; padding: 10px 12px; background: #f0f2f5; }
        .fb-link-domain { font-size: .68rem; letter-spacing: .03em; color: var(--text-2); text-transform: uppercase; }
        .fb-link-headline { font-size: .85rem; font-weight: 700; margin: 2px 0; }
        .fb-link-desc { font-size: .76rem; color: var(--text-2); }
        .fb-cta-btn { background: #e4e6eb; padding: 8px 14px; border-radius: 6px; font-weight: 700; font-size: .78rem; border: 0; white-space: nowrap; flex-shrink: 0; }

        .audience-box { display: flex; align-items: center; gap: 12px; margin-top: 18px; }
        .audience-text strong { display: block; font-size: .84rem; }
        .audience-text span { font-size: .78rem; color: var(--text-2); }

        .estimate-title { font-weight: 700; font-size: .9rem; margin: 18px 0 10px; }
        .estimate-row { display: flex; justify-content: space-between; font-size: .82rem; margin-bottom: 5px; }
        .estimate-bar { height: 6px; border-radius: 3px; background: #e4e6eb; overflow: hidden; margin-bottom: 16px; }
        .estimate-bar-fill { height: 100%; background: linear-gradient(90deg,#1877f2,#42b72a); border-radius: 3px; }

        .review-panel { grid-column: 1 / -1; background: #fff; border-radius: var(--radius-lg); padding: 20px 24px; box-shadow: var(--shadow-card); margin: 0 40px 18px; font-size: .85rem; }
        .review-panel dl { display: grid; grid-template-columns: 160px 1fr; gap: 6px 14px; margin: 0; }
        .review-panel dt { color: var(--text-2); }
        .review-panel dd { margin: 0; font-weight: 600; }

        .no-account-banner { grid-column: 1 / -1; background: #fff8e1; border: 1px solid #ffe08a; color: #7a5b00; padding: 14px 18px; border-radius: 12px; margin: 0 40px 4px; font-size: .85rem; }
    </style>
</head>
<body>
    <script>
        // The handful of genuinely server-rendered values (route URLs, the
        // avatar initial) are resolved here, before the verbatim block
        // below turns off Blade parsing for the rest of the page - Vue's
        // own double-curly-brace interpolation syntax collides with
        // Blade's, so everything inside #app has to be off-limits to the
        // compiler (including in comments - Blade parses those too).
        window.__ROUTES__ = {
            dashboard: @json(route('admin.dashboard')),
            createNew: @json(route('admin.ads.campaigns.create_new', ['platform' => 'facebook'])),
            adsDashboard: @json(route('admin.ads.dashboard')),
            campaignsIndex: @json(route('admin.ads.campaigns.index', ['platform' => 'facebook'])),
        };
        window.__USER_INITIAL__ = @json(strtoupper(substr(Auth::user()->name ?? 'U', 0, 1)));
    </script>
    @verbatim
    <div id="app" class="builder-shell">
        <aside class="rail">
            <div class="rail-logo">
                <img src="https://cdn.socialeaz.com/uploads/6kyYmO2jeGMdWShseKtxLtnuIlN7oDqBq4jxkWYL.png" alt="Socialeaz">
            </div>
            <a class="rail-icon" :href="routes.dashboard" title="Home"><i class="bx bx-home-alt"></i></a>
            <a class="rail-icon active" :href="routes.createNew" title="Create"><i class="bx bx-plus"></i></a>
            <a class="rail-icon" href="javascript:void(0)" title="Notifications"><i class="bx bx-bell"></i></a>
            <a class="rail-icon" :href="routes.adsDashboard" title="Ads Dashboard"><i class="bx bx-grid-alt"></i></a>
            <a class="rail-icon" href="javascript:void(0)" title="Analytics"><i class="bx bx-line-chart"></i></a>
            <a class="rail-icon" :href="routes.campaignsIndex" title="Campaigns"><i class="bx bx-megaphone"></i></a>
            <div class="rail-spacer"></div>
            <a class="rail-icon" href="javascript:void(0)" title="Settings"><i class="bx bx-cog"></i></a>
            <a class="rail-icon" href="javascript:void(0)" title="Help"><i class="bx bx-help-circle"></i></a>
            <div class="rail-avatar">{{ userInitial }}</div>
        </aside>

        <div class="builder-main">
            <header class="builder-topbar">
                <div>
                    <h1>Create New Campaign</h1>
                    <p class="subtitle">Build your campaign in 3 simple steps</p>
                </div>
                <div class="topbar-actions">
                    <button type="button" class="btn btn-outline" @click="notImplemented('Save as Draft')"><i class="bx bx-folder"></i> Save as Draft</button>
                    <a class="icon-close" :href="routes.campaignsIndex"><i class="bx bx-x"></i></a>
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
                    No connected Facebook Ad Account was found for your account. You can still explore this design, but publishing will fail until a Facebook Ads account is connected from the Ads dashboard.
                </div>
            </template>

            <div class="builder-grid">
                <!-- Panel 1: Campaign -->
                <section class="panel" id="panel-campaign">
                    <div class="panel-head">
                        <div class="panel-num">1</div>
                        <div class="panel-title">Campaign</div>
                    </div>

                    <div class="field-block">
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

                    <div class="map-illustration">
                        <svg viewBox="0 0 300 130" preserveAspectRatio="none">
                            <path d="M20,90 Q90,20 160,60 T280,40" fill="none" stroke="#1877f2" stroke-width="2" stroke-dasharray="5,6" opacity=".55" />
                            <circle cx="20" cy="90" r="6" fill="#1877f2" />
                            <circle cx="280" cy="40" r="6" fill="#1877f2" />
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
                        <div class="panel-title" style="color:var(--text-1)">Ad Preview</div>
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
                                <path d="M3,23 A20,20 0 0,1 43,23" fill="none" stroke="#e4e6eb" stroke-width="5" />
                                <path d="M3,23 A20,20 0 0,1 43,23" fill="none" stroke="url(#g)" stroke-width="5" stroke-dasharray="63" :stroke-dashoffset="63 - (audienceScore * 63)" stroke-linecap="round" />
                                <defs><linearGradient id="g"><stop offset="0%" stop-color="#dc3545"/><stop offset="50%" stop-color="#f5a623"/><stop offset="100%" stop-color="#42b72a"/></linearGradient></defs>
                            </svg>
                            <div class="audience-text">
                                <strong>{{ audienceLabel }}</strong>
                                <span>Potential Reach: {{ potentialReach.toLocaleString() }} people</span>
                            </div>
                        </div>

                        <div class="estimate-title">Estimated Results</div>
                        <div class="estimate-row"><span>Reach</span><span>{{ reachRange }}</span></div>
                        <div class="estimate-bar"><div class="estimate-bar-fill" style="width:70%"></div></div>
                        <div class="estimate-row"><span>Link Clicks</span><span>{{ clicksRange }}</span></div>
                        <div class="estimate-bar"><div class="estimate-bar-fill" style="width:42%"></div></div>
                        <p class="field-hint" style="margin-top:0">Illustrative estimate for this preview only, not a live delivery forecast from Meta.</p>
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
    </div>
    @endverbatim

    <script src="https://cdn.jsdelivr.net/npm/vue@3.4.21/dist/vue.global.prod.js"></script>
    <script>
        const BUILDER_DATA = {
            countries: @json($countriesData),
            pages: @json($pagesData),
            instagramAccount: @json($instagramAccountData),
            hasAdAccount: {{ $account ? 'true' : 'false' }},
            currency: @json($account->currency ?? 'USD'),
            storeUrl: @json(route('admin.ads.campaigns.store', ['platform' => 'facebook'])),
            indexUrl: @json(route('admin.ads.campaigns.index', ['platform' => 'facebook'])),
        };

        const objectives = [
            { key: 'OUTCOME_TRAFFIC', label: 'Traffic', icon: 'bx bx-cursor', description: 'Send people to a destination, like your website.', optimization_goal: 'LINK_CLICKS', call_to_action: 'LEARN_MORE' },
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

        Vue.createApp({
            data() {
                const today = new Date();
                const inTwoWeeks = new Date(today.getTime() + 14 * 86400000);
                const fmt = (d) => d.toISOString().slice(0, 10);

                return {
                    objectives,
                    countries: BUILDER_DATA.countries,
                    pages: BUILDER_DATA.pages,
                    instagram_account: BUILDER_DATA.instagramAccount,
                    hasAdAccount: BUILDER_DATA.hasAdAccount,
                    routes: window.__ROUTES__,
                    userInitial: window.__USER_INITIAL__,
                    currency: BUILDER_DATA.currency,

                    objective: 'OUTCOME_TRAFFIC',
                    name: '',
                    countries_selected: BUILDER_DATA.countries.length ? [BUILDER_DATA.countries[0].id] : [],
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

                    page_id: BUILDER_DATA.pages.length ? BUILDER_DATA.pages[0].id : '',
                    instagram_enabled: !!BUILDER_DATA.instagramAccount,
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
                    return this.objectives.find(o => o.key === this.objective)?.label || this.objective;
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
                    return extra > 0 ? `${first?.name} +${extra} more` : (first?.name || 'Select locations');
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
                // Illustrative, client-side-only heuristic for this design
                // prototype - not a call to Meta's real reach/delivery
                // estimate endpoints, which need a live ad account and
                // targeting spec round-trip well beyond this page's scope.
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
                scrollToPanel(id) {
                    document.getElementById(id)?.scrollIntoView({ behavior: 'smooth', block: 'start' });
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
                    this.thumbnailFile = e.target.files?.[0] || null;
                },
                formatShort(n) {
                    if (n >= 1000) return (n / 1000).toFixed(1).replace(/\.0$/, '') + 'K';
                    return String(n);
                },
                notImplemented(label) {
                    alert(label + ' is not wired up in this design prototype yet - only Publish submits a real campaign.');
                },
                validate() {
                    const errs = {};
                    if (!this.countries_selected.length) errs.countries = 'Select at least one location.';
                    if (!this.start_time || !this.end_time || this.start_time >= this.end_time) errs.start_time = 'End date must be after the start date.';
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
                    fd.append('objective', this.objective);
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
                    if (this.pixel_id) fd.append('pixel_id', this.pixel_id);
                    if (this.custom_event_type) fd.append('custom_event_type', this.custom_event_type);
                    if (this.object_store_url) fd.append('object_store_url', this.object_store_url);
                    if (this.application_id) fd.append('application_id', this.application_id);

                    try {
                        const res = await fetch(BUILDER_DATA.storeUrl, {
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
                        window.location.href = BUILDER_DATA.indexUrl;
                    } catch (err) {
                        alert('Network error while publishing: ' + err.message);
                    } finally {
                        this.submitting = false;
                    }
                },
            },
            mounted() {
                document.addEventListener('click', (e) => {
                    if (!e.target.closest('.dropdown-wrap')) this.showCountryDropdown = false;
                });
            },
        }).mount('#app');
    </script>
</body>
</html>
