<template>
    <LoadingComponent :props="loading" />
    <div class="flex items-center gap-4 mb-7">
        <button @click.prevent="$router.back()" class="lab-line-undo text-xl font-bold text-primary"></button>
        <h2 class="capitalize text-xl font-bold text-primary">{{ $t('label.exchange_request') }}</h2>
    </div>
    <form class="w-full d-block min-h-dvh" @submit.prevent="save">
        <div class="rounded-2xl shadow-card mb-6 bg-white">
            <h3 class="font-semibold p-4">{{ $t('label.order_id') }}: #{{ order.order_serial_no }}</h3>
            <div v-if="errors.products"
                class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 mb-5 rounded relative" role="alert">
                <span class="block sm:inline">{{ errors.products[0] }}</span>
                <span class="absolute top-0 bottom-0 right-0 px-4 py-3 cursor-pointer" @click="close">
                    <i class="lab lab-fill-close-circle margin-top-5-px"></i>
                </span>
            </div>
            <div class="mobile:overflow-x-auto">
                <table class="w-full text-left text-sm capitalize">
                    <thead class="font-semibold border-b border-t border-gray-200">
                        <tr>
                            <th class="p-4">
                                <input type="checkbox" v-model="isAllExchange" @change="selectAll($event, orderProducts)"
                                    class="cs-custom-checkbox" />
                            </th>
                            <th class="p-4">{{ $t('label.products') }}</th>
                            <th class="p-4">{{ $t('label.quantity') }}</th>
                        </tr>
                    </thead>
                    <tbody class="font-medium" v-if="orderProducts.length > 0">
                        <tr v-for="(product, index) in orderProducts" :key="index" class="group">
                            <td class="p-4 border-b group-last:border-0 border-gray-100">
                                <input v-if="product.is_refundable" type="checkbox" v-model="product.isExchange"
                                    @change="selectProduct($event, index, product)" class="cs-custom-checkbox" />
                                <span v-else class="text-danger font-semibold">{{ $t('label.not_refundable') }}</span>
                            </td>
                            <td class="p-4 border-b group-last:border-0 border-gray-100">
                                <div class="inline-flex items-center gap-3">
                                    <img :src="product.product_image" alt="product"
                                        class="w-12 h-12 object-cover rounded-md flex-shrink-0" />
                                    <div class="overflow-hidden">
                                        <h4
                                            class="text-sm capitalize whitespace-nowrap overflow-hidden text-ellipsis mb-0.5">
                                            {{ product.product_name }}</h4>
                                        <p class="text-sm capitalize whitespace-nowrap overflow-hidden text-ellipsis"
                                            v-if="product.has_variation">
                                            {{ product.variation_names }}</p>
                                    </div>
                                </div>
                            </td>
                            <td class="p-4 border-b group-last:border-0 border-gray-100">
                                <div class="flex items-center gap-1 w-20 p-1 rounded-full bg-[#F7F7FC]">
                                    <button type="button" @click.prevent="quantityDecrement(index, product)"
                                        class="lab-line-minus w-6 h-6 text-xs rounded-full bg-white text-primary">
                                    </button>
                                    <input type="text" v-model="product.quantity" @keypress="onlyNumber($event)"
                                        class="w-8 text-center bg-transparent" />
                                    <button type="button" @click.prevent="quantityIncrement(index, product)"
                                        class="lab-line-plus w-6 h-6 text-xs rounded-full bg-white text-primary">
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="rounded-2xl shadow-card mb-6 bg-white" v-if="products.length > 0">
            <h3 class="font-semibold p-4">{{ $t('label.exchange_with') }}</h3>
            <div class="mobile:overflow-x-auto">
                <table class="w-full text-left text-sm capitalize">
                    <thead class="font-semibold border-b border-t border-gray-200">
                        <tr>
                            <th class="p-4">{{ $t('label.products') }}</th>
                            <th class="p-4">{{ $t('label.exchange_with') }}</th>
                        </tr>
                    </thead>
                    <tbody class="font-medium">
                        <tr v-for="(product, index) in products" :key="index" class="group">
                            <td class="p-4 border-b group-last:border-0 border-gray-100">
                                <span class="text-sm font-semibold">{{ product.product_name }} x{{ product.quantity }}</span>
                            </td>
                            <td class="p-4 border-b group-last:border-0 border-gray-100">
                                <vue-select class="db-field-control f-b-custom-select" :id="'exchange_' + index"
                                    v-model="product.exchange_product_id" :options="exchangeProducts" label-by="name"
                                    value-by="id" :closeOnSelect="true" :searchable="true" :clearOnClose="true"
                                    placeholder="--" search-placeholder="--"
                                    @option:selected="(option) => selectExchangeProduct(index, option)" />
                                <span class="block text-xs mt-1">{{ product.exchange_product_currency_price }}</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-5 mb-6">
            <div class="rounded-2xl shadow-card bg-white p-4">
                <label class="capitalize font-semibold mb-2 block after:content-['*'] after:text-danger">{{ $t('label.exchange_reason') }}</label>
                <vue-select class="db-field-control f-b-custom-select" id="exchange_reason_id"
                    v-model="form.exchange_reason_id" :options="exchangeReasons" label-by="title" value-by="id"
                    :closeOnSelect="true" :searchable="true" :clearOnClose="true" placeholder="--"
                    search-placeholder="--" />
                <small class="db-field-alert" v-if="errors.exchange_reason_id">{{ errors.exchange_reason_id[0] }}</small>
            </div>
            <div class="rounded-2xl shadow-card bg-white p-4">
                <label class="capitalize font-semibold mb-2 block">{{ $t('label.exchange_note') }}</label>
                <textarea v-model="form.note" rows="4" class="db-field-control w-full"></textarea>
            </div>
            <div class="rounded-2xl shadow-card bg-white p-4">
                <label class="capitalize font-semibold mb-2 block">{{ $t('label.attachment') }}</label>
                <input type="file" ref="imageProperty" @change="changeImage" multiple class="db-field-control w-full" />
            </div>
        </div>

        <button type="submit" class="db-btn w-full py-3 text-white bg-primary shadow-db-card">
            <i class="lab lab-save"></i>
            <span>{{ $t('button.submit') }}</span>
        </button>
    </form>
</template>

<script>
import LoadingComponent from "../../components/LoadingComponent";
import alertService from "../../../../services/alertService";
import appService from "../../../../services/appService";
import statusEnum from "../../../../enums/modules/statusEnum";
export default {
    name: "ExchangeOrderRequestComponent",
    components: { LoadingComponent },
    data() {
        return {
            loading: {
                isActive: false,
            },
            isAllExchange: false,
            form: {
                exchange_reason_id: null,
                note: "",
                order_id: null,
                order_serial_no: null,
                products: []
            },
            search: {
                paginate: 1,
                page: 1,
                per_page: 10,
                order_column: "id",
                order_type: "desc",
            },
            products: [],
            qty: [],
            image: "",
            errors: {},
        }
    },
    computed: {
        order: function () {
            return this.$store.getters['frontendOrder/show'];
        },
        orderProducts: function () {
            return this.$store.getters['frontendOrder/orderProducts'];
        },
        exchangeReasons: function () {
            return this.$store.getters["frontendExchangeReason/lists"];
        },
        exchangeProducts: function () {
            return this.$store.getters["frontendProduct/lists"];
        },
    },
    mounted() {
        this.loading.isActive = true;
        if (this.$route.params.id) {
            this.loading.isActive = true;
            this.$store.dispatch("frontendOrder/show", this.$route.params.id).then(res => {
                this.form.order_id = res.data.data.id;
                this.form.order_serial_no = res.data.data.order_serial_no;
                this.loading.isActive = false;
            }).catch((error) => {
                this.loading.isActive = false;
            });

            this.$store.dispatch('frontendExchangeReason/lists', {
                order_column: 'id',
                order_type: 'asc',
                status: statusEnum.ACTIVE
            });

            this.$store.dispatch('frontendProduct/lists', {
                order_column: 'id',
                order_type: 'desc',
                status: statusEnum.ACTIVE,
                paginate: 0
            });
        }
    },
    methods: {
        changeImage: function (e) {
            this.image = e.target.files;
        },
        onlyNumber: function (e) {
            return appService.onlyNumber(e);
        },
        close: function () {
            this.errors.products = ""
        },
        selectAll(e, products) {
            products.forEach((product, i) => {
                let quantity = 1;
                let quantityExist = this.qty.find(p =>
                    p.product_id === product.product_id
                );

                let quantityIndex = this.qty.findIndex(p =>
                    p.product_id === product.product_id
                );

                let productData = {
                    product_id: product.product_id,
                    product_name: product.product_name,
                    quantity: quantity,
                    price: product.price,
                    total: product.total,
                    has_variation: product.has_variation,
                    variation_id: product.variation_id,
                    variation_names: product.variation_names,
                    exchange_product_id: null,
                    exchange_product_quantity: quantity,
                    exchange_product_price: 0,
                    exchange_product_currency_price: 0,
                };

                if (quantityExist) {
                    productData.quantity = this.qty[quantityIndex].quantity;
                    quantity = this.qty[quantityIndex].quantity;
                } else {
                    productData.quantity = product.quantity;
                    quantity = product.quantity;
                }

                if (this.isAllExchange) {
                    if (product.is_refundable) {
                        product.isExchange = true;
                        let productExist = this.products.find(p =>
                            p.product_id === product.product_id
                        );

                        if (productExist) {
                            let productIndex = this.products.findIndex(p =>
                                p.product_id === product.product_id
                            );
                            this.products.splice(productIndex, 1);
                            this.products.push(productData);
                        } else {
                            this.products.push(productData);
                        }
                    }
                }
                else {
                    product.isExchange = false;
                    this.products = [];
                    this.qty = [];
                }
            })
        },

        selectProduct(e, i, product) {
            let quantity = 1;
            let quantityExist = this.qty.find(p =>
                p.product_id === product.product_id
            );

            let quantityIndex = this.qty.findIndex(p =>
                p.product_id === product.product_id
            );

            let productData = {
                product_id: product.product_id,
                product_name: product.product_name,
                quantity: quantity,
                price: product.price,
                total: product.total,
                has_variation: product.has_variation,
                variation_id: product.variation_id,
                variation_names: product.variation_names,
                exchange_product_id: null,
                exchange_product_quantity: quantity,
                exchange_product_price: 0,
                exchange_product_currency_price: 0,
            };

            if (quantityExist) {
                productData.quantity = this.qty[quantityIndex].quantity;
                quantity = this.qty[quantityIndex].quantity;
            } else {
                productData.quantity = product.quantity;
                quantity = product.quantity;
            }

            if (e.target.checked) {
                this.products.push(productData);
            } else {
                let productIndex = this.products.findIndex(p =>
                    p.product_id === product.product_id
                );
                this.products.splice(productIndex, 1);
                this.isAllExchange = false;
            }
        },

        selectExchangeProduct(index, option) {
            this.products[index].exchange_product_id = option.id;
            this.products[index].exchange_product_price = option.price;
            this.products[index].exchange_product_currency_price = option.selling_price ?? option.price;
            this.products[index].exchange_product_quantity = this.products[index].quantity;
        },

        quantityIncrement: function (i, product) {
            let quantityExist = this.qty.find(p =>
                p.product_id === product.product_id
            );

            let quantity = 1;
            let quantityIndex = null;

            if (quantityExist) {
                quantityIndex = this.qty.findIndex(p =>
                    p.product_id === product.product_id
                );

                if (product.quantity < product.order_quantity) {
                    product.quantity += 1;
                } else {
                    product.quantity = product.order_quantity;
                }
                quantity = product.quantity;
                this.qty[quantityIndex].quantity = quantity;
            }

            let productExist = this.products.find(p =>
                p.product_id === product.product_id
            );

            if (productExist) {
                let productIndex = this.products.findIndex(p =>
                    p.product_id === product.product_id
                );
                this.products[productIndex].quantity = quantity;
                this.products[productIndex].exchange_product_quantity = quantity;
                this.products[productIndex].total = parseFloat(quantity * product.price);
            }
        },

        quantityDecrement: function (i, product) {
            let quantityData = {
                product_id: product.product_id,
            };
            let quantityExist = this.qty.find(p =>
                p.product_id === product.product_id
            );

            let quantity = 1;
            let quantityIndex = null;

            if (quantityExist) {
                quantityIndex = this.qty.findIndex(p =>
                    p.product_id === product.product_id
                );
                if (product.quantity > 1) {
                    product.quantity -= 1;
                } else {
                    product.quantity = 1;
                }
            }
            quantity = product.quantity ? product.quantity : 1;

            if (quantityExist) {
                this.qty[quantityIndex].quantity = quantity;
            } else {
                quantityData.quantity = quantity;
                this.qty.push(quantityData);
            }

            let productExist = this.products.find(p =>
                p.product_id === product.product_id
            );

            if (productExist) {
                let productIndex = this.products.findIndex(p =>
                    p.product_id === product.product_id
                );
                this.products[productIndex].quantity = quantity;
                this.products[productIndex].exchange_product_quantity = quantity;
                this.products[productIndex].total = parseFloat(quantity * product.price);
            }
        },

        save: function () {
            try {
                const fd = new FormData();
                fd.append('exchange_reason_id', this.form.exchange_reason_id !== null ? this.form.exchange_reason_id : '');
                fd.append('note', this.form.note);
                fd.append('order_id', this.form.order_id);
                fd.append('order_serial_no', this.form.order_serial_no);
                fd.append('products', this.products.length > 0 ? JSON.stringify(this.products) : '');
                if (this.image) {
                    for (let i = 0; i < this.image.length; i++) {
                        fd.append('image[]', this.image[i]);
                    }
                }
                this.loading.isActive = true;
                this.$store.dispatch('frontendProductExchange/save', {
                    id: this.$route.params.id,
                    form: fd,
                    search: this.search
                }).then((res) => {
                    this.loading.isActive = false;
                    alertService.successFlip(0, this.$t("menu.exchange_orders"));
                    this.form = {
                        exchange_reason_id: null,
                        note: "",
                        order_id: null,
                        order_serial_no: null,
                        products: []
                    };
                    this.products = [];
                    this.qty = [];
                    this.image = "";
                    this.errors = {};
                    this.$refs.imageProperty.value = null;
                    this.$router.push({ name: "frontend.account.exchangeOrders" });
                }).catch((err) => {
                    this.loading.isActive = false;
                    if (err.response.data.errors === undefined) {
                        if (err.response.data.message) {
                            this.errors = {};
                            alertService.error(err.response.data.message);
                        }
                    } else {
                        this.errors = err.response.data.errors;
                    }
                });
            } catch (err) {
                this.loading.isActive = false;
                alertService.error(err);
            }
        },
    }
}
</script>
