<template>
  <Header v-if="this.primaryNavigation" :menu="this.primaryNavigation"></Header>
  <FrontPage></FrontPage>
</template>

<script>
import Header from '@/modules/Header.vue';
import FrontPage from '@/pages/FrontPage.vue';

export default {
  name: 'App',
  data() {
    return {
      primaryNavigation: Array,
      frontPageId: ''
    };
  },
  components: {
    Header,
    FrontPage,
  },
  methods: {
    async getData() {
      try {
        let response = await fetch(
            "https://devanimecards.test/wp-json/global/config"
        );
        let responseObject = await response.json();
        if (responseObject) {
          this.primaryNavigation = responseObject.header.primary_navigation;
          this.frontPageId = responseObject.page_assignments.front_page.ID.toString();
        }
      } catch (error) {
        console.log(error);
      }
    },
  },
  created() {
    this.getData();
  }
};
</script>