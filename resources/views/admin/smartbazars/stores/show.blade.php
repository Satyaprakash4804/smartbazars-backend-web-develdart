@extends('layouts.admin.app', ['page' => 'sb_program', 'sub_page' => 'sb_store_review'])
@section('title', 'Review: ' . $store->name)
@section('header_data')
    @php $page_title = $store->name; $page_pretitle = 'Store Review'; @endphp
@endsection
@section('admin-content')
    @include('smartbazars._errors')
    @php
        $state = $store->reviewState();
        $mask = fn($v) => $v ? str_repeat('•', max(0, strlen($v) - 4)) . substr($v, -4) : '—';
        $docs = ['Store logo' => $store->store_logo, 'Address proof' => $store->address_proof, 'Voided cheque' => $store->voided_check];
    @endphp
    <div class="mb-3">
        <a href="{{ route('admin.smartbazars.store-review') }}" class="btn btn-sm">&larr; Back to list</a>
    </div>
    <div class="row row-cards">
        <div class="col-lg-7">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Store details</h3>
                    <div class="ms-auto">@include('smartbazars._store_state', ['store' => $store])</div>
                </div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-4">Store name</dt><dd class="col-8">{{ $store->name }}</dd>
                        <dt class="col-4">Vendor</dt>
                        <dd class="col-8">{{ $store->seller?->user?->name ?? '—' }} <span class="text-secondary small">{{ $store->seller?->user?->email }} · {{ $store->seller?->user?->mobile }}</span></dd>
                        <dt class="col-4">Contact</dt><dd class="col-8">{{ $store->contact_number ?: '—' }} @if($store->contact_email)· {{ $store->contact_email }}@endif</dd>
                        <dt class="col-4">Address</dt>
                        <dd class="col-8">{{ collect([$store->address, $store->landmark, $store->city, $store->state, $store->zipcode, $store->country])->filter()->implode(', ') ?: '—' }}</dd>
                        <dt class="col-4">Delivery zones</dt>
                        <dd class="col-8">{{ $store->zones->pluck('name')->implode(', ') ?: '—' }}</dd>
                        <dt class="col-4">Tax</dt><dd class="col-8">{{ $store->tax_name ?: '—' }} {{ $store->tax_number }}</dd>
                        <dt class="col-4">Bank</dt>
                        <dd class="col-8">{{ $store->bank_name ?: '—' }} · {{ $store->account_holder_name ?: '—' }} · A/c {{ $mask($store->account_number) }} · {{ $store->routing_number ?: '' }}</dd>
                        <dt class="col-4">Created</dt><dd class="col-8">{{ $store->created_at?->format('d M Y H:i') }}</dd>
                        @if($store->verified_at)<dt class="col-4">Last reviewed</dt><dd class="col-8">{{ $store->verified_at->format('d M Y H:i') }}</dd>@endif
                        @if($store->rejection_reason)<dt class="col-4 text-danger">Rejection reason</dt><dd class="col-8 text-danger">{{ $store->rejection_reason }}</dd>@endif
                    </dl>
                </div>
            </div>

            <div class="card mt-3">
                <div class="card-header"><h3 class="card-title">Documents</h3></div>
                <div class="card-body">
                    <div class="row g-3">
                        @foreach($docs as $label => $url)
                            <div class="col-md-4">
                                <div class="fw-bold small mb-1">{{ $label }}</div>
                                @if($url)
                                    <a href="{{ $url }}" target="_blank" rel="noopener">
                                        <img src="{{ $url }}" alt="{{ $label }}" class="img-fluid rounded border" style="max-height:160px;object-fit:contain;width:100%">
                                    </a>
                                @else
                                    <span class="text-danger small">Missing</span>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card">
                <div class="card-header"><h3 class="card-title">Checklist</h3></div>
                <div class="list-group list-group-flush">
                    @foreach($checklist as $item)
                        <div class="list-group-item">
                            <div class="d-flex justify-content-between align-items-center">
                                <span>{{ $item['label'] }} @if(!$item['required'])<span class="text-secondary small">(optional)</span>@endif</span>
                                @if($item['ok'])
                                    <span class="badge bg-green-lt">OK</span>
                                @else
                                    <span class="badge bg-{{ $item['required'] ? 'red' : 'orange' }}-lt">{{ $item['required'] ? 'Missing' : 'Not filled' }}</span>
                                @endif
                            </div>
                            @if(!$item['ok'] && !empty($item['hint']))<div class="small text-secondary mt-1">{{ $item['hint'] }}</div>@endif
                        </div>
                    @endforeach
                </div>
            </div>

            @if($state !== 'approved')
                <div class="card mt-3">
                    <div class="card-header"><h3 class="card-title">Decision</h3></div>
                    <div class="card-body">
                        @if(count($blockers))
                            <div class="alert alert-warning">Can't approve yet — missing: {{ implode(', ', $blockers) }}.</div>
                        @endif
                        <form method="POST" action="{{ route('admin.smartbazars.store-review.approve', $store) }}" class="mb-4"
                              onsubmit="return confirm('Approve this store? It becomes visible to customers.')">
                            @csrf
                            <button class="btn btn-success w-100" @disabled(count($blockers))>Approve store</button>
                        </form>
                        <form method="POST" action="{{ route('admin.smartbazars.store-review.reject', $store) }}">
                            @csrf
                            <label class="form-label required">Reject with reason (the vendor sees this)</label>
                            <textarea name="reason" rows="3" class="form-control mb-2" maxlength="500" required>{{ old('reason') }}</textarea>
                            <button class="btn btn-outline-danger w-100">Reject store</button>
                        </form>
                    </div>
                </div>
            @else
                <div class="card mt-3"><div class="card-body text-secondary">This store is approved and visible to customers.</div></div>
            @endif
        </div>
    </div>
@endsection
