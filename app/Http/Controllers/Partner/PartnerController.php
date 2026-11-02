<?php

namespace App\Http\Controllers\Partner;

use App\Http\Controllers\Controller;
use App\Models\NetworkCommission;
use App\Models\NetworkMember;
use App\Models\NetworkRegistrationRequest;
use App\Models\NetworkWithdrawalRequest;
use App\Models\Seller;
use App\Models\User;
use App\Models\WelcomeKitPurchase;
use App\Services\SmartBazars\NetworkService;
use App\Services\SmartBazars\ProgramSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * SmartBazars Partner panel (/partner) — shared by BSOs and Vendor workers.
 */
class PartnerController extends Controller
{
    public function __construct(protected NetworkService $network) {}

    protected function member(Request $request): NetworkMember
    {
        return $request->attributes->get('member');
    }

    // ---------------------------------------------------------------- auth
    public function showLogin()
    {
        return view('partner.auth.login');
    }

    public function login(Request $request)
    {
        $cred = $request->validate(['email' => 'required|email', 'password' => 'required|string']);
        $user = User::where('email', strtolower($cred['email']))->first();
        if (!$user || ($user->access_panel->value ?? null) !== 'partner' || !Hash::check($cred['password'], $user->password)) {
            return back()->withInput($request->only('email'))->with('error', 'Invalid email or password.');
        }
        Auth::login($user, $request->boolean('remember'));
        $request->session()->regenerate();
        return redirect()->route('partner.dashboard');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('partner.login');
    }

    // ---------------------------------------------------------------- dashboard
    public function dashboard(Request $request)
    {
        $m = $this->member($request);
        $stats = [
            'balance' => $m->wallet_balance,
            'total_earned' => $m->total_earned,
            'this_month' => NetworkCommission::where('network_member_id', $m->id)->where('status', 'credited')
                ->where('created_at', '>=', now()->startOfMonth())->sum('amount'),
            'pending_requests' => NetworkRegistrationRequest::where('requested_by_member_id', $m->id)
                ->whereIn('status', ['pending_seller', 'pending_admin'])->count(),
        ];
        if ($m->isAgent()) {
            $stats['sub_agents'] = NetworkMember::where('type', 'agent')->where('path', 'like', $m->path . $m->id . '/%')->count();
            $stats['direct_agents'] = $m->children()->count();
            $stats['vendors'] = Seller::where('agent_member_id', $m->id)->count();
            $stats['workers'] = NetworkMember::where('type', 'worker')->whereIn('seller_id', Seller::where('agent_member_id', $m->id)->pluck('id'))->count();
            $today = $this->network->addedBetween($m, now()->startOfDay(), now()->endOfDay());
            $stats['added_today'] = ['agents' => $today['agents']->count(), 'vendors' => $today['vendors']->count(), 'workers' => $today['workers']->count()];
            $stats['added_today']['total'] = array_sum($stats['added_today']);
        } else {
            $stats['customers'] = User::where('referred_by_worker_id', $m->id)->count();
            $stats['kits_sold'] = WelcomeKitPurchase::where('worker_member_id', $m->id)->where('status', '!=', 'cancelled')->count();
        }
        $recent = NetworkCommission::where('network_member_id', $m->id)->latest()->limit(10)->get();
        $s = ProgramSettings::all();
        return view('partner.dashboard', compact('m', 'stats', 'recent', 's'));
    }

    // ---------------------------------------------------------------- agent: team
    public function team(Request $request)
    {
        $m = $this->member($request);
        abort_unless($m->isAgent(), 403);
        $tree = $this->network->tree($m);
        $vendors = Seller::with(['user', 'workers.user'])->where('agent_member_id', $m->id)->get();
        return view('partner.team', compact('m', 'tree', 'vendors'));
    }

    /** IDs added in my downline on a given day (default today). */
    public function teamAdded(Request $request)
    {
        $m = $this->member($request);
        abort_unless($m->isAgent(), 403);
        $request->validate(['date' => 'nullable|date']);
        $day = $request->filled('date') ? \Carbon\Carbon::parse($request->input('date')) : now();
        $lists = $this->network->addedBetween($m, $day->copy()->startOfDay(), $day->copy()->endOfDay());
        $isToday = $day->isToday();
        return view('partner.team-added', compact('m', 'lists', 'day', 'isToday'));
    }

    // ---------------------------------------------------------------- ID requests
    public function requests(Request $request)
    {
        $m = $this->member($request);
        abort_unless($m->isAgent(), 403);
        $rows = NetworkRegistrationRequest::with(['seller.user', 'feePayment'])->where('requested_by_member_id', $m->id)->latest()->paginate(20);
        $myVendors = Seller::with('user:id,name')->where('agent_member_id', $m->id)->get();
        return view('partner.requests', compact('m', 'rows', 'myVendors'));
    }

    public function storeRequest(Request $request)
    {
        $m = $this->member($request);
        abort_unless($m->isAgent(), 403);
        $data = $request->validate([
            'type' => 'required|in:agent,vendor,worker',
            'name' => 'required|string|max:100',
            'email' => 'required|email|max:150',
            'mobile' => 'required|string|min:10|max:15',
            'seller_id' => 'required_if:type,worker|nullable|integer',
            'meta' => 'nullable|array',
            'meta.store_name' => 'required_if:type,vendor|nullable|string|max:150',
            'meta.address' => 'required_if:type,vendor|nullable|string|max:255',
            'meta.landmark' => 'nullable|string|max:100',
            'meta.city' => 'required_if:type,vendor|nullable|string|max:100',
            'meta.state' => 'required_if:type,vendor|nullable|string|max:100',
            'meta.zipcode' => 'required_if:type,vendor|nullable|string|max:20',
        ]);
        $req = $this->network->submitRequest($data['type'], $data, $m);
        if ($req->status === 'pending_payment' && $req->feePayment) {
            // the agent pays the joining fee first (full amount to SmartBazars — no commission is split from it)
            return redirect($req->feePayment->payUrl());
        }
        $msg = $data['type'] === 'worker'
            ? 'Request sent. The vendor and then SmartBazars admin will approve it.'
            : 'Request sent to SmartBazars admin for approval. Login details will be emailed after approval.';
        return back()->with('success', $msg);
    }

    // ---------------------------------------------------------------- worker: customers
    public function customers(Request $request)
    {
        $m = $this->member($request);
        abort_unless($m->isWorker(), 403);
        $rows = User::with('userCard')->where('referred_by_worker_id', $m->id)
            ->when($request->q, fn($q, $s) => $q->where(fn($w) => $w->where('name', 'like', "%$s%")->orWhere('mobile', 'like', "%$s%")))
            ->latest()->paginate(20)->withQueryString();
        return view('partner.customers', compact('m', 'rows'));
    }

    public function storeCustomer(Request $request)
    {
        $m = $this->member($request);
        abort_unless($m->isWorker(), 403);
        $data = $request->validate([
            'name' => 'required|string|max:100',
            'mobile' => 'required|string|min:10|max:15|unique:users,mobile',
            'email' => 'nullable|email|max:150|unique:users,email',
        ]);
        $password = 'Sb@' . Str::random(6);
        $user = User::create([
            'name' => $data['name'],
            'mobile' => $data['mobile'],
            // email is required by many flows; generate a placeholder the customer can change
            'email' => $data['email'] ?? ($data['mobile'] . '@customer.smartbazars.local'),
            'password' => $password,
            'status' => 'active',
            'access_panel' => 'web',
            'referral_code' => strtoupper(Str::random(8)),
            'referred_by_worker_id' => $m->id,
        ]);
        return back()->with('success', "Customer {$user->name} added. App login — mobile: {$user->mobile}, password: {$password}. "
            . "The customer becomes valid after buying the Welcome Kit with your code {$m->referral_code}.");
    }

    // ---------------------------------------------------------------- earnings & payouts
    public function earnings(Request $request)
    {
        $m = $this->member($request);
        $rows = NetworkCommission::where('network_member_id', $m->id)
            ->when($request->source, fn($q, $s) => $q->where('source', $s))
            ->latest()->paginate(25)->withQueryString();
        $withdrawals = NetworkWithdrawalRequest::where('network_member_id', $m->id)->latest()->limit(20)->get();
        $min = (int)ProgramSettings::get('min_withdrawal_amount', 100);
        return view('partner.earnings', compact('m', 'rows', 'withdrawals', 'min'));
    }

    public function requestWithdrawal(Request $request)
    {
        $m = $this->member($request);
        $min = (int)ProgramSettings::get('min_withdrawal_amount', 100);
        $data = $request->validate([
            'amount' => "required|numeric|min:$min",
            'method' => 'required|in:upi,bank',
            'upi_id' => 'required_if:method,upi|nullable|string|max:100',
            'account_name' => 'required_if:method,bank|nullable|string|max:100',
            'account_number' => 'required_if:method,bank|nullable|string|max:30',
            'ifsc' => 'required_if:method,bank|nullable|string|max:15',
        ]);
        DB::transaction(function () use ($m, $data) {
            $fresh = NetworkMember::whereKey($m->id)->lockForUpdate()->first();
            if ((float)$fresh->wallet_balance < (float)$data['amount']) {
                throw ValidationException::withMessages(['amount' => 'Amount is more than your balance.']);
            }
            // hold the amount until admin pays / rejects
            $fresh->update(['wallet_balance' => (float)$fresh->wallet_balance - (float)$data['amount']]);
            NetworkWithdrawalRequest::create([
                'network_member_id' => $m->id,
                'amount' => $data['amount'],
                'payout_details' => collect($data)->except('amount')->filter()->all(),
            ]);
        });
        return back()->with('success', 'Withdrawal request submitted.');
    }

    // ---------------------------------------------------------------- profile
    public function profile(Request $request)
    {
        $m = $this->member($request)->load(['user', 'parent.user', 'seller.user']);
        return view('partner.profile', compact('m'));
    }

    public function updatePassword(Request $request)
    {
        $data = $request->validate([
            'current_password' => 'required',
            'password' => 'required|string|min:8|confirmed',
        ]);
        $user = Auth::user();
        if (!Hash::check($data['current_password'], $user->password)) {
            return back()->with('error', 'Current password is wrong.');
        }
        $user->update(['password' => $data['password']]);
        return back()->with('success', 'Password changed.');
    }

    // ---------------------------------------------------------------- profile photo (shown on ID card, sidebar, header)
    public function photo()
    {
        $media = Auth::user()->getFirstMedia(\App\Enums\SpatieMediaCollectionName::PROFILE_IMAGE());
        abort_unless($media, 404);
        return response()->stream(function () use ($media) {
            fpassthru($media->stream());
        }, 200, ['Content-Type' => $media->mime_type ?: 'image/jpeg', 'Cache-Control' => 'private, max-age=86400']);
    }

    public function updatePhoto(Request $request)
    {
        $request->validate(
            ['photo' => 'required|image|mimes:jpg,jpeg,png,webp|max:6144'],
            ['photo.image' => 'Please choose a photo (JPG, PNG or WEBP).', 'photo.max' => 'The photo must be 6 MB or smaller.', 'photo.uploaded' => 'The photo could not be uploaded — it is larger than the server allows (raise upload_max_filesize / post_max_size).']
        );
        $user = Auth::user();
        $file = $request->file('photo');
        $path = \App\Support\SmartBazars\Photo::normalize($file->getRealPath());
        $normalized = $path !== $file->getRealPath();
        try {
            $user->addMedia($path)
                ->preservingOriginal()
                ->usingFileName('profile-' . $user->id . '-' . time() . '.' . ($normalized ? 'jpg' : $file->extension()))
                ->toMediaCollection(\App\Enums\SpatieMediaCollectionName::PROFILE_IMAGE());
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Partner photo upload failed: ' . $e->getMessage(), ['user_id' => $user->id]);
            $msg = 'Could not save the photo: ' . mb_substr($e->getMessage(), 0, 160);
            return $request->expectsJson() ? response()->json(['message' => $msg], 500) : back()->with('error', $msg);
        } finally {
            if ($normalized) @unlink($path);
        }
        return $request->expectsJson() ? response()->json(['ok' => true]) : back()->with('success', 'Photo updated — it now appears on your ID card.');
    }

    public function removePhoto()
    {
        Auth::user()->clearMediaCollection(\App\Enums\SpatieMediaCollectionName::PROFILE_IMAGE());
        return back()->with('success', 'Photo removed.');
    }

    // ---------------------------------------------------------------- ID card (v1.4)
    public function idCard(Request $request)
    {
        $data = app(\App\Services\SmartBazars\DocumentService::class)->memberData($this->member($request));
        return view('partner.id-card', $data);
    }

    public function idCardPdf(Request $request)
    {
        $m = $this->member($request);
        $pdf = app(\App\Services\SmartBazars\DocumentService::class)->idCardPdf($m);
        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="SmartBazars-ID-' . $m->referral_code . '.pdf"',
        ]);
    }

    // ---------------------------------------------------------------- daily report (v1.5)
    public function reports(Request $request)
    {
        $m = $this->member($request);
        $today = \App\Models\PartnerDailyReport::where('network_member_id', $m->id)->whereDate('report_date', today())->first();
        $rows = \App\Models\PartnerDailyReport::where('network_member_id', $m->id)->latest('report_date')->paginate(20);
        return view('partner.reports', compact('m', 'today', 'rows'));
    }

    public function saveReport(Request $request)
    {
        $m = $this->member($request);
        $data = $request->validate([
            'content' => 'required|string|min:10|max:5000',
            'customers_met' => 'nullable|integer|min:0|max:10000',
            'kits_sold' => 'nullable|integer|min:0|max:10000',
        ]);
        \App\Models\PartnerDailyReport::updateOrCreate(
            ['network_member_id' => $m->id, 'report_date' => today()->toDateString()],
            $data
        );
        return back()->with('success', "Today's report saved and shared with " . ($m->isWorker() ? 'your vendor and ' : '') . 'SmartBazars admin.');
    }
}
