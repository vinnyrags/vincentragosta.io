<template>
  <div class="flex-row" :class="additionalClasses()" :style="cssVars">
    <div v-if="hasBg(this.$props)" class="flex-row__bg">
      <video v-if="video" class="flex-row__video" type="video/mp4" :src="video" autoplay muted loop></video>
      <img v-if="image" class="flex-row__image" :src="image"/>
    </div>
    <div class="flex-row__wrap">
      <slot></slot>
    </div>
  </div>
</template>

<script>

import componentContainerProps from "@/assets/scripts/component-container/props/props";
import { hasBg } from "@/assets/scripts/component-container/methods/has-bg";
import { cssVars } from "@/assets/scripts/component-container/computed/css-vars";
import { componentContainerModifiers } from "@/assets/scripts/component-container/methods/modifiers";

export default {
  // eslint-disable-next-line vue/multi-word-component-names
  name: 'FlexRow',
  props: {
    ...componentContainerProps
  },
  components: {},
  methods: {
    hasBg,
    modifiers() {
      let themeModifiers = [];
      return [...themeModifiers, ...(componentContainerModifiers(this.$props))].map((modifier) => {
        return 'flex-row--' + modifier;
      });
    },
    extraClasses() {
      return [];
    },
    additionalClasses() {
      return [...this.modifiers(), ...this.extraClasses()].join(' ');
    },
  },
  computed: {
    cssVars
  }
}
</script>