<?php

namespace App\Http\Controllers\Frontend;

use Exception;
use App\Models\ProductExchange;
use App\Http\Controllers\Controller;
use App\Http\Requests\PaginateRequest;
use App\Services\ProductExchangeService;
use App\Http\Requests\ProductExchangeRequest;
use App\Http\Resources\ProductExchangeResource;
use App\Http\Resources\ProductExchangeDetailsResource;

class ProductExchangeController extends Controller
{
    private ProductExchangeService $productExchangeService;

    public function __construct(ProductExchangeService $productExchangeService)
    {
        $this->productExchangeService = $productExchangeService;
    }

    public function index(
        PaginateRequest $request
    ): \Illuminate\Http\Response | \Illuminate\Http\Resources\Json\AnonymousResourceCollection | \Illuminate\Contracts\Foundation\Application | \Illuminate\Contracts\Routing\ResponseFactory {
        try {
            return ProductExchangeResource::collection($this->productExchangeService->list($request, true));
        } catch (Exception $exception) {
            return response(['status' => false, 'message' => $exception->getMessage()], 422);
        }
    }

    public function store(ProductExchangeRequest $request): \Illuminate\Http\Response | ProductExchangeResource | \Illuminate\Contracts\Foundation\Application | \Illuminate\Contracts\Routing\ResponseFactory
    {
        try {
            return new ProductExchangeResource($this->productExchangeService->store($request));
        } catch (Exception $exception) {
            return response(['status' => false, 'message' => $exception->getMessage()], 422);
        }
    }

    public function show(ProductExchange $productExchange): \Illuminate\Foundation\Application|\Illuminate\Http\Response|ProductExchangeDetailsResource|\Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\Routing\ResponseFactory
    {
        try {
            return new ProductExchangeDetailsResource($this->productExchangeService->show($productExchange, true));
        } catch (Exception $exception) {
            return response(['status' => false, 'message' => $exception->getMessage()], 422);
        }
    }
}
