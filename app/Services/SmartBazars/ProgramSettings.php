<?php

namespace App\Services\SmartBazars;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

/**
 * Admin-editable settings for the SmartBazars program
 * (stored in the existing `settings` table under variable "smartbazars_program").
 */
class ProgramSettings
{
    public const VARIABLE = 'smartbazars_program';

    public const DEFAULTS = [
        // BSO commission on every delivered vendor sale
        'agent_direct_percent' => 1.0,       // agent who onboarded the vendor
        'agent_upline_percent' => 0.5,       // that agent's upline agent
        'agent_commission_base' => 'item_subtotal', // item_subtotal | admin_commission

        // Welcome kit commission (fixed ₹)
        'kit_worker_amount' => 30,
        'kit_vendor_amount' => 10,
        'kit_agent_amount' => 5,
        'kit_agent_upline_amount' => 2,
        'kit_requires_referral_code' => false,

        // Cashback coins
        'coins_enabled' => true,
        'coin_value' => 1.0,                 // 1 coin = ₹1
        'max_coin_redeem_percent' => 100,    // max % of order payable that coins can cover

        // User card
        'card_prefix' => 'SB',
        'card_validity_years' => 5,
        'card_requires_welcome_kit' => true,

        // Partner payouts
        'min_withdrawal_amount' => 100,

        // COD advance: customer pays this % online (from the SmartBazars wallet, topped up via Razorpay etc.)
        'cod_advance_enabled' => true,
        'cod_advance_percent' => 20.0,

        // Repurchase commission: every delivered order of a referred customer pays the customer's trail
        // (worker -> vendor -> vendor's agent -> that agent's upline). type: percent (of item value) | fixed (₹ per order)
        'repurchase_enabled' => true,
        'repurchase_requires_welcome_kit' => true,
        'repurchase_worker_type' => 'percent',
        'repurchase_worker_value' => 1.0,
        'repurchase_vendor_type' => 'percent',
        'repurchase_vendor_value' => 0.5,
        'repurchase_agent_type' => 'percent',
        'repurchase_agent_value' => 0.25,
        'repurchase_agent_upline_type' => 'percent',
        'repurchase_agent_upline_value' => 0.1,

        // Joining fee (Razorpay). Master switch OFF => nobody pays anything. Fees are NOT split as commission.
        'joining_fee_enabled' => true,
        'joining_fee_agent' => 2565.0,
        'joining_fee_worker' => 2565.0,
        'joining_fee_vendor' => 2565.0,
        'joining_fee_reuse_on_reapply' => true, // fee paid on a rejected application is reused when they re-apply
        'vendor_apply_url' => 'https://smartbazars.in/seller-register/',

        // Mobile app downloads served from /download/customer and /download/vendor (APK upload or external URL)
        'app_customer_apk' => '', 'app_customer_version' => '', 'app_customer_url' => '',
        'app_vendor_apk' => '', 'app_vendor_version' => '', 'app_vendor_url' => '',
        'app_delivery_apk' => '', 'app_delivery_version' => '', 'app_delivery_url' => '',

        // Public base URL used in vendor QR codes / letters (e.g. https://smartbazars.in)
        'public_site_url' => '',
    ];

    public static function all(): array
    {
        return Cache::remember('sb_program_settings', 300, function () {
            $row = Setting::find(self::VARIABLE);
            $value = $row?->value ?? [];
            return array_merge(self::DEFAULTS, is_array($value) ? $value : []);
        });
    }

    public static function get(string $key, $default = null)
    {
        return self::all()[$key] ?? $default ?? (self::DEFAULTS[$key] ?? null);
    }

    public static function save(array $values): void
    {
        $clean = [];
        foreach (self::DEFAULTS as $k => $def) {
            if (!array_key_exists($k, $values)) {
                $clean[$k] = self::get($k);
                continue;
            }
            $v = $values[$k];
            $clean[$k] = match (true) {
                is_bool($def) => filter_var($v, FILTER_VALIDATE_BOOLEAN),
                is_int($def) => (int)$v,
                is_float($def) => (float)$v,
                default => (string)$v,
            };
        }
        Setting::updateOrCreate(['variable' => self::VARIABLE], ['value' => json_encode($clean)]);
        Cache::forget('sb_program_settings');
    }
}
