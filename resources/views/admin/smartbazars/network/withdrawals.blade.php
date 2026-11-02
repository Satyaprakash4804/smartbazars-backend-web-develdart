@extends('layouts.admin.app', ['page' => 'sb_network', 'sub_page' => 'sb_withdrawals'])
@section('title', 'Partner Payouts')
@section('header_data')
    @php $page_title = 'Partner Payouts'; $page_pretitle = 'BSOs & BJOs withdrawals'; @endphp
@endsection
@section('admin-content')
    @include('smartbazars._errors')
    <div class="card">
        <div class="card-header">
            <ul class="nav nav-tabs card-header-tabs">
                @foreach(['pending'=>'Pending','paid'=>'Paid','rejected'=>'Rejected','all'=>'All'] as $k=>$l)
                    <li class="nav-item"><a class="nav-link {{ (request('status','pending'))===$k ? 'active' : '' }}" href="?status={{ $k }}">{{ $l }}</a></li>
                @endforeach
            </ul>
        </div>
        <div class="table-responsive">
            <table class="table card-table table-vcenter">
                <thead><tr><th>Date</th><th>Partner</th><th>Amount</th><th>Pay to</th><th>Status</th><th>Action</th></tr></thead>
                <tbody>
                @forelse($rows as $w)
                    <tr>
                        <td class="small text-secondary">{{ $w->created_at->format('d M Y') }}</td>
                        <td>{{ $w->member->user->name ?? '' }} <span class="badge bg-secondary-lt">{{ ucfirst($w->member->type ?? '') }}</span><div class="small text-secondary">{{ $w->member->user->mobile ?? '' }}</div></td>
                        <td class="fw-bold">₹{{ number_format($w->amount, 2) }}</td>
                        <td class="small">
                            @php $d = $w->payout_details ?? []; @endphp
                            @if(($d['method'] ?? '') === 'upi') UPI: <b>{{ $d['upi_id'] ?? '' }}</b>
                            @else {{ $d['account_name'] ?? '' }}<br>A/c {{ $d['account_number'] ?? '' }} · IFSC {{ $d['ifsc'] ?? '' }} @endif
                        </td>
                        <td>@include('smartbazars._status', ['s' => $w->status]) @if($w->transaction_reference)<div class="small">{{ $w->transaction_reference }}</div>@endif</td>
                        <td>
                            @if($w->status === 'pending')
                                <form method="POST" action="{{ route('admin.smartbazars.network.withdrawals.process', $w) }}" class="d-flex gap-1">
                                    @csrf
                                    <input name="transaction_reference" class="form-control form-control-sm" placeholder="UTR / ref">
                                    <button name="action" value="paid" class="btn btn-sm btn-success">Mark paid</button>
                                    <button name="action" value="rejected" class="btn btn-sm btn-outline-danger" onclick="return confirm('Reject and return the amount to their balance?')">Reject</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-secondary py-4">No withdrawal requests.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer">{{ $rows->links() }}</div>
    </div>
@endsection
