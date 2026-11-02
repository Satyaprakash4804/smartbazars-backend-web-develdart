<?php

namespace App\Http\Controllers\Partner;

use App\Http\Controllers\Controller;
use App\Models\NetworkWalletLedger;
use App\Models\NetworkWalletRecharge;
use App\Services\SmartBazars\NetworkWalletService;
use App\Services\SmartBazars\ProgramSettings;
use Illuminate\Http\Request;

/** BJO wallets: order wallet (Razorpay / request / earnings transfer) + earnings wallet, with history. */
class WalletController extends Controller
{
    public function __construct(protected NetworkWalletService $wallets) {}

    public function index(Request $request)
    {
        $member = $request->attributes->get('member');
        $member->refresh();
        return view('offline.wallet.partner', [
            'member' => $member,
            'orderBalance' => (float)$member->order_wallet_balance,
            'earnings' => (float)$member->wallet_balance,
            'ledger' => NetworkWalletLedger::where('network_member_id', $member->id)
                ->when($request->wallet, fn($q, $w) => $q->where('wallet', $w))->latest()->paginate(15, ['*'], 'l')->withQueryString(),
            'requests' => NetworkWalletRecharge::where('network_member_id', $member->id)
                ->where(fn($q) => $q->whereNull('gateway')->orWhere('status', 'approved'))->latest()->limit(10)->get(),
            'instructions' => ProgramSettings::get('wallet_recharge_instructions'),
            'min' => (float)ProgramSettings::get('wallet_min_recharge', 100),
            'need' => max(0, (float)$request->query('need', 0)),
            'coin' => $this->wallets->coinSummary($member),
        ]);
    }

    /** JSON: create the Razorpay order for the Checkout popup. */
    public function razorpayOrder(Request $request)
    {
        $member = $request->attributes->get('member');
        $data = $request->validate(['amount' => 'required|numeric|min:1|max:200000']);
        return response()->json(['ok' => true] + $this->wallets->startRazorpay($member, (float)$data['amount']));
    }

    /** JSON: browser callback after a successful payment. */
    public function razorpayVerify(Request $request)
    {
        $member = $request->attributes->get('member');
        $d = $request->validate([
            'razorpay_order_id' => 'required|string',
            'razorpay_payment_id' => 'required|string',
            'razorpay_signature' => 'required|string',
        ]);
        $r = $this->wallets->verifyRazorpay($member, $d['razorpay_order_id'], $d['razorpay_payment_id'], $d['razorpay_signature']);
        return response()->json([
            'ok' => true,
            'message' => '₹' . number_format((float)$r->amount, 2) . ' added to your order wallet.',
            'balance' => (float)$member->fresh()->order_wallet_balance,
        ]);
    }

    public function transfer(Request $request)
    {
        $member = $request->attributes->get('member');
        $data = $request->validate(['amount' => 'required|numeric|min:1|max:500000']);
        $m = $this->wallets->transferEarnings($member, (float)$data['amount']);
        return redirect()->route('partner.wallet')->with('success', '₹' . number_format((float)$data['amount'], 2) . ' moved to your order wallet. Order wallet: ₹' . number_format((float)$m->order_wallet_balance, 2) . '.');
    }

    /** Cashback coins -> order wallet. */
    public function coins(Request $request)
    {
        $member = $request->attributes->get('member');
        $data = $request->validate(['coins' => 'required|integer|min:1|max:10000000']);
        $r = $this->wallets->transferCoins($member, (int)$data['coins']);
        return redirect()->route('partner.wallet')->with('success', $r['coins'] . ' coins moved: ₹' . number_format($r['value'], 2)
            . ' added. Order wallet: ₹' . number_format($r['order_balance'], 2) . '.');
    }

    public function recharge(Request $request)
    {
        $member = $request->attributes->get('member');
        $data = $request->validate([
            'amount' => 'required|numeric|min:1|max:500000',
            'method' => 'required|in:upi,bank,cash,other',
            'reference' => 'required|string|min:4|max:80',
            'note' => 'nullable|string|max:255',
            'proof' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:4096',
        ]);
        $this->wallets->request($member, $data, $request->file('proof'));
        return redirect()->route('partner.wallet')->with('success', 'Recharge request sent. Your wallet is credited as soon as SmartBazars approves it.');
    }
}
