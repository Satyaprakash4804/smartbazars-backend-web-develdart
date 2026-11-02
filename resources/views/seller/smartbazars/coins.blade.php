@extends('layouts.seller.app', ['page' => 'sb_vendor_business', 'sub_page' => 'sb_vcoins'])
@section('title', 'My Coins')
@section('header_data')
    @php $page_title = 'My Coins'; $page_pretitle = 'Coins customers paid on your products'; @endphp
@endsection
@section('seller-content')
    @include('smartbazars._errors')
    <div class="row row-cards">
        <div class="col-lg-4">
            <div class="card" style="background:linear-gradient(135deg,#FFF8E1,#FFE0B2)">
                <div class="card-body text-center">
                    <img src="{{ asset('assets/images/smartbazars/coin@2x.png') }}" width="96" height="96" alt="coins">
                    <div class="display-6 fw-bold mt-2">{{ number_format($seller->coin_balance) }}</div>
                    <div class="text-secondary">coins · worth ₹{{ number_format($seller->coin_balance * $coinValue, 2) }}</div>
                </div>
            </div>
            <div class="card mt-3">
                <div class="card-header"><h3 class="card-title">Convert to cash</h3></div>
                <form method="POST" action="{{ route('seller.smartbazars.coins.transfer') }}" class="card-body">
                    @csrf
                    <p class="small text-secondary">Transfer coins to SmartBazars. After approval the cash value is added to your settlement / withdrawable earnings.</p>
                    <div class="input-group mb-2"><input type="number" name="coins" min="1" max="{{ $seller->coin_balance }}" class="form-control" placeholder="Coins" required><span class="input-group-text">coins</span></div>
                    <input name="vendor_note" class="form-control mb-3" placeholder="Note (optional)">
                    <button class="btn btn-primary w-100" @disabled($seller->coin_balance < 1)>Transfer to SmartBazars</button>
                    <a href="{{ route('seller.smartbazars.wholesale') }}" class="btn btn-ghost-primary w-100 mt-2">Or use coins for a bulk purchase →</a>
                </form>
            </div>
            <div class="card mt-3">
                <div class="card-header"><h3 class="card-title">Transfers</h3></div>
                <div class="list-group list-group-flush">
                    @forelse($transfers as $t)
                        <div class="list-group-item d-flex justify-content-between"><span>{{ $t->coins }} coins · ₹{{ number_format($t->rupee_value, 2) }}<div class="small text-secondary">{{ $t->created_at->format('d M Y') }}</div></span>@include('smartbazars._status', ['s' => $t->status === 'approved' ? 'paid' : $t->status])</div>
                    @empty <div class="list-group-item text-secondary">No transfers yet.</div> @endforelse
                </div>
            </div>
        </div>
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header"><h3 class="card-title">Coin history</h3></div>
                <div class="table-responsive"><table class="table card-table table-vcenter">
                    <thead><tr><th>Date</th><th>Type</th><th>Coins</th><th>Balance</th><th>Details</th></tr></thead>
                    <tbody>
                    @forelse($rows as $r)
                        <tr><td class="small text-secondary">{{ $r->created_at->format('d M Y H:i') }}</td>
                            <td><span class="badge bg-secondary-lt">{{ ucwords(str_replace('_', ' ', $r->type)) }}</span></td>
                            <td class="fw-bold {{ $r->coins >= 0 ? 'text-success' : 'text-danger' }}">{{ $r->coins > 0 ? '+' : '' }}{{ $r->coins }}</td>
                            <td>{{ $r->balance_after }}</td><td class="small">{{ $r->description }}</td></tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-secondary py-4">When customers pay with cashback coins for your products, the coins arrive here after delivery.</td></tr>
                    @endforelse
                    </tbody>
                </table></div>
                <div class="card-footer">{{ $rows->links() }}</div>
            </div>
        </div>
    </div>
@endsection
