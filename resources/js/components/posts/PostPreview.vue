<template>
  <div class="pp">

    <div v-if="!post" class="pp-card pp-not-found">

      <span class="pp-empty-icon"><i class="fas fa-ghost"></i></span>
      <h3>We couldn't find that post</h3>
      <p>It may have been deleted, or it belongs to another account.</p>
      <a :href="backUrl" class="pp-btn pp-btn-primary"><i class="fas fa-arrow-left"></i> Back to Posts</a>

    </div>

    <template v-else>

      <div class="pp-grid">

        <!-- ============ Left: header + the post as it appears ============ -->
        <div class="pp-main">

          <header class="pp-card pp-hero">

            <div class="pp-hero-text">
              <div class="pp-crumbs">
                <a :href="backUrl" class="pp-crumb-back" title="Back to Posts"><i class="fas fa-arrow-left"></i></a>
                <a :href="backUrl">Posts</a>
                <i class="fas fa-chevron-right"></i>
                <span>Preview</span>
              </div>

              <h1 class="pp-title">{{ post.title }}</h1>

              <div class="pp-chips">
                <span class="pp-badge" :class="'is-' + activeStatus.tone">{{ activeStatus.label }}</span>
                <span class="pp-chip" v-if="activeMember.scheduled_label"><i class="far fa-clock"></i> {{ activeMember.scheduled_label }}</span>
                <span class="pp-chip" v-else-if="activeMember.created_label"><i class="far fa-calendar"></i> {{ activeMember.created_label }}</span>
                <span class="pp-chip"><i class="fas fa-layer-group"></i> {{ post.platforms.length }} {{ post.platforms.length === 1 ? 'platform' : 'platforms' }}</span>
              </div>
            </div>

            <div class="pp-hero-actions">
              <a v-if="hasPlatformUrl" :href="platformUrl" target="_blank" rel="noopener noreferrer" class="pp-btn pp-btn-ghost">
                <i class="fas fa-external-link-alt"></i> View on {{ activePlatform.name }}
              </a>
              <a v-if="duplicateUrl" :href="duplicateUrl.replace('__POST__', activePlatform.post_id || post.id)" class="pp-btn pp-btn-ghost">
                <i class="far fa-copy"></i> Duplicate
              </a>
              <div ref="menu" class="pp-menu">
                <button type="button" class="pp-btn pp-btn-ghost pp-btn-icon" :aria-expanded="showMenu" aria-label="More actions" @click="showMenu = !showMenu">
                  <i class="fas fa-ellipsis-v"></i>
                </button>
                <div v-if="showMenu" class="pp-menu-list" role="menu">
                  <a :href="backUrl" role="menuitem"><i class="fas fa-th-large"></i> All posts</a>
                  <button type="button" role="menuitem" @click="copyCaption"><i class="far fa-clipboard"></i> {{ copied ? 'Caption copied' : 'Copy caption' }}</button>
                </div>
              </div>
            </div>

          </header>

          <section class="pp-card pp-stage-card">

            <!-- Platform bar: the one on show, then the rest of the group -->
            <div class="pp-pf-bar">
              <div class="pp-pf-current" :style="brandVars(activeKey)">
                <span class="pp-pf-tile"><i :class="activePlatform.icon"></i></span>
                <div class="pp-pf-current-text">
                  <strong>{{ activePlatform.name }}</strong>
                  <span class="pp-live" :class="'is-' + activeStatus.tone">{{ activeStatus.tone === 'success' ? 'Live' : activeStatus.label }}</span>
                  <small v-if="repeatedKeys.includes(activeKey)">{{ activePlatform.handle || activePlatform.page }}</small>
                </div>
              </div>
              <div v-if="otherPlatforms.length" class="pp-pf-others" aria-label="Other platforms">
                <button
                    v-for="p in visibleOtherPlatforms"
                    :key="p.post_id"
                    type="button"
                    class="pp-pf-dot"
                    :style="brandVars(p.key)"
                    :title="p.name + (repeatedKeys.includes(p.key) ? ' · ' + (p.handle || p.page) : '') + ' - ' + statusInfo(p).label"
                    @click="switchPlatform(p)">
                  <i :class="p.icon"></i>
                </button>
                <button v-if="hiddenPlatformCount" type="button" class="pp-pf-dot pp-pf-more" @click="showAllPlatforms = true">+{{ hiddenPlatformCount }}</button>
              </div>
            </div>

            <div class="pp-stage" :style="brandVars(activeKey)">

            <div class="pp-device">

              <!-- Shared post header -->
              <div class="mk-header">
                <div class="mk-avatar" :class="'mk-avatar-' + activeKey" :style="brandVars(activeKey)">
                  <img v-if="activeMember.account_avatar" :src="activeMember.account_avatar" alt="" @error="activeMember.account_avatar = null">
                  <i v-else :class="activePlatform.icon"></i>
                </div>
                <div class="mk-identity">
                  <strong>
                    {{ activePlatform.page }}
                    <i v-if="activeKey === 'x'" class="fas fa-check-circle mk-verified"></i>
                  </strong>
                  <small v-if="(activeKey === 'instagram' || activeKey === 'tiktok') && activePlatform.handle">{{ activePlatform.handle }}</small>
                  <small v-else-if="activeKey === 'x'"><template v-if="activePlatform.handle">{{ activePlatform.handle }} · </template>{{ shortDate }}</small>
                  <small v-else>{{ shortDate }} · <i class="fas fa-globe-americas"></i></small>
                </div>
                <i class="fas fa-ellipsis-h mk-more"></i>
              </div>

              <!-- Caption above media (Facebook / X / LinkedIn) -->
              <div v-if="captionFirst" class="mk-text">{{ post.content }}</div>

              <!-- Media / carousel -->
              <div v-if="mediaList.length" class="mk-media" :class="{'is-square': activeKey === 'instagram', 'is-rounded': activeKey === 'x'}">
                <template v-for="(m, i) in mediaList" :key="i">
                  <img v-if="i === mediaIndex && m.type === 'image'" :src="m.url" alt="">
                  <video v-else-if="i === mediaIndex" :src="m.url" :poster="m.poster || undefined" controls playsinline></video>
                </template>
                <template v-if="mediaList.length > 1">
                  <button type="button" class="mk-nav mk-prev" :disabled="mediaIndex === 0" @click="mediaIndex--" aria-label="Previous"><i class="fas fa-chevron-left"></i></button>
                  <button type="button" class="mk-nav mk-next" :disabled="mediaIndex === mediaList.length - 1" @click="mediaIndex++" aria-label="Next"><i class="fas fa-chevron-right"></i></button>
                  <span class="mk-counter">{{ mediaIndex + 1 }}/{{ mediaList.length }}</span>
                </template>
              </div>
              <div v-if="mediaList.length > 1" class="mk-dots">
                <span v-for="(m, i) in mediaList" :key="i" :class="{active: i === mediaIndex}" @click="mediaIndex = i"></span>
              </div>

              <!-- Instagram -->
              <template v-if="activeKey === 'instagram'">
                <div class="mk-ig-icons">
                  <i class="far fa-heart"></i>
                  <i class="far fa-comment comments-toggle" @click="openComments"></i>
                  <i class="far fa-paper-plane"></i>
                  <i class="far fa-bookmark mk-push"></i>
                </div>
                <div class="mk-likes">{{ fmt(engagement.reactionsTotal) }} likes</div>
                <div class="mk-text"><strong>{{ activePlatform.page }}</strong> {{ post.content }}</div>
                <div class="mk-link comments-toggle" @click="openComments">
                  {{ engagement.commentsCount ? 'View all ' + engagement.commentsCount + ' comments' : 'Add a comment…' }}
                </div>
                <div class="mk-time">{{ shortDate }}</div>
              </template>

              <!-- X -->
              <div v-else-if="activeKey === 'x'" class="mk-actions mk-x-actions">
                <span class="comments-toggle" @click="openComments"><i class="far fa-comment"></i> {{ fmt(engagement.commentsCount) }}</span>
                <span><i class="fas fa-retweet"></i> {{ fmt(engagement.sharesCount) }}</span>
                <span><i class="far fa-heart"></i> {{ fmt(engagement.reactionsTotal) }}</span>
                <span><i class="far fa-chart-bar"></i> {{ fmt(engagement.viewsCount) }}</span>
                <span><i class="far fa-bookmark"></i></span>
              </div>

              <!-- Facebook / LinkedIn -->
              <template v-else-if="activeKey === 'facebook' || activeKey === 'linkedin'">
                <div class="mk-reactions">
                  <span class="mk-reaction-summary">
                    <span class="mk-reaction-stack">
                      <i v-for="kind in engagement.reactions.slice(0, 3)" :key="kind.key" :class="kind.icon" :style="{color: kind.color}"></i>
                    </span>
                    {{ fmt(engagement.reactionsTotal) }}
                  </span>
                  <span>
                    <span class="comments-toggle" @click="openComments">{{ engagement.commentsCount }} comments</span>
                    · {{ engagement.sharesCount }} {{ activeKey === 'linkedin' ? 'reposts' : 'shares' }}
                  </span>
                </div>
                <div class="mk-actions">
                  <span><i class="far fa-thumbs-up"></i> Like</span>
                  <span class="comments-toggle" @click="openComments"><i class="far fa-comment"></i> Comment</span>
                  <span><i class="fas fa-share"></i> {{ activeKey === 'linkedin' ? 'Repost' : 'Share' }}</span>
                  <span v-if="activeKey === 'linkedin'"><i class="far fa-paper-plane"></i> Send</span>
                </div>
              </template>

              <!-- TikTok / YouTube / others -->
              <template v-else>
                <div class="mk-text">{{ post.content }}</div>
                <div class="mk-actions">
                  <span><i class="fas fa-heart"></i> {{ fmt(engagement.reactionsTotal) }}</span>
                  <span class="comments-toggle" @click="openComments"><i class="fas fa-comment-dots"></i> {{ fmt(engagement.commentsCount) }}</span>
                  <span><i class="fas fa-share"></i> {{ fmt(engagement.sharesCount) }}</span>
                  <span><i class="fas fa-play"></i> {{ fmt(engagement.viewsCount) }}</span>
                </div>
              </template>

            </div>

            </div>

            <div class="pp-kpis">
              <div v-for="kpi in kpis" :key="kpi.label" class="pp-kpi" :style="{'--tone': kpi.color}">
                <i :class="kpi.icon"></i>
                <strong>{{ fmt(kpi.value) }}</strong>
                <small>{{ kpi.label }}</small>
              </div>
            </div>

            <div v-if="activeMember.error_message" class="pp-alert">
              <i class="fas fa-exclamation-circle"></i>
              <div>
                <strong>Publishing to {{ activePlatform.name }} failed</strong>
                <span>{{ activeMember.error_message }}</span>
              </div>
            </div>

          </section>

        </div>

        <!-- ============ Right: where it went + the conversation ============ -->
        <div class="pp-side">

          <section class="pp-card">
            <div class="pp-card-head">
              <div>
                <h2>Published to</h2>
                <p>The same post across your connected accounts</p>
              </div>
              <a :href="backUrl" class="pp-btn pp-btn-ghost pp-btn-sm"><i class="far fa-eye"></i> View all</a>
            </div>
            <div class="pp-dist">
              <div
                  v-for="p in post.platforms"
                  :key="p.post_id"
                  class="pp-dist-item"
                  :class="{active: p.post_id === activeId}"
                  :style="brandVars(p.key)"
                  role="button"
                  tabindex="0"
                  @click="switchPlatform(p)"
                  @keydown.enter="switchPlatform(p)">
                <span class="pp-dist-logo"><i :class="p.icon"></i></span>
                <span class="pp-dist-info">
                  <strong>{{ p.name }}</strong>
                  <small>{{ p.handle ? (p.handle.startsWith('@') ? p.handle : '@' + p.handle) : p.page }}</small>
                </span>
                <span class="pp-status" :class="'is-' + statusInfo(p).tone">
                  <i class="fas" :class="statusIcon(statusInfo(p).tone)"></i> {{ statusInfo(p).label }}
                </span>
                <div class="pp-menu" @click.stop>
                  <button type="button" class="pp-kebab" :aria-expanded="openMenu === 'p' + p.post_id" :aria-label="'Actions for ' + p.name" @click="toggleMenu('p' + p.post_id)">
                    <i class="fas fa-ellipsis-v"></i>
                  </button>
                  <div v-if="openMenu === 'p' + p.post_id" class="pp-menu-list" role="menu">
                    <button type="button" role="menuitem" @click="switchPlatform(p); openMenu = null"><i class="far fa-eye"></i> Preview here</button>
                    <a v-if="post.platformUrls[p.post_id] && post.platformUrls[p.post_id] !== '#'" :href="post.platformUrls[p.post_id]" target="_blank" rel="noopener noreferrer" role="menuitem"><i class="fas fa-external-link-alt"></i> Open on {{ p.name }}</a>
                    <a v-if="duplicateUrl" :href="duplicateUrl.replace('__POST__', p.post_id)" role="menuitem"><i class="far fa-copy"></i> Duplicate</a>
                  </div>
                </div>
              </div>
            </div>
          </section>

          <!-- Comments & replies for the active platform -->
          <section class="pp-card pp-comments" id="comments">

            <div class="pp-card-head">
              <div>
                <h2>Comments <span class="pp-count">{{ engagement.comments.length }}</span></h2>
                <p>Conversation on {{ activePlatform.name }}</p>
              </div>
              <button v-if="engagement.comments.length > commentPreviewCount" type="button" class="pp-btn pp-btn-ghost pp-btn-sm" @click="showAllComments = !showAllComments">
                <i class="far fa-eye"></i> {{ showAllComments ? 'Show fewer' : 'View all comments' }}
              </button>
            </div>

            <div v-if="!engagement.comments.length" class="pp-empty">
              <span class="pp-empty-icon"><i class="far fa-comments"></i></span>
              <strong>No comments yet</strong>
              <span>Comments from {{ activePlatform.name }} will appear here. Start the conversation below.</span>
            </div>

            <div v-else class="pp-thread-list">

                <div v-for="comment in visibleComments" :key="comment.id" class="pp-thread">

                  <div class="pp-comment" :class="{own: comment.isOwn}">

                    <div class="pp-avatar" :style="{background: comment.avatarColor}">
                      <img v-if="comment.avatar" :src="comment.avatar" alt="" @error="comment.avatar = null">
                      <template v-else>{{ initials(comment.author) }}</template>
                    </div>

                    <div class="pp-comment-body">

                      <div class="pp-comment-head">
                        <strong class="pp-comment-author">{{ comment.author }} <span v-if="comment.isOwn" class="pp-you">You</span></strong>
                        <span v-if="comment.sentiment" class="pp-sentiment" :class="'is-' + comment.sentiment">
                          <i class="far" :class="{ positive: 'fa-check-circle', negative: 'fa-times-circle', neutral: 'fa-dot-circle' }[comment.sentiment] || 'fa-dot-circle'"></i>
                          {{ comment.sentiment.charAt(0).toUpperCase() + comment.sentiment.slice(1) }}
                        </span>
                        <div class="pp-menu" @click.stop>
                          <button type="button" class="pp-kebab pp-kebab-sm" :aria-expanded="openMenu === 'c' + comment.id" aria-label="Comment actions" @click="toggleMenu('c' + comment.id)">
                            <i class="fas fa-ellipsis-v"></i>
                          </button>
                          <div v-if="openMenu === 'c' + comment.id" class="pp-menu-list" role="menu">
                            <button type="button" role="menuitem" @click="toggleReply(comment.id); openMenu = null"><i class="fas fa-reply"></i> Reply</button>
                            <button type="button" role="menuitem" @click="copyText(comment.content)"><i class="far fa-clipboard"></i> Copy text</button>
                          </div>
                        </div>
                      </div>

                      <p class="pp-comment-text">{{ comment.content }}</p>

                      <div class="pp-meta">
                        <span>{{ comment.timeAgo }}</span>
                        <span v-if="comment.likes">{{ comment.likes }} {{ comment.likes === 1 ? 'like' : 'likes' }}</span>
                        <button type="button" class="pp-link" @click="toggleReply(comment.id)">Reply</button>
                      </div>

                      <div v-if="comment.replies.length" class="pp-replies">
                        <div v-for="(reply, idx) in comment.replies" :key="idx" class="pp-comment is-reply" :class="{own: reply.isOwn}">
                          <div class="pp-avatar" :style="{background: reply.avatarColor}">
                            <img v-if="reply.avatar" :src="reply.avatar" alt="" @error="reply.avatar = null">
                            <template v-else>{{ initials(reply.author) }}</template>
                          </div>
                          <div class="pp-comment-body">
                            <div class="pp-bubble">
                              <strong>{{ reply.author }} <span v-if="reply.isOwn" class="pp-you">You</span></strong>
                              <div>{{ reply.content }}</div>
                            </div>
                            <div class="pp-meta">
                              <span>{{ reply.timeAgo }}</span>
                              <span v-if="reply.likes"><i class="fas fa-heart"></i> {{ reply.likes }}</span>
                            </div>
                          </div>
                        </div>
                      </div>

                      <div v-if="replyingToId === comment.id" class="pp-composer pp-composer-inline">
                        <div class="pp-avatar pp-avatar-own">{{ userInitials }}</div>
                        <div class="pp-input-row">
                          <input type="text" v-model="replyText" :placeholder="'Reply to ' + comment.author + '…'" @keyup.enter="addReply(comment)">
                          <button type="button" class="pp-send" :disabled="!replyText.trim() || submittingReply" @click="addReply(comment)">
                            <i class="fas" :class="submittingReply ? 'fa-spinner fa-spin' : 'fa-paper-plane'"></i>
                          </button>
                        </div>
                      </div>

                    </div>

                  </div>

                </div>

            </div>

            <div class="pp-composer">
              <div class="pp-avatar pp-avatar-own pp-avatar-account" :title="'Commenting as ' + activePlatform.page">
                <img v-if="activeMember.account_avatar" :src="activeMember.account_avatar" alt="" @error="activeMember.account_avatar = null">
                <template v-else>{{ initials(activePlatform.page || userName) }}</template>
              </div>
              <div class="pp-input-row">
                <input type="text" v-model="newCommentText" :placeholder="commentPlaceholder" @keyup.enter="addComment">
              </div>
              <button type="button" class="pp-send" :disabled="!newCommentText.trim() || submittingComment" @click="addComment" aria-label="Send comment">
                <i class="fas" :class="submittingComment ? 'fa-spinner fa-spin' : 'fa-paper-plane'"></i>
              </button>
            </div>

          </section>

        </div>

      </div>

    </template>

  </div>
</template>

<script>
import { platformMeta, reactionKindsByPlatform, brandVars } from '../../data/mockPosts';

const avatarPalette = ['#F59E0B', '#3B82F6', '#EC4899', '#10B981', '#8B5CF6', '#EF4444', '#14B8A6', '#6366F1'];

function colorForName(name) {

  const sum = (name || '').split('').reduce((sum, ch) => sum + ch.charCodeAt(0), 0);

  return avatarPalette[sum % avatarPalette.length];

}

export default {

  props: {

    postId: {
      type: [Number, String],
      required: true
    },

    platform: {
      type: String,
      default: ''
    },

    backUrl: {
      type: String,
      default: '/posts'
    },

    // posts.preview route with __POST__ / __PLATFORM__ placeholders.
    previewUrlTemplate: {
      type: String,
      default: '/posts/__POST__/preview/__PLATFORM__'
    },

    userName: {
      type: String,
      default: 'Admin'
    },

    initialPost: {
      type: Object,
      default: null
    },

    // Every Post row sharing this one's group_id (see PostController::
    // preview()'s docblock) - one entry per platform the same quick-post
    // submission was published to, each shaped exactly like initialPost.
    // Falls back to just [initialPost] when empty, so a single-platform
    // post (or an older cached view without this prop) still works.
    groupPosts: {
      type: Array,
      default: () => []
    },

    // Composer URL with __POST__ for "Duplicate" (empty hides the button).
    duplicateUrl: {
      type: String,
      default: ''
    }

  },

  data() {

    return {
      post: null,
      showMenu: false,
      // Row / comment menus: 'p{postId}' or 'c{commentId}', one open at a time.
      openMenu: null,
      copied: false,
      showAllPlatforms: false,
      showAllComments: false,
      commentPreviewCount: 3,
      // The selected group member's Post id - a platform can appear more
      // than once (two Instagram accounts), so selection is per post.
      activeId: null,
      mediaIndex: 0,
      showComments: true,
      newCommentText: '',
      replyingToId: null,
      replyText: '',
      submittingReply: false,
      submittingComment: false
    };

  },

  created() {

    this.post = this.buildPost(this.initialPost, this.groupPosts);

    if (this.post) {

      const requested = this.post.platforms.find(p => Number(p.post_id) === Number(this.postId))
        || this.post.platforms.find(p => p.key === this.platform);

      this.activeId = (requested || this.post.platforms[0]).post_id;

    }

  },

  computed: {

    activePlatform() {

      if (!this.post) return {};

      return this.post.platforms.find(p => p.post_id === this.activeId) || {};

    },

    activeKey() {

      return this.activePlatform.key || '';

    },

    // The platform bar: every group member except the one on show.
    otherPlatforms() {

      return this.post ? this.post.platforms.filter(p => p.post_id !== this.activeId) : [];

    },

    visibleOtherPlatforms() {

      return this.showAllPlatforms ? this.otherPlatforms : this.otherPlatforms.slice(0, 6);

    },

    hiddenPlatformCount() {

      return this.otherPlatforms.length - this.visibleOtherPlatforms.length;

    },

    visibleComments() {

      const all = this.engagement.comments;

      return this.showAllComments ? all : all.slice(0, this.commentPreviewCount);

    },

    // Platforms with more than one account in this post - their tabs
    // name the account so the two can be told apart.
    repeatedKeys() {

      if (!this.post) return [];

      const keys = this.post.platforms.map(p => p.key);

      return keys.filter((key, i) => keys.indexOf(key) !== i);

    },

    // The raw per-platform Post row (status, dates, media, error).
    activeMember() {

      if (!this.post) return {};

      return this.post.members[this.activeId] || {};

    },

    activeStatus() {

      return this.statusInfo(this.activePlatform);

    },

    engagement() {

      if (!this.post) return null;

      return this.post.engagement[this.activeId];

    },

    platformUrl() {

      if (!this.post) return '#';

      return this.post.platformUrls[this.activeId] || '#';

    },

    hasPlatformUrl() {

      return this.platformUrl && this.platformUrl !== '#';

    },

    kpis() {

      const e = this.engagement;

      return [
        { label: 'Likes', value: e.reactionsTotal, icon: 'fas fa-heart', color: '#EF4444' },
        { label: 'Comments', value: e.commentsCount, icon: 'fas fa-comment', color: '#6D4AFF' },
        { label: 'Shares', value: e.sharesCount, icon: 'fas fa-share', color: '#10B981' },
        { label: 'Views', value: e.viewsCount, icon: 'fas fa-eye', color: '#3B82F6' },
        { label: 'Impressions', value: e.impressions, icon: 'fas fa-chart-line', color: '#F59E0B' },
        { label: 'Saves', value: e.bookmarks, icon: 'fas fa-bookmark', color: '#EC4899' }
      ];

    },

    // Carousel-aware media for the active platform's own Post row, falling
    // back to the summary's single image/video for older payloads.
    mediaList() {

      const media = this.activeMember.media;

      if (media && media.length) return media;

      if (this.post.video) return [{ type: 'video', url: this.post.video, poster: this.post.thumbnail }];

      if (this.post.image) return [{ type: 'image', url: this.post.image }];

      return [];

    },

    captionFirst() {

      return ['facebook', 'x', 'linkedin'].includes(this.activeKey);

    },

    typeLabel() {

      return { image: 'Image post', video: 'Video post', carousel: 'Carousel', text: 'Text post' }[this.post.type] || 'Post';

    },

    typeIcon() {

      return { image: 'far fa-image', video: 'fas fa-video', carousel: 'far fa-images', text: 'fas fa-align-left' }[this.post.type] || 'far fa-file';

    },

    shortDate() {

      const label = this.activeMember.scheduled_label || this.activeMember.created_label || this.post.created_at || '';

      return label.split(' · ')[0];

    },

    userInitials() {

      return this.initials(this.userName);

    },

    commentPlaceholder() {

      const map = {
        facebook: 'Write a comment...',
        instagram: 'Add a comment...',
        x: 'Post your reply',
        linkedin: 'Add a comment…',
        tiktok: 'Add comment...',
        youtube: 'Add a public comment...'
      };

      return map[this.activeKey] || 'Add a comment...';

    }

  },

  // Arriving from Engagement > Comments ("Reply") or a post's "View
  // comments" action (#comments): show the thread instead of the top of
  // the preview.
  mounted() {
    if (window.location.hash === '#comments') {
      this.showAllComments = true;
      this.openComments();
    }
    document.addEventListener('click', this.closeMenuOnOutsideClick);
  },

  beforeDestroy() {
    document.removeEventListener('click', this.closeMenuOnOutsideClick);
  },

  methods: {

    // groupPosts holds one raw post per platform the same quick-post
    // submission went to (empty for older/ungrouped posts, in which case
    // this just falls back to treating `raw` as a group of one - the
    // original single-platform behavior). platforms/engagement/
    // platformUrls end up keyed by member Post id so switchPlatform()
    // can flip activeKey with everything already loaded, no refetch.
    buildPost(raw, groupPosts) {

      if (!raw) return null;

      const mapComment = (comment) => ({
        ...comment,
        avatarColor: colorForName(comment.author),
        replies: (comment.replies || []).map(reply => ({
          ...reply,
          avatarColor: colorForName(reply.author)
        }))
      });

      const members = (groupPosts && groupPosts.length) ? groupPosts : [raw];

      const platforms = [];
      const engagement = {};
      const platformUrls = {};
      const membersByKey = {};

      members.forEach(member => {

        const key = member.platform_key;

        const meta = platformMeta[key] || {
          key,
          name: member.platform_key,
          icon: 'fas fa-share-alt',
          color: '#5D87FF'
        };

        platforms.push({
          ...meta,
          key,
          post_id: member.id,
          // Real account identity only - platformMeta's page/handle are
          // mock placeholders ("@yourbusiness") meant for the demo listing.
          page: member.account_name || meta.name,
          handle: member.account_handle || '',
          avatar: member.account_avatar || null,
          status: member.raw_status || (member.status || '').toLowerCase(),
          scheduled: !!member.scheduled_label
        });

        const kinds = reactionKindsByPlatform[key] || reactionKindsByPlatform.facebook;
        const total = member.engagement.reactionsTotal;
        const reactions = kinds.map((kind, i) => ({ ...kind, count: i === 0 ? total : 0 }));

        engagement[member.id] = {
          ...member.engagement,
          reactions,
          comments: (member.engagement.comments || []).map(mapComment)
        };

        platformUrls[member.id] = member.platform_url || '#';
        membersByKey[member.id] = member;

      });

      return {

        ...raw,

        platforms,

        engagement,

        platformUrls,

        members: membersByKey

      };

    },

    // Posts are queued as 'pending' and flip to 'completed'/'failed' once
    // published, so a pending post with a schedule is the "scheduled" one.
    brandVars,

    statusInfo(p) {

      const status = (p.status || '').toLowerCase();

      if (['completed', 'published'].includes(status)) return { label: 'Published', tone: 'success' };
      if (status === 'failed') return { label: 'Failed', tone: 'danger' };
      if (status === 'draft') return { label: 'Draft', tone: 'muted' };
      if (p.scheduled) return { label: 'Scheduled', tone: 'info' };

      return { label: 'Pending', tone: 'warning' };

    },

    statusIcon(tone) {

      return { success: 'fa-check', danger: 'fa-times', warning: 'fa-clock', info: 'fa-clock' }[tone] || 'fa-circle';

    },

    copyCaption() {

      const done = () => {
        this.copied = true;
        setTimeout(() => { this.copied = false; this.showMenu = false; }, 1200);
      };

      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(this.post.content || '').then(done).catch(() => {});
      }

    },

    closeMenuOnOutsideClick(event) {

      if (this.showMenu && this.$refs.menu && !this.$refs.menu.contains(event.target)) {
        this.showMenu = false;
      }

      if (this.openMenu && !event.target.closest('.pp-menu')) {
        this.openMenu = null;
      }

    },

    toggleMenu(key) {

      this.openMenu = this.openMenu === key ? null : key;

    },

    copyText(text) {

      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(text || '').catch(() => {});
      }

      this.openMenu = null;

    },

    switchPlatform(p) {

      this.activeId = p.post_id;
      this.mediaIndex = 0;
      this.showAllComments = false;
      this.replyingToId = null;

      if (window.history && window.history.replaceState) {

        // p.post_id is that platform's own Post row - reloading/sharing
        // this URL re-fetches the whole group regardless of which member's
        // id is in it, but pointing at the right one keeps the URL honest.
        window.history.replaceState(null, '', this.previewUrlTemplate
          .replace('__POST__', p.post_id || this.post.id)
          .replace('__PLATFORM__', p.key) + window.location.hash);

      }

    },

    openComments() {

      this.showComments = true;
      this.$nextTick(() => document.getElementById('comments')?.scrollIntoView({ behavior: 'smooth', block: 'start' }));

    },

    fmt(n) {

      n = Number(n) || 0;

      if (n >= 1e6) return (n / 1e6).toFixed(1).replace(/\.0$/, '') + 'M';
      if (n >= 1e4) return (n / 1e3).toFixed(1).replace(/\.0$/, '') + 'K';

      return n.toLocaleString();

    },

    initials(name) {

      return (name || '')
          .split(' ')
          .filter(Boolean)
          .slice(0, 2)
          .map(part => part[0].toUpperCase())
          .join('');

    },

    addComment() {

      const text = this.newCommentText.trim();

      if (!text || this.submittingComment) return;

      this.submittingComment = true;

      // The active platform's own Post row, so a comment typed on the
      // Facebook tab lands on the Facebook post, not the group's first one.
      const postId = this.activePlatform.post_id || this.post.id;
      const engagement = this.engagement;

      window.axios.post(`${this.backUrl}/${postId}/comments`, {
        content: text
      }).then(({ data }) => {

        engagement.comments.push({
          ...data.comment,
          avatarColor: colorForName(data.comment.author)
        });

        engagement.commentsCount++;
        this.newCommentText = '';

      }).catch((error) => {

        window.alert(error.response?.data?.message || 'Failed to post comment.');

      }).finally(() => {

        this.submittingComment = false;

      });

    },

    toggleReply(commentId) {

      this.replyingToId = this.replyingToId === commentId ? null : commentId;
      this.replyText = '';

    },

    addReply(comment) {

      const text = this.replyText.trim();

      if (!text || this.submittingReply) return;

      this.submittingReply = true;

      window.axios.post(`${this.backUrl}/comments/${comment.id}/replies`, {
        content: text
      }).then(({ data }) => {

        comment.replies.push({
          ...data.reply,
          avatarColor: colorForName(data.reply.author)
        });

        this.replyingToId = null;
        this.replyText = '';

      }).catch((error) => {

        window.alert(error.response?.data?.message || 'Failed to post reply.');

      }).finally(() => {

        this.submittingReply = false;

      });

    }

  }

}
</script>

<style scoped>

.pp{
  --ink:#161B2B;
  --text:#4B5263;
  --muted:#8A92A3;
  --line:#E7E9F0;
  --line-soft:#F1F3F7;
  --brand:#6D4AFF;
  --brand-2:#8F6BFF;
  --brand-soft:#F2EEFF;
  padding:24px;
  display:flex;
  flex-direction:column;
  gap:20px;
}

.pp-card{
  background:#fff;
  border:1px solid var(--line);
  border-radius:18px;
  box-shadow:0 1px 2px rgba(16,24,40,.04), 0 8px 24px rgba(16,24,40,.04);
  padding:20px 22px;
}

.pp-card-head{
  display:flex;
  align-items:flex-start;
  justify-content:space-between;
  gap:12px;
  margin-bottom:16px;
}

.pp-card-head h2{
  margin:0;
  padding:0;
  line-height:1.35;
  font-size:16px;
  font-weight:700;
  color:var(--ink);
  display:flex;
  align-items:center;
  gap:8px;
}

.pp-card-head p{
  margin:2px 0 0;
  line-height:1.4;
  font-size:12.5px;
  color:var(--muted);
}

/* Hero */
.pp-hero{
  display:flex;
  flex-wrap:wrap;
  align-items:center;
  justify-content:space-between;
  gap:18px;
  padding:22px 24px;
  background:
    radial-gradient(1200px 200px at 0% 0%, rgba(109,74,255,.07), transparent 60%),
    linear-gradient(180deg, #fff, #fbfaff);
}






.pp-hero-text{ min-width:0; }

.pp-crumbs{ display:flex; align-items:center; gap:8px; font-size:12px; color:var(--muted); margin-bottom:4px; }
.pp-crumbs a{ color:var(--muted); text-decoration:none; }
.pp-crumbs a:hover{ color:var(--brand); }
.pp-crumbs i{ font-size:8px; }

.pp-title{
  margin:0 0 10px;
  line-height:1.25;
  font-size:22px;
  font-weight:700;
  letter-spacing:-.01em;
  color:var(--ink);
  overflow-wrap:anywhere;
}

.pp-chips{ display:flex; flex-wrap:wrap; gap:8px; }

.pp-chip{
  display:inline-flex; align-items:center; gap:6px;
  height:28px; padding:0 11px;
  border-radius:8px;
  background:#fff;
  border:1px solid var(--line);
  color:var(--text);
  font-size:12px; font-weight:500;
}
.pp-chip i{ color:var(--muted); font-size:11px; }

.pp-badge{
  display:inline-flex; align-items:center; gap:6px;
  height:28px; padding:0 11px;
  border-radius:8px;
  font-size:12px; font-weight:700;
}
.pp-badge::before{ content:""; width:7px; height:7px; border-radius:50%; background:currentColor; }
.pp-badge-sm{ height:24px; padding:0 9px; font-size:11px; border-radius:7px; }
.pp-badge.is-success{ background:#E8F8EE; color:#16A34A; }
.pp-badge.is-info{ background:#EAF2FF; color:#2563EB; }
.pp-badge.is-warning{ background:#FFF6E5; color:#D97706; }
.pp-badge.is-danger{ background:#FDECEC; color:#DC2626; }
.pp-badge.is-muted{ background:#F1F3F7; color:#64748B; }

.pp-hero-actions{ display:flex; flex-wrap:wrap; gap:10px; }

.pp-btn{
  display:inline-flex; align-items:center; gap:8px;
  height:42px; padding:0 16px;
  border-radius:12px;
  font-size:13.5px; font-weight:600;
  text-decoration:none;
  border:1px solid transparent;
  transition:all .15s;
  white-space:nowrap;
}
.pp-btn-ghost{ background:#fff; border-color:var(--line); color:var(--text); }
.pp-btn-ghost:hover{ border-color:#cfc4ff; color:var(--brand); }
.pp-btn-primary{
  --pf:var(--brand);
  background:var(--pf-fill, linear-gradient(135deg, var(--brand), var(--brand-2)));
  color:var(--pf-ink, #fff);
  box-shadow:0 6px 16px color-mix(in srgb, var(--pf) 30%, transparent);
}
.pp-btn-primary:hover{ color:var(--pf-ink, #fff); transform:translateY(-1px); box-shadow:0 10px 22px color-mix(in srgb, var(--pf) 35%, transparent); }
.pp-btn-trail{ font-size:11px; opacity:.8; }

/* Platform switcher */











.pp-dot.is-info{ background:#3B82F6; }
.pp-dot.is-warning{ background:#F59E0B; }
.pp-dot.is-danger{ background:#EF4444; }

/* Layout */
.pp-grid{
  display:grid;
  grid-template-columns:minmax(0, 460px) minmax(0, 1fr);
  gap:20px;
  align-items:start;
}
.pp-side{ display:flex; flex-direction:column; gap:20px; min-width:0; }

@media (max-width: 1100px){
  .pp-grid{ grid-template-columns:1fr; }
}

/* Stage */
.pp-stage-card{ position:sticky; top:90px; }


.mk-avatar > i, .pp-pf-tile i, .pp-dist-logo i, .pp-pf-dot i{ filter:var(--pf-glow); }

.pp-stage{
  border-radius:16px;
  padding:28px 22px;
  background:
    radial-gradient(400px 220px at 15% 0%, color-mix(in srgb, var(--pf) 16%, transparent), transparent 70%),
    radial-gradient(360px 220px at 100% 100%, rgba(109,74,255,.12), transparent 70%),
    #F6F7FB;
  display:flex; justify-content:center;
}

.pp-device{
  width:100%; max-width:380px;
  background:#fff;
  border-radius:22px;
  border:1px solid rgba(16,24,40,.06);
  box-shadow:0 24px 50px rgba(16,24,40,.12), 0 2px 6px rgba(16,24,40,.05);
  overflow:hidden;
  padding-bottom:6px;
}

/* Mock post */
.mk-header{ display:flex; align-items:center; gap:10px; padding:14px 14px 10px; }
.mk-avatar{
  width:40px; height:40px; border-radius:50%; flex-shrink:0; overflow:hidden;
  display:grid; place-items:center;
  background:var(--pf-fill); color:var(--pf-ink); font-size:17px;
}
.mk-avatar img{ width:100%; height:100%; object-fit:cover; }
.mk-avatar-instagram{
  padding:2px;
  background:linear-gradient(45deg, #F58529, #DD2A7B, #8134AF, #515BD4);
}
.mk-avatar-instagram img, .mk-avatar-instagram i{ border-radius:50%; border:2px solid #fff; width:100%; height:100%; display:grid; place-items:center; }
.mk-avatar-instagram i{ background:linear-gradient(45deg, #F58529, #DD2A7B, #8134AF); }
.mk-identity{ display:flex; flex-direction:column; min-width:0; flex:1; }
.mk-identity strong{ font-size:14px; color:var(--ink); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.mk-identity small{ font-size:12px; color:var(--muted); }
.mk-verified{ color:#1D9BF0; font-size:12px; }
.mk-more{ color:var(--muted); }

.mk-text{
  padding:2px 14px 12px;
  font-size:14px; line-height:1.5;
  color:#1E2333;
  white-space:pre-wrap; overflow-wrap:anywhere;
}
.mk-text strong{ margin-right:4px; }

/* Media sits inside the card with rounded corners and a hairline border. */
.mk-media{
  position:relative; background:#F1F3F8;
  margin:2px 14px 10px; border-radius:14px; overflow:hidden;
  border:1px solid #E7E9F0;
  box-shadow:0 1px 2px rgba(16,24,40,.05), 0 6px 16px rgba(16,24,40,.06);
  isolation:isolate;
}
.mk-media img, .mk-media video{ width:100%; display:block; max-height:460px; object-fit:cover; }
.mk-media.is-square img, .mk-media.is-square video{ aspect-ratio:1 / 1; object-fit:cover; }
.mk-media.is-rounded{ border-radius:16px; }

.mk-nav{
  position:absolute; top:50%; transform:translateY(-50%);
  width:30px; height:30px; border-radius:50%; border:none;
  background:rgba(255,255,255,.92); color:var(--ink);
  display:grid; place-items:center; font-size:11px;
  box-shadow:0 2px 8px rgba(0,0,0,.2); cursor:pointer;
}
.mk-nav:disabled{ opacity:0; pointer-events:none; }
.mk-prev{ left:10px; }
.mk-next{ right:10px; }
.mk-counter{
  position:absolute; top:10px; right:10px;
  background:rgba(15,16,32,.7); color:#fff;
  font-size:11px; font-weight:600; padding:3px 8px; border-radius:20px;
}
.mk-dots{ display:flex; justify-content:center; gap:4px; padding:8px 0 0; }
.mk-dots span{ width:6px; height:6px; border-radius:50%; background:#D5D9E2; cursor:pointer; transition:all .15s; }
.mk-dots span.active{ background:#3B82F6; width:7px; height:7px; }

.mk-ig-icons{ display:flex; gap:16px; padding:10px 14px 6px; font-size:21px; color:var(--ink); }
.mk-push{ margin-left:auto; }
.mk-likes{ padding:0 14px 4px; font-size:14px; font-weight:700; color:var(--ink); }
.mk-link{ padding:0 14px 4px; font-size:13px; color:var(--muted); }
.mk-time{ padding:0 14px 10px; font-size:10.5px; color:var(--muted); text-transform:uppercase; letter-spacing:.03em; }

.mk-reactions{
  display:flex; justify-content:space-between; align-items:center;
  padding:10px 14px;
  font-size:12.5px; color:var(--muted);
}
.mk-reaction-summary{ display:inline-flex; align-items:center; gap:6px; }
.mk-reaction-stack{ display:inline-flex; }
.mk-reaction-stack i{
  width:18px; height:18px; border-radius:50%;
  background:#fff; display:grid; place-items:center; font-size:10px;
  box-shadow:0 0 0 2px #fff; margin-left:-4px;
}
.mk-reaction-stack i:first-child{ margin-left:0; }

.mk-actions{
  display:flex; justify-content:space-around;
  margin:0 14px; padding:8px 0 6px;
  border-top:1px solid var(--line-soft);
  font-size:13px; font-weight:600; color:var(--text);
}
.mk-actions span{ display:inline-flex; align-items:center; gap:6px; padding:6px 8px; border-radius:8px; }
.mk-x-actions{ justify-content:space-between; font-weight:500; color:var(--muted); border-top:none; }

.comments-toggle{ cursor:pointer; }
.mk-actions .comments-toggle:hover, .mk-link.comments-toggle:hover{ color:var(--brand); }

/* KPIs */
.pp-kpis{ display:grid; grid-template-columns:repeat(6, minmax(0, 1fr)); gap:10px; }
@media (max-width: 1400px){ .pp-kpis{ grid-template-columns:repeat(3, minmax(0, 1fr)); } }
.pp-kpi{
  display:flex; flex-direction:column; gap:2px;
  padding:14px;
  border-radius:14px;
  background:color-mix(in srgb, var(--tone) 6%, #fff);
  border:1px solid color-mix(in srgb, var(--tone) 14%, #fff);
  transition:transform .15s, box-shadow .15s;
}
.pp-kpi:hover{ transform:translateY(-2px); box-shadow:0 8px 18px rgba(16,24,40,.06); }

.pp-kpi strong{ font-size:20px; font-weight:700; color:var(--ink); line-height:1.1; }
.pp-kpi small{ font-size:12px; color:var(--muted); }

.pp-alert{
  display:flex; gap:10px; margin-top:14px;
  padding:12px 14px; border-radius:12px;
  background:#FDECEC; color:#B42318; font-size:13px;
}
.pp-alert i{ margin-top:2px; }
.pp-alert div{ display:flex; flex-direction:column; gap:2px; }

/* Distribution */
.pp-dist{ display:flex; flex-direction:column; gap:8px; }
.pp-dist-item{
  display:flex; align-items:center; gap:12px;
  width:100%; text-align:left;
  padding:10px 12px;
  border-radius:14px;
  border:1px solid var(--line);
  background:#fff;
  cursor:pointer;
  transition:all .15s;
}
.pp-dist-item:hover{ border-color:color-mix(in srgb, var(--pf) 45%, #fff); background:#FCFCFF; }
.pp-dist-item.active{
  border-color:var(--pf);
  background:color-mix(in srgb, var(--pf) 5%, #fff);
  box-shadow:0 0 0 3px color-mix(in srgb, var(--pf) 12%, transparent);
}



.pp-dist-info{ display:flex; flex-direction:column; min-width:0; flex:1; }
.pp-dist-info strong{ font-size:13.5px; color:var(--ink); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.pp-dist-info small{ font-size:12px; color:var(--muted); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.pp-dist-arrow{ color:#C4C9D4; font-size:11px; }
.pp-dist-item.active .pp-dist-arrow{ color:var(--pf); }

/* Comments */
.pp-comments{ scroll-margin-top:90px; }
.pp-count{
  display:inline-flex; align-items:center; height:20px; line-height:1;
  font-size:11px; font-weight:700;
  background:var(--brand-soft); color:var(--brand);
  padding:2px 8px; border-radius:7px;
}


.pp-empty{
  display:flex; flex-direction:column; align-items:center; text-align:center; gap:4px;
  padding:26px 16px 22px;
  color:var(--muted); font-size:12.5px;
}
.pp-empty strong{ color:var(--ink); font-size:14px; }
.pp-empty-icon{
  width:52px; height:52px; border-radius:16px; margin-bottom:6px;
  display:grid; place-items:center;
  background:var(--brand-soft); color:var(--brand); font-size:20px;
}

.pp-thread-list{ display:flex; flex-direction:column; gap:16px; max-height:520px; overflow-y:auto; padding-right:4px; }

.pp-comment{ display:flex; gap:10px; }
.pp-comment-body{ flex:1; min-width:0; }
.pp-avatar{
  width:36px; height:36px; border-radius:50%; flex-shrink:0; overflow:hidden;
  display:grid; place-items:center;
  color:#fff; font-size:12px; font-weight:700;
}
.pp-avatar img{ width:100%; height:100%; object-fit:cover; }
.pp-avatar-own{ background:linear-gradient(135deg, var(--brand), var(--brand-2)); }
.pp-comment.is-reply .pp-avatar{ width:28px; height:28px; font-size:10.5px; }

.pp-bubble{
  display:inline-block; max-width:100%;
  background:#F4F5F9;
  border-radius:4px 16px 16px 16px;
  padding:9px 13px;
  font-size:13.5px; line-height:1.45; color:#2B3142;
  overflow-wrap:anywhere;
}
.pp-bubble strong{ display:flex; align-items:center; gap:6px; font-size:12.5px; color:var(--ink); margin-bottom:2px; }
.pp-comment.own > .pp-comment-body > .pp-bubble{ background:var(--brand-soft); }
.pp-you{ font-size:10px; font-weight:700; background:var(--brand); color:#fff; padding:1px 6px; border-radius:5px; }

.pp-meta{ display:flex; align-items:center; gap:14px; padding:5px 6px 0; font-size:11.5px; color:var(--muted); }
.pp-meta .fa-heart{ color:#EF4444; }
.pp-link{ border:none; background:none; padding:0; color:var(--brand); font-weight:600; font-size:11.5px; cursor:pointer; }
.pp-link:hover{ text-decoration:underline; }

.pp-replies{
  display:flex; flex-direction:column; gap:12px;
  margin-top:12px; padding-left:14px;
  border-left:2px solid var(--line-soft);
}

.pp-composer{
  display:flex; align-items:center; gap:10px;
  margin-top:16px; padding-top:16px;
  border-top:1px solid var(--line-soft);
}
.pp-composer-inline{ margin-top:10px; padding-top:0; border-top:none; }
.pp-input-row{
  flex:1; display:flex; align-items:center; gap:8px;
  border:1px solid var(--line); border-radius:14px;
  padding:4px 4px 4px 14px;
  background:#fff;
  transition:border-color .15s, box-shadow .15s;
}
.pp-input-row:focus-within{ border-color:var(--brand); box-shadow:0 0 0 3px rgba(109,74,255,.12); }
.pp-input-row input{ flex:1; min-width:0; border:none; outline:none; font-size:13.5px; color:var(--ink); background:transparent; height:34px; }
.pp-send{
  width:38px; height:38px; flex-shrink:0;
  border:none; border-radius:11px;
  background:linear-gradient(135deg, var(--brand), var(--brand-2));
  color:#fff; font-size:14px; cursor:pointer;
  display:grid; place-items:center;
  transition:opacity .15s;
}
.pp-send:disabled{ opacity:.45; cursor:not-allowed; }

/* Not found */
.pp-not-found{
  display:flex; flex-direction:column; align-items:center; text-align:center; gap:8px;
  padding:60px 20px;
}
.pp-not-found h3{ margin:8px 0 0; font-size:18px; color:var(--ink); }
.pp-not-found p{ margin:0 0 10px; color:var(--muted); }

/* RTL */
[dir="rtl"] .pp-crumbs i, [dir="rtl"] .pp-dist-arrow, [dir="rtl"] .pp-back i{ transform:scaleX(-1); }
[dir="rtl"] .pp-dist-item{ text-align:right; }
[dir="rtl"] .pp-replies{ padding-left:0; padding-right:14px; border-left:none; border-right:2px solid var(--line-soft); }
[dir="rtl"] .pp-bubble{ border-radius:16px 4px 16px 16px; }

@media (max-width: 575.98px){
  .pp{ padding:14px; gap:14px; }
  .pp-card{ padding:16px; }
  .pp-hero-actions{ width:100%; }
  .pp-hero-actions .pp-btn{ flex:1; justify-content:center; }
  .pp-stage{ padding:16px 10px; }
  .pp-kpis{ grid-template-columns:repeat(2, minmax(0, 1fr)); }
}


/* =========================================================
   Redesign: header + platform bar + post on the left,
   distribution + conversation on the right.
========================================================= */
.pp-grid{ grid-template-columns:minmax(0, 1.35fr) minmax(0, 1fr); gap:22px; }
.pp-main{ display:flex; flex-direction:column; gap:20px; min-width:0; }
.pp-stage-card{ position:static; padding:18px; }

.pp-hero{ align-items:flex-start; flex-wrap:nowrap; padding:20px 22px; }
.pp-hero-text{ flex:1 1 auto; min-width:0; }
.pp-hero-actions{ flex-shrink:0; flex-wrap:nowrap; }
.pp-crumbs{ gap:10px; font-size:12.5px; margin-bottom:8px; }
.pp-crumbs span{ color:var(--brand); font-weight:600; }
.pp-crumb-back{ color:var(--text) !important; font-size:12px; display:inline-flex; }
.pp-crumb-back:hover{ color:var(--brand) !important; }
.pp-title{ margin:0 0 12px; }
.pp-hero-actions{ align-items:center; gap:8px; }
.pp-btn-icon{ width:40px; padding:0; justify-content:center; }
.pp-btn-sm{ height:34px; padding:0 12px; font-size:12.5px; border-radius:10px; }
.pp-btn i{ font-size:13px; }

.pp-menu{ position:relative; }
.pp-menu-list{
  position:absolute; right:0; top:calc(100% + 6px); z-index:20; min-width:190px; padding:6px;
  background:#fff; border:1px solid var(--line); border-radius:12px; box-shadow:0 16px 36px rgba(16,24,40,.14);
  display:flex; flex-direction:column;
}
.pp-menu-list a, .pp-menu-list button{
  display:flex; align-items:center; gap:10px; padding:9px 10px; border-radius:8px; border:none; background:none;
  color:var(--text); font-size:13px; font-weight:500; text-decoration:none; text-align:left; cursor:pointer; width:100%;
}
.pp-menu-list a:hover, .pp-menu-list button:hover{ background:var(--brand-soft); color:var(--brand); }
.pp-menu-list i{ width:16px; text-align:center; color:var(--muted); }

/* Platform bar */
.pp-pf-bar{ display:flex; align-items:center; justify-content:space-between; gap:14px; flex-wrap:wrap; padding:2px 2px 16px; }
.pp-pf-current{ display:flex; align-items:center; gap:12px; min-width:0; }
.pp-pf-tile{
  width:52px; height:52px; border-radius:15px; display:grid; place-items:center; flex-shrink:0;
  background:var(--pf-fill); color:var(--pf-ink); font-size:24px;
  box-shadow:0 0 0 3px #fff, 0 0 0 5px color-mix(in srgb, var(--pf) 35%, transparent), 0 10px 22px color-mix(in srgb, var(--pf) 30%, transparent);
}
.pp-pf-current-text{ display:flex; align-items:center; gap:10px; flex-wrap:wrap; min-width:0; }
.pp-pf-current-text strong{ font-size:15px; color:var(--ink); }
.pp-pf-current-text small{ flex-basis:100%; font-size:12px; color:var(--muted); margin-top:-6px; }
.pp-live{ display:inline-flex; align-items:center; gap:6px; font-size:12px; font-weight:600; }
.pp-live::before{ content:""; width:7px; height:7px; border-radius:50%; background:currentColor; box-shadow:0 0 0 3px color-mix(in srgb, currentColor 18%, transparent); }
.pp-live.is-success{ color:#16A34A; }
.pp-live.is-info{ color:#2563EB; }
.pp-live.is-warning{ color:#D97706; }
.pp-live.is-danger{ color:#DC2626; }
.pp-live.is-muted{ color:#64748B; }
.pp-pf-others{ display:flex; align-items:center; gap:8px; flex-wrap:wrap; }
.pp-pf-dot{
  width:40px; height:40px; border-radius:50%; display:grid; place-items:center; cursor:pointer;
  background:#fff; border:1px solid var(--line); color:var(--pf); font-size:16px;
  transition:transform .15s, border-color .15s, box-shadow .15s;
}
.pp-pf-dot:hover{ transform:translateY(-2px); border-color:color-mix(in srgb, var(--pf) 45%, #fff); box-shadow:0 8px 16px rgba(16,24,40,.08); }
.pp-pf-more{ color:var(--text); font-size:12.5px; font-weight:700; }

.pp-stage{ padding:22px; }
.pp-device{ max-width:560px; }

/* KPI strip under the post */
.pp-stage-card .pp-kpis{ grid-template-columns:repeat(6, minmax(0, 1fr)); gap:8px; margin-top:16px; }
.pp-stage-card .pp-kpi{ padding:10px 8px; align-items:center; text-align:center; gap:2px; }
.pp-stage-card .pp-kpi > i{ color:var(--tone); font-size:13px; }
.pp-stage-card .pp-kpi strong{ font-size:16px; font-variant-numeric:tabular-nums; }
.pp-stage-card .pp-kpi small{ font-size:11px; }
.pp-stage-card .pp-alert{ margin-top:14px; }

/* Published to - compact brand rows */
.pp-dist{ gap:8px; }
.pp-dist-item{
  display:flex; align-items:center; gap:12px; width:100%; cursor:pointer; text-align:left;
  padding:10px 10px 10px 12px; border-radius:14px; border:1px solid var(--line);
  background:linear-gradient(90deg, color-mix(in srgb, var(--pf) 5%, #fff) 0%, #fff 55%);
  transition:border-color .15s, box-shadow .15s, transform .15s;
}
.pp-dist-item:hover{ border-color:color-mix(in srgb, var(--pf) 35%, #fff); box-shadow:0 6px 14px rgba(16,24,40,.05); }
.pp-dist-item:focus-visible{ outline:2px solid var(--brand); outline-offset:2px; }
.pp-dist-item.active{
  border-color:color-mix(in srgb, var(--pf) 45%, #fff);
  background:linear-gradient(90deg, color-mix(in srgb, var(--pf) 11%, #fff) 0%, #fff 70%);
  box-shadow:0 6px 16px color-mix(in srgb, var(--pf) 14%, transparent);
}
.pp-dist-logo{
  width:38px; height:38px; border-radius:50%; flex-shrink:0; display:grid; place-items:center;
  background:var(--pf-fill); color:var(--pf-ink); font-size:17px;
}
.pp-dist-info strong{ font-size:13.5px; font-weight:700; }
.pp-dist-info small{ font-size:12px; }
.pp-status{
  display:inline-flex; align-items:center; gap:5px; height:24px; padding:0 9px; border-radius:999px;
  font-size:11px; font-weight:600; white-space:nowrap;
}
.pp-status i{ font-size:9px; }
.pp-status.is-success{ background:#E7F7EE; color:#15803D; }
.pp-status.is-info{ background:#EAF2FF; color:#2563EB; }
.pp-status.is-warning{ background:#FFF6E5; color:#B45309; }
.pp-status.is-danger{ background:#FDECEC; color:#DC2626; }
.pp-status.is-muted{ background:#F1F3F7; color:#64748B; }

.pp-kebab{
  width:30px; height:30px; border-radius:8px; border:none; background:transparent; color:#98A0B3;
  display:grid; place-items:center; cursor:pointer; font-size:13px; transition:background .15s, color .15s;
}
.pp-kebab:hover, .pp-kebab[aria-expanded="true"]{ background:#F1F3F8; color:var(--ink); }
.pp-kebab-sm{ width:26px; height:26px; font-size:12px; }

/* Comments - one card per thread */
.pp-count{ background:var(--brand-soft); color:var(--brand); }
.pp-thread-list{ gap:10px; max-height:560px; padding-right:2px; }
.pp-thread{ border:1px solid var(--line); border-radius:14px; padding:12px 12px 12px 14px; background:#fff; transition:border-color .15s, box-shadow .15s; }
.pp-thread:hover{ border-color:#dcd6fb; box-shadow:0 6px 16px rgba(16,24,40,.05); }
.pp-thread > .pp-comment > .pp-avatar{ width:40px; height:40px; font-size:13px; }
.pp-comment-head{ display:flex; align-items:center; gap:8px; min-height:26px; }
.pp-comment-author{ font-size:13.5px; color:var(--ink); display:flex; align-items:center; gap:6px; min-width:0; }
.pp-comment-head .pp-menu{ margin-left:auto; }
.pp-sentiment{
  margin-left:auto; display:inline-flex; align-items:center; gap:4px; height:22px; padding:0 8px; border-radius:999px;
  font-size:11px; font-weight:600; white-space:nowrap;
}
.pp-sentiment + .pp-menu{ margin-left:0; }
.pp-sentiment.is-positive{ background:#E7F7EE; color:#15803D; }
.pp-sentiment.is-neutral{ background:#EAF2FF; color:#2563EB; }
.pp-sentiment.is-negative{ background:#FDECEC; color:#DC2626; }
.pp-comment-text{ margin:2px 0 0; color:var(--text); font-size:13.5px; line-height:1.5; word-break:break-word; }
.pp-thread .pp-meta{ padding:6px 0 0; gap:0; }
.pp-thread .pp-meta > * + *::before{ content:"·"; margin:0 7px; color:#C4C9D4; }
.pp-thread .pp-meta .pp-link{ font-weight:600; }
.pp-thread .pp-replies .pp-bubble{ background:#F7F8FB; padding:8px 10px; border-radius:10px; }

.pp-composer{ align-items:center; gap:10px; }
.pp-avatar-account{ background:#fff; border:1px solid var(--line); color:var(--brand); width:42px; height:42px; }
.pp-composer .pp-input-row{ flex:1; height:46px; border-radius:14px; padding:0 14px; }
.pp-comments > .pp-composer .pp-send{
  width:46px; height:46px; border-radius:50%; flex-shrink:0; font-size:16px;
  background:linear-gradient(135deg, var(--brand), var(--brand-2)); color:#fff; border:none;
  box-shadow:0 8px 18px rgba(109,74,255,.32);
}

@media (max-width: 1199.98px){
  .pp-grid{ grid-template-columns:minmax(0, 1fr); }
}
@media (max-width: 575.98px){
  .pp-stage-card .pp-kpis{ grid-template-columns:repeat(3, minmax(0, 1fr)); }
  .pp-stage{ padding:12px; }
  .pp-hero-actions{ width:100%; flex-wrap:wrap; }
}
@media (max-width: 767.98px){
  .pp-hero{ flex-wrap:wrap; }
}
</style>
