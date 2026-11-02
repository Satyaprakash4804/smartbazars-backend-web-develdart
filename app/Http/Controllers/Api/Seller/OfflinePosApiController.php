<?php

namespace App\Http\Controllers\Api\Seller;

use App\Http\Controllers\Controller;
use App\Models\OfflineCustomerProfile;
use App\Models\OfflineOrder;
use App\Services\SmartBazars\OfflineCardService;
use App\Services\SmartBazars\OfflinePosService;
use App\Types\Api\ApiResponseType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Vendor POS for the seller app (token auth): same services as the web panel.
 * Base: /api/seller/smartbazars/pos
 */
class OfflinePosApiController extends Controller
{
    public function __construct(protected OfflinePosService $pos, protected OfflineCardService $cards) {}

    protected function seller(Request $request)
    {
        $seller = $request->user()->seller();
        abort_unless($seller, 403, 'Not a vendor account');
        return $seller;
    }

    protected function orderJson(OfflineOrder $o, bool $withItems = false): array
    {
        $o->loadMissing(['customer', 'card', 'member.user']);
        $row = [
            'id' => $o->id,
            'order_no' => $o->order_no,
            'status' => $o->status,
            'status_label' => $o->statusLabel(),
            'channel' => $o->channel,
            'customer' => ['name' => $o->customer?->name, 'mobile' => $o->customer?->mobile, 'card' => $o->card?->formatted_number, 'card_number' => $o->card?->card_number],
            'bjo' => $o->member?->user?->name,
            'subtotal' => (float)$o->subtotal,
            'kit_amount' => (float)$o->kit_amount,
            'delivery_charge' => 0.0,
            'coins_used' => (int)$o->coins_used,
            'coin_discount' => (float)$o->coin_discount,
            'total' => (float)$o->total,
            'payment_mode' => $o->payment_mode,
            'coins_earned' => (int)$o->coins_earned,
            'note' => $o->note,
            'created_at' => $o->created_at?->toIso8601String(),
            'handed_over_at' => $o->handed_over_at?->toIso8601String(),
        ];
        if ($withItems) {
            $row['items'] = $o->items->map(fn($i) => [
                'title' => $i->title, 'qty' => (int)$i->quantity, 'unit_price' => (float)$i->unit_price,
                'subtotal' => (float)$i->subtotal, 'is_welcome_kit' => (bool)$i->is_welcome_kit,
            ])->values();
        }
        return $row;
    }

    /** GET summary — today's numbers + pickups waiting */
    public function summary(Request $request): JsonResponse
    {
        $seller = $this->seller($request);
        $q = OfflineOrder::where('seller_id', $seller->id);
        $today = now()->startOfDay();
        return ApiResponseType::sendJsonResponse(true, 'Summary', [
            'sales_today' => (clone $q)->where('status', '!=', 'cancelled')->where('created_at', '>=', $today)->count(),
            'amount_today' => (float)(clone $q)->where('status', '!=', 'cancelled')->where('created_at', '>=', $today)->sum('total'),
            'amount_month' => (float)(clone $q)->where('status', '!=', 'cancelled')->where('created_at', '>=', now()->startOfMonth())->sum('total'),
            'pending_pickups' => (clone $q)->where('status', 'pending_pickup')->count(),
            'cards_total' => OfflineCustomerProfile::where('seller_id', $seller->id)->count(),
        ]);
    }

    /** GET lookup?q= — card number / QR payload / mobile */
    public function lookup(Request $request): JsonResponse
    {
        $this->seller($request);
        $card = $this->pos->findCard((string)$request->query('q'));
        if (!$card) {
            return ApiResponseType::sendJsonResponse(false, 'No User Card found for this number.', []);
        }
        $s = $this->pos->customerSummary($card);
        $p = $s['profile'];
        return ApiResponseType::sendJsonResponse(true, 'Customer', [
            'card_number' => $card->card_number,
            'formatted' => $card->formatted_number,
            'name' => $s['user']->name,
            'mobile' => $s['user']->mobile,
            'email' => $s['user']->email,
            'valid' => (bool)$s['valid'],
            'valid_upto' => $card->valid_upto?->format('d M Y'),
            'coins' => (int)$s['coins'],
            'coin_value' => (float)$s['coin_value'],
            'kit' => $s['kit'],
            'location' => $p ? trim($p->ward_panchayat . ', ' . $p->block . ', ' . $p->district . ', ' . $p->state) : null,
            'totals' => $s['totals'],
            'upline' => $s['trail'],
            'dates' => $s['dates'],
            'recent_orders' => $s['offline_orders']->take(10)->map(fn($o) => $this->orderJson($o))->values(),
        ]);
    }

    /** GET products?q= */
    public function products(Request $request): JsonResponse
    {
        $seller = $this->seller($request);
        return ApiResponseType::sendJsonResponse(true, 'Products', $this->pos->searchProducts($seller, trim((string)$request->query('q')), 30));
    }

    /** POST sell {card, items[{id,qty}], coins?, payment_mode?, note?, welcome_kit?} */
    public function sell(Request $request): JsonResponse
    {
        $seller = $this->seller($request);
        $request->validate([
            'card' => 'required|string|max:40',
            'items' => 'required_without:welcome_kit|array',
            'items.*.id' => 'required|integer',
            'items.*.qty' => 'required|integer|min:1|max:10000',
            'coins' => 'nullable|integer|min:0',
            'payment_mode' => 'nullable|in:cash,upi,card',
            'note' => 'nullable|string|max:255',
            'welcome_kit' => 'nullable|boolean',
        ]);
        $card = $this->pos->findCard($request->card);
        if (!$card) {
            return ApiResponseType::sendJsonResponse(false, 'User Card not found.', []);
        }
        $order = $this->pos->placeCounterSale($seller, $card, $request->input('items', []),
            $request->only(['coins', 'payment_mode', 'note', 'welcome_kit']), $request->user()->id);
        return ApiResponseType::sendJsonResponse(true, "Sale {$order->order_no} recorded.", $this->orderJson($order, true));
    }

    /** GET orders?status=&q=&page= */
    public function orders(Request $request): JsonResponse
    {
        $seller = $this->seller($request);
        $rows = OfflineOrder::with(['customer', 'card', 'member.user'])->where('seller_id', $seller->id)
            ->when($request->status, fn($q, $s) => $q->where('status', $s))
            ->when($request->q, fn($q, $s) => $q->where('order_no', 'like', "%$s%")
                ->orWhereHas('customer', fn($u) => $u->where('name', 'like', "%$s%")->orWhere('mobile', 'like', "%$s%")))
            ->latest()->paginate(20);
        return ApiResponseType::sendJsonResponse(true, 'Orders', [
            'orders' => collect($rows->items())->map(fn($o) => $this->orderJson($o))->values(),
            'current_page' => $rows->currentPage(), 'last_page' => $rows->lastPage(), 'total' => $rows->total(),
        ]);
    }

    public function order(Request $request, int $id): JsonResponse
    {
        $seller = $this->seller($request);
        $o = OfflineOrder::with('items')->where('seller_id', $seller->id)->findOrFail($id);
        return ApiResponseType::sendJsonResponse(true, 'Order', $this->orderJson($o, true));
    }

    public function handover(Request $request, int $id): JsonResponse
    {
        $seller = $this->seller($request);
        $o = OfflineOrder::where('seller_id', $seller->id)->findOrFail($id);
        $o = $this->pos->handOver($o, $request->user()->id);
        return ApiResponseType::sendJsonResponse(true, "Order {$o->order_no} handed over.", $this->orderJson($o->load('items'), true));
    }

    public function cancel(Request $request, int $id): JsonResponse
    {
        $seller = $this->seller($request);
        $data = $request->validate(['reason' => 'required|string|max:200']);
        $o = OfflineOrder::where('seller_id', $seller->id)->findOrFail($id);
        $o = $this->pos->cancel($o, $data['reason']);
        return ApiResponseType::sendJsonResponse(true, "Order {$o->order_no} cancelled.", $this->orderJson($o->load('items'), true));
    }

    // ------------------------------------------------------------------ cards

    protected function profileJson(OfflineCustomerProfile $p): array
    {
        $p->loadMissing(['user', 'card']);
        return [
            'id' => $p->id,
            'name' => $p->user->name, 'mobile' => $p->user->mobile, 'email' => $p->user->email,
            'card_number' => $p->card?->card_number, 'formatted' => $p->card?->formatted_number,
            'valid_upto' => $p->card?->valid_upto?->format('d M Y'),
            'location' => trim($p->ward_panchayat . ', ' . $p->block . ', ' . $p->district . ', ' . $p->state),
            'emailed' => (bool)$p->card_emailed_at,
            'share_url' => route('offline.card.public', $p->share_token),
            'pdf_url' => route('offline.card.public.pdf', $p->share_token),
            'whatsapp_url' => $this->cards->whatsappLink($p),
            'created_at' => $p->created_at?->toIso8601String(),
        ];
    }

    public function cards(Request $request): JsonResponse
    {
        $seller = $this->seller($request);
        $rows = OfflineCustomerProfile::with(['user', 'card'])->where('seller_id', $seller->id)
            ->when($request->q, fn($q, $s) => $q->whereHas('user', fn($u) => $u->where('name', 'like', "%$s%")->orWhere('mobile', 'like', "%$s%"))
                ->orWhereHas('card', fn($k) => $k->where('card_number', 'like', '%' . preg_replace('/\s+/', '', $s) . '%')))
            ->latest()->paginate(20);
        return ApiResponseType::sendJsonResponse(true, 'Cards', [
            'cards' => collect($rows->items())->map(fn($p) => $this->profileJson($p))->values(),
            'current_page' => $rows->currentPage(), 'last_page' => $rows->lastPage(),
        ]);
    }

    /** POST cards (multipart): name, mobile, email, ward_panchayat, block, district, state, [address_line, pincode, aadhaar_last4], photo, aadhaar */
    public function createCard(Request $request): JsonResponse
    {
        $seller = $this->seller($request);
        $data = $request->validate([
            'name' => 'required|string|max:100',
            'mobile' => 'required|string|min:10|max:15',
            'email' => 'required|email|max:150',
            'address_line' => 'nullable|string|max:255',
            'ward_panchayat' => 'required|string|max:120',
            'block' => 'required|string|max:120',
            'district' => 'required|string|max:120',
            'state' => 'required|string|max:120',
            'pincode' => 'nullable|digits:6',
            'aadhaar_last4' => 'nullable|digits:4',
            'photo' => 'required|image|mimes:jpg,jpeg,png,webp|max:5120',
            'aadhaar' => 'required|file|mimes:jpg,jpeg,png,webp,pdf|max:6144',
        ]);
        $profile = $this->cards->create($data, $request->file('photo'), $request->file('aadhaar'),
            ['type' => 'seller', 'id' => $seller->id, 'seller_id' => $seller->id, 'member_id' => null, 'user_id' => $request->user()->id]);
        return ApiResponseType::sendJsonResponse(true, 'User Card ' . $profile->card->formatted_number . ' created.', $this->profileJson($profile));
    }

    public function resendCard(Request $request, int $id): JsonResponse
    {
        $seller = $this->seller($request);
        $p = OfflineCustomerProfile::where('seller_id', $seller->id)->findOrFail($id);
        $ok = $this->cards->email($p);
        return ApiResponseType::sendJsonResponse($ok, $ok ? 'Card emailed.' : 'Could not send the email.', $this->profileJson($p));
    }
}
