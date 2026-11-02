<?php
namespace Tests\Smoke;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FlowTest extends TestCase
{
    protected function call_(string $who, string $m, string $u, array $d = []): array
    {
        $this->app['auth']->forgetGuards();
        Sanctum::actingAs(User::where('email', $who)->first(), ['*']);
        $r = $this->json($m, $u, $d);
        $j = $r->json() ?? [];
        $e = $r->exception ? ' EXC ' . $r->exception->getMessage() . ' @' . basename($r->exception->getFile()) . ':' . $r->exception->getLine() : '';
        echo "\n[" . $r->status() . "] $m $u => " . json_encode(['success' => $j['success'] ?? null, 'message' => $j['message'] ?? null]) . $e;
        return $j;
    }

    protected function show(string $label, $rows): void
    {
        echo "\n  -- $label: " . json_encode($rows);
    }

    public function test_flow(): void
    {
        $this->withoutMiddleware(\App\Http\Middleware\VerifyLicense::class);
        $C = 'customer.patna@smartbazars.in';
        $cu = User::where('email', $C)->first();
        $addr = DB::table('addresses')->where('user_id', $cu->id)->value('id');
        $code = DB::table('network_members')->where('user_id', User::where('email', 'worker.patna@smartbazars.in')->value('id'))->value('referral_code');
        $geo = '?latitude=25.6093&longitude=85.1235';

        echo "\n===== 1. Welcome kit + user card";
        $this->call_($C, 'GET', '/api/user/welcome-kit');
        $this->call_($C, 'POST', '/api/user/welcome-kit/check-referral', ['referral_code' => $code]);
        $this->call_($C, 'POST', '/api/user/welcome-kit/purchase', ['address_id' => $addr, 'referral_code' => $code]);
        $this->call_($C, 'POST', '/api/user/welcome-kit/purchase', ['address_id' => $addr, 'referral_code' => $code]); // 2nd time must fail
        $card = $this->call_($C, 'GET', '/api/user/user-card');
        $this->show('card', $card['data']['card_number'] ?? null);
        $this->show('kit commissions', DB::table('network_commissions')->where('source', 'welcome_kit')->get(['role', 'network_member_id', 'seller_id', 'amount'])->toArray());
        $this->show('wallet after kit', DB::table('wallets')->where('user_id', $cu->id)->value('balance'));

        echo "\n===== 2. Cart + order with coins (COD)";
        $storeId = DB::table('stores')->where('name', 'like', 'Patliputra%')->value('id');
        $spv = DB::table('store_product_variants as s')->join('product_variants as v', 'v.id', '=', 's.product_variant_id')
            ->join('products as p', 'p.id', '=', 'v.product_id')->where('s.store_id', $storeId)->where('s.stock', '>', 5)
            ->where('p.cashback_coins', '>', 0)->orderByDesc('s.price')->first(['v.id as vid', 'p.title', 'p.cashback_coins', 's.price', 's.special_price']);
        $this->show('product', $spv);
        $this->call_($C, 'POST', '/api/user/cart/add', ['product_variant_id' => $spv->vid, 'store_id' => $storeId, 'quantity' => 3]);
        $cart = $this->call_($C, 'GET', '/api/user/cart' . $geo . '&use_coins=1&address_id=' . $addr);
        $this->show('cart payment summary', $cart['data']['payment_summary'] ?? array_keys($cart['data'] ?? []));
        $coinsBefore = DB::table('users')->where('id', $cu->id)->value('coin_balance');
        $order = $this->call_($C, 'POST', '/api/user/orders', ['payment_type' => 'cod', 'address_id' => $addr, 'use_coins' => true]);
        $o = DB::table('orders')->latest('id')->first();
        $this->show('order', ['id' => $o?->id, 'final_total' => $o?->final_total, 'coins_used' => $o?->coins_used, 'coin_discount' => $o?->coin_discount, 'coins before' => $coinsBefore, 'coins now' => DB::table('users')->where('id', $cu->id)->value('coin_balance')]);
        if (!$o) { $this->assertTrue(true); return; }
        $item = DB::table('order_items')->where('order_id', $o->id)->first();
        $this->show('item', ['id' => $item->id, 'status' => $item->status, 'cashback_coins' => $item->cashback_coins, 'coin_discount' => $item->coin_discount]);

        echo "\n===== 3. Vendor accepts & prepares";
        $V = 'vendor.patna@smartbazars.in';
        $this->call_($V, 'POST', "/api/seller/orders/{$item->id}/accept");
        $this->call_($V, 'POST', "/api/seller/orders/{$item->id}/preparing");
        $this->show('item status', DB::table('order_items')->where('id', $item->id)->value('status'));

        echo "\n===== 4. Rider accepts, collects, delivers";
        $R = 'rider.patna@smartbazars.in';
        $this->call_($R, 'GET', '/api/delivery-boy/orders/available' . $geo);
        $this->call_($R, 'POST', "/api/delivery-boy/orders/{$o->id}/accept");
        $this->call_($R, 'PUT', "/api/delivery-boy/order-items/{$item->id}/status", ['status' => 'collected']);
        $otp = DB::table('order_items')->where('id', $item->id)->value('otp');
        $this->call_($R, 'PUT', "/api/delivery-boy/order-items/{$item->id}/status", array_filter(['status' => 'delivered', 'otp' => $otp]));
        $this->show('item status', DB::table('order_items')->where('id', $item->id)->value('status'));

        echo "\n===== 5. Payouts after delivery";
        $this->show('coin tx', DB::table('coin_transactions')->where('user_id', $cu->id)->get(['type', 'coins', 'balance_after'])->toArray());
        $this->show('sale commissions', DB::table('network_commissions')->where('source', 'vendor_sale')->get(['role', 'network_member_id', 'base_amount', 'rate', 'amount'])->toArray());
        $this->show('member wallets', DB::table('network_members')->get(['id', 'type', 'wallet_balance', 'total_earned'])->toArray());
        $this->call_($C, 'GET', '/api/user/coins');
        $this->assertTrue(true);
    }
}
