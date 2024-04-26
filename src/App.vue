<template>
  <nav>
    <router-link to="/">Home</router-link> |
    <router-link to="/about">About</router-link>
  </nav>
  <div>
    <h1>Welcome to My App</h1>
    <p v-if="loading">Loading...</p>
    <p v-else>Data Loaded Successfully</p>
  </div>
  <router-view />
</template>

<script lang="ts">
import { defineComponent, computed } from "vue";
import { useStore } from "vuex";

export default defineComponent({
  name: "App",
  setup() {
    const store = useStore();
    const loading = computed(() => !store.state.data);

    // Fetch data from external URL and set it into Vuex store
    async function fetchData() {
      try {
        const response = await fetch(
          "https://admin.vincentragosta.dev/wp-json/v1/config"
        );
        const data = await response.json();
        store.commit("setData", data);
        // console.log("Store data:", store.state.data);
      } catch (error) {
        // console.error("Error fetching data:", error);
      }
    }

    fetchData();

    return { loading };
  },
});
</script>

<style lang="scss">
@import "@/assets/styles/main.scss";
</style>
