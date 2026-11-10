<template>
    <LoadingComponent :props="loading" />

    <section v-if="categories.length > 0" class="sm:mb-10">
        <div class="container">
            <h2 class="text-2xl sm:text-4xl font-bold -mb-10">{{ $t('label.browse_by_categories')}}</h2>
            <Swiper dir="ltr" :speed="1000" :loop="true" :navigation="true" :modules="modules" class="navigate-swiper" :breakpoints="breakpoints">
                <SwiperSlide v-for="category in categories" class="mobile:!w-24">
                    <router-link :to="{name: 'frontend.product', query:{ category: category.slug}}"
                                 class="w-full rounded-2xl shadow-xs group">
                        <img class="w-full h-[120px] object-cover block rounded-tl-2xl rounded-tr-2xl" :src="category.thumb" alt="category" >
                        <span class="text-sm sm:text-xl font-medium capitalize text-center py-2 px-3 overflow-hidden whitespace-nowrap text-ellipsis block rounded-bl-2xl rounded-br-2xl group-hover:text-primary">
                            {{ category.name }}
                        </span>
                    </router-link>
                </SwiperSlide>
            </Swiper>
        </div>

        <div class="container mt-10 sm:mt-14" v-if="categoryGroups.length > 0">
            <div v-for="group in categoryGroups" :key="group.id" class="mb-8 sm:mb-10">
                <div class="flex items-center justify-between gap-4 mb-5 sm:mb-7">
                    <h3 class="text-xl sm:text-3xl font-bold capitalize">{{ group.name }}</h3>
                    <router-link :to="{ name: 'frontend.product', query: { category: group.slug } }"
                        class="py-2 px-4 text-sm sm:py-3 sm:px-6 rounded-3xl capitalize sm:text-base font-semibold whitespace-nowrap bg-primary-slate text-primary transition-all duration-300 hover:bg-primary hover:text-white">
                        {{ $t('label.show_more') }}
                    </router-link>
                </div>
                <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4 sm:gap-6">
                    <router-link v-for="child in group.children" :key="child.id"
                        :to="{ name: 'frontend.product', query: { category: child.slug } }"
                        class="w-full rounded-2xl shadow-xs group border border-gray-100 overflow-hidden">
                        <img class="w-full h-[140px] object-cover block" :src="child.cover" alt="category">
                        <span class="text-sm sm:text-lg font-medium capitalize text-center py-2 px-3 overflow-hidden whitespace-nowrap text-ellipsis block group-hover:text-primary">
                            {{ child.name }}
                        </span>
                    </router-link>
                </div>
            </div>
        </div>
    </section>
</template>

<script>

import { Navigation, Pagination, Autoplay } from 'swiper/modules';
import { Swiper, SwiperSlide } from 'swiper/vue';
import 'swiper/css';
import 'swiper/css/navigation';
import 'swiper/css/pagination';
import statusEnum from "../../../enums/modules/statusEnum";
import LoadingComponent from "../components/LoadingComponent";


export default {
    name: "CategoryComponent",
    components: {
        Swiper,
        SwiperSlide,
        LoadingComponent
    },
    setup() {
        return {
            modules: [Navigation, Pagination, Autoplay],
        }
    },
    data() {
        return {
            loading: {
                isActive: false,
            },
            settings: {
                itemsToShow: 6,
                wrapAround: false,
                snapAlign: "start"
            },
            breakpoints: {
                0: { slidesPerView: 'auto', spaceBetween: 16 },
                640: { slidesPerView: 4, spaceBetween: 24 },
                768: { slidesPerView: 5, spaceBetween: 24 },
                1024: { slidesPerView: 6, spaceBetween: 24 }
            },
        }
    },
    computed: {
        categories: function () {
            return this.$store.getters["frontendProductCategory/lists"];
        },
        trees: function () {
            return this.$store.getters["frontendProductCategory/trees"];
        },
        /**
         * Top-level categories that expose their subcategories, used to render
         * the category-driven product discover grids.
         */
        categoryGroups: function () {
            const groups = [];
            this.trees.forEach((category) => {
                if (category.children && category.children.length > 0) {
                    groups.push(category);
                }
            });
            return groups;
        },
    },
    mounted() {
        this.loading.isActive = true;
        this.$store.dispatch("frontendProductCategory/lists", {
            paginate: 0,
            order_column: "id",
            order_type: "asc",
            parent_id: null,
            status: statusEnum.ACTIVE,
        }).then(res => {
            this.loading.isActive = false;
        }).catch((err) => {
            this.loading.isActive = false;
        });

        this.$store.dispatch("frontendProductCategory/trees").then(res => {
        }).catch((err) => { });
    },
}
</script>


