<?php

namespace App\Http\Controllers\Admin\SmartBazars;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\CoinTransaction;
use App\Models\Product;
use App\Models\Seller;
use App\Models\User;
use App\Services\SmartBazars\CoinService;
use App\Services\SmartBazars\ProgramSettings;
use Illuminate\Http\Request;

/** Admin: program settings, product cashback coins (own + vendor products), coin adjustments. */
class ProgramController extends Controller
{
    public function settings()
    {
        $s = ProgramSettings::all();
        return view('admin.smartbazars.settings', compact('s'));
    }

    public function saveSettings(Request $request)
    {
        $request->validate([
            'agent_direct_percent' => 'required|numeric|min:0|max:50',
            'agent_upline_percent' => 'required|numeric|min:0|max:50',
            'agent_commission_base' => 'required|in:item_subtotal,admin_commission',
            'kit_worker_amount' => 'required|integer|min:0',
            'kit_vendor_amount' => 'required|integer|min:0',
            'kit_agent_amount' => 'required|integer|min:0',
            'kit_agent_upline_amount' => 'required|integer|min:0',
            'coin_value' => 'required|numeric|min:0.01|max:100',
            'max_coin_redeem_percent' => 'required|integer|min:0|max:100',
            'card_prefix' => 'required|alpha|max:4',
            'card_validity_years' => 'required|integer|min:1|max:20',
            'min_withdrawal_amount' => 'required|integer|min:0',
            'cod_advance_percent' => 'required|numeric|min:0|max:100',
            'repurchase_worker_type' => 'required|in:percent,fixed',
            'repurchase_worker_value' => 'required|numeric|min:0|max:100000',
            'repurchase_vendor_type' => 'required|in:percent,fixed',
            'repurchase_vendor_value' => 'required|numeric|min:0|max:100000',
            'repurchase_agent_type' => 'required|in:percent,fixed',
            'repurchase_agent_value' => 'required|numeric|min:0|max:100000',
            'repurchase_agent_upline_type' => 'required|in:percent,fixed',
            'repurchase_agent_upline_value' => 'required|numeric|min:0|max:100000',
            'public_site_url' => 'nullable|url|max:200',
            'joining_fee_agent' => 'required|numeric|min:0|max:1000000',
            'joining_fee_worker' => 'required|numeric|min:0|max:1000000',
            'joining_fee_vendor' => 'required|numeric|min:0|max:1000000',
            'vendor_apply_url' => 'nullable|url|max:200',
        ]);
        $values = $request->all();
        foreach (['coins_enabled', 'kit_requires_referral_code', 'card_requires_welcome_kit', 'cod_advance_enabled', 'repurchase_enabled', 'repurchase_requires_welcome_kit', 'joining_fee_enabled', 'joining_fee_reuse_on_reapply'] as $b) {
            $values[$b] = $request->boolean($b);
        }
        ProgramSettings::save($values);
        return back()->with('success', 'SmartBazars program settings saved.');
    }

    /** All products — own (house store) and vendors' — with editable cashback coins. */
    public function products(Request $request)
    {
        $houseSellerIds = Seller::where('is_house_store', true)->pluck('id');
        $products = Product::query()
            ->with(['seller.user:id,name'])
            ->when($request->owner === 'own', fn($q) => $q->whereIn('seller_id', $houseSellerIds))
            ->when($request->owner === 'vendor', fn($q) => $q->whereNotIn('seller_id', $houseSellerIds))
            ->when($request->seller_id, fn($q, $s) => $q->where('seller_id', $s))
            ->when($request->category_id, fn($q, $c) => $q->where('category_id', $c))
            ->when($request->q, fn($q, $s) => $q->where('title', 'like', "%$s%"))
            ->when($request->has_coins === '1', fn($q) => $q->where('cashback_coins', '>', 0))
            ->when($request->has_coins === '0', fn($q) => $q->where('cashback_coins', 0))
            ->latest('id')->paginate(30)->withQueryString();
        $sellers = Seller::with('user:id,name')->get();
        $categories = Category::select('id', 'title')->orderBy('title')->get();
        return view('admin.smartbazars.products', compact('products', 'sellers', 'categories', 'houseSellerIds'));
    }

    public function updateProductCoins(Request $request)
    {
        $data = $request->validate(['coins' => 'required|array', 'coins.*' => 'nullable|integer|min:0|max:100000']);
        foreach ($data['coins'] as $id => $coins) {
            Product::whereKey($id)->update(['cashback_coins' => (int)$coins]);
        }
        return back()->with('success', 'Cashback coins updated for ' . count($data['coins']) . ' products.');
    }

    public function bulkProductCoins(Request $request)
    {
        $data = $request->validate([
            'scope' => 'required|in:all,own,vendor,seller,category',
            'seller_id' => 'required_if:scope,seller|nullable|exists:sellers,id',
            'category_id' => 'required_if:scope,category|nullable|exists:categories,id',
            'coins' => 'required|integer|min:0|max:100000',
        ]);
        $house = Seller::where('is_house_store', true)->pluck('id');
        $q = Product::query();
        match ($data['scope']) {
            'own' => $q->whereIn('seller_id', $house),
            'vendor' => $q->whereNotIn('seller_id', $house),
            'seller' => $q->where('seller_id', $data['seller_id']),
            'category' => $q->where('category_id', $data['category_id']),
            default => null,
        };
        $n = $q->update(['cashback_coins' => $data['coins']]);
        return back()->with('success', "Cashback set to {$data['coins']} coins on $n products.");
    }

    public function toggleHouseStore(Seller $seller)
    {
        $seller->update(['is_house_store' => !$seller->is_house_store]);
        return back()->with('success', 'Store marked as ' . ($seller->is_house_store ? 'SmartBazars own store' : 'vendor store') . '.');
    }

    public function coins(Request $request)
    {
        $rows = CoinTransaction::with('user:id,name,mobile')
            ->when($request->q, fn($q, $s) => $q->whereHas('user', fn($u) => $u->where('name', 'like', "%$s%")->orWhere('mobile', 'like', "%$s%")))
            ->latest()->paginate(30)->withQueryString();
        return view('admin.smartbazars.coins', compact('rows'));
    }

    public function adjustCoins(Request $request, CoinService $coins)
    {
        $data = $request->validate([
            'user' => 'required|string',
            'coins' => 'required|integer|not_in:0|min:-100000|max:100000',
            'reason' => 'required|string|max:200',
        ]);
        $user = User::where('mobile', $data['user'])->orWhere('email', $data['user'])->orWhere('id', $data['user'])->first();
        if (!$user) {
            return back()->with('error', 'Customer not found.');
        }
        $coins->adminAdjust($user, (int)$data['coins'], $data['reason'] . ' (admin)');
        return back()->with('success', "Coins adjusted for {$user->name}.");
    }

    /** Sends a real test email so a wrong SMTP setup shows its exact error instead of failing silently. */
    public function testMail(Request $request)
    {
        $to = $request->validate(['to' => 'required|email'])['to'];
        try {
            \Illuminate\Support\Facades\Mail::raw('This is a test email from SmartBazars. If you can read it, login-detail emails will be delivered.', fn($m) => $m->to($to)->subject('SmartBazars test email'));
        } catch (\Throwable $e) {
            return back()->with('error', 'Test email FAILED: ' . mb_substr($e->getMessage(), 0, 300));
        }
        $driver = config('mail.default');
        return back()->with('success', "Test email handed to the '{$driver}' mailer for {$to}."
            . ($driver === 'log' ? ' WARNING: the mailer is "log", so nothing is really delivered — save SMTP under Settings → Email or set MAIL_MAILER=smtp in .env.' : ' Check the inbox (and spam).'));
    }
}
