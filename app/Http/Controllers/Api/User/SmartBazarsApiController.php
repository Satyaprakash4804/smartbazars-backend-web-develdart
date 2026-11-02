<?php

namespace App\Http\Controllers\Api\User;

use App\Http\Controllers\Controller;
use App\Models\CoinTransaction;
use App\Models\NetworkMember;
use App\Models\WelcomeKit;
use App\Models\WelcomeKitPurchase;
use App\Services\SmartBazars\CoinService;
use App\Services\SmartBazars\ProgramSettings;
use App\Services\SmartBazars\WelcomeKitService;
use App\Types\Api\ApiResponseType;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * SmartBazars customer APIs: Welcome Kit, User Card and Cashback Coins.
 */
#[Group('SmartBazars')]
class SmartBazarsApiController extends Controller
{
    public function __construct(protected WelcomeKitService $kits, protected CoinService $coins) {}

    /** GET /api/user/welcome-kit — kit details + the user's status (card, wallet, linked worker). */
    public function welcomeKit(Request $request): JsonResponse
    {
        $user = $request->user()->load('userCard', 'wallet');
        $kit = WelcomeKit::active();
        $purchase = WelcomeKitPurchase::where('user_id', $user->id)->where('status', '!=', 'cancelled')->latest()->first();
        $worker = $user->referred_by_worker_id ? NetworkMember::with('user:id,name', 'seller')->find($user->referred_by_worker_id) : null;

        return ApiResponseType::sendJsonResponse(true, 'Welcome kit', [
            'kit' => $kit ? [
                'id' => $kit->id,
                'name' => $kit->name,
                'description' => $kit->description,
                'price' => (float)$kit->price,
                'image' => $kit->image_url,
                'items' => $kit->items->map(fn($i) => [
                    'name' => $i->item_name,
                    'quantity' => $i->quantity,
                    'product_id' => $i->product_id,
                    'image' => $i->product?->main_image ?? null,
                ])->values(),
            ] : null,
            'purchased' => (bool)$purchase,
            'purchase' => $purchase ? $this->purchaseArray($purchase) : null,
            'user_card' => $user->userCard?->toApiArray(),
            'wallet_balance' => (float)($user->wallet->balance ?? 0),
            'referral_required' => (bool)ProgramSettings::get('kit_requires_referral_code'),
            'linked_worker' => $worker ? ['name' => $worker->user->name ?? '', 'referral_code' => $worker->referral_code] : null,
        ]);
    }

    /** POST /api/user/welcome-kit/check-referral {referral_code} */
    public function checkReferral(Request $request): JsonResponse
    {
        $request->validate(['referral_code' => 'required|string|max:20']);
        $r = app(\App\Services\SmartBazars\ReferralService::class)->resolve($request->referral_code);
        if (!$r) {
            return ApiResponseType::sendJsonResponse(false, 'Invalid referral code.', null, 422);
        }
        return ApiResponseType::sendJsonResponse(true, 'Valid referral code', [
            'type' => $r['type'],
            'worker_name' => $r['worker']?->user?->name ?? '',
            'vendor_name' => $r['seller']?->displayName(),
        ]);
    }

    /** POST /api/user/referral/link {referral_code} — attach the customer to a worker/vendor (once). */
    public function linkReferral(Request $request): JsonResponse
    {
        $request->validate(['referral_code' => 'required|string|max:20']);
        $user = $request->user();
        $svc = app(\App\Services\SmartBazars\ReferralService::class);
        if (!$svc->resolve($request->referral_code)) {
            return ApiResponseType::sendJsonResponse(false, 'Invalid referral code.', null, 422);
        }
        $linked = $svc->linkCustomer($user, $request->referral_code);
        return ApiResponseType::sendJsonResponse(true, $linked ? 'Referral code applied.' : 'Your account is already linked to a SmartBazars partner.', ['linked' => $linked]);
    }

    /** POST /api/user/welcome-kit/purchase {address_id, referral_code?} — paid from wallet. */
    public function purchase(Request $request): JsonResponse
    {
        $request->validate([
            'address_id' => 'required|integer',
            'referral_code' => 'nullable|string|max:20',
        ]);
        try {
            $purchase = $this->kits->purchase($request->user(), $request->only('address_id', 'referral_code'));
        } catch (ValidationException $e) {
            return ApiResponseType::sendJsonResponse(false, collect($e->errors())->flatten()->first(), $e->errors(), 422, true);
        }
        return ApiResponseType::sendJsonResponse(true, 'Welcome kit purchased! Your SmartBazars User Card is ready.', [
            'purchase' => $this->purchaseArray($purchase),
            'user_card' => $purchase->card?->toApiArray(),
        ]);
    }

    /** GET /api/user/user-card */
    public function userCard(Request $request): JsonResponse
    {
        $card = $request->user()->userCard;
        if (!$card) {
            return ApiResponseType::sendJsonResponse(false, 'Buy the Welcome Kit to get your SmartBazars User Card.', null, 404);
        }
        return ApiResponseType::sendJsonResponse(true, 'User card', $card->toApiArray());
    }

    /** GET /api/user/coins — balance + history */
    public function coins(Request $request): JsonResponse
    {
        $user = $request->user();
        $page = CoinTransaction::where('user_id', $user->id)->latest()->paginate((int)$request->get('per_page', 20));
        return ApiResponseType::sendJsonResponse(true, 'Coins', [
            'coin_balance' => (int)$user->coin_balance,
            'coin_value' => $this->coins->coinValue(),
            'coin_balance_value' => round($user->coin_balance * $this->coins->coinValue(), 2),
            'transactions' => $page->getCollection()->map(fn($t) => [
                'id' => $t->id,
                'type' => $t->type,
                'coins' => $t->coins,
                'balance_after' => $t->balance_after,
                'order_id' => $t->order_id,
                'description' => $t->description,
                'created_at' => $t->created_at?->toIso8601String(),
            ]),
            'current_page' => $page->currentPage(),
            'last_page' => $page->lastPage(),
        ]);
    }

    protected function purchaseArray(WelcomeKitPurchase $p): array
    {
        return [
            'id' => $p->id,
            'amount' => (float)$p->amount,
            'status' => $p->status,
            'items' => $p->items_snapshot ?? [],
            'referral_code_used' => $p->referral_code_used,
            'created_at' => $p->created_at?->toIso8601String(),
        ];
    }
}
