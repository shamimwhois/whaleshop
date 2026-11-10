<?php

namespace App\Ai;

use App\Ai\Tools\CouponCheck;
use App\Ai\Tools\OrderStatus;
use App\Ai\Tools\PolicyLookup;
use App\Ai\Tools\ProductAvailability;
use App\Ai\Tools\ProductDetails;
use App\Ai\Tools\ProductSearch;
use Laravel\Ai\Contracts\Tool;

/**
 * Resolves the enabled tool set from configuration.
 *
 * Tools are opt-in per "config/ai_commerce.php", so a store can disable any
 * capability without code changes. This is the single place the agent asks for
 * tools, which keeps enablement logic and instantiation in one spot.
 */
class ToolRegistry
{
    /**
     * @return list<Tool>
     */
    public function enabled(): array
    {
        $map = [
            'product_search'       => ProductSearch::class,
            'product_details'      => ProductDetails::class,
            'product_availability' => ProductAvailability::class,
            'order_status'         => OrderStatus::class,
            'coupon_check'         => CouponCheck::class,
            'policy_lookup'        => PolicyLookup::class,
        ];

        $tools = [];

        foreach ($map as $key => $class) {
            if (config("ai_commerce.tools.{$key}", false)) {
                $tools[] = app($class);
            }
        }

        return $tools;
    }

    /**
     * @return list<class-string<Tool>>
     */
    public function names(): array
    {
        return array_map(
            static fn (Tool $tool): string => class_basename($tool),
            $this->enabled()
        );
    }
}
