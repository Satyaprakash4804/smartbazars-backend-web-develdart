<?php

namespace App\Http\Controllers\Seller\SmartBazars;

use App\Http\Controllers\Controller;
use App\Models\CoinTransfer;
use App\Models\VendorCoinTransaction;
use App\Models\WholesaleOrder;
use App\Models\WholesaleProduct;
use App\Services\SmartBazars\DailyReportService;
use App\Services\SmartBazars\VendorCoinService;
use App\Services\SmartBazars\WholesaleService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/** Vendor panel: coins wallet & transfer to admin, bulk purchase from SmartBazars, workers' daily reports. */
class VendorBusinessController extends Controller
{
    protected function seller()
    {
        $seller = Auth::user()->seller();
        abort_unless($seller, 403);
        return $seller;
    }

    public function coins(VendorCoinService $svc)
    {
        $seller = $this->seller()->fresh();
        $rows = VendorCoinTransaction::where('seller_id', $seller->id)->latest('id')->paginate(25);
        $transfers = CoinTransfer::where('seller_id', $seller->id)->latest()->limit(20)->get();
        $coinValue = $svc->coinValue();
        return view('seller.smartbazars.coins', compact('seller', 'rows', 'transfers', 'coinValue'));
    }

    public function transfer(Request $request, VendorCoinService $svc)
    {
        $data = $request->validate(['coins' => 'required|integer|min:1', 'vendor_note' => 'nullable|string|max:200']);
        $t = $svc->requestTransfer($this->seller(), (int)$data['coins'], $data['vendor_note'] ?? null);
        return back()->with('success', "Transfer of {$t->coins} coins (₹" . number_format($t->rupee_value, 2) . ') sent to SmartBazars for approval.');
    }

    public function wholesale(VendorCoinService $svc)
    {
        $seller = $this->seller()->fresh();
        $products = WholesaleProduct::with(['storeVariant', 'product'])->where('is_active', true)
            ->orderByRaw('store_product_variant_id IS NULL')->orderBy('sort')->orderBy('title')->get();
        $coinValue = $svc->coinValue();
        return view('seller.smartbazars.wholesale', compact('seller', 'products', 'coinValue'));
    }

    public function placeWholesale(Request $request, WholesaleService $svc)
    {
        $data = $request->validate([
            'qty' => 'required|array',
            'qty.*' => 'nullable|integer|min:0',
            'use_coins' => 'nullable|integer|min:0',
            'payment_method' => 'required|in:settlement,offline',
            'vendor_note' => 'nullable|string|max:255',
        ]);
        $order = $svc->placeOrder($this->seller(), $data['qty'], (int)($data['use_coins'] ?? 0), $data['payment_method'], $data['vendor_note'] ?? null);
        return redirect()->route('seller.smartbazars.wholesale.orders')->with('success', "Bulk order #{$order->id} placed: ₹" . number_format($order->subtotal, 2)
            . ($order->coins_used ? " ({$order->coins_used} coins used)" : '') . '.');
    }

    public function wholesaleOrders()
    {
        $orders = WholesaleOrder::with('items')->where('seller_id', $this->seller()->id)->latest()->paginate(15);
        return view('seller.smartbazars.wholesale-orders', compact('orders'));
    }

    public function reports(Request $request, DailyReportService $svc)
    {
        $seller = $this->seller();
        $filters = $request->only(['member_id', 'date', 'month']);
        $filters['seller_id'] = $seller->id;
        if (empty($filters['date']) && empty($filters['month'])) {
            $filters['month'] = now()->format('Y-m');
        }
        if ($request->boolean('download')) {
            return $svc->csv($svc->query($filters), 'worker-reports-' . ($filters['date'] ?? $filters['month']) . '.csv');
        }
        $rows = $svc->query($filters)->paginate(25)->withQueryString();
        $workers = $seller->workers()->with('user:id,name')->get();
        return view('seller.smartbazars.reports', compact('rows', 'workers', 'filters'));
    }
}
