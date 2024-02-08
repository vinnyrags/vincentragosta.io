<template>
  <div class="highlight-card" v-if="highlight">
    <Heading :level="3" size="medium">{{ highlight.name }}</Heading>
    <div class="highlight-card__list list" v-for="post in highlight.posts" :key="post.id">
      <Heading :level="4">{{ post.postTitle }}</Heading>
      <p>{{ post.postContent }}</p>
    </div>
  </div>
</template>

<script>
import Heading from "@/elements/Heading.vue";
export default {
  name: 'HightlightCard',
  props: {
    highlight: Object
  },
  components: {
    Heading
  }
}
</script>

<!-- Add "scoped" attribute to limit CSS to this component only -->
<style scoped>

</style>
