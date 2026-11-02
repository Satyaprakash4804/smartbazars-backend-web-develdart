<?php

namespace App\Http\Controllers\Admin\SmartBazars;

use App\Http\Controllers\Controller;
use App\Models\NetworkCommission;
use App\Models\NetworkMember;
use App\Models\NetworkRegistrationRequest;
use App\Models\NetworkWithdrawalRequest;
use App\Models\Seller;
use App\Models\User;
use App\Services\SmartBazars\NetworkService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use App\Services\SmartBazars\NetworkFeeService;

/** Admin: BSOs & BJOs network, approvals, commissions, payouts. */
class NetworkController extends Controller
{
    public function __construct(protected NetworkService $network, protected NetworkFeeService $fees) {}

    public function members(Request $request, string $type = 'agent')
    {
        $type = $type === 'worker' ? 'worker' : 'agent';
        $members = NetworkMember::with(['user', 'parent.user', 'seller.user'])
            ->withCount(['children', 'vendors', 'customers'])
            ->where('type', $type)
            ->when($request->q, function ($q, $s) {
                $q->where(fn($w) => $w->where('referral_code', 'like', "%$s%")
                    ->orWhereHas('user', fn($u) => $u->where('name', 'like', "%$s%")
                        ->orWhere('email', 'like', "%$s%")->orWhere('mobile', 'like', "%$s%")));
            })
            ->when($request->status, fn($q, $s) => $q->where('status', $s))
            ->latest()->paginate(20)->withQueryString();

        $agents = NetworkMember::with('user:id,name')->where('type', 'agent')->where('status', 'active')->get();
        $unassignedVendors = Seller::with('user:id,name,email')->whereNull('agent_member_id')->get();

        return view('admin.smartbazars.network.members', compact('members', 'type', 'agents', 'unassignedVendors'));
    }

    public function storeAgent(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:100',
            'email' => 'required|email|max:150',
            'mobile' => 'required|string|max:20',
            'parent_id' => ['nullable', Rule::exists('network_members', 'id')->where('type', 'agent')],
            'password' => 'nullable|string|min:8',
        ]);
        $parent = !empty($data['parent_id']) ? NetworkMember::where('type', 'agent')->find($data['parent_id']) : null;
        $res = $this->network->adminCreateAgent($data, Auth::id(), $parent);

        $msg = "BSO created. Referral code: {$res['member']->referral_code}.";
        $msg .= $res['mailed'] ? ' Password sent by email.' : " Email FAILED ({$res['mail_error']}) — share this password manually: {$res['password']}";
        return back()->with('success', $msg);
    }

    public function show(NetworkMember $member)
    {
        $member->load(['user', 'parent.user', 'seller.user', 'children.user']);
        $vendors = $member->isAgent() ? Seller::with('user')->where('agent_member_id', $member->id)->get() : collect();
        $customers = $member->isWorker() ? User::where('referred_by_worker_id', $member->id)->with('userCard')->latest()->limit(100)->get() : collect();
        $commissions = NetworkCommission::where('network_member_id', $member->id)->latest()->paginate(20);
        $tree = $member->isAgent() ? $this->network->tree($member) : [];
        return view('admin.smartbazars.network.show', compact('member', 'vendors', 'customers', 'commissions', 'tree'));
    }

    public function toggleStatus(NetworkMember $member)
    {
        $member->update(['status' => $member->status === 'active' ? 'suspended' : 'active']);
        return back()->with('success', 'Status changed to ' . $member->status . '.');
    }

    public function assignVendorAgent(Request $request)
    {
        $data = $request->validate([
            'seller_id' => 'required|exists:sellers,id',
            'agent_member_id' => 'nullable|exists:network_members,id',
        ]);
        Seller::whereKey($data['seller_id'])->update(['agent_member_id' => $data['agent_member_id'] ?: null]);
        return back()->with('success', 'Vendor agent updated.');
    }

    public function tree()
    {
        $tree = $this->network->tree();
        return view('admin.smartbazars.network.tree', compact('tree'));
    }

    // ---------------- approvals
    public function requests(Request $request)
    {
        $status = $request->get('status', 'pending_admin');
        $requests = NetworkRegistrationRequest::with(['requestedByMember.user', 'requestedBySeller.user', 'seller.user', 'feePayment'])
            ->when($status !== 'all', fn($q) => $q->where('status', $status))
            ->when($request->type, fn($q, $t) => $q->where('type', $t))
            ->latest()->paginate(20)->withQueryString();
        $counts = NetworkRegistrationRequest::select('status', DB::raw('count(*) c'))->groupBy('status')->pluck('c', 'status');
        return view('admin.smartbazars.network.requests', compact('requests', 'status', 'counts'));
    }

    public function approve(Request $request, NetworkRegistrationRequest $req)
    {
        $res = $this->network->adminApprove($req, Auth::id(), $request->integer('seller_id') ?: null);
        $msg = ucfirst($req->type) . " ID created for {$res['user']->email}.";
        $msg .= $res['mailed'] ? ' Password emailed.' : " Email FAILED ({$res['mail_error']}) — share this password manually: {$res['password']}";
        return back()->with('success', $msg);
    }

    public function reject(Request $request, NetworkRegistrationRequest $req)
    {
        $request->validate(['reason' => 'required|string|min:3|max:255'], ['reason.required' => 'Please give a reason for rejecting.']);
        $this->network->adminReject($req, Auth::id(), $request->input('reason'));
        return redirect()->route('admin.smartbazars.network.requests')->with('success', 'Request rejected. The applicant can apply again.');
    }

    // ---------------- ledger & payouts
    public function commissions(Request $request)
    {
        $rows = NetworkCommission::with(['member.user', 'seller.user'])
            ->when($request->source, fn($q, $s) => $q->where('source', $s))
            ->when($request->status, fn($q, $s) => $q->where('status', $s))
            ->when($request->member_id, fn($q, $m) => $q->where('network_member_id', $m))
            ->latest()->paginate(30)->withQueryString();
        $totals = NetworkCommission::where('status', 'credited')
            ->select('source', DB::raw('sum(amount) total'))->groupBy('source')->pluck('total', 'source');
        return view('admin.smartbazars.network.commissions', compact('rows', 'totals'));
    }

    public function withdrawals(Request $request)
    {
        $rows = NetworkWithdrawalRequest::with('member.user')
            ->when($request->status ?? 'pending', fn($q, $s) => $s === 'all' ? $q : $q->where('status', $s))
            ->latest()->paginate(20)->withQueryString();
        return view('admin.smartbazars.network.withdrawals', compact('rows'));
    }

    public function processWithdrawal(Request $request, NetworkWithdrawalRequest $withdrawal)
    {
        $data = $request->validate([
            'action' => 'required|in:paid,rejected',
            'transaction_reference' => 'nullable|string|max:100',
            'admin_note' => 'nullable|string|max:255',
        ]);
        if ($withdrawal->status !== 'pending') {
            return back()->with('error', 'Already processed.');
        }
        DB::transaction(function () use ($withdrawal, $data) {
            if ($data['action'] === 'rejected') {
                // amount was held at request time -> give it back
                NetworkMember::whereKey($withdrawal->network_member_id)
                    ->update(['wallet_balance' => DB::raw('wallet_balance + ' . (float)$withdrawal->amount)]);
            }
            $withdrawal->update([
                'status' => $data['action'],
                'transaction_reference' => $data['transaction_reference'] ?? null,
                'admin_note' => $data['admin_note'] ?? null,
                'processed_at' => now(),
            ]);
        });
        return back()->with('success', 'Withdrawal marked ' . $data['action'] . '.');
    }

    // ---------------------------------------------------------------- vendor documents (v1.4)
    public function vendors(Request $request)
    {
        $rows = Seller::with(['user', 'agent.user'])
            ->withCount('referredCustomers')
            ->when($request->q, fn($q, $s) => $q->where('referral_code', 'like', "%$s%")
                ->orWhereHas('user', fn($u) => $u->where('name', 'like', "%$s%")->orWhere('email', 'like', "%$s%")->orWhere('mobile', 'like', "%$s%")))
            ->latest()->paginate(20)->withQueryString();
        return view('admin.smartbazars.network.vendors', compact('rows'));
    }

    public function vendorLetter(Seller $seller)
    {
        $pdf = app(\App\Services\SmartBazars\DocumentService::class)->authorityLetterPdf($seller);
        return response($pdf, 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'inline; filename="Authority-Letter-' . $seller->ensureReferralCode() . '.pdf"']);
    }

    public function sendVendorLetter(Seller $seller)
    {
        $ok = app(\App\Services\SmartBazars\DocumentService::class)->sendVendorWelcome($seller, true);
        return back()->with($ok ? 'success' : 'error', $ok ? 'Authority letter & QR emailed to ' . ($seller->user->email ?? 'vendor') . '.' : 'Email could not be sent — check mail settings / logs.');
    }

    public function memberIdCard(NetworkMember $member)
    {
        $pdf = app(\App\Services\SmartBazars\DocumentService::class)->idCardPdf($member);
        return response($pdf, 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'inline; filename="ID-' . $member->referral_code . '.pdf"']);
    }

    // ---------------------------------------------------------------- joining fee: details, documents, create ID
    public function showRequest(NetworkRegistrationRequest $req)
    {
        $req->load(['requestedByMember.user', 'requestedBySeller.user', 'seller.user', 'feePayment']);
        $vendors = $req->type === 'worker' && !$req->seller_id ? Seller::with('user:id,name')->get() : collect();
        $earlier = $req->reapplied_from_id ? NetworkRegistrationRequest::find($req->reapplied_from_id) : null;
        return view('admin.smartbazars.network.request-show', compact('req', 'vendors', 'earlier'));
    }

    /** Applicant documents live on the private disk — streamed to admins only. */
    public function document(NetworkRegistrationRequest $req, string $doc)
    {
        $path = $req->documents[$doc] ?? null;
        abort_unless($path && Storage::disk('local')->exists($path), 404);
        return Storage::disk('local')->response($path);
    }

    public function resendCredentials(NetworkRegistrationRequest $req)
    {
        $res = $this->network->resendCredentials($req);
        if ($res['mailed']) {
            return back()->with('success', 'New login details emailed to ' . $req->email . '.');
        }
        return back()->with('error', "Email FAILED ({$res['error']}). A new password was set — share it manually: {$res['password']}");
    }

    public function waivePayment(NetworkRegistrationRequest $req)
    {
        abort_unless($req->payment_status === 'pending', 422, 'Nothing to waive.');
        $this->fees->waiveRequest($req);
        return back()->with('success', 'Joining fee waived.');
    }

    public function waiveVendorFee(Seller $seller)
    {
        $this->fees->waiveSeller($seller);
        return back()->with('success', 'Joining fee waived for this vendor.');
    }

    public function createForm()
    {
        $agents = NetworkMember::with('user:id,name')->where('type', 'agent')->where('status', 'active')->get();
        $sellers = Seller::with('user:id,name')->get();
        return view('admin.smartbazars.network.create', [
            'agents' => $agents, 'sellers' => $sellers,
            'fees' => ['agent' => $this->fees->feeFor('agent'), 'worker' => $this->fees->feeFor('worker'), 'vendor' => $this->fees->feeFor('vendor')],
            'feesEnabled' => $this->fees->enabled(),
        ]);
    }

    public function storeMember(Request $request)
    {
        $data = $request->validate([
            'type' => 'required|in:agent,worker,vendor',
            'name' => 'required|string|max:100',
            'email' => 'required|email|max:150',
            'mobile' => 'required|string|min:10|max:20',
            'payment_mode' => 'nullable|in:collect,waive',
            'parent_id' => 'nullable|exists:network_members,id',
            'seller_id' => 'required_if:type,worker|nullable|exists:sellers,id',
            'meta' => 'nullable|array',
            'meta.store_name' => 'required_if:type,vendor|nullable|string|max:150',
            'meta.address' => 'required_if:type,vendor|nullable|string|max:255',
            'meta.landmark' => 'nullable|string|max:100',
            'meta.city' => 'required_if:type,vendor|nullable|string|max:100',
            'meta.state' => 'required_if:type,vendor|nullable|string|max:100',
            'meta.zipcode' => 'required_if:type,vendor|nullable|string|max:20',
        ]);
        $collect = ($data['payment_mode'] ?? 'collect') === 'collect';
        $res = $this->network->adminCreate($data['type'], $data, Auth::id(), $collect);

        if ($res['paid_link']) {
            return back()->with('success', ucfirst($data['type']) . " ID reserved. It is created automatically once the joining fee is paid. Payment link emailed — you can also share it: {$res['paid_link']}");
        }
        $msg = ucfirst($data['type']) . " ID created for {$res['result']['user']->email}.";
        $msg .= $res['result']['mailed'] ? ' Password emailed.' : " Email FAILED ({$res['result']['mail_error']}) — share this password manually: {$res['result']['password']}";
        return back()->with('success', $msg);
    }

    // ---------------------------------------------------------------- edit / delete / resend login details
    protected function resendResponse(array $res, string $email)
    {
        if ($res['mailed']) {
            return back()->with('success', "New login details emailed to {$email}.");
        }
        return back()->with('error', "Email FAILED ({$res['error']}). A new password was set — share it manually: {$res['password']}");
    }

    public function resendMemberCredentials(NetworkMember $member)
    {
        $member->load('user');
        abort_unless($member->user, 404);
        return $this->resendResponse($this->network->resendCredentialsFor($member->user, ucfirst($member->type), $member, route('partner.login')), $member->user->email);
    }

    public function resendVendorCredentials(Seller $seller)
    {
        $seller->load('user');
        abort_unless($seller->user, 404);
        return $this->resendResponse($this->network->resendCredentialsFor($seller->user, 'Vendor', null, route('seller.login'), $seller->ensureReferralCode()), $seller->user->email);
    }

    public function editMember(NetworkMember $member)
    {
        $member->load(['user', 'parent.user', 'seller.user']);
        $sellers = $member->isWorker() ? Seller::with('user:id,name')->get() : collect();
        // BSOs that may become this agent's upline: everyone except itself and its own downline.
        $uplines = $member->isAgent()
            ? NetworkMember::with('user:id,name')->where('type', 'agent')->where('id', '!=', $member->id)
                ->where(fn($q) => $q->whereNull('path')->orWhere('path', 'not like', '%/' . $member->id . '/%'))
                ->orderBy('level')->get()
            : collect();
        $kycService = app(\App\Services\SmartBazars\KycService::class);
        $kyc = $kycService->kycFor($member);
        $kycMissing = $kycService->memberMissing($member);
        return view('admin.smartbazars.network.edit', compact('member', 'sellers', 'uplines', 'kyc', 'kycMissing'));
    }

    public function kycDocument(NetworkMember $member, string $doc)
    {
        $path = app(\App\Services\SmartBazars\KycService::class)->kycFor($member)?->documents[$doc] ?? null;
        abort_unless($path && Storage::disk('local')->exists($path), 404);
        return Storage::disk('local')->response($path);
    }

    public function updateMember(Request $request, NetworkMember $member)
    {
        $uid = $member->user_id;
        $data = $request->validate([
            'name' => 'required|string|max:100',
            'email' => ['required', 'email', 'max:150', Rule::unique('users', 'email')->ignore($uid)->whereNull('deleted_at')],
            'mobile' => ['required', 'string', 'min:10', 'max:20', Rule::unique('users', 'mobile')->ignore($uid)->whereNull('deleted_at')],
            'status' => 'required|in:active,suspended',
            'seller_id' => [$member->isWorker() ? 'required' : 'nullable', 'exists:sellers,id'],
            'send_credentials' => 'nullable|boolean',
            'parent_id' => ['nullable', Rule::exists('network_members', 'id')->where('type', 'agent')],
        ]);

        // Upline change (agents only) goes through the mapping service: cycle check, sub-tree re-path, audit log.
        if ($member->isAgent() && $request->has('parent_id') && (int)($data['parent_id'] ?? 0) !== (int)$member->parent_id) {
            try {
                app(\App\Services\SmartBazars\NetworkMappingService::class)->moveAgent(
                    $member,
                    !empty($data['parent_id']) ? NetworkMember::where('type', 'agent')->find($data['parent_id']) : null,
                    Auth::id());
            } catch (ValidationException $e) {
                return back()->withInput()->with('error', $e->validator->errors()->first());
            }
            $member->refresh();
        }

        $emailChanged = strtolower(trim($data['email'])) !== strtolower((string)$member->user->email);
        $member->user->update(['name' => $data['name'], 'email' => strtolower(trim($data['email'])), 'mobile' => trim($data['mobile'])]);
        $update = ['status' => $data['status']];
        if ($member->isWorker()) $update['seller_id'] = (int)$data['seller_id'];
        $member->update($update);

        if ($emailChanged && $request->boolean('send_credentials')) {
            return $this->resendResponse($this->network->resendCredentialsFor($member->user->fresh(), ucfirst($member->type), $member, route('partner.login')), $member->user->email);
        }
        return redirect()->route('admin.smartbazars.network.' . ($member->isAgent() ? 'agents' : 'workers'))->with('success', 'Details updated.');
    }

    // ---------------------------------------------------------------- delete (archive) flow

    /** Step 1: show wallet balance, payouts and downline before anything is deleted. */
    public function deleteForm(NetworkMember $member)
    {
        $member->load(['user', 'parent.user', 'seller.user']);
        $preview = $this->network->deletionPreview($member);
        return view('admin.smartbazars.network.delete-member', compact('member', 'preview'));
    }

    public function destroyMember(Request $request, NetworkMember $member)
    {
        $route = 'admin.smartbazars.network.' . ($member->isAgent() ? 'agents' : 'workers');
        $request->validate([
            'confirm_wallet' => ((float)$member->wallet_balance > 0 || $member->withdrawals()->where('status', 'pending')->exists()) ? 'accepted' : 'nullable',
        ], ['confirm_wallet.accepted' => 'Please tick the box to confirm you have noted the wallet balance / pending payouts.']);

        try {
            $this->network->deleteMember($member, Auth::id());
        } catch (ValidationException $e) {
            return back()->with('error', $e->validator->errors()->first());
        }
        return redirect()->route($route)->with('success', 'Deleted. Their wallet and payout history are kept under Deleted Records, and their downline now sits under their upline (or directly under SmartBazars).');
    }

    /** Vendor delete page: shows the vendor's workers and lets the admin move or delete them. */
    public function vendorDeleteForm(Seller $seller)
    {
        $seller->load(['user', 'agent.user']);
        $workerQuery = NetworkMember::where('type', NetworkMember::TYPE_WORKER)->where('seller_id', $seller->id);
        $workers = (clone $workerQuery)->with('user:id,name,email,mobile')->withCount('customers')->orderBy('id')->paginate(10)->withQueryString();
        $customers = User::whereIn('referred_by_worker_id', (clone $workerQuery)->select('id'))->count();
        $wallets = (float)(clone $workerQuery)->sum('wallet_balance');
        return view('admin.smartbazars.network.delete-vendor', compact('seller', 'workers', 'customers', 'wallets'));
    }

    /** JSON: search existing vendors (type-ahead). agent_id limits it to one agent's vendors. */
    public function vendorSearch(Request $request)
    {
        $q = trim((string)$request->query('q', ''));
        $rows = Seller::with(['user:id,name,email', 'agent.user:id,name'])
            ->when($request->integer('exclude'), fn($x, $id) => $x->where('id', '!=', $id))
            ->when($request->integer('agent_id'), fn($x, $id) => $x->where('agent_member_id', $id))
            ->when($q !== '', fn($x) => $x->where(fn($w) => $w->where('referral_code', 'like', "%$q%")
                ->orWhereHas('user', fn($u) => $u->where('name', 'like', "%$q%")->orWhere('email', 'like', "%$q%")->orWhere('mobile', 'like', "%$q%"))))
            ->orderBy('id')->limit(10)->get();

        return response()->json($rows->map(fn($v) => [
            'id' => $v->id,
            'name' => $v->displayName(),
            'email' => $v->user->email ?? '',
            'code' => $v->referral_code,
            'agent' => $v->agent?->user?->name ?? 'SmartBazars',
        ])->values());
    }

    public function destroyVendor(Request $request, Seller $seller)
    {
        $hasWorkers = NetworkMember::where('type', NetworkMember::TYPE_WORKER)->where('seller_id', $seller->id)->exists();
        $data = $request->validate([
            'worker_action' => [$hasWorkers ? 'required' : 'nullable', Rule::in(['move_same_agent', 'move_any', 'direct', 'delete'])],
            'target_seller_id' => 'nullable|integer|exists:sellers,id',
        ]);
        $choice = $hasWorkers ? $data['worker_action'] : 'direct';
        $action = str_starts_with($choice, 'move') ? 'move' : $choice;
        $targetId = $data['target_seller_id'] ?? null;

        if ($choice === 'move_same_agent') {
            $ok = $seller->agent_member_id && $targetId
                && Seller::where('id', $targetId)->where('agent_member_id', $seller->agent_member_id)->exists();
            if (!$ok) {
                return back()->withInput()->with('error', "Pick a vendor that belongs to the same agent, or choose 'any vendor'.");
            }
        }

        try {
            $res = $this->network->deleteVendor($seller, $action, $targetId ? (int)$targetId : null, Auth::id());
        } catch (ValidationException $e) {
            return back()->withInput()->with('error', $e->validator->errors()->first());
        }

        $msg = 'Vendor deleted. Its records are kept under Deleted Records.';
        if ($res['workers'] > 0) {
            $msg .= ' ' . $res['workers'] . ($res['action'] === 'delete' ? ' worker(s) were deleted too.' : ' worker(s) moved to ' . $res['target'] . '.');
        }
        return redirect()->route('admin.smartbazars.network.vendors')->with('success', $msg);
    }

    // ---------------------------------------------------------------- deleted records (archive)

    public function deleted(Request $request)
    {
        $tab = in_array($request->query('type'), ['agent', 'worker', 'vendor'], true) ? $request->query('type') : 'agent';
        $s = trim((string)$request->query('q', ''));
        $earned = collect();

        if ($tab === 'vendor') {
            $rows = Seller::onlyTrashed()->with('user')
                ->when($s !== '', fn($q) => $q->where(fn($w) => $w->where('referral_code', 'like', "%$s%")->orWhere('deleted_snapshot', 'like', "%$s%")))
                ->orderByDesc('deleted_at')->paginate(20)->withQueryString();
            $earned = NetworkCommission::whereIn('seller_id', $rows->pluck('id'))->where('status', 'credited')
                ->groupBy('seller_id')->select('seller_id', DB::raw('sum(amount) as t'))->pluck('t', 'seller_id');
            $summary = ['count' => Seller::onlyTrashed()->count(), 'wallet' => null];
        } else {
            $base = NetworkMember::onlyTrashed()->where('type', $tab);
            $rows = (clone $base)
                ->when($s !== '', fn($q) => $q->where(fn($w) => $w->where('referral_code', 'like', "%$s%")->orWhere('deleted_snapshot', 'like', "%$s%")))
                ->orderByDesc('deleted_at')->paginate(20)->withQueryString();
            $summary = ['count' => (clone $base)->count(), 'wallet' => (float)(clone $base)->sum('wallet_balance')];
        }
        $admins = User::withTrashed()->whereIn('id', $rows->pluck('deleted_by')->filter()->unique())->pluck('name', 'id');

        return view('admin.smartbazars.network.deleted', compact('rows', 'tab', 'summary', 'earned', 'admins'));
    }

    /** One archived agent / worker: identity at deletion time + full commission and withdrawal history. */
    public function deletedShow(int $id)
    {
        $member = NetworkMember::onlyTrashed()->findOrFail($id);
        $commissions = $member->commissions()->latest()->paginate(15, ['*'], 'cp')->withQueryString();
        $withdrawals = $member->withdrawals()->latest()->paginate(15, ['*'], 'wp')->withQueryString();
        $admin = $member->deleted_by ? User::withTrashed()->find($member->deleted_by) : null;
        return view('admin.smartbazars.network.deleted-show', compact('member', 'commissions', 'withdrawals', 'admin'));
    }
}
