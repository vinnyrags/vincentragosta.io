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
import Grid from "@/layout/Grid.vue";

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
    bgTertiary: Boolean,
    bgTertiaryDark: Boolean,
    bgTertiaryLight: Boolean,
    bgGrayDark: Boolean,
    bgGray: Boolean,
    bgGrayLight: Boolean,
    bgOffWhite: Boolean,
    bgWhite: Boolean,
    bgBlack: Boolean,

    primary: Boolean,
    primaryDark: Boolean,
    primaryLight: Boolean,
    secondary: Boolean,
    secondaryDark: Boolean,
    secondaryLight: Boolean,
    tertiary: Boolean,
    tertiaryDark: Boolean,
    tertiaryLight: Boolean,
    grayDark: Boolean,
    gray: Boolean,
    grayLight: Boolean,
    offWhite: Boolean,
    white: Boolean,
    black: Boolean,

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

      if (this.bgPrimaryDark) {
        modifiers.push('bg-primary-dark');
      }

      if (this.bgPrimaryLight) {
        modifiers.push('bg-primary-light');
      }

      if (this.bgSecondary) {
        modifiers.push('bg-secondary');
      }

      if (this.bgSecondaryDark) {
        modifiers.push('bg-secondary-dark');
      }

      if (this.bgSecondaryLight) {
        modifiers.push('bg-secondary-light');
      }

      if (this.bgTertiary) {
        modifiers.push('bg-tertiary');
      }

      if (this.bgTertiaryDark) {
        modifiers.push('bg-tertiary-dark');
      }

      if (this.bgTertiaryLight) {
        modifiers.push('bg-tertiary-light');
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

      if (this.bgOffWhite) {
        modifiers.push('bg-off-white');
      }

      if (this.bgWhite) {
        modifiers.push('bg-white');
      }

      if (this.bgBlack) {
        modifiers.push('bg-black');
      }

      if (this.primary) {
        modifiers.push('primary');
      }

      if (this.primaryDark) {
        modifiers.push('primary-dark');
      }

      if (this.primaryLight) {
        modifiers.push('primary-light');
      }

      if (this.secondary) {
        modifiers.push('secondary');
      }

      if (this.secondaryDark) {
        modifiers.push('secondary-dark');
      }

      if (this.secondaryLight) {
        modifiers.push('secondary-light');
      }

      if (this.tertiary) {
        modifiers.push('tertiary');
      }

      if (this.tertiaryDark) {
        modifiers.push('tertiary-dark');
      }

      if (this.tertiaryLight) {
        modifiers.push('tertiary-light');
      }

      if (this.grayDark) {
        modifiers.push('gray-dark');
      }

      if (this.gray) {
        modifiers.push('gray');
      }

      if (this.grayLight) {
        modifiers.push('gray-light');
      }

      if (this.offWhite) {
        modifiers.push('off-white');
      }

      if (this.white) {
        modifiers.push('white');
      }

      if (this.black) {
        modifiers.push('black');
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