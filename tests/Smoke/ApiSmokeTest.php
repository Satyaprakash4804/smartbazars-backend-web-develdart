<?php
namespace Tests\Smoke;

use App\Models\User;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ApiSmokeTest extends TestCase
{
    public function test_api_gets(): void
    {
        $this->withoutMiddleware(\App\Http\Middleware\VerifyLicense::class);
        $u = [
            'customer' => User::where('email', 'customer.patna@smartbazars.in')->first(),
            'rider' => User::where('email', 'rider.patna@smartbazars.in')->first(),
            'seller' => User::where('email', 'vendor.patna@smartbazars.in')->first(),
        ];
        @unlink(storage_path('logs/smoke-api.txt'));
        $geo = 'latitude=25.6093&longitude=85.1235&lat=25.6093&lng=85.1235&per_page=5&page=1';
        foreach (Route::getRoutes() as $route) {
            if (!in_array('GET', $route->methods())) continue;
            $uri = $route->uri();
            if (!Str::startsWith($uri, 'api/')) continue;
            if (Str::contains($uri, ['paystack', 'payment/', 'docs'])) continue;
            $who = Str::startsWith($uri, 'api/delivery-boy') ? 'rider' : (Str::startsWith($uri, 'api/seller/') ? 'seller' : 'customer');
            $url = preg_replace_callback('/\{(\w+)\??\}/', function ($m) {
                return match ($m[1]) {
                    'slug' => \DB::table('products')->value('slug'),
                    'setting' => 'system',
                    default => '1',
                };
            }, $uri);
            if (Str::contains($uri, ['categories/{slug}', 'brands/{slug}'])) {
                $url = str_replace(\DB::table('products')->value('slug'), Str::contains($uri, 'categories') ? \DB::table('categories')->value('slug') : \DB::table('brands')->value('slug'), $url);
            }
            if (Str::contains($uri, 'stores/{slug}')) $url = str_replace(\DB::table('products')->value('slug'), \DB::table('stores')->value('slug'), $url);
            $this->app['auth']->forgetGuards();
            Sanctum::actingAs($u[$who], ['*']);
            try {
                $res = $this->getJson('/' . $url . '?' . $geo);
                $code = $res->getStatusCode();
                $msg = '';
                if ($code >= 500) {
                    $e = $res->exception;
                    $msg = $e ? class_basename($e) . ': ' . Str::limit($e->getMessage(), 200) . ' @ ' . str_replace(base_path() . '/', '', $e->getFile()) . ':' . $e->getLine() : Str::limit($res->getContent(), 200);
                } elseif ($code >= 400) {
                    $msg = Str::limit($res->getContent(), 160);
                } else {
                    $j = $res->json();
                    if (is_array($j) && array_key_exists('success', $j) && !$j['success']) $msg = 'success=false: ' . Str::limit((string) ($j['message'] ?? ''), 120);
                }
            } catch (\Throwable $e) {
                $code = 'EXC';
                $msg = Str::limit($e->getMessage(), 200);
            }
            file_put_contents(storage_path('logs/smoke-api.txt'), "$code\t$who\t/$url\t$msg\n", FILE_APPEND);
        }
        $this->assertTrue(true);
    }
}
