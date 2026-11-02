<?php

namespace App\Http\Controllers\Admin\SmartBazars;

use App\Http\Controllers\Controller;
use App\Models\NetworkWalletRecharge;
use App\Services\SmartBazars\NetworkWalletService;
use App\Services\SmartBazars\OfflineCardService;
use App\Services\SmartBazars\ProgramSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class WalletRechargeController extends Controller
{
    public function __construct(protected NetworkWalletService $wallets) {}

    public function index(Request $request)
    {
        $rows = NetworkWalletRecharge::with('member.user')
            ->when($request->status, fn($q, $s) => $q->where('status', $s))
            ->when($request->q, fn($q, $s) => $q->where('reference', 'like', "%$s%")
                ->orWhereHas('member.user', fn($u) => $u->where('name', 'like', "%$s%")->orWhere('mobile', 'like', "%$s%")))
            ->orderByRaw("status = 'pending' desc")->latest()->paginate(20)->withQueryString();
        return view('offline.wallet.admin', [
            'rows' => $rows,
            'pending' => NetworkWalletRecharge::where('status', 'pending')->count(),
            'instructions' => ProgramSettings::get('wallet_recharge_instructions'),
            'min' => ProgramSettings::get('wallet_min_recharge', 100),
        ]);
    }

    public function approve(Request $request, NetworkWalletRecharge $recharge)
    {
        $this->wallets->approve($recharge, Auth::id(), $request->input('note'));
        return back()->with('success', 'Recharge approved — BJO wallet credited ₹' . number_format((float)$recharge->amount, 2) . '.');
    }

    public function reject(Request $request, NetworkWalletRecharge $recharge)
    {
        $data = $request->validate(['note' => 'required|string|max:200']);
        $this->wallets->reject($recharge, Auth::id(), $data['note']);
        return back()->with('success', 'Recharge request rejected.');
    }

    public function proof(NetworkWalletRecharge $recharge)
    {
        abort_unless($recharge->proof_file && Storage::disk(OfflineCardService::DISK)->exists($recharge->proof_file), 404);
        return Storage::disk(OfflineCardService::DISK)->response($recharge->proof_file);
    }

    public function settings(Request $request)
    {
        $data = $request->validate(['wallet_min_recharge' => 'required|numeric|min:1', 'wallet_recharge_instructions' => 'required|string|max:1000']);
        ProgramSettings::save(array_merge(ProgramSettings::all(), $data));
        return back()->with('success', 'Recharge settings saved.');
    }
}
