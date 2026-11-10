import axios from 'axios'
import appService from "../../../services/appService";


export const frontendProductExchange = {
    namespaced: true,
    state: {
        lists: [],
        page: {},
        pagination: [],
        show: {},
        exchangeProducts: {},
    },
    getters: {
        lists: function (state) {
            return state.lists;
        },
        pagination: function (state) {
            return state.pagination;
        },
        page: function (state) {
            return state.page;
        },
        show: function (state) {
            return state.show;
        },
        exchangeProducts: function (state) {
            return state.exchangeProducts;
        },
    },
    actions: {
        lists: function (context, payload) {
            return new Promise((resolve, reject) => {
                let url = `frontend/exchange-order`;
                if (payload) {
                    url = url + appService.requestHandler(payload.search);
                }
                axios.get(url).then((res) => {
                    if (typeof payload.vuex === "undefined" || payload.vuex === true) {
                        context.commit("lists", res.data.data);
                        if (typeof payload.search.paginate !== 'undefined' && payload.search.paginate === 1) {
                            context.commit("pagination", res.data);
                            context.commit("page", res.data.meta);
                        }
                    }

                    resolve(res);
                }).catch((err) => {
                    reject(err);
                });
            });
        },
        save: function (context, payload) {
            return new Promise((resolve, reject) => {
                axios.post(`/frontend/exchange-order/request/${payload.id}`, payload.form).then((res) => {
                    resolve(res);
                }).catch((err) => {
                    reject(err);
                });
            });
        },
        show: function (context, payload) {
            return new Promise((resolve, reject) => {
                axios.get(`frontend/exchange-order/show/${payload}`).then((res) => {
                    context.commit("show", res.data.data);
                    context.commit("exchangeProducts", res.data.data.exchange_products);

                    resolve(res);
                }).catch((err) => {
                    reject(err);
                });
            });
        },
    },
    mutations: {
        lists: function (state, payload) {
            state.lists = payload
        },
        pagination: function (state, payload) {
            state.pagination = payload;
        },
        page: function (state, payload) {
            if (typeof payload !== "undefined" && payload !== null) {
                state.page = {
                    from: payload.from,
                    to: payload.to,
                    total: payload.total,
                };
            }
        },
        show: function (state, payload) {
            state.show = payload;
        },
        exchangeProducts: function (state, payload) {
            state.exchangeProducts = payload;
        },
    },
}
