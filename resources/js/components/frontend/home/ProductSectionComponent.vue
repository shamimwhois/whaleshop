<template>
    <LoadingComponent :props="loading" />

    <div v-if="productSections.length > 0 || promotions.length > 0" class="p-0 m-0">
        <template v-for="(productSection, key) in productSections" :key="`section-${productSection.id}`">
            <section v-if="productSection.products.length > 0" class="mb-10 sm:mb-20">
                <div class="container">
                    <div class="flex items-center justify-between gap-4 mb-5 sm:mb-7">
                        <h2 class="text-2xl sm:text-4xl font-bold capitalize">
                            {{ productSection.name }}
                        </h2>
                        <router-link v-if="productSections.length === 8"
                            :to="{ name: 'frontend.productSection.products', params: { slug: productSection.slug } }"
                            class="py-2 px-4 text-sm sm:py-3 sm:px-6 rounded-3xl capitalize sm:text-base font-semibold whitespace-nowrap bg-primary-slate text-primary transition-all duration-300 hover:bg-primary hover:text-white">
                            {{ $t('label.show_more') }}
                        </router-link>
                    </div>
                    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4 sm:gap-6">
                        <ProductListComponent :products="productSection.products" />
                    </div>
                </div>
            </section>

            <section class="mb-10 sm:mb-20" v-if="promotions[key]">
                <div class="container">
                    <router-link :to="{ name: 'frontend.promotion.products', params: { slug: promotions[key].slug } }">
                        <img class="w-full rounded-3xl" :src="promotions[key].preview" alt="promotion">
                    </router-link>
                </div>
            </section>
        </template>

        <section v-if="productSections.length === 0" v-for="promotion in promotions" :key="`promotion-${promotion.id}`"
            class="mb-10 sm:mb-20">
            <div class="container">
                <router-link :to="{ name: 'frontend.promotion.products', params: { slug: promotion.slug } }">
                    <img class="w-full rounded-3xl" :src="promotion.preview" alt="promotion">
                </router-link>
            </div>
        </section>
    </div>
</template>

<script>
import LoadingComponent from "../components/LoadingComponent.vue";
import promotionTypeEnum from "../../../enums/modules/promotionTypeEnum";
import statusEnum from "../../../enums/modules/statusEnum";
import ProductListComponent from "../components/ProductListComponent.vue";

export default {
    name: "ProductSectionComponent",
    components: {
        ProductListComponent,
        LoadingComponent
    },
    data() {
        return {
            loading: {
                isActive: false,
            },
            promotions: []
        }
    },
    computed: {
        productSections: function () {
            return this.$store.getters["frontendProductSection/lists"];
        },
    },
    mounted() {
        this.loading.isActive = true;
        this.$store.dispatch("frontendProductSection/lists").then(res => {
            this.loading.isActive = false;
        }).catch((err) => {
            this.loading.isActive = false;
        });

        this.$store.dispatch("frontendPromotion/lists", {
            paginate: 0,
            order_column: "id",
            order_type: "asc",
            type: promotionTypeEnum.BIG,
            status: statusEnum.ACTIVE,
            vuex: false
        }).then(res => {
            this.promotions = res.data.data;
        }).catch((err) => { });
    }
}
</script>