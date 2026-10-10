<template>
  <div class="ch">

    <header class="ch-head">
      <span class="ch-head-icon"><i class="bx bx-link-alt"></i></span>
      <div>
        <h1 class="ch-title">Connections</h1>
        <p class="ch-sub">Manage your social media accounts, ad accounts and chat connections all in one place.</p>
      </div>
    </header>

    <div v-if="flashMessage" class="ch-flash" :class="flashMessage.tone" role="status" aria-live="polite">
      <i class="bx" :class="flashMessage.tone === 'is-error' ? 'bx-error-circle' : 'bx-check-circle'"></i>
      <span>{{ flashMessage.text }}</span>
      <button type="button" class="ch-flash-x" @click="flashMessage = null" aria-label="Dismiss"><i class="bx bx-x"></i></button>
    </div>

    <!-- "Connect all recommended": one consent per step, back here between them. -->
    <div v-if="wizardState" class="ch-wizard" :class="{ 'is-done': !wizardState.next }">
      <span class="ch-wizard-icon"><i class="bx" :class="wizardState.next ? 'bx-rocket' : 'bx-check-double'"></i></span>
      <div class="ch-wizard-text">
        <template v-if="wizardState.next">
          <strong>Setup {{ wizardState.done + 1 }} of {{ wizardState.total }}: {{ wizardState.next.label }}</strong>
          <span>Next, sign in with {{ wizardState.next.label }}. You'll come straight back here afterwards.</span>
        </template>
        <template v-else>
          <strong>Setup finished</strong>
          <span>{{ wizardState.skipped ? wizardState.skipped + ' skipped - connect them from their cards whenever you like.' : 'All recommended platforms are connected.' }}</span>
        </template>
        <div class="ch-wizard-bar"><span :style="{ width: (100 * wizardState.done / wizardState.total) + '%' }"></span></div>
      </div>
      <div v-if="wizardState.next" class="ch-wizard-actions">
        <form method="POST" :action="urls.wizard_finish" class="ch-inline-form">
          <input type="hidden" name="_token" :value="csrf"><input type="hidden" name="_method" value="DELETE">
          <button type="submit" class="ch-btn ch-btn-ghost sm">Finish later</button>
        </form>
        <form method="POST" :action="urls.wizard_skip" class="ch-inline-form">
          <input type="hidden" name="_token" :value="csrf">
          <button type="submit" class="ch-btn ch-btn-ghost sm">Skip</button>
        </form>
        <a :href="wizardState.next.url" class="ch-btn ch-btn-primary sm">Continue <i class="bx bx-right-arrow-alt"></i></a>
      </div>
    </div>

    <div class="ch-layout">
      <div class="ch-main">

        <!-- Summary -->
        <section class="ch-hero">
          <span class="ch-hero-icon"><i class="bx bx-link"></i></span>
          <div class="ch-hero-text">
            <h2>Your Connected Platforms</h2>
            <p>Manage all your social accounts, ad accounts and messaging connections from one central place. Connect a platform once and Publishing, Ads and Inbox all use it.</p>
          </div>
          <div class="ch-hero-stats">
            <button v-for="s in heroStats" :key="s.key" type="button" class="ch-hero-stat" @click="setTab(s.key)">
              <span class="ch-hero-stat-top"><span class="ch-hero-stat-icon" :class="'is-' + s.tone"><i class="bx" :class="s.icon"></i></span><strong>{{ s.count }}</strong></span>
              <small>{{ s.label }}</small>
            </button>
          </div>
        </section>

        <!-- Tabs -->
        <nav class="ch-tabs" role="tablist">
          <button v-for="t in tabs" :key="t.key" type="button" role="tab" class="ch-tab" :class="{ 'is-active': tab === t.key }" :aria-selected="tab === t.key" @click="setTab(t.key)">
            <i class="bx" :class="t.icon"></i> {{ t.label }} <span class="ch-tab-count">{{ t.count }}</span>
          </button>
        </nav>

        <!-- First run: pick a platform right here -->
        <section v-if="!accountRows.length" class="ch-section ch-start">
          <div class="ch-section-head">
            <div>
              <h3>Connect your first platform</h3>
              <p>Sign in once - Publishing, Ads and Inbox all use the same connection.</p>
            </div>
          </div>
          <div class="ch-pick-grid">
            <button v-for="card in cards" :key="card.platform" type="button" class="ch-pick" @click="openPlatform(card.platform)">
              <span class="ch-pick-logos"><span v-for="b in card.presentation.icons" :key="b.icon" class="ch-badge" :class="'is-' + b.brand"><i class="bx" :class="b.icon"></i></span></span>
              <strong>{{ card.label }}</strong>
              <small>{{ card.presentation.subtitle }}</small>
              <span class="ch-pick-caps"><span v-for="cap in cardCapabilities(card)" :key="cap.key"><i class="bx" :class="cap.icon"></i> {{ cap.label }}</span></span>
              <span class="ch-pick-cta">Connect <i class="bx bx-right-arrow-alt"></i></span>
            </button>
          </div>
        </section>

        <!-- Account sections -->
        <template v-else>
          <section v-for="section in visibleSections" :key="section.key" class="ch-section">
            <div class="ch-section-head">
              <div>
                <h3>{{ section.title }}</h3>
                <p>{{ section.subtitle }}</p>
              </div>
              <button v-if="tab === 'all' && section.rows.length" type="button" class="ch-section-link" @click="setTab(section.key)">
                {{ section.rows.length }} {{ section.rows.length === 1 ? 'account' : 'accounts' }} <i class="bx bx-chevron-right"></i>
              </button>
            </div>

            <div class="ch-acct-grid">
              <article v-for="row in section.rows" :key="section.key + row.asset.id" class="ch-acct" :class="{ 'is-dim': row.state.key === 'paused' || row.state.key === 'off' }">
                <div class="ch-acct-top">
                  <span class="ch-acct-logo ch-badge" :class="'is-' + row.view.brand"><i class="bx" :class="row.view.icon"></i></span>
                  <div class="ch-acct-type">
                    <strong>{{ row.view.title }}</strong>
                    <small>{{ row.view.subtitle }}</small>
                  </div>
                  <span v-if="busy[row.conn.id] === 'check'" class="ch-state is-muted"><i class="bx bx-loader-alt bx-spin"></i> Checking…</span>
                  <span v-else class="ch-state" :class="'is-' + row.state.tone">{{ row.state.label }}</span>
                </div>
                <div class="ch-acct-bottom">
                  <span class="ch-acct-avatar">
                    <img v-if="row.asset.avatar_url" :src="row.asset.avatar_url" alt="" @error="row.asset.avatar_url = null">
                    <i v-else class="bx" :class="row.view.icon"></i>
                  </span>
                  <div class="ch-acct-id">
                    <strong :title="row.asset.name">{{ row.asset.name || row.asset.external_id }}</strong>
                    <small :title="row.asset.external_id">{{ row.asset.username && section.key === 'social' ? '@' + row.asset.username : row.view.idLabel + ': ' + row.asset.external_id }}</small>
                  </div>
                  <div class="ch-menu" @click.stop>
                    <button type="button" class="ch-kebab" :aria-expanded="openMenu === section.key + row.asset.id" aria-label="Account actions" @click="toggleMenu(section.key + row.asset.id)"><i class="bx bx-dots-vertical-rounded"></i></button>
                    <div v-if="openMenu === section.key + row.asset.id" class="ch-menu-list" role="menu">
                      <a v-if="row.state.key === 'reconnect' || row.state.key === 'expiring'" :href="row.conn.reconnect_url" role="menuitem" class="is-accent"><i class="bx bx-refresh"></i> Reconnect</a>
                      <a v-if="sectionLink(section.key, row)" :href="sectionLink(section.key, row).url" role="menuitem"><i class="bx" :class="sectionLink(section.key, row).icon"></i> {{ sectionLink(section.key, row).label }}</a>
                      <button v-if="row.asset.available_capabilities.includes(section.capability)" type="button" role="menuitem" :disabled="saving[row.asset.id]" @click="toggleSection(row, section)">
                        <i class="bx" :class="row.asset.enabled_capabilities.includes(section.capability) ? 'bx-pause-circle' : 'bx-play-circle'"></i>
                        {{ row.asset.enabled_capabilities.includes(section.capability) ? 'Pause' : 'Resume' }} {{ capability(section.capability).label.toLowerCase() }}
                      </button>
                      <button type="button" role="menuitem" :disabled="!!busy[row.conn.id]" @click="openMenu = null; check(row.card, row.conn)"><i class="bx bx-check-shield"></i> Check connection</button>
                      <button type="button" role="menuitem" @click="copyId(row)"><i class="bx bx-copy"></i> Copy {{ row.view.idLabel }}</button>
                      <span class="ch-menu-sep" role="separator"></span>
                      <button type="button" role="menuitem" @click="openPlatform(row.card.platform)"><i class="bx bx-slider-alt"></i> Manage connection</button>
                    </div>
                  </div>
                </div>
              </article>

              <button type="button" class="ch-acct ch-acct-add" @click="openPicker()">
                <span class="ch-acct-add-icon"><i class="bx bx-plus"></i></span>
                <strong>{{ section.rows.length ? 'Connect More Accounts' : section.emptyTitle }}</strong>
                <small>{{ section.addText }}</small>
              </button>
            </div>
          </section>
        </template>

      </div>

      <!-- Sidebar -->
      <aside class="ch-side">

        <section class="ch-panel">
          <h3><i class="bx bxs-zap"></i> Quick Actions</h3>
          <form v-if="wizard.available && !wizardState" method="POST" :action="urls.wizard_start" class="ch-quick-form">
            <input type="hidden" name="_token" :value="csrf">
            <button type="submit" class="ch-quick">
              <span class="ch-quick-icon is-violet"><i class="bx bx-rocket"></i></span>
              <span><strong>Connect all recommended</strong><small>Meta, Google, LinkedIn, X and TikTok in a row</small></span>
              <i class="bx bx-chevron-right"></i>
            </button>
          </form>
          <button type="button" class="ch-quick" @click="openPicker()">
            <span class="ch-quick-icon is-blue"><i class="bx bx-link"></i></span>
            <span><strong>Connect New Account</strong><small>Add a social, ad or messaging account</small></span>
            <i class="bx bx-chevron-right"></i>
          </button>
          <a v-if="urls.ads" :href="urls.ads" class="ch-quick">
            <span class="ch-quick-icon is-purple"><i class="bx bxs-megaphone"></i></span>
            <span><strong>Create Ad Campaign</strong><small>Launch your next campaign</small></span>
            <i class="bx bx-chevron-right"></i>
          </a>
          <a v-if="urls.composer" :href="urls.composer" class="ch-quick">
            <span class="ch-quick-icon is-green"><i class="bx bx-calendar-plus"></i></span>
            <span><strong>Schedule a Post</strong><small>Plan your content in advance</small></span>
            <i class="bx bx-chevron-right"></i>
          </a>
        </section>

        <section class="ch-panel">
          <h3><i class="bx bx-pulse"></i> Connection Status</h3>
          <div class="ch-donut-wrap">
            <svg class="ch-donut" viewBox="0 0 120 120" role="img" :aria-label="statusTotals.total + ' connections'">
              <circle cx="60" cy="60" r="48" class="ch-donut-track"></circle>
              <circle v-for="seg in donutSegments" :key="seg.key" cx="60" cy="60" r="48" class="ch-donut-seg" :stroke="seg.color"
                      :stroke-dasharray="seg.length + ' ' + (donutCircumference - seg.length)" :stroke-dashoffset="-seg.offset"></circle>
            </svg>
            <div class="ch-donut-label"><strong>{{ statusTotals.total }}</strong><small>Total<br>connections</small></div>
          </div>
          <ul class="ch-legend">
            <li><span class="dot is-ok"></span> Connected <strong>{{ statusTotals.ok }}</strong></li>
            <li><span class="dot is-warn"></span> Expiring soon <strong>{{ statusTotals.warn }}</strong></li>
            <li><span class="dot is-bad"></span> Needs reconnect <strong>{{ statusTotals.bad }}</strong></li>
          </ul>
        </section>

        <section class="ch-panel ch-help">
          <h3><i class="bx bx-bulb"></i> Need Help?</h3>
          <p>Check our documentation or contact our support team for help with connections.</p>
          <a v-if="urls.help" :href="urls.help" class="ch-help-btn">View Help Center <i class="bx bx-right-arrow-alt"></i></a>
          <span class="ch-help-art" aria-hidden="true"><i class="bx bx-support"></i></span>
        </section>

      </aside>
    </div>

    <!-- Side panel: pick a platform, then connect / manage it -->
    <transition name="ch-fade">
      <div v-if="drawer" class="ch-drawer-backdrop" @click.self="closeDrawer"></div>
    </transition>
    <transition name="ch-slide">
      <aside v-if="drawer" class="ch-drawer" role="dialog" aria-modal="true" :aria-label="drawerTitle" @keydown.esc="closeDrawer">
        <header class="ch-drawer-head">
          <button v-if="drawer !== 'picker'" type="button" class="ch-drawer-icon-btn" aria-label="All platforms" @click="openPicker()"><i class="bx bx-arrow-back"></i></button>
          <span v-if="drawerCard" class="ch-drawer-logos"><span v-for="b in drawerCard.presentation.icons" :key="b.icon" class="ch-badge" :class="'is-' + b.brand"><i class="bx" :class="b.icon"></i></span></span>
          <div class="ch-drawer-title">
            <h2>{{ drawerTitle }}</h2>
            <p>{{ drawerCard ? drawerCard.presentation.subtitle : 'Choose a platform to connect or manage.' }}</p>
          </div>
          <span v-if="drawerCard" class="ch-pill" :class="cardStatus(drawerCard).tone"><i class="bx" :class="cardStatus(drawerCard).icon"></i> {{ cardStatus(drawerCard).label }}</span>
          <button type="button" class="ch-drawer-icon-btn" aria-label="Close" @click="closeDrawer"><i class="bx bx-x"></i></button>
        </header>

        <div class="ch-drawer-body">
          <div v-if="drawer === 'picker'" class="ch-pick-list">
            <button v-for="card in cards" :key="card.platform" type="button" class="ch-pick-row" @click="openPlatform(card.platform)">
              <span class="ch-pick-logos"><span v-for="b in card.presentation.icons" :key="b.icon" class="ch-badge" :class="'is-' + b.brand"><i class="bx" :class="b.icon"></i></span></span>
              <span class="ch-pick-row-text">
                <strong>{{ card.label }}</strong>
                <small>{{ card.presentation.subtitle }}</small>
              </span>
              <span class="ch-state" :class="'is-' + pickState(card).tone">{{ pickState(card).label }}</span>
              <i class="bx bx-chevron-right"></i>
            </button>
          </div>

    <section v-for="card in drawerCards" :key="card.platform" class="ch-card ch-card-drawer" :id="card.platform">

      <div class="ch-card-head">
        <div class="ch-brand">
          <span class="ch-brand-stack">
            <span v-for="b in card.presentation.icons" :key="b.icon" class="ch-badge" :class="'is-' + b.brand"><i class="bx" :class="b.icon"></i></span>
          </span>
          <div>
            <h2>{{ card.label }}</h2>
            <p>{{ card.presentation.subtitle }}</p>
          </div>
        </div>
        <span class="ch-pill" :class="cardStatus(card).tone"><i class="bx" :class="cardStatus(card).icon"></i> {{ cardStatus(card).label }}</span>
      </div>

      <!-- Steps -->
      <div class="ch-steps">
        <div v-for="step in card.steps" :key="step.key" class="ch-step" :class="{ 'is-primary': step.primary, 'is-off': !step.available && step.key !== 'meta.whatsapp', 'has-form': step.key === 'meta.whatsapp' && waManual }">
          <div class="ch-step-main">
            <span class="ch-step-icon" :class="stepIcon(card, step.key).cls"><i class="bx" :class="stepIcon(card, step.key).icon"></i></span>
            <div>
              <strong>{{ step.label }} <span v-if="step.connected" class="ch-mini-ok"><i class="bx bx-check"></i> Connected</span></strong>
              <span class="ch-step-desc">{{ step.description }}</span>
              <span v-if="!step.available && step.note" class="ch-step-note"><i class="bx bx-info-circle"></i> {{ step.note }}</span>
            </div>
          </div>
          <div class="ch-step-actions">
            <!-- WhatsApp Embedded Signup runs right here (FB JS SDK popup). -->
            <button
                v-if="step.key === 'meta.whatsapp' && step.available && card.whatsapp_signup"
                type="button"
                class="ch-btn ch-btn-ghost"
                :disabled="waBusy"
                @click="startWhatsappSignup(card.whatsapp_signup)">
              <i class="bx" :class="waBusy ? 'bx-loader-alt bx-spin' : (step.connected ? 'bx-plus' : 'bx-link')"></i>
              {{ step.connected ? 'Add a number' : 'Connect' }}
            </button>
            <a v-else-if="step.available" :href="step.connect_url" class="ch-btn" :class="step.primary ? 'ch-btn-primary' : 'ch-btn-ghost'">
              <i class="bx" :class="step.connected ? 'bx-refresh' : 'bx-link'"></i>
              {{ step.connected ? (step.primary ? 'Add or change accounts' : 'Reconnect') : (step.primary ? card.presentation.connect_label : 'Connect') }}
            </a>
            <span v-else-if="step.key !== 'meta.whatsapp'" class="ch-btn ch-btn-disabled">Not available</span>
            <!-- WhatsApp: a System User token works with or without Embedded Signup. -->
            <button v-if="step.key === 'meta.whatsapp'" type="button" class="ch-btn ch-btn-ghost" @click="waManual = !waManual">
              <i class="bx" :class="waManual ? 'bx-x' : 'bx-key'"></i> {{ waManual ? 'Cancel' : 'Enter manually' }}
            </button>
          </div>
          <form v-if="step.key === 'meta.whatsapp' && waManual" method="POST" :action="urls.whatsapp_manual" class="ch-wa-form">
            <input type="hidden" name="_token" :value="csrf">
            <p>Paste the Phone Number ID and a permanent access token from a Meta Business System User. We verify them with Meta before saving.</p>
            <div class="ch-wa-fields">
              <label>Display name<input type="text" name="name" required maxlength="255" placeholder="e.g. Support line"></label>
              <label>Phone Number ID<input type="text" name="phone_number_id" required inputmode="numeric"></label>
              <label class="is-wide">Permanent access token<input type="password" name="access_token" required autocomplete="off"></label>
            </div>
            <button type="submit" class="ch-btn ch-btn-primary sm"><i class="bx bx-check"></i> Verify and connect</button>
          </form>
        </div>
      </div>

      <!-- Not connected yet -->
      <div v-if="!card.connections.length" class="ch-empty">
        <div class="ch-empty-art"><i class="bx bx-plug"></i></div>
        <h3>{{ card.presentation.empty_title }}</h3>
        <p>{{ card.presentation.empty_text }}</p>
        <ul class="ch-gets">
          <li v-for="cap in cardCapabilities(card)" :key="cap.key"><i class="bx" :class="cap.icon"></i> {{ cap.long[card.platform] || cap.long.default }}</li>
        </ul>
      </div>

      <!-- Consents + assets -->
      <div v-for="conn in card.connections" :key="conn.id" class="ch-conn">

        <div class="ch-conn-head">
          <div class="ch-conn-id">
            <span class="ch-step-icon sm" :class="stepIcon(card, conn.step).cls"><i class="bx" :class="stepIcon(card, conn.step).icon"></i></span>
            <div>
              <strong>{{ conn.step_label }}</strong>
              <span class="ch-conn-meta">
                <span class="ch-status" :class="statusInfo(conn.status).tone"><i class="ch-dot"></i>{{ statusInfo(conn.status).label }}</span>
                <span v-if="conn.expires_in_days !== null"><i class="bx bx-time-five"></i> {{ expiryLabel(conn) }}</span>
                <span v-if="conn.last_checked_at"><i class="bx bx-pulse"></i> Checked {{ ago(conn.last_checked_at) }}</span>
                <span v-if="conn.provider_account_id" class="ch-muted">ID {{ conn.provider_account_id }}</span>
              </span>
            </div>
          </div>
          <div class="ch-conn-actions">
            <button type="button" class="ch-btn ch-btn-ghost sm" :disabled="busy[conn.id]" @click="check(card, conn)">
              <i class="bx" :class="busy[conn.id] === 'check' ? 'bx-loader-alt bx-spin' : 'bx-revision'"></i> Check now
            </button>
            <template v-if="conn.status !== 'revoked'">
              <button v-if="confirming !== conn.id" type="button" class="ch-btn ch-btn-danger-ghost sm" @click="confirming = conn.id">
                <i class="bx bx-unlink"></i> Disconnect
              </button>
              <span v-else class="ch-confirm">
                <span>Remove SocialEaz's access?</span>
                <button type="button" class="ch-btn ch-btn-danger sm" :disabled="busy[conn.id]" @click="disconnect(card, conn)">Disconnect</button>
                <button type="button" class="ch-btn ch-btn-ghost sm" @click="confirming = null">Cancel</button>
              </span>
            </template>
          </div>
        </div>

        <div v-if="conn.needs_attention" class="ch-alert" :class="statusInfo(conn.status).tone">
          <i class="bx bx-error-circle"></i>
          <div>
            <strong>{{ attentionTitle(conn) }}</strong>
            <span v-if="conn.last_error">{{ conn.last_error }}</span>
          </div>
          <a :href="conn.reconnect_url" class="ch-btn ch-btn-primary sm"><i class="bx bx-refresh"></i> Reconnect</a>
        </div>

        <div v-if="conn.upgrade && conn.status !== 'revoked'" class="ch-alert is-info">
          <i class="bx bx-up-arrow-circle"></i>
          <div><strong>One-time upgrade available</strong><span>{{ conn.upgrade.note }}</span></div>
          <a :href="conn.upgrade.url" class="ch-btn ch-btn-primary sm"><i class="bx bx-up-arrow-alt"></i> Upgrade</a>
        </div>

        <!-- Capabilities of this consent -->
        <div class="ch-caps">
          <span v-for="cap in cardCapabilities(card)" :key="cap.key" class="ch-cap" :class="{ 'is-on': conn.capabilities.includes(cap.key) }">
            <i class="bx" :class="conn.capabilities.includes(cap.key) ? cap.icon : 'bx-lock-alt'"></i>
            {{ cap.label }}
            <a v-if="!conn.capabilities.includes(cap.key) && conn.upgradable" :href="conn.reconnect_url" class="ch-cap-up">Upgrade</a>
          </span>
        </div>

        <!-- Asset picker -->
        <div v-for="group in assetGroups(card, conn)" :key="group.kind" class="ch-group">
          <div class="ch-group-head">
            <h4><i class="bx" :class="group.icon"></i> {{ group.label }}</h4>
            <span class="ch-count">{{ group.items.length }}</span>
          </div>
          <div class="ch-assets">
            <div v-for="asset in group.items" :key="asset.id" class="ch-asset" :class="{ 'is-off': !asset.enabled_capabilities.length }">
              <span class="ch-avatar" :class="'is-' + group.brand">
                <img v-if="asset.avatar_url" :src="asset.avatar_url" alt="" @error="asset.avatar_url = null">
                <i v-else class="bx" :class="group.icon"></i>
              </span>
              <span class="ch-asset-id">
                <strong>{{ asset.name || asset.external_id }}</strong>
                <small>{{ asset.username ? '@' + asset.username : asset.external_id }}<template v-if="!asset.token_ok"> · <span class="ch-warn-text">needs reconnect</span></template><template v-if="asset.provider_status"> · <span class="ch-warn-text">{{ asset.provider_status }}</span></template></small>
                <small v-if="asset.duplicate_of" class="ch-dup-note"><i class="bx bx-copy"></i> Same account as {{ asset.duplicate_of }}. Publishing and inbox are off here so posts don't go out twice.</small>
              </span>
              <span class="ch-asset-caps">
                <button
                    v-for="cap in asset.available_capabilities"
                    :key="cap"
                    type="button"
                    class="ch-toggle-pill"
                    :class="{ 'is-on': asset.enabled_capabilities.includes(cap) }"
                    :disabled="saving[asset.id]"
                    :title="(asset.enabled_capabilities.includes(cap) ? 'Turn off ' : 'Turn on ') + capability(cap).label"
                    @click="toggleCapability(conn, asset, cap)">
                  <i class="bx" :class="capability(cap).icon"></i> {{ capability(cap).label }}
                </button>
                <span v-if="!asset.available_capabilities.length" class="ch-muted">No permissions granted</span>
              </span>
              <label class="ch-switch" :title="asset.enabled_capabilities.length ? 'Stop using this account' : 'Use this account'">
                <input type="checkbox" :checked="asset.enabled_capabilities.length > 0" :disabled="saving[asset.id] || !asset.available_capabilities.length" @change="toggleAsset(conn, asset)">
                <span></span>
              </label>
            </div>
          </div>
        </div>

        <p v-if="!assetGroups(card, conn).length" class="ch-muted ch-none">No accounts linked to this connection yet. Use “Add or change accounts” to choose them.</p>
      </div>
    </section>
        </div>
      </aside>
    </transition>

  </div>
</template>

<script>
const CAPABILITIES = [
  { key: 'posting', label: 'Publishing', icon: 'bx-send', long: { meta: 'Publish and schedule posts to Pages and Instagram', google: 'Upload and schedule videos to your YouTube channels', x: 'Publish and schedule posts on X', default: 'Publish and schedule posts' } },
  { key: 'messaging', label: 'Inbox', icon: 'bx-message-rounded-dots', long: { meta: 'Answer Messenger, Instagram and WhatsApp messages', x: 'Answer Direct Messages and X Chat', default: 'Answer direct messages' } },
  { key: 'ads', label: 'Ads', icon: 'bx-bullseye', long: { meta: 'Create and manage campaigns on your ad accounts', google: 'Create and manage Google Ads and YouTube campaigns', x: 'Promote posts on your X Ads accounts', default: 'Create and manage ad campaigns' } },
  { key: 'insights', label: 'Insights', icon: 'bx-bar-chart-alt-2', long: { meta: 'Read Page and Instagram insights for reports', google: 'Read Analytics data for reports', default: 'Read insights for reports' } }
];


// How each account kind (driver asset_groups key) appears in the overview.
const ACCOUNT_VIEWS = {
  page: { section: 'social', title: 'Facebook', subtitle: 'Business Page', idLabel: 'Page ID', brand: 'facebook', icon: 'bxl-facebook' },
  instagram: { section: 'social', title: 'Instagram', subtitle: 'Business Account', idLabel: 'Account ID', brand: 'instagram', icon: 'bxl-instagram' },
  youtube: { section: 'social', title: 'YouTube', subtitle: 'Channel', idLabel: 'Channel ID', brand: 'youtube', icon: 'bxl-youtube' },
  x_account: { section: 'social', title: 'X', subtitle: 'Account', idLabel: 'Account ID', brand: 'x', icon: 'bxl-x-logo' },
  linkedin_page: { section: 'social', title: 'LinkedIn', subtitle: 'Company Page', idLabel: 'Page ID', brand: 'linkedin', icon: 'bxl-linkedin' },
  tiktok_account: { section: 'social', title: 'TikTok', subtitle: 'Account', idLabel: 'Account ID', brand: 'tiktok', icon: 'bxl-tiktok' },
  threads_profile: { section: 'social', title: 'Threads', subtitle: 'Profile', idLabel: 'Profile ID', brand: 'threads', icon: 'bx-at' },
  pinterest_account: { section: 'social', title: 'Pinterest', subtitle: 'Account', idLabel: 'Account ID', brand: 'pinterest', icon: 'bxl-pinterest' },
  ad_account: { section: 'ads', title: 'Meta Ads', subtitle: 'Ad Account', idLabel: 'Account ID', brand: 'meta', icon: 'bxl-meta' },
  google_ads: { section: 'ads', title: 'Google Ads', subtitle: 'Ad Account', idLabel: 'Customer ID', brand: 'google', icon: 'bxl-google' },
  x_ads: { section: 'ads', title: 'X Ads', subtitle: 'Ad Account', idLabel: 'Account ID', brand: 'x', icon: 'bxl-x-logo' },
  linkedin_ads: { section: 'ads', title: 'LinkedIn Ads', subtitle: 'Ad Account', idLabel: 'Account ID', brand: 'linkedin', icon: 'bxl-linkedin' },
  tiktok_ads: { section: 'ads', title: 'TikTok Ads', subtitle: 'Advertiser Account', idLabel: 'Advertiser ID', brand: 'tiktok', icon: 'bxl-tiktok' },
  snapchat_ads: { section: 'ads', title: 'Snapchat Ads', subtitle: 'Ad Account', idLabel: 'Account ID', brand: 'snapchat', icon: 'bxl-snapchat' },
  whatsapp: { section: 'messaging', title: 'WhatsApp', subtitle: 'Business Number', idLabel: 'Phone ID', brand: 'whatsapp', icon: 'bxl-whatsapp' }
};

// The inbox side of social accounts that also take messages.
const INBOX_VIEWS = {
  page: { title: 'Messenger', subtitle: 'Facebook Page inbox', idLabel: 'Page ID', brand: 'facebook', icon: 'bxl-messenger' },
  instagram: { title: 'Instagram Direct', subtitle: 'Instagram inbox', idLabel: 'Account ID', brand: 'instagram', icon: 'bxl-instagram' },
  x_account: { title: 'X Messages', subtitle: 'DMs and X Chat', idLabel: 'Account ID', brand: 'x', icon: 'bxl-x-logo' }
};

const SECTIONS = [
  { key: 'social', capability: 'posting', title: 'Social Media Accounts', subtitle: 'Profiles and pages you publish to.', emptyTitle: 'Connect a social account', addText: 'Add more social media platforms to expand your reach and engagement.', label: 'Social Media', icon: 'bx-share-alt', tone: 'blue' },
  { key: 'ads', capability: 'ads', title: 'Ad Accounts', subtitle: 'Advertising accounts for your campaigns.', emptyTitle: 'Connect an ad account', addText: 'Add Meta, Google, TikTok, X, LinkedIn or Snapchat ad accounts.', label: 'Ads Manager', icon: 'bx-bullseye', tone: 'purple' },
  { key: 'messaging', capability: 'messaging', title: 'Messaging Accounts', subtitle: 'Inboxes your team answers from.', emptyTitle: 'Connect a messaging account', addText: 'Add Messenger, Instagram Direct, X or WhatsApp to your inbox.', label: 'Messaging', icon: 'bx-message-rounded-dots', tone: 'green' }
];

export default {

  props: {
    hub: { type: Object, required: true },
    urls: { type: Object, required: true },
    flash: { type: [Object, Array], default: () => ({}) },
    wizard: { type: Object, default: () => ({ state: null, available: false }) }
  },

  data() {
    const flash = Array.isArray(this.flash) ? {} : this.flash;

    return {
      cards: JSON.parse(JSON.stringify(this.hub.cards)),
      busy: {},
      saving: {},
      confirming: null,
      waBusy: false,
      flashMessage: flash.error
        ? { tone: 'is-error', text: flash.error }
        : (flash.success ? { tone: 'is-success', text: flash.success } : null),
      capabilityList: CAPABILITIES,
      wizardState: this.wizard.state,
      waManual: false,
      tab: 'all',
      // Side panel: null (closed), 'picker', or a platform key.
      drawer: null,
      openMenu: null,
      csrf: (document.querySelector('meta[name="csrf-token"]') || {}).content || ''
    };
  },

  computed: {
    // Every linked account once per section it belongs to.
    accountRows() {
      const rows = [];
      this.cards.forEach(card => {
        card.connections.forEach(conn => {
          Object.keys(conn.assets || {}).forEach(kind => {
            (conn.assets[kind] || []).forEach(asset => {
              const view = ACCOUNT_VIEWS[kind];
              if (view) rows.push({ section: view.section, kind, card, conn, asset, view });
              if (INBOX_VIEWS[kind] && asset.available_capabilities.includes('messaging')) {
                rows.push({ section: 'messaging', kind, card, conn, asset, view: INBOX_VIEWS[kind] });
              }
            });
          });
        });
      });
      return rows.map(row => ({ ...row, state: this.rowState(row) }));
    },

    sections() {
      return SECTIONS.map(section => ({ ...section, rows: this.accountRows.filter(r => r.section === section.key) }));
    },

    visibleSections() {
      return this.tab === 'all' ? this.sections : this.sections.filter(s => s.key === this.tab);
    },

    uniqueAccountCount() {
      return new Set(this.accountRows.map(r => r.asset.id)).size;
    },

    heroStats() {
      return this.sections.map(s => ({ key: s.key, label: s.label === 'Ads Manager' ? 'Ad Accounts' : s.label + ' Accounts', count: s.rows.length, icon: s.icon, tone: s.tone }));
    },

    tabs() {
      return [
        { key: 'all', label: 'All Connections', icon: 'bx-grid-alt', count: this.uniqueAccountCount },
        ...this.sections.map(s => ({ key: s.key, label: s.label, icon: s.icon, count: s.rows.length }))
      ];
    },

    // Per account (not per section row), worst state wins.
    statusTotals() {
      const byAsset = {};
      const rank = { ok: 0, warn: 1, bad: 2 };
      this.accountRows.forEach(r => {
        const tone = r.state.tone === 'ok' || r.state.tone === 'muted' ? 'ok' : r.state.tone;
        if (byAsset[r.asset.id] === undefined || rank[tone] > rank[byAsset[r.asset.id]]) byAsset[r.asset.id] = tone;
      });
      const values = Object.values(byAsset);
      return {
        total: values.length,
        ok: values.filter(v => v === 'ok').length,
        warn: values.filter(v => v === 'warn').length,
        bad: values.filter(v => v === 'bad').length
      };
    },

    drawerCard() {
      return this.drawer && this.drawer !== 'picker' ? this.cards.find(c => c.platform === this.drawer) || null : null;
    },

    drawerCards() {
      return this.drawerCard ? [this.drawerCard] : [];
    },

    drawerTitle() {
      return this.drawerCard ? this.drawerCard.label : 'Connect a platform';
    },

    donutCircumference() {
      return 2 * Math.PI * 48;
    },

    donutSegments() {
      const t = this.statusTotals;
      if (!t.total) return [];
      let offset = 0;
      return [
        { key: 'ok', value: t.ok, color: '#16A34A' },
        { key: 'warn', value: t.warn, color: '#F59E0B' },
        { key: 'bad', value: t.bad, color: '#EF4444' }
      ].filter(s => s.value > 0).map(s => {
        const length = (s.value / t.total) * this.donutCircumference;
        const seg = { ...s, length, offset };
        offset += length;
        return seg;
      });
    },

    summary() {
      return {
        connected: this.cards.filter(c => c.connected).length,
        attention: this.cards.reduce((n, c) => n + c.connections.filter(x => x.needs_attention).length, 0)
      };
    }
  },

  mounted() {
    // Module links point at a card (#meta, #google ...): open it.
    const hash = window.location.hash.replace('#', '');
    if (hash && this.cards.some(c => c.platform === hash)) this.openPlatform(hash);
    document.addEventListener('click', this.closeMenus);
    document.addEventListener('keydown', this.onKeydown);
  },

  beforeDestroy() {
    document.removeEventListener('click', this.closeMenus);
    document.removeEventListener('keydown', this.onKeydown);
    document.body.style.overflow = '';
  },

  watch: {
    flashMessage(value) {
      clearTimeout(this.flashTimer);
      if (value) this.flashTimer = setTimeout(() => { this.flashMessage = null; }, value.tone === 'is-error' ? 9000 : 4500);
    },

    // The page behind the panel doesn't scroll while it's open.
    drawer(value) {
      document.body.style.overflow = value ? 'hidden' : '';
    }
  },

  methods: {

    rowState(row) {
      const { conn, asset, section } = row;
      const cap = (SECTIONS.find(s => s.key === section) || {}).capability;
      if (conn.status === 'revoked') return { key: 'reconnect', label: 'Disconnected', tone: 'bad' };
      if (conn.needs_attention && conn.status === 'expiring') return { key: 'expiring', label: 'Expiring soon', tone: 'warn' };
      if (conn.needs_attention || !asset.token_ok) return { key: 'reconnect', label: 'Reconnect', tone: 'bad' };
      if (asset.provider_status) return { key: 'provider', label: asset.provider_status, tone: 'warn' };
      if (!asset.enabled_capabilities.length) return { key: 'off', label: 'Off', tone: 'muted' };
      if (cap && asset.available_capabilities.includes(cap) && !asset.enabled_capabilities.includes(cap)) return { key: 'paused', label: 'Paused', tone: 'muted' };
      return { key: 'ok', label: 'Connected', tone: 'ok' };
    },

    setTab(key) {
      this.tab = key;
      this.openMenu = null;
    },

    toggleMenu(key) {
      this.openMenu = this.openMenu === key ? null : key;
    },

    closeMenus(event) {
      if (this.openMenu && !event.target.closest('.ch-menu')) this.openMenu = null;
    },

    // Section shortcut in an account's menu.
    sectionLink(sectionKey, row) {
      if (sectionKey === 'social' && this.urls.composer) return { url: this.urls.composer, label: 'Create a post', icon: 'bx-edit-alt' };
      if (sectionKey === 'messaging' && this.urls.inbox) return { url: this.urls.inbox, label: 'Open inbox', icon: 'bx-message-rounded-dots' };
      if (sectionKey === 'ads' && this.urls.ads_create) {
        const platform = { meta: 'facebook' }[row.card.platform] || row.card.platform;
        return { url: this.urls.ads_create.replace('__PLATFORM__', platform), label: 'Create campaign', icon: 'bx-rocket' };
      }
      return null;
    },

    toggleSection(row, section) {
      this.openMenu = null;
      const on = row.asset.enabled_capabilities.includes(section.capability);
      const label = this.capability(section.capability).label.toLowerCase();
      this.toggleCapability(row.conn, row.asset, section.capability,
        (on ? 'Paused ' : 'Resumed ') + label + ' for ' + (row.asset.name || row.view.title) + '.');
    },

    copyId(row) {
      this.openMenu = null;
      const done = () => { this.flashMessage = { tone: 'is-success', text: row.view.idLabel + ' copied: ' + row.asset.external_id }; };
      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(String(row.asset.external_id)).then(done).catch(() => {
          this.flashMessage = { tone: 'is-error', text: 'Couldn’t copy. ' + row.view.idLabel + ': ' + row.asset.external_id };
        });
      } else {
        this.flashMessage = { tone: 'is-success', text: row.view.idLabel + ': ' + row.asset.external_id };
      }
    },

    openPicker() {
      this.openMenu = null;
      this.drawer = 'picker';
    },

    // Also what #meta / #google ... links from other pages open.
    openPlatform(platform) {
      this.openMenu = null;
      this.waManual = false;
      this.confirming = null;
      this.drawer = platform;
    },

    closeDrawer() {
      this.drawer = null;
      if (window.location.hash) history.replaceState(null, '', window.location.pathname + window.location.search);
    },

    onKeydown(event) {
      if (event.key === 'Escape' && this.drawer) this.closeDrawer();
    },

    // Picker row status: what the user has there today.
    pickState(card) {
      if (card.connections.some(c => c.needs_attention && c.status !== 'revoked')) return { label: 'Needs attention', tone: 'warn' };
      const accounts = card.connections.reduce((n, c) => n + Object.values(c.assets || {}).reduce((m, list) => m + list.length, 0), 0);
      if (card.connected) return { label: accounts + (accounts === 1 ? ' account' : ' accounts'), tone: 'ok' };
      if (!card.steps.some(st => st.available) && card.platform !== 'meta') return { label: 'Not available', tone: 'muted' };
      return { label: 'Connect', tone: 'brand' };
    },

    // Only the capabilities this platform offers (Google has no inbox).
    cardCapabilities(card) {
      const offered = (card.presentation && card.presentation.benefits) || CAPABILITIES.map(c => c.key);
      return CAPABILITIES.filter(c => offered.includes(c.key));
    },

    capability(key) {
      return CAPABILITIES.find(c => c.key === key) || { label: key, icon: 'bx-check' };
    },

    statusInfo(status) {
      return {
        active: { label: 'Active', tone: 'is-ok' },
        expiring: { label: 'Expiring soon', tone: 'is-warn' },
        expired: { label: 'Expired', tone: 'is-warn' },
        needs_reauth: { label: 'Reconnect needed', tone: 'is-bad' },
        revoked: { label: 'Disconnected', tone: 'is-muted' },
        error: { label: 'Check failed', tone: 'is-bad' }
      }[status] || { label: status, tone: 'is-muted' };
    },

    cardStatus(card) {
      if (card.connections.some(c => c.needs_attention && c.status !== 'revoked')) return { label: 'Needs attention', tone: 'is-warn', icon: 'bx-error' };
      if (card.connected) return { label: 'Connected', tone: 'is-ok', icon: 'bx-check-circle' };
      return { label: 'Not connected', tone: 'is-muted', icon: 'bx-plug' };
    },

    attentionTitle(conn) {
      return {
        expiring: 'Access expires soon. Reconnect to keep everything running.',
        expired: 'Access expired.',
        needs_reauth: 'Facebook needs you to log in again.',
        revoked: 'This connection was disconnected.',
        error: 'We couldn’t check this connection.'
      }[conn.status] || 'Needs attention';
    },

    // Icons come from the platform's driver (presentation.step_icons).
    stepIcon(card, key) {
      const icons = (card.presentation && card.presentation.step_icons) || {};
      const fallback = (card.presentation && card.presentation.icons && card.presentation.icons[0]) || { icon: 'bx-link', brand: 'meta' };
      const found = icons[key] || fallback;
      return { icon: found.icon, cls: 'is-' + found.brand };
    },



    // Groups and their order come from the platform's driver (presentation.asset_groups).
    assetGroups(card, conn) {
      const groups = (card.presentation && card.presentation.asset_groups) || {};
      return Object.keys(groups)
        .map(kind => ({ kind, ...groups[kind], items: conn.assets[kind] || [] }))
        .filter(g => g.items.length);
    },

    expiryLabel(conn) {
      const d = conn.expires_in_days;
      if (d < 0) return 'Expired';
      if (d === 0) return 'Expires today';
      return 'Expires in ' + d + (d === 1 ? ' day' : ' days');
    },

    ago(iso) {
      const s = Math.max(0, Math.round((Date.now() - new Date(iso).getTime()) / 1000));
      if (s < 60) return 'just now';
      if (s < 3600) return Math.round(s / 60) + ' min ago';
      if (s < 86400) return Math.round(s / 3600) + ' h ago';
      return Math.round(s / 86400) + ' d ago';
    },

    url(name, id) {
      return this.urls[name].replace('__ID__', id);
    },

    replaceConnection(card, updated) {
      const i = card.connections.findIndex(c => c.id === updated.id);
      if (i !== -1) this.$set(card.connections, i, updated);
    },

    toggleAsset(conn, asset) {
      const next = asset.enabled_capabilities.length ? [] : asset.available_capabilities.slice();
      this.save(conn, asset, next);
    },

    toggleCapability(conn, asset, cap, successText) {
      const on = asset.enabled_capabilities.includes(cap);
      const next = on ? asset.enabled_capabilities.filter(c => c !== cap) : asset.enabled_capabilities.concat(cap);
      this.save(conn, asset, next, successText);
    },

    // Optimistic: flip now, put it back if the server says no.
    save(conn, asset, next, successText) {
      const card = this.cards.find(c => c.connections.includes(conn));
      const previous = asset.enabled_capabilities;
      asset.enabled_capabilities = next;
      this.$set(this.saving, asset.id, true);

      window.axios.patch(this.url('asset', asset.id), { enabled_capabilities: next })
        .then(({ data }) => {
          this.replaceConnection(card, data.connection);
          if (successText) this.flashMessage = { tone: 'is-success', text: successText };
        })
        .catch(() => {
          asset.enabled_capabilities = previous;
          this.flashMessage = { tone: 'is-error', text: 'Couldn’t save that change. Please try again.' };
        })
        .finally(() => this.$delete(this.saving, asset.id));
    },

    check(card, conn) {
      this.$set(this.busy, conn.id, 'check');

      window.axios.post(this.url('check', conn.id))
        .then(({ data }) => {
          this.replaceConnection(card, data.connection);
          this.flashMessage = data.connection.needs_attention
            ? { tone: 'is-error', text: this.attentionTitle(data.connection) }
            : { tone: 'is-success', text: card.label + ' checked: everything works.' };
        })
        .catch(() => { this.flashMessage = { tone: 'is-error', text: 'The check didn’t complete. Please try again.' }; })
        .finally(() => this.$delete(this.busy, conn.id));
    },

    // Facebook JS SDK, loaded once, initialised on the one Meta app.
    loadFacebookSdk(cfg) {
      if (window.FB) return Promise.resolve();

      return new Promise((resolve, reject) => {
        window.fbAsyncInit = () => {
          window.FB.init({ appId: cfg.app_id, cookie: true, xfbml: false, version: cfg.graph_version });
          resolve();
        };
        const script = document.createElement('script');
        script.id = 'facebook-jssdk';
        script.src = 'https://connect.facebook.net/en_US/sdk.js';
        script.async = true;
        script.onerror = () => reject(new Error('sdk'));
        document.head.appendChild(script);
      });
    },

    // WhatsApp Embedded Signup: the popup reports the new WABA + phone
    // number via postMessage; FB.login's callback carries only the code,
    // which the backend exchanges (PostAccountController::storeWhatsappEmbedded).
    startWhatsappSignup(cfg) {
      this.waBusy = true;
      let session = {};
      const onMessage = (event) => {
        if (!String(event.origin).endsWith('facebook.com')) return;
        try {
          const data = JSON.parse(event.data);
          if (data.type === 'WA_EMBEDDED_SIGNUP' && data.event === 'FINISH') {
            session = { phone_number_id: data.data.phone_number_id, waba_id: data.data.waba_id };
          }
        } catch (e) { /* other facebook.com messages */ }
      };
      window.addEventListener('message', onMessage);

      const done = () => { window.removeEventListener('message', onMessage); this.waBusy = false; };

      this.loadFacebookSdk(cfg).then(() => {
        window.FB.login((response) => {
          const code = response && response.authResponse && response.authResponse.code;
          if (!code) { done(); return; } // closed or cancelled
          if (!session.phone_number_id) {
            done();
            this.flashMessage = { tone: 'is-error', text: 'Signup finished without a phone number. Please try again.' };
            return;
          }
          window.axios.post(cfg.store_url, { code, phone_number_id: session.phone_number_id, waba_id: session.waba_id })
            .then(({ data }) => {
              this.flashMessage = { tone: 'is-success', text: data.message || 'WhatsApp number connected.' };
              setTimeout(() => window.location.reload(), 900);
            })
            .catch((error) => {
              this.flashMessage = { tone: 'is-error', text: (error.response && error.response.data && error.response.data.message) || 'Couldn’t connect WhatsApp.' };
            })
            .finally(done);
        }, {
          config_id: cfg.config_id,
          response_type: 'code',
          override_default_response_type: true,
          extras: { setup: {}, featureType: '', sessionInfoVersion: '3' }
        });
      }).catch(() => {
        done();
        this.flashMessage = { tone: 'is-error', text: 'Couldn’t load Facebook. Check your connection or ad blocker and try again.' };
      });
    },

    disconnect(card, conn) {
      this.$set(this.busy, conn.id, 'disconnect');

      window.axios.delete(this.url('disconnect', conn.id))
        .then(({ data }) => {
          const i = this.cards.indexOf(card);
          this.$set(this.cards, i, data.card);
          this.confirming = null;
          this.flashMessage = { tone: 'is-success', text: 'Disconnected. Reconnect at any time from this page.' };
        })
        .catch(() => { this.flashMessage = { tone: 'is-error', text: 'Couldn’t disconnect. Please try again.' }; })
        .finally(() => this.$delete(this.busy, conn.id));
    }
  }
};
</script>

<style scoped>
.ch {
  --ink: #161B2B; --text: #4B5263; --muted: #8A92A3; --line: #E7E9F0; --line-soft: #F1F3F7;
  --brand: #6D4AFF; --brand-2: #8F6BFF; --brand-soft: #F2EEFF;
  --fb: linear-gradient(180deg, #18ACFE 0%, #0163E0 100%);
  --ig: radial-gradient(circle at 30% 107%, #FDF497 0%, #FDF497 5%, #FD5949 45%, #D6249F 60%, #285AEB 90%);
  --wa: linear-gradient(180deg, #5FFC7B 0%, #28D146 100%);
  --meta: linear-gradient(135deg, #0866FF 0%, #00A3FF 100%);
  padding: 24px; display: flex; flex-direction: column; gap: 20px;
}

.ch-head { display: flex; align-items: center; gap: 14px; }
.ch-head-icon { width: 48px; height: 48px; border-radius: 14px; display: grid; place-items: center; font-size: 24px; color: var(--brand); background: linear-gradient(135deg, #F2EEFF, #E8F0FF); flex-shrink: 0; }
.ch-title { margin: 0 0 2px; font-size: 26px; font-weight: 700; letter-spacing: -.01em; color: var(--ink); line-height: 1.25; }
.ch-sub { margin: 0; color: var(--muted); font-size: 13.5px; line-height: 1.5; }

.ch-flash {
  position: fixed; right: 24px; bottom: calc(24px + env(safe-area-inset-bottom, 0px)); z-index: 1100; max-width: min(440px, calc(100vw - 32px));
  display: flex; align-items: center; gap: 10px; padding: 13px 14px 13px 16px; border-radius: 14px; font-size: 13.5px; font-weight: 500;
  box-shadow: 0 18px 40px rgba(16,24,40,.18); animation: ch-toast-in .22s ease-out;
}
[dir="rtl"] .ch-flash { right: auto; left: 24px; }
@keyframes ch-toast-in { from { opacity: 0; transform: translateY(8px); } to { opacity: 1; transform: none; } }
@media (prefers-reduced-motion: reduce) { .ch-flash { animation: none; } }
@media (max-width: 575.98px) { .ch-flash { right: 16px; left: 16px; bottom: 16px; max-width: none; } }
.ch-flash i { font-size: 18px; }
.ch-flash.is-success { background: #E8F8EE; color: #166534; }
.ch-flash.is-error { background: #FDECEC; color: #B42318; }
.ch-flash-x { margin-left: auto; border: none; background: none; color: inherit; font-size: 18px; cursor: pointer; }

.ch-inline-form { display: inline-flex; margin: 0; }
.ch-step-actions { display: flex; gap: 8px; flex-wrap: wrap; justify-content: flex-end; }
.ch-step.has-form { flex-wrap: wrap; }
.ch-wa-form { flex-basis: 100%; display: flex; flex-direction: column; gap: 12px; padding-top: 14px; margin-top: 4px; border-top: 1px dashed var(--line); }
.ch-wa-form p { margin: 0; font-size: 12.5px; color: var(--muted); }
.ch-wa-fields { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 10px; }
.ch-wa-fields label { display: flex; flex-direction: column; gap: 5px; font-size: 12px; font-weight: 600; color: var(--text); }
.ch-wa-fields label.is-wide { grid-column: 1 / -1; }
.ch-wa-fields input { height: 38px; padding: 0 12px; border: 1px solid var(--line); border-radius: 10px; font-size: 13px; color: var(--ink); background: #fff; }
.ch-wa-fields input:focus { outline: none; border-color: #0866FF; box-shadow: 0 0 0 3px rgba(8, 102, 255, .12); }
.ch-wa-form .ch-btn { align-self: flex-start; }
@media (max-width: 575px) { .ch-wa-fields { grid-template-columns: 1fr; } }
.ch-wizard { display: flex; align-items: center; gap: 14px; flex-wrap: wrap; padding: 16px 18px; border-radius: 16px; background: linear-gradient(135deg, #EEF4FF 0%, #F3F8FF 100%); border: 1px solid #D6E4FF; }
.ch-wizard.is-done { background: #E8F8EE; border-color: #C9EDD6; }
.ch-wizard-icon { width: 40px; height: 40px; border-radius: 12px; display: grid; place-items: center; background: var(--meta); color: #fff; font-size: 20px; flex-shrink: 0; }
.ch-wizard.is-done .ch-wizard-icon { background: #16A34A; }
.ch-wizard-text { flex: 1; min-width: 200px; display: flex; flex-direction: column; gap: 2px; }
.ch-wizard-text strong { color: var(--ink); font-size: 14.5px; }
.ch-wizard-text span { color: var(--text); font-size: 13px; }
.ch-wizard-bar { margin-top: 8px; height: 6px; border-radius: 6px; background: rgba(8, 102, 255, .14); overflow: hidden; }
.ch-wizard-bar span { display: block; height: 100%; background: #0866FF; border-radius: 6px; transition: width .3s; }
.ch-wizard.is-done .ch-wizard-bar { display: none; }
.ch-wizard-actions { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; }

.ch-card { background: #fff; border: 1px solid var(--line); border-radius: 18px; box-shadow: 0 1px 2px rgba(16,24,40,.04), 0 8px 24px rgba(16,24,40,.04); padding: 22px 24px; display: flex; flex-direction: column; gap: 18px; }
.ch-card-head { display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap; }
.ch-brand { display: flex; align-items: center; gap: 14px; }
.ch-brand h2 { margin: 0; font-size: 18px; font-weight: 700; color: var(--ink); line-height: 1.3; }
.ch-brand p { margin: 2px 0 0; font-size: 12.5px; color: var(--muted); }
.ch-brand-stack { display: inline-flex; }
.ch-brand-stack .ch-badge + .ch-badge { margin-left: -10px; }
.ch-badge { width: 40px; height: 40px; border-radius: 12px; display: grid; place-items: center; color: #fff; font-size: 20px; border: 2px solid #fff; box-shadow: 0 4px 10px rgba(16,24,40,.12); }

.is-facebook { background: var(--fb); }
.is-instagram { background: var(--ig); }
.is-whatsapp { background: var(--wa); }
.is-meta { background: var(--meta); }
.is-google { background: #4285F4; }
.is-x { background: #000000; }
.is-linkedin { background: linear-gradient(180deg, #0A66C2 0%, #004182 100%); }
.is-tiktok, .is-threads { background: #000000; }
.is-pinterest { background: linear-gradient(180deg, #F0002A 0%, #BD001C 100%); }
.is-snapchat { background: #FFFC00; color: #000000; }
.is-youtube { background: linear-gradient(180deg, #FF3D3D 0%, #E60000 100%); }

.ch-pill { display: inline-flex; align-items: center; gap: 6px; height: 32px; padding: 0 12px; border-radius: 999px; font-size: 12.5px; font-weight: 700; }
.ch-pill.is-ok { background: #E8F8EE; color: #16A34A; }
.ch-pill.is-warn { background: #FFF6E5; color: #D97706; }
.ch-pill.is-muted { background: #F1F3F7; color: #64748B; }

.ch-steps { display: flex; flex-direction: column; gap: 10px; }
.ch-step { display: flex; align-items: center; justify-content: space-between; gap: 14px; padding: 14px 16px; border: 1px solid var(--line); border-radius: 14px; background: #fff; }
.ch-step.is-primary { background: linear-gradient(180deg, #F7FAFF, #fff); border-color: #CFE0FF; }
.ch-step.is-off { background: #FAFBFC; }
.ch-step-main { display: flex; align-items: center; gap: 12px; min-width: 0; }
.ch-step-main strong { display: flex; align-items: center; gap: 8px; color: var(--ink); font-size: 14px; }
.ch-step-desc { display: block; color: var(--muted); font-size: 12.5px; margin-top: 2px; }
.ch-step-note { display: inline-flex; align-items: center; gap: 4px; margin-top: 4px; color: #B45309; font-size: 12px; }
.ch-step-icon { width: 38px; height: 38px; border-radius: 11px; flex-shrink: 0; display: grid; place-items: center; color: #fff; font-size: 19px; }
.ch-step-icon.sm { width: 32px; height: 32px; border-radius: 9px; font-size: 16px; }
.ch-mini-ok { display: inline-flex; align-items: center; gap: 2px; font-size: 11px; font-weight: 700; color: #16A34A; background: #E8F8EE; padding: 1px 7px; border-radius: 6px; }

.ch-btn { display: inline-flex; align-items: center; justify-content: center; gap: 6px; height: 40px; padding: 0 16px; border-radius: 11px; font-size: 13.5px; font-weight: 600; text-decoration: none; border: 1px solid transparent; cursor: pointer; white-space: nowrap; transition: transform .15s, box-shadow .15s, background .15s, color .15s, border-color .15s; }
.ch-btn.sm { height: 34px; padding: 0 12px; font-size: 12.5px; border-radius: 10px; }
.ch-btn-primary { background: var(--meta); color: #fff; box-shadow: 0 6px 16px rgba(8,102,255,.28); }
.ch-btn-primary:hover { color: #fff; transform: translateY(-1px); box-shadow: 0 10px 22px rgba(8,102,255,.32); }
.ch-btn-ghost { background: #fff; border-color: var(--line); color: var(--text); }
.ch-btn-ghost:hover { border-color: #C9D7F5; color: #0866FF; }
.ch-btn-danger-ghost { background: #fff; border-color: var(--line); color: #B42318; }
.ch-btn-danger-ghost:hover { border-color: #F5C2C0; background: #FFF7F7; }
.ch-btn-danger { background: #DC2626; color: #fff; }
.ch-btn-disabled { background: #F1F3F7; color: var(--muted); cursor: default; }
.ch-btn:disabled { opacity: .6; cursor: not-allowed; transform: none; }

.ch-empty { text-align: center; padding: 18px 12px 6px; }
.ch-empty-art { width: 64px; height: 64px; margin: 0 auto 12px; border-radius: 18px; display: grid; place-items: center; background: #EEF4FF; color: #0866FF; font-size: 30px; }
.ch-empty h3 { margin: 0 0 6px; padding: 0; font-size: 16px; font-weight: 700; line-height: 1.35; color: var(--ink); }
.ch-empty p { margin: 0 auto 14px; max-width: 520px; color: var(--muted); font-size: 13px; line-height: 1.55; }
.ch-gets { list-style: none; margin: 0 auto; padding: 0; display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 8px; max-width: 640px; text-align: left; }
.ch-gets li { display: flex; align-items: center; gap: 8px; padding: 10px 12px; border-radius: 12px; background: #F8FAFF; color: var(--text); font-size: 12.5px; }
.ch-gets i { color: #0866FF; font-size: 17px; }

.ch-conn { border-top: 1px solid var(--line-soft); padding-top: 18px; display: flex; flex-direction: column; gap: 14px; }
.ch-conn-head { display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap; }
.ch-conn-id { display: flex; align-items: center; gap: 12px; }
.ch-conn-id strong { color: var(--ink); font-size: 14px; }
.ch-conn-meta { display: flex; flex-wrap: wrap; align-items: center; gap: 12px; margin-top: 3px; font-size: 12px; color: var(--muted); }
.ch-conn-meta i { font-size: 14px; vertical-align: -2px; }
.ch-conn-actions { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
.ch-confirm { display: inline-flex; align-items: center; gap: 8px; font-size: 12.5px; color: #B42318; font-weight: 600; }

.ch-status { display: inline-flex; align-items: center; gap: 5px; font-weight: 700; }
.ch-dot { width: 7px; height: 7px; border-radius: 50%; background: currentColor; display: inline-block; }
.ch-status.is-ok { color: #16A34A; } .ch-status.is-warn { color: #D97706; } .ch-status.is-bad { color: #DC2626; } .ch-status.is-muted { color: #64748B; }
.ch-muted { color: var(--muted); font-size: 12px; }
.ch-warn-text { color: #D97706; font-weight: 600; }

.ch-alert { display: flex; align-items: center; gap: 12px; padding: 12px 14px; border-radius: 12px; font-size: 13px; }
.ch-alert i { font-size: 20px; }
.ch-alert div { flex: 1; display: flex; flex-direction: column; gap: 2px; }
.ch-alert.is-warn { background: #FFF8EB; color: #92400E; }
.ch-alert.is-bad, .ch-alert.is-muted { background: #FDECEC; color: #991B1B; }
.ch-alert.is-info { background: #EEF4FF; color: #1E3A8A; }

.ch-caps { display: flex; flex-wrap: wrap; gap: 8px; }
.ch-cap { display: inline-flex; align-items: center; gap: 6px; height: 32px; padding: 0 12px; border-radius: 10px; background: #F4F5F9; color: var(--muted); font-size: 12.5px; font-weight: 600; }
.ch-cap.is-on { background: #EEF4FF; color: #0B4FD1; }
.ch-cap i { font-size: 15px; }
.ch-cap-up { margin-left: 4px; color: var(--brand); font-weight: 700; text-decoration: none; }
.ch-cap-up:hover { text-decoration: underline; }

.ch-group { display: flex; flex-direction: column; gap: 8px; }
.ch-group-head { display: flex; align-items: center; gap: 8px; }
.ch-group-head h4 { margin: 0; padding: 0; line-height: 1.35; display: flex; align-items: center; gap: 6px; font-size: 13px; font-weight: 700; color: var(--ink); }
.ch-group-head h4 i { font-size: 16px; color: var(--muted); }
.ch-count { font-size: 11px; font-weight: 700; background: #F1F3F7; color: var(--text); padding: 1px 7px; border-radius: 6px; }
.ch-assets { display: flex; flex-direction: column; border: 1px solid var(--line); border-radius: 14px; overflow: hidden; }
.ch-asset { display: flex; align-items: center; gap: 12px; padding: 12px 14px; background: #fff; transition: background .15s, opacity .15s; }
.ch-asset + .ch-asset { border-top: 1px solid var(--line-soft); }
.ch-asset:hover { background: #FBFCFF; }
.ch-asset.is-off .ch-avatar, .ch-asset.is-off .ch-asset-id { opacity: .5; }
.ch-avatar { width: 38px; height: 38px; border-radius: 50%; flex-shrink: 0; overflow: hidden; display: grid; place-items: center; color: #fff; font-size: 17px; }
.ch-avatar img { width: 100%; height: 100%; object-fit: cover; background: #fff; }
.ch-asset-id { flex: 1; min-width: 0; display: flex; flex-direction: column; }
.ch-asset-id strong { font-size: 13.5px; color: var(--ink); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.ch-asset-id small { font-size: 12px; color: var(--muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.ch-asset-id small.ch-dup-note { white-space: normal; color: #B45309; margin-top: 2px; display: flex; gap: 4px; align-items: flex-start; }
.ch-asset-caps { display: flex; flex-wrap: wrap; gap: 6px; justify-content: flex-end; }
.ch-toggle-pill { display: inline-flex; align-items: center; gap: 4px; height: 28px; padding: 0 10px; border-radius: 8px; border: 1px solid var(--line); background: #fff; color: var(--muted); font-size: 12px; font-weight: 600; cursor: pointer; transition: all .15s; }
.ch-toggle-pill i { font-size: 14px; }
.ch-toggle-pill.is-on { background: #EEF4FF; border-color: #BFD3FF; color: #0B4FD1; }
.ch-toggle-pill:hover:not(:disabled) { border-color: #9DBBFF; }
.ch-toggle-pill:disabled { opacity: .6; cursor: progress; }

.ch-switch { position: relative; width: 40px; height: 22px; flex-shrink: 0; cursor: pointer; }
.ch-switch input { opacity: 0; width: 0; height: 0; }
.ch-switch span { position: absolute; inset: 0; border-radius: 999px; background: #D5D9E2; transition: background .15s; }
.ch-switch span::after { content: ""; position: absolute; top: 3px; left: 3px; width: 16px; height: 16px; border-radius: 50%; background: #fff; box-shadow: 0 1px 3px rgba(0,0,0,.2); transition: transform .15s; }
.ch-switch input:checked + span { background: #0866FF; }
.ch-switch input:checked + span::after { transform: translateX(18px); }
.ch-switch input:disabled + span { opacity: .5; cursor: not-allowed; }
.ch-none { margin: 0; }


[dir="rtl"] .ch-brand-stack .ch-badge + .ch-badge { margin-left: 0; margin-right: -10px; }
[dir="rtl"] .ch-flash-x { margin-left: 0; margin-right: auto; }
[dir="rtl"] .ch-switch span::after { left: auto; right: 3px; }
[dir="rtl"] .ch-switch input:checked + span::after { transform: translateX(-18px); }

@media (max-width: 767.98px) {
  .ch { padding: 14px; }
  .ch-card { padding: 16px; }
  .ch-step { flex-direction: column; align-items: stretch; }
  .ch-asset { flex-wrap: wrap; }
  .ch-asset-caps { width: 100%; justify-content: flex-start; padding-left: 50px; }
  .ch-gets { grid-template-columns: 1fr; }
}

/* =========================================================
   Overview: summary, tabs, account grid, sidebar
========================================================= */
.ch-layout { display: grid; grid-template-columns: minmax(0, 1fr) 320px; gap: 22px; align-items: start; }
.ch-main { display: flex; flex-direction: column; gap: 18px; min-width: 0; }
.ch-side { display: flex; flex-direction: column; gap: 18px; position: sticky; top: 90px; }

.ch-hero {
  display: flex; align-items: center; gap: 18px; flex-wrap: wrap; padding: 22px 24px; border-radius: 18px;
  border: 1px solid #DCE5FB;
  background: radial-gradient(120% 160% at 0% 0%, #E9F0FF 0%, rgba(233,240,255,0) 60%), linear-gradient(180deg, #F7F9FF 0%, #F3F6FE 100%);
}
.ch-hero-icon { width: 60px; height: 60px; border-radius: 50%; display: grid; place-items: center; font-size: 28px; color: #fff; flex-shrink: 0;
  background: linear-gradient(135deg, #3B82F6, #2563EB); box-shadow: 0 10px 24px rgba(37,99,235,.3); }
.ch-hero-text { flex: 1 1 260px; min-width: 0; }
.ch-hero-text h2 { margin: 0 0 4px; font-size: 17px; font-weight: 700; color: var(--ink); }
.ch-hero-text p { margin: 0; font-size: 13px; color: var(--text); line-height: 1.55; max-width: 52ch; }
.ch-hero-stats { display: flex; background: #fff; border: 1px solid var(--line); border-radius: 14px; padding: 6px; }
.ch-hero-stat { display: flex; flex-direction: column; gap: 4px; min-width: 112px; padding: 8px 14px; border: none; background: none; text-align: left; cursor: pointer; border-radius: 10px; }
.ch-hero-stat + .ch-hero-stat { border-left: 1px solid var(--line-soft); border-radius: 0 10px 10px 0; }
.ch-hero-stat:hover { background: #F7F9FF; }
.ch-hero-stat-top { display: flex; align-items: center; gap: 8px; }
.ch-hero-stat-top strong { font-size: 24px; font-weight: 700; color: var(--ink); line-height: 1; font-variant-numeric: tabular-nums; }
.ch-hero-stat small { font-size: 11.5px; color: var(--muted); }
.ch-hero-stat-icon { width: 24px; height: 24px; border-radius: 50%; display: grid; place-items: center; font-size: 13px; }
.ch-hero-stat-icon.is-blue, .ch-quick-icon.is-blue { background: #E8F0FF; color: #2563EB; }
.ch-hero-stat-icon.is-purple, .ch-quick-icon.is-purple { background: #F3E8FF; color: #9333EA; }
.ch-hero-stat-icon.is-green, .ch-quick-icon.is-green { background: #E7F7EE; color: #16A34A; }
.ch-quick-icon.is-violet { background: #F2EEFF; color: var(--brand); }

.ch-tabs { display: flex; flex-wrap: nowrap; overflow-x: auto; scrollbar-width: none; gap: 4px; padding: 5px; background: #fff; border: 1px solid var(--line); border-radius: 14px; width: fit-content; max-width: 100%; }
.ch-tab { flex-shrink: 0; display: inline-flex; align-items: center; gap: 6px; height: 38px; padding: 0 12px; border: 1px solid transparent; border-radius: 10px; background: none; color: var(--text); font-size: 13px; font-weight: 600; cursor: pointer; white-space: nowrap; }
.ch-tab i { font-size: 15px; color: var(--muted); }
.ch-tab:hover { background: #F7F8FB; }
.ch-tab.is-active { background: #EEF4FF; border-color: #D6E4FF; color: #1D4ED8; }
.ch-tab.is-active i { color: #2563EB; }
.ch-tab-count { min-width: 22px; height: 20px; padding: 0 6px; border-radius: 999px; background: #F1F3F7; color: var(--muted); font-size: 11px; display: inline-grid; place-items: center; }
.ch-tab.is-active .ch-tab-count { background: #DCE8FF; color: #1D4ED8; }

.ch-section { background: #fff; border: 1px solid var(--line); border-radius: 18px; padding: 20px 20px 22px; box-shadow: 0 1px 2px rgba(16,24,40,.04), 0 8px 24px rgba(16,24,40,.03); }
.ch-section-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 12px; margin-bottom: 16px; }
.ch-section-head h3 { margin: 0 0 3px; font-size: 16px; font-weight: 700; color: var(--ink); }
.ch-section-head p { margin: 0; font-size: 12.5px; color: #3B6FD8; }
.ch-section-link { display: inline-flex; align-items: center; gap: 2px; border: none; background: none; color: #2563EB; font-size: 12.5px; font-weight: 600; cursor: pointer; white-space: nowrap; }

.ch-acct-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(230px, 1fr)); gap: 14px; }
.ch-acct { display: flex; flex-direction: column; border: 1px solid var(--line); border-radius: 14px; background: #fff; transition: border-color .15s, box-shadow .15s, transform .15s; min-width: 0; }
.ch-acct:hover { border-color: #D6DDF0; box-shadow: 0 10px 24px rgba(16,24,40,.07); transform: translateY(-1px); }
.ch-acct.is-dim .ch-acct-top, .ch-acct.is-dim .ch-acct-avatar, .ch-acct.is-dim .ch-acct-id { opacity: .6; }
.ch-acct-top { display: flex; align-items: flex-start; gap: 12px; padding: 16px 14px 14px; min-width: 0; }
.ch-acct-logo.ch-badge { width: 44px; height: 44px; border-radius: 50%; font-size: 22px; border: none; box-shadow: 0 6px 14px rgba(16,24,40,.12); flex-shrink: 0; }
.ch-acct-type { flex: 1; min-width: 0; display: flex; flex-direction: column; padding-top: 2px; }
.ch-acct-type strong { font-size: 14.5px; color: var(--ink); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.ch-acct-type small { font-size: 12px; color: var(--muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.ch-state { flex-shrink: 0; height: 22px; padding: 0 8px; border-radius: 6px; display: inline-flex; align-items: center; font-size: 11px; font-weight: 600; white-space: nowrap; max-width: 120px; overflow: hidden; text-overflow: ellipsis; }
.ch-state.is-ok { background: #E7F7EE; color: #15803D; }
.ch-state.is-warn { background: #FFF6E5; color: #B45309; }
.ch-state.is-bad { background: #FDECEC; color: #DC2626; }
.ch-state.is-muted { background: #F1F3F7; color: #64748B; }
.ch-acct-bottom { display: flex; align-items: center; gap: 10px; padding: 12px 8px 12px 14px; border-top: 1px solid var(--line-soft); margin-top: auto; }
.ch-acct-avatar { width: 36px; height: 36px; border-radius: 10px; display: grid; place-items: center; flex-shrink: 0; overflow: hidden; background: #F3F5FA; border: 1px solid var(--line); color: var(--text); font-size: 17px; }
.ch-acct-avatar img { width: 100%; height: 100%; object-fit: cover; }
.ch-acct-id { flex: 1; min-width: 0; display: flex; flex-direction: column; }
.ch-acct-id strong { font-size: 13px; color: var(--ink); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.ch-acct-id small { font-size: 11.5px; color: var(--muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; font-variant-numeric: tabular-nums; }

.ch-menu { position: relative; }
.ch-kebab { width: 30px; height: 30px; border-radius: 8px; border: none; background: none; color: #98A0B3; font-size: 18px; display: grid; place-items: center; cursor: pointer; }
.ch-kebab:hover, .ch-kebab[aria-expanded="true"] { background: #F1F3F8; color: var(--ink); }
.ch-menu-list { position: absolute; right: 0; bottom: calc(100% + 6px); z-index: 30; min-width: 210px; padding: 6px; background: #fff; border: 1px solid var(--line); border-radius: 12px; box-shadow: 0 18px 40px rgba(16,24,40,.16); display: flex; flex-direction: column; }
.ch-menu-list a, .ch-menu-list button { display: flex; align-items: center; gap: 9px; width: 100%; padding: 8px 10px; border: none; border-radius: 8px; background: none; color: var(--text); font-size: 13px; font-weight: 500; text-align: left; text-decoration: none; cursor: pointer; }
.ch-menu-list a:hover, .ch-menu-list button:hover:not(:disabled) { background: #F2EEFF; color: var(--brand); }
.ch-menu-list button:disabled { opacity: .5; cursor: default; }
.ch-menu-list i { font-size: 16px; color: var(--muted); }
.ch-menu-list .is-accent, .ch-menu-list .is-accent i { color: #DC2626; }
.ch-menu-sep { height: 1px; margin: 4px 6px; background: var(--line-soft); }
.ch-state .bx-spin { font-size: 13px; margin-right: 4px; }

.ch-acct-add { align-items: center; justify-content: center; text-align: center; gap: 6px; min-height: 148px; padding: 18px; border: 1.5px dashed #CFD6E6; background: #FBFCFF; cursor: pointer; font: inherit; }
.ch-acct-add:hover { border-color: #93B4F5; background: #F5F8FF; }
.ch-acct-add-icon { width: 40px; height: 40px; border-radius: 50%; display: grid; place-items: center; font-size: 22px; color: #2563EB; background: #E8F0FF; }
.ch-acct-add strong { font-size: 13.5px; color: var(--ink); }
.ch-acct-add small { font-size: 12px; color: var(--muted); line-height: 1.45; max-width: 30ch; }


.ch-panel { background: #fff; border: 1px solid var(--line); border-radius: 18px; padding: 18px; box-shadow: 0 1px 2px rgba(16,24,40,.04), 0 8px 24px rgba(16,24,40,.03); position: relative; overflow: hidden; }
.ch-panel h3 { display: flex; align-items: center; gap: 8px; margin: 0 0 14px; font-size: 15px; font-weight: 700; color: var(--ink); }
.ch-panel h3 i { font-size: 18px; color: var(--brand); }
.ch-quick-form { margin: 0; }
.ch-quick { display: flex; align-items: center; gap: 12px; width: 100%; padding: 12px; border: 1px solid var(--line); border-radius: 12px; background: #fff; color: inherit; text-decoration: none; text-align: left; cursor: pointer; font: inherit; transition: border-color .15s, box-shadow .15s; }
.ch-quick + .ch-quick, .ch-quick-form + .ch-quick, .ch-quick + .ch-quick-form { margin-top: 10px; }
.ch-quick:hover { border-color: #CFC4FF; box-shadow: 0 6px 16px rgba(16,24,40,.06); color: inherit; }
.ch-quick > span:nth-child(2) { flex: 1; min-width: 0; display: flex; flex-direction: column; }
.ch-quick strong { font-size: 13px; color: var(--ink); }
.ch-quick small { font-size: 11.5px; color: var(--muted); }
.ch-quick > .bx-chevron-right { color: #B4BBCB; font-size: 18px; }
.ch-quick-icon { width: 40px; height: 40px; border-radius: 10px; display: grid; place-items: center; font-size: 20px; flex-shrink: 0; }

.ch-donut-wrap { position: relative; width: 150px; height: 150px; margin: 4px auto 14px; }
.ch-donut { width: 100%; height: 100%; transform: rotate(-90deg); }
.ch-donut circle { fill: none; stroke-width: 12; }
.ch-donut-track { stroke: #EEF1F6; }
.ch-donut-seg { stroke-linecap: butt; transition: stroke-dasharray .4s; }
.ch-donut-label { position: absolute; inset: 0; display: flex; flex-direction: column; align-items: center; justify-content: center; text-align: center; }
.ch-donut-label strong { font-size: 28px; font-weight: 700; color: var(--ink); line-height: 1.1; font-variant-numeric: tabular-nums; }
.ch-donut-label small { font-size: 11px; color: var(--muted); line-height: 1.3; }
.ch-legend { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 10px; }
.ch-legend li { display: flex; align-items: center; gap: 8px; font-size: 13px; color: var(--text); }
.ch-legend strong { margin-left: auto; color: var(--ink); font-variant-numeric: tabular-nums; }
.ch-legend .dot { width: 9px; height: 9px; border-radius: 50%; }
.ch-legend .dot.is-ok { background: #16A34A; }
.ch-legend .dot.is-warn { background: #F59E0B; }
.ch-legend .dot.is-bad { background: #EF4444; }

.ch-help { background: linear-gradient(160deg, #fff 55%, #F2F6FF 100%); }
.ch-help h3 i { color: #F59E0B; }
.ch-help p { margin: 0 0 14px; font-size: 12.5px; color: var(--text); line-height: 1.55; max-width: 24ch; }
.ch-help-btn { display: inline-flex; align-items: center; gap: 6px; height: 34px; padding: 0 14px; border-radius: 999px; background: #E8F0FF; color: #1D4ED8; font-size: 12.5px; font-weight: 600; text-decoration: none; position: relative; z-index: 1; }
.ch-help-btn:hover { background: #DCE8FF; color: #1D4ED8; }
.ch-help-art { position: absolute; right: -6px; bottom: -6px; width: 92px; height: 92px; border-radius: 50%; display: grid; place-items: center; font-size: 42px; color: #3B82F6; background: radial-gradient(circle, #DCE8FF 0%, rgba(220,232,255,0) 70%); }

@media (max-width: 1199.98px) {
  .ch-layout { grid-template-columns: minmax(0, 1fr); }
  .ch-side { position: static; display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); }
}
@media (max-width: 575.98px) {
  .ch-hero-stats { width: 100%; }
  .ch-hero-stat { min-width: 0; flex: 1; padding: 8px 10px; }
  .ch-tabs { width: 100%; }
}

/* =========================================================
   Side panel: platform picker + platform detail
========================================================= */
.ch-drawer-backdrop { position: fixed; inset: 0; z-index: 1090; background: rgba(15, 18, 34, .42); backdrop-filter: blur(2px); }
.ch-drawer {
  position: fixed; top: 0; right: 0; bottom: 0; z-index: 1091; width: min(620px, 100vw);
  display: flex; flex-direction: column; background: #F7F8FC;
  box-shadow: -24px 0 60px rgba(16, 24, 40, .22); border-radius: 20px 0 0 20px; overflow: hidden;
}
[dir="rtl"] .ch-drawer { right: auto; left: 0; border-radius: 0 20px 20px 0; }
.ch-drawer-head {
  display: flex; align-items: center; gap: 12px; padding: 18px 20px; background: #fff; border-bottom: 1px solid var(--line);
  padding-top: calc(18px + env(safe-area-inset-top, 0px));
}
.ch-drawer-title { flex: 1; min-width: 0; }
.ch-drawer-title h2 { margin: 0; font-size: 18px; font-weight: 700; color: var(--ink); line-height: 1.3; }
.ch-drawer-title p { margin: 2px 0 0; font-size: 12.5px; color: var(--muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.ch-drawer-icon-btn { width: 38px; height: 38px; flex-shrink: 0; border-radius: 10px; border: 1px solid var(--line); background: #fff; color: var(--text); font-size: 20px; display: grid; place-items: center; cursor: pointer; }
.ch-drawer-icon-btn:hover { border-color: #CFC4FF; color: var(--brand); }
.ch-drawer-logos, .ch-pick-logos { display: inline-flex; flex-shrink: 0; }
.ch-drawer-logos .ch-badge, .ch-pick-logos .ch-badge { width: 38px; height: 38px; border-radius: 12px; font-size: 19px; }
.ch-drawer-logos .ch-badge + .ch-badge, .ch-pick-logos .ch-badge + .ch-badge { margin-left: -12px; }
.ch-drawer-body { flex: 1; overflow-y: auto; padding: 18px 20px calc(24px + env(safe-area-inset-bottom, 0px)); }

.ch-fade-enter-active, .ch-fade-leave-active { transition: opacity .2s ease; }
.ch-fade-enter, .ch-fade-leave-to { opacity: 0; }
.ch-slide-enter-active, .ch-slide-leave-active { transition: transform .26s cubic-bezier(.2, .8, .2, 1); }
.ch-slide-enter, .ch-slide-leave-to { transform: translateX(104%); }
[dir="rtl"] .ch-slide-enter, [dir="rtl"] .ch-slide-leave-to { transform: translateX(-104%); }
@media (prefers-reduced-motion: reduce) { .ch-slide-enter-active, .ch-slide-leave-active, .ch-fade-enter-active, .ch-fade-leave-active { transition: none; } }

/* Picker list (panel) */
.ch-pick-list { display: flex; flex-direction: column; gap: 10px; }
.ch-pick-row { display: flex; align-items: center; gap: 14px; width: 100%; padding: 14px 16px; border: 1px solid var(--line); border-radius: 14px; background: #fff; text-align: left; cursor: pointer; font: inherit; transition: border-color .15s, box-shadow .15s, transform .15s; }
.ch-pick-row:hover { border-color: #CFC4FF; box-shadow: 0 8px 20px rgba(16,24,40,.07); transform: translateY(-1px); }
.ch-pick-row-text { flex: 1; min-width: 0; display: flex; flex-direction: column; }
.ch-pick-row-text strong { font-size: 14.5px; color: var(--ink); }
.ch-pick-row-text small { font-size: 12px; color: var(--muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.ch-pick-row > .bx-chevron-right { font-size: 20px; color: #B4BBCB; }
.ch-state.is-brand { background: var(--brand-soft); color: var(--brand); }

/* Picker grid (first run, on the page) */
.ch-pick-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(210px, 1fr)); gap: 14px; }
.ch-pick { display: flex; flex-direction: column; align-items: flex-start; gap: 6px; padding: 18px; border: 1px solid var(--line); border-radius: 16px; background: #fff; text-align: left; cursor: pointer; font: inherit; transition: border-color .15s, box-shadow .15s, transform .15s; }
.ch-pick:hover { border-color: #CFC4FF; box-shadow: 0 12px 28px rgba(16,24,40,.08); transform: translateY(-2px); }
.ch-pick .ch-pick-logos { margin-bottom: 6px; }
.ch-pick strong { font-size: 15px; color: var(--ink); }
.ch-pick small { font-size: 12px; color: var(--muted); line-height: 1.45; }
.ch-pick-caps { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 4px; }
.ch-pick-caps span { display: inline-flex; align-items: center; gap: 4px; height: 24px; padding: 0 8px; border-radius: 999px; background: #F4F5F9; color: var(--text); font-size: 11px; font-weight: 600; }
.ch-pick-cta { margin-top: auto; padding-top: 10px; display: inline-flex; align-items: center; gap: 4px; color: var(--brand); font-size: 13px; font-weight: 700; }

/* The platform card inside the panel: lighter, card-in-panel look */
.ch-card-drawer { background: transparent; border: none; box-shadow: none; padding: 0; gap: 14px; }
.ch-card-drawer > .ch-card-head { display: none; }
.ch-card-drawer .ch-steps { gap: 10px; }
.ch-card-drawer .ch-step { border-radius: 14px; padding: 14px; box-shadow: 0 1px 2px rgba(16,24,40,.04); }
.ch-card-drawer .ch-step.is-primary { background: #fff; border-color: #D6DDF0; box-shadow: 0 6px 18px rgba(16,24,40,.06); }
.ch-card-drawer .ch-step-actions { flex-shrink: 0; }
.ch-card-drawer .ch-btn-primary { background: linear-gradient(135deg, var(--brand), var(--brand-2)); box-shadow: 0 6px 16px rgba(109,74,255,.28); }
.ch-card-drawer .ch-btn-primary:hover { box-shadow: 0 10px 22px rgba(109,74,255,.34); }
.ch-card-drawer .ch-empty { background: #fff; border: 1px dashed #D6DDF0; border-radius: 14px; padding: 18px; }
.ch-card-drawer .ch-gets { grid-template-columns: minmax(0, 1fr); }
.ch-card-drawer .ch-conn { background: #fff; border: 1px solid var(--line); border-radius: 16px; padding: 16px; box-shadow: 0 1px 2px rgba(16,24,40,.04); }
.ch-card-drawer .ch-conn-head { align-items: flex-start; }
.ch-card-drawer .ch-asset-caps { justify-content: flex-start; }
@media (max-width: 575.98px) {
  .ch-drawer { border-radius: 0; }
  .ch-card-drawer .ch-step { flex-wrap: wrap; }
  .ch-card-drawer .ch-step-actions { width: 100%; justify-content: flex-start; }
  .ch-card-drawer .ch-asset { flex-wrap: wrap; }
}
</style>
