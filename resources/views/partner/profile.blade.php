@extends('layouts.admin.app', ['page' => 'profile'])
@section('title', 'Profile')
@section('header_data')
    @php $page_title = 'Profile'; $page_pretitle = 'SmartBazars ' . ucfirst($m->type); @endphp
@endsection
@section('admin-content')
    @include('smartbazars._errors')
    @php $photo = \App\Support\SmartBazars\Photo::url($m->user); @endphp
    <div class="row row-cards">
        <div class="col-12"><div class="card"><div class="card-body d-flex flex-wrap align-items-center gap-3">
            @if($photo)
                <span class="avatar avatar-xl" style="width:96px;height:96px;background-image:url({{ $photo }});background-size:cover;background-position:center"></span>
            @else
                <span class="avatar avatar-xl bg-primary-lt" style="width:96px;height:96px">{{ strtoupper(mb_substr($m->user->name, 0, 2)) }}</span>
            @endif
            <div class="flex-fill">
                <div class="h3 mb-1">Profile photo</div>
                <div class="text-secondary small mb-2">Appears on your ID card (download &amp; print), the sidebar and the header. Use a clear, front-facing photo. It is resized automatically.</div>
                <div class="d-flex flex-wrap gap-2">
                    <label class="btn btn-primary mb-0"><i class="ti ti-camera me-1"></i>Take selfie<input type="file" accept="image/*" capture="user" hidden onchange="sbPhoto(this)"></label>
                    <label class="btn mb-0"><i class="ti ti-photo-up me-1"></i>Choose photo<input type="file" accept="image/*" hidden onchange="sbPhoto(this)"></label>
                    @if($photo)
                        <form method="POST" action="{{ route('partner.profile.photo.remove') }}" onsubmit="return confirm('Remove your photo?')">@csrf @method('DELETE')<button class="btn btn-outline-danger">Remove</button></form>
                    @endif
                </div>
                <div id="photoMsg" class="small mt-2"></div>
            </div>
        </div></div></div>
        <div class="col-md-6"><div class="card"><div class="card-body">
            <div class="h3">{{ $m->user->name }}</div>
            <div>{{ $m->user->email }} · {{ $m->user->mobile }}</div>
            <div class="mt-2">Referral code: <code class="fs-3">{{ $m->referral_code }}</code></div>
            @if($m->isAgent())<div>Upline: {{ $m->parent?->user?->name ?? 'SmartBazars' }} · Level {{ $m->level }}</div>
            @else<div>Vendor: {{ $m->seller?->user?->name ?? '—' }}</div>@endif
            <div class="text-secondary small mt-2">Member since {{ $m->created_at->format('d M Y') }}</div>
            @if($m->isAgent())
                <div class="mt-3">
                    <label class="form-label mb-1">Recruit link — people who apply with it join your downline</label>
                    <div class="input-group"><input id="recruitLink" class="form-control" readonly value="{{ route('partner.apply', ['ref' => $m->referral_code]) }}" onclick="this.select()"><button type="button" class="btn" onclick="navigator.clipboard.writeText(document.getElementById('recruitLink').value);this.textContent='Copied'">Copy</button></div>
                </div>
            @endif
        </div></div></div>
        <div class="col-md-6"><div class="card">
            <div class="card-header"><h3 class="card-title">Change password</h3></div>
            <form method="POST" action="{{ route('partner.profile.password') }}" class="card-body">
                @csrf
                <input type="password" name="current_password" class="form-control mb-2" placeholder="Current password" required>
                <input type="password" name="password" class="form-control mb-2" placeholder="New password (min 8)" required>
                <input type="password" name="password_confirmation" class="form-control mb-3" placeholder="Confirm new password" required>
                <button class="btn btn-primary">Update password</button>
            </form>
        </div></div>
    </div>
    <script>
        // Shrinks the photo in the browser first (phone selfies are several MB), then uploads it.
        function sbShrink(file) {
            return new Promise(function (resolve, reject) {
                var url = URL.createObjectURL(file), img = new Image();
                img.onload = function () {
                    var max = 900, s = Math.min(1, max / Math.max(img.naturalWidth, img.naturalHeight));
                    var w = Math.round(img.naturalWidth * s), h = Math.round(img.naturalHeight * s);
                    var c = document.createElement('canvas'); c.width = w; c.height = h;
                    var x = c.getContext('2d'); x.fillStyle = '#fff'; x.fillRect(0, 0, w, h); x.drawImage(img, 0, 0, w, h);
                    URL.revokeObjectURL(url);
                    c.toBlob(function (b) { b ? resolve(b) : reject(new Error('Could not process this photo.')); }, 'image/jpeg', 0.86);
                };
                img.onerror = function () { URL.revokeObjectURL(url); reject(new Error('This file is not a readable image (try a JPG or PNG).')); };
                img.src = url;
            });
        }
        async function sbPhoto(input) {
            var f = input.files && input.files[0], msg = document.getElementById('photoMsg');
            if (!f) return;
            msg.className = 'small mt-2 text-secondary'; msg.textContent = 'Uploading…';
            try {
                var blob = await sbShrink(f), fd = new FormData();
                fd.append('photo', blob, 'photo.jpg');
                fd.append('_token', '{{ csrf_token() }}');
                var r = await fetch(@json(route('partner.profile.photo')), {method: 'POST', body: fd, headers: {'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest'}});
                var j = await r.json().catch(function () { return {}; });
                if (!r.ok) {
                    var m = j.message || (j.errors && Object.values(j.errors)[0][0]) || (r.status === 419 ? 'Session expired — refresh the page and try again.' : 'Upload failed (' + r.status + ').');
                    throw new Error(m);
                }
                location.reload();
            } catch (e) {
                msg.className = 'small mt-2 text-danger'; msg.textContent = e.message;
                input.value = '';
            }
        }
    </script>
@endsection
