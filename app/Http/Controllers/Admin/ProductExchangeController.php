<?php

namespace App\Http\Controllers\Admin;

use Exception;
use App\Models\ProductExchange;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\ProductExchangeExport;
use App\Http\Requests\PaginateRequest;
use App\Services\ProductExchangeService;
use App\Http\Requests\OrderStatusRequest;
use Illuminate\Routing\Controllers\Middleware;
use App\Http\Resources\ProductExchangeResource;
use Illuminate\Routing\Controllers\HasMiddleware;
use App\Http\Resources\ProductExchangeDetailsResource;

class ProductExchangeController extends AdminController implements HasMiddleware
{
    private ProductExchangeService $productExchangeService;

    public function __construct(ProductExchangeService $productExchangeService)
    {
        parent::__construct();
        $this->productExchangeService = $productExchangeService;
    }

    public static function middleware(): array
    {
        return [
            new Middleware('permission:product-exchanges', only: ['index']),
            new Middleware('permission:product-exchanges', only: ['export']),
            new Middleware('permission:product-exchanges', only: ['show']),
            new Middleware('permission:product-exchanges', only: ['changeStatus']),
        ];
    }

    public function index(PaginateRequest $request): \Illuminate\Http\Response|\Illuminate\Http\Resources\Json\AnonymousResourceCollection|\Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\Routing\ResponseFactory
    {
        try {
            return ProductExchangeResource::collection($this->productExchangeService->list($request, false));
        } catch (Exception $exception) {
            return response(['status' => false, 'message' => $exception->getMessage()], 422);
        }
    }

    public function show(ProductExchange $productExchange): \Illuminate\Foundation\Application|\Illuminate\Http\Response|ProductExchangeDetailsResource|\Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\Routing\ResponseFactory
    {
        try {
            return new ProductExchangeDetailsResource($this->productExchangeService->show($productExchange, false));
        } catch (Exception $exception) {
            return response(['status' => false, 'message' => $exception->getMessage()], 422);
        }
    }

    public function changeStatus(ProductExchange $productExchange, OrderStatusRequest $request): \Illuminate\Http\Response|ProductExchangeDetailsResource|\Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\Routing\ResponseFactory
    {
        try {
            return new ProductExchangeDetailsResource($this->productExchangeService->changeStatus($productExchange, $request));
        } catch (Exception $exception) {
            return response(['status' => false, 'message' => $exception->getMessage()], 422);
        }
    }

    public function export(PaginateRequest $request): \Illuminate\Http\Response|\Symfony\Component\HttpFoundation\BinaryFileResponse|\Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\Routing\ResponseFactory
    {
        try {
            return Excel::download(new ProductExchangeExport($this->productExchangeService, $request), 'Product-Exchange.xlsx');
        } catch (Exception $exception) {
            return response(['status' => false, 'message' => $exception->getMessage()], 422);
        }
    }
}
