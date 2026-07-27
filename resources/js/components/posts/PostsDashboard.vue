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
    <!-- Statistics -->
    <div class="platform-tabs">

      <div
          v-for="platform in platforms"
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
    <!-- Platform Overview -->

    <div class="section-title">

      <div>

        <h3>Recent Posts</h3>

        <p>{{ posts.length }} Posts Available</p>

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
              class="video-wrapper">

            <video
                :poster="post.thumbnail"
                preload="metadata">

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
              class="video-wrapper">

            <video
                muted
                loop
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

          <!-- Reel -->
          <video
              v-else-if="post.type==='reel'"
              :poster="post.thumbnail"
              muted
              loop>

            <source
                :src="post.video"
                type="video/mp4">

          </video>

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

              <div
                  v-for="platform in post.platforms"
                  :key="platform.name"
                  class="platform-avatar"
                  :title="platform.name">

                <i
                    :class="platform.icon"
                    :style="{color:platform.color}">
                </i>

              </div>

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

  </div>
</template>

<script>
export default {

  props: {

    platform: {
      type: String,
      default: ''
    },

    createUrl: {
      type: String,
      default: ''
    }

  },

  data(){

    return{
      pagination: {

        currentPage: 1,

        perPage: 6

      },
      activePlatform: 'All',
      posts: [

        {
          id: 1,
          type: 'image',

          platforms: [
            {
              name:'Facebook',
              icon:'fab fa-facebook-f',
              color:'#1877F2'
            },
            {
              name:'Instagram',
              icon:'fab fa-instagram',
              color:'#E1306C'
            },
            {
              name:'LinkedIn',
              icon:'fab fa-linkedin-in',
              color:'#0A66C2'
            }
          ],

          status:'Published',
          title:'Summer Mega Sale',
          content:'Enjoy up to 50% OFF on all electronics this weekend only.',
          image:'https://picsum.photos/800/450?1',

          likes:582,
          comments:63,
          shares:21,
          views:1420,

          author:'John Smith',
          created_at:'26 Jul 2026'
        },

        {
          id: 2,
          type: 'video',
          platform: 'Instagram',
          icon: 'fab fa-instagram',
          color: '#E1306C',
          status: 'Published',
          title: 'Summer Collection Reel',
          content: 'Watch our newest fashion collection.',
          thumbnail: 'https://picsum.photos/800/450?22',
          video: 'https://www.w3schools.com/html/mov_bbb.mp4',
          likes: 1450,
          comments: 84,
          shares: 42,
          views: 8620,
          author: 'Sarah',
          created_at: '25 Jul 2026'
        },

        {
          id: 3,
          type: 'carousel',
          platform: 'Facebook',
          icon: 'fab fa-facebook-f',
          color: '#1877F2',
          status: 'Scheduled',
          title: 'Top 10 Products',
          content: 'Swipe through our best selling products.',
          image: 'https://picsum.photos/800/450?3',
          likes: 310,
          comments: 22,
          shares: 8,
          views: 1200,
          author: 'Marketing',
          created_at: '24 Jul 2026'
        },

        {
          id: 4,
          type: 'reel',
          platform: 'Instagram',
          icon: 'fab fa-instagram',
          color: '#E1306C',
          status: 'Published',
          title: 'Behind The Scenes',
          content: 'A quick look inside our studio.',
          thumbnail: 'https://picsum.photos/800/450?44',
          video: 'https://www.w3schools.com/html/movie.mp4',
          likes: 2200,
          comments: 163,
          shares: 93,
          views: 12000,
          author: 'Creative Team',
          created_at: '23 Jul 2026'
        },

        {
          id: 5,
          type: 'image',
          platform: 'LinkedIn',
          icon: 'fab fa-linkedin-in',
          color: '#0A66C2',
          status: 'Published',
          title: 'Hiring Laravel Developers',
          content: 'Join our engineering team.',
          image: 'https://picsum.photos/800/450?5',
          likes: 520,
          comments: 31,
          shares: 15,
          views: 2700,
          author: 'HR Team',
          created_at: '22 Jul 2026'
        },

        {
          id: 6,
          type: 'video',
          platform: 'YouTube',
          icon: 'fab fa-youtube',
          color: '#FF0000',
          status: 'Published',
          title: 'Product Demo',
          content: 'Watch our latest product demo.',
          thumbnail: 'https://picsum.photos/800/450?66',
          video: 'https://www.w3schools.com/html/mov_bbb.mp4',
          likes: 5400,
          comments: 360,
          shares: 220,
          views: 28500,
          author: 'Media Team',
          created_at: '20 Jul 2026'
        }

      ],
      filters:{

        search:'',
        status:'',
        sort:'latest'

      },

      gridView:true,
      platforms: [
        {
          name: 'All',
          icon: 'fas fa-globe',
          count: 248,
          color: '#5D87FF'
        },
        {
          name: 'Facebook',
          icon: 'fab fa-facebook-f',
          count: 124,
          color: '#1877F2'
        },
        {
          name: 'Instagram',
          icon: 'fab fa-instagram',
          count: 89,
          color: '#E1306C'
        },
        {
          name: 'X',
          icon: 'fab fa-x-twitter',
          count: 58,
          color: '#111827'
        },
        {
          name: 'LinkedIn',
          icon: 'fab fa-linkedin-in',
          count: 41,
          color: '#0A66C2'
        },
        {
          name: 'TikTok',
          icon: 'fab fa-tiktok',
          count: 36,
          color: '#111827'
        },
        {
          name: 'YouTube',
          icon: 'fab fa-youtube',
          count: 18,
          color: '#FF0000'
        }
      ]

    }

  },

  mounted() {

    const original = [...this.posts];

    let id = this.posts.length + 1;

    for (let i = 0; i < 7; i++) {

      original.forEach(post => {

        this.posts.push({

          ...post,

          id: id++,

          title: post.title + ' #' + id

        });

      });

    }

  },
  computed: {

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
    platformCards(){

      return [

        {
          name:'All',
          icon:'fas fa-globe',
          color:'#5D87FF',
          count:this.posts.length
        },

        {
          name:'Facebook',
          icon:'fab fa-facebook-f',
          color:'#1877F2',
          count:this.posts.filter(x=>x.platform=='Facebook').length
        },


      ]

    },

    filteredPosts() {

      let posts = this.posts;

      if (this.activePlatform !== 'All') {
        posts = posts.filter(post => post.platform === this.activePlatform);
      }

      if (this.filters.search) {

        const keyword = this.filters.search.toLowerCase();

        posts = posts.filter(post =>
            post.platforms.some(
                p => p.name === this.activePlatform
            )
        );

      }

      if (this.filters.status) {

        posts = posts.filter(post => post.status === this.filters.status);

      }

      return posts;

    }

  },
  methods: {

    createPost() {

      window.location.href = this.createUrl;

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
</style>