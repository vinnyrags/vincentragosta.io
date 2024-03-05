<template>
  <div class="column" :class="additionalClasses()" :style="cssVars">
    <div class="column__bg" v-if="hasBg(this.$props)">
      <video v-if="video" class="column__video" type="video/mp4" :src="video" autoplay muted loop></video>
      <Image class="section__image" v-if="image" :src="image" fit />
    </div>
    <div class="column__wrap">
      <slot></slot>
    </div>
  </div>
</template>

<script>
import sharedContainer from "@/assets/scripts/components/shared-container";
import alignmentProps from '@/assets/scripts/props/alignment';
import {hasBg} from "@/assets/scripts/functions/bg/hasBg";
import {cssVars} from "@/assets/scripts/functions/cssVars";
import Image from "@/components/Image.vue";

export default {
  // eslint-disable-next-line vue/multi-word-component-names
  name: 'Column',
  props: {
    width: {
      type: String,
      default: '12'
    },

    xs: [Boolean, String],
    sm: [Boolean, String],
    md: [Boolean, String],
    lg: [Boolean, String],
    xl: [Boolean, String],

    ...sharedContainer.props,
    ...alignmentProps,
  },
  components: {
    Image
  },
  methods: {
    hasBg,
    modifiers() {
      let modifiers = [];
      return [...modifiers, ...(sharedContainer.methods.modifiers(this.$props))].map((modifier) => {
        return 'column--' + modifier;
      });
    },
    extraClasses() {
      let classes = [];

      if (this.width) {
        classes.push('column-' + this.width);
      }

      const responsiveBreakpoints = this.responsiveBreakpoints();
      if (responsiveBreakpoints && responsiveBreakpoints.length) {
        responsiveBreakpoints.forEach((breakpoint) => {
          classes.push('column-' + breakpoint.breakpoint + '-' + breakpoint.width);
        });
      }

      return classes;
    },
    additionalClasses() {
      return [...this.modifiers(), ...this.extraClasses()].join(' ');
    },
    responsiveBreakpoints() {
      let breakpoint = [];
      if (this.xs) {
        breakpoint.push({
          breakpoint: 'xs',
          width: typeof this.xs === 'string' ? this.xs : this.width
        })
      }
      if (this.sm) {
        breakpoint.push({
          breakpoint: 'sm',
          width: typeof this.sm === 'string' ? this.sm : this.width
        })
      }
      if (this.md) {
        breakpoint.push({
          breakpoint: 'md',
          width: typeof this.md === 'string' ? this.md : this.width
        })
      }
      if (this.lg) {
        breakpoint.push({
          breakpoint: 'lg',
          width: typeof this.lg === 'string' ? this.lg : this.width
        })
      }
      if (this.xl) {
        breakpoint.push({
          breakpoint: 'xl',
          width: typeof this.xl === 'string' ? this.xl : this.width
        })
      }
      return breakpoint;
    },
  },
  computed: {
    cssVars
  },
  mounted() {
  }
}
</script>