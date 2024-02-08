<template>
  <section class="section" :class="additionalClasses()" :style="cssVars">
    <div v-if="hasBackground()" class="section__bg">
      <video v-if="video" class="section__video" type="video/mp4" :src="video" autoplay muted loop></video>
      <img v-if="image" class="section__image" :src="image"/>
    </div>
    <div class="section__container" :style="adjustSpacing()">
      <Grid class="section__grid">
        <slot></slot>
      </Grid>
    </div>
  </section>
</template>

<script>
import Grid from "@/layout/directive/Grid.vue";

export default {
  // eslint-disable-next-line vue/multi-word-component-names
  name: 'Section',
  props: {
    video: String,
    image: String,
    fluid: Boolean,
    size: String,
    bgColor: String,
    color: String,

    bgPrimary: Boolean,
    bgPrimaryDark: Boolean,
    bgPrimaryLight: Boolean,
    bgSecondary: Boolean,
    bgSecondaryDark: Boolean,
    bgSecondaryLight: Boolean,
    bgGrayDark: Boolean,
    bgGray: Boolean,
    bgGrayLight: Boolean,
    bgWhite: Boolean,
    bgBlack: Boolean,

    primary: Boolean,
    secondary: Boolean,
    grayDark: Boolean,
    gray: Boolean,
    grayLight: Boolean,
    white: Boolean,
    black: Boolean,

    // grid: Boolean,
    // edge: Boolean
  },
  components: {
    Grid
  },
  methods: {
    modifiers() {
      let modifiers = [];
      if (this.fluid) {
        modifiers.push('fluid');
      }
      if (this.hasBackground()) {
        modifiers.push('has-bg');
      }
      if (this.bgPrimary) {
        modifiers.push('bg-primary');
      }
      if (this.bgSecondary) {
        modifiers.push('bg-secondary');
      }
      if (this.bgGrayDark) {
        modifiers.push('bg-gray-dark');
      }
      if (this.bgGray) {
        modifiers.push('bg-gray');
      }
      if (this.bgGrayLight) {
        modifiers.push('bg-gray-light');
      }
      if (this.bgWhite) {
        modifiers.push('bg-white');
      }
      if (this.bgBlack) {
        modifiers.push('bg-black');
      }
      return modifiers.map((modifier) => {
        return 'section--' + modifier;
      });
    },
    extraClasses() {
      let classes = [];
      return classes;
    },
    additionalClasses() {
      // console.log(this.modifiers());
      return [...this.extraClasses(), ...this.modifiers()].join(' ');
    },
    adjustSpacing() {
      if (!this.size) {
        return '';
      }
      const multiplier = 'calc(var(--layout-spacing) * ' + this.size + ')';
      return 'padding-top: ' + multiplier + '; padding-bottom: ' + multiplier + ';';
    },
    hasBackground() {
      return this.video || this.image || this.bgColor || this.bgPrimary || this.bgSecondary || this.bgGrayDark || this.bgGray || this.bgGrayLight || this.bgWhite || this.bgBlack;
    }
  },
  computed: {
    cssVars() {
      let cssVars = {};
      if (this.bgColor) {
        Object.assign(cssVars, {
          '--section-bg-color': this.bgColor,
        });
      }
      if (this.color) {
        Object.assign(cssVars, {
          '--section-color': this.color,
        });
      }

      return cssVars;
    },
  }
}
</script>