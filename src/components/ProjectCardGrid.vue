<template>
  <Grid class="project-card-grid" fluid v-if="cards">
    <Column sm="6" :lg="setColumnLargeBreakpoint(index+1)" v-for="(card, index) in cards" :key="card.title + encodeURI(card.url) + encodeURI(card.image)">
      <ProjectCard :permalink="card.url" :image="card.image" :title="card.title"></ProjectCard>
    </Column>
  </Grid>
</template>

<script>
import ProjectCard from '@/components/ProjectCard.vue';
import Grid from "@/components/Grid.vue";
import Column from "@/components/GridColumn.vue";
export default {
  name: 'ProjectCardGrid',
  components: {
    Column,
    Grid,
    ProjectCard
  },
  props: {
    cards: Array
  },
  methods: {
    setColumnLargeBreakpoint(index) {
      let breakpoint = '3';
      if (
          index !== 1 && index % 3 === 0 && index % 6 !== 0 ||
          index !== 1 && index % 4 === 0
      ) {
        breakpoint = '6';
      }
      return breakpoint;
    }
  }
}
</script>
