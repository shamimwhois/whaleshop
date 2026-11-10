<?php

namespace App\Services;

use Exception;
use App\Enums\Ask;
use App\Models\User;
use App\Models\Order;
use App\Models\ProductExchange;
use App\Enums\ExchangeOrderStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use App\Http\Requests\PaginateRequest;
use App\Models\ProductExchangeProduct;
use Dipokhalder\Settings\Facades\Settings;
use App\Http\Requests\OrderStatusRequest;
use App\Http\Requests\ProductExchangeRequest;
use App\Libraries\QueryExceptionLibrary;

class ProductExchangeService
{
    public object $productExchange;

    protected $exchangeFilter = [
        'order_serial_no',
        'user_id',
        'status',
    ];

    protected $exceptFilter = [
        'excepts'
    ];

    /**
     * @throws Exception
     */
    public function list(PaginateRequest $request, $auth = false)
    {
        try {
            $requests    = $request->all();
            $method      = $request->get('paginate', 0) == 1 ? 'paginate' : 'get';
            $methodValue = $request->get('paginate', 0) == 1 ? $request->get('per_page', 10) : '*';
            $orderColumn = $request->get('order_column') ?? 'id';
            $orderType   = $request->get('order_type') ?? 'desc';

            return ProductExchange::where(function ($query) use ($requests, $auth) {
                if ($auth) {
                    $query->where('user_id', auth()->user()->id);
                }
                if (isset($requests['from_date']) && isset($requests['to_date'])) {
                    $first_date = Date('Y-m-d', strtotime($requests['from_date']));
                    $last_date  = Date('Y-m-d', strtotime($requests['to_date']));
                    $query->whereDate('created_at', '>=', $first_date)->whereDate(
                        'created_at',
                        '<=',
                        $last_date
                    );
                }
                foreach ($requests as $key => $request) {
                    if (in_array($key, $this->exchangeFilter)) {
                        $query->where($key, 'like', '%' . $request . '%');
                    }

                    if (in_array($key, $this->exceptFilter)) {
                        $explodes = explode('|', $request);
                        if (is_array($explodes)) {
                            foreach ($explodes as $explode) {
                                $query->where('id', '!=', $explode);
                            }
                        }
                    }
                }
            })->orderBy($orderColumn, $orderType)->$method(
                $methodValue
            );
        } catch (Exception $exception) {
            Log::info($exception->getMessage());
            throw new Exception(QueryExceptionLibrary::message($exception), 422);
        }
    }


    /**
     * @throws Exception
     */
    public function store(ProductExchangeRequest $request): object
    {
        try {
            $productExchange = ProductExchange::where('order_id', $request->order_id)->first();
            if ($productExchange) {
                throw new Exception(trans('all.message.exchange_request_exist'), 422);
            } else {
                $order = Order::findOrFail($request->order_id);
                DB::transaction(function () use ($request, $order) {
                    $this->productExchange = ProductExchange::create([
                        'exchange_reason_id'        => $request->exchange_reason_id,
                        'note'                      => $request->note,
                        'order_id'                  => $request->order_id,
                        'user_id'                   => Auth::user()->id,
                        'order_serial_no'           => $request->order_serial_no,
                        'exchange_price_difference' => 0,
                        'status'                    => ExchangeOrderStatus::PENDING,
                    ]);

                    $exchangePriceDifference = 0;
                    $products = json_decode($request->products, true);

                    if (isset($products) && count($products) > 0) {
                        foreach ($products as $product) {
                            $exchangeProductTotal = ($product['exchange_product_price'] ?? 0) * ($product['exchange_product_quantity'] ?? 0);
                            $exchangePriceDifference += ($exchangeProductTotal - $product['total']);

                            ProductExchangeProduct::create([
                                'product_exchange_id'       => $this->productExchange->id,
                                'product_id'                => $product['product_id'],
                                'variation_id'              => $product['has_variation'] ? $product['variation_id'] : null,
                                'variation_names'           => $product['has_variation'] ? $product['variation_names'] : null,
                                'quantity'                  => $product['quantity'],
                                'price'                     => $product['price'],
                                'total'                     => $product['total'],
                                'exchange_price'            => $product['total'],
                                'exchange_product_id'       => $product['exchange_product_id'] ?? null,
                                'exchange_product_quantity' => $product['exchange_product_quantity'] ?? 0,
                                'exchange_product_price'    => $product['exchange_product_price'] ?? 0,
                                'exchange_product_total'    => $exchangeProductTotal,
                                'user_id'                   => Auth::user()->id,
                            ]);
                        }
                    }

                    $this->productExchange->exchange_price_difference = $exchangePriceDifference;
                    $this->productExchange->save();

                    if ($request->image) {
                        foreach ($request->image as $image) {
                            $this->productExchange->addMedia($image)->toMediaCollection('exchange');
                        }
                    }
                });
                return $this->productExchange;
            }
        } catch (Exception $exception) {
            DB::rollBack();
            Log::info($exception->getMessage());
            throw new Exception(QueryExceptionLibrary::message($exception), 422);
        }
    }


    /**
     * @throws Exception
     */
    public function show(ProductExchange $productExchange, $auth = false): ProductExchange|array
    {
        try {
            if ($auth) {
                if ($productExchange->user_id == Auth::user()->id) {
                    return $productExchange;
                } else {
                    return [];
                }
            } else {
                return $productExchange;
            }
        } catch (Exception $exception) {
            Log::info($exception->getMessage());
            throw new Exception(QueryExceptionLibrary::message($exception), 422);
        }
    }

    /**
     * @throws Exception
     */
    public function changeStatus(ProductExchange $productExchange, OrderStatusRequest $request): ProductExchange
    {
        try {

            if ($request->status == ExchangeOrderStatus::REJECTED) {
                $request->validate([
                    'reason' => 'required|max:700',
                ]);

                if ($request->reason) {
                    $productExchange->reject_reason = $request->reason;
                }
            }

            if ($request->status == ExchangeOrderStatus::ACCEPT) {
                $order = Order::findOrFail($productExchange->order_id);
                $user  = User::findOrFail($productExchange->user_id);

                $priceDifference = $productExchange->exchange_price_difference;

                if ($priceDifference < 0 && $order->payment_method !== 1 && Settings::group('site')->get('site_is_return_product_price_add_to_credit') === Ask::YES) {
                    $user->balance += abs($priceDifference);
                    $user->save();
                }
            }

            $productExchange->status = $request->status;
            $productExchange->save();
            return $productExchange;
        } catch (Exception $exception) {
            Log::info($exception->getMessage());
            throw new Exception(QueryExceptionLibrary::message($exception), 422);
        }
    }
}
