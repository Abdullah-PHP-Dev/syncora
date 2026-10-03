<template>

  <div class="hc">

    <!-- Hero + search -->
    <div class="hc-hero">
      <div class="hc-hero-icon"><i class="bx bx-book-open"></i></div>
      <div class="hc-eyebrow">Help Center</div>
      <h3>How can we help you today?</h3>
      <p>Guides for billing, account setup, publishing and troubleshooting - or ask AI in plain words.</p>

      <form class="hc-search" @submit.prevent="askAi" role="search">
        <i class="bx bx-search"></i>
        <input type="search" v-model="q" @input="debouncedSearch" placeholder="Search articles or ask a question…" aria-label="Search the Help Center" autocomplete="off">
        <button type="submit" class="hc-ask" :disabled="!q.trim() || asking">
          <span v-if="asking" class="spinner-border spinner-border-sm"></span>
          <i v-else class="bx bx-bot"></i> Ask AI
        </button>
      </form>
      <div class="hc-hero-hint"><kbd>Enter</kbd> asks AI · typing filters the articles below</div>
    </div>

    <!-- AI answer -->
    <transition name="hc-fade">
      <div v-if="aiResult" class="hc-ai" :class="aiResult.status === 'suggested' ? 'is-found' : 'is-missing'">
        <div class="hc-ai-icon"><i class="bx" :class="aiResult.status === 'suggested' ? 'bx-bot' : 'bx-search-alt'"></i></div>
        <div class="hc-ai-main">
          <template v-if="aiResult.status === 'suggested'">
            <div class="hc-ai-label">AI answer · from the Help Center</div>
            <div class="hc-ai-question">{{ aiResult.matched_question }}</div>
            <div class="hc-ai-answer" v-html="plainTextToHtml(aiResult.answer)"></div>
            <div class="hc-ai-foot">
              <span>Didn't solve it?</span>
              <a :href="escalateUrl" class="hc-link">Open a support ticket <i class="bx bx-right-arrow-alt"></i></a>
            </div>
          </template>
          <template v-else>
            <div class="hc-ai-question">We couldn't find a confident answer to that.</div>
            <div class="hc-ai-answer">Our support team can help - your question will be added to the ticket for you.</div>
            <div class="hc-ai-foot">
              <a :href="escalateUrl" class="hc-btn hc-btn-brand"><i class="bx bx-support"></i> Open a support ticket</a>
            </div>
          </template>
        </div>
        <button type="button" class="hc-ai-close" @click="aiResult = null" aria-label="Dismiss"><i class="bx bx-x"></i></button>
      </div>
    </transition>

    <!-- Category cards -->
    <div class="hc-cats" v-if="categories.length">
      <button v-for="cat in categories" :key="cat.id" type="button" class="hc-cat" :class="{ 'is-active': activeCategory === cat.id }" @click="selectCategory(activeCategory === cat.id ? '' : cat.id)">
        <span class="hc-cat-icon"><i class="bx" :class="categoryIcon(cat.name)"></i></span>
        <span class="hc-cat-text">
          <span class="hc-cat-name">{{ cat.name }}</span>
          <span class="hc-cat-count">{{ countFor(cat.id) }} {{ countFor(cat.id) === 1 ? 'article' : 'articles' }}</span>
        </span>
      </button>
    </div>

    <div class="hc-grid">
      <!-- Articles -->
      <div class="hc-list" :class="{ 'is-loading': loading }">
        <div class="hc-list-head">
          <h5>{{ listTitle }}</h5>
          <span>
            {{ faqs.length }} {{ faqs.length === 1 ? 'article' : 'articles' }}
            <button v-if="activeCategory" type="button" class="hc-clear" @click="selectCategory('')"><i class="bx bx-x"></i> Show all</button>
          </span>
        </div>

        <template v-if="Object.keys(grouped).length">
          <section v-for="(group, categoryName) in grouped" :key="categoryName" class="hc-group">
            <h6 v-if="!activeCategory && Object.keys(grouped).length > 1" class="hc-group-title">{{ categoryName }}</h6>
            <div class="hc-faq" v-for="faq in group" :key="faq.id" :class="{ 'is-open': openId === faq.id }">
              <button type="button" class="hc-faq-q" @click="toggle(faq.id)" :aria-expanded="openId === faq.id">
                <span class="hc-faq-dot"><i class="bx bx-file-blank"></i></span>
                <span class="hc-faq-text">{{ faq.question }}</span>
                <i class="bx bx-chevron-down hc-faq-chevron"></i>
              </button>
              <!-- faq.answer is already real, Purifier-sanitized HTML (every
                   save wraps it in <p>/<br> etc.) - rendered directly, not
                   escaped-then-nl2br'd (that double-escaped real tags into
                   visible "<p>...</p>" text - see plainTextToHtml()). -->
              <div v-show="openId === faq.id" class="hc-faq-a" v-html="faq.answer"></div>
            </div>
          </section>
        </template>

        <div v-else-if="!loading" class="hc-empty">
          <div class="hc-empty-icon"><i class="bx bx-search-alt"></i></div>
          <h6>No matching articles</h6>
          <p>Try other words, ask AI, or let our team help you directly.</p>
          <div class="hc-empty-actions">
            <button v-if="q.trim()" type="button" class="hc-btn hc-btn-outline" @click="askAi"><i class="bx bx-bot"></i> Ask AI</button>
            <a :href="q.trim() ? escalateUrl : ticketsCreateUrl" class="hc-btn hc-btn-brand"><i class="bx bx-support"></i> Open a ticket</a>
          </div>
        </div>

        <div v-if="loading" class="hc-loading"><span class="spinner-border spinner-border-sm"></span></div>
      </div>
    </div>

    <!-- Contact -->
    <div class="hc-contact">
      <div class="hc-contact-icon"><i class="bx bx-support"></i></div>
      <div class="hc-contact-text">
        <h6>Still need help?</h6>
        <p>Our support team is happy to help. Open a ticket and track the conversation from Support Tickets.</p>
      </div>
      <a :href="ticketsCreateUrl" class="hc-btn hc-btn-brand"><i class="bx bx-plus"></i> Open a support ticket</a>
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

// The first page load is every published article - kept for the per-
// category counts, which shouldn't change while searching.
const allFaqs = props.initialFaqs;
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

const listTitle = computed(() => {
  if (q.value.trim()) return `Results for “${q.value.trim()}”`;
  const cat = categories.value.find(c => c.id === activeCategory.value);
  return cat ? cat.name : 'All articles';
});

function countFor(categoryId) {
  return allFaqs.filter(f => f.faq_category_id === categoryId || f.category?.id === categoryId).length;
}

// Icon by category name keywords - categories are admin-defined text.
function categoryIcon(name) {
  const n = String(name || '').toLowerCase();
  if (/bill|pay|plan|subscri|invoice|price/.test(n)) return 'bx-credit-card';
  if (/account|profile|login|setup|security|team/.test(n)) return 'bx-user-circle';
  if (/post|publish|schedul|content|calendar/.test(n)) return 'bx-calendar-edit';
  if (/integrat|api|connect|channel|webhook/.test(n)) return 'bx-plug';
  if (/ads?\b|campaign|marketing/.test(n)) return 'bx-target-lock';
  if (/message|inbox|chat|messag/.test(n)) return 'bx-message-square-dots';
  if (/ai|copilot/.test(n)) return 'bx-bot';
  if (/trouble|bug|error|issue|fix/.test(n)) return 'bx-wrench';
  return 'bx-book-content';
}

function toggle(id) {
  openId.value = openId.value === id ? null : id;
}

// Only ever safe to use on a field guaranteed to be plain text, never on
// faq.answer (real HTML - see the article body's own comment). AiCopilot
// findBestSystemMatch()'s suggested_reply (what aiResult.answer is) is
// converted to plain text server-side because it's also sent verbatim to
// real customers elsewhere - this only re-adds readable line breaks.
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
  openId.value = null;
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
  if (!question || asking.value) return;

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
.hc { --ln: #e7e9f0; --ln-soft: #f1f3f7; --ink: #161b2b; --ink2: #545d70; --muted: #8a92a3; --brand: #6d4aff; --brand-2: #8f6bff; --brand-soft: #f2eeff; color: var(--ink2); }

/* Hero */
.hc-hero { position: relative; overflow: hidden; text-align: center; padding: 40px 24px 30px; margin-bottom: 20px; background: #fff; border: 1px solid var(--ln); border-radius: 20px; box-shadow: 0 1px 2px rgba(16, 24, 40, .04); }
.hc-hero::before { content: ''; position: absolute; inset: -40% -10% auto; height: 120%; background: radial-gradient(closest-side, rgba(109, 74, 255, .13), transparent 70%), radial-gradient(closest-side at 80% 40%, rgba(143, 107, 255, .1), transparent 70%); pointer-events: none; }
.hc-hero > * { position: relative; }
.hc-hero-icon { width: 54px; height: 54px; margin: 0 auto 12px; border-radius: 16px; display: grid; place-items: center; font-size: 26px; color: #fff; background: linear-gradient(135deg, var(--brand), var(--brand-2)); box-shadow: 0 10px 22px rgba(109, 74, 255, .3); }
.hc-eyebrow { font-size: .72rem; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; color: var(--brand); margin-bottom: 4px; }
.hc-hero h3 { color: var(--ink); font-weight: 700; font-size: 1.7rem; letter-spacing: -.02em; margin: 0 0 6px; }
.hc-hero p { max-width: 560px; margin: 0 auto 22px; font-size: .9rem; }
.hc-search { display: flex; align-items: center; gap: 6px; max-width: 640px; margin: 0 auto; padding: 6px 6px 6px 16px; background: #fff; border: 1px solid var(--ln); border-radius: 14px; box-shadow: 0 10px 28px rgba(16, 24, 40, .08); transition: border-color .15s, box-shadow .15s; }
[dir="rtl"] .hc-search { padding: 6px 16px 6px 6px; }
.hc-search:focus-within { border-color: var(--brand); box-shadow: 0 0 0 4px rgba(109, 74, 255, .12), 0 10px 28px rgba(16, 24, 40, .08); }
.hc-search > i { font-size: 1.25rem; color: var(--muted); }
.hc-search input { flex: 1; min-width: 0; height: 44px; border: none; outline: none; background: transparent; font-size: .95rem; color: var(--ink); }
.hc-ask { height: 44px; padding: 0 18px; border: none; border-radius: 10px; display: inline-flex; align-items: center; gap: 6px; font-size: .86rem; font-weight: 600; color: #fff; background: linear-gradient(135deg, var(--brand), var(--brand-2)); box-shadow: 0 4px 12px rgba(109, 74, 255, .25); transition: opacity .15s, transform .15s; white-space: nowrap; }
.hc-ask:hover:not(:disabled) { transform: translateY(-1px); }
.hc-ask:disabled { opacity: .5; box-shadow: none; }
.hc-hero-hint { margin-top: 12px; font-size: .74rem; color: var(--muted); }
.hc-hero-hint kbd { font-size: .66rem; padding: 1px 5px; border-radius: 4px; background: var(--ln-soft); color: var(--ink2); border: 1px solid var(--ln); box-shadow: none; }

/* AI answer */
.hc-ai { position: relative; display: flex; gap: 14px; padding: 18px 20px; margin-bottom: 20px; border-radius: 16px; border: 1px solid #e4dcff; background: linear-gradient(180deg, #f8f6ff 0%, #fff 100%); box-shadow: 0 8px 24px rgba(109, 74, 255, .08); }
.hc-ai.is-missing { border-color: var(--ln); background: #fff; box-shadow: 0 1px 2px rgba(16, 24, 40, .04); }
.hc-ai-icon { width: 40px; height: 40px; border-radius: 12px; display: grid; place-items: center; font-size: 20px; flex-shrink: 0; color: #fff; background: linear-gradient(135deg, var(--brand), var(--brand-2)); }
.hc-ai.is-missing .hc-ai-icon { background: var(--ln-soft); color: var(--ink2); }
.hc-ai-main { flex: 1; min-width: 0; padding-inline-end: 24px; }
.hc-ai-label { font-size: .7rem; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; color: var(--brand); margin-bottom: 4px; }
.hc-ai-question { color: var(--ink); font-weight: 700; font-size: .98rem; margin-bottom: 6px; }
.hc-ai-answer { font-size: .88rem; line-height: 1.65; color: var(--ink2); }
.hc-ai-foot { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; margin-top: 12px; font-size: .8rem; color: var(--muted); }
.hc-ai-close { position: absolute; top: 12px; inset-inline-end: 12px; width: 28px; height: 28px; border: none; border-radius: 8px; background: transparent; color: var(--muted); display: grid; place-items: center; font-size: 1.1rem; }
.hc-ai-close:hover { background: var(--ln-soft); color: var(--ink); }
.hc-link { display: inline-flex; align-items: center; gap: 2px; color: var(--brand); font-weight: 600; text-decoration: none; }
.hc-link:hover { text-decoration: underline; }
[dir="rtl"] .hc-link i { transform: scaleX(-1); }
.hc-fade-enter-active, .hc-fade-leave-active { transition: opacity .2s, transform .2s; }
.hc-fade-enter, .hc-fade-leave-to { opacity: 0; transform: translateY(-4px); }

/* Category cards */
.hc-cats { display: grid; grid-template-columns: repeat(auto-fill, minmax(210px, 1fr)); gap: 12px; margin-bottom: 20px; }
.hc-cat { display: flex; align-items: center; gap: 12px; padding: 14px 16px; background: #fff; border: 1px solid var(--ln); border-radius: 14px; text-align: start; transition: border-color .15s, box-shadow .15s, transform .15s; box-shadow: 0 1px 2px rgba(16, 24, 40, .04); }
.hc-cat:hover { border-color: #d5d9e2; box-shadow: 0 8px 20px rgba(16, 24, 40, .06); transform: translateY(-1px); }
.hc-cat.is-active { border-color: var(--brand); box-shadow: 0 0 0 3px rgba(109, 74, 255, .12); }
.hc-cat-icon { width: 42px; height: 42px; border-radius: 12px; display: grid; place-items: center; font-size: 21px; background: var(--brand-soft); color: var(--brand); flex-shrink: 0; transition: background .15s, color .15s; }
.hc-cat.is-active .hc-cat-icon { background: var(--brand); color: #fff; }
.hc-cat-text { min-width: 0; }
.hc-cat-name { display: block; color: var(--ink); font-weight: 700; font-size: .86rem; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.hc-cat-count { display: block; font-size: .74rem; color: var(--muted); }

/* Layout */
.hc-grid { margin-bottom: 20px; }
.hc-clear { margin-inline-start: 8px; border: none; background: var(--ln-soft); color: var(--ink2); font-size: .74rem; font-weight: 600; border-radius: 999px; padding: 2px 10px 2px 6px; display: inline-flex; align-items: center; gap: 2px; }
.hc-clear:hover { background: var(--brand-soft); color: #4f2fd6; }

/* Articles */
.hc-list { position: relative; min-height: 200px; transition: opacity .15s; }
.hc-list.is-loading { opacity: .55; }
.hc-loading { position: absolute; inset: 0; display: grid; place-items: center; color: var(--brand); }
.hc-list-head { display: flex; justify-content: space-between; align-items: baseline; gap: 12px; margin-bottom: 12px; }
.hc-list-head h5 { color: var(--ink); font-weight: 700; font-size: 1.05rem; margin: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.hc-list-head span { font-size: .8rem; color: var(--muted); white-space: nowrap; }
.hc-group + .hc-group { margin-top: 20px; }
.hc-group-title { font-size: .72rem; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; color: var(--muted); margin: 0 0 8px 4px; }
.hc-faq { background: #fff; border: 1px solid var(--ln); border-radius: 14px; margin-bottom: 8px; overflow: hidden; transition: border-color .15s, box-shadow .15s; }
.hc-faq:hover { border-color: #d5d9e2; }
.hc-faq.is-open { border-color: #d9d0ff; box-shadow: 0 8px 22px rgba(109, 74, 255, .08); }
.hc-faq-q { width: 100%; display: flex; align-items: center; gap: 12px; padding: 14px 16px; border: none; background: transparent; text-align: start; }
.hc-faq-dot { width: 32px; height: 32px; border-radius: 9px; display: grid; place-items: center; flex-shrink: 0; font-size: 1rem; background: var(--ln-soft); color: var(--ink2); transition: background .15s, color .15s; }
.hc-faq.is-open .hc-faq-dot { background: var(--brand-soft); color: var(--brand); }
.hc-faq-text { flex: 1; min-width: 0; color: var(--ink); font-weight: 600; font-size: .9rem; line-height: 1.4; }
.hc-faq-chevron { font-size: 1.3rem; color: var(--muted); transition: transform .2s, color .2s; flex-shrink: 0; }
.hc-faq.is-open .hc-faq-chevron { transform: rotate(180deg); color: var(--brand); }
.hc-faq-a { padding: 0 16px 16px 60px; font-size: .88rem; line-height: 1.7; color: var(--ink2); }
[dir="rtl"] .hc-faq-a { padding: 0 60px 16px 16px; }
.hc-faq-a :deep(p:last-child) { margin-bottom: 0; }
.hc-faq-a :deep(a) { color: var(--brand); }

.hc-empty { text-align: center; padding: 44px 24px; background: #fff; border: 1px dashed var(--ln); border-radius: 16px; }
.hc-empty-icon { width: 60px; height: 60px; margin: 0 auto 12px; border-radius: 16px; display: grid; place-items: center; font-size: 28px; color: var(--brand); background: var(--brand-soft); }
.hc-empty h6 { color: var(--ink); font-weight: 700; margin-bottom: 4px; }
.hc-empty p { font-size: .85rem; color: var(--muted); margin-bottom: 16px; }
.hc-empty-actions { display: flex; gap: 8px; justify-content: center; flex-wrap: wrap; }

/* Buttons */
.hc-btn { display: inline-flex; align-items: center; justify-content: center; gap: 6px; height: 40px; padding: 0 16px; border-radius: 10px; font-size: .84rem; font-weight: 600; border: 1px solid transparent; text-decoration: none; white-space: nowrap; cursor: pointer; transition: box-shadow .15s, transform .15s, background .15s, border-color .15s; }
.hc-btn i { font-size: 1.05rem; }
.hc-btn-brand { background: linear-gradient(135deg, var(--brand), var(--brand-2)); color: #fff; box-shadow: 0 4px 12px rgba(109, 74, 255, .25); }
.hc-btn-brand:hover { color: #fff; transform: translateY(-1px); box-shadow: 0 6px 16px rgba(109, 74, 255, .35); }
.hc-btn-outline { background: #fff; border-color: var(--ln); color: var(--ink); }
.hc-btn-outline:hover { background: #fafbfd; border-color: #cfd4de; color: var(--ink); }

/* Contact */
.hc-contact { display: flex; align-items: center; gap: 16px; padding: 20px 22px; background: #fff; border: 1px solid var(--ln); border-radius: 16px; box-shadow: 0 1px 2px rgba(16, 24, 40, .04); }
.hc-contact-icon { width: 46px; height: 46px; border-radius: 13px; display: grid; place-items: center; font-size: 22px; color: var(--brand); background: var(--brand-soft); flex-shrink: 0; }
.hc-contact-text { flex: 1; min-width: 0; }
.hc-contact h6 { color: var(--ink); font-weight: 700; margin: 0 0 2px; }
.hc-contact p { margin: 0; font-size: .84rem; }

@media (max-width: 575.98px) {
  .hc-hero { padding: 28px 16px 22px; }
  .hc-hero h3 { font-size: 1.35rem; }
  .hc-ask { padding: 0 12px; }
  .hc-contact { flex-direction: column; text-align: center; }
  .hc-faq-a { padding: 0 16px 16px; }
}
</style>
