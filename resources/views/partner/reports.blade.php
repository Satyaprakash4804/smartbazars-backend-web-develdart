@extends('layouts.admin.app', ['page' => 'daily_report'])
@section('title', 'Daily Report')
@section('header_data')
    @php $page_title = 'Daily Report'; $page_pretitle = now()->format('l, d M Y'); @endphp
@endsection
@section('admin-content')
    @include('smartbazars._errors')
    <div class="row row-cards">
        <div class="col-lg-5">
            <div class="card">
                <div class="card-header"><h3 class="card-title">Today's report</h3>@if($today)<div class="card-actions"><span class="badge bg-success-lt">Submitted · you can edit until midnight</span></div>@endif</div>
                <form method="POST" action="{{ route('partner.reports.daily.save') }}" class="card-body">
                    @csrf
                    <div class="row g-2 mb-2">
                        <div class="col-6"><label class="form-label">{{ $m->isAgent() ? 'Vendors/people met' : 'Customers met' }}</label><input type="number" min="0" name="customers_met" value="{{ old('customers_met', $today?->customers_met) }}" class="form-control"></div>
                        <div class="col-6"><label class="form-label">Welcome kits sold</label><input type="number" min="0" name="kits_sold" value="{{ old('kits_sold', $today?->kits_sold) }}" class="form-control"></div>
                    </div>
                    <label class="form-label required">What did you do today?</label>
                    <textarea name="content" rows="8" class="form-control" required minlength="10" placeholder="Areas visited, people met, follow-ups, problems…">{{ old('content', $today?->content) }}</textarea>
                    <div class="small text-secondary mt-1">Sent to SmartBazars admin{{ $m->isWorker() ? ' and your vendor' : '' }}.</div>
                    <button class="btn btn-primary w-100 mt-3">{{ $today ? 'Update report' : 'Submit report' }}</button>
                </form>
            </div>
        </div>
        <div class="col-lg-7">
            <div class="card">
                <div class="card-header"><h3 class="card-title">My previous reports</h3></div>
                <div class="list-group list-group-flush">
                    @forelse($rows as $r)
                        <div class="list-group-item">
                            <div class="d-flex justify-content-between"><b>{{ $r->report_date->format('d M Y') }}</b><span class="small text-secondary">met {{ $r->customers_met ?? '—' }} · kits {{ $r->kits_sold ?? '—' }}</span></div>
                            <div class="small" style="white-space:pre-wrap">{{ $r->content }}</div>
                        </div>
                    @empty
                        <div class="list-group-item text-secondary">No reports yet.</div>
                    @endforelse
                </div>
                <div class="card-footer">{{ $rows->links() }}</div>
            </div>
        </div>
    </div>
@endsection
