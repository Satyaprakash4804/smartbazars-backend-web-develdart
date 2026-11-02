<?php

namespace App\Http\Controllers\Offline;

use App\Http\Controllers\Controller;
use App\Models\NetworkMember;
use App\Models\OfflineCustomerProfile;
use App\Models\OfflineOrder;
use App\Models\Seller;
use App\Models\UserCard;
use App\Services\SmartBazars\OfflineCardService;
use App\Services\SmartBazars\OfflinePosService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * One controller for the three panels that work with offline customers:
 *   seller  (vendor)  /seller/offline/...        routes: seller.offline.*
 *   worker  (BJO)     /partner/offline/...       routes: partner.offline.*
 *   admin             /admin/smartbazars/offline routes: admin.smartbazars.offline.*
 * The panel is passed as a route default: ->defaults('who', 'seller|worker|admin').
 */
class OfflineController extends Controller
{
    public function __construct(protected OfflineCardService $cards, protected OfflinePosService $pos) {}

    // ------------------------------------------------------------------ context

    protected function ctx(Request $request): array
    {
        $who = $request->route('who');
        abort_unless(in_array($who, ['seller', 'worker', 'admin'], true), 404);

        $seller = $member = null;
        if ($who === 'seller') {
            $seller = Auth::user()->seller();
            abort_unless($seller, 403);
        } elseif ($who === 'worker') {
            $member = $request->attributes->get('member');
            abort_unless($member && $member->isWorker() && $member->isActive(), 403);
            $seller = $member->seller_id ? Seller::find($member->seller_id) : null;
        } else {
            abort_unless(Auth::check(), 403);
        }

        return [
            'who' => $who,
            'seller' => $seller,
            'member' => $member,
            'rp' => ['seller' => 'seller.offline.', 'worker' => 'partner.offline.', 'admin' => 'admin.smartbazars.offline.'][$who],
            'canCreate' => $who !== 'admin',
            'canSell' => $who !== 'admin',
        ];
    }

    protected function view(string $view, array $ctx, array $data = [])
    {
        return view($view, array_merge($ctx, $data));
    }

    protected function ordersQuery(array $c)
    {
        $q = OfflineOrder::with(['customer', 'card', 'seller', 'member.user', 'items']);
        return match ($c['who']) {
            'seller' => $q->where('seller_id', $c['seller']->id),
            'worker' => $q->where('network_member_id', $c['member']->id),
            default => $q,
        };
    }

    protected function profilesQuery(array $c)
    {
        $q = OfflineCustomerProfile::with(['user', 'card', 'seller', 'member.user']);
        return match ($c['who']) {
            'seller' => $q->where('seller_id', $c['seller']->id),
            'worker' => $q->where('network_member_id', $c['member']->id),
            default => $q,
        };
    }

    // ------------------------------------------------------------------ hub

    public function home(Request $request)
    {
        $c = $this->ctx($request);
        $today = now()->startOfDay();
        $orders = $this->ordersQuery($c);
        $stats = [
            'cards_total' => $this->profilesQuery($c)->count(),
            'cards_today' => $this->profilesQuery($c)->where('created_at', '>=', $today)->count(),
            'sales_today' => (clone $orders)->where('status', '!=', 'cancelled')->where('created_at', '>=', $today)->count(),
            'amount_today' => (float)(clone $orders)->where('status', '!=', 'cancelled')->where('created_at', '>=', $today)->sum('total'),
            'amount_month' => (float)(clone $orders)->where('status', '!=', 'cancelled')->where('created_at', '>=', now()->startOfMonth())->sum('total'),
            'pending_pickups' => (clone $orders)->where('status', 'pending_pickup')->count(),
            'coins_issued' => (int)(clone $orders)->where('coins_credited', true)->where('status', '!=', 'cancelled')->sum('coins_earned'),
            'coins_redeemed' => (int)(clone $orders)->where('status', '!=', 'cancelled')->sum('coins_used'),
        ];
        $recent = (clone $orders)->latest()->limit(8)->get();
        $wallet = $c['member']?->order_wallet_balance;
        return $this->view('offline.home', $c, compact('stats', 'recent', 'wallet'));
    }

    // ------------------------------------------------------------------ cards

    public function cards(Request $request)
    {
        $c = $this->ctx($request);
        $rows = $this->profilesQuery($c)
            ->when($request->q, function ($q, $s) {
                $q->whereHas('user', fn($u) => $u->where('name', 'like', "%$s%")->orWhere('mobile', 'like', "%$s%")->orWhere('email', 'like', "%$s%"))
                    ->orWhereHas('card', fn($k) => $k->where('card_number', 'like', '%' . preg_replace('/\s+/', '', $s) . '%'));
            })
            ->when($request->district, fn($q, $d) => $q->where('district', 'like', "%$d%"))
            ->when($c['who'] === 'admin' && $request->seller_id, fn($q, $id) => $q->where('seller_id', $id))
            ->latest()->paginate(20)->withQueryString();
        return $this->view('offline.cards', $c, compact('rows'));
    }

    public function create(Request $request)
    {
        $c = $this->ctx($request);
        abort_unless($c['canCreate'], 403);
        return $this->view('offline.card-form', $c);
    }

    public function store(Request $request)
    {
        $c = $this->ctx($request);
        abort_unless($c['canCreate'], 403);
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
        ], [
            'photo.required' => 'Please take or upload the customer photo.',
            'aadhaar.required' => 'Please upload the Aadhaar card (image or PDF).',
        ]);

        $creator = match ($c['who']) {
            'seller' => ['type' => 'seller', 'id' => $c['seller']->id, 'seller_id' => $c['seller']->id, 'member_id' => null, 'user_id' => Auth::id()],
            default => ['type' => 'worker', 'id' => $c['member']->id, 'seller_id' => $c['member']->seller_id, 'member_id' => $c['member']->id, 'user_id' => Auth::id()],
        };

        $profile = $this->cards->create($data, $request->file('photo'), $request->file('aadhaar'), $creator);
        $msg = 'User Card ' . $profile->card->formatted_number . ' created for ' . $profile->user->name . '.'
            . ($profile->card_emailed_at ? ' The card was emailed to ' . $profile->user->email . '.' : ' Email could not be sent — use "Resend email".');
        return redirect()->route($c['rp'] . 'cards.show', $profile)->with('success', $msg);
    }

    public function show(Request $request, OfflineCustomerProfile $profile)
    {
        $c = $this->ctx($request);
        $this->authorizeProfile($c, $profile);
        $card = $this->cards->cardData($profile);
        $wa = $this->cards->whatsappLink($profile);
        $summary = $this->pos->customerSummary($profile->card ?: UserCard::where('user_id', $profile->user_id)->firstOrFail());
        return $this->view('offline.card-show', $c, compact('profile', 'card', 'wa', 'summary'));
    }

    public function pdf(Request $request, OfflineCustomerProfile $profile)
    {
        $c = $this->ctx($request);
        $this->authorizeProfile($c, $profile);
        return response($this->cards->pdf($profile), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="SmartBazars-User-Card-' . $profile->card->card_number . '.pdf"',
        ]);
    }

    public function resend(Request $request, OfflineCustomerProfile $profile)
    {
        $c = $this->ctx($request);
        $this->authorizeProfile($c, $profile);
        $ok = $this->cards->email($profile);
        return back()->with($ok ? 'success' : 'error', $ok ? 'Card emailed to ' . $profile->user->email . '.' : 'Could not send the email. Check the mail settings.');
    }

    /** Private files: photo for everyone who may see the profile, Aadhaar for admin and the owning vendor only. */
    public function file(Request $request, OfflineCustomerProfile $profile, string $kind)
    {
        $c = $this->ctx($request);
        $this->authorizeProfile($c, $profile);
        abort_unless(in_array($kind, ['photo', 'aadhaar'], true), 404);
        if ($kind === 'aadhaar') {
            abort_unless(in_array($c['who'], ['admin', 'seller'], true), 403);
        }
        $path = $kind === 'photo' ? $profile->photo_file : $profile->aadhaar_file;
        abort_unless($path && Storage::disk(OfflineCardService::DISK)->exists($path), 404);
        return Storage::disk(OfflineCardService::DISK)->response($path);
    }

    protected function authorizeProfile(array $c, OfflineCustomerProfile $profile): void
    {
        abort_unless($this->cards->visibleTo($profile, $c['who'], $c['seller'] ?? null, $c['member'] ?? null), 403);
    }

    // ------------------------------------------------------------------ lookup (card number / QR)

    public function lookup(Request $request)
    {
        $c = $this->ctx($request);
        $summary = null;
        $notFound = false;
        if ($q = trim((string)$request->q)) {
            $card = $this->pos->findCard($q);
            if ($card) {
                $summary = $this->pos->customerSummary($card);
                Log::info('Offline lookup', ['by' => Auth::id(), 'who' => $c['who'], 'card' => $card->card_number]);
            } else {
                $notFound = true;
            }
        }
        return $this->view('offline.lookup', $c, compact('summary', 'notFound'));
    }

    public function lookupJson(Request $request)
    {
        $c = $this->ctx($request);
        $card = $this->pos->findCard((string)$request->q);
        if (!$card) {
            return response()->json(['ok' => false, 'message' => 'No User Card found for this number.'], 404);
        }
        $s = $this->pos->customerSummary($card);
        $photo = $s['profile'] ? route($c['rp'] . 'cards.file', [$s['profile'], 'photo']) : null;
        return response()->json([
            'ok' => true,
            'card_number' => $card->card_number,
            'formatted' => $card->formatted_number,
            'name' => $s['user']->name,
            'mobile' => $s['user']->mobile,
            'valid' => $s['valid'],
            'coins' => $s['coins'],
            'coin_value' => $s['coin_value'],
            'kit' => $s['kit'],
            'orders_count' => $s['totals']['offline_orders'],
            'photo' => $c['who'] === 'worker' && $s['profile'] && !$this->cards->visibleTo($s['profile'], 'worker', null, $c['member']) ? null : $photo,
            'location' => $s['profile'] ? trim($s['profile']->ward_panchayat . ', ' . $s['profile']->district, ', ') : null,
        ]);
    }

    // ------------------------------------------------------------------ POS (vendor counter + BJO order)

    public function pos(Request $request)
    {
        $c = $this->ctx($request);
        abort_unless($c['canSell'] && $c['seller'], 403);
        $wallet = $c['member']?->order_wallet_balance;
        return $this->view('offline.pos', $c, compact('wallet'));
    }

    public function products(Request $request)
    {
        $c = $this->ctx($request);
        abort_unless($c['seller'], 403);
        return response()->json($this->pos->searchProducts($c['seller'], trim((string)$request->q)));
    }

    public function sell(Request $request)
    {
        $c = $this->ctx($request);
        abort_unless($c['canSell'] && $c['seller'], 403);
        $request->validate([
            'card' => 'required|string|max:40',
            'items' => 'required_without:welcome_kit|array',
            'items.*.id' => 'required|integer',
            'items.*.qty' => 'required|integer|min:1|max:10000',
            'welcome_kit' => 'nullable|boolean',
            'coins' => 'nullable|integer|min:0',
            'use_all_coins' => 'nullable|boolean',
            'payment_mode' => 'nullable|in:cash,upi,card',
            'note' => 'nullable|string|max:255',
        ]);
        $card = $this->pos->findCard($request->card);
        if (!$card) {
            return response()->json(['ok' => false, 'message' => 'User Card not found.'], 422);
        }
        $pay = $request->only(['coins', 'use_all_coins', 'payment_mode', 'note', 'welcome_kit']);

        $items = $request->input('items', []);
        $order = $c['who'] === 'worker'
            ? $this->pos->placeWorkerOrder($c['member'], $card, $items, $pay)
            : $this->pos->placeCounterSale($c['seller'], $card, $items, $pay, Auth::id());

        return response()->json([
            'ok' => true,
            'order_no' => $order->order_no,
            'status' => $order->status,
            'total' => (float)$order->total,
            'coins_used' => $order->coins_used,
            'coins_earned' => $order->coins_earned,
            'kit_sold' => (float)$order->kit_amount > 0,
            'url' => route($c['rp'] . 'orders.show', $order),
            'message' => $order->channel === 'worker'
                ? "Order {$order->order_no} placed. The customer can collect it from {$order->seller->displayName()}."
                : "Sale {$order->order_no} recorded.",
        ]);
    }

    // ------------------------------------------------------------------ orders

    public function orders(Request $request)
    {
        $c = $this->ctx($request);
        $rows = $this->ordersQuery($c)
            ->when($request->status, fn($q, $s) => $q->where('status', $s))
            ->when($request->channel, fn($q, $s) => $q->where('channel', $s))
            ->when($request->from, fn($q, $d) => $q->whereDate('created_at', '>=', $d))
            ->when($request->to, fn($q, $d) => $q->whereDate('created_at', '<=', $d))
            ->when($c['who'] === 'admin' && $request->seller_id, fn($q, $id) => $q->where('seller_id', $id))
            ->when($request->q, function ($q, $s) {
                $q->where(function ($w) use ($s) {
                    $w->where('order_no', 'like', "%$s%")
                        ->orWhereHas('customer', fn($u) => $u->where('name', 'like', "%$s%")->orWhere('mobile', 'like', "%$s%"))
                        ->orWhereHas('card', fn($k) => $k->where('card_number', 'like', '%' . preg_replace('/\s+/', '', $s) . '%'));
                });
            })
            ->latest()->paginate(20)->withQueryString();
        $totals = [
            'count' => $rows->total(),
            'amount' => (float)$this->ordersQuery($c)->where('status', '!=', 'cancelled')->sum('total'),
        ];
        return $this->view('offline.orders', $c, compact('rows', 'totals'));
    }

    public function showOrder(Request $request, OfflineOrder $order)
    {
        $c = $this->ctx($request);
        $this->authorizeOrder($c, $order);
        $order->load(['items', 'customer', 'card', 'seller', 'member.user']);
        // commissions this order generated (same engine as online orders) — admin audit trail
        $commissions = collect();
        if ($c['who'] === 'admin') {
            $itemIds = $order->items->pluck('id');
            $commissions = \App\Models\NetworkCommission::with(['member.user', 'seller'])
                ->where(function ($q) use ($itemIds, $order) {
                    $q->where(fn($w) => $w->whereIn('source', ['offline_vendor_sale', 'offline_repurchase'])->whereIn('source_id', $itemIds))
                        ->orWhere(fn($w) => $w->where('source', 'offline_repurchase_order')->where('source_id', $order->id));
                    if ($order->welcome_kit_purchase_id) {
                        $q->orWhere(fn($w) => $w->where('source', 'welcome_kit')->where('source_id', $order->welcome_kit_purchase_id));
                    }
                })->latest()->get();
        }
        return $this->view('offline.order-show', $c, compact('order', 'commissions'));
    }

    public function handover(Request $request, OfflineOrder $order)
    {
        $c = $this->ctx($request);
        abort_unless($c['who'] === 'seller', 403);
        $this->authorizeOrder($c, $order);
        $this->pos->handOver($order, Auth::id());
        return back()->with('success', "Order {$order->order_no} handed over to the customer.");
    }

    public function cancel(Request $request, OfflineOrder $order)
    {
        $c = $this->ctx($request);
        abort_unless(in_array($c['who'], ['seller', 'admin', 'worker'], true), 403);
        $this->authorizeOrder($c, $order);
        $data = $request->validate(['reason' => 'required|string|max:200']);
        // BJO may only cancel their own order while it is still waiting for pickup; vendor only their own orders
        if ($c['who'] === 'worker' && $order->status !== 'pending_pickup') {
            abort(403);
        }
        $this->pos->cancel($order, $data['reason']);
        return back()->with('success', "Order {$order->order_no} cancelled — stock, wallet and coins restored.");
    }

    protected function authorizeOrder(array $c, OfflineOrder $order): void
    {
        $ok = match ($c['who']) {
            'admin' => true,
            'seller' => (int)$order->seller_id === (int)$c['seller']->id,
            'worker' => (int)$order->network_member_id === (int)$c['member']->id,
        };
        abort_unless($ok, 403);
    }

    // ------------------------------------------------------------------ public card page (WhatsApp link)

    public function publicCard(string $token)
    {
        $profile = OfflineCustomerProfile::with(['user', 'card'])->where('share_token', $token)->firstOrFail();
        $card = $this->cards->cardData($profile);
        return view('offline.public.card', ['card' => $card, 'token' => $token]);
    }

    public function publicPdf(string $token)
    {
        $profile = OfflineCustomerProfile::with(['user', 'card'])->where('share_token', $token)->firstOrFail();
        return response($this->cards->pdf($profile), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="SmartBazars-User-Card-' . $profile->card->card_number . '.pdf"',
        ]);
    }
}
