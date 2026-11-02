@extends('layouts.admin.app', ['page' => 'sb_business', 'sub_page' => 'sb_reports'])
@section('title', 'Daily Reports')
@section('header_data')
    @php $page_title = 'Daily Reports — BSOs & BJOs'; $page_pretitle = $missingToday . ' active members have not reported today'; @endphp
@endsection
@section('admin-content')
    @include('smartbazars._report_table', ['rows' => $rows, 'filters' => $filters, 'members' => $members, 'sellers' => $sellers, 'action' => route('admin.smartbazars.reports')])
@endsection
