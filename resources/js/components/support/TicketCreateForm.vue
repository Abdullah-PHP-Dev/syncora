<template>

  <div class="row justify-content-center">
    <div class="col-md-8">
      <div class="card">
        <div class="card-header">
          <h5 class="mb-0"><i class="bx bx-support"></i> New Support Ticket</h5>
          <small class="text-muted">Check the <a :href="helpCenterUrl">Help Center</a> first - your question might already be answered there.</small>
        </div>
        <div class="card-body">
          <form @submit.prevent="submit">
            <div class="mb-3">
              <label class="form-label">Subject</label>
              <input type="text" v-model="form.subject" class="form-control" :class="{ 'is-invalid': errors.subject }" required maxlength="200">
              <div class="invalid-feedback" v-if="errors.subject">{{ errors.subject[0] }}</div>
            </div>
            <div class="row g-3 mb-3">
              <div class="col-md-6">
                <label class="form-label">Category</label>
                <select v-model="form.category" class="form-select">
                  <option value="Account & Billing">Account &amp; Billing</option>
                  <option value="Publishing & Posting">Publishing &amp; Posting</option>
                  <option value="Integrations & API">Integrations &amp; API</option>
                  <option value="Bug Report">Bug Report</option>
                  <option value="Other">Other</option>
                </select>
              </div>
              <div class="col-md-6">
                <label class="form-label">Priority</label>
                <select v-model="form.priority" class="form-select" required>
                  <option value="low">Low</option>
                  <option value="medium">Medium</option>
                  <option value="high">High</option>
                  <option value="urgent">Urgent</option>
                </select>
              </div>
            </div>
            <div class="mb-3">
              <label class="form-label">Describe the issue</label>
              <textarea v-model="form.body" class="form-control" :class="{ 'is-invalid': errors.body }" rows="6" required></textarea>
              <div class="invalid-feedback" v-if="errors.body">{{ errors.body[0] }}</div>
            </div>
            <div v-if="generalError" class="alert alert-danger py-2">{{ generalError }}</div>
            <div class="d-flex justify-content-end gap-2">
              <a :href="indexUrl" class="btn btn-label-secondary">Cancel</a>
              <button type="submit" class="btn btn-primary" :disabled="submitting">
                <span v-if="submitting" class="spinner-border spinner-border-sm me-1"></span>
                <i v-else class="bx bx-send"></i> Submit Ticket
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>

</template>

<script setup>
import { ref } from 'vue';

const props = defineProps({
  storeUrl: { type: String, required: true },
  indexUrl: { type: String, required: true },
  helpCenterUrl: { type: String, required: true },
});

const form = ref({ subject: '', category: 'Account & Billing', priority: 'medium', body: '' });
const errors = ref({});
const generalError = ref('');
const submitting = ref(false);

async function submit() {
  submitting.value = true;
  errors.value = {};
  generalError.value = '';

  try {
    const { data } = await window.axios.post(props.storeUrl, form.value);
    window.location.href = data.redirect_url;
  } catch (error) {
    if (error.response?.status === 422) {
      errors.value = error.response.data.errors || {};
    } else {
      generalError.value = error.response?.data?.message || 'Failed to submit ticket. Please try again.';
    }
  } finally {
    submitting.value = false;
  }
}
</script>
