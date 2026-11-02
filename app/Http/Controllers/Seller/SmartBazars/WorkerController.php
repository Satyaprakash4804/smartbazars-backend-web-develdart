<?php

namespace App\Http\Controllers\Seller\SmartBazars;

use App\Http\Controllers\Controller;
use App\Models\NetworkCommission;
use App\Models\NetworkMember;
use App\Models\NetworkRegistrationRequest;
use App\Models\WelcomeKitPurchase;
use App\Services\SmartBazars\NetworkService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/** Seller panel: vendor manages its workers (request new, approve agent-raised requests, suspend). */
class WorkerController extends Controller
{
    public function __construct(protected NetworkService $network) {}

    protected function seller()
    {
        $seller = Auth::user()->seller();
        abort_unless($seller, 403);
        return $seller;
    }

    public function index()
    {
        $seller = $this->seller();
        $workers = NetworkMember::with('user')->withCount('customers')
            ->where('type', 'worker')->where('seller_id', $seller->id)->latest()->get();
        $awaitingMe = NetworkRegistrationRequest::with('requestedByMember.user')
            ->where('type', 'worker')->where('seller_id', $seller->id)->where('status', 'pending_seller')->latest()->get();
        $history = NetworkRegistrationRequest::where('type', 'worker')->where('seller_id', $seller->id)
            ->whereIn('status', ['pending_payment', 'pending_admin', 'approved', 'rejected'])->with('feePayment')->latest()->limit(30)->get();
        $kitEarnings = NetworkCommission::where('seller_id', $seller->id)->where('source', 'welcome_kit')->where('status', 'credited')->sum('amount');
        $kitsSold = WelcomeKitPurchase::whereIn('worker_member_id', $workers->pluck('id'))->where('status', '!=', 'cancelled')->count();
        $agent = $seller->agent_member_id ? NetworkMember::with('user')->find($seller->agent_member_id) : null;
        return view('seller.smartbazars.workers', compact('seller', 'workers', 'awaitingMe', 'history', 'kitEarnings', 'kitsSold', 'agent'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:100',
            'email' => 'required|email|max:150',
            'mobile' => 'required|string|min:10|max:15',
        ]);
        $req = $this->network->submitRequest('worker', $data, $this->seller());
        if ($req->status === 'pending_payment' && $req->feePayment) {
            return redirect($req->feePayment->payUrl());
        }
        return back()->with('success', 'BJO request sent to SmartBazars admin for approval.');
    }

    public function decide(Request $request, NetworkRegistrationRequest $req)
    {
        $approve = $request->input('decision') === 'approve';
        $this->network->sellerDecision($req, $this->seller(), $approve, $request->input('reason'));
        return back()->with('success', $approve ? 'Approved — now waiting for admin approval.' : 'Request rejected.');
    }

    public function toggle(NetworkMember $member)
    {
        abort_unless($member->type === 'worker' && (int)$member->seller_id === (int)$this->seller()->id, 403);
        $member->update(['status' => $member->status === 'active' ? 'suspended' : 'active']);
        return back()->with('success', 'BJO ' . $member->status . '.');
    }

    // ---------------------------------------------------------------- QR & authority letter (v1.4)
    public function myQr()
    {
        $seller = $this->seller();
        $docs = app(\App\Services\SmartBazars\DocumentService::class);
        $data = $docs->vendorData($seller);
        $data['customers'] = \App\Models\User::where('referred_by_seller_id', $seller->id)->count()
            + \App\Models\User::whereIn('referred_by_worker_id', $seller->workers()->pluck('id'))->count();
        $data['earned'] = \App\Models\NetworkCommission::where('seller_id', $seller->id)->where('status', 'credited')->sum('amount');
        return view('seller.smartbazars.my-qr', $data);
    }

    public function letterPdf()
    {
        $seller = $this->seller();
        $pdf = app(\App\Services\SmartBazars\DocumentService::class)->authorityLetterPdf($seller);
        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="SmartBazars-Authority-Letter-' . $seller->referral_code . '.pdf"',
        ]);
    }

    public function qrDownload()
    {
        $seller = $this->seller();
        $link = app(\App\Services\SmartBazars\ReferralService::class)->referralLink($seller->ensureReferralCode());
        $png = \App\Support\SmartBazars\Qr::png($link, 900);
        return $png !== null
            ? response($png, 200, ['Content-Type' => 'image/png', 'Content-Disposition' => 'attachment; filename="SmartBazars-QR-' . $seller->referral_code . '.png"'])
            : response(\App\Support\SmartBazars\Qr::svg($link, 900), 200, ['Content-Type' => 'image/svg+xml', 'Content-Disposition' => 'attachment; filename="SmartBazars-QR-' . $seller->referral_code . '.svg"']);
    }
}
