@extends('layouts.admin.app', ['page' => 'team'])
@section('title', 'IDs added')
@section('header_data')
    @php $page_title = $isToday ? 'IDs added today' : 'IDs added on ' . $day->format('d M Y'); $page_pretitle = 'My downline'; @endphp
@endsection
@section('admin-content')
    @php
        $rows = collect();
        foreach ($lists['agents'] as $a) {
            $rows->push(['type' => 'BSO', 'cls' => 'bg-blue-lt', 'name' => $a->user->name ?? '', 'sub' => $a->referral_code, 'mobile' => $a->user->mobile ?? '', 'rel' => 'Upline: ' . ($a->parent?->user?->name ?? 'SmartBazars'), 'at' => $a->created_at]);
        }
        foreach ($lists['vendors'] as $v) {
            $rows->push(['type' => 'Vendor', 'cls' => 'bg-orange-lt', 'name' => $v->user->name ?? '', 'sub' => $v->referral_code, 'mobile' => $v->user->mobile ?? '', 'rel' => 'BSO: ' . ($v->agent?->user?->name ?? '—'), 'at' => $v->created_at]);
        }
        foreach ($lists['workers'] as $w) {
            $rows->push(['type' => 'BJO', 'cls' => 'bg-green-lt', 'name' => $w->user->name ?? '', 'sub' => $w->referral_code, 'mobile' => $w->user->mobile ?? '', 'rel' => 'Vendor: ' . ($w->seller?->user?->name ?? '—'), 'at' => $w->created_at]);
        }
        $rows = $rows->sortByDesc('at')->values();
    @endphp
    <div class="card mb-3"><div class="card-body d-flex flex-wrap gap-3 justify-content-between align-items-center">
        <div>
            <div class="h2 mb-0">{{ $rows->count() }} {{ \Illuminate\Support\Str::plural('ID', $rows->count()) }} added {{ $isToday ? 'today' : 'on ' . $day->format('d M Y') }}</div>
            <div class="text-secondary">{{ $lists['agents']->count() }} agents · {{ $lists['vendors']->count() }} vendors · {{ $lists['workers']->count() }} workers — across your downline</div>
        </div>
        <form method="GET" class="d-flex gap-2 align-items-center">
            <input type="date" name="date" value="{{ $day->toDateString() }}" max="{{ now()->toDateString() }}" class="form-control" onchange="this.form.submit()">
            @unless($isToday)<a href="{{ route('partner.team.added') }}" class="btn">Today</a>@endunless
        </form>
    </div></div>
    <div class="card">
        <div class="table-responsive"><table class="table table-vcenter card-table">
            <thead><tr><th>Type</th><th>Name</th><th>Code</th><th>Mobile</th><th>Linked to</th><th>Added at</th></tr></thead>
            <tbody>
            @forelse($rows as $r)
                <tr>
                    <td><span class="badge {{ $r['cls'] }}">{{ $r['type'] }}</span></td>
                    <td>{{ $r['name'] }}</td>
                    <td><code>{{ $r['sub'] }}</code></td>
                    <td>{{ $r['mobile'] }}</td>
                    <td class="text-secondary">{{ $r['rel'] }}</td>
                    <td class="text-secondary">{{ $r['at']->format('d M Y, h:i A') }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="text-center text-secondary py-4">No IDs were added {{ $isToday ? 'today' : 'on this day' }} in your downline.</td></tr>
            @endforelse
            </tbody>
        </table></div>
    </div>
    <a href="{{ route('partner.dashboard') }}" class="btn mt-3">Back to dashboard</a>
@endsection
