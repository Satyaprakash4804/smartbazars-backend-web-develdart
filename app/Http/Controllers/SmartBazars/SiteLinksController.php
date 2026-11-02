<?php

namespace App\Http\Controllers\SmartBazars;

use App\Http\Controllers\Controller;
use App\Services\SmartBazars\AppDownloadService;
use App\Services\SmartBazars\ProgramSettings;
use Illuminate\Support\Facades\Storage;

/** Public: app download + a drop-in script that adds Partner / Vendor login links to the website footer. */
class SiteLinksController extends Controller
{
    public function __construct(protected AppDownloadService $apps) {}

    public function download(string $app)
    {
        $app = $this->apps->normalize($app);

        if ($path = $this->apps->apkPath($app)) {
            return Storage::disk('local')->download($path, $this->apps->fileName($app), [
                'Content-Type' => 'application/vnd.android.package-archive',
                'Cache-Control' => 'no-cache',
            ]);
        }
        if ($url = $this->apps->externalUrl($app)) {
            return redirect()->away($url);
        }
        abort(404, 'The app download is not available yet. Please check back soon.');
    }

    public function footerJs()
    {
        $links = [
            ['t' => 'Partner Login', 'u' => route('partner.login')],
            ['t' => 'Vendor Login', 'u' => route('seller.login')],
            ['t' => 'Become a Partner', 'u' => route('partner.apply')],
            ['t' => 'Become a Vendor', 'u' => ProgramSettings::get('vendor_apply_url') ?: 'https://smartbazars.in/seller-register/'],
        ];
        $apps = [];
        foreach (['customer' => 'Customer App', 'vendor' => 'Vendor App', 'delivery' => 'Delivery App'] as $key => $label) {
            if ($u = $this->apps->publicUrl($key)) {
                $apps[] = ['t' => $label, 'u' => $u];
            }
        }
        $appsJson = json_encode($apps, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP);
        $json = json_encode($links, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP);

        $js = <<<JS
(function () {
  var ID = 'sb-partner-links', LINKS = {$json}, APPS = {$appsJson};
  function row(items, label) {
    var d = document.createElement('div');
    d.style.cssText = 'margin:2px 0;';
    if (label) { var b = document.createElement('strong'); b.textContent = label + ' '; d.appendChild(b); }
    items.forEach(function (l) {
      var a = document.createElement('a');
      a.href = l.u; a.textContent = l.t;
      a.style.cssText = 'color:inherit;margin:0 10px;text-decoration:underline;white-space:nowrap;';
      d.appendChild(a);
    });
    return d;
  }
  function mount() {
    var f = document.querySelector('footer'), el = document.getElementById(ID);
    if (el) { if (f && !f.contains(el)) f.appendChild(el); return; }
    el = document.createElement('div');
    el.id = ID;
    el.style.cssText = 'text-align:center;padding:12px 16px;font-size:14px;line-height:2;';
    el.appendChild(row(LINKS));
    if (APPS.length) el.appendChild(row(APPS, 'Install our apps:'));
    (f || document.body).appendChild(el);
  }
  if (document.readyState !== 'loading') mount(); else document.addEventListener('DOMContentLoaded', mount);
  // single-page sites re-render the footer on navigation — put the links back
  new MutationObserver(mount).observe(document.documentElement, {childList: true, subtree: true});
})();
JS;
        return response($js, 200, ['Content-Type' => 'application/javascript; charset=UTF-8', 'Cache-Control' => 'public, max-age=300']);
    }
}
