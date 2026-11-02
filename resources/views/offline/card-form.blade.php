@extends('layouts.admin.app', ['page' => 'sb_offline', 'sub_page' => 'off_new'])
@section('title', 'New User Card')
@section('header_data')
    @php $page_title = 'Create User Card'; $page_pretitle = 'Offline customer'; @endphp
@endsection
@section('admin-content')
    @include('offline.partials.nav')
    @if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif
    <form method="post" action="{{ route($rp . 'cards.store') }}" enctype="multipart/form-data" id="cardForm" novalidate>
        @csrf
        <div class="row g-3">
            <div class="col-lg-8">
                <div class="card mb-3"><div class="card-header"><h4 class="card-title">Customer details</h4></div>
                    <div class="card-body"><div class="row g-3">
                        <div class="col-md-6"><label class="form-label required">Full name</label><input name="name" value="{{ old('name') }}" class="form-control" maxlength="100" required></div>
                        <div class="col-md-6"><label class="form-label required">Mobile number</label><input name="mobile" value="{{ old('mobile') }}" class="form-control" inputmode="numeric" maxlength="15" placeholder="10-digit mobile" required></div>
                        <div class="col-md-6"><label class="form-label required">Email</label><input type="email" name="email" value="{{ old('email') }}" class="form-control" required>
                            <div class="form-hint">The card PDF and app login are emailed here.</div></div>
                        <div class="col-md-6"><label class="form-label">Aadhaar — last 4 digits <span class="text-secondary">(optional)</span></label><input name="aadhaar_last4" value="{{ old('aadhaar_last4') }}" class="form-control" inputmode="numeric" maxlength="4" placeholder="XXXX">
                            <div class="form-hint">The full Aadhaar number is never stored or printed.</div></div>
                    </div></div>
                </div>
                <div class="card mb-3"><div class="card-header"><h4 class="card-title">Address</h4></div>
                    <div class="card-body"><div class="row g-3">
                        <div class="col-12"><label class="form-label">House / street / village <span class="text-secondary">(optional)</span></label><input name="address_line" value="{{ old('address_line') }}" class="form-control" maxlength="255"></div>
                        <div class="col-md-6"><label class="form-label required">Ward / Panchayat</label><input name="ward_panchayat" value="{{ old('ward_panchayat') }}" class="form-control" required></div>
                        <div class="col-md-6"><label class="form-label required">Block</label><input name="block" value="{{ old('block') }}" class="form-control" required></div>
                        <div class="col-md-6"><label class="form-label required">District</label><input name="district" value="{{ old('district') }}" class="form-control" required></div>
                        <div class="col-md-4"><label class="form-label required">State</label><input name="state" value="{{ old('state', 'Uttar Pradesh') }}" class="form-control" required></div>
                        <div class="col-md-2"><label class="form-label">Pincode</label><input name="pincode" value="{{ old('pincode') }}" class="form-control" inputmode="numeric" maxlength="6"></div>
                    </div></div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="card mb-3"><div class="card-header"><h4 class="card-title">Customer photo</h4></div>
                    <div class="card-body text-center">
                        <img id="photoPreview" class="rounded mb-3 d-none" style="width:150px;height:190px;object-fit:cover" alt="">
                        <div id="photoEmpty" class="rounded bg-blue-lt d-flex align-items-center justify-content-center mx-auto mb-3" style="width:150px;height:190px"><i class="ti ti-user fs-1 text-blue"></i></div>
                        <div class="d-grid gap-2">
                            <button type="button" class="btn btn-primary" id="btnCamera"><i class="ti ti-camera me-1"></i>Take photo</button>
                            <label class="btn btn-outline-secondary mb-0"><i class="ti ti-upload me-1"></i>Upload photo
                                <input type="file" name="photo" id="photoInput" accept="image/*" class="d-none"></label>
                        </div>
                        <div class="form-hint mt-2">JPG/PNG, up to 5 MB. Shown on the card.</div>
                    </div>
                </div>
                <div class="card mb-3"><div class="card-header"><h4 class="card-title">Aadhaar card</h4></div>
                    <div class="card-body">
                        <input type="file" name="aadhaar" class="form-control" accept="image/*,application/pdf" required>
                        <div class="form-hint mt-1">Image or PDF, up to 6 MB. Stored privately — visible only to SmartBazars admin and the vendor.</div>
                    </div>
                </div>
                <button class="btn btn-success w-100 btn-lg" id="btnSubmit"><i class="ti ti-id-badge-2 me-1"></i>Create card &amp; email it</button>
            </div>
        </div>
    </form>

    {{-- Camera modal (desktop webcam / phone front camera) --}}
    <div class="modal modal-blur fade" id="camModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
            <div class="modal-header"><h5 class="modal-title">Take customer photo</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body text-center">
                <video id="camVideo" autoplay playsinline muted style="width:100%;max-height:60vh;background:#000;border-radius:8px"></video>
                <canvas id="camCanvas" class="d-none"></canvas>
                <div class="text-secondary small mt-2" id="camMsg"></div>
            </div>
            <div class="modal-footer"><button type="button" class="btn" id="camFlip">Switch camera</button><button type="button" class="btn btn-primary" id="camSnap"><i class="ti ti-camera me-1"></i>Capture</button></div>
        </div></div>
    </div>
@endsection
@push('scripts')
<script>
(function () {
    const input = document.getElementById('photoInput'), img = document.getElementById('photoPreview'), empty = document.getElementById('photoEmpty');
    const modalEl = document.getElementById('camModal'), video = document.getElementById('camVideo'), canvas = document.getElementById('camCanvas'), msg = document.getElementById('camMsg');
    let stream = null, facing = 'user';

    function showPreview(file) {
        const r = new FileReader();
        r.onload = e => { img.src = e.target.result; img.classList.remove('d-none'); empty.classList.add('d-none'); };
        r.readAsDataURL(file);
    }
    input.addEventListener('change', () => input.files[0] && showPreview(input.files[0]));

    async function start() {
        stop(); msg.textContent = '';
        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) { msg.textContent = 'Camera is not available here (needs HTTPS). Use "Upload photo" — on a phone it opens the camera.'; return; }
        try {
            stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: facing, width: { ideal: 960 }, height: { ideal: 1280 } }, audio: false });
            video.srcObject = stream;
        } catch (e) { msg.textContent = 'Could not open the camera: ' + (e.message || e.name) + '. Use "Upload photo" instead.'; }
    }
    function stop() { if (stream) { stream.getTracks().forEach(t => t.stop()); stream = null; } }

    document.getElementById('btnCamera').addEventListener('click', () => bootstrap.Modal.getOrCreateInstance(modalEl).show());
    modalEl.addEventListener('shown.bs.modal', start);
    modalEl.addEventListener('hidden.bs.modal', stop);
    document.getElementById('camFlip').addEventListener('click', () => { facing = facing === 'user' ? 'environment' : 'user'; start(); });
    document.getElementById('camSnap').addEventListener('click', () => {
        if (!video.videoWidth) return;
        // portrait crop (4:5) from the centre of the frame
        const vw = video.videoWidth, vh = video.videoHeight, ratio = 4 / 5;
        let sw = vw, sh = vw / ratio; if (sh > vh) { sh = vh; sw = vh * ratio; }
        canvas.width = 720; canvas.height = 900;
        canvas.getContext('2d').drawImage(video, (vw - sw) / 2, (vh - sh) / 2, sw, sh, 0, 0, 720, 900);
        canvas.toBlob(blob => {
            const file = new File([blob], 'customer-photo.jpg', { type: 'image/jpeg' });
            const dt = new DataTransfer(); dt.items.add(file); input.files = dt.files;
            showPreview(file);
            bootstrap.Modal.getInstance(modalEl).hide();
        }, 'image/jpeg', 0.9);
    });

    document.getElementById('cardForm').addEventListener('submit', function (e) {
        if (!input.files.length) { e.preventDefault(); alert('Please take or upload the customer photo.'); return; }
        const b = document.getElementById('btnSubmit'); b.disabled = true; b.innerHTML = 'Creating card…';
    });
})();
</script>
@endpush
