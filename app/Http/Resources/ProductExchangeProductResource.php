<?php

namespace App\Http\Resources;

use App\Libraries\AppLibrary;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductExchangeProductResource extends JsonResource
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
            'id'                       => $this->id,
            'product_id'               => $this->product_id,
            'product_name'             => $this?->product?->name,
            'product_image'            => $this?->product?->thumb,
            'variation_names'          => $this->variation_names,
            'category_name'            => $this?->product?->category?->name,
            'quantity'                 => $this->quantity,
            'price'                    => $this->price,
            'currency_price'           => AppLibrary::currencyAmountFormat($this->price),
            'total_currency_price'     => AppLibrary::currencyAmountFormat($this->total),
            'exchange_currency_price'  => AppLibrary::currencyAmountFormat($this->exchange_price),
            'exchange_product_id'      => $this->exchange_product_id,
            'exchange_product_name'    => $this?->exchangeProduct?->name,
            'exchange_product_image'   => $this?->exchangeProduct?->thumb,
            'exchange_product_quantity' => $this->exchange_product_quantity,
            'exchange_product_currency_price' => AppLibrary::currencyAmountFormat($this->exchange_product_price),
            'exchange_product_total_currency_price' => AppLibrary::currencyAmountFormat($this->exchange_product_total),
        ];
    }
}
