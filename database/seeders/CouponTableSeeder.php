<?php

namespace Database\Seeders;

use Database\Seeders\Concerns\SeedsMedia;

use Carbon\Carbon;
use App\Enums\TaxType;
use App\Models\Coupon;
use Illuminate\Database\Seeder;
use Dipokhalder\EnvEditor\EnvEditor;


class CouponTableSeeder extends Seeder
{
    use SeedsMedia;


    public function run()
    {
        $envService = new EnvEditor();
        if ($envService->getValue('DEMO') && $envService->getValue('DISPLAY_TYPE') == 'fashion') {
            $coupons = [
                [
                    'name'             => 'Super Denim Collection',
                    'code'             => 'denim',
                    'discount'         => '15.00',
                    'discount_type'    => TaxType::PERCENTAGE,
                    'start_date'       => now(),
                    'end_date'         => Carbon::now()->addDay(365),
                    'minimum_order'    => '99.00',
                    'maximum_discount' => '99.00',
                    'limit_per_user'   => '5',
                    'created_at'       => now(),
                    'updated_at'       => now()
                ],
                [
                    'name'             => 'Festive Dressing',
                    'code'             => 'festive',
                    'discount'         => '50.00',
                    'discount_type'    => TaxType::FIXED,
                    'start_date'       => now(),
                    'end_date'         => Carbon::now()->addDay(365),
                    'minimum_order'    => '149.00',
                    'maximum_discount' => '50.00',
                    'limit_per_user'   => '5',
                    'created_at'       => now(),
                    'updated_at'       => now()
                ],
            ];
            foreach ($coupons as $coupon) {
                $couponObject = Coupon::firstOrCreate(['code' => $coupon['code']], $coupon);
                if ($couponObject->wasRecentlyCreated && file_exists(public_path('/images/seeder/coupon/' . env('DISPLAY_TYPE') . '/' . strtolower(str_replace(' ', '_', $coupon['code'])) . '.png'))) {
                    $this->attachMedia($couponObject, public_path('/images/seeder/coupon/' .env('DISPLAY_TYPE') . '/' . strtolower(str_replace(' ', '_', $coupon['code'])) . '.png'), 'coupon');
                }
            }
        }
    }
}