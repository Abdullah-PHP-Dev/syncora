<template>
    <div class="crm-overlay" @mousedown.self="$emit('close')" @keydown.esc="$emit('close')">
        <aside class="crm-drawer" role="dialog" aria-modal="true" :aria-label="title">
            <header class="crm-drawer-head">
                <div>
                    <h3>{{ title }}</h3>
                    <div v-if="subtitle" class="crm-small crm-muted">{{ subtitle }}</div>
                </div>
                <button type="button" class="crm-btn crm-btn-ghost crm-icon-btn" :aria-label="closeLabel" @click="$emit('close')"><i class="bx bx-x"></i></button>
            </header>
            <div class="crm-drawer-body"><slot /></div>
            <footer class="crm-drawer-foot"><slot name="footer" /></footer>
        </aside>
    </div>
</template>

<script>
export default {
    name: 'CrmDrawer',
    props: { title: String, subtitle: String, closeLabel: { type: String, default: 'Close' } },
    mounted() { document.addEventListener('keydown', this.onKey); },
    beforeDestroy() { document.removeEventListener('keydown', this.onKey); },
    methods: { onKey(e) { if (e.key === 'Escape') this.$emit('close'); } },
};
</script>
