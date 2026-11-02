@extends('layouts.admin.app', ['page' => 'sb_network', 'sub_page' => 'sb_tree'])
@section('title', 'BSO Hierarchy')
@section('header_data')
    @php $page_title = 'BSO Hierarchy'; $page_pretitle = 'SmartBazars Network'; @endphp
@endsection
@section('admin-content')
    <div class="card">
        <div class="card-body">
            <div class="mb-3 fw-bold"><span class="avatar avatar-sm bg-primary text-white me-2">SB</span>SmartBazars (Admin)</div>
            @if(empty($tree))
                <p class="text-secondary">No agents yet. Create one from <a href="{{ route('admin.smartbazars.network.agents') }}">BSOs</a>.</p>
            @else
                @include('smartbazars._tree', ['nodes' => $tree, 'adminLinks' => true])
            @endif
        </div>
    </div>
@endsection
