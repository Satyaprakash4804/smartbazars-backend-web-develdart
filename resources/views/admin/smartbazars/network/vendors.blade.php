@extends('layouts.admin.app', ['page' => 'sb_network', 'sub_page' => 'sb_vendors'])
@section('title', 'Vendor Letters & QR')
@section('header_data')
    @php $page_title = 'Vendor Letters & QR'; $page_pretitle = 'SmartBazars Network'; @endphp
@endsection
@section('admin-content')
    <div class="card">
        <div class="card-body border-bottom py-2">
            <form class="d-flex gap-2"><input name="q" class="form-control" placeholder="Vendor name, email, mobile or code" value="{{ request('q') }}"><button class="btn">Search</button></form>
        </div>
        <div class="table-responsive">
            <table class="table card-table table-vcenter">
                <thead><tr><th>Vendor</th><th>Code</th><th>BSO</th><th>Status</th><th>Joining fee</th><th>Direct customers</th><th>Letter emailed</th><th class="w-1"></th></tr></thead>
                <tbody>
                @forelse($rows as $v)
                    @php $st = is_object($v->verification_status) ? $v->verification_status->value : $v->verification_status; @endphp
                    <tr>
                        <td><b>{{ $v->displayName() }}</b><div class="small text-secondary">{{ $v->user->name ?? '' }} · {{ $v->user->email ?? '' }}</div></td>
                        <td><code>{{ $v->referral_code }}</code></td>
                        <td class="small">{{ $v->agent?->user?->name ?? 'SmartBazars' }}</td>
                        <td>@include('smartbazars._status', ['s' => $st === 'approved' ? 'active' : $st])</td>
                        @php $vf = \App\Models\NetworkFeePayment::where('seller_id', $v->id)->where('purpose', 'vendor_joining')->latest()->first(); @endphp
                        <td class="small">@if($vf)@include('smartbazars._status', ['s' => $vf->status])<div class="text-secondary">₹{{ number_format((float)$vf->amount, 2) }}</div>@if($vf->status === 'pending')<form method="POST" action="{{ route('admin.smartbazars.network.vendors.waive-fee', $v) }}" onsubmit="return confirm('Waive joining fee?')">@csrf<button class="btn btn-sm btn-outline-secondary mt-1">Waive</button></form>@endif @else — @endif</td>
                        <td>{{ $v->referred_customers_count }}</td>
                        <td class="small">{{ $v->verified_mail_sent_at?->format('d M Y H:i') ?? '—' }}</td>
                        <td class="text-nowrap">
                            <a href="{{ route('admin.sellers.edit', $v->id) }}" class="btn btn-sm">Edit</a>
                            <form method="POST" action="{{ route('admin.smartbazars.network.vendors.resend', $v) }}" class="d-inline" onsubmit="return confirm('This sets a NEW password and emails the login details to {{ $v->user->email ?? '' }}. Continue?')">@csrf<button class="btn btn-sm btn-outline-primary">Resend login</button></form>
                            <a href="{{ route('admin.smartbazars.network.vendors.delete-form', $v) }}" class="btn btn-sm btn-outline-danger">Delete</a>
                            <a target="_blank" href="{{ route('admin.smartbazars.network.vendors.letter', $v) }}" class="btn btn-sm">View letter</a>
                            <form method="POST" action="{{ route('admin.smartbazars.network.vendors.send-letter', $v) }}" class="d-inline">@csrf<button class="btn btn-sm btn-primary">Email letter + QR</button></form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="text-center text-secondary py-4">No vendors.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer">{{ $rows->links() }}</div>
    </div>
@endsection
