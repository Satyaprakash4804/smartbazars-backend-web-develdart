<?php

namespace App\Http\Controllers\Seller\SmartBazars;

use App\Http\Controllers\Controller;
use App\Models\Seller;
use App\Services\SmartBazars\KycService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

/** First-login KYC for vendors — asks only for the details / documents that are still missing. */
class KycController extends Controller
{
    public function __construct(protected KycService $kyc) {}

    protected function seller(): Seller
    {
        return Seller::with('user')->where('user_id', Auth::id())->firstOrFail();
    }

    public function index()
    {
        $s = $this->seller();
        $missing = $this->kyc->sellerMissing($s);
        if (!$missing) return redirect()->route('seller.dashboard');
        return view('seller.smartbazars.kyc', ['seller' => $s, 'missing' => $missing, 'docs' => KycService::SELLER_DOCS]);
    }

    public function store(Request $request)
    {
        $s = $this->seller();
        $missing = $this->kyc->sellerMissing($s);
        if (!$missing) return redirect()->route('seller.dashboard');

        $rules = $this->kyc->sellerRules($s, $missing);

        $data = $request->validate($rules, ['*.uploaded' => 'The file could not be uploaded — it may be larger than the server allows (max 2 MB).']);

        $this->kyc->saveSeller($s, $data, $request->allFiles());
        return redirect()->route('seller.dashboard')->with('success', 'KYC completed. Thank you!');
    }
}
