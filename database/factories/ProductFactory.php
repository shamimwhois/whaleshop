<?php

namespace Database\Factories;

use App\Enums\Activity;
use App\Enums\Ask;
use App\Enums\Status;
use App\Enums\ShippingType;
use App\Models\Product;
use App\Models\ProductBrand;
use App\Models\ProductCategory;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name  = fake()->unique()->words(3, true);
        $price = fake()->randomFloat(2, 10, 500);

        return [
            'name'                         => Str::title($name),
            'slug'                         => Str::slug($name) . '-' . fake()->unique()->numberBetween(1, 999999),
            'sku'                          => strtoupper(Str::random(3)) . '-' . fake()->unique()->numberBetween(100000, 999999),
            'product_category_id'          => ProductCategory::factory(),
            'product_brand_id'             => ProductBrand::factory(),
            'barcode_id'                   => null,
            'unit_id'                      => null,
            'buying_price'                 => round($price * 0.7, 2),
            'selling_price'                => $price,
            'variation_price'              => $price,
            'status'                       => Status::ACTIVE,
            'order'                        => fake()->numberBetween(1, 50),
            'can_purchasable'              => Ask::YES,
            'show_stock_out'               => Activity::ENABLE,
            'maximum_purchase_quantity'    => fake()->numberBetween(1, 10),
            'low_stock_quantity_warning'   => fake()->numberBetween(1, 5),
            'weight'                       => fake()->numberBetween(1, 50) . 'kg',
            'refundable'                   => Ask::YES,
            'description'                  => '<p>' . fake()->paragraph() . '</p>',
            'shipping_and_return'          => '<p>' . fake()->sentence() . '</p>',
            'add_to_flash_sale'            => Ask::NO,
            'discount'                     => 0,
            'offer_start_date'             => now(),
            'offer_end_date'               => now()->addDays(30),
            'shipping_type'                => ShippingType::FREE,
            'shipping_cost'                => 0,
            'is_product_quantity_multiply' => Ask::NO,
        ];
    }

    /**
     * Mark the product as active.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Status::ACTIVE,
        ]);
    }

    /**
     * Mark the product as inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Status::INACTIVE,
        ]);
    }

    /**
     * Add the product to the flash sale.
     */
    public function flashSale(): static
    {
        return $this->state(fn (array $attributes) => [
            'add_to_flash_sale' => Ask::YES,
        ]);
    }

    /**
     * Apply a percentage discount with a valid offer window.
     */
    public function discounted(int $discount = 20): static
    {
        return $this->state(fn (array $attributes) => [
            'discount'         => $discount,
            'offer_start_date' => now(),
            'offer_end_date'   => now()->addDays(30),
        ]);
    }
}
