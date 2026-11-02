@extends('layouts.admin.app', ['page' => 'earnings'])
@section('title', 'Earnings & Payouts')
@section('header_data')
    @php $page_title = 'Earnings & Payouts'; $page_pretitle = 'Balance ₹' . number_format($m->wallet_balance, 2); @endphp
@endsection
@section('admin-content')
    @include('smartbazars._errors')
    <div class="row row-cards">
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header"><h3 class="card-title">Withdraw</h3></div>
                <form method="POST" action="{{ route('partner.earnings.withdraw') }}" class="card-body">
                    @csrf
                    <div class="h2">₹{{ number_format($m->wallet_balance, 2) }} <span class="small text-secondary fw-normal">available</span></div>
                    <div class="mb-2"><label class="form-label">Amount (min ₹{{ $min }})</label><input type="number" step="0.01" name="amount" min="{{ $min }}" max="{{ $m->wallet_balance }}" class="form-control" required></div>
                    <div class="mb-2"><select name="method" class="form-select" onchange="document.getElementById('upi').classList.toggle('d-none',this.value!=='upi');document.getElementById('bank').classList.toggle('d-none',this.value!=='bank')">
                        <option value="upi">UPI</option><option value="bank">Bank transfer</option></select></div>
                    <div id="upi" class="mb-2"><input name="upi_id" class="form-control" placeholder="name@upi"></div>
                    <div id="bank" class="d-none">
                        <input name="account_name" class="form-control mb-2" placeholder="Account holder name">
                        <input name="account_number" class="form-control mb-2" placeholder="Account number">
                        <input name="ifsc" class="form-control mb-2" placeholder="IFSC">
                    </div>
                    <button class="btn btn-primary w-100" @disabled($m->wallet_balance < $min)>Request withdrawal</button>
                </form>
                <div class="list-group list-group-flush">
                    @foreach($withdrawals as $w)
                        <div class="list-group-item d-flex justify-content-between"><span>₹{{ number_format($w->amount, 2) }} <span class="small text-secondary">{{ $w->created_at->format('d M') }}</span></span>@include('smartbazars._status', ['s' => $w->status])</div>
                    @endforeach
                </div>
            </div>
        </div>
        <div class="col-lg-8">
            <div class="card">
                <div class="card-body border-bottom py-2">
                    <form class="d-flex gap-2"><select name="source" class="form-select w-auto" onchange="this.form.submit()"><option value="">All</option><option value="vendor_sale" @selected(request('source')==='vendor_sale')>Vendor sales</option><option value="welcome_kit" @selected(request('source')==='welcome_kit')>Welcome kits</option></select></form>
                </div>
                @include('admin.smartbazars.network._commission_table', ['rows' => $rows, 'showMember' => false])
                <div class="card-footer">{{ $rows->links() }}</div>
            </div>
        </div>
    </div>
@endsection
