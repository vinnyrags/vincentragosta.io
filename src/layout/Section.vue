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
// TODO rename componentContainer to sharedContainer
import componentContainerProps from "@/assets/scripts/component-container/props/props";
import {hasBg} from "@/assets/scripts/component-container/methods/has-bg";
import {cssVars} from "@/assets/scripts/component-container/computed/css-vars";
import {componentContainerModifiers} from "@/assets/scripts/component-container/methods/modifiers";

export default {
  // eslint-disable-next-line vue/multi-word-component-names
  name: 'Section',
  props: {
    fluid: Boolean,
    // edge: Boolean,
    ...componentContainerProps
  },
  components: {},
  methods: {
    hasBg,
    modifiers() {
      let themeModifiers = [];

      // Add additional modifiers here

      return [...themeModifiers, ...(componentContainerModifiers(this.$props))].map((modifier) => {
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