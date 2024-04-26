<template>
  <div class="row" :class="modifiers">
    <div v-if="hasBg" class="row__bg">
      <video
        v-if="video"
        class="section__video"
        type="video/mp4"
        :src="video"
        autoplay
        muted
        loop
      ></video>
      <img v-if="image" class="row__image" :src="image" fit />
    </div>
    <div class="row__wrap">
      <slot></slot>
    </div>
  </div>
</template>

<script lang="ts">
import { defineComponent, ComponentPropsOptions } from "vue";
import { useModifiers, hasBackground } from "@/utils/component";
import props from "@/components/row";
import { DefaultPropertyStructure } from "@/components/directives/properties";

export default defineComponent({
  // eslint-disable-next-line vue/multi-word-component-names
  name: "Row",
  props: props as ComponentPropsOptions<DefaultPropertyStructure>,
  setup(props: DefaultPropertyStructure) {
    const hasBg = hasBackground(props);
    return { modifiers: useModifiers(props, "row"), hasBg };
  },
});
</script>
