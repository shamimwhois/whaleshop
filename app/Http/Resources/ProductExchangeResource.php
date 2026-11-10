<?php

namespace App\Http\Resources;

use App\Libraries\AppLibrary;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductExchangeResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param \Illuminate\Http\Request $request
     * @return array
     */
    public function toArray($request): array
    {
        return [
            'id'                          => $this->id,
            'exchange_reason_id'          => $this->exchange_reason_id,
            'note'                        => $this->note === null ? '' : $this->note,
            'order_id'                    => $this->order_id,
            'order_datetime'              => AppLibrary::datetime($this?->order?->order_datetime),
            'exchange_datetime'           => AppLibrary::datetime($this->created_at),
            'exchange_items'              => $this?->exchangeProducts->count(),
            'exchange_total_currency_price' => AppLibrary::currencyAmountFormat($this?->exchangeProducts->sum('exchange_price')),
            "exchange_total_price"        => AppLibrary::flatAmountFormat($this?->exchangeProducts->sum('exchange_price')),
            'user_id'                     => $this->user_id,
            'user_name'                   => $this?->user?->name,
            'order_serial_no'             => $this->order_serial_no,
            'status'                      => $this->status,
        ];
    }
}
