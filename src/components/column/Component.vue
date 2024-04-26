<template>
  <div class="column" :class="modifiers">
    <div class="column__bg" v-if="hasBg">
      <video
        v-if="video"
        class="column__video"
        type="video/mp4"
        :src="video"
        autoplay
        muted
        loop
      ></video>
      <Image class="section__image" v-if="image" :src="image" fit />
    </div>
    <div class="column__wrap">
      <slot></slot>
    </div>
  </div>
</template>

<script lang="ts">
import { defineComponent, ComponentPropsOptions } from "vue";
import { useModifiers, hasBackground } from "@/utils/component";
import props, { handleViewportProps } from "@/components/column";
import { DefaultPropertyStructure } from "@/components/directives/properties";

export default defineComponent({
  // eslint-disable-next-line vue/multi-word-component-names
  name: "Column",
  props: props as ComponentPropsOptions<DefaultPropertyStructure>,
  setup(props: DefaultPropertyStructure) {
    const hasBg = hasBackground(props);
    return {
      modifiers: useModifiers(props, "column", handleViewportProps),
      hasBg,
    };
  },
});
</script>
