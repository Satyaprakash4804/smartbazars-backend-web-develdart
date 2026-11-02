<?php

namespace App\Http\Controllers\Admin\SmartBazars;

use App\Http\Controllers\Controller;
use App\Models\Store;
use App\Services\SmartBazars\StoreVerificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/** Admin queue for verifying vendor stores: Pending → Approve (store goes live) or Reject with a reason. */
class StoreReviewController extends Controller
{
    public function __construct(protected StoreVerificationService $verify) {}

    protected function scoped(string $state)
    {
        $q = Store::with(['seller.user:id,name,email,mobile']);
        return match ($state) {
            'approved' => $q->where('verification_status', 'approved'),
            'rejected' => $q->where('verification_status', '!=', 'approved')->whereNotNull('rejection_reason')->where('rejection_reason', '!=', ''),
            'all' => $q,
            default => $q->where('verification_status', '!=', 'approved')->where(fn($w) => $w->whereNull('rejection_reason')->orWhere('rejection_reason', '')),
        };
    }

    public function index(Request $request)
    {
        $state = in_array($request->query('state'), ['pending', 'rejected', 'approved', 'all'], true) ? $request->query('state') : 'pending';
        $stores = $this->scoped($state)->latest()->paginate(20)->withQueryString();
        $counts = [];
        foreach (['pending', 'rejected', 'approved', 'all'] as $s) {
            $counts[$s] = $this->scoped($s)->count();
        }
        return view('admin.smartbazars.stores.index', compact('stores', 'state', 'counts'));
    }

    public function show(Store $store)
    {
        $store->load(['seller.user', 'zones']);
        return view('admin.smartbazars.stores.show', [
            'store' => $store,
            'checklist' => $this->verify->checklist($store),
            'blockers' => $this->verify->blockers($store),
        ]);
    }

    public function approve(Store $store)
    {
        try {
            $this->verify->approve($store, Auth::id());
        } catch (ValidationException $e) {
            return back()->with('error', $e->validator->errors()->first());
        }
        return redirect()->route('admin.smartbazars.store-review')->with('success', "Store \"{$store->name}\" approved — it is now visible to customers and the vendor was notified.");
    }

    public function reject(Request $request, Store $store)
    {
        $request->validate(['reason' => 'required|string|min:3|max:500'], ['reason.required' => 'Please give a reason — the vendor sees it.']);
        $this->verify->reject($store, Auth::id(), $request->input('reason'));
        return redirect()->route('admin.smartbazars.store-review')->with('success', "Store \"{$store->name}\" rejected. The vendor can fix it and resubmit.");
    }
}
