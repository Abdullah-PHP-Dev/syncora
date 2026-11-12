<template>
  <div class="ch" :dir="i18n.dir">

    <div v-if="flashMessage" class="ch-flash" :class="flashMessage.tone" role="status" aria-live="polite">
      <i class="bx" :class="flashMessage.tone === 'is-error' ? 'bx-error-circle' : 'bx-check-circle'"></i>
      <span>{{ flashMessage.text }}</span>
      <button type="button" class="ch-flash-x" @click="flashMessage = null" :aria-label="t('dismiss')"><i class="bx bx-x"></i></button>
    </div>

    <!-- ===================== PLATFORMS VIEW ===================== -->
    <template v-if="mode === 'platforms'">
      <header class="ch-head">
        <div>
          <h1 class="ch-title">{{ t('ui.title') }}</h1>
          <p class="ch-sub">{{ t('hub.subtitle') }}</p>
        </div>
        <div class="ch-head-actions">
          <button type="button" class="ch-btn ch-btn-secondary" @click="setMode('accounts')"><i class="bx bx-list-ul"></i> {{ t('hub.all_accounts') }}</button>
          <button type="button" class="ch-btn ch-btn-primary" @click="openConnect('picker')"><i class="bx bx-plus"></i> {{ t('ui.connect_platform') }}</button>
        </div>
      </header>

      <!-- Onboarding: Posts / Inbox / Comments stay locked (EnsureConnectedAccount) until one account works. -->
      <div v-if="!eligible" class="ch-banner is-gate" role="note">
        <span class="ch-banner-icon"><i class="bx bx-lock-alt"></i></span>
        <div class="ch-banner-text"><strong>{{ t('gate.title') }}</strong><span>{{ t('gate.body') }}</span></div>
      </div>

      <!-- "Connect all recommended": one consent per step, back here between them. -->
      <div v-if="wizardState" class="ch-banner" :class="{ 'is-done': !wizardState.next }">
        <span class="ch-banner-icon"><i class="bx" :class="wizardState.next ? 'bx-rocket' : 'bx-check-double'"></i></span>
        <div class="ch-banner-text">
          <template v-if="wizardState.next">
            <strong>{{ t('wizard.step', { done: wizardState.done + 1, total: wizardState.total, label: wizardState.next.label }) }}</strong>
            <span>{{ t('wizard.next', { label: wizardState.next.label }) }}</span>
          </template>
          <template v-else>
            <strong>{{ t('wizard.finished') }}</strong>
            <span>{{ wizardState.skipped ? tc('wizard.skipped', wizardState.skipped) : t('wizard.all_connected') }}</span>
          </template>
          <div class="ch-progress"><span :style="{ width: (100 * wizardState.done / wizardState.total) + '%' }"></span></div>
        </div>
        <div v-if="wizardState.next" class="ch-banner-actions">
          <form method="POST" :action="urls.wizard_finish" class="ch-inline-form">
            <input type="hidden" name="_token" :value="csrf"><input type="hidden" name="_method" value="DELETE">
            <button type="submit" class="ch-btn ch-btn-ghost sm">{{ t('wizard.finish_later') }}</button>
          </form>
          <form method="POST" :action="urls.wizard_skip" class="ch-inline-form">
            <input type="hidden" name="_token" :value="csrf">
            <button type="submit" class="ch-btn ch-btn-ghost sm">{{ t('wizard.skip') }}</button>
          </form>
          <a :href="wizardState.next.url" class="ch-btn ch-btn-primary sm">{{ t('wizard.continue') }} <i class="bx bx-right-arrow-alt"></i></a>
        </div>
      </div>

      <!-- Guided authorization (AuthorizationFlow): the planned consents, then what was granted. -->
      <section v-if="flowState" class="ch-banner is-flow" role="region" :aria-label="t('flow.title', { platform: flowState.label })">
        <span class="ch-banner-icon"><i class="bx" :class="flowState.complete ? 'bx-check-double' : 'bx-shield-quarter'"></i></span>
        <div class="ch-banner-text">
          <strong>{{ flowState.complete ? flowSummaryTitle : t('flow.title', { platform: flowState.label }) }}</strong>
          <span v-if="flowState.next">{{ t('flow.next', { label: flowState.next.label }) }} — {{ flowState.next.description }}</span>
          <span v-else>{{ t('flow.done_body', { platform: flowState.label }) }}</span>
          <span v-if="flowState.next && flowState.next.state === 'failed'" class="ch-warn-text"><i class="bx bx-error-circle"></i> {{ t('flow.failed', { platform: flowState.label }) }}</span>
          <span v-if="flowState.complete" class="ch-chips">
            <span v-for="r in flowState.results" :key="r.key" class="ch-chip" :class="r.status === 'connected' ? 'is-on' : 'is-off'">
              <i class="bx" :class="r.status === 'connected' ? 'bx-check' : 'bx-x'"></i> {{ capShort(r.key) }}
            </span>
          </span>
        </div>
        <div class="ch-banner-actions">
          <template v-if="flowState.next">
            <form method="POST" :action="urls.flow_skip" class="ch-inline-form">
              <input type="hidden" name="_token" :value="csrf">
              <button type="submit" class="ch-btn ch-btn-ghost sm">{{ t('flow.skip') }}</button>
            </form>
            <form method="POST" :action="urls.flow_continue" class="ch-inline-form">
              <input type="hidden" name="_token" :value="csrf">
              <button type="submit" class="ch-btn ch-btn-primary sm">{{ flowState.next.state === 'failed' ? t('flow.retry') : t('flow.continue', { platform: flowState.label }) }}</button>
            </form>
          </template>
          <template v-else>
            <form v-if="flowState.missing.length" method="POST" :action="authorizeUrl(flowState.platform)" class="ch-inline-form">
              <input type="hidden" name="_token" :value="csrf">
              <input v-for="c in flowState.missing" :key="c" type="hidden" name="capabilities[]" :value="c">
              <button type="submit" class="ch-btn ch-btn-primary sm"><i class="bx bx-refresh"></i> {{ t('flow.complete_missing') }}</button>
            </form>
            <form method="POST" :action="urls.flow_cancel" class="ch-inline-form">
              <input type="hidden" name="_token" :value="csrf">
              <button type="submit" class="ch-btn ch-btn-ghost sm">{{ t('flow.close') }}</button>
            </form>
          </template>
        </div>
      </section>

      <!-- Health -->
      <section class="ch-kpis" :aria-label="t('ui.title')">
        <div class="ch-kpi">
          <small>{{ t('hub.stats.platforms') }}</small>
          <strong>{{ num(stats.platforms) }} <span>{{ t('hub.stats.of', { total: num(stats.total) }) }}</span></strong>
        </div>
        <div class="ch-kpi">
          <small>{{ t('hub.stats.accounts') }}</small>
          <strong>{{ num(stats.accounts) }}</strong>
        </div>
        <button type="button" class="ch-kpi" :class="{ 'is-warn': stats.attention }" @click="filter = 'attention'">
          <small>{{ t('hub.stats.attention') }}</small>
          <strong>{{ num(stats.attention) }}</strong>
        </button>
        <div class="ch-kpi">
          <small>{{ t('hub.stats.checked') }}</small>
          <strong class="ch-kpi-sm"><span class="ch-dot" :class="lastChecked ? 'is-ok' : 'is-muted'"></span>{{ lastChecked ? ago(lastChecked) : t('ui.stats.never') }}</strong>
        </div>
      </section>

      <!-- Meta Business: one sign-in behind WhatsApp, Facebook, Instagram and Meta Ads -->
      <section v-if="metaViews.length" class="ch-meta">
        <header class="ch-meta-head">
          <span class="ch-tile is-metab">M</span>
          <div class="ch-meta-text">
            <h2>{{ t('hub.meta.title') }}</h2>
            <p>{{ metaText }}</p>
          </div>
          <button type="button" class="ch-btn" :class="metaLogin ? 'ch-btn-secondary' : 'ch-btn-primary'" @click="openConnect('facebook')">
            <i class="bx" :class="metaLogin ? 'bx-plus' : 'bxl-meta'"></i> {{ metaLogin ? t('hub.meta.add') : t('hub.meta.connect') }}
          </button>
        </header>
        <div v-for="v in metaViews" :key="v.key" class="ch-row" :class="'is-' + v.status">
          <span class="ch-tile sm" :class="'is-' + v.brand"><i class="bx" :class="v.icon"></i></span>
          <div class="ch-row-main">
            <strong>{{ v.name }} <span v-if="v.recommended && v.status === 'not_connected'" class="ch-tag">{{ t('hub.recommended') }}</span></strong>
            <small :class="{ 'ch-warn-text': v.issue }" :title="detail(v)">{{ detail(v) }}</small>
          </div>
          <div class="ch-row-chips">
            <span v-for="c in v.caps" :key="c" class="ch-chip is-grey">{{ capShort(c) }}</span>
          </div>
          <span class="ch-status" :class="statusTone(v.status)">{{ t('ui.status.' + v.status) }}</span>
          <div class="ch-row-action">
            <a v-if="action(v).url" :href="action(v).url" class="ch-btn sm" :class="action(v).cls" @click="rememberReturn(v.key)">{{ action(v).label }}</a>
            <button v-else type="button" class="ch-btn sm" :class="action(v).cls" :disabled="action(v).disabled" @click="runAction(v)">{{ action(v).label }}</button>
          </div>
        </div>
      </section>

      <!-- Every other platform -->
      <div class="ch-filterbar">
        <h2 class="ch-section-label">{{ t('hub.other') }}</h2>
        <nav class="ch-seg ch-seg-sm" role="tablist">
          <button v-for="f in filters" :key="f.key" type="button" role="tab" class="ch-seg-btn" :class="{ 'is-active': filter === f.key }" :aria-selected="filter === f.key ? 'true' : 'false'" @click="filter = f.key">
            {{ f.label }}
          </button>
        </nav>
      </div>

      <div class="ch-grid">
        <article v-for="v in filtered(mainViews)" :key="v.key" class="ch-card" :class="'is-' + v.status">
          <div class="ch-card-head">
            <span class="ch-tile" :class="'is-' + v.brand"><i class="bx" :class="v.icon"></i></span>
            <div class="ch-card-title">
              <h3>{{ v.name }}</h3>
              <p :title="v.subtitle">{{ v.subtitle }}</p>
            </div>
            <span class="ch-status" :class="statusTone(v.status)">{{ t('ui.status.' + v.status) }}</span>
          </div>
          <p class="ch-card-detail" :class="{ 'ch-warn-text': v.issue }">{{ detail(v) }}</p>
          <div v-if="v.caps.length" class="ch-chips">
            <span v-for="c in v.caps" :key="c" class="ch-chip is-grey">{{ capShort(c) }}</span>
          </div>
          <div class="ch-card-actions">
            <a v-if="action(v).url" :href="action(v).url" class="ch-btn ch-btn-block" :class="action(v).cls" @click="rememberReturn(v.key)">{{ action(v).label }}</a>
            <button v-else type="button" class="ch-btn ch-btn-block" :class="action(v).cls" :disabled="action(v).disabled" @click="runAction(v)">{{ action(v).label }}</button>
          </div>
        </article>
        <p v-if="!filtered(mainViews).length" class="ch-empty-filter">{{ t('ui.card.none_filter') }}</p>
      </div>

      <section v-if="filtered(moreViews).length" class="ch-more">
        <div class="ch-more-head">
          <h2 class="ch-section-label">{{ t('ui.card.more_title') }}</h2>
          <p>{{ t('ui.card.more_subtitle') }}</p>
        </div>
        <div class="ch-more-grid">
          <article v-for="v in filtered(moreViews)" :key="v.key" class="ch-mini">
            <span class="ch-tile sm" :class="'is-' + v.brand"><i class="bx" :class="v.icon"></i></span>
            <div class="ch-mini-text">
              <strong>{{ v.name }}</strong>
              <small :class="{ 'ch-warn-text': v.issue }">{{ v.status === 'not_connected' ? v.subtitle : detail(v) }}</small>
            </div>
            <span v-if="v.status !== 'not_connected'" class="ch-status" :class="statusTone(v.status)">{{ t('ui.status.' + v.status) }}</span>
            <button type="button" class="ch-btn sm" :class="action(v).cls" :disabled="action(v).disabled" @click="runAction(v)">{{ action(v).label }}</button>
          </article>
        </div>
      </section>
    </template>

    <!-- ===================== ACCOUNTS VIEW ===================== -->
    <template v-else>
      <header class="ch-head">
        <div>
          <p class="ch-crumb"><button type="button" class="ch-link" @click="setMode('platforms')">{{ t('ui.accounts.back') }}</button> / {{ t('ui.accounts.all_accounts') }}</p>
          <h1 class="ch-title">{{ t('ui.accounts.title') }}</h1>
          <p class="ch-sub">{{ t('ui.accounts.subtitle', { workspace: workspaceName }) }}</p>
        </div>
        <div class="ch-head-actions">
          <button type="button" class="ch-btn ch-btn-primary" @click="openConnect('picker')"><i class="bx bx-plus"></i> {{ t('ui.accounts.connect') }}</button>
        </div>
      </header>

      <div class="ch-tablebar">
        <label class="ch-search">
          <i class="bx bx-search" aria-hidden="true"></i>
          <span class="sr-only">{{ t('ui.accounts.search') }}</span>
          <input v-model="search" type="search" :placeholder="t('ui.accounts.search')">
        </label>
        <label class="ch-select">
          <span class="sr-only">{{ t('ui.accounts.platform') }}</span>
          <select v-model="platformFilter">
            <option value="">{{ t('ui.accounts.platform') }}</option>
            <option v-for="v in connectedViews" :key="v.key" :value="v.key">{{ v.name }}</option>
          </select>
        </label>
        <label class="ch-select">
          <span class="sr-only">{{ t('hub.accounts.health') }}</span>
          <select v-model="healthFilter">
            <option value="all">{{ t('hub.accounts.health') }}</option>
            <option value="attention">{{ t('hub.accounts.health_attention') }}</option>
            <option value="ok">{{ t('hub.accounts.health_ok') }}</option>
          </select>
        </label>
      </div>

      <div class="ch-table" role="table" :aria-label="t('ui.accounts.title')">
        <div class="ch-tr ch-th" role="row">
          <span role="columnheader">{{ t('ui.accounts.col_account') }}</span>
          <span role="columnheader">{{ t('ui.accounts.col_used') }}</span>
          <span role="columnheader">{{ t('ui.accounts.col_health') }}</span>
          <span role="columnheader" class="ch-td-end">{{ t('hub.accounts.col_access') }}</span>
        </div>
        <button v-for="r in visibleTableRows" :key="r.asset.id" type="button" class="ch-tr" :class="{ 'is-selected': manageAsset === r.asset.id }" role="row" @click="openManage(r.view.key, r.asset.id)">
          <span class="ch-td-account" role="cell">
            <span class="ch-tile sm" :class="'is-' + kindBrand(r)"><i class="bx" :class="kindIcon(r)"></i></span>
            <span class="ch-td-name">
              <strong>{{ assetName(r.asset) }}</strong>
              <small>{{ kindLabel(r) }}</small>
            </span>
          </span>
          <span class="ch-td-used" role="cell">{{ r.asset.enabled_capabilities.length ? r.asset.enabled_capabilities.map(capShort).join(' · ') : t('ui.accounts.not_used') }}</span>
          <span class="ch-td-health" role="cell"><span class="ch-pill" :class="r.health.tone">{{ r.health.label }}</span></span>
          <span class="ch-td-access ch-td-end" :class="'is-' + r.health.tone" role="cell">{{ r.health.access }}</span>
        </button>
        <p v-if="!visibleTableRows.length" class="ch-empty-filter">{{ t('ui.accounts.empty') }}</p>
      </div>
    </template>

    <!-- ===================== CONNECT MODAL ===================== -->
    <transition name="ch-fade">
      <div v-if="connectKey" class="ch-modal-backdrop" @click.self="closeConnect">
        <div class="ch-modal" role="dialog" aria-modal="true" :aria-label="connectTitle" @keydown.esc="closeConnect">

          <!-- Platform picker -->
          <template v-if="connectKey === 'picker'">
            <header class="ch-modal-head">
              <span class="ch-tile is-brand"><i class="bx bx-link"></i></span>
              <div class="ch-modal-title">
                <h2>{{ t('ui.connect_platform') }}</h2>
                <p>{{ t('ui.connect.adding') }} <strong>{{ workspaceName }}</strong></p>
              </div>
              <button type="button" class="ch-icon-btn" :aria-label="t('close')" @click="closeConnect"><i class="bx bx-x"></i></button>
            </header>
            <div class="ch-modal-body">
              <div class="ch-picker">
                <button v-for="v in allViews" :key="v.key" type="button" class="ch-picker-item" :disabled="!v.available && v.status === 'not_connected'" @click="openConnect(v.key)">
                  <span class="ch-tile sm" :class="'is-' + v.brand"><i class="bx" :class="v.icon"></i></span>
                  <span class="ch-picker-text"><strong>{{ v.name }}</strong><small>{{ v.subtitle }}</small></span>
                  <span v-if="v.status !== 'not_connected'" class="ch-status" :class="statusTone(v.status)">{{ t('ui.status.' + v.status) }}</span>
                  <span v-else-if="!v.available" class="ch-muted sm-text">{{ t('ui.connect.unavailable') }}</span>
                  <i v-else class="bx bx-chevron-right" aria-hidden="true"></i>
                </button>
              </div>
            </div>
          </template>

          <template v-else-if="connectViewData">
            <header class="ch-modal-head">
              <span class="ch-tile" :class="isMetaLogin ? 'is-metab' : 'is-' + connectViewData.brand"><template v-if="isMetaLogin">M</template><i v-else class="bx" :class="connectViewData.icon"></i></span>
              <div class="ch-modal-title">
                <h2>{{ connectTitle }}</h2>
                <p v-if="isMetaLogin">{{ t('hub.meta.modal_sub') }} · {{ t('ui.connect.adding') }} <strong>{{ workspaceName }}</strong></p>
                <p v-else>{{ t('ui.connect.adding') }} <strong>{{ workspaceName }}</strong></p>
              </div>
              <ol class="ch-stepper" aria-hidden="true">
                <li v-for="(s, i) in connectSteps" :key="s" :class="{ 'is-current': i === 0 }">
                  <span class="ch-stepper-dot">{{ i + 1 }}</span><span class="ch-stepper-label">{{ t('ui.connect.steps.' + s) }}</span>
                </li>
              </ol>
              <button type="button" class="ch-icon-btn" :aria-label="t('close')" @click="closeConnect"><i class="bx bx-x"></i></button>
            </header>

            <div class="ch-modal-body">
              <p v-if="connectFailed" class="ch-note is-bad" role="alert"><i class="bx bx-error-circle"></i> {{ t('ui.connect.failed') }}</p>

              <!-- Instagram: choose how -->
              <template v-if="connectViewData.flow === 'instagram'">
                <h3 class="ch-modal-h3">{{ t('ui.connect.instagram.question') }}</h3>
                <p class="ch-modal-sub">{{ t('ui.connect.instagram.sub') }}</p>
                <div class="ch-methods">
                  <label v-for="m in instagramMethods" :key="m.key" class="ch-method" :class="{ 'is-picked': connectChoice === m.key, 'is-disabled': !m.available }">
                    <input v-model="connectChoice" type="radio" name="ig-method" :value="m.key" :disabled="!m.available">
                    <span class="ch-method-head">
                      <span class="ch-radio" aria-hidden="true"></span>
                      <span class="ch-tile sm" :class="'is-' + m.brand"><i class="bx" :class="m.icon"></i></span>
                      <span><strong>{{ m.title }} <span v-if="m.recommended" class="ch-tag">{{ t('ui.connect.recommended') }}</span></strong><small>{{ m.sub }}</small></span>
                    </span>
                    <span class="ch-note" :class="m.key === 'meta.login' ? 'is-ok' : 'is-info'"><i class="bx" :class="m.key === 'meta.login' ? 'bx-check' : 'bx-info-circle'"></i> {{ m.note }}</span>
                    <span class="ch-chips">
                      <span v-for="c in m.caps" :key="c.key" class="ch-chip" :class="c.on ? 'is-on' : 'is-off'"><i class="bx" :class="c.on ? 'bx-check' : 'bx-x'"></i> {{ capShort(c.key) }}</span>
                    </span>
                    <span v-if="!m.available" class="ch-muted sm-text">{{ t('ui.connect.unavailable') }}</span>
                  </label>
                </div>
                <div class="ch-before">
                  <strong>{{ t('ui.connect.instagram.before') }}</strong>
                  <span><i class="bx bx-check"></i> {{ t('ui.connect.instagram.business') }}</span>
                  <span><i class="bx bx-check"></i> {{ t('ui.connect.instagram.admin') }}</span>
                </div>
                <p class="ch-note is-warn"><i class="bx bx-info-circle"></i> {{ t('ui.connect.instagram.one_way') }}</p>
              </template>

              <!-- Threads: its own sign-in -->
              <div v-else-if="connectViewData.flow === 'threads'" class="ch-split">
                <div>
                  <div class="ch-handshake" aria-hidden="true"><span class="ch-tile lg is-brand">S</span><span class="ch-dots"><i class="bx bx-transfer-alt"></i></span><span class="ch-tile lg is-threads"><i class="bx bx-at"></i></span></div>
                  <h3 class="ch-modal-h3 is-center">{{ t('ui.connect.threads.heading') }}</h3>
                  <p class="ch-modal-sub is-center">{{ t('ui.connect.threads.own_login') }}</p>
                  <h4 class="ch-list-title">{{ t('ui.connect.threads.ask') }}</h4>
                  <ul class="ch-perms">
                    <li v-for="p in threadsPermissions" :key="p.key">
                      <span class="ch-perm-icon"><i class="bx" :class="p.icon"></i></span>
                      <span><strong>{{ t('ui.connect.threads.' + p.key + '.title') }}</strong><small>{{ t('ui.connect.threads.' + p.key + '.text') }}</small></span>
                      <code dir="ltr">{{ p.scope }}</code>
                    </li>
                  </ul>
                </div>
                <aside class="ch-aside">
                  <h4 class="ch-list-title">{{ t('ui.connect.threads.another') }}</h4>
                  <ol class="ch-numbered"><li v-for="(s, i) in threadsAnother" :key="i">{{ s }}</li></ol>
                  <p class="ch-note is-warn"><i class="bx bx-error"></i> {{ t('ui.connect.threads.expiry') }}</p>
                </aside>
              </div>

              <!-- WhatsApp: Embedded Signup or a System User token -->
              <template v-else-if="connectViewData.flow === 'whatsapp'">
                <p class="ch-modal-sub">{{ connectViewData.description }}</p>
                <div class="ch-wa-actions">
                  <button v-if="whatsappStep && whatsappStep.available && connectCard.whatsapp_signup" type="button" class="ch-btn ch-btn-primary" :disabled="waBusy" @click="startWhatsappSignup(connectCard.whatsapp_signup)">
                    <i class="bx" :class="waBusy ? 'bx-loader-alt bx-spin' : 'bxl-whatsapp'"></i> {{ t('ui.connect.continue_with', { platform: connectViewData.name }) }}
                  </button>
                  <button type="button" class="ch-btn ch-btn-secondary" @click="waManual = !waManual"><i class="bx" :class="waManual ? 'bx-x' : 'bx-key'"></i> {{ waManual ? t('cancel') : t('step.enter_manually') }}</button>
                </div>
                <form v-if="waManual" method="POST" :action="urls.whatsapp_manual" class="ch-form">
                  <input type="hidden" name="_token" :value="csrf">
                  <p class="ch-muted">{{ t('whatsapp.help') }}</p>
                  <label>{{ t('whatsapp.name') }}<input type="text" name="name" required maxlength="255" :placeholder="t('whatsapp.name_placeholder')"></label>
                  <label>{{ t('whatsapp.phone_id') }}<input type="text" name="phone_number_id" required inputmode="numeric" dir="ltr"></label>
                  <label>{{ t('whatsapp.token') }}<input type="password" name="access_token" required autocomplete="off" dir="ltr"></label>
                  <button type="submit" class="ch-btn ch-btn-primary sm"><i class="bx bx-check"></i> {{ t('ui.accounts.connect') }}</button>
                </form>
              </template>

              <!-- ChatGPT Ads: an API key, no sign-in page -->
              <form v-else-if="connectViewData.flow === 'openai'" method="POST" :action="urls.openai_key" class="ch-form">
                <input type="hidden" name="_token" :value="csrf">
                <p class="ch-muted" v-html="t('openai.help')"></p>
                <label>{{ t('openai.key') }}<input type="password" name="api_key" required minlength="20" autocomplete="off" spellcheck="false" dir="ltr" :placeholder="t('openai.key_placeholder')"></label>
                <div class="ch-wa-actions">
                  <button type="submit" class="ch-btn ch-btn-primary sm"><i class="bx bx-check"></i> {{ t('ui.accounts.connect') }}</button>
                  <a href="https://ads.openai.com" target="_blank" rel="noopener noreferrer" class="ch-btn ch-btn-secondary sm"><i class="bx bx-link-external"></i> {{ t('openai.open') }}</a>
                </div>
              </form>

              <!-- Google Ads, Analytics, Search Console...: one row per service -->
              <google-services v-else-if="connectViewData.flow === 'google'" :services="connectCard.google_services || []" :t="t" :busy="busy"
                               @disconnect="id => disconnect(connectCard, connectCard.connections.find(c => c.id === id))" />

              <!-- Everyone else: what Socialeaz can do, plus a choice when the platform has several sign-ins -->
              <div v-else class="ch-split">
                <div>
                  <template v-if="connectOptions.length > 1">
                    <h3 class="ch-modal-h3">{{ t('ui.connect.options') }}</h3>
                    <div class="ch-options">
                      <label v-for="o in connectOptions" :key="o.key" class="ch-option" :class="{ 'is-picked': connectChoice === o.key, 'is-disabled': !o.available }">
                        <input v-model="connectChoice" type="radio" name="connect-step" :value="o.key" :disabled="!o.available">
                        <span class="ch-radio" aria-hidden="true"></span>
                        <span class="ch-option-text">
                          <strong>{{ o.label }} <span v-if="o.primary" class="ch-tag">{{ t('ui.connect.recommended') }}</span> <span v-if="o.connected" class="ch-tag is-ok">{{ t('ui.connect.connected') }}</span></strong>
                          <small>{{ o.description }}</small>
                          <small v-if="!o.available && o.note" class="ch-warn-text">{{ o.note }}</small>
                        </span>
                      </label>
                    </div>
                  </template>
                  <template v-else>
                    <h3 class="ch-modal-h3">{{ t('ui.connect.able') }}</h3>
                    <p class="ch-modal-sub">{{ t('ui.connect.able_sub') }}</p>
                  </template>
                  <ul class="ch-perms">
                    <li v-for="c in connectViewData.offers" :key="c">
                      <span class="ch-perm-icon"><i class="bx" :class="capIcon(c)"></i></span>
                      <span><strong>{{ capLabel(c) }}</strong><small>{{ capLong(c, connectViewData.card) }}</small></span>
                    </li>
                  </ul>
                </div>
                <aside class="ch-aside">
                  <p v-if="connectViewData.service && connectViewData.serviceNote" class="ch-note is-info"><i class="bx bx-info-circle"></i> {{ connectViewData.serviceNote }}</p>
                  <p class="ch-note is-info"><i class="bx bx-shield-quarter"></i> {{ t('ui.connect.privacy') }}</p>
                </aside>
              </div>
            </div>

            <footer v-if="!['whatsapp', 'openai', 'google'].includes(connectViewData.flow)" class="ch-modal-foot">
              <button type="button" class="ch-btn ch-btn-ghost" @click="closeConnect">{{ t('ui.connect.cancel') }}</button>
              <span class="ch-muted sm-text">{{ t('ui.connect.return') }}</span>
              <a v-if="connectUrl" :href="connectUrl" class="ch-btn ch-btn-lg" :class="connectViewData.flow === 'threads' ? 'ch-btn-dark' : 'ch-btn-primary'" @click="rememberReturn(connectViewData.key)">
                <i class="bx" :class="connectViewData.icon"></i> {{ connectCta }} <i class="bx bx-right-arrow-alt ch-flip"></i>
              </a>
              <span v-else class="ch-btn ch-btn-disabled ch-btn-lg">{{ t('ui.connect.unavailable') }}</span>
            </footer>
            <footer v-else class="ch-modal-foot">
              <button type="button" class="ch-btn ch-btn-ghost" @click="closeConnect">{{ t('close') }}</button>
            </footer>
          </template>
        </div>
      </div>
    </transition>

    <!-- ===================== CHOOSE ACCOUNTS (after the provider sends you back) ===================== -->
    <transition name="ch-fade">
      <div v-if="chooseData" class="ch-modal-backdrop" @click.self="closeChoose">
        <div class="ch-modal" role="dialog" aria-modal="true" :aria-label="t('hub.choose.title')">
          <header class="ch-modal-head">
            <span class="ch-tile" :class="chooseData.meta ? 'is-metab' : 'is-' + chooseData.view.brand"><template v-if="chooseData.meta">M</template><i v-else class="bx" :class="chooseData.view.icon"></i></span>
            <div class="ch-modal-title">
              <h2>{{ chooseData.meta ? t('hub.meta.modal_title') : t('ui.connect.title', { platform: chooseData.view.name }) }}</h2>
              <p>{{ chooseData.meta ? t('hub.meta.modal_sub') + ' · ' : '' }}{{ t('ui.connect.adding') }} <strong>{{ workspaceName }}</strong></p>
            </div>
            <ol class="ch-stepper" aria-hidden="true">
              <li class="is-done"><span class="ch-stepper-dot"><i class="bx bx-check"></i></span><span class="ch-stepper-label">{{ t('ui.connect.steps.sign_in') }}</span></li>
              <li class="is-current"><span class="ch-stepper-dot">2</span><span class="ch-stepper-label">{{ t('ui.connect.steps.choose') }}</span></li>
              <li><span class="ch-stepper-dot">3</span><span class="ch-stepper-label">{{ t('ui.connect.steps.done') }}</span></li>
            </ol>
            <button type="button" class="ch-icon-btn" :aria-label="t('close')" @click="closeChoose"><i class="bx bx-x"></i></button>
          </header>

          <div class="ch-modal-body">
            <div class="ch-split">
              <div class="ch-choose">
                <div class="ch-signed">
                  <span class="ch-avatar">{{ initials(chooseData.signedAs || chooseData.platformName) }}</span>
                  <span>{{ chooseData.signedAs ? t('hub.choose.signed', { platform: chooseData.platformName, name: chooseData.signedAs }) : t('hub.choose.signed_plain', { platform: chooseData.platformName }) }}</span>
                  <a v-if="chooseData.anotherUrl" :href="chooseData.anotherUrl" class="ch-link" @click="rememberReturn(chooseData.view.key)">{{ t('hub.choose.another') }}</a>
                </div>

                <template v-for="g in chooseData.groups">
                  <h4 :key="g.key + '-h'" class="ch-list-title">{{ g.title }}</h4>
                  <div :key="g.key" class="ch-pick-list" :class="{ 'is-two': g.key === 'whatsapp' || g.key === 'ad_account' }">
                    <div v-for="item in g.items" :key="item.asset.id" class="ch-pick" :class="{ 'is-on': isChosen(item), 'is-already': item.already }">
                      <label class="ch-pick-row">
                        <input type="checkbox" :checked="isChosen(item)" :disabled="item.already" @change="toggleChosen(item)">
                        <span class="ch-tile xs" :class="'is-' + kindBrand(item)"><i class="bx" :class="kindIcon(item)"></i></span>
                        <span class="ch-pick-text"><strong>{{ assetName(item.asset) }}</strong><small>{{ item.sub }}</small></span>
                        <span v-if="item.already" class="ch-muted sm-text">{{ t('hub.choose.already') }}</span>
                        <span v-else-if="item.note" class="ch-muted sm-text">{{ item.note }}</span>
                      </label>
                      <label v-for="child in item.children || []" :key="child.asset.id" class="ch-pick-row is-child">
                        <input type="checkbox" :checked="isChosen(child)" :disabled="child.already" @change="toggleChosen(child)">
                        <span class="ch-tile xs is-instagram"><i class="bx bxl-instagram"></i></span>
                        <span class="ch-pick-text"><strong>{{ child.asset.username ? '@' + child.asset.username : assetName(child.asset) }}</strong><small>{{ t('hub.choose.linked') }}</small></span>
                        <span v-if="child.already" class="ch-muted sm-text">{{ t('hub.choose.already') }}</span>
                      </label>
                    </div>
                  </div>
                  <p v-if="g.key === 'ad_account'" :key="g.key + '-n'" class="ch-muted sm-text ch-note-plain">{{ t('hub.choose.ads_note') }}</p>
                </template>
              </div>
              <aside class="ch-aside">
                <h4 class="ch-list-title">{{ t('ui.connect.able') }}</h4>
                <ul class="ch-checks">
                  <li v-for="c in chooseData.view.offers" :key="c"><i class="bx bx-check"></i><span><strong>{{ capLabel(c) }}</strong><small>{{ capLong(c, chooseData.view.card) }}</small></span></li>
                </ul>
                <p class="ch-note is-info"><i class="bx bx-shield-quarter"></i> {{ t('ui.connect.privacy') }}</p>
              </aside>
            </div>
          </div>

          <footer class="ch-modal-foot">
            <button type="button" class="ch-btn ch-btn-secondary" :disabled="chooseSaving" @click="closeChoose">{{ t('hub.choose.back') }}</button>
            <span class="ch-muted sm-text">{{ chooseSummary }}</span>
            <button type="button" class="ch-btn ch-btn-primary ch-btn-lg" :disabled="chooseSaving" @click="confirmChoose">
              <i v-if="chooseSaving" class="bx bx-loader-alt bx-spin"></i>
              {{ chooseSaving ? t('hub.choose.saving') : (chosenNew.length ? tc('hub.choose.button', chosenNew.length) : t('hub.choose.keep')) }}
              <i v-if="!chooseSaving" class="bx bx-right-arrow-alt ch-flip"></i>
            </button>
          </footer>
        </div>
      </div>
    </transition>

    <!-- ===================== MANAGE PANEL ===================== -->
    <transition name="ch-fade">
      <div v-if="manageViewData" class="ch-drawer-backdrop" @click.self="closeManage"></div>
    </transition>
    <transition name="ch-slide">
      <aside v-if="manageViewData" class="ch-drawer" role="dialog" aria-modal="true" :aria-label="manageTitle">
        <header class="ch-drawer-head">
          <span class="ch-tile" :class="'is-' + (manageAccount ? kindBrand(manageAccount) : manageViewData.brand)"><i class="bx" :class="manageAccount ? kindIcon(manageAccount) : manageViewData.icon"></i></span>
          <div class="ch-modal-title">
            <h2>{{ manageTitle }}</h2>
            <p v-if="manageAccount">{{ kindLabel(manageAccount) }} · {{ t('hub.manage.sign_in', { step: manageAccount.conn.step_label }) }}</p>
            <p v-else>{{ t('ui.manage.adding', { count: manageViewData.countLabel || tc('ui.units.accounts', 0), workspace: workspaceName }) }}</p>
          </div>
          <button type="button" class="ch-icon-btn" :aria-label="t('close')" @click="closeManage"><i class="bx bx-x"></i></button>
        </header>

        <div class="ch-drawer-body">
          <div v-if="chooseMode" class="ch-success">
            <span class="ch-success-icon"><i class="bx bx-check"></i></span>
            <div><strong>{{ t('ui.manage.choose_title') }}</strong><span>{{ t('ui.manage.choose_sub', { workspace: workspaceName }) }}</span></div>
          </div>

          <google-services v-if="manageViewData.flow === 'google' && !manageAccount" :services="manageCard.google_services || []" :t="t" :busy="busy"
                           @disconnect="id => disconnect(manageCard, manageCard.connections.find(c => c.id === id))" />

          <section v-for="conn in manageConns" :key="conn.id" class="ch-conn">
            <div class="ch-health" :class="healthBox(conn).tone">
              <i class="bx" :class="healthBox(conn).icon" aria-hidden="true"></i>
              <div class="ch-health-text">
                <strong>{{ healthBox(conn).label }} <span class="ch-muted">· {{ conn.last_checked_at ? t('ui.manage.checked', { time: ago(conn.last_checked_at) }) : t('ui.manage.never') }}</span></strong>
                <span>{{ [conn.step_label, conn.account_label ? t('ui.manage.signed_as', { name: conn.account_label }) : null, renewLabel(conn)].filter(Boolean).join(' · ') }}</span>
                <span v-if="conn.last_error && conn.needs_attention" class="ch-warn-text">{{ conn.last_error }}</span>
              </div>
              <div class="ch-health-actions">
                <button type="button" class="ch-btn ch-btn-secondary sm" :disabled="!!busy[conn.id]" @click="check(manageCard, conn)">
                  <i class="bx" :class="busy[conn.id] === 'check' ? 'bx-loader-alt bx-spin' : 'bx-revision'"></i> {{ t('ui.manage.check') }}
                </button>
              </div>
            </div>

            <p v-if="missingCaps(conn).length" class="ch-note is-info">
              <i class="bx bx-lock-open-alt"></i>
              <span>{{ t('ui.manage.missing') }} <strong>{{ missingCaps(conn).map(capShort).join(', ') }}</strong></span>
              <a :href="conn.reconnect_url" class="ch-link" @click="rememberReturn(manageViewData.key)">{{ t('ui.manage.grant') }}</a>
            </p>
            <p v-if="conn.upgrade && conn.status !== 'revoked'" class="ch-note is-info">
              <i class="bx bx-up-arrow-circle"></i> <span>{{ conn.upgrade.note }}</span>
              <a :href="conn.upgrade.url" class="ch-link">{{ t('ui.manage.upgrade') }}</a>
            </p>

            <article v-for="a in manageAccountsOf(conn)" :key="a.asset.id" class="ch-account" :class="{ 'is-off': !a.asset.enabled_capabilities.length }">
              <div v-if="!manageAccount" class="ch-account-head">
                <span class="ch-avatar lg" :style="avatarStyle(a.asset)">
                  <img v-if="a.asset.avatar_url" :src="a.asset.avatar_url" alt="" @error="a.asset.avatar_url = null">
                  <template v-else>{{ initials(assetName(a.asset)) }}</template>
                  <span class="ch-avatar-badge ch-tile" :class="'is-' + kindBrand(a)"><i class="bx" :class="kindIcon(a)"></i></span>
                </span>
                <span class="ch-td-name">
                  <strong>{{ assetName(a.asset) }}</strong>
                  <small>{{ kindLabel(a) }}</small>
                  <small v-if="!a.asset.token_ok" class="ch-warn-text">{{ t('asset.needs_reconnect') }}</small>
                  <small v-if="a.asset.provider_status" class="ch-warn-text">{{ a.asset.provider_status }}</small>
                  <small v-if="a.asset.duplicate_of" class="ch-muted">{{ t('asset.duplicate', { name: a.asset.duplicate_of }) }}</small>
                </span>
                <label class="ch-switch" :title="t('ui.manage.use')">
                  <span class="sr-only">{{ t('ui.manage.use') }}</span>
                  <input type="checkbox" :checked="a.asset.enabled_capabilities.length > 0" :disabled="saving[a.asset.id] || !a.asset.available_capabilities.length" @change="toggleAsset(conn, a.asset)">
                  <span></span>
                </label>
              </div>
              <template v-else>
                <p v-if="a.asset.provider_status" class="ch-note is-warn"><i class="bx bx-error"></i> {{ a.asset.provider_status }}</p>
                <p v-if="!a.asset.token_ok" class="ch-note is-bad"><i class="bx bx-error-circle"></i> {{ t('asset.needs_reconnect') }}</p>
              </template>
              <div v-if="a.asset.available_capabilities.length" class="ch-uses">
                <p class="ch-uses-title">{{ t('ui.manage.use_for') }}</p>
                <div v-for="cap in a.asset.available_capabilities" :key="cap" class="ch-use">
                  <span><strong>{{ capLabel(cap) }}</strong><small>{{ capHint(cap) }}</small></span>
                  <label class="ch-switch sm">
                    <span class="sr-only">{{ capLabel(cap) }}</span>
                    <input type="checkbox" :checked="a.asset.enabled_capabilities.includes(cap)" :disabled="saving[a.asset.id]" @change="toggleCapability(conn, a.asset, cap)">
                    <span></span>
                  </label>
                </div>
              </div>

              <template v-if="manageAccount && linkedOf(a).length">
                <p class="ch-uses-title">{{ a.kind === 'instagram' ? t('hub.manage.linked_page') : t('hub.manage.linked') }}</p>
                <button v-for="l in linkedOf(a)" :key="l.asset.id" type="button" class="ch-linked" :class="'is-' + accountHealth(l).tone" @click="openManage(l.viewKey, l.asset.id)">
                  <span class="ch-tile xs" :class="'is-' + kindBrand(l)"><i class="bx" :class="kindIcon(l)"></i></span>
                  <span class="ch-pick-text"><strong>{{ l.asset.username ? '@' + l.asset.username : assetName(l.asset) }}</strong><small>{{ accountHealth(l).access }}</small></span>
                  <i class="bx bx-chevron-right ch-flip" aria-hidden="true"></i>
                </button>
              </template>
            </article>
            <p v-if="!manageAccountsOf(conn).length" class="ch-muted ch-none">{{ t('ui.manage.empty') }}</p>

            <div v-if="conn.status !== 'revoked'" class="ch-danger">
              <div>
                <strong>{{ t('ui.manage.disconnect_title') }}</strong>
                <span>{{ t('ui.manage.disconnect_text', { workspace: workspaceName }) }}<template v-if="manageCard.platform === 'meta' && conn.step === 'meta.login'"> {{ t('ui.manage.disconnect_meta') }}</template></span>
              </div>
              <button v-if="confirming !== conn.id" type="button" class="ch-btn ch-btn-danger sm" @click="confirming = conn.id"><i class="bx bx-unlink"></i> {{ t('ui.manage.disconnect') }}</button>
              <span v-else class="ch-confirm">
                <button type="button" class="ch-btn ch-btn-danger-solid sm" :disabled="!!busy[conn.id]" @click="disconnect(manageCard, conn)">{{ t('ui.manage.confirm') }}</button>
                <button type="button" class="ch-btn ch-btn-ghost sm" @click="confirming = null">{{ t('cancel') }}</button>
              </span>
            </div>
          </section>
        </div>

        <footer class="ch-drawer-foot">
          <a v-if="manageReconnect" :href="manageReconnect" class="ch-btn ch-btn-secondary" @click="rememberReturn(manageViewData.key)"><i class="bx bx-refresh"></i> {{ t('ui.manage.reconnect') }}</a>
          <button v-else type="button" class="ch-btn ch-btn-secondary" @click="openConnect(manageViewData.key)"><i class="bx bx-plus"></i> {{ t('ui.manage.add') }}</button>
          <button type="button" class="ch-btn ch-btn-primary" @click="closeManage">{{ t('ui.manage.done') }}</button>
        </footer>
      </aside>
    </transition>

  </div>
</template>
<script>
import { createTranslator } from '../ads/facebook/i18n';
import GoogleServices from './GoogleServices.vue';

const CAPS = {
  posting: 'bx-send',
  messaging: 'bx-message-rounded-dots',
  engagement: 'bx-comment-detail',
  ads: 'bx-bullseye',
  insights: 'bx-bar-chart-alt-2'
};
const CAP_ORDER = ['posting', 'messaging', 'engagement', 'ads', 'leads', 'insights'];

// The page shows one card per platform people know (Facebook, Instagram ...),
// while connections stay per provider sign-in (driver `card`): a view picks
// the driver card, the account kinds (driver asset_groups) and the sign-in
// steps that belong to it. group: 'meta' (one Meta login), 'main', 'more'.
const PLATFORMS = [
  { key: 'whatsapp', group: 'meta', card: 'meta', kinds: ['whatsapp'], steps: ['meta.whatsapp'], brand: 'whatsapp', icon: 'bxl-whatsapp', flow: 'whatsapp' },
  { key: 'facebook', group: 'meta', card: 'meta', kinds: ['page'], steps: ['meta.login'], brand: 'facebook', icon: 'bxl-facebook', flow: 'steps' },
  { key: 'instagram', group: 'meta', card: 'meta', kinds: ['instagram'], steps: ['meta.instagram_login'], brand: 'instagram', icon: 'bxl-instagram', flow: 'instagram' },
  { key: 'meta_ads', group: 'meta', card: 'meta', kinds: ['ad_account'], brand: 'meta', icon: 'bxl-meta', flow: 'steps' },
  { key: 'threads', group: 'main', card: 'threads', kinds: ['threads_profile'], owns: true, brand: 'threads', icon: 'bx-at', flow: 'threads' },
  { key: 'tiktok', group: 'main', card: 'tiktok', kinds: ['tiktok_account', 'tiktok_ads', 'tiktok_comments'], unitKinds: ['tiktok_account'], owns: true, brand: 'tiktok', icon: 'bxl-tiktok', flow: 'steps' },
  { key: 'linkedin', group: 'main', card: 'linkedin', kinds: ['linkedin_page', 'linkedin_ads'], unitKinds: ['linkedin_page'], owns: true, brand: 'linkedin', icon: 'bxl-linkedin', flow: 'steps' },
  { key: 'x', group: 'main', card: 'x', kinds: ['x_account', 'x_ads'], unitKinds: ['x_account'], owns: true, brand: 'x', icon: 'bxl-x-logo', flow: 'steps' },
  { key: 'youtube', group: 'main', card: 'google', kinds: ['youtube'], service: 'youtube', brand: 'youtube', icon: 'bxl-youtube', flow: 'service' },
  { key: 'snapchat', group: 'main', card: 'snapchat', kinds: ['snapchat_ads'], owns: true, brand: 'snapchat', icon: 'bxl-snapchat', flow: 'steps' },
  { key: 'business_profile', group: 'main', card: 'google', kinds: ['business_profile'], service: 'business_profile', brand: 'gbp', icon: 'bx-store', flow: 'service' },
  { key: 'google', group: 'more', card: 'google', kinds: ['google_ads'], owns: true, brand: 'google', icon: 'bxl-google', flow: 'google' },
  { key: 'pinterest', group: 'more', card: 'pinterest', kinds: ['pinterest_account'], owns: true, brand: 'pinterest', icon: 'bxl-pinterest', flow: 'steps' },
  { key: 'openai', group: 'more', card: 'openai', kinds: ['openai_ads'], owns: true, brand: 'openai', icon: 'bx-bot', flow: 'openai' }
];

const KIND_ICONS = {
  page: ['facebook', 'bxl-facebook'], instagram: ['instagram', 'bxl-instagram'], ad_account: ['meta', 'bxl-meta'],
  whatsapp: ['whatsapp', 'bxl-whatsapp'], youtube: ['youtube', 'bxl-youtube'], business_profile: ['gbp', 'bx-store'],
  google_ads: ['google', 'bxl-google'], x_account: ['x', 'bxl-x-logo'], x_ads: ['x', 'bxl-x-logo'],
  linkedin_page: ['linkedin', 'bxl-linkedin'], linkedin_ads: ['linkedin', 'bxl-linkedin'], tiktok_account: ['tiktok', 'bxl-tiktok'],
  tiktok_ads: ['tiktok', 'bxl-tiktok'], tiktok_comments: ['tiktok', 'bxl-tiktok'], threads_profile: ['threads', 'bx-at'],
  pinterest_account: ['pinterest', 'bxl-pinterest'], snapchat_ads: ['snapchat', 'bxl-snapchat'], openai_ads: ['openai', 'bx-bot']
};

const AVATAR_COLORS = ['#2563EB', '#0D9488', '#7C3AED', '#DB2777', '#EA580C', '#0891B2', '#4F46E5', '#B45309'];
const RETURN_KEY = 'ch.return';

export default {
  components: { GoogleServices },

  props: {
    hub: { type: Object, required: true },
    urls: { type: Object, required: true },
    flash: { type: [Object, Array], default: () => ({}) },
    wizard: { type: Object, default: () => ({ state: null, available: false }) },
    // AuthorizationFlow::state(): the guided run after "Connect selected", or null.
    flow: { type: Object, default: null },
    // Has an account Posts / Inbox / Comments can use (User::hasEligibleConnection).
    eligible: { type: Boolean, default: true },
    // ConnectionHubController::workspacePayload(): the open workspace.
    workspace: { type: Object, default: () => ({ name: '', recommended: [] }) },
    // __('connections') for the current locale.
    messages: { type: Object, default: () => ({}) },
    locale: { type: String, default: 'en' }
  },

  data() {
    const flash = Array.isArray(this.flash) ? {} : this.flash;

    return {
      cards: JSON.parse(JSON.stringify(this.hub.cards)),
      busy: {},
      saving: {},
      confirming: null,
      waBusy: false,
      waManual: false,
      flashMessage: flash.error
        ? { tone: 'is-error', text: flash.error }
        : (flash.success ? { tone: 'is-success', text: flash.success } : null),
      wizardState: this.wizard.state,
      flowState: this.flow,
      mode: 'platforms',
      filter: 'all',
      search: '',
      platformFilter: '',
      healthFilter: 'all',
      connectKey: null,
      connectChoice: null,
      connectFailed: false,
      manageKey: null,
      manageAsset: null,
      chooseMode: false,
      // "Choose accounts" after a sign-in: the platform, the accounts that existed before, the picks.
      chooseKey: null,
      chooseBefore: [],
      chosen: {},
      chooseSaving: false,
      csrf: (document.querySelector('meta[name="csrf-token"]') || {}).content || ''
    };
  },

  computed: {
    i18n() {
      return createTranslator(this.messages, this.locale);
    },

    workspaceName() {
      return this.workspace.name || this.t('ui.workspace.label');
    },

    allViews() {
      return PLATFORMS.map(def => this.buildView(def)).filter(Boolean);
    },

    metaViews() { return this.allViews.filter(v => v.group === 'meta'); },
    mainViews() { return this.allViews.filter(v => v.group === 'main'); },
    moreViews() { return this.allViews.filter(v => v.group === 'more'); },

    connectedViews() {
      return this.allViews.filter(v => v.accounts.length);
    },

    stats() {
      const counted = this.metaViews.concat(this.mainViews);
      const inUse = new Set();
      this.allViews.forEach(v => v.accounts.forEach(a => { if (a.asset.enabled_capabilities.length) inUse.add(a.asset.id); }));
      return {
        platforms: counted.filter(v => v.status !== 'not_connected').length,
        total: counted.length,
        accounts: inUse.size,
        attention: this.allViews.filter(v => v.status === 'attention').length
      };
    },

    filters() {
      return [
        { key: 'all', label: this.t('ui.filters.all') },
        { key: 'attention', label: this.t('ui.filters.attention') },
        { key: 'not_connected', label: this.t('ui.filters.not_connected') }
      ];
    },

    // The one Meta sign-in behind Facebook, Instagram, WhatsApp and Meta Ads.
    metaLogin() {
      const meta = this.cards.find(c => c.platform === 'meta');
      const conn = meta && meta.connections.find(c => c.step === 'meta.login' && c.status !== 'revoked');
      return conn ? { ok: !conn.needs_attention, days: conn.expires_in_days, label: conn.account_label, conn } : null;
    },

    metaText() {
      const m = this.metaLogin;
      if (!m) return this.t('hub.meta.body_new');
      if (!m.ok) return this.t('hub.meta.reconnect');
      const who = m.label ? this.t('hub.meta.body', { name: m.label }) : this.t('hub.meta.body_plain');
      return m.days !== null && m.days >= 0 ? who + ' ' + this.tc('hub.meta.renews', Math.max(1, m.days)) : who;
    },

    lastChecked() {
      let latest = null;
      this.cards.forEach(c => c.connections.forEach(conn => {
        if (conn.last_checked_at && (!latest || conn.last_checked_at > latest)) latest = conn.last_checked_at;
      }));
      return latest;
    },

    tableRows() {
      const seen = new Set();
      const rows = [];
      this.allViews.forEach(view => view.accounts.forEach(a => {
        if (seen.has(a.asset.id)) return;
        seen.add(a.asset.id);
        rows.push({ ...a, view, health: this.accountHealth(a) });
      }));
      return rows;
    },

    visibleTableRows() {
      const q = this.search.trim().toLocaleLowerCase();
      return this.tableRows.filter(r => (!this.platformFilter || r.view.key === this.platformFilter)
        && (this.healthFilter === 'all' || (this.healthFilter === 'attention') === r.health.attention)
        && (!q || [r.asset.name, r.asset.username, r.asset.external_id, r.view.name].some(s => s && String(s).toLocaleLowerCase().includes(q))));
    },

    connectViewData() {
      return this.connectKey && this.connectKey !== 'picker' ? this.allViews.find(v => v.key === this.connectKey) || null : null;
    },

    connectCard() {
      return this.connectViewData ? this.cards.find(c => c.platform === this.connectViewData.card) : null;
    },

    // Facebook and Meta Ads connect through the one Meta login.
    isMetaLogin() {
      return !!this.connectViewData && ['facebook', 'meta_ads'].includes(this.connectViewData.key);
    },

    connectTitle() {
      if (!this.connectViewData) return this.t('ui.connect_platform');
      return this.isMetaLogin ? this.t('hub.meta.modal_title') : this.t('ui.connect.title', { platform: this.connectViewData.name });
    },

    connectSteps() {
      return this.connectViewData && this.connectViewData.flow === 'instagram' ? ['method', 'choose', 'done'] : ['sign_in', 'choose', 'done'];
    },

    // Sign-in choices for platforms with several (TikTok, X, LinkedIn ...).
    connectOptions() {
      const v = this.connectViewData;
      if (!v || v.flow !== 'steps') return [];
      return this.stepsFor(v);
    },

    instagramMethods() {
      const meta = this.connectCard;
      if (!meta) return [];
      const step = key => meta.steps.find(s => s.key === key);
      const login = step('meta.login');
      const ig = step('meta.instagram_login');
      const linked = this.connectViewData.accounts.filter(a => a.conn.step === 'meta.login').length;
      return [
        login && {
          key: 'meta.login', brand: 'facebook', icon: 'bxl-facebook', recommended: true, available: login.available, url: login.connect_url,
          title: this.t('ui.connect.instagram.fb_title'), sub: this.t('ui.connect.instagram.fb_sub'),
          note: this.metaLogin ? (linked ? this.tc('ui.connect.instagram.found', linked) + '. ' + this.t('ui.connect.instagram.fb_signed') : this.t('ui.connect.instagram.fb_signed')) : this.t('ui.connect.instagram.fb_new'),
          caps: ['posting', 'messaging', 'engagement', 'insights', 'ads'].map(k => ({ key: k, on: true }))
        },
        ig && {
          key: 'meta.instagram_login', brand: 'instagram', icon: 'bxl-instagram', available: ig.available, url: ig.connect_url,
          title: this.t('ui.connect.instagram.ig_title'), sub: this.t('ui.connect.instagram.ig_sub'), note: this.t('ui.connect.instagram.ig_info'),
          caps: ['posting', 'messaging', 'engagement', 'insights'].map(k => ({ key: k, on: true })).concat({ key: 'ads', on: false })
        }
      ].filter(Boolean);
    },

    threadsPermissions() {
      return [
        { key: 'profile', icon: 'bx-show', scope: 'threads_basic' },
        { key: 'publish', icon: 'bx-send', scope: 'threads_content_publish' },
        { key: 'replies', icon: 'bx-comment-detail', scope: 'threads_read_replies · threads_manage_replies' },
        { key: 'insights', icon: 'bx-bar-chart-alt-2', scope: 'threads_manage_insights' }
      ];
    },

    threadsAnother() {
      const steps = (((this.messages.ui || {}).connect || {}).threads || {}).another_steps;
      return Array.isArray(steps) ? steps : Object.values(steps || {});
    },

    whatsappStep() {
      return this.connectCard ? this.connectCard.steps.find(s => s.key === 'meta.whatsapp') : null;
    },

    connectUrl() {
      const v = this.connectViewData;
      if (!v) return null;
      if (v.flow === 'service') return v.serviceUrl;
      if (v.flow === 'instagram') {
        const m = this.instagramMethods.find(x => x.key === this.connectChoice);
        return m && m.available ? m.url : null;
      }
      const steps = this.stepsFor(v);
      const picked = steps.find(s => s.key === this.connectChoice) || steps.find(s => s.available);
      return picked && picked.available ? picked.connect_url : null;
    },

    connectCta() {
      const v = this.connectViewData;
      if (v.flow === 'service') return this.t('ui.connect.continue_with', { platform: 'Google' });
      if (this.isMetaLogin || (v.flow === 'instagram' && this.connectChoice !== 'meta.instagram_login')) {
        return this.t('ui.connect.continue_with', { platform: this.t('ui.platforms.facebook.name') });
      }
      if (v.flow === 'instagram') return this.t('ui.connect.continue_with', { platform: v.name });
      return this.t('ui.connect.continue', { platform: v.name });
    },

    // ---------- choose accounts ----------

    chooseData() {
      const view = this.chooseKey ? this.allViews.find(v => v.key === this.chooseKey) : null;
      if (!view) return null;
      const card = this.cards.find(c => c.platform === view.card);
      const before = new Set(this.chooseBefore);
      const meta = view.card === 'meta';
      const views = meta ? this.metaViews : [view];
      const items = [];
      const seen = new Set();
      views.forEach(v => v.accounts.forEach(a => {
        if (seen.has(a.asset.id)) return;
        seen.add(a.asset.id);
        items.push({ ...a, viewKey: v.key, already: before.has(a.asset.id) });
      }));
      const fresh = items.filter(i => !i.already);
      const newest = fresh.length ? fresh[fresh.length - 1].conn : null;
      const groups = [];
      if (meta) {
        const igs = items.filter(i => i.kind === 'instagram');
        const pages = items.filter(i => i.kind === 'page').map(p => {
          const children = igs.filter(ig => ig.asset.linked_page_id && String(ig.asset.linked_page_id) === String(p.asset.external_id));
          return { ...p, children, sub: p.asset.category || this.kindLabel(p), note: children.length ? null : this.t('hub.choose.no_ig') };
        });
        const nested = new Set(pages.flatMap(p => p.children.map(c => c.asset.id)));
        if (pages.length) groups.push({ key: 'page', title: this.t('hub.choose.pages'), items: pages });
        const loose = igs.filter(i => !nested.has(i.asset.id)).map(i => ({ ...i, sub: this.kindLabel(i) }));
        if (loose.length) groups.push({ key: 'instagram', title: this.t('hub.choose.instagram'), items: loose });
        const wa = items.filter(i => i.kind === 'whatsapp').map(i => ({ ...i, sub: i.asset.username || i.asset.external_id }));
        if (wa.length) groups.push({ key: 'whatsapp', title: this.t('hub.choose.whatsapp'), items: wa });
        const ads = items.filter(i => i.kind === 'ad_account').map(i => ({ ...i, sub: i.asset.external_id }));
        if (ads.length) groups.push({ key: 'ad_account', title: this.t('hub.choose.ads'), items: ads });
      } else {
        const asset = (card.presentation && card.presentation.asset_groups) || {};
        Object.keys(asset).forEach(kind => {
          const list = items.filter(i => i.kind === kind).map(i => ({ ...i, sub: i.asset.username ? '@' + i.asset.username : this.kindLabel(i) }));
          if (list.length) groups.push({ key: kind, title: asset[kind].label, items: list });
        });
      }
      return {
        view, meta, groups, items,
        platformName: meta ? 'Meta' : view.name,
        signedAs: newest ? newest.account_label : null,
        anotherUrl: newest ? newest.reconnect_url : null
      };
    },

    chosenNew() {
      if (!this.chooseData) return [];
      return this.chooseData.items.filter(i => !i.already && this.isChosen(i));
    },

    chooseSummary() {
      const counts = {};
      this.chosenNew.forEach(i => { counts[i.viewKey] = (counts[i.viewKey] || 0) + 1; });
      return Object.keys(counts).map(k => this.tc(k === 'meta_ads' ? 'hub.meta_ads.unit' : 'ui.platforms.' + k + '.unit', counts[k])).join(' · ');
    },

    // ---------- manage panel ----------

    manageViewData() {
      return this.manageKey ? this.allViews.find(v => v.key === this.manageKey) || null : null;
    },

    manageCard() {
      return this.manageViewData ? this.cards.find(c => c.platform === this.manageViewData.card) : null;
    },

    manageAccount() {
      return this.manageViewData && this.manageAsset ? this.manageViewData.accounts.find(a => a.asset.id === this.manageAsset) || null : null;
    },

    manageConns() {
      if (!this.manageViewData) return [];
      return this.manageAccount ? [this.manageAccount.conn] : this.manageViewData.conns;
    },

    manageTitle() {
      return this.manageAccount ? this.assetName(this.manageAccount.asset) : (this.manageViewData ? this.manageViewData.name : '');
    },

    manageReconnect() {
      const conn = this.manageConns.find(c => c.needs_attention || c.status === 'revoked');
      return conn ? conn.reconnect_url : null;
    },

    flowSummaryTitle() {
      const results = this.flowState.results;
      const connected = results.filter(r => r.status === 'connected').length;
      const key = connected === results.length ? 'done_title' : (connected ? 'partial_title' : 'none_title');
      return this.t('flow.' + key, { platform: this.flowState.label });
    }
  },

  mounted() {
    // Back from a provider sign-in this page started: choose what to keep.
    let back = null;
    try {
      const raw = window.sessionStorage.getItem(RETURN_KEY);
      window.sessionStorage.removeItem(RETURN_KEY);
      if (raw) back = raw.charAt(0) === '{' ? JSON.parse(raw) : { key: raw, ids: null };
    } catch (e) { back = null; }
    if (back && this.allViews.some(v => v.key === back.key)) {
      if (this.flashMessage && this.flashMessage.tone === 'is-error') {
        this.openConnect(back.key);
        this.connectFailed = true;
      } else {
        this.openChoose(back.key, back.ids);
      }
    } else {
      // Module links point at a provider (#meta, #google ...) or a platform (#instagram).
      const hash = window.location.hash.replace('#', '');
      const view = this.allViews.find(v => v.key === hash) || this.allViews.find(v => v.card === hash);
      if (view) {
        if (view.status === 'not_connected') this.openConnect(view.key);
        else this.openManage(view.key);
      }
    }
    document.addEventListener('keydown', this.onKeydown);
  },

  beforeDestroy() {
    document.removeEventListener('keydown', this.onKeydown);
    document.body.style.overflow = '';
  },

  watch: {
    flashMessage(value) {
      clearTimeout(this.flashTimer);
      if (value) this.flashTimer = setTimeout(() => { this.flashMessage = null; }, value.tone === 'is-error' ? 9000 : 4500);
    },

    // The page behind a modal or panel doesn't scroll while it's open.
    connectKey() { this.lockScroll(); },
    manageKey() { this.lockScroll(); },
    chooseKey() { this.lockScroll(); }
  },

  methods: {
    t(key, params) {
      return this.i18n.t(key, params);
    },

    tc(key, count, params) {
      return this.i18n.tc(key, count, params);
    },

    num(n) {
      return Number(n || 0).toLocaleString(this.i18n.intl);
    },

    initials(name) {
      const parts = String(name || '?').trim().split(/\s+/).filter(Boolean);
      return ((parts[0] || '?')[0] + (parts.length > 1 ? parts[1][0] : (parts[0] || '').slice(1, 2))).toUpperCase();
    },

    avatarStyle(asset) {
      if (asset.avatar_url) return {};
      const seed = String(asset.name || asset.id).split('').reduce((n, ch) => n + ch.charCodeAt(0), 0);
      return { background: AVATAR_COLORS[seed % AVATAR_COLORS.length] };
    },

    assetName(asset) {
      return asset.name || (asset.username ? '@' + asset.username : asset.external_id);
    },

    // Platform copy; Meta Ads has its own block next to the Meta group.
    pText(key, field) {
      return key === 'meta_ads' ? this.t('hub.meta_ads.' + field) : this.t('ui.platforms.' + key + '.' + field);
    },

    // ---------- platform views ----------

    buildView(def) {
      const card = this.cards.find(c => c.platform === def.card);
      if (!card) return null;
      const service = def.service ? (card.google_services || []).find(s => s.key === def.service) : null;
      if (def.service && !service) return null;

      const listed = new Set();
      card.connections.forEach(conn => Object.values(conn.assets || {}).forEach(list => list.forEach(a => listed.add(a.id))));

      const conns = [];
      const accounts = [];
      card.connections.forEach(conn => {
        if (conn.status === 'revoked') return;
        let mine = def.owns || (def.steps || []).includes(conn.step);
        def.kinds.forEach(kind => (conn.assets[kind] || []).forEach(asset => {
          mine = true;
          // A second healthy sign-in for an account already listed (TikTok comments).
          if (asset.linked_asset_id && listed.has(asset.linked_asset_id) && asset.token_ok && !conn.needs_attention) return;
          accounts.push({ asset, kind, conn });
        }));
        if (mine) conns.push(conn);
      });

      // Google Ads & Analytics: any Google service other than the two that have their own card.
      const services = def.flow === 'google'
        ? (card.google_services || []).filter(s => !['youtube', 'business_profile'].includes(s.key) && s.status === 'connected')
        : [];
      const issue = this.viewIssue(def, conns, accounts);
      const connected = accounts.length > 0 || services.length > 0;
      const status = issue && (connected || (conns.length && def.flow !== 'google')) ? 'attention' : (connected ? 'connected' : 'not_connected');

      const caps = CAP_ORDER.filter(cap => accounts.some(a => a.asset.enabled_capabilities.includes(cap)));
      const unitKinds = def.unitKinds || def.kinds;
      const primary = accounts.filter(a => unitKinds.includes(a.kind)).length;
      const ads = accounts.filter(a => !unitKinds.includes(a.kind) && /ads|ad_account/.test(a.kind)).length;
      const countLabel = [
        primary ? this.tc(def.key === 'meta_ads' ? 'hub.meta_ads.unit' : 'ui.platforms.' + def.key + '.unit', primary) : null,
        ads ? this.tc('ui.units.ad_account', ads) : null
      ].filter(Boolean).join(' · ') || services.map(sv => this.t('google_services.services.' + sv.key + '.name')).join(' · ');

      const offers = (card.presentation && card.presentation.benefits) || CAP_ORDER;
      return {
        ...def,
        name: this.pText(def.key, 'name'),
        subtitle: this.pText(def.key, 'subtitle'),
        description: this.pText(def.key, 'description'),
        recommended: (this.workspace.recommended || []).includes(def.key),
        conns, accounts, status, issue, caps, countLabel, ads,
        offers: def.key === 'facebook' ? offers : CAP_ORDER.filter(c => offers.includes(c) && this.kindsOffer(card, def).includes(c)),
        serviceUrl: service ? service.connect_url : null,
        serviceNote: service && service.status === 'unavailable' ? this.t('ui.card.unavailable') : null,
        available: this.viewAvailable(def, card, service)
      };
    },

    // Capabilities this view's account kinds can carry (driver asset_groups).
    kindsOffer(card, def) {
      const groups = (card.presentation && card.presentation.asset_groups) || {};
      const out = new Set();
      def.kinds.forEach(k => ((groups[k] || {}).capabilities || []).forEach(c => out.add(c)));
      return out.size ? Array.from(out) : CAP_ORDER;
    },

    viewAvailable(def, card, service) {
      if (def.flow === 'service') return !!(service && service.connect_url && service.status !== 'unavailable');
      if (def.flow === 'google' || def.flow === 'whatsapp') return true;
      if (def.flow === 'openai') return card.steps.some(s => s.available);
      return this.stepsFor(def, card).some(s => s.available);
    },

    stepsFor(view, cardArg) {
      const card = cardArg || this.cards.find(c => c.platform === view.card);
      if (!card) return [];
      if (view.key === 'facebook' || view.key === 'meta_ads') return card.steps.filter(s => s.key === 'meta.login');
      if (view.key === 'instagram') return card.steps.filter(s => ['meta.login', 'meta.instagram_login'].includes(s.key));
      if (view.key === 'whatsapp') return card.steps.filter(s => s.key === 'meta.whatsapp');
      return card.steps;
    },

    viewIssue(def, conns, accounts) {
      const first = accounts[0];
      const name = (first && (first.asset.username ? '@' + first.asset.username : first.asset.name)) || this.pText(def.key, 'name');
      const conn = conns.find(c => c.needs_attention);
      if (conn) {
        const d = conn.expires_in_days;
        let text;
        if (conn.status === 'expiring' && d !== null && d > 0) text = this.tc('ui.alerts.expiring', d, { name });
        else if (conn.status === 'expiring') text = this.t('ui.alerts.expiring_today', { name });
        else if (conn.status === 'expired') text = this.t('ui.alerts.expired', { name });
        else if (conn.status === 'needs_reauth') text = this.t('ui.alerts.reauth');
        else text = this.t('ui.alerts.error');
        return { text, url: conn.reconnect_url, tone: conn.status === 'expiring' ? 'warn' : 'bad' };
      }
      const broken = accounts.find(a => !a.asset.token_ok);
      if (broken) return { text: this.t('ui.alerts.token', { name: broken.asset.name || name }), url: broken.conn.reconnect_url, tone: 'bad' };
      const flagged = accounts.find(a => a.asset.provider_status);
      if (flagged) return { text: flagged.asset.provider_status, url: null, tone: 'warn' };
      return null;
    },

    // The one line under a platform's name.
    detail(v) {
      if (v.issue) return v.issue.text;
      if (v.status === 'not_connected') {
        // Meta rows carry the "Recommended" tag already; say what the platform does instead.
        if (v.recommended) return v.group === 'meta' ? v.description : this.t('hub.recommended');
        return this.workspace.has_goals && v.group !== 'more' ? this.t('hub.optional') : v.description;
      }
      if (v.key === 'facebook') {
        return [v.countLabel, v.accounts.slice(0, 2).map(a => this.assetName(a.asset)).join(', ')].filter(Boolean).join(' · ');
      }
      if (v.key === 'meta_ads') {
        const cur = v.accounts[0] && v.accounts[0].asset.username;
        return [v.countLabel, cur].filter(Boolean).join(' · ');
      }
      const main = v.accounts.find(a => !/ads|ad_account/.test(a.kind)) || v.accounts[0];
      if (!main) return v.countLabel;
      const handle = main.asset.username ? '@' + main.asset.username : this.assetName(main.asset);
      const days = main.conn.expires_in_days;
      const extra = v.ads ? this.tc('ui.units.ad_account', v.ads) : (days !== null && days >= 0 ? this.tc('hub.renews', Math.max(1, days)) : null);
      return [handle, extra].filter(Boolean).join(' · ');
    },

    // The platform's one button: Fix now, Manage or Connect.
    action(v) {
      if (v.issue) return { label: this.t('ui.card.fix'), cls: 'ch-btn-dark', url: v.issue.url };
      if (v.status !== 'not_connected') return { label: this.t('ui.card.manage'), cls: 'ch-btn-secondary', run: 'manage' };
      if (!v.available) return { label: this.t('ui.card.unavailable'), cls: 'ch-btn-disabled', disabled: true };
      return { label: this.t('hub.connect'), cls: v.group === 'meta' ? 'ch-btn-secondary' : 'ch-btn-primary', run: 'connect' };
    },

    runAction(v) {
      const a = this.action(v);
      if (a.run === 'connect') this.openConnect(v.key);
      else this.openManage(v.key);
    },

    filtered(views) {
      if (this.filter === 'all') return views;
      return views.filter(v => v.status === this.filter);
    },

    statusTone(status) {
      return { connected: 'is-ok', attention: 'is-warn', not_connected: 'is-muted' }[status];
    },

    accountHealth(a) {
      const conn = a.conn;
      const days = conn.expires_in_days;
      if (conn.status === 'revoked' || (conn.needs_attention && conn.status !== 'expiring') || !a.asset.token_ok) {
        return { label: this.t('ui.accounts.reconnect'), access: this.t('ui.accounts.action'), tone: 'is-bad', attention: true };
      }
      if (conn.needs_attention) {
        return { label: this.t('ui.accounts.expiring'), access: days !== null ? this.tc('hub.accounts.ends', Math.max(1, days)) : this.t('ui.accounts.action'), tone: 'is-warn', attention: true };
      }
      if (a.asset.provider_status) return { label: this.t('ui.accounts.action'), access: a.asset.provider_status, tone: 'is-bad', attention: true };
      if (!a.asset.enabled_capabilities.length) return { label: this.t('ui.accounts.off'), access: this.t('ui.accounts.not_used'), tone: 'is-muted', attention: false };
      let access;
      if (days !== null && days >= 0) access = this.tc('ui.accounts.renews', Math.max(1, days));
      else if (a.kind === 'whatsapp') access = this.t('hub.accounts.via_login', { platform: 'Meta' });
      else access = this.t('hub.accounts.refreshes');
      return { label: this.t('ui.accounts.healthy'), access, tone: 'is-ok', attention: false };
    },

    healthBox(conn) {
      if (conn.needs_attention || conn.status === 'revoked') {
        const tone = conn.status === 'expiring' ? 'is-warn' : 'is-bad';
        return { tone, icon: 'bx-error-circle', label: this.attentionTitle(conn) };
      }
      return { tone: 'is-ok', icon: 'bx-check-shield', label: this.t('ui.manage.healthy') };
    },

    renewLabel(conn) {
      const d = conn.expires_in_days;
      if (d === null || d === undefined) return this.t('ui.manage.auto');
      return d >= 0 ? this.tc('ui.manage.renews', Math.max(1, d)) : null;
    },

    // What this sign-in could grant but wasn't allowed.
    missingCaps(conn) {
      if (!conn.upgradable || conn.status === 'revoked') return [];
      const offered = conn.offered_capabilities || [];
      return CAP_ORDER.filter(c => offered.includes(c) && !conn.capabilities.includes(c));
    },

    manageAccountsOf(conn) {
      const list = this.manageViewData.accounts.filter(a => a.conn.id === conn.id);
      return this.manageAccount ? list.filter(a => a.asset.id === this.manageAsset) : list;
    },

    // A Page's Instagram accounts, or an Instagram account's Page.
    linkedOf(a) {
      const meta = this.allViews.filter(v => v.card === 'meta');
      const find = key => (meta.find(v => v.key === key) || { accounts: [] }).accounts;
      if (a.kind === 'page') {
        return find('instagram').filter(i => String(i.asset.linked_page_id) === String(a.asset.external_id)).map(i => ({ ...i, viewKey: 'instagram' }));
      }
      if (a.kind === 'instagram' && a.asset.linked_page_id) {
        return find('facebook').filter(p => String(p.asset.external_id) === String(a.asset.linked_page_id)).map(p => ({ ...p, viewKey: 'facebook' }));
      }
      return [];
    },

    kindBrand(a) { return (KIND_ICONS[a.kind] || ['brand'])[0]; },
    kindIcon(a) { return (KIND_ICONS[a.kind] || [null, 'bx-link'])[1]; },
    kindLabel(a) {
      const title = this.t('views.' + a.kind + '.title');
      const sub = this.t('views.' + a.kind + '.subtitle');
      const base = title.startsWith('views.') ? a.kind : title + (sub.startsWith('views.') ? '' : ' ' + sub);
      if (a.kind === 'instagram' && a.asset.linked_page_id) {
        const page = this.linkedOf(a)[0];
        if (page) return title + ' · ' + this.t('hub.accounts.via_page', { name: this.assetName(page.asset) });
      }
      return base;
    },

    capShort(key) { return this.t('ui.caps.' + key + '.short'); },
    capLabel(key) { return this.t('ui.caps.' + key + '.label'); },
    capHint(key) { return this.t('ui.caps.' + key + '.hint'); },
    capIcon(key) { return CAPS[key] || 'bx-check'; },
    capLong(key, platform) {
      const text = ((this.messages.capabilities || {})[key]) || {};
      return text[platform] || text.default || this.capHint(key);
    },

    attentionTitle(conn) {
      const known = ['expiring', 'expired', 'needs_reauth', 'revoked', 'error'];
      return this.t('attention.' + (known.includes(conn.status) ? conn.status : 'default'));
    },

    ago(iso) {
      const s = Math.max(0, Math.round((Date.now() - new Date(iso).getTime()) / 1000));
      if (s < 60) return this.t('ago.now');
      if (s < 3600) return this.t('ago.minutes', { count: Math.round(s / 60) });
      if (s < 86400) return this.t('ago.hours', { count: Math.round(s / 3600) });
      return this.t('ago.days', { count: Math.round(s / 86400) });
    },

    // ---------- navigation ----------

    setMode(mode) {
      this.mode = mode;
      window.scrollTo({ top: 0, behavior: 'smooth' });
    },

    openConnect(key) {
      this.manageKey = null;
      this.chooseKey = null;
      this.connectFailed = false;
      this.waManual = false;
      this.connectKey = key;
      const v = key !== 'picker' ? this.allViews.find(x => x.key === key) : null;
      if (!v) { this.connectChoice = null; return; }
      if (v.flow === 'instagram') {
        const methods = this.instagramMethods.filter(m => m.available);
        this.connectChoice = methods.length ? methods[0].key : null;
      } else {
        const steps = this.stepsFor(v).filter(s => s.available);
        const pick = steps.find(s => s.primary && !s.connected) || steps.find(s => !s.connected) || steps[0];
        this.connectChoice = pick ? pick.key : null;
      }
    },

    closeConnect() {
      this.connectKey = null;
      this.connectFailed = false;
    },

    openManage(key, assetId = null, choose = false) {
      this.connectKey = null;
      this.chooseKey = null;
      this.confirming = null;
      this.chooseMode = choose;
      this.manageAsset = assetId;
      this.manageKey = key;
    },

    closeManage() {
      this.manageKey = null;
      this.manageAsset = null;
      this.chooseMode = false;
      if (window.location.hash) history.replaceState(null, '', window.location.pathname + window.location.search);
    },

    // After a sign-in: pick which of the new accounts this workspace uses.
    openChoose(key, beforeIds) {
      if (!Array.isArray(beforeIds)) { this.openManage(key, null, true); return; }
      this.chooseBefore = beforeIds;
      this.chooseKey = key;
      const data = this.chooseData;
      const fresh = data ? data.items.filter(i => !i.already) : [];
      if (!fresh.length) { this.chooseKey = null; this.openManage(key, null, true); return; }
      const picks = {};
      fresh.forEach(i => { picks[i.asset.id] = i.asset.enabled_capabilities.length > 0 || i.asset.available_capabilities.length === 0; });
      this.chosen = picks;
    },

    closeChoose() {
      if (this.chooseSaving) return;
      this.chooseKey = null;
    },

    isChosen(item) {
      return item.already || !!this.chosen[item.asset.id];
    },

    toggleChosen(item) {
      if (item.already) return;
      this.$set(this.chosen, item.asset.id, !this.chosen[item.asset.id]);
    },

    // New accounts left unticked stay in the list but are switched off here.
    confirmChoose() {
      const data = this.chooseData;
      const changes = data.items.filter(i => !i.already).map(i => {
        const want = !!this.chosen[i.asset.id];
        const has = i.asset.enabled_capabilities.length > 0;
        if (want === has) return null;
        return { item: i, next: want ? i.asset.available_capabilities.slice() : [] };
      }).filter(Boolean);
      const count = this.chosenNew.length;
      const finish = (failed) => {
        this.chooseSaving = false;
        this.chooseKey = null;
        this.flashMessage = failed
          ? { tone: 'is-error', text: this.t('hub.choose.failed') }
          : (count ? { tone: 'is-success', text: this.tc('hub.choose.done', count, { workspace: this.workspaceName }) } : null);
      };
      if (!changes.length) { finish(false); return; }
      this.chooseSaving = true;
      Promise.all(changes.map(c => window.axios.patch(this.url('asset', c.item.asset.id), { enabled_capabilities: c.next })
        .then(({ data: res }) => {
          const card = this.cards.find(x => x.connections.some(conn => conn.id === c.item.conn.id));
          this.replaceConnection(card, res.connection);
          return true;
        })
        .catch(() => false)))
        .then(results => finish(results.includes(false)));
    },

    // Remember which accounts existed, so the return trip can show only what's new.
    rememberReturn(key) {
      const ids = [];
      this.cards.forEach(c => c.connections.forEach(conn => Object.values(conn.assets || {}).forEach(list => list.forEach(a => ids.push(a.id)))));
      try { window.sessionStorage.setItem(RETURN_KEY, JSON.stringify({ key, ids })); } catch (e) { /* storage blocked */ }
    },

    lockScroll() {
      document.body.style.overflow = this.connectKey || this.manageKey || this.chooseKey ? 'hidden' : '';
    },

    onKeydown(event) {
      if (event.key !== 'Escape') return;
      if (this.chooseKey) this.closeChoose();
      else if (this.connectKey) this.closeConnect();
      else if (this.manageKey) this.closeManage();
    },

    authorizeUrl(platform, name = 'authorize') {
      return this.urls[name].replace('__PLATFORM__', platform);
    },

    url(name, id) {
      return this.urls[name].replace('__ID__', id);
    },

    // ---------- actions (existing endpoints) ----------

    replaceConnection(card, updated) {
      if (!card) return;
      const i = card.connections.findIndex(c => c.id === updated.id);
      if (i !== -1) this.$set(card.connections, i, updated);
    },

    toggleAsset(conn, asset) {
      const next = asset.enabled_capabilities.length ? [] : asset.available_capabilities.slice();
      this.save(conn, asset, next);
    },

    toggleCapability(conn, asset, cap) {
      const on = asset.enabled_capabilities.includes(cap);
      const next = on ? asset.enabled_capabilities.filter(c => c !== cap) : asset.enabled_capabilities.concat(cap);
      this.save(conn, asset, next);
    },

    // Optimistic: flip now, put it back if the server says no.
    save(conn, asset, next) {
      const card = this.cards.find(c => c.connections.some(x => x.id === conn.id));
      const previous = asset.enabled_capabilities;
      asset.enabled_capabilities = next;
      this.$set(this.saving, asset.id, true);

      window.axios.patch(this.url('asset', asset.id), { enabled_capabilities: next })
        .then(({ data }) => this.replaceConnection(card, data.connection))
        .catch(() => {
          asset.enabled_capabilities = previous;
          this.flashMessage = { tone: 'is-error', text: this.t('toast.save_failed') };
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
            : { tone: 'is-success', text: this.t('toast.checked', { platform: card.label }) };
        })
        .catch(() => { this.flashMessage = { tone: 'is-error', text: this.t('toast.check_failed') }; })
        .finally(() => this.$delete(this.busy, conn.id));
    },

    disconnect(card, conn) {
      if (!card || !conn) return;
      this.$set(this.busy, conn.id, 'disconnect');

      window.axios.delete(this.url('disconnect', conn.id))
        .then(({ data }) => {
          const i = this.cards.findIndex(c => c.platform === card.platform);
          this.$set(this.cards, i, data.card);
          this.confirming = null;
          this.manageAsset = null;
          this.flashMessage = { tone: 'is-success', text: this.t('toast.disconnected') };
        })
        .catch(() => { this.flashMessage = { tone: 'is-error', text: this.t('toast.disconnect_failed') }; })
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
            this.flashMessage = { tone: 'is-error', text: this.t('toast.wa_no_number') };
            return;
          }
          window.axios.post(cfg.store_url, { code, phone_number_id: session.phone_number_id, waba_id: session.waba_id })
            .then(({ data }) => {
              this.flashMessage = { tone: 'is-success', text: data.message || this.t('toast.wa_connected') };
              this.rememberReturn('whatsapp');
              setTimeout(() => window.location.reload(), 900);
            })
            .catch((error) => {
              this.flashMessage = { tone: 'is-error', text: (error.response && error.response.data && error.response.data.message) || this.t('toast.wa_failed') };
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
        this.flashMessage = { tone: 'is-error', text: this.t('toast.fb_load_failed') };
      });
    }
  }
};
</script>

<style scoped>
/* Socialeaz design system (Canva "Workspaces & Social Connections", Nov 2026). */
.ch {
  --p600: #2563EB; --p700: #1D4ED8; --p100: #DBE8FE; --p50: #EFF5FF; --accent: #4F46E5;
  --ink: #0F172A; --ink5: #64748B; --line: #E5EAF2; --canvas: #F5F7FB;
  --ok: #15803D; --ok-bg: #DCFCE7; --warn: #B45309; --warn-bg: #FEF3C7; --bad: #B91C1C; --bad-bg: #FEE2E2;
  --r-sm: 8px; --r-md: 12px; --r-lg: 16px; --r-xl: 22px;
  --sh-sm: 0 1px 2px rgba(15,23,42,.05); --sh-md: 0 4px 14px rgba(15,23,42,.06); --sh-lg: 0 24px 60px rgba(15,23,42,.18);
  font-family: Inter, 'Noto Sans Arabic', system-ui, -apple-system, 'Segoe UI', sans-serif;
  color: var(--ink); padding: 24px; display: flex; flex-direction: column; gap: 20px; min-width: 0;
}
.ch *, .ch *::before, .ch *::after { box-sizing: border-box; }
.sr-only { position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px; overflow: hidden; clip: rect(0,0,0,0); white-space: nowrap; border: 0; }
.ch-muted { color: var(--ink5); }
.sm-text { font-size: 12px; }
.ch-warn-text { color: var(--warn); }
.ch-link { padding: 0; border: 0; background: none; color: var(--p600); font: inherit; font-weight: 600; cursor: pointer; text-decoration: none; }
.ch-link:hover { text-decoration: underline; }
.ch-inline-form { display: inline; margin: 0; }
[dir="rtl"] .ch-flip { transform: scaleX(-1); }

/* Buttons */
.ch-btn { display: inline-flex; align-items: center; justify-content: center; gap: 7px; min-height: 40px; padding: 0 16px; border: 1px solid transparent; border-radius: var(--r-md); font: inherit; font-size: 14px; font-weight: 600; line-height: 1; white-space: nowrap; cursor: pointer; text-decoration: none; transition: background .15s, border-color .15s, box-shadow .15s, color .15s; }
.ch-btn i { font-size: 18px; }
.ch-btn.sm { min-height: 34px; padding: 0 12px; font-size: 13px; border-radius: var(--r-sm); }
.ch-btn-lg { min-height: 50px; padding: 0 22px; font-size: 15px; border-radius: var(--r-md); }
.ch-btn-block { width: 100%; }
.ch-btn-primary { background: var(--p600); color: #fff; box-shadow: 0 6px 16px rgba(37,99,235,.24); }
.ch-btn-primary:hover { background: var(--p700); color: #fff; }
.ch-btn-secondary { background: #fff; border-color: var(--line); color: var(--ink); box-shadow: var(--sh-sm); }
.ch-btn-secondary:hover { border-color: #CBD5E1; color: var(--ink); }
.ch-btn-soft { background: var(--p50); color: var(--p600); }
.ch-btn-soft:hover { background: var(--p100); color: var(--p700); }
.ch-btn-ghost { background: transparent; color: var(--ink); }
.ch-btn-ghost:hover { background: var(--canvas); }
.ch-btn-dark { background: var(--ink); color: #fff; }
.ch-btn-dark:hover { background: #1E293B; color: #fff; }
.ch-btn-danger { background: #fff; border-color: #FECACA; color: var(--bad); }
.ch-btn-danger:hover { background: var(--bad-bg); }
.ch-btn-danger-solid { background: var(--bad); color: #fff; }
.ch-btn-disabled { background: var(--canvas); color: var(--ink5); cursor: default; }
.ch-btn:disabled { opacity: .55; cursor: not-allowed; }
.ch-btn:focus-visible, .ch-icon-btn:focus-visible, .ch-seg-btn:focus-visible, .ch-kpi:focus-visible, .ch-tr:focus-visible, .ch-linked:focus-visible, .ch-picker-item:focus-visible { outline: 3px solid rgba(37,99,235,.35); outline-offset: 2px; }
.ch-icon-btn { display: grid; place-items: center; width: 40px; height: 40px; flex-shrink: 0; border: 1px solid var(--line); border-radius: var(--r-md); background: #fff; color: var(--ink); font-size: 20px; cursor: pointer; }
.ch-icon-btn:hover { background: var(--canvas); }

/* Pills, tags, chips */
.ch-pill { display: inline-flex; align-items: center; gap: 6px; padding: 4px 10px; border-radius: 999px; font-size: 12.5px; font-weight: 600; white-space: nowrap; }
.ch-pill::before { content: ''; width: 6px; height: 6px; border-radius: 50%; background: currentColor; }
.ch-pill.sm { padding: 3px 8px; font-size: 11.5px; }
.ch-pill.is-ok { background: var(--ok-bg); color: var(--ok); }
.ch-pill.is-warn { background: var(--warn-bg); color: var(--warn); }
.ch-pill.is-bad { background: var(--bad-bg); color: var(--bad); }
.ch-pill.is-muted { background: #F1F5F9; color: var(--ink5); }
.ch-tag { display: inline-block; margin-inline-start: 6px; padding: 2px 8px; border-radius: 999px; background: var(--p50); color: var(--p600); font-size: 11.5px; font-weight: 600; vertical-align: middle; }
.ch-tag.is-ok { background: var(--ok-bg); color: var(--ok); }
.ch-chips { display: flex; flex-wrap: wrap; gap: 6px; }
.ch-chip { display: inline-flex; align-items: center; gap: 4px; padding: 4px 9px; border: 1px solid var(--p100); border-radius: var(--r-sm); background: var(--p50); color: var(--p700); font-size: 12.5px; font-weight: 600; }
.ch-chip.is-off { border-color: var(--line); background: #fff; color: var(--ink5); text-decoration: line-through; }

/* Brand tiles (44px, radius 12) */
.ch-tile { display: inline-grid; place-items: center; flex-shrink: 0; width: 44px; height: 44px; border-radius: var(--r-md); color: #fff; font-size: 24px; font-weight: 800; }
.ch-tile.sm { width: 36px; height: 36px; border-radius: 10px; font-size: 20px; }
.ch-tile.xs { width: 26px; height: 26px; border-radius: 7px; font-size: 16px; }
.ch-tile.lg { width: 72px; height: 72px; border-radius: 18px; font-size: 38px; }
.ch-tile.is-facebook { background: #1877F2; }
.ch-tile.is-instagram { background: radial-gradient(circle at 30% 107%, #FDF497 0%, #FD5949 45%, #D6249F 60%, #285AEB 90%); }
.ch-tile.is-threads, .ch-tile.is-tiktok, .ch-tile.is-x { background: #000; }
.ch-tile.is-snapchat { background: #FFFC00; color: #000; }
.ch-tile.is-linkedin { background: #0A66C2; }
.ch-tile.is-youtube { background: #fff; color: #FF0000; border: 1px solid var(--line); }
.ch-tile.is-gbp, .ch-tile.is-google { background: #fff; color: #1A73E8; border: 1px solid var(--line); }
.ch-tile.is-meta { background: #fff; color: #0866FF; border: 1px solid var(--line); }
.ch-tile.is-whatsapp { background: #25D366; }
.ch-tile.is-pinterest { background: #E60023; }
.ch-tile.is-openai { background: #10A37F; }
.ch-tile.is-brand { background: linear-gradient(135deg, var(--p600), var(--accent)); }

/* Avatars */
.ch-avatars { display: inline-flex; }
.ch-avatar { position: relative; display: inline-grid; place-items: center; flex-shrink: 0; width: 30px; height: 30px; border: 2px solid #fff; border-radius: 50%; background: var(--p600); color: #fff; font-size: 11px; font-weight: 700; overflow: visible; }
.ch-avatars .ch-avatar + .ch-avatar { margin-inline-start: -8px; }
.ch-avatar img { width: 100%; height: 100%; border-radius: 50%; object-fit: cover; }
.ch-avatar.is-more { background: #F1F5F9; color: var(--ink5); }
.ch-avatar.lg { width: 42px; height: 42px; font-size: 14px; }
.ch-avatar-badge { position: absolute; inset-inline-end: -4px; bottom: -4px; width: 18px; height: 18px; border: 2px solid #fff; border-radius: 6px; font-size: 11px; }

/* Header */
.ch-head { display: flex; align-items: flex-end; justify-content: space-between; gap: 16px; flex-wrap: wrap; }
.ch-crumb { margin: 0 0 6px; color: var(--ink5); font-size: 14px; }
.ch-crumb-strong { color: var(--p600); font-weight: 600; }
.ch-title { margin: 0 0 6px; font-size: 26px; line-height: 34px; font-weight: 700; letter-spacing: -.02em; color: var(--ink); }
.ch-sub { margin: 0; color: var(--ink5); font-size: 14.5px; line-height: 22px; }
.ch-head-actions { display: flex; gap: 10px; flex-wrap: wrap; }

/* Banners (gate, wizard, guided flow) */
.ch-banner { display: flex; align-items: center; gap: 14px; flex-wrap: wrap; padding: 14px 16px; border: 1px solid var(--p100); border-radius: var(--r-lg); background: var(--p50); }
.ch-banner.is-gate { border-color: #FDE68A; background: #FFFBEB; }
.ch-banner.is-done { border-color: #BBF7D0; background: #F0FDF4; }
.ch-banner-icon { display: grid; place-items: center; width: 40px; height: 40px; flex-shrink: 0; border-radius: var(--r-md); background: #fff; color: var(--p600); font-size: 20px; }
.is-gate .ch-banner-icon { color: var(--warn); }
.ch-banner-text { flex: 1; min-width: 220px; display: flex; flex-direction: column; gap: 4px; font-size: 13.5px; color: var(--ink5); }
.ch-banner-text strong { color: var(--ink); font-size: 14.5px; }
.ch-banner-actions { display: flex; gap: 8px; flex-wrap: wrap; }
.ch-progress { height: 6px; margin-top: 4px; border-radius: 99px; background: #fff; overflow: hidden; }
.ch-progress span { display: block; height: 100%; background: var(--p600); }

/* Filters */
.ch-filterbar { display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap; }
.ch-seg { display: inline-flex; gap: 2px; padding: 4px; border: 1px solid var(--line); border-radius: var(--r-md); background: #fff; overflow-x: auto; max-width: 100%; }
.ch-seg-btn { display: inline-flex; align-items: center; gap: 6px; padding: 8px 14px; border: 0; border-radius: var(--r-sm); background: none; color: var(--ink5); font: inherit; font-size: 14px; font-weight: 500; white-space: nowrap; cursor: pointer; }
.ch-seg-btn:hover { color: var(--ink); }
.ch-seg-btn.is-active { background: var(--p600); color: #fff; }
.ch-seg-count { padding: 1px 7px; border-radius: 999px; background: #F1F5F9; color: var(--ink5); font-size: 12px; }
.is-active .ch-seg-count { background: rgba(255,255,255,.22); color: #fff; }

/* Platform cards */
.ch-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 18px; }
.ch-card { display: flex; flex-direction: column; gap: 14px; min-width: 0; padding: 20px; border: 1px solid var(--line); border-radius: var(--r-xl); background: #fff; box-shadow: var(--sh-md); }
.ch-card.is-attention { border-color: #FCD34D; box-shadow: 0 0 0 1px #FDE68A, var(--sh-md); }
.ch-card.is-not_connected { border-style: dashed; box-shadow: none; }
.ch-card-head { display: flex; align-items: flex-start; gap: 12px; }
.ch-card-title { flex: 1; min-width: 0; }
.ch-card-title h3 { margin: 0; font-size: 17px; line-height: 24px; font-weight: 650; color: var(--ink); }
.ch-card-title p { margin: 2px 0 0; color: var(--ink5); font-size: 13.5px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.ch-card-accounts { display: flex; align-items: center; gap: 10px; min-height: 30px; }
.ch-card-count { font-size: 14px; color: var(--ink); }
.ch-card-alert { display: flex; align-items: flex-start; gap: 8px; padding: 10px 12px; border: 1px solid #FDE68A; border-radius: var(--r-md); background: #FFFBEB; color: #92400E; font-size: 13.5px; line-height: 1.45; }
.ch-card-alert.is-bad { border-color: #FECACA; background: #FEF2F2; color: var(--bad); }
.ch-card-alert i { font-size: 17px; margin-top: 1px; }
.ch-card-desc { flex: 1; margin: 0; color: var(--ink5); font-size: 14px; line-height: 22px; }
.ch-card-actions { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-top: auto; }
.ch-empty-filter { grid-column: 1 / -1; margin: 0; padding: 28px; border: 1px dashed var(--line); border-radius: var(--r-lg); color: var(--ink5); text-align: center; }

/* More integrations */
.ch-more { display: flex; flex-direction: column; gap: 12px; }

.ch-more-head p { margin: 2px 0 0; color: var(--ink5); font-size: 13.5px; }
.ch-more-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; }
.ch-mini { display: flex; align-items: center; gap: 12px; min-width: 0; padding: 14px 16px; border: 1px solid var(--line); border-radius: var(--r-lg); background: #fff; }
.ch-mini-text { flex: 1; min-width: 0; display: flex; flex-direction: column; }
.ch-mini-text strong { font-size: 14.5px; }
.ch-mini-text small { color: var(--ink5); font-size: 12.5px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

/* Accounts table */
.ch-tablebar { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
.ch-search, .ch-select { display: flex; align-items: center; gap: 8px; height: 44px; padding: 0 14px; border: 1px solid var(--line); border-radius: var(--r-md); background: #fff; color: var(--ink5); }
.ch-search { flex: 1 1 260px; max-width: 320px; }
.ch-search input, .ch-select select { width: 100%; border: 0; outline: none; background: none; color: var(--ink); font: inherit; font-size: 14px; }
.ch-search:focus-within, .ch-select:focus-within { border-color: var(--p600); box-shadow: 0 0 0 3px rgba(37,99,235,.15); }
.ch-seg-end { margin-inline-start: auto; }
.ch-table { border: 1px solid var(--line); border-radius: var(--r-xl); background: #fff; box-shadow: var(--sh-sm); overflow: hidden; }
.ch-tr { display: grid; grid-template-columns: minmax(0, 1.4fr) minmax(0, 1.3fr) minmax(0, .8fr) minmax(0, .9fr); align-items: center; gap: 16px; width: 100%; padding: 14px 20px; border: 0; border-top: 1px solid var(--line); background: none; color: inherit; font: inherit; text-align: start; cursor: pointer; }
.ch-tr:hover { background: #FAFBFD; }
.ch-th { border-top: 0; background: #FAFBFD; color: var(--ink5); font-size: 12px; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; cursor: default; }
.ch-td-account { display: flex; align-items: center; gap: 12px; min-width: 0; }
.ch-td-name { display: flex; flex-direction: column; min-width: 0; flex: 1; }
.ch-td-name strong { font-size: 14.5px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.ch-td-name small { color: var(--ink5); font-size: 12.5px; }
.ch-td-used { color: var(--ink); font-size: 14px; }
.ch-td-health { display: flex; flex-direction: column; align-items: flex-start; gap: 4px; }
.ch-td-health small { color: var(--ink5); font-size: 12.5px; }

/* Modal */
.ch-modal-backdrop { position: fixed; inset: 0; z-index: 1095; display: grid; place-items: center; padding: 24px; background: rgba(15,23,42,.45); overflow-y: auto; }
.ch-modal { display: flex; flex-direction: column; width: min(1000px, 100%); max-height: calc(100vh - 48px); border-radius: 24px; background: #fff; box-shadow: var(--sh-lg); overflow: hidden; }
.ch-modal-head, .ch-drawer-head { display: flex; align-items: center; gap: 14px; padding: 20px 24px; border-bottom: 1px solid var(--line); }
.ch-modal-title { flex: 1; min-width: 0; }
.ch-modal-title h2 { margin: 0; font-size: 20px; line-height: 28px; font-weight: 700; }
.ch-modal-title p { margin: 2px 0 0; color: var(--ink5); font-size: 14px; }
.ch-modal-title p strong { color: var(--ink); }
.ch-modal-body { flex: 1; overflow-y: auto; padding: 24px; display: flex; flex-direction: column; gap: 16px; }
.ch-modal-foot { display: flex; align-items: center; gap: 14px; padding: 16px 24px; border-top: 1px solid var(--line); }
.ch-modal-foot .sm-text { margin-inline-start: auto; }
.ch-modal-h3 { margin: 0; font-size: 17px; font-weight: 650; }
.ch-modal-h3.is-center, .ch-modal-sub.is-center { text-align: center; }
.ch-modal-sub { margin: -8px 0 0; color: var(--ink5); font-size: 14px; line-height: 22px; }
.ch-stepper { display: flex; align-items: center; gap: 8px; margin: 0; padding: 0; list-style: none; }
.ch-stepper li { display: flex; align-items: center; gap: 6px; color: var(--ink5); font-size: 13.5px; font-weight: 500; }
.ch-stepper li + li::before { content: ''; width: 28px; height: 2px; margin-inline-end: 4px; background: var(--line); }
.ch-stepper-dot { display: grid; place-items: center; width: 26px; height: 26px; border: 1.5px solid #CBD5E1; border-radius: 50%; font-size: 12px; }
.ch-stepper li.is-current { color: var(--p600); font-weight: 600; }
.ch-stepper li.is-current .ch-stepper-dot { border-color: var(--p600); background: var(--p600); color: #fff; box-shadow: 0 0 0 4px var(--p50); }

.ch-split { display: grid; grid-template-columns: minmax(0, 1.5fr) minmax(0, 1fr); gap: 24px; }
.ch-aside { display: flex; flex-direction: column; gap: 14px; padding: 18px; border-radius: var(--r-lg); background: var(--canvas); align-self: start; }
.ch-list-title { margin: 16px 0 8px; font-size: 15px; font-weight: 650; }
.ch-aside .ch-list-title { margin-top: 0; }
.ch-perms { display: flex; flex-direction: column; margin: 8px 0 0; padding: 0; list-style: none; }
.ch-perms li { display: flex; align-items: flex-start; gap: 12px; padding: 12px 0; border-bottom: 1px solid var(--line); }
.ch-perms li > span:nth-child(2) { flex: 1; display: flex; flex-direction: column; }
.ch-perms strong { font-size: 14.5px; }
.ch-perms small { color: var(--ink5); font-size: 13px; line-height: 1.5; }
.ch-perms code { padding: 2px 8px; border-radius: 6px; background: var(--canvas); color: var(--ink5); font-size: 11.5px; white-space: nowrap; }
.ch-perm-icon { display: grid; place-items: center; width: 36px; height: 36px; flex-shrink: 0; border: 1px solid var(--line); border-radius: 10px; color: var(--p600); font-size: 18px; }
.ch-numbered { margin: 0; padding-inline-start: 20px; display: flex; flex-direction: column; gap: 8px; font-size: 14px; }
.ch-handshake { display: flex; align-items: center; justify-content: center; gap: 16px; margin-bottom: 8px; }
.ch-dots { color: var(--ink5); font-size: 22px; }

.ch-note { display: flex; align-items: flex-start; gap: 8px; margin: 0; padding: 12px 14px; border-radius: var(--r-md); font-size: 13.5px; line-height: 1.55; }
.ch-note i { font-size: 17px; margin-top: 1px; flex-shrink: 0; }
.ch-note .ch-link { margin-inline-start: auto; white-space: nowrap; }
.ch-note.is-info { background: var(--p50); color: #1E3A8A; }
.ch-note.is-ok { background: #F0FDF4; color: #14532D; }
.ch-note.is-warn { background: #FFFBEB; color: #92400E; border: 1px solid #FDE68A; }
.ch-note.is-bad { background: #FEF2F2; color: var(--bad); }

/* Choice cards */
.ch-methods { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
.ch-method, .ch-option { position: relative; display: flex; flex-direction: column; gap: 12px; padding: 18px; border: 1.5px solid var(--line); border-radius: var(--r-lg); background: #fff; cursor: pointer; }
.ch-method.is-picked, .ch-option.is-picked { border-color: var(--p600); box-shadow: 0 0 0 3px var(--p50); }
.ch-method.is-disabled, .ch-option.is-disabled { opacity: .55; cursor: not-allowed; }
.ch-method input, .ch-option input { position: absolute; opacity: 0; pointer-events: none; }
.ch-method-head { display: flex; align-items: flex-start; gap: 12px; }
.ch-method-head > span:last-child { display: flex; flex-direction: column; }
.ch-method-head strong { font-size: 16px; }
.ch-method-head small, .ch-option-text small { color: var(--ink5); font-size: 13px; line-height: 1.5; }
.ch-radio { flex-shrink: 0; width: 20px; height: 20px; margin-top: 2px; border: 2px solid #CBD5E1; border-radius: 50%; }
.is-picked .ch-radio { border: 6px solid var(--p600); }
.ch-method input:focus-visible ~ .ch-method-head .ch-radio, .ch-option input:focus-visible + .ch-radio { box-shadow: 0 0 0 3px rgba(37,99,235,.3); }
.ch-options { display: flex; flex-direction: column; gap: 10px; }
.ch-option { flex-direction: row; align-items: flex-start; padding: 14px 16px; }
.ch-option-text { display: flex; flex-direction: column; gap: 2px; }
.ch-option-text strong { font-size: 14.5px; }
.ch-before { display: flex; align-items: center; gap: 18px; flex-wrap: wrap; padding: 14px 18px; border: 1px solid var(--line); border-radius: var(--r-lg); font-size: 14px; }
.ch-before span { display: inline-flex; align-items: center; gap: 6px; color: var(--ink5); }
.ch-before i { color: var(--ok); font-size: 18px; }
.ch-form { display: flex; flex-direction: column; gap: 12px; }
.ch-form label { display: flex; flex-direction: column; gap: 6px; font-size: 13px; font-weight: 600; }
.ch-form input { height: 44px; padding: 0 12px; border: 1px solid var(--line); border-radius: var(--r-md); font: inherit; font-size: 14px; font-weight: 400; }
.ch-form input:focus { outline: none; border-color: var(--p600); box-shadow: 0 0 0 3px rgba(37,99,235,.15); }
.ch-wa-actions { display: flex; gap: 10px; flex-wrap: wrap; }

.ch-picker { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 10px; }
.ch-picker-item { display: flex; align-items: center; gap: 12px; padding: 12px 14px; border: 1px solid var(--line); border-radius: var(--r-lg); background: #fff; color: inherit; font: inherit; text-align: start; cursor: pointer; }
.ch-picker-item:hover:not(:disabled) { border-color: var(--p100); background: var(--p50); }
.ch-picker-item:disabled { opacity: .55; cursor: not-allowed; }
.ch-picker-item > i { color: var(--ink5); font-size: 20px; }
[dir="rtl"] .ch-picker-item > i { transform: scaleX(-1); }
.ch-picker-text { flex: 1; min-width: 0; display: flex; flex-direction: column; }
.ch-picker-text strong { font-size: 14.5px; }
.ch-picker-text small { color: var(--ink5); font-size: 12.5px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

/* Manage panel */
.ch-drawer-backdrop { position: fixed; inset: 0; z-index: 1090; background: rgba(15,23,42,.4); }
.ch-drawer { position: fixed; z-index: 1092; top: 0; bottom: 0; inset-inline-end: 0; display: flex; flex-direction: column; width: min(520px, 100vw); background: #fff; box-shadow: var(--sh-lg); }
.ch-drawer-body { flex: 1; overflow-y: auto; padding: 20px 24px; display: flex; flex-direction: column; gap: 18px; }
.ch-drawer-foot { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; padding: 16px 24px; border-top: 1px solid var(--line); }
.ch-success { display: flex; gap: 12px; padding: 16px; border-radius: var(--r-lg); background: #F0FDF4; }
.ch-success > div { display: flex; flex-direction: column; gap: 4px; font-size: 13.5px; color: #14532D; line-height: 1.5; }
.ch-success strong { color: var(--ink); font-size: 15px; }
.ch-success-icon { display: grid; place-items: center; width: 36px; height: 36px; flex-shrink: 0; border-radius: 50%; background: var(--ok); color: #fff; font-size: 22px; }
.ch-conn { display: flex; flex-direction: column; gap: 12px; padding-bottom: 18px; border-bottom: 1px solid var(--line); }
.ch-conn:last-child { border-bottom: 0; }
.ch-health { display: flex; align-items: flex-start; gap: 12px; flex-wrap: wrap; padding: 14px 16px; border: 1px solid #BBF7D0; border-radius: var(--r-lg); background: #F0FDF4; }
.ch-health > i { font-size: 22px; color: var(--ok); }
.ch-health.is-warn { border-color: #FDE68A; background: #FFFBEB; }
.ch-health.is-warn > i { color: var(--warn); }
.ch-health.is-bad { border-color: #FECACA; background: #FEF2F2; }
.ch-health.is-bad > i { color: var(--bad); }
.ch-health-text { flex: 1; min-width: 180px; display: flex; flex-direction: column; gap: 2px; font-size: 13px; color: var(--ink5); line-height: 1.5; }
.ch-health-text strong { color: var(--ink); font-size: 14.5px; }
.ch-health-actions { display: flex; gap: 8px; }
.ch-account { display: flex; flex-direction: column; gap: 10px; padding: 14px; border: 1px solid var(--line); border-radius: var(--r-lg); }
.ch-account.is-off { background: #FAFBFD; }
.ch-account-head { display: flex; align-items: center; gap: 12px; }
.ch-uses { display: flex; flex-direction: column; padding-top: 6px; border-top: 1px solid var(--line); }
.ch-uses-title { margin: 4px 0; color: var(--ink5); font-size: 12px; font-weight: 700; letter-spacing: .05em; text-transform: uppercase; }
.ch-use { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 8px 0; }
.ch-use + .ch-use { border-top: 1px solid #F1F5F9; }
.ch-use > span { display: flex; flex-direction: column; }
.ch-use strong { font-size: 14px; font-weight: 600; }
.ch-use small { color: var(--ink5); font-size: 12.5px; }
.ch-none { margin: 0; font-size: 13.5px; }
.ch-danger { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; padding: 14px 16px; border: 1px solid #FECACA; border-radius: var(--r-lg); background: #FFF7F7; }
.ch-danger > div { flex: 1; min-width: 200px; display: flex; flex-direction: column; gap: 2px; font-size: 13px; color: var(--ink5); line-height: 1.5; }
.ch-danger strong { color: var(--bad); font-size: 14px; }
.ch-confirm { display: inline-flex; gap: 6px; }

/* Switch */
.ch-switch { position: relative; display: inline-block; flex-shrink: 0; width: 44px; height: 26px; cursor: pointer; }
.ch-switch.sm { width: 38px; height: 22px; }
.ch-switch input { position: absolute; opacity: 0; width: 100%; height: 100%; margin: 0; cursor: pointer; }
.ch-switch span { position: absolute; inset: 0; border-radius: 999px; background: #CBD5E1; transition: background .15s; pointer-events: none; }
.ch-switch span::after { content: ''; position: absolute; top: 3px; inset-inline-start: 3px; width: 20px; height: 20px; border-radius: 50%; background: #fff; box-shadow: 0 1px 2px rgba(0,0,0,.2); transition: transform .15s; }
.ch-switch.sm span::after { width: 16px; height: 16px; }
.ch-switch input:checked + span { background: var(--p600); }
.ch-switch input:checked + span::after { transform: translateX(18px); }
.ch-switch.sm input:checked + span::after { transform: translateX(16px); }
[dir="rtl"] .ch-switch input:checked + span::after { transform: translateX(-18px); }
[dir="rtl"] .ch-switch.sm input:checked + span::after { transform: translateX(-16px); }
.ch-switch input:focus-visible + span { box-shadow: 0 0 0 3px rgba(37,99,235,.3); }
.ch-switch input:disabled + span { opacity: .5; }

/* Toast */
.ch-flash { position: fixed; right: 24px; bottom: calc(24px + env(safe-area-inset-bottom, 0px)); z-index: 1100; max-width: min(440px, calc(100vw - 32px)); display: flex; align-items: center; gap: 10px; padding: 13px 14px 13px 16px; border-radius: 14px; font-size: 13.5px; font-weight: 500; box-shadow: 0 18px 40px rgba(16,24,40,.18); animation: ch-toast-in .22s ease-out; }
[dir="rtl"] .ch-flash { right: auto; left: 24px; }
.ch-flash i { font-size: 18px; }
.ch-flash.is-success { background: #E8F8EE; color: #166534; }
.ch-flash.is-error { background: #FDECEC; color: #991B1B; }
.ch-flash-x { display: grid; place-items: center; margin-inline-start: auto; padding: 0; border: 0; background: none; color: inherit; cursor: pointer; opacity: .7; }
@keyframes ch-toast-in { from { opacity: 0; transform: translateY(8px); } to { opacity: 1; transform: none; } }

/* Transitions */
.ch-fade-enter-active, .ch-fade-leave-active { transition: opacity .18s ease; }
.ch-fade-enter, .ch-fade-leave-to { opacity: 0; }
.ch-slide-enter-active, .ch-slide-leave-active { transition: transform .22s ease; }
.ch-slide-enter, .ch-slide-leave-to { transform: translateX(100%); }
[dir="rtl"] .ch-slide-enter, [dir="rtl"] .ch-slide-leave-to { transform: translateX(-100%); }
@media (prefers-reduced-motion: reduce) {
  .ch-flash { animation: none; }
  .ch-fade-enter-active, .ch-fade-leave-active, .ch-slide-enter-active, .ch-slide-leave-active { transition: none; }
}

/* Responsive */
@media (max-width: 1199.98px) {
  .ch-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
  .ch-stepper .ch-stepper-label { display: none; }
}
@media (max-width: 991.98px) {
  .ch-split, .ch-methods { grid-template-columns: minmax(0, 1fr); }
  .ch-kpis { grid-template-columns: repeat(2, minmax(0, 1fr)); }
  .ch-row { grid-template-columns: auto minmax(0, 1fr) auto; }
  .ch-row-chips { grid-column: 2 / -1; grid-row: 2; }
  .ch-row .ch-status { display: none; }
  .ch-tr { grid-template-columns: minmax(0, 1fr) auto auto; }
  .ch-td-used, .ch-th span:nth-child(2) { display: none; }
}
@media (max-width: 767.98px) {
  .ch { padding: 16px; gap: 16px; }
  .ch-grid, .ch-more-grid, .ch-picker { grid-template-columns: minmax(0, 1fr); }
  .ch-head { align-items: stretch; }
  .ch-head-actions { width: 100%; }
  .ch-head-actions .ch-btn { flex: 1; }
  .ch-title { font-size: 24px; }
  .ch-filterbar { flex-direction: column; align-items: stretch; }
    .ch-modal-backdrop { padding: 0; place-items: end stretch; }
  .ch-modal { width: 100%; max-height: 92vh; border-radius: 22px 22px 0 0; }
  .ch-stepper { display: none; }
  .ch-modal-head, .ch-modal-body, .ch-modal-foot, .ch-drawer-head, .ch-drawer-body, .ch-drawer-foot { padding-inline: 16px; }
  .ch-modal-foot { flex-wrap: wrap; }
  .ch-modal-foot .sm-text { order: 3; width: 100%; margin: 0; text-align: center; }
  .ch-modal-foot .ch-btn-lg { flex: 1; }
  .ch-tablebar { flex-direction: column; align-items: stretch; }
  .ch-search { max-width: none; }
  .ch-seg-end { margin-inline-start: 0; }
  .ch-th { display: none; }
  .ch-tr { grid-template-columns: minmax(0, 1fr) auto; padding: 12px 14px; }
  .ch-td-health { display: none; }
  .ch-kpis { grid-template-columns: minmax(0, 1fr) minmax(0, 1fr); }
  .ch-meta-head { flex-wrap: wrap; }
  .ch-meta-head .ch-btn { width: 100%; }
  .ch-pick-list.is-two { grid-template-columns: minmax(0, 1fr); }
  .ch-flash { right: 16px; left: 16px; bottom: 16px; max-width: none; }
}
/* PDF design: KPIs, Meta group, other platforms, choose accounts */
.ch-section-label { margin: 0; color: var(--ink5); font-size: 12.5px; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; }
.ch-kpis { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 14px; }
.ch-kpi { display: flex; flex-direction: column; gap: 6px; min-width: 0; padding: 16px 18px; border: 1px solid var(--line); border-radius: var(--r-lg); background: #fff; color: inherit; font: inherit; text-align: start; box-shadow: var(--sh-sm); }
button.ch-kpi { cursor: pointer; }
.ch-kpi small { color: var(--ink5); font-size: 13px; }
.ch-kpi strong { font-size: 26px; line-height: 32px; font-weight: 700; }
.ch-kpi strong span { color: var(--ink5); font-size: 15px; font-weight: 500; }
.ch-kpi strong.ch-kpi-sm { display: flex; align-items: center; gap: 8px; font-size: 16px; line-height: 32px; }
.ch-kpi.is-warn { border-color: #FDE68A; background: #FFFBEB; }
.ch-kpi.is-warn small, .ch-kpi.is-warn strong { color: #92400E; }
.ch-dot { width: 8px; height: 8px; border-radius: 50%; background: var(--ok); flex-shrink: 0; }
.ch-dot.is-muted { background: #CBD5E1; }
.ch-status { display: inline-flex; align-items: center; gap: 6px; font-size: 13px; font-weight: 600; white-space: nowrap; }
.ch-status::before { content: ''; width: 7px; height: 7px; border-radius: 50%; background: currentColor; }
.ch-status.is-ok { color: var(--ok); }
.ch-status.is-warn { color: var(--warn); }
.ch-status.is-muted { color: var(--ink5); }
.ch-status.is-muted::before { background: #CBD5E1; }
.ch-chip.is-grey { border-color: transparent; background: #F1F5F9; color: #334155; font-weight: 500; }
.ch-tile.is-metab { background: #0866FF; color: #fff; font-size: 20px; }

.ch-meta { border: 1px solid var(--line); border-radius: var(--r-xl); background: #fff; box-shadow: var(--sh-sm); overflow: hidden; }
.ch-meta-head { display: flex; align-items: center; gap: 14px; padding: 18px 20px; border-bottom: 1px solid var(--line); }
.ch-meta-text { flex: 1; min-width: 0; }
.ch-meta-text h2 { margin: 0; font-size: 17px; font-weight: 700; }
.ch-meta-text p { margin: 2px 0 0; color: var(--ink5); font-size: 13.5px; line-height: 1.5; }
.ch-row { display: grid; grid-template-columns: auto minmax(160px, 1.2fr) minmax(0, 1.4fr) 140px auto; align-items: center; gap: 14px; padding: 14px 20px; }
.ch-row + .ch-row { border-top: 1px solid var(--line); }
.ch-row-main { display: flex; flex-direction: column; min-width: 0; }
.ch-row-main strong { font-size: 15px; font-weight: 650; }
.ch-row-main small { color: var(--ink5); font-size: 13px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.ch-row-chips { display: flex; flex-wrap: wrap; gap: 6px; }
.ch-row-action .ch-btn { min-width: 92px; }

.ch-filterbar .ch-seg-sm .ch-seg-btn { padding: 6px 12px; font-size: 13px; }
.ch-card-title h3 { margin: 0; font-size: 16px; line-height: 22px; font-weight: 650; color: var(--ink); }
.ch-card-detail { margin: 0; color: var(--ink5); font-size: 13.5px; line-height: 1.5; min-height: 20px; }
.ch-card .ch-card-actions { display: flex; }
.ch-more-head p { margin: 4px 0 0; color: var(--ink5); font-size: 13.5px; }

.ch-td-end { text-align: end; justify-self: end; }
.ch-td-access { font-size: 13.5px; color: var(--ink5); }
.ch-td-access.is-is-warn { color: var(--warn); }
.ch-td-access.is-is-bad { color: var(--bad); }
.ch-tr.is-selected { background: var(--p50); box-shadow: inset 3px 0 0 var(--p600); }
[dir="rtl"] .ch-tr.is-selected { box-shadow: inset -3px 0 0 var(--p600); }
.ch-select select { appearance: auto; }

.ch-stepper li.is-done { color: var(--ok); }
.ch-stepper li.is-done .ch-stepper-dot { border-color: var(--ok); background: var(--ok); color: #fff; }
.ch-choose { display: flex; flex-direction: column; gap: 4px; }
.ch-signed { display: flex; align-items: center; gap: 10px; padding: 12px 14px; border-radius: var(--r-md); background: var(--canvas); font-size: 14px; }
.ch-signed .ch-link { margin-inline-start: auto; }
.ch-pick-list { display: flex; flex-direction: column; gap: 10px; }
.ch-pick-list.is-two { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); }
.ch-pick { border: 1.5px solid var(--line); border-radius: var(--r-lg); background: #fff; overflow: hidden; }
.ch-pick.is-on { border-color: var(--p600); background: #F8FAFF; }
.ch-pick.is-already { opacity: .65; background: var(--canvas); }
.ch-pick-row { display: flex; align-items: center; gap: 12px; padding: 12px 14px; margin: 0; cursor: pointer; }
.ch-pick-row.is-child { margin-inline-start: 34px; padding-inline-start: 12px; border-top: 1px solid var(--line); }
.ch-pick-row input { width: 18px; height: 18px; flex-shrink: 0; accent-color: var(--p600); }
.ch-pick-text { flex: 1; min-width: 0; display: flex; flex-direction: column; }
.ch-pick-text strong { font-size: 14.5px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.ch-pick-text small { color: var(--ink5); font-size: 12.5px; }
.ch-note-plain { margin: 2px 0 0; }
.ch-checks { display: flex; flex-direction: column; gap: 12px; margin: 0; padding: 0; list-style: none; }
.ch-checks li { display: flex; gap: 10px; }
.ch-checks li > i { display: grid; place-items: center; flex-shrink: 0; width: 22px; height: 22px; border-radius: 50%; background: var(--ok-bg); color: var(--ok); font-size: 14px; }
.ch-checks li > span { display: flex; flex-direction: column; }
.ch-checks strong { font-size: 14px; }
.ch-checks small { color: var(--ink5); font-size: 12.5px; line-height: 1.45; }
.ch-linked { display: flex; align-items: center; gap: 10px; width: 100%; padding: 10px 12px; border: 1px solid var(--line); border-radius: var(--r-md); background: #fff; color: inherit; font: inherit; text-align: start; cursor: pointer; }
.ch-linked.is-is-warn { border-color: #FDE68A; background: #FFFBEB; }
.ch-linked.is-is-bad { border-color: #FECACA; background: #FEF2F2; }
.ch-linked > i { color: var(--ink5); font-size: 18px; }
</style>
