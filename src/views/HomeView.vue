<template>
  <article>
    <h1>Testing</h1>
    <Section>
      <Row><Column>Hello!</Column></Row>
    </Section>
  </article>
</template>

<script lang="ts">
import { defineComponent } from "vue";
import Section from "@/components/section/Component.vue";
import Row from "@/components/row/Component.vue";
import Column from "@/components/column/Component.vue";

export default defineComponent({
  name: "HomeView",
  components: {
    Column,
    Row,
    Section,
  },
});
</script>
