<?php

namespace App\Http\Controllers\SmartBazars;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Payments\RazorpayController;
use App\Models\NetworkFeePayment;
use App\Services\SmartBazars\NetworkFeeService;
use Illuminate\Http\Request;

/** Razorpay checkout page for joining fees. Access is by the unguessable fee uuid (applicants have no login yet). */
class NetworkFeeController extends Controller
{
    public function __construct(protected NetworkFeeService $fees) {}

    protected function find(string $uuid): NetworkFeePayment
    {
        return NetworkFeePayment::with('request')->where('uuid', $uuid)->firstOrFail();
    }

    public function show(string $uuid)
    {
        $fee = $this->find($uuid);
        if ($fee->status !== 'pending') {
            return redirect()->route('network-fee.status', $uuid);
        }
        // a request rejected / cancelled by admin while unpaid can no longer be paid
        if ($fee->request && $fee->request->status === 'rejected') {
            return view('payments.network-fee-status', ['fee' => $fee, 'state' => 'closed']);
        }

        try {
            $orderId = $this->fees->ensureOrder($fee);
        } catch (\Throwable $e) {
            return view('payments.network-fee-status', ['fee' => $fee, 'state' => 'error', 'message' => $e->getMessage()]);
        }

        return view('payments.network-fee', [
            'fee' => $fee,
            'keyId' => app(RazorpayController::class)->keyId(),
            'orderId' => $orderId,
            'amountPaise' => (int)round(((float)$fee->amount) * 100),
            'role' => $fee->request ? ucfirst($fee->request->type) : 'Vendor',
        ]);
    }

    public function verify(Request $request, string $uuid)
    {
        $fee = $this->find($uuid);
        $data = $request->validate([
            'razorpay_order_id' => 'required|string',
            'razorpay_payment_id' => 'required|string',
            'razorpay_signature' => 'required|string',
        ]);
        $ok = $this->fees->verifyAndMarkPaid($fee, $data['razorpay_order_id'], $data['razorpay_payment_id'], $data['razorpay_signature']);
        return redirect()->route('network-fee.status', $uuid)->with($ok ? 'success' : 'error', $ok ? 'Payment received.' : 'We could not verify the payment. If money was deducted it will be confirmed automatically shortly.');
    }

    public function status(string $uuid)
    {
        $fee = $this->find($uuid);
        return view('payments.network-fee-status', ['fee' => $fee->fresh('request'), 'state' => $fee->isSettled() ? 'paid' : 'pending']);
    }
}
