<template>

  <div>

    <div class="hc-hero">
      <h4><i class="bx bx-help-circle"></i> Help Center</h4>
      <p>
        Search Socialeaz's own platform guides - billing, account setup, cross-platform posting, and troubleshooting.
        Can't find an answer? <a :href="ticketsCreateUrl" class="text-white text-decoration-underline">Open a support ticket</a>.
      </p>
      <div class="hc-search input-group">
        <span class="input-group-text bg-white border-0"><i class="bx bx-search"></i></span>
        <input type="text" v-model="q" @input="debouncedSearch" class="form-control border-0" placeholder="Search the Help Center...">
      </div>
    </div>

    <div class="row">
      <div class="col-md-3">
        <div class="card">
          <div class="list-group list-group-flush">
            <a href="javascript:void(0)" class="list-group-item list-group-item-action" :class="{ active: !activeCategory }" @click="selectCategory('')">All categories</a>
            <a href="javascript:void(0)" v-for="cat in categories" :key="cat.id" class="list-group-item list-group-item-action" :class="{ active: activeCategory === cat.id }" @click="selectCategory(cat.id)">{{ cat.name }}</a>
          </div>
        </div>
      </div>
      <div class="col-md-9">
        <div v-if="loading" class="card"><div class="card-body text-center text-muted py-5"><span class="spinner-border spinner-border-sm me-2"></span> Loading...</div></div>
        <template v-else-if="Object.keys(grouped).length">
          <div v-for="(group, categoryName) in grouped" :key="categoryName">
            <h6 class="hc-category-title">{{ categoryName }}</h6>
            <div class="accordion mb-3">
              <div class="accordion-item" v-for="faq in group" :key="faq.id">
                <h2 class="accordion-header">
                  <button class="accordion-button collapsed" type="button" @click="toggle(faq.id)">
                    {{ faq.question }}
                  </button>
                </h2>
                <div class="accordion-collapse collapse" :class="{ show: openId === faq.id }">
                  <div class="accordion-body" v-html="nl2br(faq.answer)"></div>
                </div>
              </div>
            </div>
          </div>
        </template>
        <div v-else class="card">
          <div class="card-body text-center text-muted py-5">
            <i class="bx bx-search-alt fs-1 d-block mb-2"></i>
            No matching articles yet. <a :href="ticketsCreateUrl">Open a support ticket</a> and we'll help directly.
          </div>
        </div>
      </div>
    </div>

  </div>

</template>

<script setup>
import { ref, computed } from 'vue';

const props = defineProps({
  initialFaqs: { type: Array, default: () => [] },
  initialCategories: { type: Array, default: () => [] },
  fetchUrl: { type: String, required: true },
  ticketsCreateUrl: { type: String, required: true },
});

const faqs = ref(props.initialFaqs);
const categories = ref(props.initialCategories);
const loading = ref(false);
const q = ref('');
const activeCategory = ref('');
const openId = ref(null);

const grouped = computed(() => {
  const out = {};
  for (const faq of faqs.value) {
    const name = faq.category ? faq.category.name : 'General';
    if (!out[name]) out[name] = [];
    out[name].push(faq);
  }
  return out;
});

function toggle(id) {
  openId.value = openId.value === id ? null : id;
}

function nl2br(text) {
  const escaped = (text || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
  return escaped.replace(/\n/g, '<br>');
}

let debounceTimer = null;
function debouncedSearch() {
  clearTimeout(debounceTimer);
  debounceTimer = setTimeout(search, 400);
}

function selectCategory(id) {
  activeCategory.value = id;
  search();
}

async function search() {
  loading.value = true;
  try {
    const { data } = await window.axios.get(props.fetchUrl, {
      params: { q: q.value || undefined, category: activeCategory.value || undefined },
    });
    faqs.value = data.faqs;
    categories.value = data.categories;
  } finally {
    loading.value = false;
  }
}
</script>

<style scoped>
.hc-hero {
  background: linear-gradient(135deg, #0ea5e9 0%, #6366f1 55%, #8b5cf6 100%);
  border-radius: 20px;
  padding: 32px 36px;
  color: #fff;
  margin-bottom: 24px;
}
.hc-hero h4 { color: #fff; font-weight: 700; margin-bottom: 6px; }
.hc-hero p { color: rgba(255,255,255,.85); margin-bottom: 16px; max-width: 620px; }
.hc-search { max-width: 520px; }
.hc-category-title { font-weight: 700; color: #1f2937; margin: 24px 0 10px; }
</style>
