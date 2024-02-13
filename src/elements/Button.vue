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
    primary: Boolean,
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
    white: Boolean,
    offWhite: Boolean,
    black: Boolean,
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
          this.primary ||
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

      if (this.variant && ['outline', 'ghost'].includes(this.variant)) {
        modifiers.push(this.variant);
      }

      return modifiers.map((modifier) => {
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
