<template>
  <div class="button-group" :class="additionalClasses()">
    <slot></slot>
  </div>
</template>

<script>
export default {
  name: 'ButtonGroup',
  props: {},
  methods: {
    modifiers() {
      let modifiers = [];
      return modifiers.map((modifier) => {
        return 'button-group--' + modifier;
      });
    },
    extraClasses() {
      let classes = [];
      return classes;
    },
    additionalClasses() {
      return [...this.modifiers(), ...this.extraClasses()].join(' ');
    }
  }
}
</script>
