<template>
    <LoadingComponent :props="loading" />

    <div class="flex items-center gap-4 mb-7">
        <button @click.prevent="$router.back()" class="lab-line-undo text-xl font-bold text-primary"></button>
        <h2 class="capitalize text-xl font-bold text-primary">{{ $t('label.exchange_order_details') }}</h2>
    </div>

    <div class="rounded-2xl shadow-card mb-6 bg-white">
        <h3 class="font-semibold p-4">{{ $t('label.order_id') }}: #{{ exchangeOrder.order_serial_no }}</h3>
        <div class="mobile:overflow-x-auto">
            <table class="w-full text-left text-sm capitalize">
                <thead class="font-semibold border-b border-t border-gray-200">
                    <tr>
                        <th class="p-4">{{ $t('label.products') }}</th>
                        <th class="p-4">{{ $t('label.exchange_with') }}</th>
                        <th class="p-4">{{ $t('label.price') }}</th>
                    </tr>
                </thead>
                <tbody class="font-medium" v-if="exchangeProducts.length > 0">
                    <tr v-for="exchangeProduct in exchangeProducts" class="group">
                        <td class="p-4 border-b group-last:border-0 border-gray-100">
                            <div class="inline-flex items-center gap-3">
                                <img :src="exchangeProduct.product_image" alt="product"
                                    class="w-12 h-12 object-cover rounded-md flex-shrink-0" />
                                <div class="overflow-hidden">
                                    <h4 class="text-sm capitalize whitespace-nowrap overflow-hidden text-ellipsis mb-0.5">
                                        {{ exchangeProduct.product_name }}
                                    </h4>
                                    <p class="text-sm capitalize whitespace-nowrap overflow-hidden text-ellipsis"
                                        v-if="exchangeProduct.variation_names">
                                        {{ exchangeProduct.variation_names }}</p>
                                    <span class="text-xs">x{{ exchangeProduct.quantity }}</span>
                                </div>
                            </div>
                        </td>
                        <td class="p-4 border-b group-last:border-0 border-gray-100">
                            <div class="inline-flex items-center gap-3" v-if="exchangeProduct.exchange_product_id">
                                <img :src="exchangeProduct.exchange_product_image" alt="product"
                                    class="w-12 h-12 object-cover rounded-md flex-shrink-0" />
                                <div class="overflow-hidden">
                                    <h4 class="text-sm capitalize whitespace-nowrap overflow-hidden text-ellipsis mb-0.5">
                                        {{ exchangeProduct.exchange_product_name }}
                                    </h4>
                                    <span class="text-xs">x{{ exchangeProduct.exchange_product_quantity }}</span>
                                </div>
                            </div>
                            <span v-else class="text-xs text-text">--</span>
                        </td>
                        <td class="p-4 border-b group-last:border-0 border-gray-100">{{ exchangeProduct.currency_price }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <div class="rounded-2xl shadow-card p-4 mb-6 bg-white mobile:mb-20">
        <dl class="mb-5" v-if="exchangeOrder.exchange_reason_name">
            <dt class="font-semibold capitalize mb-2">{{ $t('label.exchange_reason') }}</dt>
            <dd class="text-sm">{{ exchangeOrder.exchange_reason_name }}</dd>
        </dl>
        <dl class="mb-5" v-if="exchangeOrder.note">
            <dt class="font-semibold capitalize mb-2">{{ $t('label.exchange_note') }}</dt>
            <dd class="text-sm">{{ exchangeOrder.note }}</dd>
        </dl>
        <dl v-if="exchangeOrder.images?.length > 0">
            <dt class="font-semibold capitalize mb-3">{{ $t('label.attachment') }}</dt>
            <dd class="inline-flex flex-wrap gap-4">
                <img :src="image" alt="exchange" class="w-20 h-20 rounded-lg object-cover"
                    v-for="image in exchangeOrder.images" />
            </dd>
        </dl>
    </div>
    <div class="rounded-2xl shadow-card p-4 bg-white mobile:mb-20">
        <h3 class="capitalize font-semibold mb-2 text-focus">{{ $t('label.exchange_response') }}</h3>
        <dl class="flex items-center gap-1 mb-3">
            <dt class="capitalize text-sm">{{ $t('label.status') }}:</dt>
            <dd class="capitalize text-sm font-semibold text-success">
                <span :class="orderStatusClass(exchangeOrder.status)">
                    {{ enums.exchangeStatusEnumArray[exchangeOrder.status] }}
                </span>
            </dd>
        </dl>
        <p class="text-sm" v-if="exchangeOrder.status === enums.exchangeStatusEnum.REJECTED"> {{ exchangeOrder.reject_reason }}
        </p>
    </div>
</template>

<script>
import LoadingComponent from "../../components/LoadingComponent";
import exchangeStatusEnum from "../../../../enums/modules/exchangeStatusEnum";
import appService from "../../../../services/appService";
export default {
    name: "ExchangeOrderDetailsComponent",
    components: { LoadingComponent },
    data() {
        return {
            loading: {
                isActive: false,
            },
            enums: {
                exchangeStatusEnum: exchangeStatusEnum,
                exchangeStatusEnumArray: {
                    [exchangeStatusEnum.PENDING]: this.$t("label.pending"),
                    [exchangeStatusEnum.ACCEPT]: this.$t("label.accepted"),
                    [exchangeStatusEnum.REJECTED]: this.$t("label.rejected"),
                },
            },

        };
    },
    mounted() {
        this.loading.isActive = true;
        if (this.$route.params.id) {
            this.loading.isActive = true;
            this.$store.dispatch("frontendProductExchange/show", this.$route.params.id).then(res => {
                this.loading.isActive = false;
            }).catch((error) => {
                this.loading.isActive = false;
            });
        }
    },
    computed: {
        exchangeOrder: function () {
            return this.$store.getters["frontendProductExchange/show"];
        },
        exchangeProducts: function () {
            return this.$store.getters['frontendProductExchange/exchangeProducts'];
        },
    },
    methods: {
        orderStatusClass: function (status) {
            return appService.returnStatusClass(status);
        },
    }
}
</script>
