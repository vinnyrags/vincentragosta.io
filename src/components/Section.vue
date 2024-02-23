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
    fluid: Boolean,
    // edge: Boolean,
  },
  components: {},
  methods: {
    hasBg,
    modifiers() {
      let themeModifiers = [];

      // Add additional modifiers here

      return [...themeModifiers, ...(sharedContainer.methods.modifiers(this.$props))].map((modifier) => {
        return 'section--' + modifier;
      });
    },
    extraClasses() {
      let classes = [];
      return classes;
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