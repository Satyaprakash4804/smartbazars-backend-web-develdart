<?php

namespace App\Services\SmartBazars;

use App\Enums\Seller\SellerSettlementTypeEnum;
use App\Models\NetworkCommission;
use App\Models\NetworkMember;
use App\Models\OrderItem;
use App\Models\Seller;
use App\Models\WelcomeKitPurchase;
use App\Services\SellerStatementService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Commission distribution.
 *
 * 1) Vendor sale (credited when an item is DELIVERED):
 *      agent who onboarded the vendor ........ agent_direct_percent (default 1%)
 *      that agent's upline agent ............. agent_upline_percent (default 0.5%)
 *    Base = item subtotal (default) or platform commission on the item (setting).
 *
 * 2) Welcome kit (credited when the kit is PAID):
 *      worker whose referral code was used ... ₹30
 *      vendor who employs that worker ........ ₹10 (goes to the seller statement / settlement)
 *      agent who onboarded that vendor ....... ₹5
 *      that agent's upline agent ............. ₹2
 *
 * Every row is unique per (source, source_id, role) so re-running is safe.
 */
class CommissionService
{
    public function __construct(protected SellerStatementService $statements) {}

    // ------------------------------------------------------------------ vendor sales

    public function onOrderItemDelivered(OrderItem $item): void
    {
        $seller = $item->store?->seller;
        if (!$seller || !$seller->agent_member_id) {
            return;
        }
        $agent = NetworkMember::find($seller->agent_member_id);
        if (!$agent || !$agent->isAgent()) {
            return;
        }

        $s = ProgramSettings::all();
        $base = $s['agent_commission_base'] === 'admin_commission'
            ? (float)$item->admin_commission_amount
            : (float)$item->subtotal;
        if ($base <= 0) {
            return;
        }

        $desc = "Sale commission — Order #{$item->order_id}, item #{$item->id}";
        $this->creditMember($agent, 'vendor_sale', $item->id, 'direct_agent', $base, (float)$s['agent_direct_percent'], $desc);

        $upline = $agent->parent;
        if ($upline && $upline->isAgent()) {
            $this->creditMember($upline, 'vendor_sale', $item->id, 'upline_agent', $base, (float)$s['agent_upline_percent'], $desc . ' (downline)');
        }
    }

    public function reverseOrderItem(OrderItem $item, string $reason = 'Item returned/cancelled'): void
    {
        $this->reverse('vendor_sale', $item->id, $reason);
        $this->reverse('repurchase', $item->id, $reason);
    }

    // ------------------------------------------------------------------ repurchase (v1.4)

    /**
     * Every delivered item bought by a referred customer pays the customer's trail:
     *   worker (who added/referred the customer) -> worker's vendor -> vendor's agent -> agent's upline,
     * or, for customers referred by a vendor's QR: vendor -> vendor's agent -> agent's upline.
     * Each role is either a % of the item value or a fixed ₹ amount per order (admin setting).
     */
    public function onRepurchaseItemDelivered(OrderItem $item): void
    {
        $s = ProgramSettings::all();
        if (empty($s['repurchase_enabled'])) {
            return;
        }
        $customer = $item->order?->user;
        if (!$customer) {
            return;
        }
        if (!empty($s['repurchase_requires_welcome_kit']) && empty($customer->welcome_kit_purchased_at)) {
            return;
        }
        $trail = app(ReferralService::class)->trailFor($customer);
        if (!array_filter($trail)) {
            return;
        }
        $base = max(0, (float)$item->subtotal - (float)$item->promo_discount);
        $desc = "Repurchase — Order #{$item->order_id} by " . ($customer->name ?? 'customer');

        $roles = [
            'worker' => $trail['worker'],
            'vendor' => $trail['vendor'],
            'agent' => $trail['agent'],
            'agent_upline' => $trail['upline'],
        ];
        foreach ($roles as $role => $who) {
            if (!$who) continue;
            $type = $s["repurchase_{$role}_type"] ?? 'percent';
            $value = (float)($s["repurchase_{$role}_value"] ?? 0);
            if ($value <= 0) continue;

            if ($type === 'fixed') {
                // fixed ₹ once per order
                $source = 'repurchase_order';
                $sourceId = (int)$item->order_id;
                $amount = $value;
                $rate = null;
            } else {
                $source = 'repurchase';
                $sourceId = (int)$item->id;
                $amount = round($base * $value / 100, 2);
                $rate = $value;
            }
            if ($amount <= 0) continue;

            if ($who instanceof Seller) {
                $this->creditVendorGeneric($who, $source, $sourceId, 'repurchase_vendor', $amount, $base, $rate, $desc);
            } else {
                $this->creditMember($who, $source, $sourceId, 'repurchase_' . $role, $base, $rate, $desc, $amount);
            }
        }
    }

    protected function creditVendorGeneric(Seller $vendor, string $source, int $sourceId, string $role, float $amount, float $base, ?float $rate, string $desc): void
    {
        try {
            DB::transaction(function () use ($vendor, $source, $sourceId, $role, $amount, $base, $rate, $desc) {
                NetworkCommission::create([
                    'seller_id' => $vendor->id,
                    'source' => $source,
                    'source_id' => $sourceId,
                    'role' => $role,
                    'base_amount' => $base,
                    'rate' => $rate,
                    'amount' => $amount,
                    'status' => 'credited',
                    'description' => $desc,
                ]);
                $this->statements->addEntry([
                    'seller_id' => $vendor->id,
                    'entry_type' => SellerSettlementTypeEnum::CREDIT(),
                    'amount' => $amount,
                    'currency_code' => 'INR',
                    'reference_type' => 'repurchase_commission',
                    'reference_id' => $source . ':' . $sourceId,
                    'description' => $desc,
                ]);
            });
        } catch (QueryException $e) {
            if (($e->errorInfo[1] ?? null) != 1062) throw $e;
        }
    }

    // ------------------------------------------------------------------ offline (User Card) orders

    /**
     * Same rules as online orders, paid when the offline order is COMPLETED (counter sale, or BJO order handed over):
     *   1) vendor sale  -> agent who onboarded the vendor + that agent's upline
     *   2) repurchase   -> customer's trail: BJO -> vendor -> BSO -> BSO's upline (percent of item value, or fixed per order)
     * The Welcome Kit line is excluded (it has its own kit commission). Rows are unique per (source, id, role).
     */
    public function onOfflineOrderCompleted(\App\Models\OfflineOrder $o): void
    {
        if ($o->commissions_done) {
            return;
        }
        $o->loadMissing(['items', 'customer']);
        $items = $o->items->where('is_welcome_kit', false);
        $s = ProgramSettings::all();

        // 1) vendor sale commission (agent chain)
        $seller = Seller::find($o->seller_id);
        $agent = $seller?->agent_member_id ? NetworkMember::find($seller->agent_member_id) : null;
        if ($agent && $agent->isAgent()) {
            foreach ($items as $it) {
                $base = ($s['agent_commission_base'] ?? 'item_subtotal') === 'admin_commission'
                    ? (float)$it->admin_commission_amount : (float)$it->subtotal;
                if ($base <= 0) continue;
                $desc = "Sale commission — Offline order {$o->order_no}, item #{$it->id}";
                $this->creditMember($agent, 'offline_vendor_sale', $it->id, 'direct_agent', $base, (float)$s['agent_direct_percent'], $desc);
                $upline = $agent->parent;
                if ($upline && $upline->isAgent()) {
                    $this->creditMember($upline, 'offline_vendor_sale', $it->id, 'upline_agent', $base, (float)$s['agent_upline_percent'], $desc . ' (downline)');
                }
            }
        }

        // 2) repurchase commission (customer's trail)
        $customer = $o->customer;
        $eligible = !empty($s['repurchase_enabled']) && $customer
            && (empty($s['repurchase_requires_welcome_kit']) || !empty($customer->welcome_kit_purchased_at));
        if ($eligible) {
            $trail = app(ReferralService::class)->trailFor($customer);
            if (array_filter($trail)) {
                $roles = ['worker' => $trail['worker'], 'vendor' => $trail['vendor'], 'agent' => $trail['agent'], 'agent_upline' => $trail['upline']];
                $desc = "Repurchase — Offline order {$o->order_no} by " . ($customer->name ?? 'customer');
                foreach ($roles as $role => $who) {
                    if (!$who) continue;
                    $type = $s["repurchase_{$role}_type"] ?? 'percent';
                    $value = (float)($s["repurchase_{$role}_value"] ?? 0);
                    if ($value <= 0) continue;

                    if ($type === 'fixed') {
                        if ($items->isEmpty()) continue;
                        $this->creditRepurchase($who, 'offline_repurchase_order', (int)$o->id, $role, (float)$items->sum('subtotal'), null, $value, $desc);
                    } else {
                        foreach ($items as $it) {
                            $base = max(0, (float)$it->subtotal);
                            $amount = round($base * $value / 100, 2);
                            if ($amount <= 0) continue;
                            $this->creditRepurchase($who, 'offline_repurchase', (int)$it->id, $role, $base, $value, $amount, $desc);
                        }
                    }
                }
            }
        }
        $o->forceFill(['commissions_done' => true])->saveQuietly();
    }

    protected function creditRepurchase($who, string $source, int $sourceId, string $role, float $base, ?float $rate, float $amount, string $desc): void
    {
        if ($who instanceof Seller) {
            $this->creditVendorGeneric($who, $source, $sourceId, 'repurchase_vendor', $amount, $base, $rate, $desc);
        } else {
            $this->creditMember($who, $source, $sourceId, 'repurchase_' . $role, $base, $rate, $desc, $amount);
        }
    }

    public function reverseOfflineOrder(\App\Models\OfflineOrder $o, string $reason = 'Offline order cancelled'): void
    {
        $o->loadMissing('items');
        foreach ($o->items as $it) {
            $this->reverse('offline_vendor_sale', $it->id, $reason);
            $this->reverse('offline_repurchase', $it->id, $reason);
        }
        $this->reverse('offline_repurchase_order', $o->id, $reason);
        $o->forceFill(['commissions_done' => false])->saveQuietly();
    }

    // ------------------------------------------------------------------ welcome kit

    public function onWelcomeKitPurchased(WelcomeKitPurchase $purchase): void
    {
        if ($purchase->commission_distributed) {
            return;
        }
        $s = ProgramSettings::all();
        $worker = $purchase->worker;

        if ($worker && $worker->isWorker() && $worker->isActive()) {
            $desc = "Welcome kit #{$purchase->id} — customer " . ($purchase->user->name ?? '');
            $this->creditMember($worker, 'welcome_kit', $purchase->id, 'worker', 0, null, $desc, (float)$s['kit_worker_amount']);

            $vendor = $worker->seller;
            if ($vendor) {
                $this->creditVendor($vendor, $purchase, (float)$s['kit_vendor_amount']);

                $agent = $vendor->agent_member_id ? NetworkMember::find($vendor->agent_member_id) : null;
                if ($agent && $agent->isAgent()) {
                    $this->creditMember($agent, 'welcome_kit', $purchase->id, 'vendor_agent', 0, null, $desc, (float)$s['kit_agent_amount']);
                    $upline = $agent->parent;
                    if ($upline && $upline->isAgent()) {
                        $this->creditMember($upline, 'welcome_kit', $purchase->id, 'vendor_agent_upline', 0, null, $desc . ' (downline)', (float)$s['kit_agent_upline_amount']);
                    }
                }
            }
        } elseif ($purchase->user && $purchase->user->referred_by_seller_id) {
            // SmartBazars v1.4: customer joined through a vendor's QR/referral code (no worker in the trail)
            $vendor = Seller::find($purchase->user->referred_by_seller_id);
            if ($vendor) {
                $desc = "Welcome kit #{$purchase->id} — customer " . ($purchase->user->name ?? '') . ' (your referral)';
                $this->creditVendor($vendor, $purchase, (float)$s['kit_vendor_amount']);
                $agent = $vendor->agent_member_id ? NetworkMember::find($vendor->agent_member_id) : null;
                if ($agent && $agent->isAgent()) {
                    $this->creditMember($agent, 'welcome_kit', $purchase->id, 'vendor_agent', 0, null, $desc, (float)$s['kit_agent_amount']);
                    $upline = $agent->parent;
                    if ($upline && $upline->isAgent()) {
                        $this->creditMember($upline, 'welcome_kit', $purchase->id, 'vendor_agent_upline', 0, null, $desc . ' (downline)', (float)$s['kit_agent_upline_amount']);
                    }
                }
            }
        }

        $purchase->update(['commission_distributed' => true]);
    }

    public function reverseWelcomeKit(WelcomeKitPurchase $purchase, string $reason = 'Welcome kit cancelled'): void
    {
        $this->reverse('welcome_kit', $purchase->id, $reason);
    }

    // ------------------------------------------------------------------ core

    protected function creditMember(NetworkMember $member, string $source, int $sourceId, string $role,
                                    float $base, ?float $rate, string $desc, ?float $fixed = null): void
    {
        $amount = $fixed ?? round($base * ($rate ?? 0) / 100, 2);
        if ($amount <= 0 || !$member->isActive()) {
            return;
        }
        try {
            DB::transaction(function () use ($member, $source, $sourceId, $role, $base, $rate, $amount, $desc) {
                NetworkCommission::create([
                    'network_member_id' => $member->id,
                    'source' => $source,
                    'source_id' => $sourceId,
                    'role' => $role,
                    'base_amount' => $base,
                    'rate' => $rate,
                    'amount' => $amount,
                    'status' => 'credited',
                    'description' => $desc,
                ]);
                NetworkMember::whereKey($member->id)->update([
                    'wallet_balance' => DB::raw('wallet_balance + ' . $amount),
                    'total_earned' => DB::raw('total_earned + ' . $amount),
                ]);
            });
        } catch (QueryException $e) {
            // duplicate (source, source_id, role) => already paid, ignore
            if (!str_contains($e->getMessage(), 'nc_unique_source_role') && ($e->errorInfo[1] ?? null) != 1062) {
                throw $e;
            }
        }
    }

    protected function creditVendor(Seller $vendor, WelcomeKitPurchase $purchase, float $amount): void
    {
        if ($amount <= 0) return;
        try {
            DB::transaction(function () use ($vendor, $purchase, $amount) {
                NetworkCommission::create([
                    'seller_id' => $vendor->id,
                    'source' => 'welcome_kit',
                    'source_id' => $purchase->id,
                    'role' => 'vendor',
                    'amount' => $amount,
                    'status' => 'credited',
                    'description' => "Welcome kit #{$purchase->id} sold by your worker",
                ]);
                $this->statements->addEntry([
                    'seller_id' => $vendor->id,
                    'entry_type' => SellerSettlementTypeEnum::CREDIT(),
                    'amount' => $amount,
                    'currency_code' => 'INR',
                    'reference_type' => 'welcome_kit_commission',
                    'reference_id' => (string)$purchase->id,
                    'description' => "Welcome kit commission (purchase #{$purchase->id})",
                    'meta' => ['welcome_kit_purchase_id' => $purchase->id],
                ]);
            });
        } catch (QueryException $e) {
            if (($e->errorInfo[1] ?? null) != 1062) throw $e;
        }
    }

    protected function reverse(string $source, int $sourceId, string $reason): void
    {
        $rows = NetworkCommission::where('source', $source)->where('source_id', $sourceId)
            ->where('status', 'credited')->get();
        foreach ($rows as $row) {
            DB::transaction(function () use ($row, $reason) {
                $row->update(['status' => 'reversed', 'description' => trim($row->description . ' | Reversed: ' . $reason)]);
                if ($row->network_member_id) {
                    NetworkMember::whereKey($row->network_member_id)->update([
                        'wallet_balance' => DB::raw('wallet_balance - ' . (float)$row->amount),
                        'total_earned' => DB::raw('total_earned - ' . (float)$row->amount),
                    ]);
                } elseif ($row->seller_id) {
                    $this->statements->addEntry([
                        'seller_id' => $row->seller_id,
                        'entry_type' => SellerSettlementTypeEnum::DEBIT(),
                        'amount' => $row->amount,
                        'currency_code' => 'INR',
                        'reference_type' => 'commission_reversal',
                        'reference_id' => (string)$row->source_id,
                        'description' => 'Welcome kit commission reversed: ' . $reason,
                    ]);
                }
            });
        }
        if ($rows->isNotEmpty()) {
            Log::info("SmartBazars commissions reversed", compact('source', 'sourceId', 'reason'));
        }
    }
}
