<?php

namespace App\Http\Controllers\Partner;

use App\Http\Controllers\Controller;
use App\Models\NetworkRegistrationRequest;
use App\Services\SmartBazars\NetworkFeeService;
use App\Services\SmartBazars\NetworkService;
use Illuminate\Http\Request;

/** Public "Apply now" for BSOs & BJOs (linked from the partner login page). */
class ApplyController extends Controller
{
    public function __construct(protected NetworkService $network, protected NetworkFeeService $fees) {}

    public function form(Request $request)
    {
        $retry = $request->query('retry')
            ? NetworkRegistrationRequest::where('uuid', $request->query('retry'))->where('status', 'rejected')->where('source', 'public')->first()
            : null;

        $code = strtoupper(trim((string)($request->query('ref') ?: ($retry->meta['referral_code'] ?? ''))));
        $referrer = $code ? \App\Models\NetworkMember::with('user:id,name')->where('referral_code', $code)->where('type', 'agent')->where('status', 'active')->first() : null;

        return view('partner.apply', [
            'refCode' => $code,
            'referrer' => $referrer,
            'retry' => $retry,
            'fees' => ['agent' => $this->fees->feeFor('agent'), 'worker' => $this->fees->feeFor('worker')],
        ]);
    }

    public function submit(Request $request)
    {
        $data = $request->validate([
            'type' => 'required|in:agent,worker',
            'name' => 'required|string|max:100',
            'email' => 'required|email|max:150',
            'mobile' => 'required|string|min:10|max:15',
            'address' => 'required|string|max:255',
            'city' => 'required|string|max:100',
            'state' => 'required|string|max:100',
            'zipcode' => 'required|string|max:20',
            'aadhar_number' => ['required', 'regex:/^\d{4}\s?\d{4}\s?\d{4}$/'],
            'pan_number' => ['required', 'regex:/^[A-Za-z]{5}\d{4}[A-Za-z]$/'],
            'qualification' => 'required|string|max:150',
            'education_details' => 'nullable|string|max:1000',
            'referral_code' => 'nullable|string|max:20',
            'retry' => 'nullable|string|max:40',
            'aadhar_doc' => 'required|file|mimes:jpg,jpeg,png,pdf|max:4096',
            'pan_doc' => 'required|file|mimes:jpg,jpeg,png,pdf|max:4096',
            'education_doc' => 'required|file|mimes:jpg,jpeg,png,pdf|max:4096',
        ], [
            'aadhar_number.regex' => 'Enter the 12-digit Aadhaar number.',
            'pan_number.regex' => 'Enter a valid PAN (e.g. ABCDE1234F).',
        ]);

        // Applied before but never paid? Take them back to that payment instead of blocking them.
        $open = NetworkRegistrationRequest::where('source', 'public')->where('status', 'pending_payment')
            ->where(fn($q) => $q->where('email', strtolower($data['email']))->orWhere('mobile', $data['mobile']))
            ->with('feePayment')->latest()->first();
        if ($open && $open->feePayment) {
            return redirect($open->feePayment->payUrl())->with('success', 'You already have an application waiting for payment. Complete it below.');
        }

        $req = $this->network->submitPublicApplication($data, $request->allFiles(), $data['retry'] ?? null);

        if ($req->status === 'pending_payment' && $req->feePayment) {
            return redirect($req->feePayment->payUrl());
        }
        return redirect()->route('partner.login')->with('success', 'Application submitted. SmartBazars will review it and email you the result.');
    }
}
