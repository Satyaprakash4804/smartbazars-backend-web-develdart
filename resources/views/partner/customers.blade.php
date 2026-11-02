@extends('layouts.admin.app', ['page' => 'customers'])
@section('title', 'My Customers')
@section('header_data')
    @php $page_title = 'My Customers'; $page_pretitle = 'Code: ' . $m->referral_code; @endphp
@endsection
@section('admin-content')
    @include('smartbazars._errors')
    <div class="row row-cards">
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header"><h3 class="card-title">Add customer</h3></div>
                <form method="POST" action="{{ route('partner.customers.store') }}" class="card-body">
                    @csrf
                    <div class="mb-2"><label class="form-label required">Name</label><input name="name" value="{{ old('name') }}" class="form-control" required></div>
                    <div class="mb-2"><label class="form-label required">Mobile</label><input name="mobile" value="{{ old('mobile') }}" class="form-control" required></div>
                    <div class="mb-3"><label class="form-label">Email</label><input type="email" name="email" value="{{ old('email') }}" class="form-control"></div>
                    <button class="btn btn-primary w-100">Add customer</button>
                    <p class="small text-secondary mt-2 mb-0">The customer logs into the SmartBazars app with the mobile + password shown after saving. They become a <b>valid</b> customer after buying the Welcome Kit with your code.</p>
                </form>
            </div>
        </div>
        <div class="col-lg-8">
            <div class="card">
                <div class="card-body border-bottom py-2"><form class="d-flex gap-2"><input name="q" class="form-control" placeholder="Search name / mobile" value="{{ request('q') }}"><button class="btn">Search</button></form></div>
                <div class="table-responsive">
                    <table class="table card-table table-vcenter">
                        <thead><tr><th>Customer</th><th>Added</th><th>Welcome kit</th><th>User card</th></tr></thead>
                        <tbody>
                        @forelse($rows as $c)
                            <tr>
                                <td>{{ $c->name }}<div class="small text-secondary">{{ $c->mobile }}</div></td>
                                <td class="small text-secondary">{{ $c->created_at->format('d M Y') }}</td>
                                <td>{!! $c->welcome_kit_purchased_at ? '<span class="badge bg-success-lt">Valid ✓</span>' : '<span class="badge bg-warning-lt">Kit pending</span>' !!}</td>
                                <td class="small">{{ $c->userCard->card_number ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-center text-secondary py-4">No customers yet.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="card-footer">{{ $rows->links() }}</div>
            </div>
        </div>
    </div>
@endsection
