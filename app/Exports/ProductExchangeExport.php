<?php

namespace App\Exports;

use App\Libraries\AppLibrary;
use App\Http\Requests\PaginateRequest;
use App\Services\ProductExchangeService;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\FromCollection;

class ProductExchangeExport implements FromCollection, WithHeadings
{

    public ProductExchangeService $productExchangeService;
    public PaginateRequest $request;

    public function __construct(ProductExchangeService $productExchangeService, $request)
    {
        $this->productExchangeService = $productExchangeService;
        $this->request                = $request;
    }

    public function collection()
    {
        $exchangeArray  = [];
        $exchangesArray = $this->productExchangeService->list($this->request);

        foreach ($exchangesArray as $exchange) {
            $exchangeArray[] = [
                $exchange->order_serial_no,
                optional($exchange->user)->name,
                AppLibrary::flatAmountFormat($exchange?->exchangeProducts->sum('exchange_price')),
                AppLibrary::datetime($exchange->created_at),
                trans('exchangeOrderStatus.' . $exchange->status),
            ];
        }
        return collect($exchangeArray);
    }

    public function headings(): array
    {
        return [
            trans('all.label.order_serial_no'),
            trans('all.label.customer'),
            trans('all.label.total_exchange_price'),
            trans('all.label.date'),
            trans('all.label.status'),
        ];
    }
}
