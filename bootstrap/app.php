<?php
use App\Http\Middleware\ActiveDeliveryBoy;
use App\Http\Middleware\CheckInstallation;
use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureSystemVendorTypeSelected;
use App\Http\Middleware\SetLocale;
use App\Http\Middleware\StorageCorsMiddleware;
use App\Http\Middleware\ValidateAdmin;
use App\Http\Middleware\ValidateSeller;
use App\Http\Middleware\EnsureSellerHasSubscription;
use App\Http\Middleware\VerifiedDeliveryBoy;
use App\Http\Middleware\CheckMaintenanceMode;
use App\Http\Middleware\SellerQueryTokenAuthenticate;
use App\Http\Middleware\EnsureSubscriptionFeatureSelected;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: [
            __DIR__ . '/../routes/api.php',
            __DIR__ . '/../routes/delivery-boy-api.php',
        ],
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withProviders(require __DIR__ . '/providers.php')

    ->withMiddleware(function (Middleware $middleware) {
        // SmartBazars production: correct https/IP detection behind hosting proxies, load balancers or Cloudflare
        $middleware->trustProxies(at: '*');

        $middleware->redirectGuestsTo(function (Request $request) {

            if ($request->is('admin/*') || $request->is('admin')) {
                return route('admin.login');
            }

            if ($request->is('seller/*') || $request->is('seller')) {
                return route('seller.login');
            }

            if ($request->is('partner/*') || $request->is('partner')) {
                return route('partner.login');
            }

            return route('login');
        });

        $middleware->redirectUsersTo(function (Request $request) {

            if ($request->is('admin/login') || $request->is('admin')) {
                return route('admin.dashboard');
            }

            if ($request->is('seller/login') || $request->is('seller')) {
                return route('seller.dashboard');
            }

            if ($request->is('partner/login') || $request->is('partner')) {
                return route('partner.dashboard');
            }

            return redirect('/');
        });

        $middleware->alias([
            'validate.admin' =>
                ValidateAdmin::class,

            'validate.seller' =>
                ValidateSeller::class,

            'validate.partner' =>
                \App\Http\Middleware\ValidatePartner::class,

            'kyc.complete' =>
                \App\Http\Middleware\EnsureKycComplete::class,

            'ensure.seller.subscription' =>
                EnsureSellerHasSubscription::class,

            'seller.query.token' =>
                SellerQueryTokenAuthenticate::class,

            'permission' =>
                CheckPermission::class,

            'verified.delivery.boy' =>
                VerifiedDeliveryBoy::class,

            'active.delivery.boy' =>
                ActiveDeliveryBoy::class,

            'locale' =>
                SetLocale::class,

            'storage.cors' =>
                StorageCorsMiddleware::class,

            'maintenance' =>
                CheckMaintenanceMode::class,

            'ensure.system.type' =>
                EnsureSystemVendorTypeSelected::class,

            'ensure.subscription.feature' =>
                EnsureSubscriptionFeatureSelected::class,
        ]);

        $middleware->web(
            prepend: [
                CheckMaintenanceMode::class,
                CheckInstallation::class,
            ],
            append: [
                SetLocale::class,
                StorageCorsMiddleware::class,
            ]
        );

        $middleware->api(
            prepend: [
                CheckMaintenanceMode::class,
            ]
        );
    })

    ->withExceptions(function (Exceptions $exceptions) {
        // SmartBazars: explain WHY an API request is unauthenticated (helps the web/app debug login problems)
        $exceptions->render(function (\Illuminate\Auth\AuthenticationException $e, \Illuminate\Http\Request $request) {
            if (!$request->is('api/*')) {
                return null; // panels keep redirecting to their login pages
            }
            $bearer = $request->bearerToken();
            if (!$bearer) {
                $reason = 'no_token_sent';
            } else {
                $token = \Laravel\Sanctum\PersonalAccessToken::findToken($bearer);
                $reason = !$token ? 'token_not_found_or_revoked' : (!$token->tokenable ? 'token_user_missing' : 'token_rejected');
            }
            \Illuminate\Support\Facades\Log::info('API 401', [
                'path' => $request->path(),
                'reason' => $reason,
                'token_prefix' => $bearer ? substr($bearer, 0, 6) : null,
                'origin' => $request->headers->get('origin'),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
                'reason' => $reason,
            ], 401);
        });
    })

    ->create();