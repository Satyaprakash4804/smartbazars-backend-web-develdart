@extends('layouts.admin.app', ['page' => 'sb_network', 'sub_page' => 'sb_vendors'])
@section('title', 'Delete Vendor')
@section('header_data')
    @php $page_title = 'Delete Vendor'; $page_pretitle = 'SmartBazars Network'; @endphp
@endsection
@section('admin-content')
    @php
        $hasWorkers = $workers->total() > 0;
        $agentName = $seller->agent?->user?->name;
        $money = fn($v) => '₹' . number_format((float)$v, 2);
    @endphp
    @include('smartbazars._errors')
    <div class="row row-cards">
        <div class="col-12 col-lg-8">
            <div class="card">
                <div class="card-body d-flex flex-wrap align-items-center gap-3">
                    <span class="avatar avatar-lg bg-red-lt">{{ strtoupper(mb_substr($seller->displayName(), 0, 1)) }}</span>
                    <div class="flex-fill" style="min-width:200px">
                        <h3 class="mb-1">{{ $seller->displayName() }}</h3>
                        <div class="text-secondary small text-break">{{ $seller->user->name ?? '' }} · {{ $seller->user->email ?? '' }} · {{ $seller->user->mobile ?? '' }}</div>
                        <div class="mt-1"><code>{{ $seller->referral_code }}</code> · BSO: <b>{{ $agentName ?? 'SmartBazars (direct)' }}</b></div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">BJOs of this vendor</h3>
                    <div class="card-actions text-secondary small">{{ $workers->total() }} worker(s) · {{ $customers }} customer(s) below them · wallets {{ $money($wallets) }}</div>
                </div>
                @if($hasWorkers)
                    <div class="table-responsive">
                        <table class="table card-table table-vcenter">
                            <thead><tr><th>BJO</th><th>Code</th><th class="text-end">Customers</th><th class="text-end">Wallet</th></tr></thead>
                            <tbody>
                            @foreach($workers as $w)
                                <tr>
                                    <td><b>{{ $w->user->name ?? '#' . $w->id }}</b><div class="small text-secondary text-break">{{ $w->user->email ?? '' }}</div></td>
                                    <td><code>{{ $w->referral_code }}</code></td>
                                    <td class="text-end">{{ $w->customers_count }}</td>
                                    <td class="text-end">{{ $money($w->wallet_balance) }}</td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                    @if($workers->hasPages())<div class="card-footer">{{ $workers->links() }}</div>@endif
                @else
                    <div class="card-body text-secondary">This vendor has no workers.</div>
                @endif
            </div>
        </div>

        <div class="col-12 col-lg-4">
            <form method="POST" action="{{ route('admin.smartbazars.network.vendors.destroy', $seller) }}" id="sbVendorDelete" class="card border-danger">
                @csrf @method('DELETE')
                <div class="card-header"><h3 class="card-title text-danger">Confirm delete</h3></div>
                <div class="card-body">
                    @if($hasWorkers)
                        <div class="fw-bold mb-2">What should happen to the {{ $workers->total() }} worker(s)?</div>
                        <div class="form-selectgroup form-selectgroup-boxes d-flex flex-column mb-3">
                            <label class="form-selectgroup-item flex-fill">
                                <input type="radio" name="worker_action" value="move_same_agent" class="form-selectgroup-input" @disabled(!$seller->agent_member_id) @checked(old('worker_action') === 'move_same_agent')>
                                <div class="form-selectgroup-label d-flex align-items-center p-2">
                                    <div class="me-2"><span class="form-selectgroup-check"></span></div>
                                    <div><b>Move to another vendor of the same agent</b><div class="small text-secondary">{{ $agentName ? 'BSO: ' . $agentName : 'This vendor has no agent' }}</div></div>
                                </div>
                            </label>
                            <label class="form-selectgroup-item flex-fill">
                                <input type="radio" name="worker_action" value="move_any" class="form-selectgroup-input" @checked(old('worker_action') === 'move_any')>
                                <div class="form-selectgroup-label d-flex align-items-center p-2">
                                    <div class="me-2"><span class="form-selectgroup-check"></span></div>
                                    <div><b>Move to any other vendor</b></div>
                                </div>
                            </label>
                            <label class="form-selectgroup-item flex-fill">
                                <input type="radio" name="worker_action" value="direct" class="form-selectgroup-input" @checked(old('worker_action') === 'direct')>
                                <div class="form-selectgroup-label d-flex align-items-center p-2">
                                    <div class="me-2"><span class="form-selectgroup-check"></span></div>
                                    <div><b>Move to SmartBazars direct</b></div>
                                </div>
                            </label>
                            <label class="form-selectgroup-item flex-fill">
                                <input type="radio" name="worker_action" value="delete" class="form-selectgroup-input" @checked(old('worker_action') === 'delete')>
                                <div class="form-selectgroup-label d-flex align-items-center p-2">
                                    <div class="me-2"><span class="form-selectgroup-check"></span></div>
                                    <div><b class="text-danger">Delete the workers too</b><div class="small text-secondary">Their wallets and history are kept in Deleted Records.</div></div>
                                </div>
                            </label>
                        </div>

                        <div id="sbTargetBox" class="mb-3 d-none">
                            <label class="form-label">Search vendor (name, email, mobile or code)</label>
                            <input type="text" id="sbTargetSearch" class="form-control" placeholder="Type to search…" autocomplete="off">
                            <input type="hidden" name="target_seller_id" id="sbTargetId" value="{{ old('target_seller_id') }}">
                            <div id="sbTargetPicked" class="alert alert-success py-2 mt-2 mb-0 d-none"></div>
                            <div id="sbTargetList" class="list-group list-group-flush border rounded mt-2 d-none" style="max-height:260px;overflow:auto"></div>
                        </div>
                    @endif

                    <ul class="small ps-3">
                        <li>The vendor's records stay under <a href="{{ route('admin.smartbazars.network.deleted', ['type' => 'vendor']) }}">Deleted Records</a>.</li>
                    </ul>
                    <div class="d-grid gap-2">
                        <button class="btn btn-danger" id="sbVendorSubmit">Delete vendor</button>
                        <a href="{{ route('admin.smartbazars.network.vendors') }}" class="btn">Cancel</a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <script>
        (function () {
            const form = document.getElementById('sbVendorDelete');
            const box = document.getElementById('sbTargetBox');
            if (!box) return;
            const input = document.getElementById('sbTargetSearch'), list = document.getElementById('sbTargetList');
            const hid = document.getElementById('sbTargetId'), picked = document.getElementById('sbTargetPicked');
            const submit = document.getElementById('sbVendorSubmit');
            const url = @json(route('admin.smartbazars.network.vendors.search'));
            const exclude = {{ $seller->id }}, agentId = {{ (int)$seller->agent_member_id }};
            let timer = null, seq = 0;

            const mode = () => (form.querySelector('input[name=worker_action]:checked') || {}).value || '';
            const needsTarget = () => mode() === 'move_same_agent' || mode() === 'move_any';
            const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'}[c]));

            function refresh() {
                box.classList.toggle('d-none', !needsTarget());
                submit.disabled = !mode() || (needsTarget() && !hid.value);
            }
            function clearPick() { hid.value = ''; picked.classList.add('d-none'); refresh(); }
            async function search() {
                const my = ++seq;
                const p = new URLSearchParams({q: input.value.trim(), exclude});
                if (mode() === 'move_same_agent') p.set('agent_id', agentId);
                try {
                    const rows = await (await fetch(url + '?' + p, {headers: {'Accept': 'application/json'}})).json();
                    if (my !== seq) return;
                    list.innerHTML = rows.length ? rows.map(r => `<button type="button" class="list-group-item list-group-item-action" data-id="${r.id}" data-name="${esc(r.name)}">
                        <div class="fw-bold">${esc(r.name)} <code class="ms-1">${esc(r.code)}</code></div>
                        <div class="small text-secondary text-break">${esc(r.email)} · BSO: ${esc(r.agent)}</div></button>`).join('')
                        : '<div class="list-group-item text-secondary">No vendor found.</div>';
                    list.classList.remove('d-none');
                } catch (e) { list.innerHTML = '<div class="list-group-item text-danger">Search failed. Try again.</div>'; list.classList.remove('d-none'); }
            }
            form.querySelectorAll('input[name=worker_action]').forEach(r => r.addEventListener('change', () => { clearPick(); list.classList.add('d-none'); input.value = ''; if (needsTarget()) search(); }));
            input.addEventListener('input', () => { clearPick(); clearTimeout(timer); timer = setTimeout(search, 250); });
            input.addEventListener('focus', () => { if (needsTarget() && !hid.value) search(); });
            list.addEventListener('click', e => {
                const b = e.target.closest('button[data-id]'); if (!b) return;
                hid.value = b.dataset.id; picked.textContent = 'BJOs will move to: ' + b.dataset.name;
                picked.classList.remove('d-none'); list.classList.add('d-none'); input.value = ''; refresh();
            });
            form.addEventListener('submit', e => {
                if (!confirm(mode() === 'delete' ? 'Delete this vendor AND all its workers? Records are kept under Deleted Records.' : 'Delete this vendor? Records are kept under Deleted Records.')) e.preventDefault();
            });
            refresh();
        })();
    </script>
@endsection
