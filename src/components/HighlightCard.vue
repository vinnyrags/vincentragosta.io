<template>
  <div class="highlight-card">
    <Heading :level="3" size="medium">{{ title }}</Heading>
    <p v-if="excerpt">{{ excerpt }}</p>
    <Button :href="url" v-if="url" v-bind="buttonStyleAttribute">Read More</Button>
  </div>
</template>

<script>
import Heading from "@/elements/Heading.vue";
import Button from "@/elements/Button.vue";

export default {
  name: 'HightlightCard',
  props: {
    title: String,
    excerpt: String,
    url: String,
    primaryDark: Boolean,
    primaryLight: Boolean,
    secondary: Boolean,
    secondaryDark: Boolean,
    secondaryLight: Boolean,
    tertiary: Boolean,
    tertiaryDark: Boolean,
    tertiaryLight: Boolean,
    gray: Boolean,
    grayDark: Boolean,
    grayLight: Boolean,
    black: Boolean,
  },
  components: {
    Heading,
    Button
  },
  computed: {
    buttonStyleAttribute() {
      let attribute = "";

      if (this.primaryDark) {
        attribute = {primaryDark: true};
      }

      if (this.primaryLight) {
        attribute = {primaryLight: true};
      }

      if (this.secondary) {
        attribute = {secondary: true};
      }

      if (this.secondaryDark) {
        attribute = {secondaryDark: true};
      }

      if (this.secondaryLight) {
        attribute = {secondaryLight: true};
      }

      if (this.tertiary) {
        attribute = {tertiary: true};
      }

      if (this.tertiaryDark) {
        attribute = {tertiaryDark: true};
      }

      if (this.tertiaryLight) {
        attribute = {tertiaryLight: true};
      }

      if (this.gray) {
        attribute = {gray: true};
      }

      if (this.grayDark) {
        attribute = {grayDark: true};
      }

      if (this.grayLight) {
        attribute = {grayLight: true};
      }

      if (this.black) {
        attribute = {black: true};
      }

      return attribute;
    }
  },
  methods: {
    modifiers() {
      let modifiers = [];

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

      if (this.gray) {
        modifiers.push('gray');
      }

      if (this.grayDark) {
        modifiers.push('gray-dark');
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
        return 'highlight-card--' + modifier;
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
}
</script>

<!-- Add "scoped" attribute to limit CSS to this component only -->
<style scoped>

</style>
