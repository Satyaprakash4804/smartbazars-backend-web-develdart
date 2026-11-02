@extends('layouts.admin.app', ['page' => 'sb_network', 'sub_page' => 'sb_commissions'])
@section('title', 'Commissions')
@section('header_data')
    @php $page_title = 'Commissions'; $page_pretitle = 'SmartBazars Network'; @endphp
@endsection
@section('admin-content')
    <div class="row row-cards mb-3">
        <div class="col-sm-6"><div class="card card-sm"><div class="card-body"><div class="text-secondary">Paid on vendor sales</div><div class="h2 mb-0">₹{{ number_format($totals['vendor_sale'] ?? 0, 2) }}</div></div></div></div>
        <div class="col-sm-6"><div class="card card-sm"><div class="card-body"><div class="text-secondary">Paid on welcome kits</div><div class="h2 mb-0">₹{{ number_format($totals['welcome_kit'] ?? 0, 2) }}</div></div></div></div>
    </div>
    <div class="card">
        <div class="card-body border-bottom py-2">
            <form class="d-flex gap-2">
                <select name="source" class="form-select w-auto"><option value="">All sources</option><option value="vendor_sale" @selected(request('source')==='vendor_sale')>Vendor sales</option><option value="welcome_kit" @selected(request('source')==='welcome_kit')>Welcome kits</option></select>
                <select name="status" class="form-select w-auto"><option value="">Any status</option><option value="credited" @selected(request('status')==='credited')>Credited</option><option value="reversed" @selected(request('status')==='reversed')>Reversed</option></select>
                <button class="btn">Filter</button>
            </form>
        </div>
        @include('admin.smartbazars.network._commission_table', ['rows' => $rows, 'showMember' => true])
        <div class="card-footer">{{ $rows->links() }}</div>
    </div>
@endsection
