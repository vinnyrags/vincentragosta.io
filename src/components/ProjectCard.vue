<template>
  <a class="project-card" v-if="permalink" :href="permalink">
    <Image class="project-card__image" v-if="image" :src="image" fit></Image>
    <span v-if="title" class="project-card__content">
      <span class="heading heading--medium project-card__heading">{{ title }}</span>
      <span class="project-card__learn-more">Learn More</span>
    </span>
  </a>
</template>

<script>
import Image from "@/components/Image.vue";

export default {
  name: 'ProjectCard',
  props: {
    permalink: String,
    image: String,
    title: String
  },
  components: {
    Image
  }
}
</script>
