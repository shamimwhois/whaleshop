<?php

namespace App\Ai\Tools;

use App\Models\Product;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * Read-only tool returning the full detail of a single catalogue product.
 */
class ProductDetails implements Tool
{
    public function description(): Stringable|string
    {
        return 'Fetch full details for a single product by its id: name, price, brand, category, '
            . 'stock status and description. Use after a search to confirm specifics.';
    }

    public function handle(Request $request): Stringable|string
    {
        $arguments = $request->validate([
            'product_id' => ['required', 'integer'],
        ]);

        $product = Product::with(['category', 'brand'])->find($arguments['product_id']);

        if ($product === null) {
            return 'No product exists with id ' . $arguments['product_id'] . '.';
        }

        $payload = [
            'id'          => $product->getKey(),
            'name'        => $product->name,
            'sku'         => $product->sku,
            'price'       => (float) $product->selling_price,
            'category'    => $product->category?->name,
            'brand'       => $product->brand?->name,
            'in_stock'    => (bool) $product->show_stock_out,
            'warranty'    => $product->warranty,
            'refundable'  => (bool) $product->refundable,
            'description' => \Illuminate\Support\Str::limit(strip_tags((string) $product->description), 1200),
        ];

        return (string) json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'product_id' => $schema->integer()->required()->description('The product id to look up.'),
        ];
    }
}
