<template>
  <blockquote class="blockquote" :class="additionalClasses()" :cite="source" v-if="quote">
    <p class="blockquote__quote">{{ quote }}</p>
    <footer class="blockquote__attribution">
      <span class="blockquote__image" v-if="hasImageSlot">
        <slot name="image"></slot>
      </span>
<!--      <Image class="blockquote__attribution-image" :src="image" v-if="image"/>-->
      {{ attribution && !hasImageSlot ? attribution : '' }}<cite v-if="attributionCite && !hasImageSlot">{{ attributionCite }}</cite>
    </footer>
  </blockquote>
</template>

<script>
// import Image from "@/components/Image.vue";

export default {
  // eslint-disable-next-line vue/multi-word-component-names
  name: 'Blockquote',
  props: {
    source: String,
    quote: String,
    attributionSource: String,
    attributionCite: String,
    // image: String,
    noAttribution: Boolean,
    large: Boolean,
    xlarge: Boolean
  },
  components: {
    // Image
  },
  computed: {
    attribution() {
      let attribution = '-';
      if (this.attributionSource) {
        attribution += this.attributionSource;
      }
      if (this.attributionCite) {
        attribution += ', ';
      }
      return attribution;
    },
    hasImageSlot() {
      return !!this.$slots.left;
    },
  },
  methods: {
    modifiers() {
      let modifiers = [];

      if (this.noAttribution) {
        modifiers.push('no-attribution');
      }

      if (this.large) {
        modifiers.push('large');
      }

      if (this.xlarge) {
        modifiers.push('xlarge');
      }

      return modifiers.map((modifier) => {
        return 'blockquote--' + modifier;
      });
    },
    extraClasses() {
      return [];
    },
    additionalClasses() {
      return [...this.modifiers(), ...this.extraClasses()].join(' ');
    }
  },
}
</script>
