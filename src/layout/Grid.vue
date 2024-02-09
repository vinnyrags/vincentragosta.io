<template>
  <div class="grid" :class="additionalClasses()">
    <slot></slot>
  </div>
</template>

<script>
export default {
  // eslint-disable-next-line vue/multi-word-component-names
  name: 'Grid',
  props: {},
  components: {},
  methods: {
    modifiers() {
      let modifiers = [];
      return modifiers;
    },
    extraClasses() {
      let classes = [];
      return classes;
    },
    additionalClasses() {
      return [...this.extraClasses(), ...this.modifiers()].join(' ');
    },
  },
  computed: {}
}
</script>