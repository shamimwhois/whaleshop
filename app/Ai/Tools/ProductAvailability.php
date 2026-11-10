<?php

namespace App\Ai\Tools;

use App\Models\Product;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * Read-only tool reporting whether a product (or a variation) is orderable.
 *
 * Kept deliberately conservative: it reports the model's configured purchase
 * flags rather than querying live stock tables, which vary per installation.
 */
class ProductAvailability implements Tool
{
    public function description(): Stringable|string
    {
        return 'Check whether a product can currently be purchased, including maximum purchase '
            . 'quantity and whether it is shown when out of stock.';
    }

    public function handle(Request $request): Stringable|string
    {
        $arguments = $request->validate([
            'product_id' => ['required', 'integer'],
        ]);

        $product = Product::find($arguments['product_id']);

        if ($product === null) {
            return 'No product exists with id ' . $arguments['product_id'] . '.';
        }

        $payload = [
            'id'                        => $product->getKey(),
            'name'                      => $product->name,
            'purchasable'               => (bool) $product->can_purchasable,
            'show_when_out_of_stock'    => (bool) $product->show_stock_out,
            'maximum_purchase_quantity' => $product->maximum_purchase_quantity,
            'low_stock_warning'         => $product->low_stock_quantity_warning,
        ];

        return (string) json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'product_id' => $schema->integer()->required()->description('The product id to check.'),
        ];
    }
}
