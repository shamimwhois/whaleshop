<template>
    <LoadingComponent :props="loading" />
    <div class="col-12">
        <div class="db-card p-4">
            <div class="flex flex-wrap gap-y-5 items-end justify-between">
                <div>
                    <div class="flex flex-wrap items-start gap-y-2 gap-x-3 mb-5">
                        <p class="text-2xl font-medium">{{ $t('label.order_id') }}:
                            <span class="text-heading">
                                #{{ order.order_serial_no }}
                            </span>
                        </p>
                        <div class="flex items-center gap-2 mt-1.5">
                            <span :class="'text-sm capitalize px-2 rounded-3xl ' + orderStatusClass(order.status)">
                                {{ enums.exchangeStatusEnumArray[order.status] }}
                            </span>
                        </div>
                    </div>
                    <ul class="flex flex-col gap-2">
                        <li class="flex items-center gap-2">
                            <i class="lab lab-line-calendar lab-font-size-16"></i>
                            <span class="text-xs">{{ order.exchange_datetime }}</span>
                        </li>
                    </ul>
                </div>

                <div class="flex flex-wrap gap-3" v-if="order.status === enums.exchangeStatusEnum.PENDING">
                    <ProductExchangeReasonComponent />
                    <button type="button" @click="changeStatus(enums.exchangeStatusEnum.ACCEPT)"
                        class="flex items-center justify-center text-white gap-2 px-4 h-[38px] rounded shadow-db-card bg-[#2AC769]">
                        <i class="lab lab-fill-save"></i>
                        <span class="text-sm capitalize text-white">{{ $t('button.accept') }}</span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12 sm:col-6">
        <div class="row">
            <div class="col-12">
                <div class="db-card">
                    <div class="db-card-header">
                        <h3 class="db-card-title">{{ $t('label.exchange_details') }}</h3>
                    </div>
                    <div class="db-card-body p-0">
                        <div class="pl-8 py-4 pr-4">
                            <div class="mb-3 pb-3 border-b last:mb-0 last:border-b-0 border-gray-2"
                                v-if="exchangeProducts.length > 0" v-for="product in exchangeProducts" :key="product">
                                <div class="flex items-center gap-3 relative">
                                    <h3
                                        class="absolute top-5 ltr:-left-3 rtl:-right-3 text-sm w-[26px] h-[26px] leading-[26px] text-center rounded-full text-white bg-heading">
                                        {{ product.quantity }}</h3>
                                    <img class="w-16 h-16 rounded-lg flex-shrink-0" :src="product.product_image"
                                        alt="thumbnail">
                                    <div class="flex-auto overflow-hidden">
                                        <h4 class="text-sm capitalize whitespace-nowrap overflow-hidden text-ellipsis">
                                            {{ product.product_name }}</h4>
                                        <p class="text-sm overflow-hidden" v-if="product.variation_names">{{
                                            product.variation_names }}</p>
                                        <div class="flex flex-wrap items-center justify-between gap-4">
                                            <div class="flex items-center gap-8">
                                                <span class="text-sm font-semibold">{{
                                                    product.currency_price
                                                }}</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="flex items-center gap-3 relative mt-3" v-if="product.exchange_product_id">
                                    <span class="text-xs font-semibold text-heading ltr:pl-8 rtl:pr-8">
                                        {{ $t('label.exchange_with') }}:
                                    </span>
                                    <img class="w-12 h-12 rounded-lg flex-shrink-0" :src="product.exchange_product_image"
                                        alt="thumbnail">
                                    <div class="flex-auto overflow-hidden">
                                        <h4 class="text-sm capitalize whitespace-nowrap overflow-hidden text-ellipsis">
                                            {{ product.exchange_product_name }}</h4>
                                        <span class="text-sm font-semibold">{{
                                            product.exchange_product_currency_price
                                        }} x {{ product.exchange_product_quantity }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="flex items-center justify-between p-4 border-t border-dashed border-[#EFF0F6]">
                            <h4 class="text-sm leading-6 font-bold capitalize">{{ $t('label.total') }}</h4>
                            <h5 class="text-sm leading-6 font-bold capitalize">
                                {{ order.exchange_total_currency_price }}
                            </h5>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-12" v-if="order.images?.length > 0">
                <div class="db-card">
                    <div class="db-card-header">
                        <h3 class="db-card-title">{{ $t('label.attachment') }}</h3>
                    </div>
                    <div class="db-card-body">
                        <div class="inline-flex flex-wrap gap-4">
                            <img data-modal="imagePreviewModal" :src="image" alt="exchange" class="w-20 h-20 rounded-lg object-cover"
                                v-for="image in order.images" @click="previewImage(image)" />
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-12" v-if="order.status === enums.exchangeStatusEnum.REJECTED">
                <div class="db-card">
                    <div class="db-card-header">
                        <h3 class="db-card-title">{{ $t('label.reject_reason') }}</h3>
                    </div>
                    <div class="db-card-body">
                        <p class="capitalize">{{ order.reject_reason }}</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12 sm:col-6">
        <div class="row">
            <div class="col-12" v-if="order.exchange_reason_name">
                <div class="db-card">
                    <div class="db-card-header">
                        <h3 class="db-card-title">{{ $t('label.exchange_reason') }}</h3>
                    </div>
                    <div class="db-card-body">
                        <p class="capitalize">{{ order.exchange_reason_name }}</p>
                    </div>
                </div>
            </div>
            <div class="col-12" v-if="order.note">
                <div class="db-card">
                    <div class="db-card-header">
                        <h3 class="db-card-title">{{ $t('label.exchange_note') }}</h3>
                    </div>
                    <div class="db-card-body">
                        <p class="capitalize">{{ order.note }}</p>
                    </div>
                </div>
            </div>
            <div class="col-12">
                <div class="db-card">
                    <div class="db-card-header">
                        <h3 class="db-card-title">{{ $t('label.information') }}</h3>
                    </div>
                    <div class="db-card-body">
                        <div class="flex items-center gap-3 mb-4">
                            <img class="w-8 rounded-full" :src="exchangeOrderUser.image" alt="avatar">
                            <h4 class="font-semibold text-sm capitalize text-[#374151]">
                                {{ textShortener(exchangeOrderUser.name, 20) }}
                            </h4>
                        </div>
                        <ul class="flex flex-col gap-3 py-4 border-t border-[#EFF0F6]">
                            <li class="flex items-center gap-2.5">
                                <i class="lab lab-line-mail lab-font-size-14"></i>
                                <span class="text-xs">{{ exchangeOrderUser.email }}</span>
                            </li>
                            <li v-if="exchangeOrderUser.phone" class="flex items-center gap-2.5">
                                <i class="lab lab-line-call-calling lab-font-size-14"></i>
                                <span class="text-xs">{{ exchangeOrderUser.country_code + '' + exchangeOrderUser.phone }}</span>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div id="imagePreviewModal" class="modal flex items-center" @click="hidePreviewImage">
        <div class="max-w-lg w-full mx-auto relative ">
            <button @click="hidePreviewImage"
                class="absolute top-2 right-2 text-white bg-black bg-opacity-50 rounded-full w-8 h-8 hover:bg-opacity-75">✕</button>
            <img data-modal="imagePreviewModal" :src="previewImg" alt="exchange" class="w-full h-full rounded-lg object-cover" />
        </div>
    </div>
</template>

<script>
import LoadingComponent from "../components/LoadingComponent";
import appService from "../../../services/appService";
import exchangeStatusEnum from "../../../enums/modules/exchangeStatusEnum";
import alertService from "../../../services/alertService";
import ProductExchangeReasonComponent from "./ProductExchangeReasonComponent";

export default {
    name: "ProductExchangeShowComponent",
    components: {
        LoadingComponent,
        ProductExchangeReasonComponent
    },
    data() {
        return {
            loading: {
                isActive: false
            },
            payment_status: null,
            exchange_status: null,
            enums: {
                exchangeStatusEnum: exchangeStatusEnum,
                exchangeStatusEnumArray: {
                    [exchangeStatusEnum.PENDING]: this.$t("label.pending"),
                    [exchangeStatusEnum.ACCEPT]: this.$t("label.accept"),
                    [exchangeStatusEnum.REJECTED]: this.$t("label.rejected"),
                },
            },
            previewImg: null
        }
    },
    computed: {
        order: function () {
            return this.$store.getters['productExchange/show'];
        },
        exchangeProducts: function () {
            return this.$store.getters['productExchange/exchangeProducts'];
        },
        exchangeOrderUser: function () {
            return this.$store.getters['productExchange/exchangeOrderUser'];
        },
    },
    mounted() {
        this.loading.isActive = true;
        this.$store.dispatch('productExchange/show', this.$route.params.id).then(res => {
            this.payment_status = res.data.data.payment_status;
            this.exchange_status = res.data.data.status;
            this.loading.isActive = false;
        }).catch((error) => {
            this.loading.isActive = false;
        });
    },
    methods: {
        orderStatusClass: function (status) {
            return appService.returnStatusClass(status);
        },
        textShortener: function (text, number = 30) {
            return appService.textShortener(text, number);
        },
        previewImage: function (img) {
            this.previewImg = img;
            appService.modalShow('#imagePreviewModal');
        },
        hidePreviewImage: function () {
            appService.modalHide('#imagePreviewModal');
        },
        changeStatus: function (status) {
            appService.acceptOrder().then((res) => {
                try {
                    this.loading.isActive = true;
                    this.$store.dispatch("productExchange/changeStatus", {
                        id: this.$route.params.id,
                        status: status,
                    }).then((res) => {
                        this.exchange_status = res.data.data.status;
                        this.loading.isActive = false;
                        alertService.successFlip(
                            1,
                            this.$t("label.status")
                        );
                    }).catch((err) => {
                        this.loading.isActive = false;
                        alertService.error(err.response.data.message);
                    });
                } catch (err) {
                    this.loading.isActive = false;
                    alertService.error(err.response.data.message);
                }
            }).catch((err) => {
                this.loading.isActive = false;
            });
        },
    }
}
</script>
