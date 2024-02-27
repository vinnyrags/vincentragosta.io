<template>
  <div class="row" :class="additionalClasses()" :style="cssVars">
    <div v-if="hasBg(this.$props)" class="row__bg">
      <video v-if="video" class="row__video" type="video/mp4" :src="video" autoplay muted loop></video>
      <img v-if="image" class="row__image" :src="image"/>
    </div>
    <div class="row__wrap">
      <slot></slot>
    </div>
  </div>
</template>

<script>

import sharedContainer from "@/assets/scripts/components/shared-container";
import halignmentProps from '@/assets/scripts/props/alignment/halignment';
import {hasBg} from "@/assets/scripts/functions/bg/hasBg";
import {cssVars} from "@/assets/scripts/functions/cssVars";

export default {
  // eslint-disable-next-line vue/multi-word-component-names
  name: 'Row',
  props: {
    ...sharedContainer.props,
    ...halignmentProps
  },
  components: {},
  methods: {
    hasBg,
    modifiers() {
      let modifiers = [];
      return [...modifiers, ...(sharedContainer.methods.modifiers(this.$props))].map((modifier) => {
        return 'row--' + modifier;
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