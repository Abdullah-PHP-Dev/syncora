<template>
  <div class="bs" :class="{ 'is-empty': !summary }">
    <template v-if="summary">
      <div class="bs-row">
        <span>{{ t('budget_summary.budget') }}<small v-if="summary.days"> · {{ tc('budget_summary.times_days', summary.days, { amount: money(summary.amount) }) }}</small><small v-else-if="summary.perDay"> · {{ t('budget_summary.per_day') }}</small><small v-else> · {{ t('budget_summary.lifetime') }}</small></span>
        <strong>{{ money(summary.budget) }}</strong>
      </div>
      <div class="bs-row is-muted">
        <span>{{ t('budget_summary.vat', { rate: summary.vatRate }) }}</span>
        <strong>{{ money(summary.vat) }}</strong>
      </div>
      <div class="bs-row is-total">
        <span>{{ t('budget_summary.total') }}<small v-if="summary.perDay"> {{ t('budget_summary.per_day') }}</small></span>
        <strong>{{ money(summary.total) }}</strong>
      </div>
      <p v-if="summary.perDay" class="bs-note"><i class="bx bx-info-circle"></i> {{ t('budget_summary.no_end') }}</p>
    </template>
    <p v-else class="bs-note"><i class="bx bx-calculator"></i> {{ t('budget_summary.empty') }}</p>
  </div>
</template>

<script setup>
// Budget + VAT + total card for the campaign builder (the same breakdown
// the old Facebook form showed). summary comes from the builder's
// budgetSummary computed; money formats in the ad account currency.
import { useBuilderI18n } from './i18n';

const { t, tc } = useBuilderI18n();

defineProps({
  summary: { type: Object, default: null },
  money: { type: Function, required: true },
});
</script>

<style scoped>
.bs { margin: 6px 0 14px; margin-inline-start: auto; max-width: 420px; padding: 14px 16px; border: 1px solid #E7E9F0; border-radius: 14px; background: linear-gradient(180deg, #FBFAFF 0%, #fff 100%); }
.bs-row { display: flex; justify-content: space-between; align-items: baseline; gap: 12px; padding: 4px 0; font-size: .86rem; color: #374151; }
.bs-row small { color: #8A92A3; font-size: .74rem; }
.bs-row.is-muted { color: #8A92A3; }
.bs-row.is-total { margin-top: 6px; padding-top: 10px; border-top: 1px dashed #DCD6FF; font-size: 1rem; font-weight: 700; color: #161B2B; }
.bs-row.is-total strong { color: #4F2FD6; font-size: 1.1rem; }
.bs-note { margin: 6px 0 0; color: #8A92A3; font-size: .76rem; display: flex; gap: 6px; align-items: flex-start; }
.bs.is-empty .bs-note { margin: 0; }
@media (max-width: 767.98px) { .bs { max-width: none; } }
</style>
