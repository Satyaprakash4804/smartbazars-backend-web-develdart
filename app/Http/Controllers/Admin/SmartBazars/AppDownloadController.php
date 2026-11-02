<?php

namespace App\Http\Controllers\Admin\SmartBazars;

use App\Http\Controllers\Controller;
use App\Services\SmartBazars\AppDownloadService;
use App\Services\SmartBazars\ProgramSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class AppDownloadController extends Controller
{
    public function save(Request $request)
    {
        $rules = [];
        $messages = [];
        foreach (array_keys(AppDownloadService::APPS) as $a) {
            $rules["{$a}_apk"] = 'nullable|file|max:512000';
            $rules["{$a}_version"] = 'nullable|string|max:20';
            $rules["{$a}_url"] = 'nullable|url|max:300';
            $messages["{$a}_apk.uploaded"] = 'The APK failed to upload — it is probably larger than the server upload limit (upload_max_filesize / post_max_size). Raise the limit, or paste an external download link instead.';
        }
        $request->validate($rules, $messages);

        $new = [];
        foreach (array_keys(AppDownloadService::APPS) as $a) {
            $new["app_{$a}_version"] = (string)$request->input("{$a}_version", '');
            $new["app_{$a}_url"] = (string)$request->input("{$a}_url", '');
            if ($file = $request->file("{$a}_apk")) {
                if (strtolower($file->getClientOriginalExtension()) !== 'apk') {
                    throw ValidationException::withMessages(["{$a}_apk" => 'Please upload a .apk file.']);
                }
                $old = (string)ProgramSettings::get("app_{$a}_apk", '');
                $new["app_{$a}_apk"] = $file->storeAs('smartbazars/apps', "smartbazars-{$a}-" . now()->format('YmdHis') . '.apk', 'local');
                if ($old !== '') Storage::disk('local')->delete($old);
            }
        }
        ProgramSettings::save(array_merge(ProgramSettings::all(), $new));
        return back()->with('success', 'App download settings saved.');
    }

    public function remove(string $app)
    {
        abort_unless(isset(AppDownloadService::APPS[$app]), 404);
        $old = (string)ProgramSettings::get("app_{$app}_apk", '');
        if ($old !== '') Storage::disk('local')->delete($old);
        ProgramSettings::save(array_merge(ProgramSettings::all(), ["app_{$app}_apk" => '']));
        return back()->with('success', 'Uploaded APK removed.');
    }
}
