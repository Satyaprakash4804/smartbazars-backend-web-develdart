@extends('layouts.admin.app', ['page' => 'sb_network', 'sub_page' => 'sb_deleted'])
@section('title', 'Deleted Records')
@section('header_data')
    @php $page_title = 'Deleted Records'; $page_pretitle = 'SmartBazars Network'; @endphp
@endsection
@section('admin-content')
    @php
        $money = fn($v) => '₹' . number_format((float)$v, 2);
        $tabs = ['agent' => 'BSOs', 'worker' => 'BJOs', 'vendor' => 'Vendors'];
    @endphp
    <div class="row row-cards mb-3">
        <div class="col-6 col-md-3"><div class="card card-sm"><div class="card-body"><div class="subheader">Deleted {{ strtolower($tabs[$tab]) }}</div><div class="h2 mb-0">{{ $summary['count'] }}</div></div></div></div>
        @if($summary['wallet'] !== null)
            <div class="col-6 col-md-3"><div class="card card-sm"><div class="card-body"><div class="subheader">Wallet balance left</div><div class="h2 mb-0">{{ $money($summary['wallet']) }}</div></div></div></div>
        @endif
    </div>

    <div class="card">
        <div class="card-header flex-wrap gap-2">
            <ul class="nav nav-pills">
                @foreach($tabs as $k => $label)
                    <li class="nav-item"><a class="nav-link {{ $tab === $k ? 'active' : '' }}" href="{{ route('admin.smartbazars.network.deleted', ['type' => $k]) }}">{{ $label }}</a></li>
                @endforeach
            </ul>
            <form class="ms-md-auto d-flex gap-2 flex-grow-1 flex-md-grow-0">
                <input type="hidden" name="type" value="{{ $tab }}">
                <input name="q" class="form-control" placeholder="Name, email, mobile or code" value="{{ request('q') }}">
                <button class="btn">Search</button>
            </form>
        </div>
        <div class="table-responsive">
            <table class="table card-table table-vcenter text-nowrap">
                <thead>
                @if($tab === 'vendor')
                    <tr><th>Vendor</th><th>Code</th><th>BSO at deletion</th><th>BJOs</th><th class="text-end">Commission earned</th><th>Deleted</th></tr>
                @else
                    <tr><th>{{ ucfirst($tab) }}</th><th>Code</th><th>{{ $tab === 'agent' ? 'Upline' : 'Vendor' }}</th><th class="text-end">Wallet balance</th><th class="text-end">Total earned</th><th>Deleted</th><th class="w-1"></th></tr>
                @endif
                </thead>
                <tbody>
                @forelse($rows as $r)
                    @php $sn = $r->deleted_snapshot ?? []; @endphp
                    @if($tab === 'vendor')
                        <tr>
                            <td><b>{{ $sn['business'] ?? ($sn['name'] ?? '#' . $r->id) }}</b><div class="small text-secondary">{{ $sn['email'] ?? ($r->user->email ?? '') }} · {{ $sn['mobile'] ?? ($r->user->mobile ?? '') }}</div></td>
                            <td><code>{{ $sn['referral_code'] ?? $r->referral_code }}</code></td>
                            <td class="small">{{ $sn['upline'] ?? '—' }}</td>
                            <td class="small">{{ $sn['workers'] ?? 0 }}@if(!empty($sn['workers_target'])) <span class="text-secondary">→ {{ $sn['workers_target'] }}</span>@endif</td>
                            <td class="text-end">{{ $money($earned[$r->id] ?? 0) }}</td>
                            <td class="small">{{ $r->deleted_at?->format('d M Y H:i') }}<div class="text-secondary">by {{ $admins[$r->deleted_by] ?? '—' }}</div></td>
                        </tr>
                    @else
                        <tr>
                            <td><b>{{ $sn['name'] ?? $r->user->name ?? '#' . $r->id }}</b><div class="small text-secondary">{{ $sn['email'] ?? '' }} · {{ $sn['mobile'] ?? '' }}</div></td>
                            <td><code>{{ $r->referral_code }}</code></td>
                            <td class="small">{{ $sn['upline'] ?? '—' }}</td>
                            <td class="text-end {{ (float)$r->wallet_balance > 0 ? 'text-warning fw-bold' : '' }}">{{ $money($r->wallet_balance) }}</td>
                            <td class="text-end">{{ $money($r->total_earned) }}</td>
                            <td class="small">{{ $r->deleted_at?->format('d M Y H:i') }}<div class="text-secondary">by {{ $admins[$r->deleted_by] ?? '—' }}</div></td>
                            <td><a href="{{ route('admin.smartbazars.network.deleted.show', $r->id) }}" class="btn btn-sm">View records</a></td>
                        </tr>
                    @endif
                @empty
                    <tr><td colspan="7" class="text-center text-secondary py-4">No deleted {{ strtolower($tabs[$tab]) }}.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if($rows->hasPages())<div class="card-footer">{{ $rows->links() }}</div>@endif
    </div>
@endsection
