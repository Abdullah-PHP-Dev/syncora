<template>
  <div class="preview-page">

    <div class="preview-topbar">

      <a :href="backUrl" class="back-link">
        <i class="fas fa-arrow-left"></i>
        Back to Posts
      </a>

      <h2>Post Preview</h2>

      <span class="preview-status" v-if="post" :class="post.status.toLowerCase()">
        {{ post.status }}
      </span>

    </div>

    <div v-if="!post" class="not-found">

      <i class="fas fa-ghost fa-2x"></i>
      <p>We couldn't find that post.</p>
      <a :href="backUrl" class="btn btn-primary">Back to Posts</a>

    </div>

    <div v-else class="preview-layout">

      <div class="preview-main">

        <div class="post-comments-row" :class="{'side-by-side': showComments}">

        <div class="sticky-post">

        <div class="preview-toolbar-row">

          <div class="platform-switch-tabs">

            <button
                v-for="p in post.platforms"
                :key="p.key"
                class="switch-tab"
                :class="{active: p.key === activeKey}"
                @click="switchPlatform(p)">

              <i
                  :class="p.icon"
                  :style="{color: p.key === activeKey ? '#fff' : p.color}">
              </i>

              {{ p.name }}

            </button>

          </div>

        </div>

        <!-- Facebook -->
        <div v-if="activeKey==='facebook'" class="mock-card facebook-mock">

          <div class="mock-header">

            <div class="mock-avatar" :style="{background:activePlatform.color}">
              <i class="fas fa-store"></i>
            </div>

            <div class="mock-identity">
              <strong>{{ activePlatform.page }}</strong>
              <small>{{ post.created_at }} · <i class="fas fa-globe-americas"></i></small>
            </div>

            <a
                :href="platformUrl"
                target="_blank"
                rel="noopener noreferrer"
                class="mock-more"
                :title="'Open on ' + activePlatform.name">
              <i class="fas fa-external-link-alt"></i>
            </a>

          </div>

          <div class="mock-text">{{ post.content }}</div>

          <div class="mock-media" v-if="mediaUrl">
            <img v-if="mediaKind==='image'" :src="mediaUrl">
            <video v-else :src="mediaUrl" :poster="post.thumbnail" controls></video>
          </div>

          <div class="mock-reactions">

            <div class="reaction-summary">

              <span class="reaction-stack">
                <i
                    v-for="kind in engagement.reactions"
                    :key="kind.key"
                    :class="kind.icon"
                    :style="{color:kind.color}">
                </i>
              </span>

              {{ engagement.reactionsTotal.toLocaleString() }}

            </div>

            <span>
              <span class="comments-toggle" @click="showComments = !showComments">{{ engagement.commentsCount }} comments</span>
              · {{ engagement.sharesCount }} shares
            </span>

          </div>

          <div class="mock-actions">
            <span><i class="far fa-thumbs-up"></i> Like</span>
            <span @click="showComments = !showComments" class="comments-toggle"><i class="far fa-comment"></i> Comment</span>
            <span><i class="fas fa-share"></i> Share</span>
          </div>

        </div>

        <!-- Instagram -->
        <div v-else-if="activeKey==='instagram'" class="mock-card instagram-mock">

          <div class="mock-header">

            <div class="mock-avatar instagram-avatar">
              <i class="fab fa-instagram"></i>
            </div>

            <div class="mock-identity">
              <strong>{{ activePlatform.page }}</strong>
              <small>Sponsored</small>
            </div>

            <a
                :href="platformUrl"
                target="_blank"
                rel="noopener noreferrer"
                class="mock-more"
                :title="'Open on ' + activePlatform.name">
              <i class="fas fa-external-link-alt"></i>
            </a>

          </div>

          <div class="mock-media square" v-if="mediaUrl">
            <img v-if="mediaKind==='image'" :src="mediaUrl">
            <video v-else :src="mediaUrl" :poster="post.thumbnail" controls></video>
          </div>

          <div class="instagram-icons">
            <i class="far fa-heart"></i>
            <i class="far fa-comment"></i>
            <i class="far fa-paper-plane"></i>
            <i class="far fa-bookmark save-icon"></i>
          </div>

          <div class="mock-likes"><strong>{{ engagement.reactionsTotal.toLocaleString() }} likes</strong></div>

          <div class="mock-text">
            <strong>{{ activePlatform.handle }}</strong> {{ post.content }}
          </div>

          <div class="mock-comments-link comments-toggle" @click="showComments = !showComments">
            {{ showComments ? 'Hide comments' : 'View all ' + engagement.commentsCount + ' comments' }}
          </div>

        </div>

        <!-- X -->
        <div v-else-if="activeKey==='x'" class="mock-card x-mock">

          <div class="mock-header">

            <div class="mock-avatar x-avatar">
              <i class="fas fa-building"></i>
            </div>

            <div class="mock-identity">
              <strong>{{ activePlatform.page }} <i class="fas fa-check-circle verified"></i></strong>
              <small>{{ activePlatform.handle }} · {{ post.created_at }}</small>
            </div>

            <a
                :href="platformUrl"
                target="_blank"
                rel="noopener noreferrer"
                class="mock-more"
                :title="'Open on ' + activePlatform.name">
              <i class="fas fa-external-link-alt"></i>
            </a>

          </div>

          <div class="mock-text">{{ post.content }}</div>

          <div class="mock-media rounded" v-if="mediaUrl">
            <img v-if="mediaKind==='image'" :src="mediaUrl">
            <video v-else :src="mediaUrl" :poster="post.thumbnail" controls></video>
          </div>

          <div class="mock-actions x-actions">
            <span class="comments-toggle" @click="showComments = !showComments"><i class="far fa-comment"></i> {{ engagement.commentsCount }}</span>
            <span><i class="fas fa-retweet"></i> {{ engagement.sharesCount }}</span>
            <span><i class="far fa-heart"></i> {{ engagement.reactionsTotal }}</span>
            <span><i class="far fa-bookmark"></i> {{ engagement.bookmarks }}</span>
            <span><i class="far fa-chart-bar"></i> {{ engagement.viewsCount.toLocaleString() }}</span>
          </div>

        </div>

        <!-- LinkedIn -->
        <div v-else-if="activeKey==='linkedin'" class="mock-card linkedin-mock">

          <div class="mock-header">

            <div class="mock-avatar linkedin-avatar">
              <i class="fas fa-building"></i>
            </div>

            <div class="mock-identity">
              <strong>{{ activePlatform.page }}</strong>
              <small>{{ activePlatform.handle }}</small>
              <small>{{ post.created_at }} · <i class="fas fa-globe-americas"></i></small>
            </div>

            <a
                :href="platformUrl"
                target="_blank"
                rel="noopener noreferrer"
                class="mock-more"
                :title="'Open on ' + activePlatform.name">
              <i class="fas fa-external-link-alt"></i>
            </a>

          </div>

          <div class="mock-text">{{ post.content }}</div>

          <div class="mock-media" v-if="mediaUrl">
            <img v-if="mediaKind==='image'" :src="mediaUrl">
            <video v-else :src="mediaUrl" :poster="post.thumbnail" controls></video>
          </div>

          <div class="mock-reactions">

            <div class="reaction-summary">

              <span class="reaction-stack">
                <i
                    v-for="kind in engagement.reactions"
                    :key="kind.key"
                    :class="kind.icon"
                    :style="{color:kind.color}">
                </i>
              </span>

              {{ engagement.reactionsTotal.toLocaleString() }}

            </div>

            <span>
              <span class="comments-toggle" @click="showComments = !showComments">{{ engagement.commentsCount }} comments</span>
              · {{ engagement.sharesCount }} reposts
            </span>

          </div>

          <div class="mock-actions">
            <span><i class="far fa-thumbs-up"></i> Like</span>
            <span class="comments-toggle" @click="showComments = !showComments"><i class="far fa-comment"></i> Comment</span>
            <span><i class="fas fa-share"></i> Repost</span>
            <span><i class="far fa-paper-plane"></i> Send</span>
          </div>

          <div class="impressions-note">
            <i class="fas fa-chart-line"></i> {{ engagement.impressions.toLocaleString() }} impressions
          </div>

        </div>

        <!-- Generic (TikTok / YouTube) -->
        <div v-else class="mock-card generic-mock">

          <div class="mock-header">

            <div class="mock-avatar" :style="{background:activePlatform.color}">
              <i :class="activePlatform.icon"></i>
            </div>

            <div class="mock-identity">
              <strong>{{ activePlatform.page }}</strong>
              <small>{{ activePlatform.handle }}</small>
            </div>

            <a
                :href="platformUrl"
                target="_blank"
                rel="noopener noreferrer"
                class="mock-more"
                :title="'Open on ' + activePlatform.name">
              <i class="fas fa-external-link-alt"></i>
            </a>

          </div>

          <div class="mock-media" v-if="mediaUrl">
            <img v-if="mediaKind==='image'" :src="mediaUrl">
            <video v-else :src="mediaUrl" :poster="post.thumbnail" controls></video>
          </div>

          <div class="mock-text">{{ post.content }}</div>

          <div class="mock-reactions">
            <span><i class="fas fa-heart like-icon"></i> {{ engagement.reactionsTotal.toLocaleString() }}</span>
            <span>
              <span class="comments-toggle" @click="showComments = !showComments">{{ engagement.commentsCount }} comments</span>
              · {{ engagement.sharesCount }} shares · {{ engagement.viewsCount.toLocaleString() }} views
            </span>
          </div>

        </div>

        </div>

        <!-- Comments & replies (shared across platforms) -->
        <div class="comments-panel" v-if="showComments">

          <h5 class="comments-toggle" @click="showComments = !showComments">
            Comments <span class="comments-count">({{ engagement.commentsCount }})</span>
            <i class="fas fa-chevron-up collapse-chevron"></i>
          </h5>

          <div v-if="!engagement.comments.length" class="no-comments">
            No comments yet.
          </div>

          <div
              v-for="comment in engagement.comments"
              :key="comment.id"
              class="comment-thread">

            <div class="comment-row">

              <div class="comment-avatar" :style="{background:comment.avatarColor}">
                {{ initials(comment.author) }}
              </div>

              <div class="comment-body">

                <div class="comment-bubble" :class="{own: comment.isOwn}">
                  <strong>{{ comment.author }}</strong>
                  <div>{{ comment.content }}</div>
                </div>

                <div class="comment-meta">
                  <span>{{ comment.timeAgo }}</span>
                  <span>Like{{ comment.likes ? ' · ' + comment.likes : '' }}</span>
                  <span class="reply-toggle" @click="toggleReply(comment.id)">Reply</span>
                </div>

                <div
                    v-for="(reply, idx) in comment.replies"
                    :key="idx"
                    class="reply-row">

                  <div class="comment-avatar reply-avatar" :style="{background:reply.avatarColor}">
                    {{ initials(reply.author) }}
                  </div>

                  <div class="comment-body">

                    <div class="comment-bubble" :class="{own: reply.isOwn}">
                      <strong>{{ reply.author }}</strong>
                      <div>{{ reply.content }}</div>
                    </div>

                    <div class="comment-meta">
                      <span>{{ reply.timeAgo }}</span>
                      <span>Like{{ reply.likes ? ' · ' + reply.likes : '' }}</span>
                    </div>

                  </div>

                </div>

                <div v-if="replyingToId === comment.id" class="reply-row reply-composer-row">

                  <div class="comment-avatar reply-avatar own-avatar">{{ userInitials }}</div>

                  <div class="composer-input-row">

                    <input
                        type="text"
                        v-model="replyText"
                        placeholder="Write a reply..."
                        @keyup.enter="addReply(comment)">

                    <button
                        class="comment-send-btn"
                        :disabled="!replyText.trim() || submittingReply"
                        @click="addReply(comment)">
                      <i class="fas fa-paper-plane"></i>
                    </button>

                  </div>

                </div>

              </div>

            </div>

          </div>

          <div class="comment-composer">

            <div class="comment-avatar own-avatar">{{ userInitials }}</div>

            <div class="composer-input-row">

              <input
                  type="text"
                  v-model="newCommentText"
                  :placeholder="commentPlaceholder"
                  @keyup.enter="addComment">

              <button
                  class="comment-send-btn"
                  :disabled="!newCommentText.trim() || submittingComment"
                  @click="addComment">
                <i class="fas fa-paper-plane"></i>
              </button>

            </div>

          </div>

        </div>

        </div>

      </div>

      <aside class="preview-sidebar">

        <h4>Also posted on</h4>

        <p class="sidebar-sub">Same post, published across your connected platforms</p>

        <div
            v-for="p in post.platforms"
            :key="p.key"
            class="sidebar-item"
            :class="{active: p.key === activeKey}"
            @click="switchPlatform(p)">

          <div class="sidebar-icon" :style="{background:p.color}">
            <i :class="p.icon"></i>
          </div>

          <div class="sidebar-info">
            <strong>{{ p.name }}</strong>
            <small>{{ post.status }}</small>
          </div>

          <i class="fas fa-chevron-right"></i>

        </div>

        <div class="sidebar-stats">

          <div class="stat">
            <strong>{{ engagement.reactionsTotal.toLocaleString() }}</strong>
            <small>Likes</small>
          </div>

          <div class="stat">
            <strong>{{ engagement.commentsCount }}</strong>
            <small>Comments</small>
          </div>

          <div class="stat">
            <strong>{{ engagement.viewsCount.toLocaleString() }}</strong>
            <small>Views</small>
          </div>

        </div>

      </aside>

    </div>

  </div>
</template>

<script>
import { platformMeta, reactionKindsByPlatform } from '../../data/mockPosts';

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
      default: '/admin/posts'
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

    engagement() {

      if (!this.post) return null;

      return this.post.engagement[this.activeKey];

    },

    platformUrl() {

      if (!this.post) return '#';

      return this.post.platformUrls[this.activeKey] || '#';

    },

    userInitials() {

      return this.userName
          .split(' ')
          .filter(Boolean)
          .slice(0, 2)
          .map(part => part[0].toUpperCase())
          .join('');

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

    },

    mediaUrl() {

      if (!this.post) return null;

      return this.post.image || this.post.video || null;

    },

    mediaKind() {

      if (!this.post) return null;

      return this.post.video ? 'video' : 'image';

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
          page: member.account_name || meta.page,
          handle: member.account_handle || meta.handle
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

      });

      return {

        ...raw,

        platforms,

        engagement,

        platformUrls

      };

    },

    switchPlatform(p) {

      this.activeKey = p.key;

      if (window.history && window.history.replaceState) {

        // p.post_id is that platform's own Post row - reloading/sharing
        // this URL re-fetches the whole group regardless of which member's
        // id is in it, but pointing at the right one keeps the URL honest.
        window.history.replaceState(null, '', `${this.backUrl}/${p.post_id || this.post.id}/preview/${p.key}`);

      }

    },

    initials(name) {

      return name
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

      window.axios.post(`${this.backUrl}/${this.post.id}/comments`, {
        content: text
      }).then(({ data }) => {

        this.engagement.comments.push({
          ...data.comment,
          avatarColor: colorForName(data.comment.author)
        });

        this.engagement.commentsCount++;
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

.preview-page{
  background:#F6F9FC;
  min-height:100vh;
  padding:30px;
}

.preview-topbar{
  display:flex;
  align-items:center;
  gap:18px;
  margin-bottom:24px;
}

.preview-topbar h2{
  font-size:24px;
  font-weight:700;
  color:#2A3547;
  margin:0;
}

.back-link{
  display:flex;
  align-items:center;
  gap:8px;
  color:#5D87FF;
  font-weight:600;
  text-decoration:none;
}

.preview-status{
  margin-left:auto;
  padding:6px 16px;
  border-radius:30px;
  color:#fff;
  font-size:12px;
  font-weight:600;
}

.preview-status.published{ background:#13DEB9; }
.preview-status.scheduled{ background:#FFAE1F; }
.preview-status.failed{ background:#FA896B; }

.not-found{
  background:#fff;
  border-radius:18px;
  padding:60px;
  text-align:center;
  color:#7C8FAC;
}

.not-found i{ margin-bottom:16px; color:#CBD5E1; }

.preview-layout{
  display:grid;
  grid-template-columns:1fr 340px;
  gap:28px;
  align-items:start;
}

@media(max-width:992px){
  .preview-layout{
    grid-template-columns:1fr;
  }
}

.platform-switch-tabs{
  display:flex;
  gap:10px;
  flex-wrap:wrap;
  margin-bottom:20px;
}

.switch-tab{
  display:flex;
  align-items:center;
  gap:8px;
  padding:10px 18px;
  border-radius:30px;
  border:1px solid #E5E7EB;
  background:#fff;
  font-weight:600;
  font-size:14px;
  color:#2A3547;
  cursor:pointer;
  transition:.2s;
}

.switch-tab.active{
  background:#5D87FF;
  border-color:#5D87FF;
  color:#fff;
}

.mock-card{
  background:#fff;
  border-radius:18px;
  padding:22px;
  box-shadow:0 10px 30px rgba(0,0,0,.06);
  max-width:560px;
}

.mock-header{
  display:flex;
  align-items:center;
  gap:12px;
  margin-bottom:14px;
}

.mock-avatar{
  width:44px;
  height:44px;
  border-radius:50%;
  background:#5D87FF;
  color:#fff;
  display:flex;
  align-items:center;
  justify-content:center;
  font-size:18px;
  flex-shrink:0;
}

.instagram-avatar{
  background:linear-gradient(135deg,#F58529,#DD2A7B,#8134AF);
}

.x-avatar{ background:#111827; }
.linkedin-avatar{ background:#0A66C2; }

.mock-identity{
  display:flex;
  flex-direction:column;
  line-height:1.4;
}

.mock-identity small{
  color:#7C8FAC;
  font-size:12px;
}

.mock-more{
  margin-left:auto;
  color:#7C8FAC;
  transition:.2s;
}

.mock-more:hover{
  color:#5D87FF;
}

.verified{
  color:#1D9BF0;
  font-size:13px;
}

.mock-text{
  color:#2A3547;
  line-height:1.6;
  margin-bottom:14px;
  white-space:pre-line;
}

.mock-media{
  border-radius:12px;
  overflow:hidden;
  margin-bottom:14px;
  background:#000;
}

.mock-media img,
.mock-media video{
  width:100%;
  max-height:420px;
  object-fit:cover;
  display:block;
}

.mock-media.square img,
.mock-media.square video{
  aspect-ratio:1/1;
  max-height:none;
}

.mock-media.rounded{
  border:1px solid #E5E7EB;
}

.mock-reactions{
  display:flex;
  justify-content:space-between;
  color:#7C8FAC;
  font-size:13px;
  border-bottom:1px solid #EDF2F7;
  padding-bottom:12px;
  margin-bottom:6px;
}

.like-icon{ color:#5D87FF; }

.mock-actions{
  display:none;
  justify-content:space-around;
  padding-top:8px;
  font-size:14px;
  font-weight:600;
  color:#7C8FAC;
}

.mock-actions.x-actions{
  justify-content:space-between;
  font-weight:400;
  color:#7C8FAC;
}

.instagram-icons{
  display:flex;
  gap:16px;
  font-size:22px;
  color:#2A3547;
  margin-bottom:10px;
}

.save-icon{
  margin-left:auto;
}

.mock-likes{
  margin-bottom:8px;
}

.mock-comments-link{
  color:#7C8FAC;
  font-size:13px;
  margin-top:6px;
}

.preview-sidebar{
  background:#fff;
  border-radius:18px;
  padding:22px;
  box-shadow:0 10px 30px rgba(0,0,0,.06);
  position:sticky;
  top:20px;
  align-self:start;
  max-height:calc(100vh - 40px);
  overflow-y:auto;
}

.sticky-post{
  position:sticky;
  top:20px;
  align-self:start;
  z-index:2;
}

.post-comments-row.side-by-side{
  display:grid;
  grid-template-columns:1fr 380px;
  gap:24px;
  align-items:start;
}

.post-comments-row.side-by-side .sticky-post{
  margin:0;
}

.post-comments-row.side-by-side .comments-panel{
  margin-top:0;
  position:sticky;
  top:20px;
  max-height:calc(100vh - 40px);
  overflow-y:auto;
}

@media(max-width:992px){

  .preview-sidebar,
  .sticky-post{
    position:static;
    max-height:none;
  }

  .post-comments-row.side-by-side{
    display:block;
  }

  .post-comments-row.side-by-side .comments-panel{
    position:static;
    max-height:none;
    margin-top:20px;
  }

}

.preview-sidebar h4{
  margin:0 0 4px;
  color:#2A3547;
}

.sidebar-sub{
  color:#7C8FAC;
  font-size:13px;
  margin-bottom:18px;
}

.sidebar-item{
  display:flex;
  align-items:center;
  gap:12px;
  padding:12px;
  border-radius:12px;
  cursor:pointer;
  transition:.2s;
  margin-bottom:8px;
}

.sidebar-item:hover{
  background:#F6F9FC;
}

.sidebar-item.active{
  background:#ECF2FF;
}

.sidebar-icon{
  width:38px;
  height:38px;
  border-radius:50%;
  display:flex;
  align-items:center;
  justify-content:center;
  color:#fff;
  flex-shrink:0;
}

.sidebar-info{
  display:flex;
  flex-direction:column;
  flex:1;
}

.sidebar-info small{
  color:#7C8FAC;
  font-size:12px;
}

.sidebar-item .fa-chevron-right{
  color:#CBD5E1;
  font-size:12px;
}

.sidebar-stats{
  display:flex;
  justify-content:space-between;
  border-top:1px solid #EDF2F7;
  margin-top:14px;
  padding-top:16px;
}

.sidebar-stats .stat{
  text-align:center;
}

.sidebar-stats strong{
  display:block;
  font-size:18px;
  color:#2A3547;
}

.sidebar-stats small{
  color:#7C8FAC;
  font-size:12px;
}

/* ==========================
   Reaction stacks
========================== */

.reaction-summary{
  display:flex;
  align-items:center;
  gap:6px;
}

.reaction-stack{
  display:inline-flex;
}

.reaction-stack i{
  width:18px;
  height:18px;
  font-size:11px;
  border-radius:50%;
  background:#fff;
  border:2px solid #fff;
  display:flex;
  align-items:center;
  justify-content:center;
  box-shadow:0 0 0 1px rgba(0,0,0,.06);
  margin-left:-6px;
}

.reaction-stack i:first-child{
  margin-left:0;
}

.impressions-note{
  margin-top:10px;
  padding-top:10px;
  border-top:1px solid #EDF2F7;
  color:#7C8FAC;
  font-size:13px;
}

.impressions-note i{
  color:#0A66C2;
  margin-right:6px;
}

/* ==========================
   Comments & replies
========================== */

.comments-panel{
  background:#fff;
  border-radius:18px;
  padding:22px;
  box-shadow:0 10px 30px rgba(0,0,0,.06);
  max-width:560px;
  margin-top:20px;
}

.comments-panel h5{
  margin:0 0 16px;
  color:#2A3547;
  font-weight:700;
  display:flex;
  align-items:center;
  gap:8px;
  cursor:pointer;
}

.comments-count{
  color:#7C8FAC;
  font-weight:400;
}

.comments-toggle{
  cursor:pointer;
}

.comments-toggle:hover{
  text-decoration:underline;
}

.collapse-chevron{
  margin-left:auto;
  font-size:12px;
  color:#7C8FAC;
}

.no-comments{
  color:#7C8FAC;
  font-size:14px;
}

.comment-thread + .comment-thread{
  margin-top:18px;
}

.comment-row{
  display:flex;
  gap:10px;
}

.comment-avatar{
  width:36px;
  height:36px;
  border-radius:50%;
  color:#fff;
  font-size:13px;
  font-weight:700;
  display:flex;
  align-items:center;
  justify-content:center;
  flex-shrink:0;
}

.reply-avatar{
  width:30px;
  height:30px;
  font-size:11px;
}

.comment-body{
  flex:1;
}

.comment-bubble{
  background:#F1F5F9;
  border-radius:16px;
  padding:10px 14px;
  color:#2A3547;
  font-size:14px;
  line-height:1.5;
}

.comment-bubble strong{
  display:block;
  font-size:13px;
  margin-bottom:2px;
}

.comment-meta{
  display:flex;
  gap:14px;
  margin-top:6px;
  margin-left:6px;
  font-size:12px;
  font-weight:600;
  color:#7C8FAC;
}

.reply-row{
  display:flex;
  gap:10px;
  margin-top:12px;
  margin-left:24px;
  padding-left:14px;
  border-left:2px solid #EDF2F7;
}

.reply-toggle{
  cursor:pointer;
}

.reply-toggle:hover{
  text-decoration:underline;
}

.comment-bubble.own{
  background:#ECF2FF;
}

/* ==========================
   Open on Platform
========================== */

.preview-toolbar-row{
  display:none;
  align-items:center;
  justify-content:space-between;
  flex-wrap:wrap;
  gap:14px;
  margin-bottom:20px;
}

.preview-toolbar-row .platform-switch-tabs{
  margin-bottom:0;
  flex:1;
}

/* ==========================
   Comment / reply composer
========================== */

.comment-composer{
  display:flex;
  gap:10px;
  margin-top:20px;
  padding-top:18px;
  border-top:1px solid #EDF2F7;
}

.own-avatar{
  background:#5D87FF;
}

.composer-input-row{
  flex:1;
  display:flex;
  align-items:center;
  gap:8px;
  background:#F1F5F9;
  border-radius:30px;
  padding:6px 6px 6px 16px;
}

.composer-input-row input{
  flex:1;
  border:none;
  outline:none;
  box-shadow:none;
  background:transparent;
  font-size:14px;
  color:#2A3547;
}

.composer-input-row input:focus,
.composer-input-row input:focus-visible{
  border:none;
  outline:none;
  box-shadow:none;
}

.comment-send-btn{
  width:32px;
  height:32px;
  border-radius:50%;
  border:none;
  background:#5D87FF;
  color:#fff;
  display:flex;
  align-items:center;
  justify-content:center;
  flex-shrink:0;
  transition:.2s;
}

.comment-send-btn:disabled{
  background:#CBD5E1;
  cursor:not-allowed;
}

.reply-composer-row{
  align-items:center;
}

</style>
