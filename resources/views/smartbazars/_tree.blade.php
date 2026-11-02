{{-- Recursive agent tree. $nodes = [['member'=>NetworkMember,'children'=>[...]], ...] --}}
<ul class="sb-tree">
    @foreach($nodes as $node)
        @php $m = $node['member']; @endphp
        @continue(!$m)
        <li>
            <div class="sb-node {{ $m->status !== 'active' ? 'opacity-50' : '' }}">
                <span class="avatar avatar-xs bg-green-lt me-1">{{ strtoupper(substr($m->user->name ?? 'A', 0, 1)) }}</span>
                @if(!empty($adminLinks))
                    <a href="{{ route('admin.smartbazars.network.show', $m) }}" class="fw-bold">{{ $m->user->name ?? '-' }}</a>
                @else
                    <span class="fw-bold">{{ $m->user->name ?? '-' }}</span>
                @endif
                <span class="text-secondary small ms-1">{{ $m->referral_code }} · L{{ $m->level }}</span>
                <span class="badge bg-blue-lt ms-1" title="Vendors onboarded">{{ $m->vendors_count ?? 0 }} vendors</span>
            </div>
            @if(!empty($node['children']))
                @include('smartbazars._tree', ['nodes' => $node['children'], 'adminLinks' => $adminLinks ?? false])
            @endif
        </li>
    @endforeach
</ul>
@once
    <style>
        .sb-tree { list-style: none; padding-left: 1.25rem; margin: 0; border-left: 2px dashed #c9e7cf; }
        .sb-tree > li { margin: .35rem 0; position: relative; }
        .sb-tree > li::before { content: ''; position: absolute; left: -1.25rem; top: .9rem; width: 1rem; border-top: 2px dashed #c9e7cf; }
        .sb-node { display: inline-flex; align-items: center; flex-wrap: wrap; gap: .15rem; padding: .25rem .6rem; border: 1px solid var(--tblr-border-color); border-radius: .5rem; background: var(--tblr-bg-surface); }
    </style>
@endonce
