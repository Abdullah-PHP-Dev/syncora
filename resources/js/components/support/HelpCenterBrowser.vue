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
        <input type="text" v-model="q" @input="debouncedSearch" @keyup.enter="askAi" class="form-control border-0" placeholder="Search the Help Center...">
        <button type="button" class="btn btn-light" :disabled="!q.trim() || asking" @click="askAi">
          <span v-if="asking" class="spinner-border spinner-border-sm"></span>
          <template v-else><i class="bx bx-bulb"></i> Ask AI</template>
        </button>
      </div>
    </div>

    <div v-if="aiResult" class="card mb-3 hc-ai-card">
      <div class="card-body">
        <template v-if="aiResult.status === 'suggested'">
          <div class="d-flex align-items-start gap-2">
            <i class="bx bx-bulb text-primary fs-4"></i>
            <div>
              <div class="fw-semibold mb-1">{{ aiResult.matched_question }}</div>
              <div v-html="plainTextToHtml(aiResult.answer)"></div>
            </div>
          </div>
        </template>
        <template v-else>
          <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
            <span class="text-muted"><i class="bx bx-search-alt me-1"></i> We couldn't find a confident answer to that in the Help Center.</span>
            <a :href="escalateUrl" class="btn btn-sm btn-primary">Open a support ticket</a>
          </div>
        </template>
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
                  <!-- faq.answer is already real, Purifier-sanitized HTML
                       (every save wraps it in <p>/<br> etc.) - rendered
                       directly, not escaped-then-nl2br'd (that combination
                       double-escaped real tags into literally visible
                       "<p>...</p>" text - see plainTextToHtml()'s docblock
                       for why that helper is for a different, genuinely
                       plain-text field instead). -->
                  <div class="accordion-body" v-html="faq.answer"></div>
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
  askAiUrl: { type: String, required: true },
});

const faqs = ref(props.initialFaqs);
const categories = ref(props.initialCategories);
const loading = ref(false);
const q = ref('');
const activeCategory = ref('');
const openId = ref(null);
const asking = ref(false);
const aiResult = ref(null);

// Prefills the ticket form with the exact question that couldn't be
// confidently answered - TicketCreateForm.vue reads these as initial
// values, see its own props.
const escalateUrl = computed(() => {
  const question = q.value.trim();
  const params = new URLSearchParams({
    subject: question.length > 200 ? question.slice(0, 197) + '...' : question,
    body: question,
  });

  return `${props.ticketsCreateUrl}?${params.toString()}`;
});

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

// Only ever safe to use on a field guaranteed to be plain text, never on
// faq.answer (real HTML - see the accordion body's own comment). AiCopilot
// findBestMatch()/findBestSystemMatch()'s suggested_reply (what aiResult.answer
// is) is converted to plain text server-side specifically because it's also
// sent verbatim to a real customer/dropped into a human agent's reply box
// elsewhere in the app - this only re-adds readable line breaks for display
// here, it never needs to interpret real markup.
function plainTextToHtml(text) {
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
  aiResult.value = null;
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

async function askAi() {
  const question = q.value.trim();
  if (!question) return;

  asking.value = true;
  aiResult.value = null;
  try {
    const { data } = await window.axios.post(props.askAiUrl, { question });
    aiResult.value = data;
  } catch (error) {
    aiResult.value = { status: 'no_match' };
  } finally {
    asking.value = false;
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
