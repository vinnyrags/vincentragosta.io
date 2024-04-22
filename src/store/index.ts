import { createStore } from "vuex";

export default createStore({
  state: {
    data: null,
  },
  mutations: {
    setData(state, payload) {
      state.data = payload;
    },
  },
});
