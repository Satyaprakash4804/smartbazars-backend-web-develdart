<?php

namespace App\Http\Controllers\Partner;

use App\Http\Controllers\Controller;
use App\Models\NetworkMember;
use App\Services\SmartBazars\KycService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

/** First-login KYC for agents & workers — asks only for what is still missing. */
class KycController extends Controller
{
    public function __construct(protected KycService $kyc) {}

    protected function member(): NetworkMember
    {
        return NetworkMember::with('user')->where('user_id', Auth::id())->firstOrFail();
    }

    public function index()
    {
        $m = $this->member();
        $missing = $this->kyc->memberMissing($m);
        if (!$missing) return redirect()->route('partner.dashboard');
        return view('partner.kyc', ['m' => $m, 'missing' => $missing, 'kyc' => $this->kyc->kycFor($m)]);
    }

    public function store(Request $request)
    {
        $m = $this->member();
        $missing = $this->kyc->memberMissing($m);
        if (!$missing) return redirect()->route('partner.dashboard');

        $uid = $m->user_id;
        $all = [
            'name' => 'required|string|max:100',
            'mobile' => ['required', 'string', 'min:10', 'max:15', Rule::unique('users', 'mobile')->ignore($uid)->whereNull('deleted_at')],
            'email' => ['required', 'email', 'max:150', Rule::unique('users', 'email')->ignore($uid)->whereNull('deleted_at')],
            'address' => 'required|string|max:255',
            'city' => 'required|string|max:100',
            'state' => 'required|string|max:100',
            'zipcode' => 'required|string|max:20',
            'aadhar_number' => ['required', 'regex:/^\d{4}\s?\d{4}\s?\d{4}$/'],
            'pan_number' => ['required', 'regex:/^[A-Za-z]{5}\d{4}[A-Za-z]$/'],
            'qualification' => 'required|string|max:150',
            'aadhar_doc' => 'required|file|mimes:jpg,jpeg,png,pdf|max:4096',
            'pan_doc' => 'required|file|mimes:jpg,jpeg,png,pdf|max:4096',
            'education_doc' => 'required|file|mimes:jpg,jpeg,png,pdf|max:4096',
        ];
        $rules = array_intersect_key($all, array_flip($missing));
        $rules['education_details'] = 'nullable|string|max:1000';

        $data = $request->validate($rules, [
            'aadhar_number.regex' => 'Enter the 12-digit Aadhaar number.',
            'pan_number.regex' => 'Enter a valid PAN (e.g. ABCDE1234F).',
            '*.uploaded' => 'The file could not be uploaded — it may be larger than the server allows.',
        ]);

        $this->kyc->saveMember($m, $data, $request->allFiles());
        return redirect()->route('partner.dashboard')->with('success', 'KYC completed. Thank you!');
    }
}
