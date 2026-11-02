<div class="table-responsive">
    <table class="table card-table table-vcenter">
        <thead><tr><th>Date</th>@if($showMember)<th>Earner</th>@endif<th>Source</th><th>Role</th><th>Base</th><th>Amount</th><th>Status</th><th>Note</th></tr></thead>
        <tbody>
        @forelse($rows as $r)
            <tr>
                <td class="small text-secondary text-nowrap">{{ $r->created_at->format('d M Y H:i') }}</td>
                @if($showMember)
                    <td>{{ $r->member?->user?->name ?? ('Vendor: ' . ($r->seller?->user?->name ?? '')) }}</td>
                @endif
                <td>{{ $r->source === 'vendor_sale' ? 'Vendor sale #'.$r->source_id : 'Welcome kit #'.$r->source_id }}</td>
                <td class="small">{{ ucwords(str_replace('_', ' ', $r->role)) }}</td>
                <td class="small">{{ $r->rate ? '₹'.number_format($r->base_amount,2).' × '.rtrim(rtrim($r->rate,'0'),'.').'%' : 'Fixed' }}</td>
                <td class="fw-bold">₹{{ number_format($r->amount, 2) }}</td>
                <td>@include('smartbazars._status', ['s' => $r->status])</td>
                <td class="small text-secondary">{{ \Illuminate\Support\Str::limit($r->description, 60) }}</td>
            </tr>
        @empty
            <tr><td colspan="8" class="text-center text-secondary py-4">No commissions yet.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
