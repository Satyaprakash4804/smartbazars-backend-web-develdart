<?php

namespace App\Http\Controllers\Admin\SmartBazars;

use App\Http\Controllers\Controller;
use App\Models\CoinTransfer;
use App\Models\NetworkMember;
use App\Models\Seller;
use App\Models\SellerUser;
use App\Models\Store;
use App\Models\User;
use App\Models\VendorCoinTransaction;
use App\Models\WholesaleOrder;
use App\Models\WholesaleProduct;
use App\Services\SmartBazars\DailyReportService;
use App\Services\SmartBazars\VendorCoinService;
use App\Services\SmartBazars\WholesaleService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Admin: coin ledger & vendor transfers, wholesale (bulk) catalogue & orders, daily reports, own store. */
class BusinessController extends Controller
{
    // ------------------------------------------------------------ coins
    public function coinLedger(Request $request, VendorCoinService $svc)
    {
        $summary = [
            'admin' => $svc->adminBalance(),
            'vendors' => (int)Seller::sum('coin_balance'),
            'customers' => (int)User::sum('coin_balance'),
            'pending' => (int)CoinTransfer::where('status', 'pending')->sum('coins'),
            'coin_value' => $svc->coinValue(),
        ];
        $transfers = CoinTransfer::with('seller.user')->where('status', 'pending')->latest()->get();
        $rows = VendorCoinTransaction::with('seller.user')
            ->when($request->who === 'admin', fn($q) => $q->whereNull('seller_id'))
            ->when($request->who === 'vendors', fn($q) => $q->whereNotNull('seller_id'))
            ->when($request->seller_id, fn($q, $s) => $q->where('seller_id', $s))
            ->latest('id')->paginate(30)->withQueryString();
        $sellers = Seller::with('user:id,name')->get();
        return view('admin.smartbazars.business.coins', compact('summary', 'transfers', 'rows', 'sellers'));
    }

    public function processTransfer(Request $request, CoinTransfer $transfer, VendorCoinService $svc)
    {
        $svc->processTransfer($transfer, $request->input('action') === 'approve', $request->input('admin_note'));
        return back()->with('success', 'Transfer ' . ($request->input('action') === 'approve' ? 'approved — ₹' . number_format($transfer->rupee_value, 2) . ' credited to the vendor settlement.' : 'rejected — coins returned.'));
    }

    // ------------------------------------------------------------ wholesale
    public function wholesale(Request $request)
    {
        $products = WholesaleProduct::with(['product:id,title', 'storeVariant'])
            ->when($request->src === 'own', fn($q) => $q->whereNotNull('store_product_variant_id'))
            ->when($request->src === 'manual', fn($q) => $q->whereNull('store_product_variant_id'))
            ->when($request->q, fn($q, $s) => $q->where('title', 'like', "%$s%"))
            ->orderBy('sort')->latest('id')->paginate(25)->withQueryString();
        $catalog = \App\Models\Product::select('id', 'title')->orderBy('title')->limit(3000)->get();
        $edit = $request->edit ? WholesaleProduct::find($request->edit) : null;
        return view('admin.smartbazars.business.wholesale', compact('products', 'catalog', 'edit'));
    }

    public function saveWholesale(Request $request, ?WholesaleProduct $item = null)
    {
        $data = $request->validate([
            'product_id' => 'nullable|exists:products,id',
            'title' => 'required_without:product_id|nullable|string|max:190',
            'unit' => 'nullable|string|max:50',
            'description' => 'nullable|string|max:2000',
            'mrp' => 'nullable|numeric|min:0',
            'vendor_price' => 'required|numeric|min:0.01',
            'min_qty' => 'required|integer|min:1',
            'max_qty' => 'nullable|integer|min:1|gte:min_qty',
            'stock' => 'nullable|integer|min:0',
            'max_coin_percent' => 'required|integer|min:0|max:100',
            'image' => 'nullable|image|max:2048',
        ]);
        if (empty($data['title']) && !empty($data['product_id'])) {
            $data['title'] = \App\Models\Product::find($data['product_id'])->title;
        }
        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('wholesale', 'public');
        } else {
            unset($data['image']);
        }
        $data['mrp'] = $data['mrp'] ?? 0;
        $data['is_active'] = $request->boolean('is_active', true);
        $item && $item->exists ? $item->update($data) : WholesaleProduct::create($data);
        return redirect()->route('admin.smartbazars.wholesale.index')->with('success', 'Bulk product saved.');
    }

    public function syncOwnStoreWholesale(\App\Services\SmartBazars\WholesaleCatalog $catalog)
    {
        $n = $catalog->syncOwnStore();
        return back()->with('success', $n ? "$n SmartBazars own-store products added to the vendor bulk catalogue." : 'All SmartBazars own-store products are already in the bulk catalogue.');
    }

    public function toggleWholesale(WholesaleProduct $item)
    {
        $item->update(['is_active' => !$item->is_active]);
        return back()->with('success', $item->is_active ? 'Product is visible to vendors.' : 'Product hidden from vendors.');
    }

    public function wholesaleOrders(Request $request)
    {
        $orders = WholesaleOrder::with(['seller.user', 'items'])
            ->when($request->status, fn($q, $s) => $q->where('status', $s))
            ->latest()->paginate(20)->withQueryString();
        return view('admin.smartbazars.business.wholesale-orders', compact('orders'));
    }

    public function updateWholesaleOrder(Request $request, WholesaleOrder $order, WholesaleService $svc)
    {
        $request->validate(['status' => 'required|in:' . implode(',', WholesaleOrder::STATUSES)]);
        $svc->updateStatus($order, $request->status, $request->admin_note);
        return back()->with('success', "Bulk order #{$order->id} marked {$request->status}.");
    }

    // ------------------------------------------------------------ daily reports
    public function reports(Request $request, DailyReportService $svc)
    {
        $filters = $request->only(['type', 'member_id', 'date', 'month', 'seller_id']);
        if (empty($filters['date']) && empty($filters['month'])) {
            $filters['month'] = now()->format('Y-m');
        }
        if ($request->boolean('download')) {
            $name = 'SmartBazars-daily-reports-' . ($filters['date'] ?? $filters['month']) . '.csv';
            return $svc->csv($svc->query($filters), $name);
        }
        $rows = $svc->query($filters)->paginate(30)->withQueryString();
        $members = NetworkMember::with('user:id,name')->orderBy('type')->get();
        $sellers = Seller::with('user:id,name')->get();
        $missingToday = NetworkMember::with('user:id,name')->where('status', 'active')
            ->whereDoesntHave('dailyReports', fn($q) => $q->whereDate('report_date', today()))->count();
        return view('admin.smartbazars.business.reports', compact('rows', 'members', 'sellers', 'filters', 'missingToday'));
    }

    // ------------------------------------------------------------ own store
    /** Open (and create if needed) the SmartBazars own store, then manage its products in the vendor panel. */
    public function ownStore()
    {
        $admin = Auth::user();
        $seller = DB::transaction(function () use ($admin) {
            $seller = Seller::where('is_house_store', true)->orderBy('id')->first();
            if (!$seller) {
                $owner = User::firstOrCreate(['email' => 'store@smartbazars.in'], [
                    'name' => 'SmartBazars Store',
                    'mobile' => '9000000999',
                    'password' => Str::random(24),
                    'access_panel' => 'seller',
                    'status' => 'active',
                ]);
                if (!$owner->hasRole('seller')) $owner->assignRole('seller');
                $seller = Seller::create([
                    'user_id' => $owner->id, 'is_house_store' => true,
                    'address' => 'SmartBazars Head Office', 'city' => 'Patna', 'landmark' => '', 'state' => 'Bihar',
                    'zipcode' => '800001', 'country' => 'India', 'country_code' => '91',
                    'verification_status' => 'approved', 'visibility_status' => 'visible',
                    'metadata' => ['store_name' => 'SmartBazars'],
                ]);
            }
            SellerUser::firstOrCreate(['user_id' => $admin->id], ['seller_id' => $seller->id]);
            return $seller;
        });
        if (!Store::where('seller_id', $seller->id)->exists()) {
            session()->flash('info', 'Create the SmartBazars store (name, address, delivery zone) first, then add products.');
        }
        session(['impersonating_as_seller' => true, 'impersonated_seller_id' => $seller->id]);
        return redirect()->route('seller.dashboard')->with('success', 'You are managing the SmartBazars own store. Use "Back to Admin" in the header to return.');
    }
}
