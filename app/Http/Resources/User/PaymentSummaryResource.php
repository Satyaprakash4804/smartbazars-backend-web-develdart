<?php

namespace App\Http\Resources\User;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentSummaryResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'items_total' => $this['items_total'] ?? 0,
            'per_store_drop_off_fee' => $this['per_store_drop_off_fee'] ?? 0,
            'is_rush_delivery' => $this['is_rush_delivery'] ?? false,
            'is_rush_delivery_available' => $this['is_rush_delivery_available'] ?? false,
            'delivery_charges' => $this['delivery_charges'] ?? 0,
            'handling_charges' => $this['handling_charges'] ?? 0,
            'delivery_distance_charges' => $this['delivery_distance_charges'] ?? 0,
            'delivery_distance_km' => $this['delivery_distance_km'] ?? 0,
            'total_stores' => $this['total_stores'] ?? 0,
            'total_delivery_charges' => $this['total_delivery_charges'] ?? 0,
            'estimated_delivery_time' => $this['estimated_delivery_time'] ?? 0,
            'use_wallet' => $this['use_wallet'] ?? false,
            'promo_code' => $this['promo_code'] ?? '',
            'promo_discount' => $this['promo_discount'] ?? 0,
            'promo_applied' => $this['promo_applied'] ?? [],
            'promo_error' => $this['promo_error'] ?? null,
            'wallet_balance' => round($this['wallet_balance'] ?? 0, 2),
            'wallet_amount_used' => round($this['wallet_amount_used'] ?? 0, 2),
            'payable_amount' => round($this['payable_amount'] ?? 0, 2),
            // SmartBazars cashback coins
            'use_coins' => $this['use_coins'] ?? false,
            'coin_balance' => (int)($this['coin_balance'] ?? 0),
            'max_coins_usable' => (int)($this['max_coins_usable'] ?? 0),
            'coins_used' => (int)($this['coins_used'] ?? 0),
            'coin_discount' => round($this['coin_discount'] ?? 0, 2),
            'cod_advance_enabled' => (bool)($this['cod_advance_enabled'] ?? false),
            'cod_advance_percent' => (float)($this['cod_advance_percent'] ?? 0),
            'cod_advance_amount' => round($this['cod_advance_amount'] ?? 0, 2),
            'cod_balance_on_delivery' => round($this['cod_balance_on_delivery'] ?? 0, 2),
            'cod_advance_shortfall' => round($this['cod_advance_shortfall'] ?? 0, 2),
        ];
    }
}
