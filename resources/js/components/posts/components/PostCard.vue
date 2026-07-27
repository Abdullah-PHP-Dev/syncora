<template>
  <div class="post-card">

    <!-- Media -->
    <div class="media-wrapper">

      <img
          v-if="imageUrl"
          :src="imageUrl"
          class="media-image"
      >

      <div
          v-else
          class="media-empty">

        <i class="fas fa-image fa-2x"></i>

      </div>

      <span
          class="status-badge"
          :class="badgeClass">

                {{ statusText }}

            </span>

    </div>

    <!-- Body -->

    <div class="card-body">

      <div class="card-top">

                <span class="platform">

                    {{ post.platform }}

                </span>

        <div class="dropdown">

          <button
              class="btn btn-link text-muted"
              data-bs-toggle="dropdown">

            <i class="fas fa-ellipsis-v"></i>

          </button>

          <ul class="dropdown-menu dropdown-menu-end">

            <li>

              <a
                  class="dropdown-item"
                  @click="$emit('view', post)">

                <i class="fas fa-eye me-2"></i>

                View

              </a>

            </li>

            <li>

              <a
                  class="dropdown-item text-danger"
                  @click="$emit('delete', post)">

                <i class="fas fa-trash me-2"></i>

                Delete

              </a>

            </li>

          </ul>

        </div>

      </div>

      <div class="content">

        {{ shortContent }}

      </div>

      <div class="dates">

        <div>

          <small>Created</small>

          <strong>{{ formatDate(post.created_at) }}</strong>

        </div>

        <div>

          <small>Scheduled</small>

          <strong>{{ formatDate(post.schedule_at) }}</strong>

        </div>

      </div>

    </div>

  </div>

</template>

<script>
export default {

  props: {

    post: {
      type: Object,
      required: true
    }

  },

  computed: {

    imageUrl() {

      if (this.post.media && this.post.media.length) {
        return this.post.media[0].media_url;
      }

      return this.post.url || null;

    },

    shortContent() {

      if (!this.post.content)
        return '';

      return this.post.content.length > 120
          ? this.post.content.substring(0, 120) + '...'
          : this.post.content;

    },

    statusText() {

      if (this.post.status === 'completed')
        return 'Published';

      if (this.post.status === 'failed')
        return 'Failed';

      if (this.post.schedule_mode == 1)
        return 'Scheduled';

      return this.post.status;

    },

    badgeClass() {

      if (this.post.status === 'completed')
        return 'success';

      if (this.post.status === 'failed')
        return 'danger';

      if (this.post.schedule_mode == 1)
        return 'warning';

      return 'secondary';

    }

  },

  methods: {

    formatDate(date) {

      if (!date)
        return '-';

      return new Date(date).toLocaleString();

    }

  }

}
</script>

<style scoped>

.post-card{

  background:#fff;
  border-radius:18px;
  overflow:hidden;
  box-shadow:0 5px 20px rgba(0,0,0,.05);
  transition:.3s;
}

.post-card:hover{

  transform:translateY(-6px);
  box-shadow:0 12px 35px rgba(0,0,0,.10);

}

.media-wrapper{

  height:220px;
  position:relative;
  background:#eef3f9;

}

.media-image{

  width:100%;
  height:100%;
  object-fit:cover;

}

.media-empty{

  height:100%;
  display:flex;
  align-items:center;
  justify-content:center;
  color:#a0aec0;

}

.status-badge{

  position:absolute;
  top:15px;
  right:15px;
  color:#fff;
  padding:6px 14px;
  border-radius:50px;
  font-size:12px;
  font-weight:600;

}

.success{background:#13DEB9;}
.warning{background:#FFAE1F;}
.danger{background:#FA896B;}
.secondary{background:#5A6A85;}

.card-body{

  padding:20px;

}

.card-top{

  display:flex;
  justify-content:space-between;
  align-items:center;

}

.platform{

  font-weight:600;
  color:#5D87FF;

}

.content{

  margin:18px 0;
  color:#2A3547;
  line-height:1.6;
  min-height:70px;

}

.dates{

  display:flex;
  justify-content:space-between;
  margin-top:20px;
  border-top:1px solid #eef2f7;
  padding-top:15px;

}

.dates small{

  display:block;
  color:#7c8fac;

}

.dates strong{

  font-size:13px;

}
</style>