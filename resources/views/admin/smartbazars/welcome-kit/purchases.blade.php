@extends('layouts.admin.app', ['page' => 'sb_program', 'sub_page' => 'sb_kit_orders'])
@section('title', 'Welcome Kit Orders')
@section('header_data')
    @php $page_title = 'Welcome Kit Orders'; $page_pretitle = 'SmartBazars Program'; @endphp
@endsection
@section('admin-content')
    @include('smartbazars._errors')
    <div class="card">
        <div class="card-body border-bottom py-2">
            <form class="d-flex gap-2">
                <input name="q" class="form-control" placeholder="Customer name or mobile" value="{{ request('q') }}">
                <select name="status" class="form-select w-auto"><option value="">Any status</option>
                    @foreach(\App\Models\WelcomeKitPurchase::STATUSES as $s)<option value="{{ $s }}" @selected(request('status')===$s)>{{ ucfirst($s) }}</option>@endforeach</select>
                <button class="btn">Filter</button>
            </form>
        </div>
        <div class="table-responsive">
            <table class="table card-table table-vcenter">
                <thead><tr><th>#</th><th>Customer</th><th>Deliver to</th><th>Referred by</th><th>Amount</th><th>Card</th><th>Status</th><th>Update</th></tr></thead>
                <tbody>
                @forelse($rows as $p)
                    @php $a = $p->address_snapshot ?? []; @endphp
                    <tr>
                        <td>{{ $p->id }}<div class="small text-secondary">{{ $p->created_at->format('d M Y') }}</div></td>
                        <td>{{ $p->user->name ?? '' }}<div class="small text-secondary">{{ $p->user->mobile ?? '' }}</div></td>
                        <td class="small">{{ $a['address_line1'] ?? '' }} {{ $a['address_line2'] ?? '' }}, {{ $a['city'] ?? '' }} {{ $a['zipcode'] ?? '' }}<br>{{ $a['mobile'] ?? '' }}</td>
                        <td class="small">@if($p->worker){{ $p->worker->user->name ?? '' }} <code>{{ $p->referral_code_used }}</code><br><span class="text-secondary">Vendor: {{ $p->worker->seller?->user?->name ?? '—' }}</span>@else — @endif</td>
                        <td>₹{{ number_format($p->amount, 2) }}</td>
                        <td class="small">{{ $p->card->card_number ?? '—' }}</td>
                        <td>@include('smartbazars._status', ['s' => $p->status])</td>
                        <td>
                            @if(!in_array($p->status, ['cancelled','delivered']))
                                <form method="POST" action="{{ route('admin.smartbazars.welcome-kits.purchases.update', $p) }}" class="d-flex gap-1" onsubmit="return this.status.value !== 'cancelled' || confirm('Cancel: refund ₹{{ $p->amount }} to wallet, reverse commissions and block the card?')">
                                    @csrf
                                    <select name="status" class="form-select form-select-sm">
                                        @foreach(\App\Models\WelcomeKitPurchase::STATUSES as $s)<option value="{{ $s }}" @selected($p->status===$s)>{{ ucfirst($s) }}</option>@endforeach
                                    </select>
                                    <button class="btn btn-sm btn-primary">Save</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="text-center text-secondary py-4">No kit orders yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer">{{ $rows->links() }}</div>
    </div>
@endsection
