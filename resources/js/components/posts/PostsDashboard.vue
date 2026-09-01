<template>
  <div class="posts-dashboard">

    <div class="dashboard-header">

      <div>

        <div class="page-badge">
          Social Media Management
        </div>

        <h2 class="page-title">
          Social Media Posts
        </h2>

        <p class="page-subtitle">
          Manage, schedule and monitor all your social media posts from one place.
        </p>

      </div>

      <div class="header-actions">

        <button
            class="btn btn-outline-secondary">

          <i class="fas fa-download mr-2"></i>

          Export

        </button>

        <button
            class="btn btn-primary btn-create"
            @click="createPost">

          <i class="fas fa-plus mr-2"></i>

          Create Post

        </button>

      </div>

    </div>

    <!-- Quick Post Composer -->
    <div class="quick-composer" @click="openQuickCreate">

      <div class="composer-avatar">{{ userInitials }}</div>

      <div class="composer-input">What's on your mind, {{ userFirstName }}?</div>

      <div class="composer-icons">

        <i class="fas fa-image" style="color:#45BD62" title="Photo" @click.stop="openQuickCreate"></i>

        <i class="fas fa-video" style="color:#F3425F" title="Video" @click.stop="openQuickCreate"></i>

      </div>

    </div>

    <!-- Statistics -->
    <div class="platform-tabs">

      <div
          v-for="platform in platformTabs"
          :key="platform.name"
          class="platform-tab"
          :class="{ active: activePlatform === platform.name }"
          @click="activePlatform = platform.name">

        <span v-if="platform.key === 'all'" class="platform-tab-icon" :style="{ background: activePlatform === platform.name ? 'rgba(255,255,255,.2)' : platform.color + '1a', color: activePlatform === platform.name ? '#fff' : platform.color }">
          <i :class="platform.icon"></i>
        </span>

        <account-avatar-badge
            v-else
            style="margin-right: 15px;"
            :avatar-url="platform.avatarUrl"
            :icon="platform.icon"
            :color="platform.color"
            :size="44" />

        <div class="platform-info">
          <strong>{{ platform.accountName || platform.name }}</strong>
          <small>{{ platform.count }} Posts</small>
        </div>

      </div>

    </div>

    <!-- Toolbar -->
    <div class="toolbar">

      <div class="toolbar-left">

        <div class="search-box">

          <i class="fas fa-search"></i>

          <input
              type="text"
              v-model="filters.search"
              placeholder="Search posts by title or content...">

        </div>

      </div>

      <div class="toolbar-right">

        <select class="modern-select" v-model="filters.status">
          <option value="">All Status</option>
          <option value="Published">Published</option>
          <option value="Scheduled">Scheduled</option>
          <option value="Failed">Failed</option>
        </select>

        <select class="modern-select" v-model="filters.sort">
          <option value="latest">Latest First</option>
          <option value="oldest">Oldest First</option>
        </select>

        <button
            class="modern-btn d-none"
            :class="{active: gridView}"
            title="Grid view"
            @click="gridView = true">
          <i class="fas fa-th-large"></i>
        </button>

        <button
            class="modern-btn d-none"
            :class="{active: !gridView}"
            title="List view"
            @click="gridView = false">
          <i class="fas fa-list"></i>
        </button>

      </div>

    </div>
    <!-- Platform Overview -->

    <div class="section-title">

      <div>

        <h3>Recent Posts</h3>

        <p>{{ pageInfo.total }} Posts Available</p>

      </div>

    </div>

    <div
        v-if="!loading && paginatedPosts.length === 0"
        class="posts-empty-state">

      No posts found.

    </div>

    <div
        class="posts-grid"
        :class="{list:!gridView, loading:loading}">

      <div
          class="post-card"
          v-for="post in paginatedPosts"
          :key="post.id">

        <div class="post-image">

          <!-- Image -->
          <img
              v-if="post.type==='image'"
              :src="post.image"
          >

          <!-- Video -->
          <div
              v-else-if="post.type==='video'"
              class="video-wrapper"
              @mouseenter="playPreview"
              @mouseleave="pausePreview">

            <video
                :poster="post.thumbnail"
                preload="metadata"
                muted
                playsinline>

              <source
                  :src="post.video"
                  type="video/mp4">

            </video>

            <div class="play-button">

              <i class="fas fa-play"></i>

            </div>

          </div>

          <div
              v-else-if="post.type==='reel'"
              class="video-wrapper"
              @mouseenter="playPreview"
              @mouseleave="pausePreview">

            <video
                muted
                loop
                playsinline
                :poster="post.thumbnail">

              <source
                  :src="post.video"
                  type="video/mp4">

            </video>

            <div class="play-button reel">

              <i class="fas fa-play"></i>

            </div>

          </div>

          <!-- Carousel -->
          <img
              v-else-if="post.type==='carousel'"
              :src="post.image"
          >

          <!-- Text only -->
          <div
              v-else-if="post.type==='text'"
              class="text-only-card">

            {{ post.content }}

          </div>

          <span
              class="status-badge"
              :class="post.status.toLowerCase()">

        {{ post.status }}

    </span>

          <!-- Type Badge -->
          <div class="media-type">

            <i
                v-if="post.type==='video'"
                class="fas fa-play-circle">
            </i>

            <i
                v-else-if="post.type==='carousel'"
                class="far fa-images">
            </i>

            <i
                v-else-if="post.type==='reel'"
                class="fas fa-film">
            </i>

          </div>

        </div>

        <div class="post-body">

          <div class="post-platform">

            <div class="platform-icons">

              <a
                  v-for="platform in post.platforms"
                  :key="platform.name"
                  class="platform-account-pill"
                  :href="previewUrl(post, platform)"
                  :title="platform.page + ' · ' + platform.name">

                <account-avatar-badge
                    :avatar-url="platform.avatar"
                    :icon="platform.icon"
                    :color="platform.color"
                    :size="32" />

                <span class="platform-account-name">{{ platform.page }}</span>

              </a>

            </div>

            <i class="fas fa-ellipsis-v"></i>

          </div>

          <h4>

            {{ post.title }}

          </h4>

          <p>

            {{ post.content }}

          </p>

          <div class="post-stats">

                <span>

                    ❤️ {{ post.likes }}

                </span>

            <span>

                    💬 {{ post.comments }}

                </span>

            <span>

                    🔄 {{ post.shares }}

                </span>

            <span>

                    👁 {{ post.views }}

                </span>

          </div>

          <div class="post-footer">

                <span>

                    <i class="far fa-user"></i>

                    {{ post.author }}

                </span>

            <span>

                    <i class="far fa-calendar"></i>

                    {{ post.created_at }}

                </span>

          </div>

        </div>

      </div>

    </div>

    <div
        class="pagination-wrapper"
        v-if="totalPages > 1">

      <div class="pagination-info">

        Showing

        <strong>{{ pageInfo.from }}</strong>

        -

        <strong>{{ pageInfo.to }}</strong>

        of

        <strong>{{ pageInfo.total }}</strong>

        Posts

      </div>

      <div class="pagination">

        <button
            class="page-btn"
            :disabled="pagination.currentPage===1"
            @click="goToPage(pagination.currentPage - 1)">

          <i class="fas fa-chevron-left"></i>

        </button>

        <button

            v-for="page in totalPages"

            :key="page"

            class="page-btn"

            :class="{active:page===pagination.currentPage}"

            @click="goToPage(page)">

          {{ page }}

        </button>

        <button

            class="page-btn"

            :disabled="pagination.currentPage===totalPages"

            @click="goToPage(pagination.currentPage + 1)">

          <i class="fas fa-chevron-right"></i>

        </button>

      </div>

    </div>

    <!-- Quick Create Modal -->
    <div
        class="modal fade"
        id="quickCreateModal"
        tabindex="-1"
        ref="quickCreateModal">

      <div class="modal-dialog modal-dialog-centered">

        <div class="modal-content quick-create-modal">

          <div class="modal-header">

            <h5 class="modal-title">Create post</h5>

            <button
                type="button"
                class="btn-close"
                data-bs-dismiss="modal"
                aria-label="Close"></button>

          </div>

          <div class="modal-body">

            <div class="composer-user-row">

              <div class="composer-avatar">{{ userInitials }}</div>

              <div>
                <strong>{{ userName }}</strong>
                <div class="composer-audience">
                  <i class="fas fa-users"></i> Friends
                </div>
              </div>

            </div>

            <textarea
                class="quick-textarea"
                rows="4"
                v-model="quickPost.content"
                :placeholder="'What\'s on your mind, ' + userFirstName + '?'">
            </textarea>

            <div
                class="quick-media-preview"
                v-if="quickPost.mediaPreview">

              <img
                  v-if="quickPost.mediaType==='image'"
                  :src="quickPost.mediaPreview">

              <video
                  v-else
                  :src="quickPost.mediaPreview"
                  controls>
              </video>

              <button
                  type="button"
                  class="remove-media-btn"
                  @click="removeQuickMedia">
                <i class="fas fa-times"></i>
              </button>

            </div>

            <div class="quick-platform-label">Post to</div>

            <div class="quick-platform-select">

              <div
                  v-for="platform in accountOptions"
                  :key="platform.key"
                  class="quick-account-chip"
                  :class="{active: quickPost.platforms.includes(platform.key)}"
                  @click="toggleQuickPlatform(platform.key)">

                <account-avatar-badge
                    :avatar-url="platform.avatarUrl"
                    :icon="platform.icon"
                    :color="platform.color"
                    :size="32" />

                <span class="quick-account-name">{{ platform.accountName }}</span>

              </div>

            </div>

            <div class="quick-schedule-row">

              <label class="quick-checkbox">
                <input type="checkbox" v-model="quickPost.scheduleLater">
                Schedule for later
              </label>

              <input
                  v-if="quickPost.scheduleLater"
                  type="datetime-local"
                  class="modern-select"
                  v-model="quickPost.scheduleAt">

            </div>

            <div class="add-to-post-row">

              <span>Add to your post</span>

              <div class="add-to-post-icons">

                <label class="media-upload-btn" title="Photo/Video">
                  <i class="fas fa-image" style="color:#45BD62"></i>
                  <input
                      type="file"
                      accept="image/*,video/*"
                      class="d-none"
                      @change="onQuickMediaSelected">
                </label>

                <i class="fas fa-user-tag" style="color:#1877F2" title="Tag people"></i>

                <i class="far fa-smile" style="color:#F7B928" title="Feeling/activity"></i>

                <i class="fas fa-map-marker-alt" style="color:#F5533D" title="Location"></i>

              </div>

            </div>

          </div>

          <div class="modal-footer">

            <button
                type="button"
                class="btn btn-primary w-100"
                :disabled="!canSubmitQuickPost"
                @click="submitQuickPost">

              {{ quickPostSubmitting ? 'Posting...' : (quickPost.scheduleLater ? 'Schedule Post' : 'Post') }}

            </button>

          </div>

        </div>

      </div>

    </div>

  </div>
</template>

<script>
import { platformMeta, platformOrder } from '../../data/mockPosts';
import AccountAvatarBadge from './AccountAvatarBadge.vue';

export default {

  components: {
    AccountAvatarBadge
  },

  props: {

    platform: {
      type: String,
      default: ''
    },

    createUrl: {
      type: String,
      default: ''
    },

    previewUrlBase: {
      type: String,
      default: '/admin/posts'
    },

    // Base for the PUBLIC (unauthenticated) share-preview page - see
    // routes/web.php's posts.share route and PostController::sharePreview().
    // Different from previewUrlBase above: that one requires login and is
    // for viewing your own post inside the app; this one is what Snap's
    // Creative Kit share button points at, since Snap's servers fetch its
    // og:image/og:title with no session cookie.
    shareUrlBase: {
      type: String,
      default: '/share/posts'
    },

    userName: {
      type: String,
      default: 'Admin'
    },

    initialPosts: {
      type: Array,
      default: () => []
    },

    apiUrl: {
      type: String,
      default: ''
    },

    quickCreateUrl: {
      type: String,
      default: ''
    },

    // Real connected accounts ({id, platform, name, username, avatar_url}) -
    // the "Create post" modal shows these instead of bare platform logos,
    // same reasoning as the calendar's picker (resources/views/admin/posts/
    // dashboard.blade.php): picking "Facebook" should look like picking the
    // actual connected Page, not an abstract network icon.
    postingAccounts: {
      type: Array,
      default: () => []
    },

    initialTotal: {
      type: Number,
      default: 0
    },

    initialLastPage: {
      type: Number,
      default: 1
    },

    initialPerPage: {
      type: Number,
      default: 6
    },

    platformCounts: {
      type: Object,
      default: () => ({})
    }

  },

  data(){

    return{
      loading: false,
      searchDebounce: null,
      pagination: {

        currentPage: 1,

        perPage: this.initialPerPage,

        total: this.initialTotal,

        lastPage: this.initialLastPage

      },
      activePlatform: 'All',
      posts: this.initialPosts.map(this.transformPost),
      platformCountsData: { ...this.platformCounts },
      filters:{

        search:'',
        status:'',
        sort:'latest'

      },

      gridView:true,
      platforms: [
        { key: 'all', name: 'All', icon: 'fas fa-globe', color: '#5D87FF' },
        ...platformOrder.map(key => ({
          key,
          name: platformMeta[key].name,
          icon: platformMeta[key].icon,
          color: platformMeta[key].color
        }))
      ],

      quickPost: {
        content: '',
        platforms: [],
        mediaFile: null,
        mediaPreview: null,
        mediaType: null,
        scheduleLater: false,
        scheduleAt: ''
      },
      quickPostSubmitting: false

    }

  },

  computed: {

    userInitials() {

      return this.userName
          .split(' ')
          .filter(Boolean)
          .slice(0, 2)
          .map(part => part[0].toUpperCase())
          .join('');

    },

    userFirstName() {

      return this.userName.split(' ')[0];

    },

    platformOptions() {

      return platformOrder.map(key => platformMeta[key]);

    },

    // One chip per connected platform (deduped - quickCreateUrl posts to
    // every posting-permitted account on a platform at once, so a second
    // Facebook Page can't be targeted separately), carrying the real
    // account's name/avatar alongside that platform's icon/color. Falls
    // back to platformOptions when no real accounts were passed in, so
    // this still renders something sensible if the prop is ever omitted.
    accountOptions() {

      if (!this.postingAccounts || !this.postingAccounts.length) {
        return this.platformOptions.map(p => ({ ...p, accountName: p.name, avatarUrl: null }));
      }

      const seen = new Set();
      const options = [];

      this.postingAccounts.forEach(account => {
        if (seen.has(account.platform)) return;
        seen.add(account.platform);

        const meta = platformMeta[account.platform] || {
          key: account.platform,
          name: account.platform,
          icon: 'fas fa-share-alt',
          color: '#5D87FF'
        };

        options.push({
          ...meta,
          accountName: account.name || account.username || meta.name,
          avatarUrl: account.avatar_url || null
        });

      });

      return options;

    },

    canSubmitQuickPost() {

      return (this.quickPost.content.trim().length > 0 || this.quickPost.mediaPreview)
          && this.quickPost.platforms.length > 0
          && !this.quickPostSubmitting;

    },

    totalPages() {

      return this.pagination.lastPage;

    },

    paginatedPosts() {

      return this.posts;

    },

    pageInfo() {

      if (!this.pagination.total) {

        return {
          from:0,
          to:0,
          total:0
        };

      }

      const from =
          (this.pagination.currentPage - 1) *
          this.pagination.perPage + 1;

      const to = Math.min(

          this.pagination.currentPage *
          this.pagination.perPage,

          this.pagination.total

      );

      return {

        from,

        to,

        total:this.pagination.total

      };

    },

    platformTabs() {

      return this.platforms.map(platform => {

        const account = this.accountOptions.find(a => a.key === platform.key);

        return {
          ...platform,
          accountName: account ? account.accountName : null,
          avatarUrl: account ? account.avatarUrl : null,
          count: this.platformCountsData[platform.key] || 0
        };

      });

    }

  },
  methods: {

    // Backend groups every platform a single quick-post submission was
    // published to under raw.platforms (see PostController::
    // platformsForGroups()) - one entry per {platform_key, status,
    // post_id, post_url}. Falls back to wrapping raw.platform_key alone
    // for older/ungrouped responses that never had a platforms array.
    transformPost(raw) {

      const entries = (raw.platforms && raw.platforms.length)
        ? raw.platforms
        : [{ platform_key: raw.platform_key, status: raw.status, post_id: raw.id, post_url: raw.post_url }];

      const platforms = entries.map(entry => {
        const key = entry.platform_key;
        const meta = platformMeta[key] || {
          key,
          name: entry.platform_key,
          icon: 'fas fa-share-alt',
          color: '#5D87FF'
        };

        return {
          ...meta,
          key,
          post_id: entry.post_id,
          status: entry.status,
          // Each platform in the group carries its OWN connected account -
          // falling back to raw.account_name here would show the same one
          // account's name under every platform badge in a multi-platform
          // post, which is wrong the moment two different Pages are involved.
          page: entry.account_name || raw.account_name || meta.page,
          handle: entry.account_username ? ('@' + entry.account_username) : (raw.account_handle || meta.handle),
          avatar: entry.account_avatar || null
        };
      });

      return {

        ...raw,

        platforms

      };

    },

    goToPage(page) {

      if (page < 1 || page > this.pagination.lastPage || page === this.pagination.currentPage) return;

      this.pagination.currentPage = page;
      this.fetchPosts();

    },

    fetchPosts() {

      if (!this.apiUrl) return;

      this.loading = true;

      window.axios.get(this.apiUrl, {
        params: {
          page: this.pagination.currentPage,
          per_page: this.pagination.perPage,
          search: this.filters.search || undefined,
          status: this.filters.status || undefined,
          platform: this.activePlatform !== 'All' ? this.activePlatform.toLowerCase() : undefined,
          sort: this.filters.sort
        }
      }).then(({ data }) => {

        this.posts = data.data.map(this.transformPost);
        this.pagination.total = data.total;
        this.pagination.lastPage = data.last_page;
        this.platformCountsData = data.platform_counts || this.platformCountsData;

      }).finally(() => {

        this.loading = false;

      });

    },

    createPost() {

      window.location.href = this.createUrl;

    },

    previewUrl(post, platform) {

      // platform.post_id is that specific platform's own Post row - falls
      // back to the card's representative post.id for older responses
      // without a grouped platforms array. Without this, every icon in a
      // grouped card would link to the same (wrong) post regardless of
      // which platform was clicked.
      return `${this.previewUrlBase}/${platform.post_id || post.id}/preview/${platform.key}`;

    },

    // quickStore()'s response is keyed by platform (facebook/instagram/...),
    // each an array of that platform's own Post row - one quick-post
    // submission fans out to several identical rows, so any one of them
    // points at the same content/media for the public share-preview page.
    snapchatShareUrl(results) {

      if (!results) return null;

      for (const platform in results) {
        const rows = results[platform];
        if (Array.isArray(rows) && rows.length && rows[0].id) {
          return `${this.shareUrlBase}/${rows[0].id}`;
        }
      }

      return null;

    },

    // Snap Creative Kit only exposes a declarative, class-based init - no
    // imperative "trigger a share now" call (confirmed against
    // developers.snap.com/snap-kit/creative-kit/web) - so the button has to
    // already be in the DOM with the right data-share-url before this
    // fires. SweetAlert2's didOpen callback is exactly that moment for the
    // button injected via the html: option above.
    initSnapchatShareButtons() {

      if (window.snap && window.snap.creativekit) {
        window.snap.creativekit.initalizeShareButtons(
          document.getElementsByClassName('snapchat-share-button')
        );
      }

    },

    playPreview(e) {

      const video = e.currentTarget.querySelector('video');

      if (!video) return;

      video.currentTime = 0;

      const playPromise = video.play();

      if (playPromise && playPromise.catch) playPromise.catch(() => {});

    },

    pausePreview(e) {

      const video = e.currentTarget.querySelector('video');

      if (!video) return;

      video.pause();

      video.currentTime = 0;

    },

    openQuickCreate() {

      // eslint-disable-next-line no-undef
      const modalEl = this.$refs.quickCreateModal;

      if (window.bootstrap && modalEl) {

        window.bootstrap.Modal.getOrCreateInstance(modalEl).show();

      }

    },

    toggleQuickPlatform(key) {

      const idx = this.quickPost.platforms.indexOf(key);

      if (idx === -1) {
        this.quickPost.platforms.push(key);
      } else {
        this.quickPost.platforms.splice(idx, 1);
      }

    },

    onQuickMediaSelected(e) {

      const file = e.target.files[0];

      if (!file) return;

      this.quickPost.mediaFile = file;
      this.quickPost.mediaType = file.type.startsWith('video') ? 'video' : 'image';
      this.quickPost.mediaPreview = URL.createObjectURL(file);

    },

    removeQuickMedia() {

      this.quickPost.mediaFile = null;
      this.quickPost.mediaPreview = null;
      this.quickPost.mediaType = null;

    },

    quickPostErrorMessage(payload) {

      const errors = payload && payload.errors;

      if (Array.isArray(errors) && errors.length) {
        return errors.map(e => (e && e.message) ? e.message : JSON.stringify(e)).join(' ');
      }

      if (errors && typeof errors === 'object') {
        return Object.values(errors).flat().join(' ');
      }

      return (payload && payload.message) || 'Something went wrong while posting.';

    },

    submitQuickPost() {

      if (!this.canSubmitQuickPost || !this.quickCreateUrl) return;

      this.quickPostSubmitting = true;

      const formData = new FormData();

      formData.append('content', this.quickPost.content);
      this.quickPost.platforms.forEach(key => formData.append('platforms[]', key));

      if (this.quickPost.mediaFile) {
        formData.append('media', this.quickPost.mediaFile);
      }

      if (this.quickPost.scheduleLater && this.quickPost.scheduleAt) {
        formData.append('schedule_mode', '1');
        formData.append('schedule_at', this.quickPost.scheduleAt);
      }

      window.axios.post(this.quickCreateUrl, formData).then(({ data }) => {

        const modalEl = this.$refs.quickCreateModal;

        if (window.bootstrap && modalEl) {
          window.bootstrap.Modal.getOrCreateInstance(modalEl).hide();
        }

        this.quickPost = {
          content: '',
          platforms: [],
          mediaFile: null,
          mediaPreview: null,
          mediaType: null,
          scheduleLater: false,
          scheduleAt: ''
        };

        this.pagination.currentPage = 1;
        this.fetchPosts();

        const message = data.message || 'Post published successfully!';
        const shareUrl = this.snapchatShareUrl(data.results);

        if (window.Swal) {
          if (shareUrl) {
            window.Swal.fire({
              title: 'Success!',
              icon: 'success',
              html: message +
                '<div class="mt-3 pt-2 border-top">' +
                  '<p class="text-muted small mb-2">Snapchat has no auto-publish API - share this post manually instead:</p>' +
                  '<a href="#" class="btn btn-sm btn-outline-dark snapchat-share-button" data-share-url="' + shareUrl + '">' +
                    '<i class="fab fa-snapchat-ghost"></i> Share to Snapchat' +
                  '</a>' +
                '</div>',
              didOpen: this.initSnapchatShareButtons
            });
          } else {
            window.Swal.fire('Success!', message, 'success');
          }
        } else {
          window.alert(message);
        }

        if (data.errors && data.errors.length) {
          const warning = this.quickPostErrorMessage(data);
          if (window.Swal) {
            window.Swal.fire('Partial success', warning, 'warning');
          } else {
            window.alert(warning);
          }
        }

      }).catch((error) => {

        const message = this.quickPostErrorMessage(error.response && error.response.data);

        if (window.Swal) {
          window.Swal.fire('Error!', message, 'error');
        } else {
          window.alert(message);
        }

      }).finally(() => {

        this.quickPostSubmitting = false;

      });

    }

  },

  watch:{

    activePlatform(){

      this.pagination.currentPage = 1;
      this.fetchPosts();

    },

    'filters.search'(){

      this.pagination.currentPage = 1;

      clearTimeout(this.searchDebounce);
      this.searchDebounce = setTimeout(() => {
        this.fetchPosts();
      }, 400);

    },

    'filters.status'(){

      this.pagination.currentPage = 1;
      this.fetchPosts();

    },

    'filters.sort'(){

      this.pagination.currentPage = 1;
      this.fetchPosts();

    }

  }

}
</script>
<style scoped>

.posts-dashboard{

  background:#F6F9FC;

  min-height:100vh;

  padding:30px;

}

.dashboard-header{

  background:white;

  border-radius:20px;

  padding:35px;

  display:flex;

  justify-content:space-between;

  align-items:center;

  margin-bottom:30px;

  box-shadow:0 8px 30px rgba(0,0,0,.05);

}

.page-badge{

  display:inline-block;

  background:#ECF2FF;

  color:#5D87FF;

  padding:8px 16px;

  border-radius:50px;

  font-size:13px;

  font-weight:600;

  margin-bottom:18px;

}

.page-title{

  font-size:34px;

  font-weight:700;

  color:#2A3547;

  margin-bottom:10px;

}

.page-subtitle{

  color:#7C8FAC;

  font-size:15px;

  margin:0;

}

.header-actions{

  display:flex;

  gap:12px;

}

.btn-create{

  border-radius:12px;

  padding:12px 28px;

  font-weight:600;

}

.btn{

  border-radius:12px;

  padding:12px 24px;

}
/* =====================================
   Platform Cards
===================================== */

.platform-grid{

  display:grid;

  grid-template-columns:repeat(auto-fill,minmax(280px,1fr));

  gap:24px;

  margin-bottom:35px;

}

.platform-card{

  background:#fff;

  border-radius:18px;

  padding:25px;

  transition:.3s;

  box-shadow:0 10px 30px rgba(0,0,0,.05);

  border:2px solid transparent;

}

.platform-card:hover{

  transform:translateY(-8px);

  box-shadow:0 20px 45px rgba(0,0,0,.08);

}

.platform-header{

  display:flex;

  justify-content:space-between;

  align-items:center;

  margin-bottom:25px;

}

.platform-icon{

  width:60px;

  height:60px;

  border-radius:16px;

  display:flex;

  align-items:center;

  justify-content:center;

  color:white;

  font-size:26px;

}

.connection-status{

  background:#ECFDF3;

  color:#16A34A;

  padding:5px 12px;

  border-radius:30px;

  font-size:12px;

  font-weight:600;

}

.platform-card h4{

  font-size:20px;

  color:#2A3547;

  margin-bottom:8px;

}

.platform-card h2{

  font-size:38px;

  font-weight:700;

  margin-bottom:5px;

  color:#111827;

}

.platform-card p{

  color:#7C8FAC;

  margin-bottom:25px;

}

.platform-footer{

  display:flex;

  justify-content:space-between;

  border-top:1px solid #EEF2F7;

  padding-top:18px;

  font-size:14px;

  font-weight:600;

}

.facebook .platform-icon{

  background:#1877F2;

}

.instagram .platform-icon{

  background:linear-gradient(135deg,#F58529,#DD2A7B,#8134AF);

}

.twitter .platform-icon{

  background:#000;

}

.linkedin .platform-icon{

  background:#0A66C2;

}

.tiktok .platform-icon{

  background:#111827;

}

.youtube .platform-icon{

  background:#FF0000;

}

/* ========================================
   Toolbar
======================================== */

.toolbar{

  background:#fff;

  border-radius:18px;

  padding:20px 25px;

  display:flex;

  justify-content:space-between;

  align-items:center;

  margin-bottom:30px;

  box-shadow:0 8px 25px rgba(0,0,0,.05);

}

.toolbar-left{

  flex:1;

}

.toolbar-right{

  display:flex;

  gap:12px;

  align-items:center;

}

.search-box{

  width:380px;

  max-width:100%;

  position:relative;

}

.search-box i{

  position:absolute;

  left:18px;

  top:50%;

  transform:translateY(-50%);

  color:#94A3B8;

}

.search-box input{

  width:100%;

  height:48px;

  padding-left:48px;

  border:1px solid #E5E7EB;

  border-radius:12px;

  outline:none;

  transition:.3s;

  background:#F8FAFC;

}

.search-box input:focus{

  border-color:#5D87FF;

  background:#fff;

}

.modern-select{

  height:48px;

  border:1px solid #E5E7EB;

  border-radius:12px;

  padding:0 16px;

  background:#fff;

  min-width:160px;

  outline:none;

}

.modern-btn{

  width:48px;

  height:48px;

  border-radius:12px;

  display:flex;

  justify-content:center;

  align-items:center;

}

.modern-btn.active{

  background:#5D87FF;

  color:white;

}


/* ==========================
   Posts
========================== */

.section-title{

  display:flex;

  justify-content:space-between;

  align-items:center;

  margin-bottom:20px;

}

.section-title h3{

  font-size:28px;

  color:#2A3547;

  margin:0;

}

.section-title p{

  color:#7C8FAC;

}

.posts-grid{

  display:grid;

  grid-template-columns:repeat(auto-fill,minmax(370px,1fr));

  gap:28px;

}

.posts-grid.list{

  display:flex;

  flex-direction:column;

  gap:18px;

}

.posts-grid.loading{

  opacity:0.5;

  pointer-events:none;

  transition:opacity 0.15s ease;

}

.posts-empty-state{

  padding:60px 20px;

  text-align:center;

  color:#8897AA;

  background:#fff;

  border-radius:12px;

}

.posts-grid.list .post-card{

  display:flex;

  flex-direction:row;

}

.posts-grid.list .post-image{

  width:260px;

  height:170px;

  flex-shrink:0;

}

.posts-grid.list .post-body{

  flex:1;

  display:flex;

  flex-direction:column;

  justify-content:center;

}

.posts-grid.list .post-body p{

  min-height:0;

}

.posts-grid.list .post-stats{

  margin:14px 0;

}

@media(max-width:768px){

  .posts-grid.list .post-card{

    flex-direction:column;

  }

  .posts-grid.list .post-image{

    width:100%;

    height:220px;

  }

}

.post-card{

  background:white;

  border-radius:18px;

  overflow:hidden;

  box-shadow:0 10px 25px rgba(0,0,0,.05);

  transition:.3s;

}

.post-card:hover{

  transform:translateY(-8px);

  box-shadow:0 20px 40px rgba(0,0,0,.10);

}

.post-image{

  position:relative;

  height:220px;

}

.post-image img{

  width:100%;

  height:100%;

  object-fit:cover;

}

.status-badge{

  position:absolute;

  top:18px;

  right:18px;

  padding:7px 16px;

  border-radius:30px;

  color:white;

  font-size:12px;

  font-weight:600;

}

.status-badge.published{

  background:#13DEB9;

}

.status-badge.scheduled{

  background:#FFAE1F;

}

.status-badge.failed{

  background:#FA896B;

}

.post-body{

  padding:22px;

}

.post-platform{

  display:flex;

  justify-content:space-between;

  margin-bottom:15px;

  font-weight:600;

}

.post-body h4{

  font-size:22px;

  margin-bottom:12px;

  color:#2A3547;

}

.post-body p{

  color:#7C8FAC;

  line-height:1.7;

  min-height:75px;

}

.post-stats{

  display:flex;

  justify-content:space-between;

  margin:22px 0;

  font-size:14px;

  color:#5D87FF;

}

.post-footer{

  border-top:1px solid #EDF2F7;

  padding-top:16px;

  display:flex;

  justify-content:space-between;

  color:#94A3B8;

  font-size:14px;

}

.platform-tabs {
  display: flex;
  gap: 16px;
  overflow-x: auto;
  padding-bottom: 10px;
  margin-bottom: 30px;
}

.platform-tab {
  min-width: 170px;
  background: #fff;
  border-radius: 14px;
  padding: 16px 20px;
  display: flex;
  align-items: center;
  cursor: pointer;
  transition: .25s;
  box-shadow: 0 5px 18px rgba(0,0,0,.05);
}

.platform-tab:hover {
  transform: translateY(-3px);
}

.platform-tab.active {
  background: #5D87FF;
  color: #fff;
}

.platform-tab-icon {
  width: 44px;
  height: 44px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 20px;
  margin-right: 15px;
  flex-shrink: 0;
  transition: .25s;
}


.platform-info {
  display: flex;
  flex-direction: column;
}

.platform-info strong {
  font-size: 15px;
}

.platform-info small {
  opacity: .8;
}

.post-image img{

  transition:.4s;

}

.post-card:hover img{

  transform:scale(1.08);

}

/* ==========================
   Pagination
========================== */

.pagination-wrapper{

  margin-top:40px;

  display:flex;

  justify-content:space-between;

  align-items:center;

}

.pagination-info{

  color:#7C8FAC;

  font-size:15px;

}

.pagination{

  display:flex;

  gap:10px;

}

.page-btn{

  width:42px;

  height:42px;

  border:none;

  background:#fff;

  border-radius:10px;

  cursor:pointer;

  transition:.25s;

  box-shadow:0 5px 15px rgba(0,0,0,.05);

}

.page-btn:hover{

  background:#5D87FF;

  color:#fff;

}

.page-btn.active{

  background:#5D87FF;

  color:#fff;

}

.page-btn:disabled{

  opacity:.45;

  cursor:not-allowed;

}

.post-image video{

  width:100%;

  height:100%;

  object-fit:cover;

}

.media-type{

  position:absolute;

  left:18px;

  top:18px;

  width:42px;

  height:42px;

  border-radius:50%;

  background:rgba(0,0,0,.65);

  display:flex;

  align-items:center;

  justify-content:center;

  color:#fff;

  font-size:18px;

  backdrop-filter:blur(8px);

}

.video-wrapper{
  position:relative;
  width:100%;
  height:100%;
  overflow:hidden;
}

.video-wrapper video{
  width:100%;
  height:100%;
  object-fit:cover;
}

.play-button{

  position:absolute;

  left:50%;
  top:50%;

  transform:translate(-50%,-50%);

  width:72px;
  height:72px;

  border-radius:50%;

  background:rgba(255,255,255,.95);

  display:flex;
  justify-content:center;
  align-items:center;

  font-size:30px;

  color:#5D87FF;

  box-shadow:0 12px 35px rgba(0,0,0,.25);

  transition:.3s;
}

.post-card:hover .play-button{

  transform:translate(-50%,-50%) scale(1.15);

}

.play-button.reel{

  background:#E1306C;
  color:#fff;

}

.platform-icons{
  display:flex;
  align-items:center;
  flex-wrap:wrap;
  gap:6px;
}

.platform-account-pill{
  display:inline-flex;
  align-items:center;
  gap:8px;
  padding:6px 14px 6px 6px;
  border-radius:30px;
  border:1px solid #E5E7EB;
  text-decoration:none;
  transition:.2s;
  max-width:190px;
}

.platform-account-pill:hover{
  border-color:#5D87FF;
  box-shadow:0 2px 8px rgba(93,135,255,.15);
  text-decoration:none;
}

.platform-account-name{
  font-size:13px;
  font-weight:600;
  color:#2A3547;
  overflow:hidden;
  text-overflow:ellipsis;
  white-space:nowrap;
  max-width:140px;
}

/* ==========================
   Quick Composer
========================== */

.quick-composer{
  background:#fff;
  border-radius:16px;
  padding:16px 20px;
  display:flex;
  align-items:center;
  gap:14px;
  box-shadow:0 8px 25px rgba(0,0,0,.05);
  margin-bottom:24px;
  cursor:pointer;
  transition:.25s;
}

.quick-composer:hover{
  box-shadow:0 12px 30px rgba(0,0,0,.08);
}

.composer-avatar{
  width:44px;
  height:44px;
  border-radius:50%;
  background:#5D87FF;
  color:#fff;
  display:flex;
  align-items:center;
  justify-content:center;
  font-weight:700;
  flex-shrink:0;
}

.composer-input{
  flex:1;
  background:#F1F5F9;
  border-radius:30px;
  padding:12px 20px;
  color:#7C8FAC;
}

.composer-icons{
  display:flex;
  gap:16px;
  font-size:20px;
}

.text-only-card{
  height:100%;
  width:100%;
  display:flex;
  align-items:center;
  justify-content:center;
  text-align:center;
  padding:24px;
  background:linear-gradient(135deg,#5D87FF,#4f46e5);
  color:#fff;
  font-size:18px;
  font-weight:600;
  line-height:1.5;
}

/* ==========================
   Quick Create Modal
========================== */

.quick-create-modal{
  border-radius:18px;
  overflow:hidden;
  border:none;
}

.composer-user-row{
  display:flex;
  align-items:center;
  gap:12px;
  margin-bottom:16px;
}

.composer-audience{
  font-size:13px;
  color:#7C8FAC;
}

.quick-textarea{
  width:100%;
  border:none;
  outline:none;
  resize:none;
  font-size:18px;
  color:#2A3547;
}

.quick-media-preview{
  position:relative;
  margin-top:10px;
  border-radius:12px;
  overflow:hidden;
  max-height:260px;
}

.quick-media-preview img,
.quick-media-preview video{
  width:100%;
  max-height:260px;
  object-fit:cover;
}

.remove-media-btn{
  position:absolute;
  top:10px;
  right:10px;
  width:32px;
  height:32px;
  border-radius:50%;
  border:none;
  background:rgba(0,0,0,.6);
  color:#fff;
  display:flex;
  align-items:center;
  justify-content:center;
}

.quick-platform-label{
  margin-top:18px;
  margin-bottom:10px;
  font-weight:600;
  color:#2A3547;
  font-size:14px;
}

.quick-platform-select{
  display:flex;
  flex-wrap:wrap;
  gap:10px;
}

.quick-account-chip{
  display:flex;
  align-items:center;
  gap:8px;
  padding:6px 14px 6px 6px;
  border-radius:30px;
  border:1px solid #E5E7EB;
  cursor:pointer;
  font-size:13px;
  font-weight:600;
  color:#2A3547;
  transition:.2s;
}

.quick-account-chip.active{
  background:#5D87FF;
  border-color:#5D87FF;
  color:#fff;
}

.quick-account-name{
  max-width:140px;
  overflow:hidden;
  text-overflow:ellipsis;
  white-space:nowrap;
}

.quick-schedule-row{
  display:flex;
  align-items:center;
  justify-content:space-between;
  gap:12px;
  margin-top:18px;
}

.quick-checkbox{
  display:flex;
  align-items:center;
  gap:8px;
  font-size:14px;
  color:#2A3547;
  margin:0;
}

.add-to-post-row{
  margin-top:18px;
  border:1px solid #E5E7EB;
  border-radius:12px;
  padding:12px 18px;
  display:flex;
  align-items:center;
  justify-content:space-between;
  font-weight:600;
  font-size:14px;
  color:#2A3547;
}

.add-to-post-icons{
  display:flex;
  gap:16px;
  font-size:20px;
  align-items:center;
}

.media-upload-btn{
  cursor:pointer;
  display:flex;
  margin:0;
}
</style>