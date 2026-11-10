<?php

namespace Database\Seeders;


use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        if ($this->isAlreadySeeded()) {
            $this->command?->warn(
                'Database already contains data. Skipping DatabaseSeeder so existing rows are not duplicated. ' .
                'Seeders are intended for an empty database; use php artisan migrate:fresh --seed for a clean install.'
            );

            return;
        }

        $this->call(CompanyTableSeeder::class);
        $this->call(ThemeTableSeeder::class);
        $this->call(CookiesTableSeeder::class);
        $this->call(SiteTableSeeder::class);
        $this->call(MenuTableSeeder::class);
        $this->call(PermissionTableSeeder::class);
        $this->call(RoleTableSeeder::class);
        $this->call(RolePermissionTableSeeder::class);
        $this->call(UserTableSeeder::class);
        $this->call(LanguageTableSeeder::class);
        $this->call(BarcodeTableSeeder::class);
        $this->call(MenuTemplateTableSeeder::class);
        $this->call(BenefitTableSeeder::class);
        $this->call(MenuSectionTableSeeder::class);
        $this->call(PaymentGatewayTableSeeder::class);
        $this->call(SmsGatewayTableSeeder::class);
        $this->call(SocialLoginTableSeeder::class);
        $this->call(NotificationAlertTableSeeder::class);
        $this->call(OtpTableSeeder::class);
        $this->call(SocialMediaTableSeeder::class);
        $this->call(ShippingSetupTableSeeder::class);
        $this->call(PaymentGatewayDataTableSeeder::class);
        $this->call(ReturnReasonTableSeeder::class);
        $this->call(ExchangeReasonTableSeeder::class);
        $this->call(MailTableSeeder::class);
        $this->call(SliderTableSeeder::class);
        $this->call(ProductBrandTableSeeder::class);
        $this->call(ProductCategoryTableSeeder::class);
        $this->call(TaxTableSeeder::class);
        $this->call(UnitTableSeeder::class);
        $this->call(ProductAttributeTableSeeder::class);
        $this->call(ProductAttributeOptionTableSeeder::class);
        $this->call(SupplierTableSeeder::class);
        $this->call(PushNotificationTableSeeder::class);
        $this->call(ProductTableSeeder::class);
        $this->call(ProductVariationTableSeeder::class);
        $this->call(CouponTableSeeder::class);
        $this->call(PromotionTableSeeder::class);
        $this->call(ProductSectionTableSeeder::class);
        $this->call(OutletTableSeeder::class);
        $this->call(PageTableSeeder::class);
        $this->call(WishlistTableSeeder::class);
        $this->call(PurchaseTableSeeder::class);
        $this->call(OrderTableSeeder::class);
        $this->call(PosOrderTableSeeder::class);
        $this->call(DamageTableSeeder::class);
        $this->call(ReturnOrderTableSeeder::class);
        $this->call(ReturnAndRefundTableSeeder::class);
        $this->call(ProductReviewTableSeeder::class);
        $this->call(CurrencyTableSeeder::class);
        $this->call(SubscriberTableSeeder::class);
        $this->call(NotificationTableSeeder::class);
        $this->call(LicenseTableSeeder::class);
        $this->call(CountryTableSeeder::class);
        $this->call(StateTableSeeder::class);
        $this->call(CityTableSeeder::class);
        $this->call(AiAgentTableSeeder::class);
        $this->call(AiAgentDataTableSeeder::class);

        if (env('DEMO')) {
            $this->call(DemoMediaTableSeeder::class);
        }
    }

    /**
     * The bundled seeders use bulk inserts, so running them twice would violate
     * unique constraints and leave the database half seeded.
     */
    private function isAlreadySeeded(): bool
    {
        return Schema::hasTable('users') && DB::table('users')->exists();
    }
}
