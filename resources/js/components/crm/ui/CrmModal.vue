<template>
    <div class="crm-overlay" @mousedown.self="$emit('close')">
        <div class="crm-modal-wrap" @mousedown.self="$emit('close')">
            <div class="crm-modal" role="dialog" aria-modal="true" :aria-label="title" :style="width ? { width } : null">
                <header class="crm-modal-head">
                    <div class="d-flex gap-2 align-items-start">
                        <span v-if="icon" class="crm-kpi-icon" :style="tone === 'danger' ? 'background:#fef2f2;color:#dc2626' : ''"><i :class="['bx', icon]"></i></span>
                        <div>
                            <h3>{{ title }}</h3>
                            <div v-if="subtitle" class="crm-small crm-muted">{{ subtitle }}</div>
                        </div>
                    </div>
                    <button type="button" class="crm-btn crm-btn-ghost crm-icon-btn" :aria-label="closeLabel" @click="$emit('close')"><i class="bx bx-x"></i></button>
                </header>
                <div class="crm-modal-body"><slot /></div>
                <footer class="crm-modal-foot"><slot name="footer" /></footer>
            </div>
        </div>
    </div>
</template>

<script>
export default {
    name: 'CrmModal',
    props: { title: String, subtitle: String, icon: String, tone: String, width: String, closeLabel: { type: String, default: 'Close' } },
    mounted() { document.addEventListener('keydown', this.onKey); },
    beforeDestroy() { document.removeEventListener('keydown', this.onKey); },
    methods: { onKey(e) { if (e.key === 'Escape') this.$emit('close'); } },
};
</script>
