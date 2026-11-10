<?php

namespace App\Http\Controllers\Admin;

use Exception;
use App\Models\ExchangeReason;
use App\Services\ExchangeReasonService;
use App\Http\Requests\PaginateRequest;
use App\Http\Requests\ExchangeReasonRequest;
use App\Http\Resources\ExchangeReasonResource;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Routing\Controllers\HasMiddleware;

class ExchangeReasonController extends AdminController implements HasMiddleware
{
    public ExchangeReasonService $exchangeReasonService;

    public function __construct(ExchangeReasonService $exchangeReasonService)
    {
        parent::__construct();
        $this->exchangeReasonService = $exchangeReasonService;
    }

    public static function middleware(): array
    {
        return [
            new Middleware('permission:settings', only: ['index', 'store', 'update', 'destroy', 'show']),
        ];
    }

    public function index(PaginateRequest $request): \Illuminate\Foundation\Application|\Illuminate\Http\Response|\Illuminate\Http\Resources\Json\AnonymousResourceCollection|\Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\Routing\ResponseFactory
    {
        try {
            return ExchangeReasonResource::collection($this->exchangeReasonService->list($request));
        } catch (Exception $exception) {
            return response(['status' => false, 'message' => $exception->getMessage()], 422);
        }
    }

    public function store(ExchangeReasonRequest $request): \Illuminate\Foundation\Application|\Illuminate\Http\Response|ExchangeReasonResource|\Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\Routing\ResponseFactory
    {
        try {
            return new ExchangeReasonResource($this->exchangeReasonService->store($request));
        } catch (Exception $exception) {
            return response(['status' => false, 'message' => $exception->getMessage()], 422);
        }
    }

    public function update(ExchangeReasonRequest $request, ExchangeReason $exchangeReason): \Illuminate\Foundation\Application|\Illuminate\Http\Response|\Illuminate\Contracts\Routing\ResponseFactory|\Illuminate\Contracts\Foundation\Application|ExchangeReasonResource
    {
        try {
            return new ExchangeReasonResource($this->exchangeReasonService->update($request, $exchangeReason));
        } catch (Exception $exception) {
            return response(['status' => false, 'message' => $exception->getMessage()], 422);
        }
    }

    public function destroy(ExchangeReason $exchangeReason): \Illuminate\Foundation\Application|\Illuminate\Http\Response|\Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\Routing\ResponseFactory
    {
        try {
            $this->exchangeReasonService->destroy($exchangeReason);
            return response('', 202);
        } catch (Exception $exception) {
            return response(['status' => false, 'message' => $exception->getMessage()], 422);
        }
    }

    public function show(ExchangeReason $exchangeReason): \Illuminate\Foundation\Application|\Illuminate\Http\Response|\Illuminate\Contracts\Routing\ResponseFactory|\Illuminate\Contracts\Foundation\Application|ExchangeReasonResource
    {
        try {
            return new ExchangeReasonResource($this->exchangeReasonService->show($exchangeReason));
        } catch (Exception $exception) {
            return response(['status' => false, 'message' => $exception->getMessage()], 422);
        }
    }
}
