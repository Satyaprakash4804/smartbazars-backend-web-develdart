@extends('layouts.admin.app', ['page' => 'sb_network', 'sub_page' => 'sb_mapping'])
@section('title', 'Network Mapping')
@section('header_data')
    @php $page_title = 'Network Mapping'; $page_pretitle = 'SmartBazars Network'; @endphp
@endsection
@section('admin-content')
<style>
    #nm { --nm-line: var(--tblr-border-color, #e6e7e9); --nm-surface: var(--tblr-bg-surface, #fff); --nm-muted: var(--tblr-secondary, #667382);
          --nm-hover: var(--tblr-bg-surface-secondary, #f6f8fb); --nm-primary: var(--tblr-primary, #206bc4);
          --nm-agent: #206bc4; --nm-vendor: #f76707; --nm-worker: #0ca678; }
    #nm .nm-stat { display:flex; align-items:center; gap:.75rem; padding:.85rem 1rem; }
    #nm .nm-stat b { font-size:1.35rem; line-height:1.1; display:block; }
    #nm .nm-ico { width:38px; height:38px; border-radius:10px; display:grid; place-items:center; font-weight:700; color:#fff; flex:none; font-size:.8rem; }
    #nm .nm-toolbar { display:flex; flex-wrap:wrap; gap:.5rem; align-items:center; }
    #nm .nm-search { position:relative; flex:1 1 240px; min-width:200px; }
    #nm .nm-search-results { position:absolute; z-index:30; top:calc(100% + 4px); left:0; right:0; background:var(--nm-surface); border:1px solid var(--nm-line);
          border-radius:10px; box-shadow:0 8px 24px rgba(0,0,0,.12); max-height:320px; overflow:auto; display:none; }
    #nm .nm-search-results button { display:flex; width:100%; gap:.6rem; align-items:center; padding:.5rem .75rem; border:0; background:none; text-align:left; color:inherit; }
    #nm .nm-search-results button:hover { background:var(--nm-hover); }
    #nm .nm-tree-wrap { padding:.75rem; min-height:320px; max-height:calc(100vh - 290px); overflow:auto; }
    #nm .nm-kids { margin-left:19px; padding-left:12px; border-left:2px solid var(--nm-line); }
    #nm .nm-row { display:flex; align-items:center; gap:.6rem; padding:.45rem .6rem; margin:3px 0; border:1px solid transparent; border-radius:10px; background:var(--nm-surface);
          transition:background .12s, border-color .12s, box-shadow .12s; position:relative; }
    #nm .nm-row:hover { background:var(--nm-hover); }
    #nm .nm-row.is-selected { border-color:var(--nm-primary); box-shadow:0 0 0 3px rgba(32,107,196,.12); }
    #nm .nm-row.nm-flash { animation:nmflash 1.6s ease; }
    @keyframes nmflash { 0%,60% { background:rgba(247,103,7,.2); } 100% { background:var(--nm-surface); } }
    #nm .nm-row[draggable="true"] { cursor:grab; }
    #nm .nm-grip { color:var(--nm-muted); opacity:.5; flex:none; font-size:14px; letter-spacing:-2px; user-select:none; }
    #nm .nm-caret { width:22px; height:22px; flex:none; border:0; background:none; color:var(--nm-muted); border-radius:6px; display:grid; place-items:center; padding:0; }
    #nm .nm-caret:hover { background:var(--nm-line); }
    #nm .nm-caret svg { transition:transform .15s; } #nm .nm-caret.is-open svg { transform:rotate(90deg); }
    #nm .nm-caret-space { width:22px; flex:none; }
    #nm .nm-main { flex:1; min-width:0; }
    #nm .nm-name { font-weight:600; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
    #nm .nm-sub { font-size:.78rem; color:var(--nm-muted); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
    #nm .nm-badges { display:flex; gap:.35rem; flex-wrap:wrap; justify-content:flex-end; }
    #nm .nm-badge { font-size:.68rem; padding:.15rem .5rem; border-radius:20px; background:var(--nm-hover); color:var(--nm-muted); border:1px solid var(--nm-line); white-space:nowrap; }
    #nm .nm-dot { width:9px; height:9px; border-radius:50%; flex:none; background:#2fb344; } #nm .nm-dot.off { background:#d63939; } #nm .nm-dot.wait { background:#f59f00; }
    #nm .nm-kebab { border:0; background:none; width:30px; height:30px; border-radius:8px; color:var(--nm-muted); flex:none; font-size:18px; line-height:1; }
    #nm .nm-kebab:hover { background:var(--nm-line); }
    #nm .nm-menu { position:fixed; z-index:1080; min-width:190px; background:var(--nm-surface); border:1px solid var(--nm-line); border-radius:10px; box-shadow:0 10px 30px rgba(0,0,0,.18); padding:4px; }
    #nm .nm-menu button, #nm .nm-menu a { display:block; width:100%; text-align:left; padding:.5rem .7rem; border:0; background:none; border-radius:7px; color:inherit; text-decoration:none; font-size:.9rem; }
    #nm .nm-menu button:hover, #nm .nm-menu a:hover { background:var(--nm-hover); }
    #nm .nm-root { background:linear-gradient(90deg, rgba(32,107,196,.08), transparent); border:1px dashed var(--nm-line); }
    #nm .nm-more { margin:4px 0 4px 34px; }
    #nm .nm-empty { text-align:center; color:var(--nm-muted); padding:1.25rem .5rem; font-size:.9rem; }
    /* drag feedback */
    #nm.is-dragging .nm-row.nm-valid { border-color:rgba(32,107,196,.45); border-style:dashed; }
    #nm.is-dragging .nm-row.nm-over, #nm.is-dragging .nm-drop.nm-over { background:rgba(32,107,196,.14); border-color:var(--nm-primary); border-style:solid; box-shadow:0 0 0 3px rgba(32,107,196,.18); }
    #nm.is-dragging .nm-row.nm-dragged { opacity:.4; }
    #nm .nm-drop { border:2px dashed var(--nm-line); border-radius:12px; padding:.5rem; transition:all .12s; }
    #nm.is-dragging .nm-drop.nm-valid { border-color:rgba(32,107,196,.5); background:rgba(32,107,196,.04); }
    #nm .nm-side-list { max-height:260px; overflow:auto; }
    #nm .nm-log { font-size:.82rem; padding:.5rem 0; border-bottom:1px solid var(--nm-line); } #nm .nm-log:last-child { border-bottom:0; }
    #nm .nm-sticky { position:sticky; top:12px; display:flex; flex-direction:column; gap:1rem; }
    /* modal + toast */
    #nm-modal, #nm-picker { position:fixed; inset:0; z-index:1070; background:rgba(15,23,42,.55); display:none; align-items:center; justify-content:center; padding:1rem; }
    #nm-modal.is-open, #nm-picker.is-open { display:flex; }
    #nm-modal .nm-dialog, #nm-picker .nm-dialog { background:var(--tblr-bg-surface,#fff); color:var(--tblr-body-color,inherit); border-radius:14px; width:100%; max-width:480px; box-shadow:0 24px 60px rgba(0,0,0,.3); overflow:hidden; }
    #nm-picker .nm-dialog { max-height:80vh; display:flex; flex-direction:column; }
    #nm-picker .nm-pick-list { overflow:auto; padding:.25rem .5rem .75rem; }
    #nm-picker .nm-pick { display:flex; gap:.6rem; align-items:center; width:100%; border:0; background:none; text-align:left; padding:.55rem .6rem; border-radius:9px; color:inherit; }
    #nm-picker .nm-pick:hover { background:var(--tblr-bg-surface-secondary,#f6f8fb); } #nm-picker .nm-pick[disabled] { opacity:.4; cursor:not-allowed; }
    #nm-toasts { position:fixed; right:16px; bottom:16px; z-index:1090; display:flex; flex-direction:column; gap:8px; }
    #nm-toasts .nm-toast { background:#1d273b; color:#fff; padding:.65rem .9rem; border-radius:10px; font-size:.9rem; max-width:340px; box-shadow:0 8px 24px rgba(0,0,0,.25); }
    #nm-toasts .nm-toast.err { background:#d63939; } #nm-toasts .nm-toast.ok { background:#2fb344; }
    @media (max-width: 767px) {
        #nm .nm-badges .nm-badge.hide-sm { display:none; }
        #nm .nm-tree-wrap { max-height:none; padding:.4rem; }
        #nm .nm-kids { margin-left:11px; padding-left:8px; }
        #nm-picker, #nm-modal { align-items:flex-end; padding:0; } #nm-picker .nm-dialog, #nm-modal .nm-dialog { max-width:none; border-radius:16px 16px 0 0; }
    }
</style>

<div id="nm">
    <div class="row g-2 mb-3">
        <div class="col-6 col-lg-3"><div class="card"><div class="nm-stat"><span class="nm-ico" style="background:var(--nm-agent)">A</span><div><b id="st-agents">–</b><span class="text-secondary small">BSOs</span></div></div></div></div>
        <div class="col-6 col-lg-3"><div class="card"><div class="nm-stat"><span class="nm-ico" style="background:var(--nm-vendor)">V</span><div><b id="st-vendors">–</b><span class="text-secondary small">Vendors</span></div></div></div></div>
        <div class="col-6 col-lg-3"><div class="card"><div class="nm-stat"><span class="nm-ico" style="background:var(--nm-worker)">W</span><div><b id="st-workers">–</b><span class="text-secondary small">BJOs</span></div></div></div></div>
        <div class="col-6 col-lg-3"><div class="card"><div class="nm-stat"><span class="nm-ico" style="background:#f59f00">!</span><div><b id="st-unassigned">–</b><span class="text-secondary small">Vendors without agent</span></div></div></div></div>
    </div>

    <div class="row g-3">
        <div class="col-xl-8">
            <div class="card">
                <div class="card-header">
                    <div class="nm-toolbar w-100">
                        <div class="nm-search">
                            <input id="nm-q" type="search" class="form-control" placeholder="Search name, mobile, email or code…" autocomplete="off">
                            <div class="nm-search-results" id="nm-results"></div>
                        </div>
                        <label class="form-check form-check-inline m-0"><input class="form-check-input" type="checkbox" id="f-vendor" checked><span class="form-check-label">Vendors</span></label>
                        <label class="form-check form-check-inline m-0"><input class="form-check-input" type="checkbox" id="f-worker" checked><span class="form-check-label">BJOs</span></label>
                        <div class="btn-group ms-auto">
                            <button class="btn btn-sm" id="nm-expand">Expand agents</button>
                            <button class="btn btn-sm" id="nm-collapse">Collapse</button>
                            <button class="btn btn-sm" id="nm-refresh" title="Reload">↻</button>
                        </div>
                    </div>
                </div>
                <div class="nm-tree-wrap" id="nm-tree" role="tree"><div class="nm-empty">Loading network…</div></div>
                <div class="card-footer small text-secondary" id="nm-hint"></div>
            </div>
        </div>

        <div class="col-xl-4">
            <div class="nm-sticky">
                <div class="card">
                    <div class="card-header"><h3 class="card-title">Vendors without an agent</h3><span class="badge bg-warning-lt ms-auto" id="un-count">0</span></div>
                    <div class="card-body nm-drop" id="nm-unassigned" data-drop="unassigned">
                        <div class="nm-side-list" id="nm-un-list"></div>
                    </div>
                    <div class="card-footer small text-secondary">Drop a vendor here to unlink it from its agent.</div>
                </div>
                <div class="card">
                    <div class="card-header"><h3 class="card-title">Details</h3></div>
                    <div class="card-body" id="nm-details"><div class="text-secondary small">Select an agent, vendor or worker to see their upline chain and quick actions.</div></div>
                </div>
                <div class="card">
                    <div class="card-header"><h3 class="card-title">Recent changes</h3></div>
                    <div class="card-body py-1" id="nm-logs"><div class="text-secondary small py-2">No changes yet.</div></div>
                </div>
            </div>
        </div>
    </div>

    <div id="nm-modal" role="dialog" aria-modal="true"><div class="nm-dialog" id="nm-modal-body"></div></div>
    <div id="nm-picker" role="dialog" aria-modal="true"><div class="nm-dialog" id="nm-picker-body"></div></div>
    <div id="nm-toasts"></div>
</div>

@php
    $nmUrls = [
        'data' => route('admin.smartbazars.network.mapping.data'),
        'search' => route('admin.smartbazars.network.mapping.search'),
        'workers' => route('admin.smartbazars.network.mapping.workers', ['seller' => '__ID__']),
        'move' => route('admin.smartbazars.network.mapping.move'),
        'status' => route('admin.smartbazars.network.mapping.status', ['member' => '__ID__']),
        'editMember' => route('admin.smartbazars.network.member.edit', ['member' => '__ID__']),
        'editVendor' => route('admin.sellers.edit', '__ID__'),
        'idCard' => route('admin.smartbazars.network.id-card', ['member' => '__ID__']),
    ];
    $nmCsrf = csrf_token();
@endphp
<script>
(function () {
    'use strict';
    const URLS = @json($nmUrls);
    const CSRF = @json($nmCsrf);
    const url = (k, id) => URLS[k].replace('__ID__', encodeURIComponent(id));
    const COLORS = { agent: 'var(--nm-agent)', vendor: 'var(--nm-vendor)', worker: 'var(--nm-worker)' };
    const PAGE = 50;
    const canDragDevice = window.matchMedia && window.matchMedia('(hover:hover) and (pointer:fine)').matches;

    const S = {
        agents: new Map(), vendors: new Map(), workers: new Map(), loadedWorkers: new Set(),
        open: new Set(), cap: {}, selected: null, show: { vendor: true, worker: true }, logs: [],
    };
    let drag = null, hoverTimer = null;

    // ---------------------------------------------------------------- tiny DOM helper (no innerHTML with data → XSS-safe)
    function h(tag, props, ...kids) {
        const el = document.createElement(tag);
        for (const [k, v] of Object.entries(props || {})) {
            if (v === false || v == null) continue;
            if (k === 'class') el.className = v;
            else if (k === 'text') el.textContent = v;
            else if (k.startsWith('on')) el.addEventListener(k.slice(2), v);
            else if (k === 'style') el.style.cssText = v;
            else el.setAttribute(k, v === true ? '' : v);
        }
        for (const kid of kids.flat()) if (kid != null && kid !== false) el.append(kid.nodeType ? kid : document.createTextNode(String(kid)));
        return el;
    }
    const $ = (id) => document.getElementById(id);
    const initials = (n) => (n || '?').trim().split(/\s+/).slice(0, 2).map(w => w[0]).join('').toUpperCase();
    const caretSvg = () => { const s = document.createElementNS('http://www.w3.org/2000/svg', 'svg'); s.setAttribute('width', '14'); s.setAttribute('height', '14'); s.setAttribute('viewBox', '0 0 24 24'); s.setAttribute('fill', 'none'); s.setAttribute('stroke', 'currentColor'); s.setAttribute('stroke-width', '2.5'); const p = document.createElementNS('http://www.w3.org/2000/svg', 'path'); p.setAttribute('d', 'M9 6l6 6-6 6'); s.append(p); return s; };

    function toast(msg, kind) {
        const t = h('div', { class: 'nm-toast ' + (kind || ''), text: msg });
        $('nm-toasts').append(t); setTimeout(() => t.remove(), kind === 'err' ? 6000 : 3500);
    }
    async function api(u, opts) {
        const res = await fetch(u, Object.assign({ headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' }, opts));
        let body = {}; try { body = await res.json(); } catch (e) { /* non-JSON */ }
        if (!res.ok) throw new Error(body.message || (body.errors && Object.values(body.errors)[0][0]) || ('Request failed (' + res.status + ')'));
        return body;
    }

    // ---------------------------------------------------------------- graph helpers
    const key = (t, id) => t + ':' + id;
    const agentChildren = (pid) => [...S.agents.values()].filter(a => (a.parent_id || null) === (pid || null)).sort((a, b) => a.name.localeCompare(b.name));
    const vendorsOf = (aid) => [...S.vendors.values()].filter(v => v.agent_id === aid).sort((a, b) => a.name.localeCompare(b.name));
    const workersOf = (sid) => [...S.workers.values()].filter(w => w.seller_id === sid).sort((a, b) => a.name.localeCompare(b.name));
    function agentChain(id) { const out = []; let cur = S.agents.get(id), guard = 0; while (cur && guard++ < 100) { out.unshift(cur); cur = cur.parent_id ? S.agents.get(cur.parent_id) : null; } return out; }
    function isDescendant(candidateId, ancestorId) { let cur = S.agents.get(candidateId), guard = 0; while (cur && guard++ < 100) { if (cur.id === ancestorId) return true; cur = cur.parent_id ? S.agents.get(cur.parent_id) : null; } return false; }
    const levelOf = (a) => agentChain(a.id).length;
    const vendorLevel = (v) => (v.agent_id && S.agents.get(v.agent_id)) ? levelOf(S.agents.get(v.agent_id)) + 1 : null;
    const workerLevel = (w) => { const v = S.vendors.get(w.seller_id); const l = v ? vendorLevel(v) : null; return l ? l + 1 : null; };
    const nodeLevel = (t, n) => t === 'agent' ? levelOf(n) : t === 'vendor' ? vendorLevel(n) : workerLevel(n);
    function descendantCount(id) { let n = 0; const stack = agentChildren(id); while (stack.length) { const c = stack.pop(); n++; stack.push(...agentChildren(c.id)); } return n; }
    const nodeOf = (t, id) => (t === 'agent' ? S.agents : t === 'vendor' ? S.vendors : S.workers).get(id);
    const label = (t, id) => (nodeOf(t, id) || {}).name || ('#' + id);

    // ---------------------------------------------------------------- drag & drop rules
    function canDrop(d, t) {
        if (!d || !t) return false;
        if (d.type === 'agent') {
            const a = S.agents.get(d.id); if (!a) return false;
            if (t.kind === 'root') return a.parent_id != null;
            if (t.kind === 'agent') return t.id !== a.id && a.parent_id !== t.id && !isDescendant(t.id, a.id);
        }
        if (d.type === 'vendor') {
            const v = S.vendors.get(d.id); if (!v) return false;
            if (t.kind === 'unassigned') return v.agent_id != null;
            if (t.kind === 'agent') { const ag = S.agents.get(t.id); return !!ag && ag.status === 'active' && v.agent_id !== t.id; }
        }
        if (d.type === 'worker') {
            const w = S.workers.get(d.id); return !!w && t.kind === 'vendor' && w.seller_id !== t.id;
        }
        return false;
    }
    const dropInfo = (el) => { const z = el && el.closest('[data-drop]'); return z ? { el: z, kind: z.dataset.drop, id: z.dataset.id ? Number(z.dataset.id) : null } : null; };

    function startDrag(e, type, id) {
        drag = { type, id };
        e.dataTransfer.effectAllowed = 'move'; e.dataTransfer.setData('text/plain', type + ':' + id);
        requestAnimationFrame(() => {
            $('nm').classList.add('is-dragging');
            document.querySelectorAll('#nm [data-drop]').forEach(z => { if (canDrop(drag, { kind: z.dataset.drop, id: z.dataset.id ? Number(z.dataset.id) : null })) z.classList.add('nm-valid'); });
            const me = document.querySelector('#nm .nm-row[data-key="' + key(type, id) + '"]'); if (me) me.classList.add('nm-dragged');
        });
    }
    function endDrag() {
        drag = null; clearTimeout(hoverTimer);
        $('nm').classList.remove('is-dragging');
        document.querySelectorAll('#nm .nm-valid, #nm .nm-over, #nm .nm-dragged').forEach(z => z.classList.remove('nm-valid', 'nm-over', 'nm-dragged'));
    }
    document.addEventListener('dragover', (e) => {
        if (!drag) return; const t = dropInfo(e.target); if (!t) return;
        if (canDrop(drag, t)) {
            e.preventDefault(); e.dataTransfer.dropEffect = 'move';
            document.querySelectorAll('#nm .nm-over').forEach(z => z !== t.el && z.classList.remove('nm-over')); t.el.classList.add('nm-over');
            const k = t.kind === 'agent' ? key('agent', t.id) : t.kind === 'vendor' ? key('vendor', t.id) : null;
            if (k && !S.open.has(k)) { clearTimeout(hoverTimer); hoverTimer = setTimeout(() => { S.open.add(k); render(true); }, 800); }
        }
    });
    document.addEventListener('dragleave', (e) => { const t = dropInfo(e.target); if (t && !t.el.contains(e.relatedTarget)) { t.el.classList.remove('nm-over'); clearTimeout(hoverTimer); } });
    document.addEventListener('drop', (e) => {
        if (!drag) return; const t = dropInfo(e.target); if (!t || !canDrop(drag, t)) return;
        e.preventDefault(); const d = drag; endDrag(); askMove(d.type, d.id, t.kind === 'unassigned' || t.kind === 'root' ? null : t.id);
    });
    document.addEventListener('dragend', endDrag);

    // ---------------------------------------------------------------- confirm + execute move
    function targetName(type, targetId) { return targetId == null ? (type === 'vendor' ? 'Unassigned (no agent)' : 'SmartBazars (top level)') : label(type === 'worker' ? 'vendor' : 'agent', targetId); }
    function currentParentId(type, id) { const n = nodeOf(type, id); return type === 'agent' ? n.parent_id : type === 'vendor' ? n.agent_id : n.seller_id; }

    function askMove(type, id, targetId) {
        const n = nodeOf(type, id); if (!n) return;
        const from = currentParentId(type, id);
        const fromName = from == null ? targetName(type, null) : label(type === 'worker' ? 'vendor' : 'agent', from);
        const notes = [];
        if (type === 'agent') { const c = descendantCount(id); notes.push(c ? c + ' sub-agent' + (c > 1 ? 's' : '') + ' and all their vendors move together with ' + n.name + '.' : n.name + ' has no downline, only this agent moves.'); }
        if (type === 'vendor') notes.push('Commissions on this vendor’s sales and repurchases will follow the new agent chain.');
        if (type === 'worker') notes.push('Customers referred by this worker stay with the worker.');
        notes.push('Only future commissions change. Credited commissions are never rewritten.');
        const body = $('nm-modal-body'); body.replaceChildren(
            h('div', { class: 'p-3 p-md-4' },
                h('div', { class: 'd-flex align-items-center gap-3 mb-3' }, h('span', { class: 'nm-ico', style: 'background:' + COLORS[type], text: initials(n.name) }),
                    h('div', {}, h('div', { class: 'fw-bold', text: 'Move ' + n.name + '?' }), h('div', { class: 'small text-secondary text-capitalize', text: type }))),
                h('div', { class: 'p-3 rounded mb-3', style: 'background:var(--nm-hover)' },
                    h('div', { class: 'small text-secondary', text: 'From' }), h('div', { class: 'fw-semibold mb-2', text: fromName }),
                    h('div', { class: 'small text-secondary', text: 'To' }), h('div', { class: 'fw-semibold', style: 'color:var(--nm-primary)', text: targetName(type, targetId) })),
                h('ul', { class: 'small text-secondary ps-3 mb-3' }, notes.map(t => h('li', { text: t }))),
                h('div', { class: 'd-flex gap-2 justify-content-end' },
                    h('button', { class: 'btn', text: 'Cancel', onclick: closeModal }),
                    h('button', { class: 'btn btn-primary', id: 'nm-confirm', text: 'Confirm move', onclick: (ev) => doMove(ev.currentTarget, type, id, targetId) }))));
        $('nm-modal').classList.add('is-open'); setTimeout(() => $('nm-confirm') && $('nm-confirm').focus(), 30);
    }
    function closeModal() { $('nm-modal').classList.remove('is-open'); $('nm-picker').classList.remove('is-open'); }
    $('nm-modal').addEventListener('click', (e) => { if (e.target.id === 'nm-modal') closeModal(); });
    $('nm-picker').addEventListener('click', (e) => { if (e.target.id === 'nm-picker') closeModal(); });
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape') { closeModal(); closeMenu(); } });

    async function doMove(btn, type, id, targetId) {
        btn.disabled = true; btn.textContent = 'Moving…';
        try {
            const res = await api(URLS.move, { method: 'POST', body: JSON.stringify({ type, id, target_id: targetId }) });
            const n = nodeOf(type, id), old = currentParentId(type, id);
            if (type === 'agent') n.parent_id = targetId; else if (type === 'vendor') n.agent_id = targetId;
            else { n.seller_id = targetId; const ov = S.vendors.get(old), nv = S.vendors.get(targetId); if (ov) ov.workers_count = Math.max(0, ov.workers_count - 1); if (nv) nv.workers_count++; }
            if (targetId != null) S.open.add(key(type === 'worker' ? 'vendor' : 'agent', targetId));
            if (res.log) { S.logs.unshift(res.log); S.logs = S.logs.slice(0, 15); }
            closeModal(); S.selected = key(type, id); await revealPath(type, id); render(); flash(type, id); toast(n.name + ' moved to ' + targetName(type, targetId), 'ok');
        } catch (err) { closeModal(); toast(err.message, 'err'); }
    }

    // ---------------------------------------------------------------- "Move to…" picker (touch / keyboard friendly)
    function openPicker(type, id, byLevel) {
        const n = nodeOf(type, id); const q = h('input', { class: 'form-control', type: 'search', placeholder: 'Search…', autocomplete: 'off' });
        const list = h('div', { class: 'nm-pick-list' });
        const lvlSel = h('select', { class: 'form-select mb-2' });
        const title = byLevel ? 'Change level' : type === 'agent' ? 'Choose new upline agent' : type === 'vendor' ? 'Choose agent for this vendor' : 'Choose vendor for this worker';
        function fill() {
            const term = q.value.trim().toLowerCase(); list.replaceChildren();
            let items = [];
            if (type === 'agent') items.push({ kind: 'root', id: null, name: 'SmartBazars (top level)', sub: 'No upline', type: 'root', lvl: 1 });
            if (type === 'vendor') items.push({ kind: 'unassigned', id: null, name: 'Unassigned (no agent)', sub: 'Unlink from agent', type: 'root', lvl: 0 });
            const pool = type === 'worker' ? [...S.vendors.values()] : [...S.agents.values()];
            pool.sort((a, b) => a.name.localeCompare(b.name)).forEach(p => {
                const pl = type === 'worker' ? vendorLevel(p) : levelOf(p);
                items.push({ kind: type === 'worker' ? 'vendor' : 'agent', id: p.id, name: p.name, sub: (p.code || '') + (p.mobile ? ' · ' + p.mobile : ''), type: type === 'worker' ? 'vendor' : 'agent', lvl: pl == null ? null : pl + 1 });
            });
            if (byLevel) {
                if (!lvlSel.options.length) {
                    const cur = nodeLevel(type, n);
                    const lv = [...new Set(items.filter(i => i.lvl != null && canDrop({ type, id }, { kind: i.kind, id: i.id })).map(i => i.lvl))].sort((a, b) => a - b);
                    lv.forEach(l => lvlSel.append(h('option', { value: l, text: l === 0 ? 'Unassigned (no level)' : 'Level ' + l + (l === cur ? ' (current)' : '') })));
                    if (lv.includes(cur)) lvlSel.value = String(cur);
                }
                items = items.filter(i => String(i.lvl) === lvlSel.value);
            }
            let shown = 0;
            for (const it of items) {
                if (term && !(it.name + ' ' + it.sub).toLowerCase().includes(term)) continue;
                if (shown >= 80) break;
                const ok = canDrop({ type, id }, { kind: it.kind, id: it.id });
                shown++;
                list.append(h('button', { class: 'nm-pick', disabled: !ok, onclick: () => { closeModal(); askMove(type, id, it.id); } },
                    h('span', { class: 'nm-ico', style: 'width:30px;height:30px;font-size:.7rem;background:' + (COLORS[it.type] || '#667382'), text: it.type === 'root' ? '★' : initials(it.name) }),
                    h('span', { style: 'min-width:0' }, h('div', { class: 'fw-semibold text-truncate', text: it.name }), h('div', { class: 'small text-secondary text-truncate', text: it.sub })),
                    !ok && it.id != null ? h('span', { class: 'nm-badge ms-auto', text: 'not allowed' }) : null));
            }
            if (!shown) list.append(h('div', { class: 'nm-empty', text: 'No match.' }));
        }
        q.addEventListener('input', fill); lvlSel.addEventListener('change', fill); fill();
        $('nm-picker-body').replaceChildren(h('div', { class: 'p-3 border-bottom' }, h('div', { class: 'fw-bold mb-2', text: title }), h('div', { class: 'small text-secondary mb-2', text: 'Moving: ' + n.name + (nodeLevel(type, n) ? ' · now Level ' + nodeLevel(type, n) : '') }), byLevel ? [h('div', { class: 'small text-secondary mb-1', text: 'New level (then pick the upline at that level)' }), lvlSel] : null, q), list);
        $('nm-picker').classList.add('is-open'); setTimeout(() => q.focus(), 30);
    }

    // ---------------------------------------------------------------- row menu
    let menuEl = null;
    function closeMenu() { if (menuEl) { menuEl.remove(); menuEl = null; } }
    document.addEventListener('click', (e) => { if (menuEl && !menuEl.contains(e.target) && !e.target.closest('.nm-kebab')) closeMenu(); });
    window.addEventListener('scroll', closeMenu, true);
    function openMenu(btn, type, id) {
        closeMenu(); const n = nodeOf(type, id); const r = btn.getBoundingClientRect();
        const items = [
            h('button', { text: 'Move to…', onclick: () => { closeMenu(); openPicker(type, id); } }),
            h('button', { text: 'Change level…', onclick: () => { closeMenu(); openPicker(type, id, true); } }),
            h('button', { text: 'View details', onclick: () => { closeMenu(); select(type, id); } }),
            h('a', { text: 'Edit profile', href: type === 'vendor' ? url('editVendor', id) : url('editMember', id) }),
        ];
        if (type !== 'vendor') items.push(h('button', { text: n.status === 'active' ? 'Suspend' : 'Activate', onclick: async () => { closeMenu(); try { const res = await api(url('status', id), { method: 'POST', body: '{}' }); n.status = res.status; render(); toast(n.name + ' is now ' + res.status, 'ok'); } catch (e) { toast(e.message, 'err'); } } }));
        menuEl = h('div', { class: 'nm-menu', role: 'menu' }, items); $('nm').append(menuEl);
        const w = 200, left = Math.min(Math.max(8, r.right - w), window.innerWidth - w - 8); const top = (r.bottom + 160 > window.innerHeight) ? Math.max(8, r.top - menuEl.offsetHeight - 4) : r.bottom + 4;
        menuEl.style.left = left + 'px'; menuEl.style.top = top + 'px';
    }

    // ---------------------------------------------------------------- rendering
    function statusDot(s) { return h('span', { class: 'nm-dot' + (s === 'active' ? '' : (s === 'suspended' || s === 'rejected') ? ' off' : ' wait'), title: s }); }

    function row(type, n, opts) {
        const k = key(type, n.id);
        const el = h('div', { class: 'nm-row' + (S.selected === k ? ' is-selected' : ''), 'data-key': k, role: 'treeitem', tabindex: '0',
            draggable: canDragDevice ? 'true' : false,
            onclick: (e) => { if (!e.target.closest('button,a')) select(type, n.id); },
            ondragstart: (e) => startDrag(e, type, n.id) });
        if (opts.drop) { el.dataset.drop = opts.drop; if (n.id != null) el.dataset.id = n.id; }
        if (canDragDevice) el.append(h('span', { class: 'nm-grip', 'aria-hidden': 'true', text: '⋮⋮' }));
        el.append(opts.expandable ? h('button', { class: 'nm-caret' + (S.open.has(k) ? ' is-open' : ''), 'aria-label': 'Expand', onclick: (e) => { e.stopPropagation(); toggle(type, n.id); } }, caretSvg()) : h('span', { class: 'nm-caret-space' }));
        el.append(h('span', { class: 'nm-ico', style: 'width:32px;height:32px;font-size:.72rem;background:' + COLORS[type], text: initials(n.name) }));
        el.append(h('div', { class: 'nm-main' }, h('div', { class: 'nm-name', text: n.name }), h('div', { class: 'nm-sub', text: [n.code, n.mobile].filter(Boolean).join(' · ') })));
        el.append(h('div', { class: 'nm-badges' }, opts.badges || []));
        el.append(statusDot(n.status));
        el.append(h('button', { class: 'nm-kebab', 'aria-label': 'Actions', text: '⋯', onclick: (e) => { e.stopPropagation(); openMenu(e.currentTarget, type, n.id); } }));
        return el;
    }
    const badge = (t, cls) => h('span', { class: 'nm-badge ' + (cls || ''), text: t });

    function capped(list, k, build, host) {
        const cap = S.cap[k] || PAGE;
        list.slice(0, cap).forEach(x => host.append(build(x)));
        if (list.length > cap) host.append(h('button', { class: 'btn btn-sm nm-more', text: 'Show ' + Math.min(PAGE, list.length - cap) + ' more (' + (list.length - cap) + ' hidden)', onclick: () => { S.cap[k] = cap + PAGE; render(true); } }));
    }

    function agentNode(a) {
        const k = key('agent', a.id), subs = agentChildren(a.id), vs = vendorsOf(a.id);
        const expandable = subs.length > 0 || (S.show.vendor && vs.length > 0);
        const wrap = h('div', { role: 'group' });
        wrap.append(row('agent', a, { drop: 'agent', expandable, badges: [badge('L' + levelOf(a)), subs.length ? badge(subs.length + ' sub-agent' + (subs.length > 1 ? 's' : ''), 'hide-sm') : null, vs.length ? badge(vs.length + ' vendor' + (vs.length > 1 ? 's' : ''), 'hide-sm') : null] }));
        if (S.open.has(k) && expandable) {
            const kids = h('div', { class: 'nm-kids' }); capped(subs, 'a' + a.id, agentNode, kids);
            if (S.show.vendor) capped(vs, 'v' + a.id, vendorNode, kids); wrap.append(kids);
        }
        return wrap;
    }
    function vendorNode(v) {
        const k = key('vendor', v.id), expandable = S.show.worker && v.workers_count > 0, wrap = h('div');
        wrap.append(row('vendor', v, { drop: 'vendor', expandable, badges: [vendorLevel(v) ? badge('L' + vendorLevel(v)) : null, badge(v.workers_count + ' worker' + (v.workers_count === 1 ? '' : 's'), 'hide-sm')] }));
        if (S.open.has(k) && expandable) {
            const kids = h('div', { class: 'nm-kids' });
            if (!S.loadedWorkers.has(v.id)) kids.append(h('div', { class: 'nm-empty py-2', text: 'Loading workers…' }));
            else capped(workersOf(v.id), 'w' + v.id, workerNode, kids);
            wrap.append(kids);
        }
        return wrap;
    }
    const workerNode = (w) => row('worker', w, { badges: [workerLevel(w) ? badge('L' + workerLevel(w)) : null, badge(w.customers + ' customer' + (w.customers === 1 ? '' : 's'), 'hide-sm')] });

    function render(keepScroll) {
        closeMenu();
        const host = $('nm-tree'), sc = host.scrollTop; host.replaceChildren();
        const top = agentChildren(null);
        const root = h('div', { class: 'nm-row nm-root', 'data-drop': 'root' },
            h('span', { class: 'nm-ico', style: 'width:32px;height:32px;background:#1d273b', text: 'SB' }),
            h('div', { class: 'nm-main' }, h('div', { class: 'nm-name', text: 'SmartBazars (Company)' }), h('div', { class: 'nm-sub', text: 'Top-level agents report directly to the company' })),
            h('div', { class: 'nm-badges' }, badge(top.length + ' top-level agent' + (top.length === 1 ? '' : 's'))));
        host.append(root);
        const kids = h('div', { class: 'nm-kids' }); capped(top, 'root', agentNode, kids);
        host.append(top.length ? kids : h('div', { class: 'nm-empty', text: 'No agents yet. Create one from BSOs → Create ID.' }));
        if (keepScroll) host.scrollTop = sc;
        renderSide(); renderStats();
    }
    function renderStats() {
        const un = [...S.vendors.values()].filter(v => v.agent_id == null);
        $('st-agents').textContent = S.agents.size; $('st-vendors').textContent = S.vendors.size;
        $('st-workers').textContent = [...S.vendors.values()].reduce((s, v) => s + v.workers_count, 0); $('st-unassigned').textContent = un.length; $('un-count').textContent = un.length;
    }
    function renderSide() {
        const un = [...S.vendors.values()].filter(v => v.agent_id == null).sort((a, b) => a.name.localeCompare(b.name)), list = $('nm-un-list'); list.replaceChildren();
        if (!un.length) list.append(h('div', { class: 'nm-empty', text: 'Every vendor has an agent. 🎉' }));
        capped(un, 'un', (v) => row('vendor', v, { badges: [] }), list);
        $('nm-logs').replaceChildren(...(S.logs.length ? S.logs.map(l => h('div', { class: 'nm-log' }, h('div', {}, h('b', { text: l.label }), ' ', h('span', { class: 'text-secondary', text: '(' + l.type + ')' })), h('div', { class: 'text-secondary', text: l.from + '  →  ' + l.to }), h('div', { class: 'text-secondary', style: 'font-size:.74rem', text: (l.affected ? l.affected + ' downline agent' + (l.affected > 1 ? 's' : '') + ' updated · ' : '') + (l.at || 'just now') }))) : [h('div', { class: 'text-secondary small py-2', text: 'No changes yet.' })]));
        renderDetails();
    }
    function renderDetails() {
        const box = $('nm-details'); if (!S.selected) return;
        const [type, sid] = S.selected.split(':'), id = Number(sid), n = nodeOf(type, id); if (!n) return;
        const chain = type === 'agent' ? agentChain(id).slice(0, -1) : type === 'vendor' ? (n.agent_id ? agentChain(n.agent_id) : []) : (S.vendors.get(n.seller_id) && S.vendors.get(n.seller_id).agent_id ? agentChain(S.vendors.get(n.seller_id).agent_id) : []);
        const crumbs = ['SmartBazars', ...chain.map(a => a.name)]; if (type === 'worker') crumbs.push(label('vendor', n.seller_id));
        box.replaceChildren(
            h('div', { class: 'd-flex align-items-center gap-2 mb-2' }, h('span', { class: 'nm-ico', style: 'background:' + COLORS[type], text: initials(n.name) }), h('div', { style: 'min-width:0' }, h('div', { class: 'fw-bold text-truncate', text: n.name }), h('div', { class: 'small text-secondary text-capitalize', text: type + ' · ' + n.status }))),
            h('dl', { class: 'row small mb-2' },
                h('dt', { class: 'col-4 text-secondary fw-normal', text: 'Code' }), h('dd', { class: 'col-8' }, h('code', { text: n.code || '—' })),
                h('dt', { class: 'col-4 text-secondary fw-normal', text: 'Mobile' }), h('dd', { class: 'col-8', text: n.mobile || '—' }),
                h('dt', { class: 'col-4 text-secondary fw-normal', text: 'Email' }), h('dd', { class: 'col-8 text-break', text: n.email || '—' })),
            h('div', { class: 'small text-secondary mb-1', text: 'Upline chain' }),
            h('div', { class: 'd-flex flex-wrap align-items-center gap-1 mb-3 small' }, crumbs.map((c, i) => [i ? h('span', { class: 'text-secondary', text: '›' }) : null, h('span', { class: 'nm-badge', text: c })])),
            h('div', { class: 'd-flex gap-2 flex-wrap' },
                h('button', { class: 'btn btn-sm btn-primary', text: 'Move to…', onclick: () => openPicker(type, id) }),
                h('button', { class: 'btn btn-sm', text: 'Change level…', onclick: () => openPicker(type, id, true) }),
                h('a', { class: 'btn btn-sm', text: 'Edit', href: type === 'vendor' ? url('editVendor', id) : url('editMember', id) }),
                type !== 'vendor' ? h('a', { class: 'btn btn-sm', text: 'ID card', target: '_blank', href: url('idCard', id) }) : null));
    }

    // ---------------------------------------------------------------- interactions
    function select(type, id) { S.selected = key(type, id); document.querySelectorAll('#nm .nm-row.is-selected').forEach(r => r.classList.remove('is-selected')); document.querySelectorAll('#nm .nm-row[data-key="' + S.selected + '"]').forEach(r => r.classList.add('is-selected')); renderDetails(); }
    async function loadWorkers(sid) {
        if (S.loadedWorkers.has(sid)) return;
        try { const res = await api(url('workers', sid), { method: 'GET' }); res.workers.forEach(w => S.workers.set(w.id, w)); S.loadedWorkers.add(sid); } catch (e) { toast(e.message, 'err'); }
    }
    async function toggle(type, id) {
        const k = key(type, id);
        if (S.open.has(k)) S.open.delete(k); else { S.open.add(k); if (type === 'vendor') { render(true); await loadWorkers(id); } }
        render(true);
    }
    async function revealPath(type, id) {
        if (type === 'agent') { const a = S.agents.get(id); if (a && a.parent_id) agentChain(a.parent_id).forEach(x => S.open.add(key('agent', x.id))); }
        if (type === 'vendor') { const v = S.vendors.get(id); if (v && v.agent_id) agentChain(v.agent_id).forEach(x => S.open.add(key('agent', x.id))); }
        if (type === 'worker') { const w = S.workers.get(id); if (w) { S.open.add(key('vendor', w.seller_id)); await loadWorkers(w.seller_id); const v = S.vendors.get(w.seller_id); if (v && v.agent_id) agentChain(v.agent_id).forEach(x => S.open.add(key('agent', x.id))); } }
    }
    function flash(type, id) {
        const els = document.querySelectorAll('#nm .nm-row[data-key="' + key(type, id) + '"]'); const el = els[0]; if (!el) return;
        el.scrollIntoView({ block: 'center', behavior: 'smooth' }); el.classList.remove('nm-flash'); void el.offsetWidth; el.classList.add('nm-flash');
    }

    let st = null; const results = $('nm-results');
    $('nm-q').addEventListener('input', (e) => {
        clearTimeout(st); const q = e.target.value.trim(); if (q.length < 2) { results.style.display = 'none'; return; }
        st = setTimeout(async () => {
            try {
                const res = await api(URLS.search + '?q=' + encodeURIComponent(q), { method: 'GET' }); results.replaceChildren();
                if (!res.results.length) results.append(h('div', { class: 'nm-empty', text: 'No match.' }));
                res.results.forEach(r => results.append(h('button', { onclick: async () => {
                    results.style.display = 'none'; $('nm-q').value = '';
                    if (r.type === 'worker' && r.seller_id) await loadWorkers(r.seller_id);
                    S.selected = key(r.type, r.id); await revealPath(r.type, r.id); render(); flash(r.type, r.id);
                } }, h('span', { class: 'nm-ico', style: 'width:28px;height:28px;font-size:.65rem;background:' + COLORS[r.type], text: initials(r.label) }),
                    h('span', { style: 'min-width:0' }, h('div', { class: 'fw-semibold text-truncate', text: r.label }), h('div', { class: 'small text-secondary text-truncate', text: r.type + ' · ' + r.sub })))));
                results.style.display = 'block';
            } catch (err) { toast(err.message, 'err'); }
        }, 250);
    });
    document.addEventListener('click', (e) => { if (!e.target.closest('.nm-search')) results.style.display = 'none'; });
    $('f-vendor').addEventListener('change', (e) => { S.show.vendor = e.target.checked; render(true); });
    $('f-worker').addEventListener('change', (e) => { S.show.worker = e.target.checked; render(true); });
    $('nm-expand').addEventListener('click', () => { S.agents.forEach(a => { if (agentChildren(a.id).length || vendorsOf(a.id).length) S.open.add(key('agent', a.id)); }); render(true); });
    $('nm-collapse').addEventListener('click', () => { S.open.clear(); render(true); });
    $('nm-refresh').addEventListener('click', () => load(true));
    $('nm-hint').textContent = canDragDevice
        ? 'Drag an agent onto another agent (or the company row) to change its upline · drag a vendor onto an agent · drag a worker onto a vendor. Or use ⋯ → Move to….'
        : 'Tap ⋯ on any card → “Move to…” to change its upline or vendor.';

    async function load(manual) {
        try {
            const d = await api(URLS.data, { method: 'GET' });
            S.agents = new Map(d.agents.map(a => [a.id, a])); S.vendors = new Map(d.vendors.map(v => [v.id, v]));
            S.workers = new Map(); S.loadedWorkers = new Set(); S.logs = d.logs || [];
            if (!manual) agentChildren(null).slice(0, 5).forEach(a => S.open.add(key('agent', a.id)));
            render(); if (manual) toast('Reloaded', 'ok');
        } catch (e) { $('nm-tree').replaceChildren(h('div', { class: 'nm-empty text-danger', text: e.message }), h('div', { class: 'text-center' }, h('button', { class: 'btn btn-sm', text: 'Retry', onclick: () => load() }))); }
    }
    load();
})();
</script>
@endsection
