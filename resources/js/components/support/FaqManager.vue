<template>

  <div>

    <div class="faq-hero" :style="{ background: gradient }">
      <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
        <div>
          <h4><i :class="['bx', icon]"></i> {{ heading }}</h4>
          <p>{{ subtitle }}</p>
        </div>
        <div class="d-flex gap-2">
          <button type="button" class="btn btn-light fw-semibold" @click="openCategoryModal">
            <i class="bx bx-folder-plus"></i> New Category
          </button>
          <button type="button" class="btn btn-light fw-semibold" @click="openCreateModal">
            <i class="bx bx-plus"></i> New FAQ
          </button>
        </div>
      </div>
    </div>

    <div v-if="notice" class="alert alert-success d-flex align-items-center gap-2">
      <i class="bx bx-check-circle fs-5"></i> {{ notice }}
    </div>
    <div v-if="errorNotice" class="alert alert-danger d-flex align-items-center gap-2">
      <i class="bx bx-error-circle fs-5"></i> {{ errorNotice }}
    </div>

    <div class="card mb-3">
      <div class="card-body">
        <div class="row g-2 align-items-end">
          <div class="col-md-4">
            <label class="form-label small text-muted mb-1">Search</label>
            <input type="text" v-model="filters.q" @input="debouncedFetch" class="form-control" placeholder="Search question or answer...">
          </div>
          <div class="col-md-3">
            <label class="form-label small text-muted mb-1">Category</label>
            <select v-model="filters.category" @change="fetchFaqs(1)" class="form-select">
              <option value="">All categories</option>
              <option v-for="cat in categories" :key="cat.id" :value="cat.id">{{ cat.name }}</option>
            </select>
          </div>
          <div class="col-md-3">
            <label class="form-label small text-muted mb-1">Status</label>
            <select v-model="filters.status" @change="fetchFaqs(1)" class="form-select">
              <option value="">All statuses</option>
              <option value="draft">Draft</option>
              <option value="published">Published</option>
            </select>
          </div>
          <div class="col-md-2">
            <button type="button" class="btn btn-outline-primary w-100" @click="fetchFaqs(1)"><i class="bx bx-search"></i> Filter</button>
          </div>
        </div>
      </div>
    </div>

    <div class="card">
      <div class="table-responsive">
        <table class="table table-hover mb-0 faq-row">
          <thead>
            <tr>
              <th>Question</th>
              <th>Category</th>
              <th>Language</th>
              <th>Status</th>
              <th class="text-end">Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="loading">
              <td colspan="5" class="text-center text-muted py-4"><span class="spinner-border spinner-border-sm me-2"></span> Loading...</td>
            </tr>
            <tr v-else-if="!faqs.length">
              <td colspan="5" class="text-center text-muted py-4">{{ emptyText }}</td>
            </tr>
            <tr v-for="faq in faqs" :key="faq.id" v-else>
              <td style="max-width:380px;">{{ truncate(faq.question, 80) }}</td>
              <td>{{ faq.category ? faq.category.name : '—' }}</td>
              <td>{{ (faq.language || 'en').toUpperCase() }}</td>
              <td><span class="badge faq-status-badge" :class="faq.status === 'published' ? 'bg-label-success' : 'bg-label-secondary'">{{ faq.status }}</span></td>
              <td class="text-end">
                <button type="button" class="btn btn-sm btn-icon btn-outline-primary" title="Edit" @click="openEditModal(faq)">
                  <i class="bx bx-edit-alt"></i>
                </button>
                <button type="button" class="btn btn-sm btn-icon btn-outline-danger" title="Delete" @click="deleteFaq(faq)">
                  <i class="bx bx-trash"></i>
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <div class="card-footer d-flex justify-content-between align-items-center" v-if="meta.last_page > 1">
        <small class="text-muted">Page {{ meta.current_page }} of {{ meta.last_page }} ({{ meta.total }} total)</small>
        <div class="btn-group btn-group-sm">
          <button type="button" class="btn btn-outline-secondary" :disabled="meta.current_page <= 1" @click="fetchFaqs(meta.current_page - 1)">Prev</button>
          <button type="button" class="btn btn-outline-secondary" :disabled="meta.current_page >= meta.last_page" @click="fetchFaqs(meta.current_page + 1)">Next</button>
        </div>
      </div>
    </div>

    <!-- Create/Edit modal -->
    <div class="modal fade" ref="faqModalEl" tabindex="-1">
      <div class="modal-dialog modal-lg">
        <form @submit.prevent="submitFaq">
          <div class="modal-content">
            <div class="modal-header">
              <h5 class="modal-title">{{ editingId ? 'Edit FAQ' : 'New FAQ' }}</h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
              <div class="mb-3">
                <label class="form-label">Question</label>
                <input type="text" v-model="form.question" class="form-control" required maxlength="500" :placeholder="questionPlaceholder">
              </div>
              <div class="mb-3">
                <label class="form-label">Answer</label>
                <textarea v-model="form.answer" class="form-control" rows="5" required :placeholder="answerPlaceholder"></textarea>
              </div>
              <div class="row g-3">
                <div class="col-md-4">
                  <label class="form-label">Category</label>
                  <select v-model="form.faq_category_id" class="form-select">
                    <option value="">Uncategorized</option>
                    <option v-for="cat in categories" :key="cat.id" :value="cat.id">{{ cat.name }}</option>
                  </select>
                </div>
                <div class="col-md-4">
                  <label class="form-label">Language</label>
                  <select v-model="form.language" class="form-select">
                    <option value="en">English</option>
                    <option value="ar">Arabic</option>
                  </select>
                </div>
                <div class="col-md-4">
                  <label class="form-label">Status</label>
                  <select v-model="form.status" class="form-select">
                    <option value="draft">Draft</option>
                    <option value="published">Published</option>
                  </select>
                </div>
              </div>
              <div class="mt-3">
                <label class="form-label">Tags <small class="text-muted">(comma-separated)</small></label>
                <input type="text" v-model="form.tags" class="form-control" placeholder="billing, refunds, subscription">
              </div>
              <div v-if="formError" class="alert alert-danger mt-3 mb-0 py-2">{{ formError }}</div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">Cancel</button>
              <button type="submit" class="btn btn-primary" :disabled="saving">
                <span v-if="saving" class="spinner-border spinner-border-sm me-1"></span>
                Save FAQ
              </button>
            </div>
          </div>
        </form>
      </div>
    </div>

    <!-- Category modal -->
    <div class="modal fade" ref="categoryModalEl" tabindex="-1">
      <div class="modal-dialog">
        <form @submit.prevent="submitCategory">
          <div class="modal-content">
            <div class="modal-header">
              <h5 class="modal-title">New Category</h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
              <label class="form-label">Category name</label>
              <input type="text" v-model="categoryName" class="form-control" required maxlength="100" placeholder="Billing & Payments">
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">Cancel</button>
              <button type="submit" class="btn btn-primary" :disabled="savingCategory">Create Category</button>
            </div>
          </div>
        </form>
      </div>
    </div>

  </div>

</template>

<script setup>
// Shared by both System FAQ management (admin.faqs.*, admin-role gated)
// and a seller's own Knowledge Base (admin.knowledge-base.*) - same UI,
// different backend URLs and scoping, passed in as props rather than
// duplicating this component. Both controllers speak the same JSON shape
// (see FaqController/KnowledgeBaseController docblocks: ajax() requests
// get JSON, a plain browser GET gets the Blade wrapper this mounts into).
import { ref, ref as vueRef } from 'vue';

const props = defineProps({
  initialFaqs: { type: Object, required: true },
  initialCategories: { type: Array, default: () => [] },
  fetchUrl: { type: String, required: true },
  storeUrl: { type: String, required: true },
  // A route()-generated URL with the literal token FAQ_ID standing in for
  // the real id (eg. ".../admin/faqs/FAQ_ID") - built server-side via
  // route(), not string-concatenated here, so it always carries the
  // LaravelLocalization locale prefix Laravel's route() adds automatically
  // and a hand-built "/admin/faqs" + id would silently drop.
  updateUrlTemplate: { type: String, required: true },
  categoryStoreUrl: { type: String, required: true },
  heading: { type: String, default: 'FAQ Management' },
  subtitle: { type: String, default: '' },
  icon: { type: String, default: 'bx-help-circle' },
  gradient: { type: String, default: 'linear-gradient(135deg, #0ea5e9 0%, #6366f1 55%, #8b5cf6 100%)' },
  emptyText: { type: String, default: 'No FAQs yet - create the first one above.' },
  questionPlaceholder: { type: String, default: '' },
  answerPlaceholder: { type: String, default: '' },
});

const faqs = ref(props.initialFaqs.data || []);
const meta = ref({
  current_page: props.initialFaqs.current_page || 1,
  last_page: props.initialFaqs.last_page || 1,
  total: props.initialFaqs.total || 0,
});
const categories = ref(props.initialCategories);
const loading = ref(false);
const notice = ref('');
const errorNotice = ref('');

const filters = ref({ q: '', category: '', status: '' });
let debounceTimer = null;
function debouncedFetch() {
  clearTimeout(debounceTimer);
  debounceTimer = setTimeout(() => fetchFaqs(1), 400);
}

async function fetchFaqs(page = 1) {
  loading.value = true;
  try {
    const { data } = await window.axios.get(props.fetchUrl, {
      params: { q: filters.value.q || undefined, category: filters.value.category || undefined, status: filters.value.status || undefined, page },
    });
    faqs.value = data.faqs.data;
    meta.value = { current_page: data.faqs.current_page, last_page: data.faqs.last_page, total: data.faqs.total };
    categories.value = data.categories;
  } catch (error) {
    errorNotice.value = 'Failed to load FAQs.';
  } finally {
    loading.value = false;
  }
}

function truncate(text, len) {
  return text && text.length > len ? text.slice(0, len) + '…' : text;
}

// --- FAQ create/edit modal ---
const faqModalEl = vueRef(null);
let faqModalInstance = null;
const editingId = ref(null);
const saving = ref(false);
const formError = ref('');
const form = ref({ question: '', answer: '', faq_category_id: '', language: 'en', status: 'draft', tags: '' });

function ensureFaqModal() {
  if (!faqModalInstance) {
    faqModalInstance = new window.bootstrap.Modal(faqModalEl.value);
  }
  return faqModalInstance;
}

function openCreateModal() {
  editingId.value = null;
  formError.value = '';
  form.value = { question: '', answer: '', faq_category_id: '', language: 'en', status: 'draft', tags: '' };
  ensureFaqModal().show();
}

function openEditModal(faq) {
  editingId.value = faq.id;
  formError.value = '';
  form.value = {
    question: faq.question,
    answer: faq.answer,
    faq_category_id: faq.faq_category_id || '',
    language: faq.language || 'en',
    status: faq.status,
    tags: (faq.tags || []).join(', '),
  };
  ensureFaqModal().show();
}

async function submitFaq() {
  saving.value = true;
  formError.value = '';

  try {
    const url = editingId.value ? props.updateUrlTemplate.replace('FAQ_ID', editingId.value) : props.storeUrl;
    const method = editingId.value ? 'put' : 'post';
    const { data } = await window.axios[method](url, form.value);

    notice.value = data.message;
    errorNotice.value = '';
    ensureFaqModal().hide();
    await fetchFaqs(meta.value.current_page);
  } catch (error) {
    formError.value = error.response?.data?.message
      || Object.values(error.response?.data?.errors || {}).flat().join(' ')
      || 'Failed to save FAQ.';
  } finally {
    saving.value = false;
  }
}

async function deleteFaq(faq) {
  if (!confirm('Delete this FAQ?')) return;

  try {
    const { data } = await window.axios.delete(props.updateUrlTemplate.replace('FAQ_ID', faq.id));
    notice.value = data.message;
    await fetchFaqs(faqs.value.length === 1 && meta.value.current_page > 1 ? meta.value.current_page - 1 : meta.value.current_page);
  } catch (error) {
    errorNotice.value = error.response?.data?.message || 'Failed to delete FAQ.';
  }
}

// --- Category modal ---
const categoryModalEl = vueRef(null);
let categoryModalInstance = null;
const categoryName = ref('');
const savingCategory = ref(false);

function ensureCategoryModal() {
  if (!categoryModalInstance) {
    categoryModalInstance = new window.bootstrap.Modal(categoryModalEl.value);
  }
  return categoryModalInstance;
}

function openCategoryModal() {
  categoryName.value = '';
  ensureCategoryModal().show();
}

async function submitCategory() {
  savingCategory.value = true;

  try {
    const { data } = await window.axios.post(props.categoryStoreUrl, { name: categoryName.value });
    notice.value = data.message;
    categories.value = [...categories.value, data.category];
    ensureCategoryModal().hide();
  } catch (error) {
    errorNotice.value = error.response?.data?.message || 'Failed to create category.';
  } finally {
    savingCategory.value = false;
  }
}
</script>

<style scoped>
.faq-hero {
  border-radius: 20px;
  padding: 32px 36px;
  color: #fff;
  margin-bottom: 24px;
}
.faq-hero h4 { color: #fff; font-weight: 700; margin-bottom: 6px; }
.faq-hero p { color: rgba(255,255,255,.85); margin-bottom: 0; max-width: 620px; }
.faq-row td { vertical-align: middle; }
.faq-status-badge { text-transform: capitalize; }
</style>
