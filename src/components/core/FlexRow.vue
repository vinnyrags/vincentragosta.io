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

import sharedContainer from "@/assets/scripts/shared-container/SharedContainer";
import halignmentProps from '@/assets/scripts/shared-container/props/halignment';
import {hasBg} from "@/assets/scripts/shared-container/methods/has-bg";
import {cssVars} from "@/assets/scripts/shared-container/computed/css-vars";

export default {
  // eslint-disable-next-line vue/multi-word-component-names
  name: 'FlexRow',
  props: {
    ...sharedContainer.props,
    ...halignmentProps
  },
  components: {},
  methods: {
    hasBg,
    modifiers() {
      let themeModifiers = [];
      return [...themeModifiers, ...(sharedContainer.methods.modifiers(this.$props))].map((modifier) => {
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