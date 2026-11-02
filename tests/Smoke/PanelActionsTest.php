<?php
namespace Tests\Smoke;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class PanelActionsTest extends TestCase
{
    protected function post_(string $who, string $u, array $d = []): void
    {
        $this->app['auth']->forgetGuards();
        $r = $this->actingAs(User::where('email', $who)->first())->withSession(['_token' => 't'])->post($u, $d + ['_token' => 't']);
        $flash = session('success') ?? session('error') ?? (session('errors') ? json_encode(session('errors')->all()) : '');
        $e = $r->exception && $r->status() >= 500 ? ' EXC ' . Str::limit($r->exception->getMessage(), 200) . ' @' . basename($r->exception->getFile()) . ':' . $r->exception->getLine() : '';
        echo "\n[" . $r->status() . "] " . explode('@', $who)[0] . " POST $u :: " . Str::limit((string) $flash, 160) . $e;
    }
    protected function show(string $l, $v): void { echo "\n  -- $l: " . json_encode($v); }

    public function test_actions(): void
    {
        $this->withoutMiddleware([\App\Http\Middleware\VerifyLicense::class, \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class]);
        $A = 'admin@smartbazars.in'; $AG = 'agent.bihar@smartbazars.in'; $W = 'worker.patna@smartbazars.in'; $V = 'vendor.patna@smartbazars.in';
        $patnaSeller = DB::table('sellers')->where('user_id', User::where('email', $V)->value('id'))->value('id');

        echo "\n===== Admin: agents, tree, settings";
        $this->post_($A, '/admin/smartbazars/network/agents', ['name' => 'Test Agent', 'email' => 'test.agent@smartbazars.in', 'mobile' => '9111111111', 'parent_id' => 1, 'password' => 'Agent@123']);
        $new = DB::table('network_members')->latest('id')->first();
        $this->show('new agent', ['id' => $new->id, 'level' => $new->level, 'path' => $new->path, 'code' => $new->referral_code]);
        $this->post_($A, "/admin/smartbazars/network/member/{$new->id}/toggle");
        $this->show('status', DB::table('network_members')->where('id', $new->id)->value('status'));
        $this->post_($A, "/admin/smartbazars/network/member/{$new->id}/toggle");
        $this->post_($A, '/admin/smartbazars/settings', ['agent_direct_percent' => 1, 'agent_upline_percent' => 0.5, 'agent_commission_base' => 'item_subtotal', 'kit_worker_amount' => 30, 'kit_vendor_amount' => 10, 'kit_agent_amount' => 5, 'kit_agent_upline_amount' => 2, 'coin_value' => 1, 'max_coin_redeem_percent' => 100, 'card_prefix' => 'SB', 'card_validity_years' => 1, 'min_withdrawal_amount' => 10]);
        $this->post_($A, '/admin/smartbazars/coins/adjust', ['user' => 'customer.gaya@smartbazars.in', 'coins' => 20, 'reason' => 'Goodwill']);
        $this->show('gaya coins', DB::table('users')->where('email', 'customer.gaya@smartbazars.in')->value('coin_balance'));
        $pid = DB::table('products')->value('id');
        $this->post_($A, '/admin/smartbazars/products/coins', ['coins' => [$pid => 12]]);
        $this->post_($A, '/admin/smartbazars/products/coins/bulk', ['scope' => 'seller', 'seller_id' => $patnaSeller, 'coins' => 5]);
        $this->show('product coins', [DB::table('products')->where('id', $pid)->value('cashback_coins'), DB::table('products')->where('seller_id', $patnaSeller)->distinct()->pluck('cashback_coins')]);
        $this->post_($A, "/admin/smartbazars/stores/{$patnaSeller}/house-toggle");
        $this->post_($A, "/admin/smartbazars/stores/{$patnaSeller}/house-toggle");

        echo "\n===== Partner (agent) requests -> admin approval";
        $this->post_($AG, '/partner/requests', ['type' => 'agent', 'name' => 'Sub Agent Two', 'email' => 'sub2@smartbazars.in', 'mobile' => '9222222222']);
        $this->post_($AG, '/partner/requests', ['type' => 'vendor', 'name' => 'Gaya Vendor', 'email' => 'gaya.vendor@smartbazars.in', 'mobile' => '9333333333', 'meta' => ['store_name' => 'Gaya Kirana', 'address' => 'Station Rd', 'city' => 'Gaya', 'state' => 'Bihar', 'zipcode' => '823001']]);
        $this->post_($AG, '/partner/requests', ['type' => 'worker', 'name' => 'Worker Two', 'email' => 'worker2@smartbazars.in', 'mobile' => '9444444444', 'seller_id' => $patnaSeller]);
        $this->show('requests', DB::table('network_registration_requests')->get(['id', 'type', 'status'])->toArray());
        foreach (DB::table('network_registration_requests')->where('status', 'pending_admin')->pluck('id') as $rid) {
            $this->post_($A, "/admin/smartbazars/network/requests/$rid/approve");
        }
        $wr = DB::table('network_registration_requests')->where('type', 'worker')->value('id');
        $this->post_($V, "/seller/smartbazars/workers/requests/$wr", ['decision' => 'approve']);
        $this->post_($A, "/admin/smartbazars/network/requests/$wr/approve");
        $this->show('requests after', DB::table('network_registration_requests')->get(['id', 'type', 'status', 'created_user_id'])->toArray());
        $this->show('new users', DB::table('users')->whereIn('email', ['sub2@smartbazars.in', 'gaya.vendor@smartbazars.in', 'worker2@smartbazars.in'])->get(['email', 'access_panel'])->toArray());
        $this->show('gaya seller/store', [DB::table('sellers')->latest('id')->first(['id', 'agent_member_id']), DB::table('stores')->latest('id')->value('name')]);

        echo "\n===== Vendor adds worker directly; reject flow";
        $this->post_($V, '/seller/smartbazars/workers', ['name' => 'Worker Three', 'email' => 'worker3@smartbazars.in', 'mobile' => '9555555555']);
        $last = DB::table('network_registration_requests')->latest('id')->first();
        $this->show('vendor worker req', [$last->type, $last->status]);
        $this->post_($A, "/admin/smartbazars/network/requests/{$last->id}/reject", ['reason' => 'Docs missing']);
        $this->show('after reject', DB::table('network_registration_requests')->where('id', $last->id)->value('status'));

        echo "\n===== Worker onboards customer; earnings withdrawal";
        $this->post_($W, '/partner/customers', ['name' => 'New Customer', 'mobile' => '9666666666', 'email' => 'newcust@smartbazars.in']);
        $this->show('customer', DB::table('users')->where('mobile', '9666666666')->first(['id', 'access_panel', 'referred_by_worker_id']));
        // give the worker some balance from the earlier kit sale
        DB::table('network_members')->where('user_id', User::where('email', $W)->value('id'))->update(['wallet_balance' => 30]);
        $this->post_($W, '/partner/earnings/withdraw', ['amount' => 500, 'method' => 'upi', 'upi_id' => 'rohit@upi']);
        $this->post_($W, '/partner/earnings/withdraw', ['amount' => 25, 'method' => 'upi', 'upi_id' => 'rohit@upi']);
        $wd = DB::table('network_withdrawal_requests')->latest('id')->first();
        $this->show('withdrawal', [$wd?->amount, $wd?->status, DB::table('network_members')->where('id', $wd?->network_member_id)->value('wallet_balance')]);
        if ($wd) $this->post_($A, "/admin/smartbazars/network/withdrawals/{$wd->id}", ['action' => 'paid', 'transaction_reference' => 'UTR123']);
        $this->show('withdrawal after', DB::table('network_withdrawal_requests')->where('id', $wd?->id)->value('status'));

        echo "\n===== Welcome kit admin";
        $this->post_($A, '/admin/smartbazars/welcome-kits', ['name' => 'Festive Kit', 'price' => 299, 'description' => 'Diwali']);
        $k = DB::table('welcome_kits')->latest('id')->first();
        $this->post_($A, "/admin/smartbazars/welcome-kits/{$k->id}/items", ['product_id' => $pid, 'quantity' => 2]);
        $this->post_($A, "/admin/smartbazars/welcome-kits/{$k->id}/items", ['item_name' => 'SmartBazars Tote Bag', 'quantity' => 1]);
        $this->post_($A, "/admin/smartbazars/welcome-kits/{$k->id}", ['name' => 'Festive Kit 2026', 'price' => 299]);
        $this->post_($A, "/admin/smartbazars/welcome-kits/{$k->id}/activate");
        $this->show('active kits', DB::table('welcome_kits')->where('is_active', 1)->pluck('name'));
        $item = DB::table('welcome_kit_items')->where('welcome_kit_id', $k->id)->value('id');
        $this->post_($A, "/admin/smartbazars/welcome-kits/{$k->id}/items/$item/delete");
        $first = DB::table('welcome_kits')->orderBy('id')->value('id');
        $this->post_($A, "/admin/smartbazars/welcome-kits/$first/activate");
        $this->post_($A, "/admin/smartbazars/welcome-kits/{$k->id}/delete");
        $p = DB::table('welcome_kit_purchases')->value('id');
        if ($p) { $this->post_($A, "/admin/smartbazars/welcome-kit-orders/$p", ['status' => 'dispatched']); }
        $c = DB::table('user_cards')->value('id');
        if ($c) { $this->post_($A, "/admin/smartbazars/user-cards/$c/toggle"); $this->show('card', DB::table('user_cards')->where('id', $c)->value('status')); }

        echo "\n===== Partner login + password";
        $this->app['auth']->forgetGuards();
        auth()->logout();
        $r = $this->post('/partner/login', ['email' => 'agent.bihar@smartbazars.in', 'password' => 'Agent@123']);
        echo "\n[" . $r->status() . "] partner login -> " . $r->headers->get('Location');
        $r = $this->post('/partner/login', ['email' => 'agent.bihar@smartbazars.in', 'password' => 'wrong']);
        echo "\n[" . $r->status() . "] partner bad login -> " . $r->headers->get('Location');
        $this->assertTrue(true);
    }
}
