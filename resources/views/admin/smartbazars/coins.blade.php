@extends('layouts.admin.app', ['page' => 'sb_program', 'sub_page' => 'sb_coins'])
@section('title', 'Coin Transactions')
@section('header_data')
    @php $page_title = 'Cashback Coin Transactions'; $page_pretitle = 'SmartBazars Program'; @endphp
@endsection
@section('admin-content')
    @include('smartbazars._errors')
    <div class="card mb-3">
        <form method="POST" action="{{ route('admin.smartbazars.coins.adjust') }}" class="card-body row g-2 align-items-end">
            @csrf
            <div class="col-md-4"><label class="form-label">Customer (mobile / email / id)</label><input name="user" class="form-control" required></div>
            <div class="col-md-2"><label class="form-label">Coins (+/-)</label><input type="number" name="coins" class="form-control" required></div>
            <div class="col-md-4"><label class="form-label">Reason</label><input name="reason" class="form-control" required></div>
            <div class="col-md-2"><button class="btn btn-primary w-100">Adjust</button></div>
        </form>
    </div>
    <div class="card">
        <div class="card-body border-bottom py-2"><form class="d-flex gap-2"><input name="q" class="form-control" placeholder="Customer name / mobile" value="{{ request('q') }}"><button class="btn">Search</button></form></div>
        <div class="table-responsive">
            <table class="table card-table table-vcenter">
                <thead><tr><th>Date</th><th>Customer</th><th>Type</th><th>Coins</th><th>Balance</th><th>Details</th></tr></thead>
                <tbody>
                @forelse($rows as $t)
                    <tr>
                        <td class="small text-secondary">{{ $t->created_at->format('d M Y H:i') }}</td>
                        <td>{{ $t->user->name ?? '' }} <span class="small text-secondary">{{ $t->user->mobile ?? '' }}</span></td>
                        <td><span class="badge bg-secondary-lt">{{ ucfirst($t->type) }}</span></td>
                        <td class="fw-bold {{ $t->coins >= 0 ? 'text-success' : 'text-danger' }}">{{ $t->coins > 0 ? '+' : '' }}{{ $t->coins }}</td>
                        <td>{{ $t->balance_after }}</td>
                        <td class="small">{{ $t->description }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-secondary py-4">No coin activity yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer">{{ $rows->links() }}</div>
    </div>
@endsection
