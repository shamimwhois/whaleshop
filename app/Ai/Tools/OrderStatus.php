<?php

namespace App\Ai\Tools;

use App\Models\Order;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Auth;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * Read-only tool exposing order status to the *authenticated* customer only.
 *
 * Ownership is enforced: a customer can only look up their own orders, which
 * prevents the agent from leaking another customer's data through prompt
 * injection.
 */
class OrderStatus implements Tool
{
    public function description(): Stringable|string
    {
        return 'Look up the status of the signed-in customer\'s own order by its serial number. '
            . 'Only orders belonging to the current customer are returned.';
    }

    public function handle(Request $request): Stringable|string
    {
        $arguments = $request->validate([
            'order_serial_no' => ['required', 'string', 'max:100'],
        ]);

        $user = Auth::user();

        if ($user === null) {
            return 'The customer is not signed in, so orders cannot be looked up. Ask them to sign in first.';
        }

        $order = Order::query()
            ->where('order_serial_no', $arguments['order_serial_no'])
            ->where('user_id', $user->getAuthIdentifier())
            ->first();

        if ($order === null) {
            return 'No order with that serial number belongs to the current customer.';
        }

        return (string) json_encode([
            'order_serial_no' => $order->order_serial_no,
            'status'          => $order->status,
            'payment_status'  => $order->payment_status,
            'subtotal'        => (float) $order->subtotal,
            'total'           => (float) $order->total,
            'placed_at'       => optional($order->created_at)->toDateTimeString(),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'order_serial_no' => $schema->string()->required()->description('The customer order serial number.'),
        ];
    }
}
