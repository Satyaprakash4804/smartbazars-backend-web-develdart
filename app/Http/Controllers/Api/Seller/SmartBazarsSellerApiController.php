<?php

namespace App\Http\Controllers\Api\Seller;

use App\Http\Controllers\Controller;
use App\Models\NetworkMember;
use App\Models\NetworkRegistrationRequest;
use App\Services\SmartBazars\NetworkService;
use App\Services\SmartBazars\ReferralService;
use App\Types\Api\ApiResponseType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/** Vendor app: manage downline workers (SmartBazars v1.5). */
class SmartBazarsSellerApiController extends Controller
{
    public function __construct(protected NetworkService $network) {}

    protected function seller(Request $request)
    {
        $seller = $request->user()->seller();
        abort_unless($seller, 403, 'Not a vendor account');
        return $seller;
    }

    /** GET /api/seller/smartbazars/workers */
    public function workers(Request $request): JsonResponse
    {
        $seller = $this->seller($request);
        $workers = NetworkMember::with('user:id,name,email,mobile')->withCount('customers')
            ->where('type', 'worker')->where('seller_id', $seller->id)->latest()->get()
            ->map(fn($w) => [
                'id' => $w->id,
                'name' => $w->user->name ?? '',
                'email' => $w->user->email ?? '',
                'mobile' => $w->user->mobile ?? '',
                'referral_code' => $w->referral_code,
                'status' => $w->status,
                'customers' => $w->customers_count,
                'total_earned' => (float)$w->total_earned,
            ]);
        $requests = NetworkRegistrationRequest::with('feePayment')->where('type', 'worker')->where('seller_id', $seller->id)
            ->whereIn('status', ['pending_payment', 'pending_seller', 'pending_admin', 'rejected'])->latest()->limit(50)->get()
            ->map(fn($r) => [
                'id' => $r->id,
                'fee_amount' => $r->fee_amount !== null ? (float)$r->fee_amount : null,
                'payment_url' => $r->status === 'pending_payment' ? $r->feePayment?->payUrl() : null,
                'name' => $r->name,
                'email' => $r->email,
                'mobile' => $r->mobile,
                'status' => $r->status,
                'needs_my_approval' => $r->status === 'pending_seller',
                'reason' => $r->rejection_reason,
                'created_at' => $r->created_at?->toIso8601String(),
            ]);
        return ApiResponseType::sendJsonResponse(true, 'BJOs', [
            'vendor_code' => $seller->ensureReferralCode(),
            'qr_link' => app(ReferralService::class)->referralLink($seller->referral_code),
            'coin_balance' => (int)$seller->coin_balance,
            'workers' => $workers,
            'requests' => $requests,
        ]);
    }

    /** POST /api/seller/smartbazars/workers {name,email,mobile} -> request (admin approves, password emailed) */
    public function createWorker(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:100',
            'email' => 'required|email|max:150',
            'mobile' => 'required|string|min:10|max:15',
        ]);
        try {
            $req = $this->network->submitRequest('worker', $data, $this->seller($request));
        } catch (ValidationException $e) {
            return ApiResponseType::sendJsonResponse(false, collect($e->errors())->flatten()->first(), $e->errors(), 422, true);
        }
        if ($req->status === 'pending_payment' && $req->feePayment) {
            // joining fee must be paid first (full amount to SmartBazars — no commission split)
            return ApiResponseType::sendJsonResponse(true, 'Pay the joining fee of ₹' . number_format((float)$req->fee_amount, 2) . ' to send this worker for approval.', [
                'request_id' => $req->id,
                'payment_required' => true,
                'fee_amount' => (float)$req->fee_amount,
                'payment_url' => $req->feePayment->payUrl(),
            ]);
        }
        return ApiResponseType::sendJsonResponse(true, 'BJO request sent to SmartBazars admin for approval. Login details will be emailed to the worker.', ['request_id' => $req->id, 'payment_required' => false]);
    }

    /** POST /api/seller/smartbazars/workers/requests/{id} {decision: approve|reject, reason?} */
    public function decide(Request $request, int $id): JsonResponse
    {
        $req = NetworkRegistrationRequest::findOrFail($id);
        try {
            $this->network->sellerDecision($req, $this->seller($request), $request->input('decision') === 'approve', $request->input('reason'));
        } catch (ValidationException $e) {
            return ApiResponseType::sendJsonResponse(false, collect($e->errors())->flatten()->first(), null, 422);
        }
        return ApiResponseType::sendJsonResponse(true, $request->input('decision') === 'approve' ? 'Approved — waiting for admin approval.' : 'Request rejected.');
    }

    /** POST /api/seller/smartbazars/workers/{id}/toggle */
    public function toggle(Request $request, int $id): JsonResponse
    {
        $w = NetworkMember::where('type', 'worker')->where('seller_id', $this->seller($request)->id)->findOrFail($id);
        $w->update(['status' => $w->status === 'active' ? 'suspended' : 'active']);
        return ApiResponseType::sendJsonResponse(true, 'BJO ' . $w->status . '.', ['status' => $w->status]);
    }

    // ---------------------------------------------------------------- bulk purchase (v1.5.1)
    /** GET /api/seller/smartbazars/bulk — products vendors can buy from SmartBazars */
    public function bulkCatalog(Request $request): JsonResponse
    {
        $seller = $this->seller($request)->fresh();
        $value = app(\App\Services\SmartBazars\VendorCoinService::class)->coinValue();
        $items = \App\Models\WholesaleProduct::with(['storeVariant', 'product'])->where('is_active', true)
            ->orderByRaw('store_product_variant_id IS NULL')->orderBy('sort')->orderBy('title')->get()
            ->map(fn($p) => [
                'id' => $p->id,
                'title' => $p->title,
                'unit' => $p->unit,
                'description' => $p->description,
                'image' => $p->image_url,
                'mrp' => (float)$p->mrp,
                'vendor_price' => (float)$p->vendor_price,
                'min_qty' => (int)$p->min_qty,
                'max_qty' => $p->max_qty ? (int)$p->max_qty : null,
                'stock' => $p->available_stock,
                'max_coin_percent' => (int)$p->max_coin_percent,
                'is_smartbazars' => $p->is_smartbazars,
            ]);
        return ApiResponseType::sendJsonResponse(true, 'Bulk products', [
            'coin_balance' => (int)$seller->coin_balance,
            'coin_value' => $value,
            'products' => $items,
        ]);
    }

    /** POST /api/seller/smartbazars/bulk {items:{id:qty}, use_coins, payment_method: settlement|offline, note} */
    public function bulkPlace(Request $request): JsonResponse
    {
        $data = $request->validate([
            'items' => 'required|array',
            'items.*' => 'nullable|integer|min:0',
            'use_coins' => 'nullable|integer|min:0',
            'payment_method' => 'required|in:settlement,offline',
            'note' => 'nullable|string|max:255',
        ]);
        try {
            $order = app(\App\Services\SmartBazars\WholesaleService::class)->placeOrder(
                $this->seller($request), $data['items'], (int)($data['use_coins'] ?? 0), $data['payment_method'], $data['note'] ?? null);
        } catch (ValidationException $e) {
            return ApiResponseType::sendJsonResponse(false, collect($e->errors())->flatten()->first(), $e->errors(), 422, true);
        }
        return ApiResponseType::sendJsonResponse(true, "Bulk order #{$order->id} placed", $this->orderJson($order->load('items')));
    }

    /** GET /api/seller/smartbazars/bulk/orders */
    public function bulkOrders(Request $request): JsonResponse
    {
        $orders = \App\Models\WholesaleOrder::with('items')->where('seller_id', $this->seller($request)->id)->latest()->limit(50)->get();
        return ApiResponseType::sendJsonResponse(true, 'Bulk orders', $orders->map(fn($o) => $this->orderJson($o)));
    }

    protected function orderJson($o): array
    {
        return [
            'id' => $o->id,
            'status' => $o->status,
            'subtotal' => (float)$o->subtotal,
            'coins_used' => (int)$o->coins_used,
            'coin_value' => (float)$o->coin_value,
            'amount_payable' => (float)$o->amount_payable,
            'payment_method' => $o->payment_method,
            'created_at' => $o->created_at?->toIso8601String(),
            'items' => $o->items->map(fn($i) => ['title' => $i->title, 'quantity' => $i->quantity, 'price' => (float)$i->price, 'line_total' => (float)$i->line_total])->values(),
        ];
    }

    // ---------------------------------------------------------------- KYC (first login)
    /** GET /api/seller/smartbazars/kyc — what is still missing (empty = KYC complete) */
    public function kycStatus(Request $request): JsonResponse
    {
        $seller = $this->seller($request);
        if ($seller->is_house_store) {
            return ApiResponseType::sendJsonResponse(true, 'KYC', ['complete' => true, 'missing' => [], 'values' => [], 'documents' => []]);
        }
        return ApiResponseType::sendJsonResponse(true, 'KYC', app(\App\Services\SmartBazars\KycService::class)->sellerStatus($seller));
    }

    /** POST /api/seller/smartbazars/kyc (multipart) — submits only the missing details / documents */
    public function kycSubmit(Request $request): JsonResponse
    {
        $seller = $this->seller($request);
        $kyc = app(\App\Services\SmartBazars\KycService::class);
        $missing = $kyc->sellerMissing($seller);
        if (!$missing) {
            return ApiResponseType::sendJsonResponse(true, 'KYC already complete.', $kyc->sellerStatus($seller));
        }
        try {
            $data = $request->validate($kyc->sellerRules($seller, $missing), ['*.uploaded' => 'The file could not be uploaded — it may be too large (max 2 MB).']);
        } catch (ValidationException $e) {
            return ApiResponseType::sendJsonResponse(false, collect($e->errors())->flatten()->first(), $e->errors(), 422, true);
        }
        $kyc->saveSeller($seller, $data, $request->allFiles());
        return ApiResponseType::sendJsonResponse(true, 'KYC completed. Thank you!', $kyc->sellerStatus($seller->fresh()));
    }
}
