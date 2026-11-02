<?php

namespace App\Services\SmartBazars;

/** Cash-on-Delivery advance (admin: Program Settings → COD advance %). */
class CodAdvance
{
    public function percent(): float
    {
        if (!ProgramSettings::get('cod_advance_enabled', true)) {
            return 0.0;
        }
        return max(0.0, min(100.0, (float)ProgramSettings::get('cod_advance_percent', 20)));
    }

    public function amount(float $orderTotal): float
    {
        return round(max(0, $orderTotal) * $this->percent() / 100, 2);
    }

    public function summary(float $orderTotal, float $walletBalance): array
    {
        $pct = $this->percent();
        $adv = $this->amount($orderTotal);
        return [
            'cod_advance_enabled' => $pct > 0,
            'cod_advance_percent' => $pct,
            'cod_advance_amount' => $adv,
            'cod_balance_on_delivery' => round(max(0, $orderTotal - $adv), 2),
            'cod_advance_shortfall' => round(max(0, $adv - $walletBalance), 2),
        ];
    }
}
