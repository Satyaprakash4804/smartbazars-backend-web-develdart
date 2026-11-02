@extends('layouts.admin.app', ['page' => 'sb_network', 'sub_page' => 'sb_requests'])
@section('title', 'Create ID')
@section('header_data')
    @php $page_title = 'Create ID'; $page_pretitle = 'BSOs, workers & vendors'; @endphp
@endsection
@section('admin-content')
    @include('smartbazars._errors')
    <div class="row justify-content-center"><div class="col-lg-6">
        <div class="card">
            <div class="card-header"><h3 class="card-title">New ID</h3></div>
            <form method="POST" action="{{ route('admin.smartbazars.network.create.store') }}" class="card-body" id="create-form">
                @csrf
                <div class="mb-3"><div class="form-selectgroup w-100">
                    @foreach(['agent' => 'BSO', 'worker' => 'BJO', 'vendor' => 'Vendor'] as $k => $l)
                        <label class="form-selectgroup-item flex-fill"><input type="radio" name="type" value="{{ $k }}" class="form-selectgroup-input" @checked(old('type', 'agent') === $k) onchange="sbT(this.value)"><span class="form-selectgroup-label">{{ $l }}</span></label>
                    @endforeach
                </div></div>
                <div class="mb-2"><label class="form-label required">Full name</label><input name="name" value="{{ old('name') }}" class="form-control" required></div>
                <div class="mb-2"><label class="form-label required">Email</label><input type="email" name="email" value="{{ old('email') }}" class="form-control" required></div>
                <div class="mb-2"><label class="form-label required">Mobile</label><input name="mobile" value="{{ old('mobile') }}" class="form-control" required></div>

                <div data-for="agent vendor" class="mb-2 d-none"><label class="form-label"><span data-for="agent">Upline agent</span><span data-for="vendor" class="d-none">Onboarding agent (upline)</span> <span class="text-secondary">(optional)</span></label>
                    <select name="parent_id" class="form-select"><option value="">— Directly under SmartBazars —</option>@foreach($agents as $a)<option value="{{ $a->id }}" @selected(old('parent_id') == $a->id)>{{ $a->user->name ?? '' }} ({{ $a->referral_code }})</option>@endforeach</select></div>
                <div data-for="worker" class="mb-2 d-none"><label class="form-label required">Works for vendor</label>
                    <select name="seller_id" class="form-select"><option value="">Select vendor…</option>@foreach($sellers as $s)<option value="{{ $s->id }}" @selected(old('seller_id')==$s->id)>{{ $s->user->name ?? '#'.$s->id }}</option>@endforeach</select></div>
                <div data-for="vendor" class="d-none">
                    <div class="mb-2"><label class="form-label required">Store / business name</label><input name="meta[store_name]" value="{{ old('meta.store_name') }}" class="form-control"></div>
                    <div class="mb-2"><label class="form-label required">Address</label><input name="meta[address]" value="{{ old('meta.address') }}" class="form-control"></div>
                    <div class="row g-2 mb-2">
                        <div class="col-6"><label class="form-label">Landmark</label><input name="meta[landmark]" value="{{ old('meta.landmark') }}" class="form-control"></div>
                        <div class="col-6"><label class="form-label required">City</label><input name="meta[city]" value="{{ old('meta.city') }}" class="form-control"></div>
                        <div class="col-6"><label class="form-label required">State</label><input name="meta[state]" value="{{ old('meta.state') }}" class="form-control"></div>
                        <div class="col-6"><label class="form-label required">PIN code</label><input name="meta[zipcode]" value="{{ old('meta.zipcode') }}" class="form-control"></div>
                    </div>
                </div>

                <div class="mb-3 mt-3">
                    <label class="form-label">Joining payment</label>
                    @if($feesEnabled)
                        <label class="form-check"><input type="radio" name="payment_mode" value="collect" class="form-check-input" @checked(old('payment_mode', 'collect') === 'collect')><span class="form-check-label">Collect payment — ID is created after they pay <b data-fee>₹</b></span></label>
                        <label class="form-check"><input type="radio" name="payment_mode" value="waive" class="form-check-input" @checked(old('payment_mode') === 'waive')><span class="form-check-label">No payment — create the ID now</span></label>
                    @else
                        <div class="text-secondary small">Joining payments are switched off in settings — the ID is created immediately.</div>
                    @endif
                </div>
                <button class="btn btn-primary w-100">Create ID</button>
            </form>
        </div>
    </div></div>
    <script>
        var sbFees = @json($fees);
        function sbT(t) {
            document.querySelectorAll('#create-form [data-for]').forEach(el => el.classList.toggle('d-none', !el.dataset.for.split(' ').includes(t)));
            var f = document.querySelector('[data-fee]'); if (f) f.textContent = '(₹' + Number(sbFees[t]).toFixed(2) + ')';
        }
        sbT(document.querySelector('#create-form input[name=type]:checked').value);
    </script>
@endsection
