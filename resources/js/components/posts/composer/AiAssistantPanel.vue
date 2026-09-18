<template>

  <aside class="ai-panel">

    <div class="ai-panel__header">
      <span class="ai-panel__icon"><i class="bx bx-sparkles"></i></span>
      <div>
        <h6 class="mb-0">AI Content Assistant</h6>
        <small class="text-muted">Generate engaging content with AI</small>
      </div>
    </div>

    <div class="ai-panel__tabs">
      <button
          v-for="tab in tabs"
          :key="tab.key"
          type="button"
          class="ai-panel__tab"
          :class="{ 'ai-panel__tab--active': activeTab === tab.key }"
          @click="activeTab = tab.key">
        <i :class="tab.icon"></i>
        <span>{{ tab.label }}</span>
      </button>
    </div>

    <label class="ai-panel__prompt-label">What would you like to create?</label>
    <textarea
        v-model="prompt"
        class="ai-panel__prompt"
        rows="3"
        placeholder="Describe the content you want to create...&#10;Example: Create a motivational post about productivity for entrepreneurs"></textarea>

    <button type="button" class="ai-panel__generate" :disabled="generating || !prompt.trim()" @click="generate">
      <span v-if="generating" class="spinner-border spinner-border-sm me-1"></span>
      <i v-else class="bx bx-magic-wand me-1"></i>
      Generate Content
    </button>

    <p v-if="notice" class="ai-panel__notice">{{ notice }}</p>

    <div class="ai-panel__suggestions">

      <div class="ai-panel__suggestions-head">
        <span>AI Suggestions</span>
      </div>

      <div class="ai-suggestion-card">
        <div class="ai-suggestion-card__icon" style="background:#EEF2FF;color:#6366F1;"><i class="bx bx-image"></i></div>
        <div class="ai-suggestion-card__body">
          <strong>AI Image</strong>
          <small>Generate stunning visuals for your post</small>
          <button type="button" class="ai-suggestion-card__action" @click="requestGeneration('image')">Generate Image</button>
        </div>
      </div>

      <div class="ai-suggestion-card">
        <div class="ai-suggestion-card__icon" style="background:#ECFDF5;color:#10B981;"><i class="bx bx-video"></i></div>
        <div class="ai-suggestion-card__body">
          <strong>AI Video</strong>
          <small>Create engaging videos with AI</small>
          <button type="button" class="ai-suggestion-card__action" @click="requestGeneration('video')">Generate Video</button>
        </div>
      </div>

      <div class="ai-suggestion-card">
        <div class="ai-suggestion-card__icon" style="background:#FFF7ED;color:#F97316;"><i class="bx bx-edit-alt"></i></div>
        <div class="ai-suggestion-card__body">
          <strong>AI Description</strong>
          <small>Generate engaging post descriptions</small>
          <button type="button" class="ai-suggestion-card__action" @click="requestGeneration('description')">Generate Description</button>
        </div>
      </div>

      <div class="ai-suggestion-card">
        <div class="ai-suggestion-card__icon" style="background:#FDF4FF;color:#C026D3;"><i class="bx bx-hash"></i></div>
        <div class="ai-suggestion-card__body">
          <strong>AI Hashtags</strong>
          <small>Get relevant hashtags for your post</small>
          <button type="button" class="ai-suggestion-card__action" @click="requestGeneration('hashtags')">Generate Hashtags</button>
        </div>
      </div>

    </div>

  </aside>

</template>

<script setup>

// Text generation (title/description/hashtags) is real - proxied through
// PostController::generateAiContent() (admin.posts.generate-ai-content),
// which calls Google Gemini server-side so the API key never reaches this
// component. One call returns all three fields together (Gemini has no
// separate "just hashtags" mode - the schema always asks for the full
// set), so every text-flavored trigger below (the main Generate Content
// button, and the Description/Hashtags/Title suggestion cards) makes the
// same request and PostComposer.vue's onAiGenerated() picks out whichever
// field(s) that trigger cares about.
//
// Image generation is also real now - PostController::generateAiImage()
// (admin.posts.generate-ai-image), Cloudflare Workers AI server-side, same
// "credentials never reach the client" shape. It returns a hosted R2 URL
// (not the raw base64 Cloudflare itself returns - see that method's
// docblock), which is what onAiGenerated() below hands to MediaGrid.vue.
//
// Video genuinely has no backend (out of scope for both AI tasks so far)
// - stays an honest UI-only notice, same as this whole panel used to be
// before either endpoint existed.
import { ref } from 'vue';

const emit = defineEmits(['generated']);

const tabs = [
  { key: 'all', label: 'All', icon: 'bx bx-grid-alt' },
  { key: 'image', label: 'Image', icon: 'bx bx-image' },
  { key: 'video', label: 'Video', icon: 'bx bx-video' },
  { key: 'description', label: 'Description', icon: 'bx bx-edit-alt' },
  { key: 'hashtags', label: 'Hashtags', icon: 'bx bx-hash' },
  { key: 'title', label: 'Title', icon: 'bx bx-heading' },
];

const activeTab = ref('all');
const prompt = ref('');
const generating = ref(false);
const notice = ref('');

const textKinds = ['all', 'description', 'hashtags', 'title'];

function generate() {
  requestGeneration(activeTab.value);
}

async function requestGeneration(kind) {
  if (kind === 'video') {
    notice.value = 'AI video generation isn\'t connected to a backend endpoint yet - this panel is UI only for that, for now.';
    return;
  }

  if (!prompt.value.trim()) {
    notice.value = 'Describe what you\'d like to create first.';
    return;
  }

  generating.value = true;
  notice.value = '';

  try {
    const isImage = kind === 'image';
    const { data } = await window.axios.post(
      isImage ? '/admin/posts/generate-ai-image' : '/admin/posts/generate-ai-content',
      { prompt: prompt.value }
    );

    if (!data.success) {
      notice.value = data.message || 'Failed to generate content.';
      return;
    }

    // kind tells the parent which field(s) the CLICKED trigger cares
    // about (so the "Generate Hashtags" card doesn't stomp a title the
    // user already wrote by hand, and an image result doesn't get
    // mistaken for a text one) - the text endpoint's response always
    // carries all three text fields together regardless of which text
    // trigger was clicked, since Gemini generates them as one call.
    emit('generated', { kind, ...data.data });
  } catch (error) {
    notice.value = error.response?.data?.message || 'Something went wrong while generating content. Please try again.';
  } finally {
    generating.value = false;
  }
}

</script>

<style scoped>

.ai-panel {
  background: #fff;
  border: 1px solid #E5E7EB;
  border-radius: 16px;
  padding: 20px;
}

.ai-panel__header {
  display: flex;
  align-items: center;
  gap: 10px;
  margin-bottom: 18px;
}

.ai-panel__icon {
  width: 36px;
  height: 36px;
  border-radius: 10px;
  background: linear-gradient(135deg,#8B5CF6,#6366F1);
  color: #fff;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 18px;
  flex-shrink: 0;
}

.ai-panel__tabs {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
  margin-bottom: 16px;
}

.ai-panel__tab {
  display: flex;
  align-items: center;
  gap: 5px;
  border: none;
  background: #F3F4F6;
  color: #6b7280;
  font-size: .74rem;
  font-weight: 500;
  padding: 6px 10px;
  border-radius: 8px;
}

.ai-panel__tab--active {
  background: #EEF2FF;
  color: #6366F1;
}

.ai-panel__prompt-label {
  font-size: .78rem;
  font-weight: 600;
  color: #374151;
  margin-bottom: 6px;
}

.ai-panel__prompt {
  width: 100%;
  border: 1px solid #E5E7EB;
  border-radius: 10px;
  padding: 10px 12px;
  font-size: .82rem;
  resize: vertical;
  margin-bottom: 12px;
}

.ai-panel__generate {
  width: 100%;
  border: none;
  border-radius: 10px;
  padding: 10px;
  background: linear-gradient(135deg,#8B5CF6,#6366F1);
  color: #fff;
  font-weight: 600;
  font-size: .84rem;
  display: flex;
  align-items: center;
  justify-content: center;
}

.ai-panel__generate:disabled {
  opacity: .6;
}

.ai-panel__notice {
  font-size: .74rem;
  color: #9a6700;
  background: #FFFBEB;
  border: 1px solid #FDE68A;
  border-radius: 8px;
  padding: 8px 10px;
  margin: 12px 0 0;
}

.ai-panel__suggestions {
  margin-top: 20px;
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.ai-panel__suggestions-head {
  font-size: .84rem;
  font-weight: 600;
  color: #1f2937;
}

.ai-suggestion-card {
  display: flex;
  gap: 10px;
  border: 1px solid #E5E7EB;
  border-radius: 12px;
  padding: 12px;
}

.ai-suggestion-card__icon {
  width: 36px;
  height: 36px;
  border-radius: 9px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 16px;
  flex-shrink: 0;
}

.ai-suggestion-card__body {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.ai-suggestion-card__body strong {
  font-size: .82rem;
}

.ai-suggestion-card__body small {
  color: #6b7280;
  font-size: .72rem;
}

.ai-suggestion-card__action {
  align-self: flex-start;
  margin-top: 6px;
  border: none;
  background: #EEF2FF;
  color: #6366F1;
  font-size: .72rem;
  font-weight: 600;
  padding: 5px 10px;
  border-radius: 7px;
}

</style>
