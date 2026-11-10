<?php

namespace App\Ai\Tools;

use App\Models\Coupon;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * Read-only tool that validates a coupon code and reports its terms.
 *
 * This never applies the coupon (that is a write action handled by a separate,
 * approval-gated tool); it only tells the agent whether a code is usable.
 */
class CouponCheck implements Tool
{
    public function description(): Stringable|string
    {
        return 'Validate a coupon or discount code and report its type, value, minimum order and '
            . 'expiry. Use this before telling a customer their code is valid.';
    }

    public function handle(Request $request): Stringable|string
    {
        $arguments = $request->validate([
            'code' => ['required', 'string', 'max:100'],
        ]);

        /** @var class-string<\Illuminate\Database\Eloquent\Model> $model */
        $model = \App\Models\Coupon::class;

        $coupon = $model::query()->where('code', $arguments['code'])->first();

        if ($coupon === null) {
            return 'No coupon exists with code ' . $arguments['code'] . '.';
        }

        return (string) json_encode([
            'code'             => $coupon->getAttribute('code'),
            'valid'            => true,
            'discount'         => $coupon->getAttribute('discount'),
            'discount_type'    => $coupon->getAttribute('discount_type'),
            'minimum_order'    => $coupon->getAttribute('minimum_order'),
            'maximum_discount' => $coupon->getAttribute('maximum_discount'),
            'expires_at'       => $coupon->getAttribute('expire_date'),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'code' => $schema->string()->required()->description('The coupon code to validate.'),
        ];
    }
}
