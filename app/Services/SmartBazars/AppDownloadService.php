<?php

namespace App\Services\SmartBazars;

use App\Enums\SettingTypeEnum;
use App\Models\Setting;
use Illuminate\Support\Facades\Storage;

/** Where "Download the app" should send people: uploaded APK > external URL > Play Store link from web settings. */
class AppDownloadService
{
    public const APPS = ['customer' => 'SmartBazars', 'vendor' => 'SmartBazars Vendor', 'delivery' => 'SmartBazars Delivery'];

    public function normalize(string $app): string
    {
        return $app === 'app' ? 'customer' : $app;
    }

    public function apkPath(string $app): ?string
    {
        $path = (string)ProgramSettings::get("app_{$app}_apk", '');
        return $path !== '' && Storage::disk('local')->exists($path) ? $path : null;
    }

    public function externalUrl(string $app): ?string
    {
        $url = trim((string)ProgramSettings::get("app_{$app}_url", ''));
        if ($url === '' && $app === 'customer') {
            $web = Setting::find(SettingTypeEnum::WEB())?->value;
            $web = is_array($web) ? $web : (json_decode((string)$web, true) ?: []);
            $url = trim((string)($web['appSectionPlaystoreLink'] ?? ''));
        }
        return $url !== '' ? $url : null;
    }

    public function available(string $app): bool
    {
        return $this->apkPath($app) !== null || $this->externalUrl($app) !== null;
    }

    /** Stable public link to hand to the website / QR codes, or null if nothing is configured yet. */
    public function publicUrl(string $app): ?string
    {
        return $this->available($app) ? route('smartbazars.download', $app) : null;
    }

    public function fileName(string $app): string
    {
        $ver = trim((string)ProgramSettings::get("app_{$app}_version", ''));
        return 'SmartBazars' . ($app === 'vendor' ? '-Vendor' : ($app === 'delivery' ? '-Delivery' : '')) . ($ver !== '' ? '-v' . preg_replace('/[^0-9A-Za-z._-]/', '', $ver) : '') . '.apk';
    }
}
