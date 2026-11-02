@extends('layouts.admin.app', ['page' => 'team'])
@section('title', 'My Network')
@section('header_data')
    @php $page_title = 'My Network'; $page_pretitle = 'BSOs & vendors'; @endphp
@endsection
@section('admin-content')
    <div class="row row-cards">
        <div class="col-lg-6">
            <div class="card"><div class="card-header"><h3 class="card-title">BSO tree</h3></div>
                <div class="card-body">@include('smartbazars._tree', ['nodes' => $tree])</div></div>
        </div>
        <div class="col-lg-6">
            <div class="card"><div class="card-header"><h3 class="card-title">My vendors & their workers</h3></div>
                <div class="list-group list-group-flush">
                    @forelse($vendors as $v)
                        <div class="list-group-item">
                            <div class="fw-bold">{{ $v->user->name ?? '#'.$v->id }} <span class="badge bg-{{ $v->verification_status === 'approved' ? 'success' : 'warning' }}-lt">{{ str_replace('_',' ',$v->verification_status) }}</span></div>
                            <div class="small text-secondary">{{ $v->city }} · {{ $v->user->mobile ?? '' }}</div>
                            @if($v->workers->count())
                                <div class="mt-1 small">BJOs: @foreach($v->workers as $w)<span class="badge bg-orange-lt me-1">{{ $w->user->name ?? '' }} ({{ $w->referral_code }})</span>@endforeach</div>
                            @endif
                        </div>
                    @empty
                        <div class="list-group-item text-secondary">No vendors yet. <a href="{{ route('partner.requests') }}">Create a vendor ID</a>.</div>
                    @endforelse
                </div></div>
        </div>
    </div>
@endsection
