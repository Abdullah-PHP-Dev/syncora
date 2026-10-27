<template>
  <div class="gs-table-wrap">
    <table class="gs-table">
      <caption v-if="caption" class="gs-sr">{{ caption }}</caption>
      <thead>
        <tr>
          <th v-for="col in columns" :key="col.key" scope="col" :class="['is-' + (col.align || (col.numeric ? 'end' : 'start')), col.class]"
              :aria-sort="sortKey === col.key ? (sortDir === 'asc' ? 'ascending' : 'descending') : null">
            <button v-if="col.sortable !== false && rows.length > 1" type="button" class="gs-th-btn" @click="sort(col)">
              {{ col.label }}<i class="bx" :class="sortKey === col.key ? (sortDir === 'asc' ? 'bx-chevron-up' : 'bx-chevron-down') : 'bx-sort-alt-2'" aria-hidden="true"></i>
            </button>
            <span v-else>{{ col.label }}</span>
          </th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="(row, i) in visible" :key="rowKey ? row[rowKey] : i">
          <td v-for="col in columns" :key="col.key" :class="['is-' + (col.align || (col.numeric ? 'end' : 'start')), col.class]">
            <slot :name="'cell-' + col.key" :row="row" :value="row[col.key]" :index="i">{{ col.format ? col.format(row[col.key], row) : row[col.key] }}</slot>
          </td>
        </tr>
      </tbody>
    </table>
    <button v-if="limit && sorted.length > limit" type="button" class="gs-link gs-table-more" @click="expanded = !expanded">
      {{ expanded ? t('common.view_all') + ' ▲' : t('properties.show_more', { n: sorted.length - limit }) }}
    </button>
  </div>
</template>

<script>
import { t } from '../store';

/* Sortable table: header buttons toggle the order (aria-sort reflects it). */
export default {
  name: 'GsTable',
  props: { columns: { type: Array, required: true }, rows: { type: Array, default: () => [] }, initialSort: { type: String, default: '' }, limit: { type: Number, default: 0 }, rowKey: { type: String, default: '' }, caption: { type: String, default: '' } },
  data() { return { sortKey: this.initialSort, sortDir: 'desc', expanded: false }; },
  computed: {
    sorted() {
      if (!this.sortKey) return this.rows;
      const dir = this.sortDir === 'asc' ? 1 : -1;
      return [...this.rows].sort((a, b) => {
        const x = a[this.sortKey]; const y = b[this.sortKey];
        if (x === y) return 0;
        if (x === null || x === undefined) return 1;
        if (y === null || y === undefined) return -1;
        return (typeof x === 'number' && typeof y === 'number' ? x - y : String(x).localeCompare(String(y))) * dir;
      });
    },
    visible() { return this.limit && !this.expanded ? this.sorted.slice(0, this.limit) : this.sorted; },
  },
  methods: {
    t,
    sort(col) {
      if (this.sortKey === col.key) this.sortDir = this.sortDir === 'asc' ? 'desc' : 'asc';
      else { this.sortKey = col.key; this.sortDir = col.numeric ? 'desc' : 'asc'; }
    },
  },
};
</script>
