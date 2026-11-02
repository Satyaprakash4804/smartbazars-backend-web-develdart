@extends('layouts.admin.app', ['page' => 'sb_program', 'sub_page' => 'sb_cards'])
@section('title', 'User Cards')
@section('header_data')
    @php $page_title = 'User Cards'; $page_pretitle = 'SmartBazars Program'; @endphp
@endsection
@section('admin-content')
    <div class="card">
        <div class="card-body border-bottom py-2">
            <form class="d-flex gap-2"><input name="q" class="form-control" placeholder="Card no., name or mobile" value="{{ request('q') }}"><button class="btn">Search</button></form>
        </div>
        <div class="table-responsive">
            <table class="table card-table table-vcenter">
                <thead><tr><th>Card no.</th><th>Holder</th><th>Issued</th><th>Valid upto</th><th>Status</th><th></th></tr></thead>
                <tbody>
                @forelse($rows as $c)
                    <tr>
                        <td><code class="fs-4">{{ $c->formatted_number }}</code></td>
                        <td>{{ $c->user->name ?? '' }}<div class="small text-secondary">{{ $c->user->mobile ?? '' }}</div></td>
                        <td>{{ $c->issued_at?->format('d-m-Y') }}</td>
                        <td>{{ $c->valid_upto?->format('d-m-Y') }}</td>
                        <td>@include('smartbazars._status', ['s' => $c->status])</td>
                        <td><form method="POST" action="{{ route('admin.smartbazars.user-cards.toggle', $c) }}">@csrf
                            <button class="btn btn-sm {{ $c->status==='active' ? 'btn-outline-danger' : 'btn-outline-success' }}">{{ $c->status==='active' ? 'Block' : 'Unblock' }}</button></form></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-secondary py-4">No cards issued yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer">{{ $rows->links() }}</div>
    </div>
@endsection
