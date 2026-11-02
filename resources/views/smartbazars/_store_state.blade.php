@php $rs = $store->reviewState(); @endphp
@if($rs === 'approved')
    <span class="badge bg-green-lt">Approved</span>
@elseif($rs === 'rejected')
    <span class="badge bg-red-lt">Rejected</span>
    @if(!empty($showReason))<div class="small text-danger mt-1">{{ $store->rejection_reason }}</div>@endif
@else
    <span class="badge bg-orange-lt">Pending review</span>
@endif
