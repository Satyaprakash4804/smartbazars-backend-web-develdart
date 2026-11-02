{{-- Shared daily-report filter + table (admin & vendor). --}}
<div class="card">
    <div class="card-body border-bottom py-2">
        <form class="row g-2 align-items-end" action="{{ $action }}">
            @isset($sellers)
                <div class="col-6 col-md-2"><label class="form-label small">Role</label><select name="type" class="form-select"><option value="">All</option><option value="agent" @selected(($filters['type'] ?? '')==='agent')>BSOs</option><option value="worker" @selected(($filters['type'] ?? '')==='worker')>BJOs</option></select></div>
                <div class="col-6 col-md-2"><label class="form-label small">Vendor</label><select name="seller_id" class="form-select"><option value="">All</option>@foreach($sellers as $s)<option value="{{ $s->id }}" @selected(($filters['seller_id'] ?? '')==$s->id)>{{ $s->displayName() }}</option>@endforeach</select></div>
            @endisset
            <div class="col-12 col-md-3"><label class="form-label small">Person</label><select name="member_id" class="form-select"><option value="">Everyone</option>@foreach($members as $m)<option value="{{ $m->id }}" @selected(($filters['member_id'] ?? '')==$m->id)>{{ $m->user->name ?? '' }} ({{ $m->referral_code }})</option>@endforeach</select></div>
            <div class="col-6 col-md-2"><label class="form-label small">Day</label><input type="date" name="date" value="{{ $filters['date'] ?? '' }}" class="form-control"></div>
            <div class="col-6 col-md-2"><label class="form-label small">or Month</label><input type="month" name="month" value="{{ empty($filters['date']) ? ($filters['month'] ?? '') : '' }}" class="form-control"></div>
            <div class="col-12 col-md-auto d-flex gap-2"><button class="btn">Show</button><button name="download" value="1" class="btn btn-primary"><i class="ti ti-download me-1"></i>Download CSV</button></div>
        </form>
    </div>
    <div class="table-responsive"><table class="table card-table table-vcenter">
        <thead><tr><th>Date</th><th>Name</th><th>Role</th><th class="text-nowrap">Met / Kits</th><th>Report</th></tr></thead>
        <tbody>
        @forelse($rows as $r)
            <tr>
                <td class="text-nowrap">{{ $r->report_date?->format('d M Y') }}</td>
                <td>{{ $r->member?->user?->name }}<div class="small text-secondary">{{ $r->member?->referral_code }}</div></td>
                <td><span class="badge bg-{{ $r->member?->isAgent() ? 'green' : 'orange' }}-lt">{{ ucfirst($r->member?->type ?? '') }}</span>
                    @if($r->member?->isWorker())<div class="small text-secondary">{{ $r->member->seller?->displayName() }}</div>@endif</td>
                <td class="small">{{ $r->customers_met ?? '—' }} / {{ $r->kits_sold ?? '—' }}</td>
                <td style="white-space:pre-wrap;min-width:260px">{{ $r->content }}</td>
            </tr>
        @empty
            <tr><td colspan="5" class="text-center text-secondary py-4">No reports for this filter.</td></tr>
        @endforelse
        </tbody>
    </table></div>
    <div class="card-footer">{{ $rows->links() }}</div>
</div>
