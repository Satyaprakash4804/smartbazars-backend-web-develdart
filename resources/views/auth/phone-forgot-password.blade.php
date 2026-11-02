@extends('layouts.admin.guest')
@section('title', 'Forgot password')
@section('content')
<div class="page page-center">
    <div class="container container-tight py-4">
        <div class="text-center mb-4">
            <img src="{{ !empty($systemSettings['logo']) ? $systemSettings['logo'] : asset('logos/hyper-local-logo.png') }}" alt="SmartBazars" style="max-height:90px">
        </div>
        <div class="card card-md"><div class="card-body">
            <h2 class="h2 text-center mb-1">Forgot password</h2>
            <p class="text-center text-secondary mb-4">We will send an OTP by SMS to your registered mobile number.</p>
            <div id="fpAlert"></div>

            <div id="stepPhone">
                <label class="form-label">Mobile number</label>
                <div class="input-group mb-3"><span class="input-group-text">+91</span>
                    <input type="tel" id="fpMobile" class="form-control" inputmode="numeric" maxlength="10" placeholder="10-digit mobile" autofocus></div>
                <div id="fpRecaptcha"></div>
                <button class="btn btn-primary w-100" id="btnSend">Send OTP</button>
            </div>

            <div id="stepOtp" class="d-none">
                <label class="form-label">Enter the 6-digit OTP</label>
                <input type="text" id="fpOtp" class="form-control form-control-lg text-center mb-3" inputmode="numeric" maxlength="6" autocomplete="one-time-code">
                <label class="form-label">New password</label>
                <input type="password" id="fpPass" class="form-control mb-2" minlength="8" autocomplete="new-password">
                <label class="form-label">Confirm new password</label>
                <input type="password" id="fpPass2" class="form-control mb-3" minlength="8" autocomplete="new-password">
                <button class="btn btn-primary w-100" id="btnReset">Verify &amp; reset password</button>
                <button class="btn btn-link w-100 mt-2" id="btnResend" type="button">Change number / resend OTP</button>
            </div>
        </div></div>
        <div class="text-center text-secondary mt-3"><a href="{{ $loginUrl }}">Back to login</a></div>
    </div>
</div>
@endsection
@section('footer')
<script type="module">
import { initializeApp } from 'https://www.gstatic.com/firebasejs/10.12.2/firebase-app.js';
import { getAuth, RecaptchaVerifier, signInWithPhoneNumber } from 'https://www.gstatic.com/firebasejs/10.12.2/firebase-auth.js';

const PANEL = @json($panel);
const $ = id => document.getElementById(id);
const csrf = document.querySelector('meta[name="csrf-token"]')?.content || @json(csrf_token());
const say = (type, t) => { $('fpAlert').innerHTML = `<div class="alert alert-${type}">${t.replace(/[<>]/g, '')}</div>`; };
let auth, verifier, confirmation;

async function boot() {
    const r = await fetch('/api/settings/firebase-config'); const j = await r.json();
    if (!j.data || !j.data.apiKey) { say('danger', 'Phone verification is not configured. Please contact support.'); $('btnSend').disabled = true; return; }
    auth = getAuth(initializeApp(j.data, 'sb-forgot'));
    auth.languageCode = 'en';
}
function newVerifier() {
    if (verifier) { try { verifier.clear(); } catch (e) {} }
    $('fpRecaptcha').innerHTML = '';
    verifier = new RecaptchaVerifier(auth, 'fpRecaptcha', { size: 'invisible' });
}
$('btnSend').addEventListener('click', async () => {
    const m = $('fpMobile').value.replace(/\D/g, '');
    if (m.length !== 10) return say('warning', 'Enter a valid 10-digit mobile number.');
    const b = $('btnSend'); b.disabled = true;
    try {
        const c = await fetch('/api/auth/phone/forgot-check', { method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json' }, body: JSON.stringify({ mobile: m, panel: PANEL }) });
        const cj = await c.json();
        if (!cj.exists) { say('danger', 'No account found for this mobile number.'); return; }
        newVerifier();
        confirmation = await signInWithPhoneNumber(auth, '+91' + m, verifier);
        $('stepPhone').classList.add('d-none'); $('stepOtp').classList.remove('d-none'); say('success', 'OTP sent to +91 ' + m + '.');
    } catch (e) { say('danger', e.code === 'auth/too-many-requests' ? 'Too many attempts. Please try again later.' : 'Could not send the OTP. Please try again.'); }
    finally { b.disabled = false; }
});
$('btnResend').addEventListener('click', () => { $('stepOtp').classList.add('d-none'); $('stepPhone').classList.remove('d-none'); $('fpAlert').innerHTML = ''; });
$('btnReset').addEventListener('click', async () => {
    const p = $('fpPass').value;
    if (p.length < 8) return say('warning', 'Password must be at least 8 characters.');
    if (p !== $('fpPass2').value) return say('warning', 'Passwords do not match.');
    const b = $('btnReset'); b.disabled = true;
    try {
        const cred = await confirmation.confirm($('fpOtp').value.trim());
        const idToken = await cred.user.getIdToken(true);
        const r = await fetch('/api/auth/phone/reset-password', { method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf },
            body: JSON.stringify({ idToken, password: p, password_confirmation: p, panel: PANEL }) });
        const j = await r.json();
        if (j.success) { say('success', j.message); setTimeout(() => location.href = @json($loginUrl), 1800); }
        else say('danger', j.message || 'Could not reset the password.');
    } catch (e) { say('danger', e.code === 'auth/invalid-verification-code' ? 'Wrong OTP. Please check and try again.' : 'Verification failed. Please request a new OTP.'); }
    finally { b.disabled = false; }
});
boot();
</script>
@endsection
