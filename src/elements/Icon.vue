<template>
  <span class="icon" :class="additionalClasses()">
    <slot></slot>
  </span>
</template>

<script>
export default {
  // eslint-disable-next-line vue/multi-word-component-names
  name: 'Icon',
  props: {
    name: String
  },
  methods: {
    modifiers() {
      let modifiers = [];

      return modifiers.map((modifier) => {
        return 'icon--' + modifier;
      });
    },
    extraClasses() {
      let classes = [];

      if (this.name) {
        classes.push('icon-' + this.name);
      }

      return classes;
    },
    additionalClasses() {
      return [...this.modifiers(), ...this.extraClasses()].join(' ');
    }
  }
}
</script>