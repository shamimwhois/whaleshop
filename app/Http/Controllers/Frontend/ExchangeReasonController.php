<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Resources\ExchangeReasonResource;
use Exception;
use App\Services\ExchangeReasonService;
use App\Http\Controllers\Controller;
use App\Http\Requests\PaginateRequest;

class ExchangeReasonController extends Controller
{
    private ExchangeReasonService $exchangeReasonService;

    public function __construct(ExchangeReasonService $exchangeReasonService)
    {
        $this->exchangeReasonService = $exchangeReasonService;
    }

    public function index(
        PaginateRequest $request
    ): \Illuminate\Http\Response | \Illuminate\Http\Resources\Json\AnonymousResourceCollection | \Illuminate\Contracts\Foundation\Application | \Illuminate\Contracts\Routing\ResponseFactory {
        try {
            return ExchangeReasonResource::collection($this->exchangeReasonService->list($request));
        } catch (Exception $exception) {
            return response(['status' => false, 'message' => $exception->getMessage()], 422);
        }
    }
}
