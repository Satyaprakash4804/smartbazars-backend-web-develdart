@extends('layouts.seller.app', ['page' => 'sb_vendor_business', 'sub_page' => 'sb_wreports'])
@section('title', 'BJO Daily Reports')
@section('header_data')
    @php $page_title = 'BJO Daily Reports'; $page_pretitle = 'Your workers'; @endphp
@endsection
@section('seller-content')
    @include('smartbazars._report_table', ['rows' => $rows, 'filters' => $filters, 'members' => $workers, 'action' => route('seller.smartbazars.reports')])
@endsection
