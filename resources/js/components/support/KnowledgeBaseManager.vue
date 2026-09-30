<template>

  <div class="kb">

    <!-- Hero -->
    <div class="kb-hero">
      <div class="kb-hero-id">
        <span class="kb-hero-mark"><i class="bx bx-book-open"></i></span>
        <div>
          <h4>Knowledge Base</h4>
          <p>Manage the information your AI Copilot can use to answer customer questions.</p>
          <small>Only published entries are available to the AI Copilot.</small>
        </div>
      </div>
      <div class="kb-hero-actions">
        <button type="button" class="kb-btn kb-btn-light" @click="openImportModal">
          <i class="bx bx-upload"></i> Import
        </button>
        <a :href="exportUrl" class="kb-btn kb-btn-light" :title="selected.length ? 'Export the selected entries' : 'Export all entries matching the current filters'">
          <i class="bx bx-download"></i> {{ selected.length ? `Export (${selected.length})` : 'Export' }}
        </a>
        <button type="button" class="kb-btn kb-btn-light kb-btn-brand-text" @click="openCategoryModal">
          <i class="bx bx-plus"></i> New Category
        </button>
        <div class="kb-split" v-click-outside="() => (addMenuOpen = false)">
          <button type="button" class="kb-btn kb-btn-brand" @click="openCreateModal">
            <i class="bx bx-plus"></i> Add Knowledge
          </button>
          <button type="button" class="kb-btn kb-btn-brand kb-split-caret" aria-label="More add options" @click="addMenuOpen = !addMenuOpen">
            <i class="bx bx-chevron-down"></i>
          </button>
          <div v-if="addMenuOpen" class="kb-menu kb-menu-right">
            <button type="button" @click="addMenuOpen = false; openCreateModal()"><i class="bx bx-help-circle"></i> Add FAQ</button>
            <button type="button" @click="addMenuOpen = false; openImportModal()"><i class="bx bx-spreadsheet"></i> Import from CSV</button>
            <button type="button" @click="addMenuOpen = false; openCategoryModal()"><i class="bx bx-folder-plus"></i> New Category</button>
          </div>
        </div>
      </div>
    </div>

    <!-- Notices -->
    <transition name="kb-fade">
      <div v-if="notice" class="kb-notice ok"><i class="bx bx-check-circle"></i> {{ notice }} <button type="button" @click="notice = ''"><i class="bx bx-x"></i></button></div>
    </transition>
    <transition name="kb-fade">
      <div v-if="errorNotice" class="kb-notice bad"><i class="bx bx-error-circle"></i> {{ errorNotice }} <button type="button" @click="errorNotice = ''"><i class="bx bx-x"></i></button></div>
    </transition>

    <!-- Stats -->
    <div class="kb-stats">
      <div class="kb-stat blue">
        <span class="kb-stat-ic"><i class="bx bx-file"></i></span>
        <div>
          <div class="lbl">Total Knowledge</div>
          <div class="val">{{ stats.total }}</div>
          <div class="sub up" v-if="stats.this_month"><i class="bx bx-up-arrow-alt"></i> {{ stats.this_month }} this month</div>
          <div class="sub" v-else>No new entries this month</div>
        </div>
      </div>
      <div class="kb-stat green">
        <span class="kb-stat-ic"><i class="bx bx-check-circle"></i></span>
        <div>
          <div class="lbl">Published</div>
          <div class="val">{{ stats.published }}</div>
          <div class="sub">{{ publishedPct }}% of total</div>
        </div>
      </div>
      <div class="kb-stat amber" role="button" @click="quickFilter('draft')">
        <span class="kb-stat-ic"><i class="bx bx-edit"></i></span>
        <div>
          <div class="lbl">Drafts</div>
          <div class="val">{{ stats.drafts }}</div>
          <div class="sub">{{ stats.drafts ? 'Needs review' : 'All reviewed' }}</div>
        </div>
      </div>
      <a class="kb-stat red" :href="urls.knowledgeGaps">
        <span class="kb-stat-ic"><i class="bx bx-error"></i></span>
        <div>
          <div class="lbl">Knowledge Gaps</div>
          <div class="val">{{ stats.knowledge_gaps }}</div>
          <div class="sub" :class="{ bad: stats.knowledge_gaps }">{{ stats.knowledge_gaps ? 'Need attention' : 'All clear' }}</div>
        </div>
      </a>
    </div>

    <!-- Filters -->
    <div class="kb-card kb-filters">
      <div class="kb-field kb-field-search">
        <i class="bx bx-search"></i>
        <input type="text" v-model="filters.q" @input="debouncedFetch" class="form-control" placeholder="Search questions, answers, tags...">
      </div>
      <div class="kb-field">
        <i class="bx bx-purchase-tag"></i>
        <select v-model="filters.category" @change="fetchFaqs(1)" class="form-select">
          <option value="">All Categories</option>
          <option v-for="cat in categories" :key="cat.id" :value="cat.id">{{ cat.name }}</option>
        </select>
      </div>
      <div class="kb-field">
        <i class="bx bx-time-five"></i>
        <select v-model="filters.status" @change="fetchFaqs(1)" class="form-select">
          <option value="">All Status</option>
          <option value="published">Published</option>
          <option value="draft">Draft</option>
          <option value="archived">Archived</option>
        </select>
      </div>
      <div class="kb-field">
        <i class="bx bx-globe"></i>
        <select v-model="filters.language" @change="fetchFaqs(1)" class="form-select">
          <option value="">All Languages</option>
          <option v-for="(label, code) in LANGUAGES" :key="code" :value="code">{{ label }}</option>
        </select>
      </div>
      <button type="button" class="kb-btn kb-btn-brand" @click="fetchFaqs(1)"><i class="bx bx-filter-alt"></i> Filter</button>
      <button type="button" class="kb-btn kb-btn-outline" @click="clearFilters"><i class="bx bx-refresh"></i> Clear</button>
    </div>

    <!-- Table -->
    <div class="kb-card">
      <div v-if="selected.length" class="kb-bulk">
        <strong>{{ selected.length }} selected</strong>
        <button type="button" class="kb-btn kb-btn-sm kb-btn-outline" @click="bulk('publish')"><i class="bx bx-check-circle"></i> Publish</button>
        <button type="button" class="kb-btn kb-btn-sm kb-btn-outline" @click="bulk('draft')"><i class="bx bx-edit"></i> Move to Draft</button>
        <button type="button" class="kb-btn kb-btn-sm kb-btn-outline" @click="bulk('archive')"><i class="bx bx-archive"></i> Archive</button>
        <a :href="exportUrl" class="kb-btn kb-btn-sm kb-btn-outline"><i class="bx bx-download"></i> Export</a>
        <button type="button" class="kb-btn kb-btn-sm kb-btn-danger" @click="bulk('delete')"><i class="bx bx-trash"></i> Delete</button>
        <button type="button" class="kb-link ms-auto" @click="selected = []">Clear selection</button>
      </div>

      <div class="kb-table-wrap">
        <table class="kb-table">
          <thead>
            <tr>
              <th class="c-check"><input type="checkbox" class="form-check-input" :checked="allSelected" :indeterminate.prop="someSelected" @change="toggleAll" :disabled="!faqs.length"></th>
              <th>Question</th>
              <th>Category</th>
              <th>Language</th>
              <th>Status</th>
              <th>Updated</th>
              <th class="text-end">Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="loading">
              <td colspan="7" class="kb-empty"><span class="spinner-border spinner-border-sm me-2"></span> Loading...</td>
            </tr>
            <tr v-else-if="!faqs.length">
              <td colspan="7" class="kb-empty">
                <i class="bx bx-book-open"></i>
                <template v-if="hasFilters">
                  <strong>No entries match these filters</strong>
                  <button type="button" class="kb-link" @click="clearFilters">Clear filters</button>
                </template>
                <template v-else>
                  <strong>Your Knowledge Base is empty</strong>
                  <span>Add your business hours, delivery policy or pricing so the AI Copilot can answer your customers with it.</span>
                  <button type="button" class="kb-btn kb-btn-brand mt-2" @click="openCreateModal"><i class="bx bx-plus"></i> Add your first FAQ</button>
                </template>
              </td>
            </tr>
            <template v-else>
              <tr v-for="faq in faqs" :key="faq.id" :class="{ sel: selected.includes(faq.id) }">
                <td class="c-check"><input type="checkbox" class="form-check-input" :value="faq.id" v-model="selected"></td>
                <td class="c-q">
                  <button type="button" class="q" @click="openEditModal(faq)">{{ faq.question }}</button>
                  <span class="a">{{ plain(faq.answer) }}</span>
                </td>
                <td>
                  <span v-if="faq.category" class="kb-cat">
                    <span class="kb-cat-ic" :style="iconStyle(categoryIcon(faq.category))"><i :class="['bx', categoryIcon(faq.category)]"></i></span>
                    {{ faq.category.name }}
                  </span>
                  <span v-else class="text-muted">Uncategorized</span>
                </td>
                <td>
                  <span class="kb-lang"><span class="kb-flag" v-html="FLAGS[faq.language] || FLAGS.en"></span>{{ LANGUAGES[faq.language] || faq.language }}</span>
                </td>
                <td>
                  <span class="kb-pill" :class="faq.status">{{ STATUS_LABELS[faq.status] || faq.status }}</span>
                  <i v-if="faq.status === 'published' && faq.copilot_enabled === false" class="bx bx-bot kb-copilot-off" title="Hidden from AI Copilot"></i>
                </td>
                <td class="c-upd" :title="new Date(faq.updated_at).toLocaleString()">{{ timeAgo(faq.updated_at) }}</td>
                <td class="text-end">
                  <button type="button" class="kb-dots" aria-label="Actions" @click.stop="openRowMenu($event, faq)"><i class="bx bx-dots-horizontal-rounded"></i></button>
                </td>
              </tr>
            </template>
          </tbody>
        </table>
      </div>

      <div class="kb-foot" v-if="meta.total">
        <small>Showing {{ meta.from }}–{{ meta.to }} of {{ meta.total }}</small>
        <div class="kb-pages" v-if="meta.last_page > 1">
          <button type="button" :disabled="meta.current_page <= 1" @click="fetchFaqs(meta.current_page - 1)"><i class="bx bx-chevron-left"></i></button>
          <button v-for="p in pageList" :key="p.key" type="button" :class="{ on: p.n === meta.current_page }" :disabled="!p.n" @click="p.n && fetchFaqs(p.n)">{{ p.n || '…' }}</button>
          <button type="button" :disabled="meta.current_page >= meta.last_page" @click="fetchFaqs(meta.current_page + 1)"><i class="bx bx-chevron-right"></i></button>
        </div>
      </div>
    </div>

    <!-- Row action menu (fixed-positioned so the table's horizontal scroll container can't clip it) -->
    <div v-if="rowMenu.faq" class="kb-menu kb-row-menu" :style="{ top: rowMenu.top + 'px', left: rowMenu.left + 'px' }" @click.stop>
      <button type="button" @click="runRow('edit')"><i class="bx bx-edit-alt"></i> Edit</button>
      <button v-if="rowMenu.faq.status !== 'published'" type="button" @click="runRow('publish')"><i class="bx bx-check-circle"></i> Publish</button>
      <button v-if="rowMenu.faq.status !== 'draft'" type="button" @click="runRow('draft')"><i class="bx bx-undo"></i> Move to Draft</button>
      <button v-if="rowMenu.faq.status !== 'archived'" type="button" @click="runRow('archive')"><i class="bx bx-archive"></i> Archive</button>
      <hr>
      <button type="button" class="danger" @click="runRow('delete')"><i class="bx bx-trash"></i> Delete</button>
    </div>

    <!-- Add / Edit FAQ modal -->
    <div class="modal fade" ref="faqModalEl" tabindex="-1">
      <div class="modal-dialog modal-xl modal-dialog-centered">
        <form class="modal-content kb-modal" @submit.prevent="submitFaq()">
          <div class="kb-modal-head">
            <div>
              <h5>{{ editingId ? 'Edit FAQ' : 'Add FAQ' }}</h5>
              <p>Create a question and answer that your AI Copilot can use when responding to customers.</p>
            </div>
            <button type="button" class="kb-close" data-bs-dismiss="modal" aria-label="Close"><i class="bx bx-x"></i></button>
          </div>

          <div class="kb-modal-body">
            <div class="kb-modal-main">
              <div class="mb-3">
                <label class="kb-label">Question <span class="req">*</span></label>
                <input type="text" v-model="form.question" class="form-control" :class="{ 'is-invalid': fieldErrors.question }" maxlength="500" placeholder="Enter customer question..." required>
                <div class="kb-count"><span class="kb-err">{{ fieldErrors.question }}</span>{{ form.question.length }} / 500</div>
              </div>

              <div class="mb-3">
                <div class="d-flex justify-content-between align-items-end mb-1">
                  <label class="kb-label mb-0">Answer <span class="req">*</span></label>
                  <button type="button" class="kb-ai-btn" :disabled="improving || !form.question.trim()" @click="improveAnswer" title="Enter a question first">
                    <span v-if="improving" class="spinner-border spinner-border-sm"></span><i v-else class="bx bxs-magic-wand"></i> Improve with AI
                  </button>
                </div>
                <textarea v-model="form.answer" class="form-control" :class="{ 'is-invalid': fieldErrors.answer }" rows="5" maxlength="2000" placeholder="Enter detailed answer..." required></textarea>
                <div class="kb-count">
                  <span class="kb-err">{{ fieldErrors.answer }}</span>
                  <button v-if="previousAnswer !== null" type="button" class="kb-link me-2" @click="undoImprove"><i class="bx bx-undo"></i> Undo AI change</button>
                  {{ form.answer.length }} / 2000
                </div>
              </div>

              <div class="row g-3">
                <div class="col-md-5">
                  <div class="d-flex justify-content-between align-items-end mb-1">
                    <label class="kb-label mb-0">Category</label>
                    <button type="button" class="kb-link" @click="inlineCategory.open = !inlineCategory.open">+ Create new category</button>
                  </div>
                  <div class="kb-field">
                    <i :class="['bx', selectedCategory ? categoryIcon(selectedCategory) : 'bx-folder']" :style="selectedCategory ? { color: iconStyle(categoryIcon(selectedCategory)).color } : {}"></i>
                    <select v-model="form.faq_category_id" class="form-select">
                      <option value="">Select category</option>
                      <option v-for="cat in categories" :key="cat.id" :value="cat.id">{{ cat.name }}</option>
                    </select>
                  </div>
                  <div v-if="inlineCategory.open" class="kb-inline-cat">
                    <input type="text" v-model="inlineCategory.name" class="form-control form-control-sm" maxlength="100" placeholder="Category name" @keydown.enter.prevent="createInlineCategory">
                    <button type="button" class="kb-btn kb-btn-sm kb-btn-brand" :disabled="!inlineCategory.name.trim() || savingCategory" @click="createInlineCategory">Add</button>
                  </div>
                </div>
                <div class="col-md-3">
                  <label class="kb-label">Language <span class="req">*</span></label>
                  <div class="kb-field">
                    <i class="bx bx-globe"></i>
                    <select v-model="form.language" class="form-select">
                      <option v-for="(label, code) in LANGUAGES" :key="code" :value="code">{{ label }}</option>
                    </select>
                  </div>
                </div>
                <div class="col-md-4">
                  <label class="kb-label">Tags</label>
                  <div class="kb-tags" @click="$refs.tagInput.focus()">
                    <span v-for="tag in form.tags" :key="tag" class="kb-tag">{{ tag }}<button type="button" @click.stop="removeTag(tag)" aria-label="Remove tag"><i class="bx bx-x"></i></button></span>
                    <input ref="tagInput" type="text" v-model="tagDraft" :placeholder="form.tags.length ? '' : '+ Add tag'" @keydown="onTagKey" @blur="addTag(tagDraft)">
                  </div>
                </div>
              </div>

              <div class="row g-3 mt-1">
                <div class="col-md-6">
                  <label class="kb-label">AI Copilot</label>
                  <label class="kb-switch">
                    <input type="checkbox" v-model="form.copilot_enabled">
                    <span class="kb-switch-track"></span>
                    <small>{{ form.copilot_enabled ? 'Published knowledge will be available to AI Copilot.' : 'AI Copilot will not use this entry, even when published.' }}</small>
                  </label>
                </div>
                <div class="col-md-6">
                  <label class="kb-label">Status</label>
                  <div class="kb-radios">
                    <label><input type="radio" class="form-check-input" value="published" v-model="form.status"> Published</label>
                    <label><input type="radio" class="form-check-input" value="draft" v-model="form.status"> Draft</label>
                    <label v-if="editingId"><input type="radio" class="form-check-input" value="archived" v-model="form.status"> Archived</label>
                  </div>
                </div>
              </div>

              <div v-if="formError" class="kb-notice bad mt-3 mb-0"><i class="bx bx-error-circle"></i> {{ formError }}</div>
            </div>

            <aside class="kb-modal-side">
              <div class="kb-ai-card">
                <div class="d-flex gap-2">
                  <span class="kb-ai-ic"><i class="bx bx-bot"></i></span>
                  <div>
                    <strong>AI Optimize Answer</strong>
                    <p>Use AI to improve your answer with best practices, tone and clarity.</p>
                  </div>
                </div>
                <button type="button" class="kb-ai-btn" :disabled="improving || !form.question.trim()" @click="improveAnswer">
                  <span v-if="improving" class="spinner-border spinner-border-sm"></span><i v-else class="bx bxs-magic-wand"></i> Improve with AI
                </button>
                <small v-if="!form.question.trim()" class="d-block mt-2 text-muted">Enter a question first.</small>
              </div>

              <div class="kb-side-sec">
                <div class="kb-side-h">Suggested Tags</div>
                <div class="kb-suggest">
                  <button v-for="tag in suggestedTags" :key="tag" type="button" @click="addTag(tag)">{{ tag }}</button>
                  <button type="button" class="add" @click="$refs.tagInput.focus()">+ Add tag</button>
                </div>
              </div>

              <div class="kb-side-sec">
                <div class="kb-side-h">Category Icons</div>
                <div class="kb-presets">
                  <button v-for="p in PRESETS" :key="p.name" type="button" :class="{ on: selectedCategory && selectedCategory.name.toLowerCase() === p.name.toLowerCase() }" :disabled="savingCategory" @click="pickPreset(p)">
                    <span class="kb-cat-ic" :style="iconStyle(p.icon)"><i :class="['bx', p.icon]"></i></span>
                    {{ p.name }}
                  </button>
                </div>
              </div>
            </aside>
          </div>

          <div class="kb-modal-foot">
            <button type="button" class="kb-btn kb-btn-outline" data-bs-dismiss="modal">Cancel</button>
            <button type="button" class="kb-btn kb-btn-outline-brand" :disabled="saving" @click="submitFaq('draft')">Save Draft</button>
            <button type="submit" class="kb-btn kb-btn-brand" :disabled="saving">
              <span v-if="saving" class="spinner-border spinner-border-sm me-1"></span>
              {{ primaryLabel }}
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- New Category modal -->
    <div class="modal fade" ref="categoryModalEl" tabindex="-1">
      <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content kb-modal" @submit.prevent="submitCategory">
          <div class="kb-modal-head">
            <div>
              <h5>New Category</h5>
              <p>Group related entries so they're easier to find and manage.</p>
            </div>
            <button type="button" class="kb-close" data-bs-dismiss="modal" aria-label="Close"><i class="bx bx-x"></i></button>
          </div>
          <div class="kb-modal-main">
            <label class="kb-label">Category name <span class="req">*</span></label>
            <input type="text" v-model="categoryForm.name" class="form-control" required maxlength="100" placeholder="e.g. Shipping">
            <label class="kb-label mt-3">Icon</label>
            <div class="kb-icon-grid">
              <button v-for="icon in ICON_CHOICES" :key="icon" type="button" :class="{ on: categoryForm.icon === icon }" @click="categoryForm.icon = icon">
                <span class="kb-cat-ic" :style="iconStyle(icon)"><i :class="['bx', icon]"></i></span>
              </button>
            </div>
          </div>
          <div class="kb-modal-foot">
            <button type="button" class="kb-btn kb-btn-outline" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="kb-btn kb-btn-brand" :disabled="savingCategory">Create Category</button>
          </div>
        </form>
      </div>
    </div>

    <!-- Import modal -->
    <div class="modal fade" ref="importModalEl" tabindex="-1">
      <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content kb-modal" @submit.prevent="submitImport">
          <div class="kb-modal-head">
            <div>
              <h5>Import Knowledge</h5>
              <p>Upload a CSV with the columns <code>question, answer, category, language, status, tags</code>.</p>
            </div>
            <button type="button" class="kb-close" data-bs-dismiss="modal" aria-label="Close"><i class="bx bx-x"></i></button>
          </div>
          <div class="kb-modal-main">
            <label class="kb-drop" :class="{ has: importFile }">
              <input type="file" accept=".csv,text/csv" @change="importFile = $event.target.files[0] || null">
              <i class="bx bx-cloud-upload"></i>
              <strong>{{ importFile ? importFile.name : 'Choose a CSV file' }}</strong>
              <small>Max 2 MB, up to 500 rows. New categories are created automatically.</small>
            </label>
            <button type="button" class="kb-link mt-2" @click="downloadTemplate"><i class="bx bx-download"></i> Download template</button>
            <div v-if="importError" class="kb-notice bad mt-3 mb-0"><i class="bx bx-error-circle"></i> {{ importError }}</div>
          </div>
          <div class="kb-modal-foot">
            <button type="button" class="kb-btn kb-btn-outline" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="kb-btn kb-btn-brand" :disabled="!importFile || importing">
              <span v-if="importing" class="spinner-border spinner-border-sm me-1"></span> Import
            </button>
          </div>
        </form>
      </div>
    </div>

  </div>

</template>

<script setup>
// Seller-facing Knowledge Base (admin.knowledge-base.*). System FAQ
// management keeps the simpler shared FaqManager.vue - this component is
// the redesigned seller page only, talking to KnowledgeBaseController's
// JSON endpoints (index on an axios GET, store/update/destroy, bulk,
// import/export, improve, categories.store).
import { ref, computed, onMounted, onBeforeUnmount } from 'vue';

const props = defineProps({
  initialFaqs: { type: Object, required: true },
  initialCategories: { type: Array, default: () => [] },
  initialStats: { type: Object, default: () => ({}) },
  // route()-generated URLs (they carry the locale prefix); update and
  // destroy use the literal FAQ_ID token in place of the id.
  urls: { type: Object, required: true },
});

const LANGUAGES = { en: 'English', ar: 'Arabic' };
const STATUS_LABELS = { published: 'Published', draft: 'Draft', archived: 'Archived' };

const FLAGS = {
  en: '<svg viewBox="0 0 60 60"><rect width="60" height="60" fill="#012169"/><path d="M0 0L60 60M60 0L0 60" stroke="#fff" stroke-width="12"/><path d="M0 0L60 60M60 0L0 60" stroke="#C8102E" stroke-width="5"/><path d="M30 0v60M0 30h60" stroke="#fff" stroke-width="18"/><path d="M30 0v60M0 30h60" stroke="#C8102E" stroke-width="10"/></svg>',
  ar: '<svg viewBox="0 0 60 60"><rect width="60" height="60" fill="#006C35"/><path d="M16 24c4-3 8 2 12-1s8 2 12-1 5 1 6 0" stroke="#fff" stroke-width="3" fill="none" stroke-linecap="round"/><path d="M17 38h26" stroke="#fff" stroke-width="3" stroke-linecap="round"/></svg>',
};

// Quick-pick categories on the Add FAQ modal: clicking one selects the
// seller's existing category with that name, or creates it.
const PRESETS = [
  { name: 'Shipping', icon: 'bx-car' },
  { name: 'Payments', icon: 'bx-credit-card' },
  { name: 'Returns', icon: 'bx-undo' },
  { name: 'Orders', icon: 'bx-package' },
  { name: 'Products', icon: 'bx-cube' },
  { name: 'General', icon: 'bx-info-circle' },
];

const ICON_CHOICES = [
  'bx-car', 'bx-credit-card', 'bx-undo', 'bx-package', 'bx-cube', 'bx-info-circle',
  'bx-time-five', 'bx-store', 'bx-purchase-tag', 'bx-shield-quarter', 'bx-gift', 'bx-phone',
  'bx-map', 'bx-user', 'bx-wrench', 'bx-folder',
];

const ICON_COLORS = {
  'bx-car': '#2563eb', 'bx-credit-card': '#ea580c', 'bx-undo': '#f97316', 'bx-package': '#2563eb',
  'bx-cube': '#6d4aff', 'bx-info-circle': '#6d4aff', 'bx-time-five': '#0891b2', 'bx-store': '#16a34a',
  'bx-purchase-tag': '#db2777', 'bx-shield-quarter': '#0f766e', 'bx-gift': '#e11d48', 'bx-phone': '#0284c7',
  'bx-map': '#65a30d', 'bx-user': '#7c3aed', 'bx-wrench': '#475569', 'bx-folder': '#64748b',
};

// Categories created before icons existed (or by CSV import) get one
// guessed from their name.
const ICON_GUESSES = [
  [/ship|deliver|courier|توصيل|شحن/i, 'bx-car'],
  [/pay|billing|price|invoice|دفع|سعر/i, 'bx-credit-card'],
  [/return|refund|exchange|استرجاع|استبدال/i, 'bx-undo'],
  [/order|track|طلب/i, 'bx-package'],
  [/product|item|stock|منتج/i, 'bx-cube'],
  [/hour|time|open|ساعات|دوام/i, 'bx-time-five'],
  [/store|branch|location|فرع|متجر/i, 'bx-store'],
  [/warrant|guarantee|ضمان/i, 'bx-shield-quarter'],
  [/offer|discount|promo|coupon|عرض|خصم/i, 'bx-purchase-tag'],
  [/contact|support|call|تواصل/i, 'bx-phone'],
  [/account|profile|حساب/i, 'bx-user'],
];

const STOPWORDS = new Set(['the', 'you', 'your', 'our', 'what', 'when', 'where', 'which', 'how', 'can', 'does', 'do', 'are', 'is', 'for', 'with', 'from', 'this', 'that', 'have', 'will', 'there', 'any', 'about', 'into', 'offer', 'accept']);

// --- state ---
const faqs = ref(props.initialFaqs.data || []);
const meta = ref(pageMeta(props.initialFaqs));
const categories = ref(props.initialCategories);
const stats = ref({ total: 0, published: 0, drafts: 0, knowledge_gaps: 0, this_month: 0, ...props.initialStats });
const loading = ref(false);
const notice = ref('');
const errorNotice = ref('');
const filters = ref({ q: '', category: '', status: '', language: '' });
const selected = ref([]);
const addMenuOpen = ref(false);

function pageMeta(p) {
  return { current_page: p.current_page || 1, last_page: p.last_page || 1, total: p.total || 0, from: p.from || 0, to: p.to || 0 };
}

const publishedPct = computed(() => (stats.value.total ? Math.round((stats.value.published / stats.value.total) * 100) : 0));
const hasFilters = computed(() => Object.values(filters.value).some(Boolean));
const allSelected = computed(() => faqs.value.length > 0 && faqs.value.every((f) => selected.value.includes(f.id)));
const someSelected = computed(() => selected.value.length > 0 && !allSelected.value);

// Selected rows win; with nothing ticked, export what the filters show.
const exportUrl = computed(() => {
  const params = new URLSearchParams();
  if (selected.value.length) {
    params.set('ids', selected.value.join(','));
  } else {
    Object.entries(filters.value).forEach(([k, v]) => { if (v) params.set(k, v); });
  }
  const qs = params.toString();
  return props.urls.export + (qs ? (props.urls.export.includes('?') ? '&' : '?') + qs : '');
});

const pageList = computed(() => {
  const { current_page: cur, last_page: last } = meta.value;
  const nums = [...new Set([1, last, cur - 1, cur, cur + 1].filter((n) => n >= 1 && n <= last))].sort((a, b) => a - b);
  const out = [];
  nums.forEach((n, i) => {
    if (i && n - nums[i - 1] > 1) out.push({ key: 'gap' + n, n: null });
    out.push({ key: n, n });
  });
  return out;
});

function flash(message, isError = false) {
  if (isError) { errorNotice.value = message; notice.value = ''; } else { notice.value = message; errorNotice.value = ''; }
  clearTimeout(flash.timer);
  flash.timer = setTimeout(() => { notice.value = ''; errorNotice.value = ''; }, 6000);
}

function errorMessage(error, fallback) {
  return error.response?.data?.message || Object.values(error.response?.data?.errors || {}).flat().join(' ') || fallback;
}

// --- listing ---
let debounceTimer = null;
function debouncedFetch() {
  clearTimeout(debounceTimer);
  debounceTimer = setTimeout(() => fetchFaqs(1), 400);
}

async function fetchFaqs(page = 1) {
  loading.value = true;
  try {
    const params = { page };
    Object.entries(filters.value).forEach(([k, v]) => { if (v) params[k] = v; });
    const { data } = await window.axios.get(props.urls.index, { params, headers: { 'X-Requested-With': 'XMLHttpRequest' } });
    faqs.value = data.faqs.data;
    meta.value = pageMeta(data.faqs);
    categories.value = data.categories;
    stats.value = { ...stats.value, ...data.stats };
    selected.value = selected.value.filter((id) => faqs.value.some((f) => f.id === id));
  } catch (error) {
    flash('Failed to load the Knowledge Base.', true);
  } finally {
    loading.value = false;
  }
}

function clearFilters() {
  filters.value = { q: '', category: '', status: '', language: '' };
  fetchFaqs(1);
}

function quickFilter(status) {
  filters.value.status = status;
  fetchFaqs(1);
}

function toggleAll(e) {
  selected.value = e.target.checked ? faqs.value.map((f) => f.id) : [];
}

function plain(html) {
  const div = document.createElement('div');
  div.innerHTML = html || '';
  return (div.textContent || '').replace(/\s+/g, ' ').trim();
}

function timeAgo(iso) {
  if (!iso) return '—';
  const s = Math.max(0, (Date.now() - new Date(iso).getTime()) / 1000);
  const units = [[31536000, 'year'], [2592000, 'month'], [604800, 'week'], [86400, 'day'], [3600, 'hour'], [60, 'minute']];
  for (const [sec, name] of units) {
    const n = Math.floor(s / sec);
    if (n >= 1) return `${n} ${name}${n > 1 ? 's' : ''} ago`;
  }
  return 'Just now';
}

function categoryIcon(cat) {
  if (cat.icon) return cat.icon;
  const hit = ICON_GUESSES.find(([re]) => re.test(cat.name || ''));
  return hit ? hit[1] : 'bx-folder';
}

function iconStyle(icon) {
  const c = ICON_COLORS[icon] || '#64748b';
  return { color: c, background: c + '1a' };
}

// --- row menu + bulk ---
const rowMenu = ref({ faq: null, top: 0, left: 0 });

function openRowMenu(event, faq) {
  const r = event.currentTarget.getBoundingClientRect();
  rowMenu.value = rowMenu.value.faq?.id === faq.id ? { faq: null } : { faq, top: r.bottom + 4, left: Math.max(8, r.right - 180) };
}

function closeRowMenu() { rowMenu.value = { faq: null, top: 0, left: 0 }; }

function runRow(action) {
  const faq = rowMenu.value.faq;
  closeRowMenu();
  if (action === 'edit') return openEditModal(faq);
  if (action === 'delete') return deleteFaq(faq);
  return bulk(action, [faq.id]);
}

async function bulk(action, ids = selected.value) {
  if (!ids.length) return;
  if (action === 'delete' && !confirm(`Delete ${ids.length} ${ids.length === 1 ? 'entry' : 'entries'}? This can't be undone.`)) return;
  try {
    const { data } = await window.axios.post(props.urls.bulk, { ids, action });
    flash(data.message);
    selected.value = selected.value.filter((id) => !ids.includes(id));
    const onlyRowsLeft = action === 'delete' && ids.length >= faqs.value.length && meta.value.current_page > 1;
    await fetchFaqs(onlyRowsLeft ? meta.value.current_page - 1 : meta.value.current_page);
  } catch (error) {
    flash(errorMessage(error, 'Action failed.'), true);
  }
}

async function deleteFaq(faq) {
  if (!confirm(`Delete "${faq.question}"? This can't be undone.`)) return;
  try {
    const { data } = await window.axios.delete(props.urls.update.replace('FAQ_ID', faq.id));
    flash(data.message);
    await fetchFaqs(faqs.value.length === 1 && meta.value.current_page > 1 ? meta.value.current_page - 1 : meta.value.current_page);
  } catch (error) {
    flash(errorMessage(error, 'Failed to delete FAQ.'), true);
  }
}

// --- modals ---
const faqModalEl = ref(null);
const categoryModalEl = ref(null);
const importModalEl = ref(null);
const modals = {};
function modal(name, el) {
  if (!modals[name]) modals[name] = new window.bootstrap.Modal(el.value);
  return modals[name];
}

// --- FAQ form ---
const blankForm = () => ({ question: '', answer: '', faq_category_id: '', language: 'en', status: 'published', copilot_enabled: true, tags: [] });
const form = ref(blankForm());
const editingId = ref(null);
const saving = ref(false);
const formError = ref('');
const fieldErrors = ref({});
const tagDraft = ref('');
const improving = ref(false);
const previousAnswer = ref(null);
const aiTags = ref([]);
const inlineCategory = ref({ open: false, name: '' });

const selectedCategory = computed(() => categories.value.find((c) => c.id === form.value.faq_category_id) || null);
const primaryLabel = computed(() => {
  if (form.value.status === 'published') return editingId.value ? 'Update & Publish' : 'Publish FAQ';
  return editingId.value ? 'Update FAQ' : 'Save FAQ';
});

const suggestedTags = computed(() => {
  const words = (form.value.question.toLowerCase().match(/[\p{L}\p{N}]{4,}/gu) || []).filter((w) => !STOPWORDS.has(w));
  const pool = [...aiTags.value, ...(selectedCategory.value ? [selectedCategory.value.name.toLowerCase()] : []), ...words];
  return [...new Set(pool)].filter((t) => !form.value.tags.includes(t)).slice(0, 6);
});

function resetFormState() {
  formError.value = '';
  fieldErrors.value = {};
  tagDraft.value = '';
  previousAnswer.value = null;
  aiTags.value = [];
  inlineCategory.value = { open: false, name: '' };
}

function openCreateModal() {
  editingId.value = null;
  form.value = blankForm();
  resetFormState();
  modal('faq', faqModalEl).show();
}

function openEditModal(faq) {
  editingId.value = faq.id;
  form.value = {
    question: faq.question,
    answer: faq.answer,
    faq_category_id: faq.faq_category_id || '',
    language: faq.language || 'en',
    status: faq.status,
    copilot_enabled: faq.copilot_enabled !== false,
    tags: [...(faq.tags || [])],
  };
  resetFormState();
  modal('faq', faqModalEl).show();
}

function addTag(raw) {
  const tag = (raw || '').replace(/,/g, '').trim().toLowerCase().slice(0, 40);
  if (tag && !form.value.tags.includes(tag) && form.value.tags.length < 15) form.value.tags.push(tag);
  tagDraft.value = '';
}

function onTagKey(e) {
  if (e.key === 'Enter' || e.key === ',') {
    e.preventDefault();
    addTag(tagDraft.value);
  } else if (e.key === 'Backspace' && tagDraft.value === '') {
    form.value.tags.pop();
  }
}

function removeTag(tag) {
  form.value.tags = form.value.tags.filter((t) => t !== tag);
}

async function improveAnswer() {
  improving.value = true;
  formError.value = '';
  try {
    const { data } = await window.axios.post(props.urls.improve, {
      question: form.value.question,
      answer: plain(form.value.answer),
      language: form.value.language,
    });
    previousAnswer.value = form.value.answer;
    form.value.answer = data.answer;
    aiTags.value = data.tags || [];
  } catch (error) {
    formError.value = errorMessage(error, 'AI could not improve this answer.');
  } finally {
    improving.value = false;
  }
}

function undoImprove() {
  form.value.answer = previousAnswer.value;
  previousAnswer.value = null;
}

async function submitFaq(forceStatus = null) {
  if (forceStatus) form.value.status = forceStatus;
  addTag(tagDraft.value);
  saving.value = true;
  formError.value = '';
  fieldErrors.value = {};

  try {
    const url = editingId.value ? props.urls.update.replace('FAQ_ID', editingId.value) : props.urls.store;
    const payload = { ...form.value, tags: form.value.tags.join(', ') };
    const { data } = await window.axios[editingId.value ? 'put' : 'post'](url, payload);
    modal('faq', faqModalEl).hide();
    flash(data.message);
    await fetchFaqs(editingId.value ? meta.value.current_page : 1);
  } catch (error) {
    const errs = error.response?.data?.errors || {};
    fieldErrors.value = Object.fromEntries(Object.entries(errs).map(([k, v]) => [k, v[0]]));
    formError.value = Object.keys(errs).length ? 'Please fix the highlighted fields.' : errorMessage(error, 'Failed to save FAQ.');
  } finally {
    saving.value = false;
  }
}

// --- categories ---
const categoryForm = ref({ name: '', icon: 'bx-folder' });
const savingCategory = ref(false);

async function createCategory(name, icon) {
  savingCategory.value = true;
  try {
    const { data } = await window.axios.post(props.urls.categoryStore, { name, icon });
    categories.value = [...categories.value, data.category];
    return data.category;
  } finally {
    savingCategory.value = false;
  }
}

function openCategoryModal() {
  categoryForm.value = { name: '', icon: 'bx-folder' };
  modal('category', categoryModalEl).show();
}

async function submitCategory() {
  try {
    const cat = await createCategory(categoryForm.value.name, categoryForm.value.icon);
    modal('category', categoryModalEl).hide();
    flash(`Category "${cat.name}" created.`);
  } catch (error) {
    flash(errorMessage(error, 'Failed to create category.'), true);
  }
}

async function createInlineCategory() {
  const name = inlineCategory.value.name.trim();
  if (!name) return;
  const existing = categories.value.find((c) => c.name.toLowerCase() === name.toLowerCase());
  try {
    const cat = existing || await createCategory(name, categoryIcon({ name }));
    form.value.faq_category_id = cat.id;
    inlineCategory.value = { open: false, name: '' };
  } catch (error) {
    formError.value = errorMessage(error, 'Failed to create category.');
  }
}

async function pickPreset(preset) {
  const existing = categories.value.find((c) => c.name.toLowerCase() === preset.name.toLowerCase());
  try {
    const cat = existing || await createCategory(preset.name, preset.icon);
    form.value.faq_category_id = cat.id;
  } catch (error) {
    formError.value = errorMessage(error, 'Failed to create category.');
  }
}

// --- import ---
const importFile = ref(null);
const importing = ref(false);
const importError = ref('');

function openImportModal() {
  importFile.value = null;
  importError.value = '';
  modal('import', importModalEl).show();
}

async function submitImport() {
  importing.value = true;
  importError.value = '';
  try {
    const body = new FormData();
    body.append('file', importFile.value);
    const { data } = await window.axios.post(props.urls.import, body);
    modal('import', importModalEl).hide();
    flash(data.message);
    await fetchFaqs(1);
  } catch (error) {
    importError.value = errorMessage(error, 'Import failed.');
  } finally {
    importing.value = false;
  }
}

function downloadTemplate() {
  const csv = '﻿question,answer,category,language,status,tags\n'
    + '"Do you deliver to Jeddah?","Yes, we deliver to Jeddah within 2-3 business days.",Shipping,en,published,"delivery, jeddah"\n';
  const a = document.createElement('a');
  a.href = URL.createObjectURL(new Blob([csv], { type: 'text/csv;charset=utf-8' }));
  a.download = 'knowledge-base-template.csv';
  a.click();
  URL.revokeObjectURL(a.href);
}

// --- global listeners (row menu dismissal) ---
function onDocClick() { if (rowMenu.value.faq) closeRowMenu(); }
onMounted(() => {
  document.addEventListener('click', onDocClick);
  window.addEventListener('scroll', closeRowMenu, true);
  window.addEventListener('resize', closeRowMenu);
});
onBeforeUnmount(() => {
  document.removeEventListener('click', onDocClick);
  window.removeEventListener('scroll', closeRowMenu, true);
  window.removeEventListener('resize', closeRowMenu);
});

// Local directive: closes the "Add Knowledge" dropdown on outside click.
const vClickOutside = {
  // Vue 2.7 directive hooks (inserted/unbind, not Vue 3's mounted/unmounted).
  inserted(el, binding) {
    el._kbOutside = (e) => { if (!el.contains(e.target)) binding.value(e); };
    document.addEventListener('click', el._kbOutside);
  },
  unbind(el) { document.removeEventListener('click', el._kbOutside); },
};
</script>

<style scoped>
.kb { --ln: #e6e8ef; --ln-soft: #f0f2f6; --ink: #1b2130; --ink2: #5b6475; --muted: #8a93a3; --brand: #6d4aff; --brand-soft: #f1edff; --radius: 14px; }

/* Buttons */
.kb-btn { display: inline-flex; align-items: center; justify-content: center; gap: .4rem; height: 40px; padding: 0 1rem; border-radius: 10px; font-size: .86rem; font-weight: 600; border: 1px solid transparent; cursor: pointer; white-space: nowrap; text-decoration: none; transition: background .15s, border-color .15s, color .15s, box-shadow .15s; }
.kb-btn i { font-size: 1.05rem; }
.kb-btn:disabled { opacity: .6; cursor: not-allowed; }
.kb-btn-sm { height: 32px; padding: 0 .7rem; font-size: .8rem; border-radius: 8px; }
.kb-btn-light { background: #fff; color: var(--ink); box-shadow: 0 1px 2px rgba(16, 24, 40, .08); }
.kb-btn-light:hover { background: #f8f9fc; color: var(--ink); }
.kb-btn-brand-text { color: var(--brand); }
.kb-btn-brand-text:hover { color: var(--brand); }
.kb-btn-brand { background: var(--brand); color: #fff; }
.kb-btn-brand:hover:not(:disabled) { background: #5a36f0; color: #fff; }
.kb-btn-outline { background: #fff; border-color: var(--ln); color: var(--ink2); }
.kb-btn-outline:hover { border-color: #cfd4de; color: var(--ink); }
.kb-btn-outline-brand { background: #fff; border-color: var(--brand); color: var(--brand); }
.kb-btn-outline-brand:hover:not(:disabled) { background: var(--brand-soft); }
.kb-btn-danger { background: #fff; border-color: #f5c2c0; color: #d92d20; }
.kb-btn-danger:hover { background: #fef3f2; }
.kb-link { background: none; border: 0; padding: 0; color: var(--brand); font-size: .78rem; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: .2rem; }
.kb-link:hover { text-decoration: underline; }

/* Hero */
.kb-hero { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 1rem 1.25rem; padding: 1.5rem; border-radius: 18px; margin-bottom: 1.25rem; color: #fff; background: linear-gradient(110deg, #12b886 0%, #16a3b8 45%, #5b7cfa 80%, #7c5cff 100%); }
.kb-hero-id { display: flex; align-items: center; gap: 1rem; flex: 1 1 340px; min-width: 0; }
.kb-hero-mark { width: 60px; height: 60px; border-radius: 50%; background: rgba(255, 255, 255, .2); display: inline-flex; align-items: center; justify-content: center; font-size: 1.8rem; flex-shrink: 0; }
.kb-hero h4 { color: #fff; font-weight: 700; font-size: 1.35rem; margin: 0 0 .3rem; }
.kb-hero p { color: rgba(255, 255, 255, .92); margin: 0; font-size: .92rem; }
.kb-hero small { color: rgba(255, 255, 255, .8); font-size: .78rem; }
.kb-hero-actions { display: flex; flex-wrap: wrap; gap: .5rem; }
.kb-hero-actions .kb-btn { padding: 0 .85rem; }
.kb-hero-actions .kb-split-caret { padding: 0 .5rem; }
.kb-split { position: relative; display: inline-flex; }
.kb-split > .kb-btn:first-child { border-radius: 10px 0 0 10px; }
.kb-split-caret { border-radius: 0 10px 10px 0 !important; padding: 0 .55rem; border-left: 1px solid rgba(255, 255, 255, .3); }

/* Menus */
.kb-menu { position: absolute; z-index: 1060; min-width: 190px; background: #fff; border: 1px solid var(--ln); border-radius: 12px; box-shadow: 0 12px 32px rgba(16, 24, 40, .14); padding: .35rem; }
.kb-menu-right { right: 0; top: calc(100% + 6px); }
.kb-row-menu { position: fixed; min-width: 180px; }
.kb-menu button { display: flex; align-items: center; gap: .55rem; width: 100%; background: none; border: 0; padding: .5rem .65rem; border-radius: 8px; font-size: .84rem; color: var(--ink); text-align: left; cursor: pointer; }
.kb-menu button:hover { background: #f5f6fa; }
.kb-menu button i { font-size: 1.05rem; color: var(--ink2); }
.kb-menu button.danger, .kb-menu button.danger i { color: #d92d20; }
.kb-menu hr { margin: .3rem 0; border-color: var(--ln-soft); opacity: 1; }

/* Notices */
.kb-notice { display: flex; align-items: center; gap: .55rem; padding: .7rem 1rem; border-radius: 12px; font-size: .86rem; margin-bottom: 1rem; }
.kb-notice i { font-size: 1.15rem; }
.kb-notice button { margin-left: auto; background: none; border: 0; color: inherit; opacity: .7; cursor: pointer; }
.kb-notice.ok { background: #ecfdf3; color: #067647; border: 1px solid #abefc6; }
.kb-notice.bad { background: #fef3f2; color: #b42318; border: 1px solid #fecdca; }
.kb-fade-enter-active, .kb-fade-leave-active { transition: opacity .2s; }
.kb-fade-enter, .kb-fade-leave-to { opacity: 0; }

/* Stats */
.kb-stats { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 1rem; margin-bottom: 1.25rem; }
.kb-stat { display: flex; align-items: flex-start; gap: .9rem; padding: 1.1rem 1.2rem; border-radius: var(--radius); border: 1px solid; text-decoration: none; transition: transform .15s, box-shadow .15s; }
a.kb-stat:hover, .kb-stat[role="button"]:hover { transform: translateY(-1px); box-shadow: 0 6px 16px rgba(16, 24, 40, .06); cursor: pointer; }
.kb-stat-ic { width: 46px; height: 46px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-size: 1.4rem; flex-shrink: 0; }
.kb-stat .lbl { font-size: .82rem; color: var(--ink2); font-weight: 500; }
.kb-stat .val { font-size: 1.55rem; font-weight: 700; color: var(--ink); line-height: 1.25; font-variant-numeric: tabular-nums; }
.kb-stat .sub { font-size: .76rem; color: var(--muted); margin-top: .15rem; display: flex; align-items: center; gap: .15rem; }
.kb-stat .sub.up { color: #079455; }
.kb-stat .sub.bad { color: #d92d20; }
.kb-stat.blue { background: #f3f7ff; border-color: #d9e4ff; }
.kb-stat.blue .kb-stat-ic { background: #dfe8ff; color: #2e5bff; }
.kb-stat.green { background: #f2fbf6; border-color: #cdeedb; }
.kb-stat.green .kb-stat-ic { background: #d8f3e3; color: #079455; }
.kb-stat.amber { background: #fffaf2; border-color: #fbe3bf; }
.kb-stat.amber .kb-stat-ic { background: #fdebd0; color: #dc6803; }
.kb-stat.red { background: #fff6f5; border-color: #fbd3cf; }
.kb-stat.red .kb-stat-ic { background: #fde0dc; color: #d92d20; }

/* Cards / filters */
.kb-card { background: #fff; border: 1px solid var(--ln); border-radius: var(--radius); margin-bottom: 1.25rem; }
.kb-filters { display: grid; grid-template-columns: minmax(220px, 2.2fr) repeat(3, minmax(150px, 1fr)) auto auto; gap: .7rem; padding: .8rem; align-items: center; }
.kb-field { position: relative; }
.kb-field > i { position: absolute; left: .8rem; top: 50%; transform: translateY(-50%); color: var(--muted); font-size: 1.1rem; pointer-events: none; z-index: 1; }
.kb-field .form-control, .kb-field .form-select { padding-left: 2.35rem; height: 40px; border-radius: 10px; border-color: var(--ln); font-size: .86rem; }
.kb-field .form-control:focus, .kb-field .form-select:focus { border-color: var(--brand); box-shadow: 0 0 0 3px rgba(109, 74, 255, .12); }

/* Bulk bar */
.kb-bulk { display: flex; align-items: center; flex-wrap: wrap; gap: .5rem; padding: .65rem 1rem; background: var(--brand-soft); border-bottom: 1px solid #e0d8ff; border-radius: var(--radius) var(--radius) 0 0; font-size: .84rem; color: var(--ink); }
.kb-bulk strong { margin-right: .4rem; }

/* Table */
.kb-table-wrap { overflow-x: auto; }
.kb-table { width: 100%; min-width: 860px; border-collapse: collapse; }
.kb-table thead th { font-size: .7rem; font-weight: 700; letter-spacing: .05em; text-transform: uppercase; color: var(--ink2); padding: .85rem 1rem; border-bottom: 1px solid var(--ln); white-space: nowrap; }
.kb-table tbody td { padding: .8rem 1rem; border-bottom: 1px solid var(--ln-soft); vertical-align: middle; font-size: .84rem; color: var(--ink2); }
.kb-table tbody tr:last-child td { border-bottom: 0; }
.kb-table tbody tr:hover td { background: #fafbfd; }
.kb-table tbody tr.sel td { background: #f8f6ff; }
.kb-table .c-check { width: 44px; padding-right: 0; }
.kb-table .c-q { max-width: 460px; }
.kb-table .c-q .q { display: block; background: none; border: 0; padding: 0; text-align: left; font-weight: 600; color: var(--ink); font-size: .87rem; cursor: pointer; }
.kb-table .c-q .q:hover { color: var(--brand); }
.kb-table .c-q .a { display: block; margin-top: .15rem; font-size: .78rem; color: var(--muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 460px; }
.kb-table .c-upd { white-space: nowrap; }
.kb-empty { text-align: center; padding: 3rem 1rem !important; color: var(--muted); }
.kb-empty > i { display: block; font-size: 2.4rem; color: #c9cedb; margin-bottom: .5rem; }
.kb-empty strong { display: block; color: var(--ink); font-size: .95rem; margin-bottom: .25rem; }
.kb-empty span { display: block; max-width: 420px; margin: 0 auto; font-size: .82rem; }

.kb-cat { display: inline-flex; align-items: center; gap: .55rem; white-space: nowrap; color: var(--ink); }
.kb-cat-ic { width: 32px; height: 32px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-size: 1.05rem; flex-shrink: 0; }
.kb-lang { display: inline-flex; align-items: center; gap: .5rem; white-space: nowrap; color: var(--ink); }
.kb-flag { width: 20px; height: 20px; border-radius: 50%; overflow: hidden; display: inline-flex; box-shadow: 0 0 0 1px rgba(0, 0, 0, .06); }
.kb-flag :deep(svg) { width: 100%; height: 100%; }
.kb-pill { display: inline-block; min-width: 86px; text-align: center; padding: .3rem .75rem; border-radius: 8px; font-size: .76rem; font-weight: 600; }
.kb-pill.published { background: #e3f8ec; color: #067647; }
.kb-pill.draft { background: #eef0f4; color: #475467; }
.kb-pill.archived { background: #eceff3; color: #667085; }
.kb-copilot-off { margin-left: .35rem; color: #f79009; vertical-align: middle; }
.kb-dots { width: 34px; height: 34px; border-radius: 9px; border: 1px solid var(--ln); background: #fff; color: var(--ink); display: inline-flex; align-items: center; justify-content: center; font-size: 1.2rem; cursor: pointer; }
.kb-dots:hover { border-color: #cfd4de; background: #f8f9fc; }

.kb-foot { display: flex; align-items: center; justify-content: space-between; gap: .75rem; padding: .75rem 1rem; border-top: 1px solid var(--ln-soft); color: var(--muted); }
.kb-pages { display: flex; gap: .3rem; }
.kb-pages button { min-width: 32px; height: 32px; padding: 0 .5rem; border-radius: 8px; border: 1px solid var(--ln); background: #fff; color: var(--ink2); font-size: .8rem; font-weight: 600; cursor: pointer; }
.kb-pages button.on { background: var(--brand); border-color: var(--brand); color: #fff; }
.kb-pages button:disabled:not(.on) { opacity: .5; cursor: default; }

/* Modal */
.kb-modal { border: 0; border-radius: 16px; overflow: hidden; }
.kb-modal-head { display: flex; justify-content: space-between; align-items: flex-start; gap: 1rem; padding: 1.25rem 1.5rem .9rem; border-bottom: 1px solid var(--ln-soft); }
.kb-close { flex-shrink: 0; width: 32px; height: 32px; border-radius: 8px; border: 0; background: transparent; color: var(--ink2); font-size: 1.4rem; display: inline-flex; align-items: center; justify-content: center; cursor: pointer; }
.kb-close:hover { background: #f2f4f7; color: var(--ink); }
.kb-modal-head h5 { margin: 0; font-weight: 700; color: var(--ink); }
.kb-modal-head p { margin: .2rem 0 0; font-size: .8rem; color: var(--muted); }
.kb-modal-body { display: grid; grid-template-columns: minmax(0, 1fr) 290px; }
.kb-modal-main { padding: 1.25rem 1.5rem; }
.kb-modal-side { padding: 1.25rem; border-left: 1px solid var(--ln-soft); background: #fcfcfe; }
.kb-modal-foot { display: flex; justify-content: flex-end; gap: .6rem; padding: .9rem 1.5rem; border-top: 1px solid var(--ln-soft); }
.kb-label { display: block; font-size: .8rem; font-weight: 600; color: var(--ink); margin-bottom: .35rem; }
.kb-label .req { color: #d92d20; }
.kb-modal .form-control, .kb-modal .form-select { border-color: var(--ln); border-radius: 10px; font-size: .86rem; }
.kb-modal .form-control:focus, .kb-modal .form-select:focus { border-color: var(--brand); box-shadow: 0 0 0 3px rgba(109, 74, 255, .12); }
.kb-count { display: flex; justify-content: flex-end; align-items: center; font-size: .72rem; color: var(--muted); margin-top: .25rem; }
.kb-err { margin-right: auto; color: #d92d20; }
.kb-ai-btn { display: inline-flex; align-items: center; gap: .35rem; background: #fff; border: 1px solid #d9d0ff; color: var(--brand); border-radius: 8px; padding: .3rem .7rem; font-size: .78rem; font-weight: 600; cursor: pointer; }
.kb-ai-btn:hover:not(:disabled) { background: var(--brand-soft); }
.kb-ai-btn:disabled { opacity: .55; cursor: not-allowed; }
.kb-inline-cat { display: flex; gap: .4rem; margin-top: .5rem; }

.kb-tags { display: flex; flex-wrap: wrap; align-items: center; gap: .3rem; min-height: 40px; padding: .3rem .5rem; border: 1px solid var(--ln); border-radius: 10px; cursor: text; }
.kb-tags:focus-within { border-color: var(--brand); box-shadow: 0 0 0 3px rgba(109, 74, 255, .12); }
.kb-tags input { flex: 1; min-width: 70px; border: 0; outline: 0; font-size: .82rem; background: transparent; }
.kb-tag { display: inline-flex; align-items: center; gap: .15rem; background: var(--brand-soft); color: var(--brand); border-radius: 6px; padding: .12rem .2rem .12rem .45rem; font-size: .75rem; font-weight: 600; }
.kb-tag button { background: none; border: 0; padding: 0; color: inherit; display: inline-flex; cursor: pointer; }

.kb-switch { display: flex; align-items: center; gap: .6rem; cursor: pointer; margin: 0; }
.kb-switch input { position: absolute; opacity: 0; }
.kb-switch-track { position: relative; width: 38px; height: 22px; border-radius: 22px; background: #d0d5dd; flex-shrink: 0; transition: background .15s; }
.kb-switch-track::after { content: ''; position: absolute; top: 3px; left: 3px; width: 16px; height: 16px; border-radius: 50%; background: #fff; transition: transform .15s; box-shadow: 0 1px 2px rgba(0, 0, 0, .2); }
.kb-switch input:checked + .kb-switch-track { background: #12b76a; }
.kb-switch input:checked + .kb-switch-track::after { transform: translateX(16px); }
.kb-switch input:focus-visible + .kb-switch-track { box-shadow: 0 0 0 3px rgba(109, 74, 255, .25); }
.kb-switch small { font-size: .75rem; color: var(--ink2); }
.kb-radios { display: flex; gap: 1.25rem; min-height: 22px; align-items: center; }
.kb-radios label { display: inline-flex; align-items: center; gap: .4rem; font-size: .84rem; color: var(--ink); cursor: pointer; }
.kb-radios .form-check-input { margin: 0; }
.kb-radios .form-check-input:checked { background-color: var(--brand); border-color: var(--brand); }

.kb-ai-card { background: linear-gradient(160deg, #f4f0ff, #fbfaff); border: 1px solid #e6defe; border-radius: 12px; padding: .95rem; }
.kb-ai-card strong { font-size: .86rem; color: var(--brand); }
.kb-ai-card p { font-size: .76rem; color: var(--ink2); margin: .2rem 0 .7rem; }
.kb-ai-ic { width: 34px; height: 34px; border-radius: 50%; background: #e6defe; color: var(--brand); display: inline-flex; align-items: center; justify-content: center; font-size: 1.15rem; flex-shrink: 0; }
.kb-side-sec { margin-top: 1.1rem; }
.kb-side-h { font-size: .8rem; font-weight: 700; color: var(--ink); margin-bottom: .5rem; }
.kb-suggest { display: flex; flex-wrap: wrap; gap: .35rem; }
.kb-suggest button { background: #fff; border: 1px solid var(--ln); border-radius: 6px; padding: .2rem .55rem; font-size: .74rem; color: var(--ink2); cursor: pointer; }
.kb-suggest button:hover { border-color: var(--brand); color: var(--brand); }
.kb-suggest button.add { color: var(--brand); border-color: #d9d0ff; }
.kb-presets { display: grid; grid-template-columns: repeat(3, 1fr); gap: .45rem; }
.kb-presets button { display: flex; flex-direction: column; align-items: center; gap: .3rem; background: #fff; border: 1px solid var(--ln); border-radius: 10px; padding: .6rem .3rem; font-size: .72rem; color: var(--ink2); cursor: pointer; }
.kb-presets button:hover, .kb-presets button.on { border-color: var(--brand); color: var(--brand); }
.kb-presets button.on { background: var(--brand-soft); }

.kb-icon-grid { display: grid; grid-template-columns: repeat(8, 1fr); gap: .4rem; }
.kb-icon-grid button { display: flex; align-items: center; justify-content: center; background: #fff; border: 1px solid var(--ln); border-radius: 10px; padding: .35rem; cursor: pointer; }
.kb-icon-grid button.on { border-color: var(--brand); box-shadow: 0 0 0 2px rgba(109, 74, 255, .2); }

.kb-drop { display: flex; flex-direction: column; align-items: center; gap: .3rem; padding: 1.75rem 1rem; border: 2px dashed #d0d5dd; border-radius: 12px; text-align: center; cursor: pointer; color: var(--ink2); }
.kb-drop:hover, .kb-drop.has { border-color: var(--brand); background: #faf8ff; }
.kb-drop input { display: none; }
.kb-drop i { font-size: 2rem; color: var(--brand); }
.kb-drop strong { color: var(--ink); font-size: .88rem; word-break: break-all; }
.kb-drop small { font-size: .75rem; color: var(--muted); }

/* Responsive */
@media (max-width: 1199.98px) {
  .kb-stats { grid-template-columns: repeat(2, minmax(0, 1fr)); }
  .kb-filters { grid-template-columns: 1fr 1fr 1fr; }
  .kb-filters .kb-field-search { grid-column: 1 / -1; }
}
@media (max-width: 991.98px) {
  .kb-modal-body { grid-template-columns: 1fr; }
  .kb-modal-side { border-left: 0; border-top: 1px solid var(--ln-soft); }
}
@media (max-width: 575.98px) {
  .kb-hero { padding: 1.25rem; }
  .kb-hero-actions { width: 100%; }
  .kb-stats { grid-template-columns: 1fr; }
  .kb-filters { grid-template-columns: 1fr 1fr; }
  .kb-foot { flex-direction: column; }
  .kb-icon-grid { grid-template-columns: repeat(4, 1fr); }
}

/* RTL */
[dir="rtl"] .kb-field > i { left: auto; right: .8rem; }
[dir="rtl"] .kb-field .form-control, [dir="rtl"] .kb-field .form-select { padding-left: .75rem; padding-right: 2.35rem; }
[dir="rtl"] .kb-menu-right { right: auto; left: 0; }
[dir="rtl"] .kb-menu button, [dir="rtl"] .kb-table .c-q .q { text-align: right; }
[dir="rtl"] .kb-notice button { margin-left: 0; margin-right: auto; }
[dir="rtl"] .kb-modal-side { border-left: 0; border-right: 1px solid var(--ln-soft); }
[dir="rtl"] .kb-split > .kb-btn:first-child { border-radius: 0 10px 10px 0; }
[dir="rtl"] .kb-split-caret { border-radius: 10px 0 0 10px !important; border-left: 0; border-right: 1px solid rgba(255, 255, 255, .3); }
</style>
