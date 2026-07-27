<template>

  <div class="stats-row">

    <div
        class="stat-card"
        v-for="card in cards"
        :key="card.title">

      <div class="icon">

        <i :class="card.icon"></i>

      </div>

      <div>

        <div class="value">

          {{ card.value }}

        </div>

        <div class="title">

          {{ card.title }}

        </div>

      </div>

    </div>

  </div>

</template>

<script>

export default{

  props:['stats'],

  computed:{

    cards(){

      return[
        {
          title:'Total Posts',
          value:this.stats.total,
          icon:'fas fa-layer-group'
        },
        {
          title:'Published',
          value:this.stats.published,
          icon:'fas fa-check-circle'
        },
        {
          title:'Scheduled',
          value:this.stats.scheduled,
          icon:'fas fa-clock'
        },
        {
          title:'Failed',
          value:this.stats.failed,
          icon:'fas fa-times-circle'
        }
      ]

    }

  }

}

</script>

<style scoped>

.stats-row{

  display:grid;

  grid-template-columns:repeat(4,1fr);

  gap:20px;

  margin-bottom:30px;

}

.stat-card{

  background:#fff;

  border-radius:16px;

  padding:24px;

  display:flex;

  align-items:center;

  gap:18px;

  box-shadow:0 6px 18px rgba(0,0,0,.05);

  transition:.3s;

}

.stat-card:hover{

  transform:translateY(-5px);

}

.icon{

  width:60px;

  height:60px;

  border-radius:50%;

  background:#ECF2FF;

  display:flex;

  align-items:center;

  justify-content:center;

  color:#5D87FF;

  font-size:24px;

}

.value{

  font-size:28px;

  font-weight:700;

}

.title{

  color:#7C8FAC;

}

@media(max-width:992px){

  .stats-row{

    grid-template-columns:repeat(2,1fr);

  }

}

</style>