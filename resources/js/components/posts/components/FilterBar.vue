<template>

  <div class="filter-card">

    <input
        type="text"
        class="form-control"
        placeholder="Search posts..."
        v-model="local.search">

    <select
        class="form-control"
        v-model="local.status">

      <option value="">All Status</option>
      <option>Published</option>
      <option>Scheduled</option>
      <option>Failed</option>

    </select>

    <select
        class="form-control"
        v-model="local.platform">

      <option value="">All Platforms</option>
      <option>Facebook</option>
      <option>Instagram</option>
      <option>LinkedIn</option>
      <option>YouTube</option>

    </select>

    <select
        class="form-control"
        v-model="local.sort">

      <option value="latest">Latest</option>
      <option value="oldest">Oldest</option>

    </select>

  </div>

</template>

<script>

export default{

  props:['value'],

  computed:{

    local:{

      get(){

        return this.value

      },

      set(v){

        this.$emit('input',v)

      }

    }

  }

}

</script>

<style scoped>

.filter-card{

  background:#fff;

  border-radius:16px;

  padding:20px;

  box-shadow:0 5px 18px rgba(0,0,0,.05);

  display:grid;

  grid-template-columns:2fr 1fr 1fr 1fr;

  gap:15px;

}

.form-control{

  height:48px;

  border-radius:10px;

}

@media(max-width:992px){

  .filter-card{

    grid-template-columns:1fr;

  }

}

</style>