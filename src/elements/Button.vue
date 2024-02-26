<template>
  <a :href="href" class="button" :class="additionalClasses()" :target="target">
    <span class="button__slot button__slot--left" v-if="hasLeftSlot">
      <slot name="left"></slot>
    </span>
    <slot></slot>
    <span class="button__slot button__slot--right" v-if="hasRightSlot">
      <slot name="right"></slot>
    </span>
  </a>
</template>

<script>
import colors from "@/assets/scripts/props/colors";
import {maybeAddColorModifiers} from "@/assets/scripts/functions/maybeAddColorModifiers";

export default {
  // eslint-disable-next-line vue/multi-word-component-names
  name: 'Button',
  props: {
    href: {
      type: String,
      default: '#'
    },
    target: String,
    variant: String,
    ...colors
  },
  computed: {
    hasLeftSlot() {
      return !!this.$slots.left;
    },
    hasRightSlot() {
      return !!this.$slots.right;
    },
  },
  methods: {
    modifiers() {
      let modifiers = [];

      if (
          !this.primary &&
          !this.primaryDark &&
          !this.primaryLight &&
          !this.secondary &&
          !this.secondaryDark &&
          !this.secondaryLight &&
          !this.tertiary &&
          !this.tertiaryDark &&
          !this.tertiaryLight &&
          !this.gray &&
          !this.grayDark &&
          !this.grayLight &&
          !this.offWhite &&
          !this.white &&
          !this.black
      ) {
        modifiers.push('primary');
      }

      if (this.variant && ['outline', 'ghost'].includes(this.variant)) {
        modifiers.push(this.variant);
      }

      return [...modifiers, ...(maybeAddColorModifiers(this.$props))].map((modifier) => {
        return 'button--' + modifier;
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
  mounted() {}
}
</script>
