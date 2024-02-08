<template>
  <a v-if="permalink" :href="permalink" class="project-card">
    <img v-if="image" class="project-card__image"
         :src="image"/>
    <span v-if="title" class="project-card__content">
      <span class="heading heading--medium project-card__heading">{{ title }}</span>
      <span class="project-card__learn-more">Learn More</span>
    </span>
  </a>
</template>

<script>
export default {
  name: 'ProjectCard',
  props: {
    permalink: String,
    image: String,
    title: String
  }
}
</script>
