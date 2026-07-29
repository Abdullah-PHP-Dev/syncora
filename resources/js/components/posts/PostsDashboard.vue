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

        <i
            :class="platform.icon"
            :style="{ color: activePlatform === platform.name ? '#fff' : platform.color }">
        </i>

        <div class="platform-info">
          <strong>{{ platform.name }}</strong>
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
            class="modern-btn"
            :class="{active: gridView}"
            title="Grid view"
            @click="gridView = true">
          <i class="fas fa-th-large"></i>
        </button>

        <button
            class="modern-btn"
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

        <p>{{ filteredPosts.length }} Posts Available</p>

      </div>

    </div>

    <div
        class="posts-grid"
        :class="{list:!gridView}">

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
                  class="platform-avatar"
                  :href="previewUrl(post, platform)"
                  :title="'View on ' + platform.name">

                <i
                    :class="platform.icon"
                    :style="{color:platform.color}">
                </i>

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
            @click="pagination.currentPage--">

          <i class="fas fa-chevron-left"></i>

        </button>

        <button

            v-for="page in totalPages"

            :key="page"

            class="page-btn"

            :class="{active:page===pagination.currentPage}"

            @click="pagination.currentPage=page">

          {{ page }}

        </button>

        <button

            class="page-btn"

            :disabled="pagination.currentPage===totalPages"

            @click="pagination.currentPage++">

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
                  v-for="platform in platformOptions"
                  :key="platform.key"
                  class="quick-platform-chip"
                  :class="{active: quickPost.platforms.includes(platform.key)}"
                  @click="toggleQuickPlatform(platform.key)">

                <i
                    :class="platform.icon"
                    :style="{color: quickPost.platforms.includes(platform.key) ? '#fff' : platform.color}">
                </i>

                {{ platform.name }}

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

              {{ quickPost.scheduleLater ? 'Schedule Post' : 'Post' }}

            </button>

          </div>

        </div>

      </div>

    </div>

  </div>
</template>

<script>
import { mockPosts, platformMeta, platformOrder } from '../../data/mockPosts';

export default {

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

    userName: {
      type: String,
      default: 'Admin'
    }

  },

  data(){

    return{
      pagination: {

        currentPage: 1,

        perPage: 6

      },
      activePlatform: 'All',
      posts: mockPosts,
      filters:{

        search:'',
        status:'',
        sort:'latest'

      },

      gridView:true,
      platforms: [
        { name: 'All', icon: 'fas fa-globe', color: '#5D87FF' },
        ...platformOrder.map(key => ({
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
      }

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

    canSubmitQuickPost() {

      return (this.quickPost.content.trim().length > 0 || this.quickPost.mediaPreview)
          && this.quickPost.platforms.length > 0;

    },

    totalPages() {

      return Math.ceil(
          this.filteredPosts.length / this.pagination.perPage
      );

    },

    paginatedPosts() {

      const start =
          (this.pagination.currentPage - 1) *
          this.pagination.perPage;

      return this.filteredPosts.slice(
          start,
          start + this.pagination.perPage
      );

    },

    pageInfo() {

      if (!this.filteredPosts.length) {

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

          this.filteredPosts.length

      );

      return {

        from,

        to,

        total:this.filteredPosts.length

      };

    },

    platformTabs() {

      return this.platforms.map(platform => ({

        ...platform,

        count: platform.name === 'All'
            ? this.posts.length
            : this.posts.filter(post =>
                post.platforms.some(p => p.name === platform.name)
              ).length

      }));

    },

    filteredPosts() {

      let posts = this.posts;

      if (this.activePlatform !== 'All') {
        posts = posts.filter(post =>
            post.platforms.some(p => p.name === this.activePlatform)
        );
      }

      if (this.filters.search) {

        const keyword = this.filters.search.toLowerCase();

        posts = posts.filter(post =>
            post.title.toLowerCase().includes(keyword) ||
            post.content.toLowerCase().includes(keyword)
        );

      }

      if (this.filters.status) {

        posts = posts.filter(post => post.status === this.filters.status);

      }

      posts = [...posts].sort((a, b) => {

        const diff = new Date(a.created_at) - new Date(b.created_at);

        return this.filters.sort === 'oldest' ? diff : -diff;

      });

      return posts;

    }

  },
  methods: {

    createPost() {

      window.location.href = this.createUrl;

    },

    previewUrl(post, platform) {

      return `${this.previewUrlBase}/${post.id}/preview/${platform.key}`;

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

    submitQuickPost() {

      if (!this.canSubmitQuickPost) return;

      const nextId = Math.max(...this.posts.map(p => p.id)) + 1;

      const newPost = {
        id: nextId,
        type: this.quickPost.mediaType === 'video' ? 'video' : (this.quickPost.mediaPreview ? 'image' : 'text'),
        platformKeys: [...this.quickPost.platforms],
        platforms: this.quickPost.platforms.map(key => platformMeta[key]),
        status: this.quickPost.scheduleLater ? 'Scheduled' : 'Published',
        title: this.quickPost.content.split('\n')[0].slice(0, 60) || 'New Post',
        content: this.quickPost.content,
        image: this.quickPost.mediaType === 'image' ? this.quickPost.mediaPreview : null,
        thumbnail: this.quickPost.mediaType === 'video' ? this.quickPost.mediaPreview : null,
        video: this.quickPost.mediaType === 'video' ? this.quickPost.mediaPreview : null,
        likes: 0,
        comments: 0,
        shares: 0,
        views: 0,
        author: this.userName,
        created_at: this.quickPost.scheduleLater && this.quickPost.scheduleAt
            ? this.quickPost.scheduleAt
            : new Date().toISOString().slice(0, 10)
      };

      this.posts.unshift(newPost);

      this.quickPost = {
        content: '',
        platforms: [],
        mediaFile: null,
        mediaPreview: null,
        mediaType: null,
        scheduleLater: false,
        scheduleAt: ''
      };

      const modalEl = this.$refs.quickCreateModal;

      if (window.bootstrap && modalEl) {
        window.bootstrap.Modal.getOrCreateInstance(modalEl).hide();
      }

    }

  },

  watch:{

    activePlatform(){

      this.pagination.currentPage = 1;

    },

    'filters.search'(){

      this.pagination.currentPage = 1;

    },

    'filters.status'(){

      this.pagination.currentPage = 1;

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

.post-platform i:first-child{

  margin-right:8px;

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

.platform-tab i {
  font-size: 26px;
  margin-right: 15px;
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
  gap:8px;
}

.platform-avatar{
  width:34px;
  height:34px;
  border-radius:50%;
  background:#F5F7FA;
  display:flex;
  align-items:center;
  justify-content:center;
  border:1px solid #E5E7EB;
  transition:.25s;
}

.platform-avatar:hover{
  transform:translateY(-2px);
  background:#fff;
  box-shadow:0 5px 12px rgba(0,0,0,.08);
}

.platform-avatar i{
  font-size:15px;
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

.quick-platform-chip{
  display:flex;
  align-items:center;
  gap:8px;
  padding:8px 14px;
  border-radius:30px;
  border:1px solid #E5E7EB;
  cursor:pointer;
  font-size:13px;
  font-weight:600;
  color:#2A3547;
  transition:.2s;
}

.quick-platform-chip.active{
  background:#5D87FF;
  border-color:#5D87FF;
  color:#fff;
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