<template>
  <div class="highlight-card" :class="additionalClasses()">
    <Heading :level="3" size="medium">{{ title }}</Heading>
    <p v-if="excerpt">{{ excerpt }}</p>
    <Button :href="url" v-if="url" v-bind="buttonStyleAttribute">Read More</Button>
  </div>
</template>

<script>
import colors from "@/assets/scripts/props/colors";
import {maybeAddColorModifiers} from "@/assets/scripts/functions/maybeAddColorModifiers";
import Heading from "@/elements/Heading.vue";
import Button from "@/elements/Button.vue";

export default {
  name: 'HightlightCard',
  props: {
    title: String,
    excerpt: String,
    url: String,
    ...colors
  },
  components: {
    Heading,
    Button
  },
  computed: {
    buttonStyleAttribute() {
      let attribute = "";

      if (this.primary) {
        attribute = {primary: true};
      }

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

      return [...modifiers, ...(maybeAddColorModifiers(this.$props))].map((modifier) => {
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
