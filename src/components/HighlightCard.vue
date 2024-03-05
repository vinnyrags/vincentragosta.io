<template>
  <div class="highlight-card" :class="additionalClasses()">
    <div class="highlight-card__image-container" v-if="image && horizontal">
      <Image class="highlight-card__image" v-if="image" :src="image" fit />
    </div>
    <div class="highlight-card__content">
      <div class="highlight-card__image-container" v-if="image && !horizontal">
        <Image class="highlight-card__image" v-if="image" :src="image" fit />
      </div>
      <Heading class="highlight-card__heading" :level="3" size="medium" v-if="title">{{ title }}</Heading>
      <p class="highlight-card__excerpt" v-if="excerpt">{{ excerpt }}</p>
      <Button class="highlight-card__button" :href="url" v-if="url && horizontal" v-bind="buttonStyleAttribute">Read More</Button>
    </div>
    <Button class="highlight-card__button" :href="url" v-if="url && !horizontal" v-bind="buttonStyleAttribute">Read More</Button>
  </div>
</template>

<script>
import colors from "@/assets/scripts/props/colors";
import {maybeAddColorModifiers} from "@/assets/scripts/functions/maybeAddColorModifiers";
import Heading from "@/elements/Heading.vue";
import Button from "@/elements/Button.vue";
import Image from "@/components/Image.vue";

export default {
  name: 'HightlightCard',
  props: {
    image: String,
    title: String,
    excerpt: String,
    url: String,
    ...colors,
    sleek: Boolean,
    sleekImage: Boolean,
    sleekButton: Boolean,
    horizontal: Boolean,
  },
  components: {
    Heading,
    Button,
    Image
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
    },
  },
  methods: {
    modifiers() {
      let modifiers = [];

      if (this.sleek) {
        modifiers.push('sleek');
      }

      if (this.sleekImage) {
        modifiers.push('sleek-image');
      }

      if (this.sleekButton) {
        modifiers.push('sleek-button');
      }

      if (this.horizontal) {
        modifiers.push('horizontal');
      }

      return [...modifiers, ...(maybeAddColorModifiers(this.$props))].map((modifier) => {
        return 'highlight-card--' + modifier;
      });
    },
    extraClasses() {
      return [];
    },
    additionalClasses() {
      return [...this.modifiers(), ...this.extraClasses()].join(' ');
    }
  },
  created() {
    window.addEventListener("resize", () => {
      console.log('tick');
    });
  }
}
</script>

<!-- Add "scoped" attribute to limit CSS to this component only -->
<style scoped>

</style>
