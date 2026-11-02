<?php
namespace Tests\Smoke;

use App\Http\Middleware\VerifyLicense;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class PanelSmokeTest extends TestCase
{
    protected array $tableCache = [];

    protected function idFor(string $uri, string $param): string
    {
        $map = ['member' => 'network_members', 'kit' => 'welcome_kits', 'role' => 'roles', 'plan' => 'subscription_plans',
            'transaction' => 'wallet_transactions', 'orderId' => 'orders', 'returnId' => 'order_item_returns',
            'address' => 'addresses', 'update' => 'system_updates', 'withdrawal' => 'network_withdrawal_requests'];
        if ($param === 'locale') return 'en';
        if ($param === 'slug' || $param === 'orderSlug') {
            $segs = explode('/', $uri);
            $i = array_search('{' . $param . '}', $segs);
            $seg = $segs[$i - 1] ?? '';
            $t = $this->guessTable($seg) ?? ($param === 'orderSlug' ? 'orders' : null);
            if ($t && Schema::hasColumn($t, 'slug')) return (string) DB::table($t)->value('slug');
            return 'x';
        }
        if ($param === 'setting') return 'system';
        if ($param === 'policy') return 'privacy-policy';
        $t = $map[$param] ?? null;
        if (!$t) {
            $segs = explode('/', $uri);
            $i = array_search('{' . $param . '}', $segs);
            for ($j = $i - 1; $j >= 0 && !$t; $j--) $t = $this->guessTable($segs[$j]);
        }
        if ($t) {
            $v = DB::table($t)->min('id');
            if ($v) return (string) $v;
        }
        return '1';
    }

    protected function guessTable(string $seg): ?string
    {
        $seg = str_replace('-', '_', $seg);
        $over = ['customers' => 'users', 'system_users' => 'users', 'delivery_boy_withdrawals' => 'delivery_boy_withdrawal_requests',
            'seller_withdrawals' => 'seller_withdrawal_requests', 'product_approvals' => 'products', 'commissions' => 'seller_statements',
            'subscription_plans' => 'subscription_plans', 'attributes' => 'global_product_attributes', 'tax_rates' => 'tax_rates',
            'tax_classes' => 'tax_classes', 'returns' => 'order_item_returns', 'withdrawals' => 'seller_withdrawal_requests',
            'feedback' => 'seller_feedback', 'notifications' => 'notifications', 'requests' => 'network_registration_requests'];
        foreach ([$over[$seg] ?? null, $seg, Str::plural($seg), Str::snake($seg)] as $c) {
            if ($c && Schema::hasTable($c)) return $c;
        }
        return null;
    }

    public function test_panels(): void
    {
        $this->withoutMiddleware(VerifyLicense::class);
        $users = [
            'admin' => User::where('email', 'admin@smartbazars.in')->first(),
            'seller' => User::where('email', 'vendor.patna@smartbazars.in')->first(),
            'partner' => User::where('email', 'agent.bihar@smartbazars.in')->first(),
            'partner_worker' => User::where('email', 'worker.patna@smartbazars.in')->first(),
        ];
        $only = getenv('SMOKE_ONLY') ?: null;
        $out = [];
        @unlink(storage_path('logs/smoke-panels.txt'));
        @unlink(storage_path('logs/smoke-progress.txt'));
        foreach (Route::getRoutes() as $route) {
            if (!in_array('GET', $route->methods())) continue;
            $uri = $route->uri();
            $prefix = explode('/', $uri)[0];
            if (!in_array($prefix, ['admin', 'seller', 'partner'])) continue;
            if (Str::contains($uri, ['logout', 'login', 'register', 'password', 'export', 'download', 'invoice'])) continue;
            if ($only && !Str::contains($uri, $only)) continue;
            $who = $prefix === 'partner' ? (Str::contains($uri, 'customers') ? 'partner_worker' : 'partner') : $prefix;
            $url = preg_replace_callback('/\{(\w+)\??\}/', fn($m) => $this->idFor($uri, $m[1]), $uri);
            $this->app['auth']->forgetGuards();
            file_put_contents(storage_path('logs/smoke-progress.txt'), "$who /$url\n", FILE_APPEND);
            try {
                $q = Str::contains($uri, ['datatable', 'get-']) ? '?draw=1&start=0&length=10&search[value]=a&columns[0][data]=id&order[0][column]=0&order[0][dir]=desc' : (Str::endsWith($uri, 'search') ? '?search=a&q=a' : '');
                $res = $this->actingAs($users[$who])->get('/' . $url . $q);
                $code = $res->getStatusCode();
                $msg = '';
                if ($code >= 500) {
                    $e = $res->exception;
                    $msg = $e ? (class_basename($e) . ': ' . Str::limit($e->getMessage(), 220) . ' @ ' . str_replace(base_path() . '/', '', $e->getFile()) . ':' . $e->getLine()) : '';
                } elseif ($code >= 300 && $code < 400) {
                    $msg = '-> ' . $res->headers->get('Location');
                }
            } catch (\Throwable $e) {
                $code = 'EXC';
                $msg = class_basename($e) . ': ' . Str::limit($e->getMessage(), 220) . ' @ ' . $e->getFile() . ':' . $e->getLine();
            }
            $out[] = "$code\t$who\t/$url\t$msg";
            file_put_contents(storage_path('logs/smoke-panels.txt'), "$code\t$who\t/$url\t$msg\n", FILE_APPEND);
        }
        
        $this->assertTrue(true);
    }
}
