<template>
    <header class="crm-head">
        <div>
            <div class="crm-crumbs">
                <a :href="crm.pageUrl('overview')">{{ crm.t('nav.crm') }}</a>
                <template v-for="(c, i) in crumbs"> › <a v-if="c.url" :key="'c' + i" :href="c.url">{{ c.label }}</a><span v-else :key="'s' + i">{{ c.label }}</span></template>
            </div>
            <h1 class="crm-title">{{ title }}</h1>
        </div>
        <div class="crm-head-actions">
            <div class="crm-search-global" @focusout="onBlur">
                <input v-model="q" class="crm-input" type="search" :placeholder="crm.t('common.search_crm')" :aria-label="crm.t('common.search_crm')" @input="search" @keydown.esc="q = ''">
                <div v-if="q.length > 1 && results" class="crm-search-results">
                    <template v-for="group in ['contacts', 'companies', 'deals']">
                        <div v-if="results[group] && results[group].length" :key="group">
                            <h6>{{ crm.t('nav.' + group) }}</h6>
                            <a v-for="r in results[group]" :key="group + r.id" :href="r.url || crm.pageUrl('deals')">
                                <span>{{ r.name }}</span><span class="crm-muted crm-small crm-ltr">{{ r.sub }}</span>
                            </a>
                        </div>
                    </template>
                    <div v-if="!hasResults" class="crm-muted crm-small" style="padding:10px">{{ crm.t('inbox.no_suggestions') }}</div>
                </div>
            </div>
            <slot />
        </div>
    </header>
</template>

<script>
export default {
    name: 'CrmHeader',
    inject: ['crm'],
    props: { title: String, crumbs: { type: Array, default: () => [] } },
    data: () => ({ q: '', results: null, timer: null }),
    computed: { hasResults() { return this.results && ['contacts', 'companies', 'deals'].some((g) => this.results[g]?.length); } },
    methods: {
        search() {
            clearTimeout(this.timer);
            if (this.q.trim().length < 2) { this.results = null; return; }
            this.timer = setTimeout(async () => { this.results = await this.crm.api.get('search', { q: this.q }).catch(() => null); }, 220);
        },
        onBlur(e) { if (!this.$el.contains(e.relatedTarget)) setTimeout(() => { this.results = this.q.length > 1 ? this.results : null; }, 150); },
    },
};
</script>
