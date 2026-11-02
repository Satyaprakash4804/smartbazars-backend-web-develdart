<?php

namespace App\Http\Controllers\Admin\SmartBazars;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\UserCard;
use App\Models\WelcomeKit;
use App\Models\WelcomeKitItem;
use App\Models\WelcomeKitPurchase;
use App\Services\SmartBazars\WelcomeKitService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Admin: manage Welcome Kit (price + contents), kit orders and User Cards. */
class WelcomeKitController extends Controller
{
    public function __construct(protected WelcomeKitService $kits) {}

    public function index()
    {
        $kits = WelcomeKit::withCount(['items', 'purchases'])->latest()->get();
        $stats = [
            'sold' => WelcomeKitPurchase::where('status', '!=', 'cancelled')->count(),
            'revenue' => WelcomeKitPurchase::where('status', '!=', 'cancelled')->sum('amount'),
            'pending_dispatch' => WelcomeKitPurchase::whereIn('status', ['paid', 'processing'])->count(),
            'cards' => UserCard::where('status', 'active')->count(),
        ];
        return view('admin.smartbazars.welcome-kit.index', compact('kits', 'stats'));
    }

    public function store(Request $request)
    {
        $data = $this->validateKit($request);
        $kit = WelcomeKit::create($data);
        return redirect()->route('admin.smartbazars.welcome-kits.edit', $kit)->with('success', 'Kit created. Now add the products it contains.');
    }

    public function edit(WelcomeKit $kit)
    {
        $kit->load('items.product');
        $products = Product::select('id', 'title')->orderBy('title')->limit(2000)->get();
        return view('admin.smartbazars.welcome-kit.edit', compact('kit', 'products'));
    }

    public function update(Request $request, WelcomeKit $kit)
    {
        $kit->update($this->validateKit($request, $kit));
        return back()->with('success', 'Kit updated.');
    }

    public function activate(WelcomeKit $kit)
    {
        DB::transaction(function () use ($kit) {
            WelcomeKit::where('id', '!=', $kit->id)->update(['is_active' => false]);
            $kit->update(['is_active' => !$kit->is_active]);
        });
        return back()->with('success', $kit->is_active ? 'Kit is now live for customers.' : 'Kit deactivated.');
    }

    public function destroy(WelcomeKit $kit)
    {
        if ($kit->purchases()->exists()) {
            return back()->with('error', 'This kit has purchases — deactivate it instead.');
        }
        $kit->delete();
        return redirect()->route('admin.smartbazars.welcome-kits.index')->with('success', 'Kit deleted.');
    }

    public function addItem(Request $request, WelcomeKit $kit)
    {
        $data = $request->validate([
            'product_id' => 'nullable|exists:products,id',
            'item_name' => 'required_without:product_id|nullable|string|max:150',
            'quantity' => 'required|integer|min:1|max:100',
        ]);
        if (empty($data['item_name']) && !empty($data['product_id'])) {
            $data['item_name'] = Product::find($data['product_id'])->title;
        }
        $data['sort'] = (int)$kit->items()->max('sort') + 1;
        $kit->items()->create($data);
        return back()->with('success', 'Item added.');
    }

    public function removeItem(WelcomeKit $kit, WelcomeKitItem $item)
    {
        abort_unless($item->welcome_kit_id === $kit->id, 404);
        $item->delete();
        return back()->with('success', 'Item removed.');
    }

    // --------------- kit orders
    public function purchases(Request $request)
    {
        $rows = WelcomeKitPurchase::with(['user', 'worker.user', 'worker.seller.user', 'card'])
            ->when($request->status, fn($q, $s) => $q->where('status', $s))
            ->when($request->q, fn($q, $s) => $q->whereHas('user', fn($u) => $u->where('name', 'like', "%$s%")->orWhere('mobile', 'like', "%$s%")))
            ->latest()->paginate(25)->withQueryString();
        return view('admin.smartbazars.welcome-kit.purchases', compact('rows'));
    }

    public function updatePurchase(Request $request, WelcomeKitPurchase $purchase)
    {
        $data = $request->validate([
            'status' => 'required|in:' . implode(',', WelcomeKitPurchase::STATUSES),
            'admin_note' => 'nullable|string|max:255',
        ]);
        if ($data['status'] === 'cancelled') {
            $this->kits->cancel($purchase, $data['admin_note'] ?? 'Cancelled by admin');
            return back()->with('success', 'Kit cancelled, amount refunded to wallet and commissions reversed.');
        }
        $purchase->update($data);
        return back()->with('success', 'Kit order updated.');
    }

    public function cards(Request $request)
    {
        $rows = UserCard::with('user')
            ->when($request->q, fn($q, $s) => $q->where('card_number', 'like', "%$s%")
                ->orWhereHas('user', fn($u) => $u->where('name', 'like', "%$s%")->orWhere('mobile', 'like', "%$s%")))
            ->latest()->paginate(25)->withQueryString();
        return view('admin.smartbazars.welcome-kit.cards', compact('rows'));
    }

    public function toggleCard(UserCard $card)
    {
        $card->update(['status' => $card->status === 'active' ? 'blocked' : 'active']);
        return back()->with('success', 'Card ' . $card->status . '.');
    }

    protected function validateKit(Request $request, ?WelcomeKit $kit = null): array
    {
        $data = $request->validate([
            'name' => 'required|string|max:150',
            'description' => 'nullable|string|max:2000',
            'price' => 'required|numeric|min:1|max:100000',
            'image' => 'nullable|image|max:2048',
        ]);
        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('welcome-kits', 'public');
        } else {
            unset($data['image']);
        }
        return $data;
    }
}
