<?php

namespace App\Http\Resources;

use App\Libraries\AppLibrary;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductExchangeDetailsResource extends JsonResource
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
            'exchange_reason_name'        => $this?->exchangeReason?->title,
            'note'                        => $this->note === null ? '' : $this->note,
            'reject_reason'               => $this->reject_reason === null ? '' : $this->reject_reason,
            'order_id'                    => $this->order_id,
            'order_datetime'              => AppLibrary::datetime($this?->order?->order_datetime),
            'exchange_datetime'           => AppLibrary::datetime($this->created_at),
            'exchange_total_currency_price' => AppLibrary::currencyAmountFormat($this?->exchangeProducts->sum('exchange_price')),
            "exchange_total_price"        => AppLibrary::flatAmountFormat($this?->exchangeProducts->sum('exchange_price')),
            'user_id'                     => $this->user_id,
            'order_serial_no'             => $this->order_serial_no,
            'status'                      => $this->status,
            "images"                      => $this->images,
            'user'                        => new UserResource($this?->user),
            'exchange_products'           => ProductExchangeProductResource::collection($this?->exchangeProducts),
        ];
    }
}
