<template>
  <section class="section" :class="modifiers">
    <div v-if="hasBg" class="section__bg">
      <video
        v-if="video"
        class="section__video"
        type="video/mp4"
        :src="video"
        autoplay
        muted
        loop
      ></video>
      <img v-if="image" class="section__image" :src="image" fit />
    </div>
    <div class="section__wrap">
      <slot></slot>
    </div>
  </section>
</template>

<script lang="ts">
import { defineComponent, ComponentPropsOptions } from "vue";
import { useModifiers, hasBackground } from "@/utils/component";
import props from "@/components/Section";
import { DefaultPropertyStructure } from "@/directives/properties";

export default defineComponent({
  // eslint-disable-next-line vue/multi-word-component-names
  name: "Section",
  props: props as ComponentPropsOptions<DefaultPropertyStructure>,
  setup(props: DefaultPropertyStructure) {
    const hasBg = hasBackground(props);
    return { modifiers: useModifiers(props, "section"), hasBg };
  },
});
</script>
