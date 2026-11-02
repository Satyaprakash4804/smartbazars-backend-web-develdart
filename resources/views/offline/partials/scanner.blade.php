{{-- QR scanner modal. Usage: @include('offline.partials.scanner', ['onScan' => 'myCallback']) — myCallback(cardNumber) is called with the decoded card number. --}}
<div class="modal modal-blur fade" id="sbScanModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="ti ti-scan me-2"></i>Scan User Card QR</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="sbScanReader" style="width:100%"></div>
                <div class="text-secondary small mt-2" id="sbScanHint">Point the camera at the QR on the customer's User Card.</div>
            </div>
        </div>
    </div>
</div>
@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
<script>
(function () {
    let scanner = null;
    const modalEl = document.getElementById('sbScanModal');
    window.sbOpenScanner = function () { bootstrap.Modal.getOrCreateInstance(modalEl).show(); };

    modalEl.addEventListener('shown.bs.modal', function () {
        if (typeof Html5Qrcode === 'undefined') {
            document.getElementById('sbScanHint').textContent = 'Scanner could not load. Type the card number instead.';
            return;
        }
        scanner = new Html5Qrcode('sbScanReader');
        scanner.start({ facingMode: 'environment' }, { fps: 10, qrbox: { width: 240, height: 240 } }, function (text) {
            // QR = "SMARTBAZARS:CARD:<no>" + holder details on the next lines (old cards: first line only)
            const m = String(text).match(/SMARTBAZARS:CARD:\s*([A-Z0-9]+)/i);
            const card = (m ? m[1] : String(text).split(/\r?\n/)[0]).replace(/[\s-]+/g, '').trim();
            stop();
            bootstrap.Modal.getInstance(modalEl).hide();
            @if(!empty($onScan)) {{ $onScan }}(card); @endif
        }, function () {}).catch(function () {
            document.getElementById('sbScanHint').textContent = 'Camera permission denied or unavailable. Type the card number instead.';
        });
    });
    modalEl.addEventListener('hidden.bs.modal', stop);
    function stop() { if (scanner) { scanner.stop().then(function () { scanner.clear(); }).catch(function () {}); scanner = null; } }
})();
</script>
@endpush
