<template>
  <article>
    <h1>Testing</h1>
    <Section>Hello!</Section>
  </article>
</template>

<script lang="ts">
import { defineComponent } from "vue";
import Section from "@/components/Section/Section.vue";

export default defineComponent({
  name: "HomeView",
  components: {
    Section,
  },
});
</script>
