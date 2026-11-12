<template>
    <section class="crm-card">
        <h3 class="crm-card-title">{{ crm.t('settings.section_lists') }}</h3>
        <p class="crm-card-sub">{{ crm.t('settings.lists_hint') }}</p>
        <div v-for="list in lists" :key="list.key" class="mb-4">
            <div class="d-flex justify-content-between align-items-center mb-1">
                <strong class="crm-small">{{ crm.t('settings.list_' + list.key) }}</strong>
                <button type="button" class="crm-btn crm-btn-ghost crm-btn-sm" :disabled="busy" @click="reset(list.key)">{{ crm.t('settings.reset_default') }}</button>
            </div>
            <p class="crm-hint mb-2">{{ crm.t('settings.list_' + list.key + '_hint') }}</p>
            <div class="d-flex flex-wrap gap-2 mb-2">
                <span v-for="(item, i) in values[list.key]" :key="item" class="crm-chip plain" style="gap:6px">
                    <span dir="auto">{{ crm.opt(list.prefix, item) }}</span>
                    <button v-if="i > 0" type="button" class="crm-btn crm-btn-ghost crm-icon-btn" style="height:18px;width:18px;padding:0" :aria-label="crm.t('settings.move_up')" @click="move(list.key, i)"><i class="bx bx-chevron-left bx-flip-horizontal-rtl"></i></button>
                    <button type="button" class="crm-btn crm-btn-ghost crm-icon-btn" style="height:18px;width:18px;padding:0" :aria-label="crm.t('common.delete')" @click="remove(list.key, i)"><i class="bx bx-x"></i></button>
                </span>
            </div>
            <form class="d-flex gap-2" @submit.prevent="add(list)">
                <input v-model.trim="draft[list.key]" class="crm-input" style="max-width:260px" :maxlength="list.max" :placeholder="crm.t('settings.add_item')" :aria-label="crm.t('settings.list_' + list.key)" dir="auto">
                <button type="submit" class="crm-btn crm-btn-sm" :disabled="!draft[list.key]"><i class="bx bx-plus"></i> {{ crm.t('common.add') }}</button>
            </form>
        </div>
        <div class="d-flex justify-content-end">
            <button type="button" class="crm-btn crm-btn-primary" :disabled="busy || !dirty" @click="save"><i class="bx bx-check"></i> {{ crm.t('common.save') }}</button>
        </div>
    </section>
</template>

<script>
const LISTS = [
    { key: 'sources', prefix: 'sources', max: 40 }, { key: 'extra_statuses', prefix: 'lead_status', max: 20 },
    { key: 'disqualify_reasons', prefix: 'reasons', max: 40 }, { key: 'lost_reasons', prefix: 'lost_reasons', max: 80 }, { key: 'win_reasons', prefix: 'win_reasons', max: 80 },
];

/** S-03: the picklists people choose from. Built-in entries are translated; typed entries are shown as written. */
export default {
    name: 'CrmSettingsLists',
    inject: ['crm'],
    props: { settings: { type: Object, required: true }, defaults: { type: Object, default: () => ({}) } },
    emits: ['saved'],
    data() {
        const values = {};
        LISTS.forEach((l) => { values[l.key] = [...(this.settings[l.key] || [])]; });
        return { lists: LISTS, values, draft: {}, busy: false, dirty: false };
    },
    methods: {
        add(list) {
            const v = this.draft[list.key];
            if (v && !this.values[list.key].includes(v)) { this.values[list.key].push(v); this.dirty = true; }
            this.$set(this.draft, list.key, '');
        },
        remove(key, i) { this.values[key].splice(i, 1); this.dirty = true; },
        move(key, i) { const a = this.values[key]; a.splice(i - 1, 0, a.splice(i, 1)[0]); this.dirty = true; },
        reset(key) { this.values[key] = [...(this.defaults[key] || [])]; this.dirty = true; },
        async save() {
            this.busy = true;
            try {
                await this.crm.api.put('settings', this.values);
                this.crm.notify(this.crm.t('settings.lists_saved'));
                this.dirty = false;
                this.$emit('saved');
            } catch (e) { this.crm.notify(this.crm.error(e), 'error'); }
            this.busy = false;
        },
    },
};
</script>
