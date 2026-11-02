@extends('layouts.admin.app', ['page' => 'dashboard'])
@section('title', 'Dashboard')
@section('header_data')
    @php $page_title = 'Namaste, ' . (auth()->user()->name ?? ''); $page_pretitle = 'SmartBazars ' . ucfirst($m->type); @endphp
@endsection
@section('admin-content')
    <div class="card mb-3 bg-green-lt">
        <div class="card-body d-flex flex-wrap align-items-center justify-content-between gap-2">
            <div>
                <div class="text-secondary">Your referral code</div>
                <div class="h1 mb-0 font-monospace" id="refcode">{{ $m->referral_code }}</div>
            </div>
            <button class="btn btn-success" onclick="navigator.clipboard.writeText('{{ $m->referral_code }}');this.innerText='Copied!'">Copy code</button>
        </div>
        <div class="card-footer small">
            @if($m->isAgent())
                You earn <b>{{ $s['agent_direct_percent'] }}%</b> on every delivered sale of vendors you onboard, <b>{{ $s['agent_upline_percent'] }}%</b> on sales of vendors onboarded by your direct sub-agents,
                and ₹{{ $s['kit_agent_amount'] }} / ₹{{ $s['kit_agent_upline_amount'] }} on each Welcome Kit sold by those vendors' workers.
            @else
                Customers enter this code when buying the ₹{{ \App\Models\WelcomeKit::active()?->price ?? 250 }} Welcome Kit — you earn <b>₹{{ $s['kit_worker_amount'] }}</b> per kit.
            @endif
        </div>
    </div>
    <div class="row row-cards">
        <div class="col-6 col-lg-3"><div class="card card-sm"><div class="card-body"><div class="text-secondary">Available balance</div><div class="h2 mb-0">₹{{ number_format($stats['balance'], 2) }}</div></div></div></div>
        <div class="col-6 col-lg-3"><div class="card card-sm"><div class="card-body"><div class="text-secondary">This month</div><div class="h2 mb-0">₹{{ number_format($stats['this_month'], 2) }}</div></div></div></div>
        <div class="col-6 col-lg-3"><div class="card card-sm"><div class="card-body"><div class="text-secondary">Total earned</div><div class="h2 mb-0">₹{{ number_format($stats['total_earned'], 2) }}</div></div></div></div>
        @if($m->isAgent())
            <div class="col-12 col-lg-6"><a href="{{ route('partner.team.added') }}" class="card card-sm card-link card-link-pop text-decoration-none">
                <div class="card-body d-flex align-items-center gap-3">
                    <span class="avatar avatar-lg bg-primary-lt"><i class="ti ti-user-plus fs-1"></i></span>
                    <div class="flex-fill">
                        <div class="text-secondary">IDs added today in my downline</div>
                        <div class="h1 mb-0">{{ $stats['added_today']['total'] }}</div>
                        <div class="small text-secondary">{{ $stats['added_today']['agents'] }} agents · {{ $stats['added_today']['vendors'] }} vendors · {{ $stats['added_today']['workers'] }} workers</div>
                    </div>
                    <span class="text-primary small">View list →</span>
                </div>
            </a></div>
            <div class="col-6 col-lg-3"><div class="card card-sm"><div class="card-body"><div class="text-secondary">Pending ID requests</div><div class="h2 mb-0">{{ $stats['pending_requests'] }}</div></div></div></div>
            <div class="col-6 col-lg-3"><div class="card card-sm"><div class="card-body"><div class="text-secondary">Direct agents</div><div class="h2 mb-0">{{ $stats['direct_agents'] }}</div></div></div></div>
            <div class="col-6 col-lg-3"><div class="card card-sm"><div class="card-body"><div class="text-secondary">Total downline</div><div class="h2 mb-0">{{ $stats['sub_agents'] }}</div></div></div></div>
            <div class="col-6 col-lg-3"><div class="card card-sm"><div class="card-body"><div class="text-secondary">Vendors</div><div class="h2 mb-0">{{ $stats['vendors'] }}</div></div></div></div>
            <div class="col-6 col-lg-3"><div class="card card-sm"><div class="card-body"><div class="text-secondary">BJOs (my vendors)</div><div class="h2 mb-0">{{ $stats['workers'] }}</div></div></div></div>
        @else
            <div class="col-6 col-lg-3"><div class="card card-sm"><div class="card-body"><div class="text-secondary">Kits sold</div><div class="h2 mb-0">{{ $stats['kits_sold'] }}</div></div></div></div>
            <div class="col-6 col-lg-3"><div class="card card-sm"><div class="card-body"><div class="text-secondary">Customers added</div><div class="h2 mb-0">{{ $stats['customers'] }}</div></div></div></div>
        @endif
    </div>
    <div class="card mt-3">
        <div class="card-header"><h3 class="card-title">Recent earnings</h3><div class="card-actions"><a href="{{ route('partner.earnings') }}">View all</a></div></div>
        <div class="list-group list-group-flush">
            @forelse($recent as $r)
                <div class="list-group-item d-flex justify-content-between">
                    <div><div>{{ $r->description }}</div><div class="small text-secondary">{{ $r->created_at->diffForHumans() }}</div></div>
                    <div class="fw-bold {{ $r->status === 'reversed' ? 'text-danger text-decoration-line-through' : 'text-success' }}">+₹{{ number_format($r->amount, 2) }}</div>
                </div>
            @empty
                <div class="list-group-item text-secondary">No earnings yet.</div>
            @endforelse
        </div>
    </div>
@endsection
