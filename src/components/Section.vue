<template>
  <section class="section" :class="additionalClasses()" :style="cssVars">
    <div v-if="hasBg(this.$props)" class="section__bg">
      <video v-if="video" class="section__video" type="video/mp4" :src="video" autoplay muted loop></video>
      <img v-if="image" class="section__image" :src="image"/>
    </div>
    <div class="section__wrap">
      <slot></slot>
    </div>
  </section>
</template>

<script>
// TODO add README somewhere clever
import sharedContainer from "@/assets/scripts/components/shared-container";
import {hasBg} from "@/assets/scripts/functions/bg/hasBg";
import {cssVars} from "@/assets/scripts/functions/cssVars";

export default {
  // eslint-disable-next-line vue/multi-word-component-names
  name: 'Section',
  props: {
    ...sharedContainer.props,
    wide: Boolean,
    narrow: Boolean,
    fluid: Boolean,
    vborderPrimary: Boolean,
    vborderSecondary: Boolean,
    vborderTertiary: Boolean,
    grid: Boolean,
    gridNone: Boolean,
    gridHalf: Boolean,
  },
  components: {},
  methods: {
    hasBg,
    modifiers() {
      let modifiers = [];

      if (this.wide) {
        modifiers.push('wide');
      }

      if (this.narrow) {
        modifiers.push('narrow');
      }

      if (this.fluid) {
        modifiers.push('fluid');
      }

      if (this.vborderPrimary) {
        modifiers.push('vborder-primary');
      }

      if (this.vborderSecondary) {
        modifiers.push('vborder-secondary');
      }

      if (this.vborderTertiary) {
        modifiers.push('vborder-tertiary');
      }

      if (this.grid) {
        modifiers.push('grid');
      }

      if (this.gridNone) {
        modifiers.push('grid-none');
      }

      if (this.gridHalf) {
        modifiers.push('grid-half');
      }

      return [...modifiers, ...(sharedContainer.methods.modifiers(this.$props))].map((modifier) => {
        return 'section--' + modifier;
      });
    },
    extraClasses() {
      return [];
    },
    additionalClasses() {
      return [...this.modifiers(), ...this.extraClasses()].join(' ');
    }
  },
  computed: {
    cssVars
  }
}
</script>