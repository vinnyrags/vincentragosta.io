<template>
  <header class="header" :class="open ? 'header--open' : ''">
    <Heading :level="1" class="header__logo">
      <a href="/">Vincent Ragosta Inc.</a>
    </Heading>
    <div class="header__menu-container" v-if="menu">
      <div class="header__hamburger" @click="open = (open === false);">
        <span></span>
        <span></span>
        <span></span>
        <span></span>
      </div>
      <ul class="header__menu">
        <li class="header__menu-item" :class="addAdditionalMenuItemClass(item)" v-for="item in menu" :key="item.title">
          <a class="header__menu-item-link" :href="item.url">{{ item.title }}</a>
        </li>
      </ul>
    </div>
  </header>
</template>

<script>
import Heading from "@/elements/Heading.vue";

export default {
  // eslint-disable-next-line vue/multi-word-component-names
  name: 'Header',
  data() {
    return {
      open: false,
      activePageSlug: '/'
    };
  },
  props: {
    menu: Object
  },
  components: {
    Heading
  },
  methods: {
    closeMenu() {
      if (window.innerWidth > 768 && this.open) {
        this.open = false;
      }
    },
    getMenuItemActiveClass(url) {
      return (this.activePageSlug === url ? 'active ' : '');
    }
  },
  created() {
    this.activePageSlug = window.location.pathname;
    window.addEventListener('resize', this.closeMenu)
  },
  mounted() {
    document.body.style.setProperty('--header-height', this.$el.clientHeight + 'px');
  },
  computed: {
    addAdditionalMenuItemClass() {
      return (item) => {
        return this.getMenuItemActiveClass(item.url) + item.classes.join(' ');
      };
    }
  }
}
</script>