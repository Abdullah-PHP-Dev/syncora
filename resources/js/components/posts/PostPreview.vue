<template>
  <div class="pp">

    <div v-if="!post" class="pp-card pp-not-found">

      <span class="pp-empty-icon"><i class="fas fa-ghost"></i></span>
      <h3>We couldn't find that post</h3>
      <p>It may have been deleted, or it belongs to another account.</p>
      <a :href="backUrl" class="pp-btn pp-btn-primary"><i class="fas fa-arrow-left"></i> Back to Posts</a>

    </div>

    <template v-else>

      <!-- Hero: title, status/meta chips, primary actions -->
      <header class="pp-card pp-hero">

        <div class="pp-hero-main">

          <a :href="backUrl" class="pp-back" title="Back to Posts"><i class="fas fa-arrow-left"></i></a>

          <div class="pp-hero-text">

            <div class="pp-crumbs">
              <a :href="backUrl">Posts</a>
              <i class="fas fa-chevron-right"></i>
              <span>Preview</span>
            </div>

            <h1 class="pp-title">{{ post.title }}</h1>

            <div class="pp-chips">
              <span class="pp-badge" :class="'is-' + activeStatus.tone">{{ activeStatus.label }}</span>
              <span class="pp-chip"><i :class="typeIcon"></i> {{ typeLabel }}</span>
              <span class="pp-chip" v-if="activeMember.scheduled_label"><i class="far fa-clock"></i> Scheduled for {{ activeMember.scheduled_label }}</span>
              <span class="pp-chip" v-else-if="activeMember.created_label"><i class="far fa-calendar"></i> {{ activeMember.created_label }}</span>
              <span class="pp-chip"><i class="fas fa-layer-group"></i> {{ post.platforms.length }} {{ post.platforms.length === 1 ? 'platform' : 'platforms' }}</span>
            </div>

          </div>

        </div>

        <div class="pp-hero-actions">
          <a :href="backUrl" class="pp-btn pp-btn-ghost"><i class="fas fa-th-large"></i> All posts</a>
          <a
              v-if="hasPlatformUrl"
              :href="platformUrl"
              target="_blank"
              rel="noopener noreferrer"
              class="pp-btn pp-btn-primary"
              :style="brandVars(activeKey)">
            <i :class="activePlatform.icon"></i> View on {{ activePlatform.name }}
            <i class="fas fa-external-link-alt pp-btn-trail"></i>
          </a>
        </div>

      </header>

      <!-- Platform switcher -->
      <nav class="pp-switcher" v-if="post.platforms.length > 1" aria-label="Platforms">
        <button
            v-for="p in post.platforms"
            :key="p.key"
            type="button"
            class="pp-switch"
            :class="{active: p.key === activeKey}"
            :style="brandVars(p.key)"
            @click="switchPlatform(p)">
          <span class="pp-switch-icon"><i :class="p.icon"></i></span>
          <span class="pp-switch-name">{{ p.name }}</span>
          <span class="pp-dot" :class="'is-' + statusInfo(p).tone" :title="statusInfo(p).label"></span>
        </button>
      </nav>

      <div class="pp-grid">

        <!-- Left: device stage with the platform-native mock -->
        <section class="pp-card pp-stage-card">

          <div class="pp-card-head">
            <div>
              <h2>Live preview</h2>
              <p>How your post appears on {{ activePlatform.name }}</p>
            </div>
            <span class="pp-platform-pill" :style="brandVars(activeKey)"><i :class="activePlatform.icon"></i></span>
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

        </section>

        <!-- Right: insights, distribution, comments -->
        <div class="pp-side">

          <section class="pp-card">
            <div class="pp-card-head">
              <div>
                <h2>Performance</h2>
                <p>{{ activePlatform.name }} · {{ activePlatform.page }}</p>
              </div>
            </div>
            <div class="pp-kpis">
              <div v-for="kpi in kpis" :key="kpi.label" class="pp-kpi" :style="{'--tone': kpi.color}">
                <span class="pp-kpi-icon"><i :class="kpi.icon"></i></span>
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

          <section class="pp-card">
            <div class="pp-card-head">
              <div>
                <h2>Published to</h2>
                <p>The same post across your connected accounts</p>
              </div>
            </div>
            <div class="pp-dist">
              <button
                  v-for="p in post.platforms"
                  :key="p.key"
                  type="button"
                  class="pp-dist-item"
                  :class="{active: p.key === activeKey}"
                  :style="brandVars(p.key)"
                  @click="switchPlatform(p)">
                <span class="pp-dist-avatar">
                  <img v-if="p.avatar" :src="p.avatar" alt="" @error="p.avatar = null">
                  <i v-else :class="p.icon"></i>
                  <span class="pp-dist-badge"><i :class="p.icon"></i></span>
                </span>
                <span class="pp-dist-info">
                  <strong>{{ p.page }}</strong>
                  <small>{{ p.name }}<template v-if="p.handle && p.handle !== p.page"> · {{ p.handle }}</template></small>
                </span>
                <span class="pp-badge pp-badge-sm" :class="'is-' + statusInfo(p).tone">{{ statusInfo(p).label }}</span>
                <i class="fas fa-chevron-right pp-dist-arrow"></i>
              </button>
            </div>
          </section>

          <!-- Comments & replies for the active platform -->
          <section class="pp-card pp-comments" id="comments">

            <div class="pp-card-head comments-toggle" @click="showComments = !showComments">
              <div>
                <h2>Comments <span class="pp-count">{{ engagement.comments.length }}</span></h2>
                <p>Conversation on {{ activePlatform.name }}</p>
              </div>
              <i class="fas pp-collapse" :class="showComments ? 'fa-chevron-up' : 'fa-chevron-down'"></i>
            </div>

            <template v-if="showComments">

              <div v-if="!engagement.comments.length" class="pp-empty">
                <span class="pp-empty-icon"><i class="far fa-comments"></i></span>
                <strong>No comments yet</strong>
                <span>Comments from {{ activePlatform.name }} will appear here. Start the conversation below.</span>
              </div>

              <div v-else class="pp-thread-list">

                <div v-for="comment in engagement.comments" :key="comment.id" class="pp-thread">

                  <div class="pp-comment" :class="{own: comment.isOwn}">

                    <div class="pp-avatar" :style="{background: comment.avatarColor}">
                      <img v-if="comment.avatar" :src="comment.avatar" alt="" @error="comment.avatar = null">
                      <template v-else>{{ initials(comment.author) }}</template>
                    </div>

                    <div class="pp-comment-body">

                      <div class="pp-bubble">
                        <strong>{{ comment.author }} <span v-if="comment.isOwn" class="pp-you">You</span></strong>
                        <div>{{ comment.content }}</div>
                      </div>

                      <div class="pp-meta">
                        <span>{{ comment.timeAgo }}</span>
                        <span v-if="comment.likes"><i class="fas fa-heart"></i> {{ comment.likes }}</span>
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
                <div class="pp-avatar pp-avatar-own">{{ userInitials }}</div>
                <div class="pp-input-row">
                  <input type="text" v-model="newCommentText" :placeholder="commentPlaceholder" @keyup.enter="addComment">
                  <button type="button" class="pp-send" :disabled="!newCommentText.trim() || submittingComment" @click="addComment">
                    <i class="fas" :class="submittingComment ? 'fa-spinner fa-spin' : 'fa-paper-plane'"></i>
                  </button>
                </div>
              </div>

            </template>

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
    }

  },

  data() {

    return {
      post: null,
      activeKey: '',
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

      const requested = this.post.platforms.find(p => p.key === this.platform);

      this.activeKey = requested ? requested.key : this.post.platforms[0].key;

    }

  },

  computed: {

    activePlatform() {

      if (!this.post) return {};

      return this.post.platforms.find(p => p.key === this.activeKey) || platformMeta[this.activeKey] || {};

    },

    // The raw per-platform Post row (status, dates, media, error).
    activeMember() {

      if (!this.post) return {};

      return this.post.members[this.activeKey] || {};

    },

    activeStatus() {

      return this.statusInfo(this.activePlatform);

    },

    engagement() {

      if (!this.post) return null;

      return this.post.engagement[this.activeKey];

    },

    platformUrl() {

      if (!this.post) return '#';

      return this.post.platformUrls[this.activeKey] || '#';

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
      this.openComments();
    }
  },

  methods: {

    // groupPosts holds one raw post per platform the same quick-post
    // submission went to (empty for older/ungrouped posts, in which case
    // this just falls back to treating `raw` as a group of one - the
    // original single-platform behavior). platforms/engagement/
    // platformUrls end up keyed/indexed by platform so switchPlatform()
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

        engagement[key] = {
          ...member.engagement,
          reactions,
          comments: (member.engagement.comments || []).map(mapComment)
        };

        platformUrls[key] = member.platform_url || '#';
        membersByKey[key] = member;

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

    switchPlatform(p) {

      this.activeKey = p.key;
      this.mediaIndex = 0;
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

.pp-hero-main{ display:flex; align-items:flex-start; gap:16px; min-width:0; flex:1; }

.pp-back{
  width:42px; height:42px; flex-shrink:0;
  display:grid; place-items:center;
  border-radius:12px;
  border:1px solid var(--line);
  background:#fff;
  color:var(--text);
  text-decoration:none;
  transition:all .15s;
}
.pp-back:hover{ color:var(--brand); border-color:#cfc4ff; background:var(--brand-soft); }

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
.pp-switcher{
  display:flex; gap:10px; flex-wrap:wrap;
}
.pp-switch{
  position:relative;
  display:inline-flex; align-items:center; gap:10px;
  height:48px; padding:0 18px 0 8px;
  border-radius:14px;
  border:1px solid var(--line);
  background:#fff;
  color:var(--text);
  font-size:13.5px; font-weight:600;
  cursor:pointer;
  transition:all .15s;
}
.pp-switch:hover{ border-color:var(--pf); transform:translateY(-1px); box-shadow:0 6px 16px rgba(16,24,40,.06); }
.pp-switch-icon{
  width:32px; height:32px; border-radius:10px;
  display:grid; place-items:center;
  background:color-mix(in srgb, var(--pf) 12%, #fff);
  color:var(--pf); font-size:15px;
}
.pp-switch.active{
  border-color:var(--pf);
  box-shadow:0 0 0 3px color-mix(in srgb, var(--pf) 14%, transparent), 0 8px 20px rgba(16,24,40,.06);
  color:var(--ink);
}
.pp-switch.active .pp-switch-icon{ background:var(--pf-fill); color:var(--pf-ink); }
.pp-switch.active .pp-switch-icon i{ filter:var(--pf-glow); }

.pp-dot{ width:8px; height:8px; border-radius:50%; background:#94A3B8; }
.pp-dot.is-success{ background:#16A34A; }
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
@media (max-width: 1100px){ .pp-stage-card{ position:static; } }

.pp-platform-pill{
  width:38px; height:38px; border-radius:12px;
  display:grid; place-items:center;
  background:var(--pf-fill); color:var(--pf-ink); font-size:17px;
  box-shadow:0 6px 14px color-mix(in srgb, var(--pf) 35%, transparent);
}
.pp-platform-pill i, .mk-avatar > i, .pp-dist-badge i{ filter:var(--pf-glow); }

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

.mk-media{ position:relative; background:#0F1020; }
.mk-media img, .mk-media video{ width:100%; display:block; max-height:460px; object-fit:cover; }
.mk-media.is-square img, .mk-media.is-square video{ aspect-ratio:1 / 1; object-fit:cover; }
.mk-media.is-rounded{ margin:0 14px 10px; border-radius:16px; overflow:hidden; }

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
.pp-kpi-icon{
  width:30px; height:30px; border-radius:9px; margin-bottom:8px;
  display:grid; place-items:center;
  background:color-mix(in srgb, var(--tone) 16%, #fff);
  color:var(--tone); font-size:13px;
}
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
.pp-dist-avatar{
  position:relative; width:40px; height:40px; flex-shrink:0;
  border-radius:50%;
  display:grid; place-items:center;
  background:color-mix(in srgb, var(--pf) 12%, #fff);
  color:var(--pf); font-size:16px;
}
.pp-dist-avatar img{ width:100%; height:100%; border-radius:50%; object-fit:cover; }
.pp-dist-badge{
  position:absolute; right:-3px; bottom:-3px;
  width:18px; height:18px; border-radius:50%;
  display:grid; place-items:center;
  background:var(--pf-fill); color:var(--pf-ink); font-size:9px;
  border:2px solid #fff;
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
.pp-collapse{ color:var(--muted); font-size:12px; margin-top:6px; }

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

</style>
