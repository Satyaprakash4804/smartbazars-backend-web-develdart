@php $dl = app(\App\Services\SmartBazars\AppDownloadService::class); @endphp
<form method="POST" action="{{ route('admin.smartbazars.app-download.save') }}" enctype="multipart/form-data" class="mt-3">
    @csrf
    <div class="card">
        <div class="card-header"><h3 class="card-title">Mobile app download (website &amp; QR)</h3></div>
        <div class="card-body">
            <p class="text-secondary small">Upload the Android APK (or paste a link). People then download it from one stable link that always serves the latest file.</p>
            @foreach(\App\Services\SmartBazars\AppDownloadService::APPS as $a => $label)
                <div class="border rounded p-3 mb-3">
                    <h4 class="mb-2">{{ $label }} app</h4>
                    @error("{$a}_apk")<div class="text-danger small mb-2">{{ $message }}</div>@enderror
                    <div class="row g-2">
                        <div class="col-md-8"><label class="form-label">APK file</label><input type="file" name="{{ $a }}_apk" class="form-control" accept=".apk"></div>
                        <div class="col-md-4"><label class="form-label">Version</label><input name="{{ $a }}_version" value="{{ old($a.'_version', $s['app_'.$a.'_version'] ?? '') }}" class="form-control" placeholder="1.0.0"></div>
                        <div class="col-12"><label class="form-label">…or external link (Play Store / Drive)</label><input type="url" name="{{ $a }}_url" value="{{ old($a.'_url', $s['app_'.$a.'_url'] ?? '') }}" class="form-control" placeholder="https://play.google.com/store/apps/details?id=…"></div>
                    </div>
                    <div class="small mt-2">
                        @if($dl->apkPath($a))<span class="badge bg-success-lt">APK uploaded</span> <button type="submit" form="rm-{{ $a }}" class="btn btn-sm btn-outline-danger ms-1" onclick="return confirm('Remove the uploaded APK?')">Remove</button>
                        @elseif($dl->available($a))<span class="badge bg-info-lt">Redirects to external link</span>
                        @else<span class="badge bg-warning-lt">Not set up — download will show "not available"</span>@endif
                        <div class="mt-1">Public link: <a href="{{ route('smartbazars.download', $a) }}" target="_blank">{{ route('smartbazars.download', $a) }}</a></div>
                    </div>
                </div>
            @endforeach
            <button class="btn btn-primary">Save app download</button>
        </div>
    </div>
</form>
@foreach(array_keys(\App\Services\SmartBazars\AppDownloadService::APPS) as $a)
    <form id="rm-{{ $a }}" method="POST" action="{{ route('admin.smartbazars.app-download.remove', $a) }}" class="d-none">@csrf</form>
@endforeach
<div class="card mt-3">
    <div class="card-header"><h3 class="card-title">Website footer links (Partner &amp; Vendor login)</h3></div>
    <div class="card-body">
        <p class="small text-secondary mb-2">Add this one line to the website footer / custom footer script. It adds Partner Login, Vendor Login, Become a Partner/Vendor and Download App to the footer, with the correct live links.</p>
        <input class="form-control" readonly onclick="this.select()" value="{{ '<script src="' . route('smartbazars.footer-js') . '" defer></script>' }}">
    </div>
</div>
<div class="card mt-3">
    <div class="card-header"><h3 class="card-title">Email check (login details &amp; letters)</h3></div>
    <div class="card-body">
        <p class="small text-secondary mb-2">Sends a real test email using the saved SMTP settings (Settings → Email). If it fails, the exact error is shown here.</p>
        <form method="POST" action="{{ route('admin.smartbazars.test-mail') }}" class="d-flex gap-2">@csrf
            <input type="email" name="to" class="form-control" value="{{ auth()->user()->email ?? '' }}" required>
            <button class="btn btn-outline-primary text-nowrap">Send test email</button>
        </form>
    </div>
</div>
