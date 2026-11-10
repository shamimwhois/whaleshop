const ProductExchangeComponent = () => import("../../components/admin/productExchanges/ProductExchangeComponent");
const ProductExchangeListComponent = () => import("../../components/admin/productExchanges/ProductExchangeListComponent");
const ProductExchangeShowComponent = () => import("../../components/admin/productExchanges/ProductExchangeShowComponent");

export default [
    {
        path: '/admin/product-exchanges',
        component: ProductExchangeComponent,
        name: 'admin.productExchange',
        redirect: { name: 'admin.productExchange.list' },
        meta: {
            isFrontend: false,
            auth: true,
            permissionUrl: 'product-exchanges',
            breadcrumb: 'product_exchanges'
        },
        children: [
            {
                path: '',
                component: ProductExchangeListComponent,
                name: 'admin.productExchange.list',
                meta: {
                    isFrontend: false,
                    auth: true,
                    permissionUrl: 'product-exchanges',
                    breadcrumb: ''
                },
            },
            {
                path: "show/:id",
                component: ProductExchangeShowComponent,
                name: "admin.productExchange.show",
                meta: {
                    isFrontend: false,
                    auth: true,
                    permissionUrl: "product-exchanges",
                    breadcrumb: "view",
                },
            }
        ]
    }
]
