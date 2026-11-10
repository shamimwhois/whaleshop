<template>
    <LoadingComponent :props="loading" />
    <h2 class="capitalize text-2xl font-bold mb-7 text-primary">{{ $t('label.exchange_orders') }}</h2>
    <div class="rounded-2xl shadow-card bg-white mobile:mb-20">
        <div class="max-md:overflow-x-auto">
            <table class="w-full text-left text-sm capitalize">
                <thead class="font-semibold border-b-2 border-gray-200">
                    <tr>
                        <th class="p-4">{{ $t('label.order_id') }}</th>
                        <th class="p-4">{{ $t('label.products') }}</th>
                        <th class="p-4">{{ $t('label.status') }}</th>
                        <th class="p-4">{{ $t('label.amount') }}</th>
                        <th class="p-4">{{ $t('label.action') }}</th>
                    </tr>
                </thead>
                <tbody class="font-medium" v-if="exchangeOrders.length > 0">
                    <tr v-for="exchangeOrder in exchangeOrders" :key="exchangeOrder">
                        <td class="p-4 border-t border-gray-100">
                            <h5 class="font-semibold mb-1">{{ exchangeOrder.order_serial_no }}</h5>
                            <p class="text-xs text-text">{{ exchangeOrder.exchange_datetime }}</p>
                        </td>
                        <td class="p-4 border-t border-gray-100">
                            {{ exchangeOrder.exchange_items }} {{ $t('label.product') }}
                        </td>
                        <td class="p-4 border-t border-gray-100">
                            <span :class="orderStatusClass(exchangeOrder.status)">
                                {{ enums.exchangeStatusEnumArray[exchangeOrder.status] }}
                            </span>
                        </td>
                        <td class="p-4 border-t border-gray-100">{{ exchangeOrder.exchange_total_currency_price }}</td>
                        <td class="p-4 border-t border-gray-100">
                            <RouterLink
                                :to="{ name: 'frontend.account.exchangeOrder.details', params: { id: exchangeOrder.id } }">
                                <i
                                    class="lab-fill-eye w-[30px] h-[30px] text-center rounded-lg text-white bg-primary shadow-action before:mt-1 lab-font-size-16"></i>
                            </RouterLink>
                        </td>
                    </tr>
                </tbody>
                <tbody class="db-table-body" v-else>
                    <tr class="db-table-body-tr">
                        <td class="db-table-body-td text-center" colspan="5">
                            <div class="p-4">
                                <div class="max-w-[300px] mx-auto mt-2">
                                    <img class="w-full h-full" :src="setting.not_found" alt="Not Found">
                                </div>
                                <span class="d-block mt-3 text-lg">{{ $t('message.no_data_found') }}</span>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div class="px-4 py-6 border-t border-gray-100" v-if="exchangeOrders.length > 0">
            <div class="hidden sm:flex sm:flex-1 sm:items-center sm:justify-between">
                <PaginationTextComponent :props="{ page: paginationPage }" />
                <PaginationComponent @pagination-change-page="list" :data="pagination" :limit="1" :keep-length="false" />
            </div>
        </div>
    </div>
</template>

<script>
import LoadingComponent from "../../components/LoadingComponent";
import exchangeStatusEnum from "../../../../enums/modules/exchangeStatusEnum";
import appService from "../../../../services/appService";
import PaginationComponent from "../../components/PaginationComponent";
import PaginationTextComponent from "../../components/PaginationTextComponent";
export default {
    name: "ExchangeOrdersComponent",
    components: { LoadingComponent, PaginationComponent, PaginationTextComponent },
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

            search: {
                paginate: 1,
                page: 1,
                per_page: 10,
                order_column: "id",
                order_by: "desc",

            },

        };
    },
    mounted() {
        this.list();
    },
    computed: {
        setting: function () {
            return this.$store.getters['frontendSetting/lists'];
        },
        exchangeOrders: function () {
            return this.$store.getters["frontendProductExchange/lists"];
        },
        pagination: function () {
            return this.$store.getters["frontendProductExchange/pagination"];
        },
        paginationPage: function () {
            return this.$store.getters['frontendProductExchange/page'];
        },
    },
    methods: {
        orderStatusClass: function (status) {
            return appService.returnStatusClass(status);
        },
        list: function (page = 1) {
            this.loading.isActive = true;
            this.search.page = page;
            this.$store.dispatch("frontendProductExchange/lists", {
                search: this.search
            }).then((res) => {
                this.loading.isActive = false;
            }).catch((err) => {
                this.loading.isActive = false;
            });
        },
    }
}
</script>
