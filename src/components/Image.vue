<template>
  <figure class="image" :class="additionalClasses()">
    <picture class="image__picture">
      <source class="image__source" v-for="source in srcset" :key="source.src + source.media" :srcset="source.src" :media="source.media"/>
      <img class="image__img" v-if="src" :src="src" :width="width" :height="height" :alt="alt" />
    </picture>
    <figcaption class="image__caption" v-if="caption">{{ caption }}</figcaption>
  </figure>
</template>

<script>
export default {
  // eslint-disable-next-line vue/multi-word-component-names
  name: 'Image',
  props: {
    src: String,
    width: String,
    height: String,
    alt: String,
    style: String,
    fit: Boolean,
    caption: String,

    xs: String,
    sm: String,
    md: String,
    lg: String,
    xl: String
  },
  computed: {
    srcset() {
      let sourceSet = [];
      const rootVars = getComputedStyle(document.body);


      if (this.xs) {
        sourceSet.push({
          src: this.xs,
          media: '(min-width:' + rootVars.getPropertyValue('--breakpoint-xs') + ')'
        });
      }

      if (this.sm) {
        sourceSet.push({
          src: this.sm,
          media: '(min-width:' + rootVars.getPropertyValue('--breakpoint-sm') + ')'
        });
      }

      if (this.md) {
        sourceSet.push({
          src: this.md,
          media: '(min-width:' + rootVars.getPropertyValue('--breakpoint-md') + ')'
        });
      }

      if (this.lg) {
        sourceSet.push({
          src: this.lg,
          media: '(min-width:' + rootVars.getPropertyValue('--breakpoint-lg') + ')'
        });
      }

      if (this.xl) {
        sourceSet.push({
          src: this.xl,
          media: '(min-width:' + rootVars.getPropertyValue('--breakpoint-xl') + ')'
        });
      }

      return sourceSet;
    }
  },
  methods: {
    modifiers() {
      let modifiers = [];

      if (this.fit) {
        modifiers.push('fit');
      }

      return modifiers.map((modifier) => {
        return 'image--' + modifier;
      });
    },
    extraClasses() {
      return [];
    },
    additionalClasses() {
      return [...this.modifiers(), ...this.extraClasses()].join(' ');
    }
  }
}
</script>
