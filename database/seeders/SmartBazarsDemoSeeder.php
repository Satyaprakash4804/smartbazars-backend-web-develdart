<?php

namespace Database\Seeders;

use App\Enums\SettingTypeEnum;
use App\Enums\SpatieMediaCollectionName;
use App\Models\Address;
use App\Models\Banner;
use App\Models\Brand;
use App\Models\Category;
use App\Models\DeliveryBoy;
use App\Models\DeliveryZone;
use App\Models\FeaturedSection;
use App\Models\NetworkMember;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Seller;
use App\Models\Setting;
use App\Models\Store;
use App\Models\StoreProductVariant;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WelcomeKit;
use App\Services\SmartBazars\NetworkService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * SmartBazars demo data — run with:
 *     php artisan db:seed --class=SmartBazarsDemoSeeder
 *
 * Creates: 2 delivery zones (Delhi NCR, all Bihar), 10 categories, brands, 2 vendors + stores,
 * 50 products with images, delivery boys, customers, agents, workers, welcome kit, banners,
 * home sections and base settings (INR, Asia/Kolkata, wallet + COD).
 * Safe to run more than once (existing demo rows are reused, not duplicated).
 * All logins are listed in database/seeders/demo/DEMO_LOGINS.md
 */
class SmartBazarsDemoSeeder extends Seeder
{
    protected string $dir;
    protected array $catalog;

    public function run(): void
    {
        // Safety: demo accounts use public passwords — never create them on the live site by accident
        if (app()->environment('production') && !env('ALLOW_DEMO_SEED', false)) {
            $this->command?->error('Demo data is disabled in production (APP_ENV=production). Set ALLOW_DEMO_SEED=true only on a test server.');
            return;
        }
        $this->dir = database_path('seeders/demo');
        $this->catalog = json_decode(file_get_contents($this->dir . '/catalog.json'), true);

        $this->call([CountriesSeeder::class, DefaultRolesSeeder::class, DefaultSubscriptionPlanSeeder::class]);

        DB::transaction(function () {
            $this->settings();
            $zones = $this->zones();
            $admin = $this->admin();
            $cats = $this->categories();
            $brands = $this->brands();
            [$agentDelhi, $agentBihar] = $this->agents($admin);
            $stores = $this->vendors($zones, $agentDelhi, $agentBihar);
            $this->products($cats, $brands, $stores);
            $this->deliveryBoys($zones);
            $workers = $this->workers($stores);
            $this->customers($workers);
            $this->welcomeKit();
            $this->banners($cats);
            $this->featuredSections($cats);
        });

        foreach (['system', 'web', 'payment', 'app'] as $v) {
            try {
                app(\App\Services\SettingService::class)->clearSettingCache($v);
            } catch (\Throwable) {
            }
        }
        Cache::forget('sb_program_settings');
        $this->command?->info('SmartBazars demo data ready. Logins: database/seeders/demo/DEMO_LOGINS.md');
    }

    // ------------------------------------------------------------------ settings
    protected function mergeSetting(string $variable, array $values): void
    {
        $row = Setting::find($variable);
        $current = $row && is_array($row->value) ? $row->value : [];
        // keep anything the admin already configured, fill only empty keys
        foreach ($values as $k => $v) {
            if (!array_key_exists($k, $current) || $current[$k] === '' || $current[$k] === null) {
                $current[$k] = $v;
            }
        }
        Setting::updateOrCreate(['variable' => $variable], ['value' => json_encode($current)]);
    }

    protected function settings(): void
    {
        $this->mergeSetting('system', [
            'appName' => 'SmartBazars',
            'systemVendorType' => 'multiple',
            'systemTimezone' => 'Asia/Kolkata',
            'currency' => 'INR',
            'currencySymbol' => '₹',
            'sellerSupportEmail' => 'seller.support@smartbazars.in',
            'sellerSupportNumber' => '+91 9000000100',
            'copyrightDetails' => '© ' . date('Y') . ' SmartBazars. All rights reserved.',
            'companyAddress' => 'SmartBazars, Boring Road, Patna, Bihar 800001',
            'checkoutType' => 'multi_store',
            'minimumCartAmount' => 99,
            'maximumItemsAllowedInCart' => 50,
            'lowStockLimit' => '10',
            'maximumDistanceToNearestStore' => '1000',
            'enableWallet' => true,
            'welcomeWalletBalanceAmount' => 0,
            'demoMode' => false,
        ]);
        $this->mergeSetting('web', [
            'siteName' => 'SmartBazars',
            'siteCopyright' => '© ' . date('Y') . ' SmartBazars',
            'supportNumber' => '+91 9000000100',
            'supportEmail' => 'support@smartbazars.in',
            'address' => 'Boring Road, Patna, Bihar 800001',
            'shortDescription' => 'Your Everyday Shopping, Made Smarter. Groceries & daily needs delivered across Delhi NCR and Bihar.',
            'defaultLatitude' => '28.6315',
            'defaultLongitude' => '77.2167',
            'metaKeywords' => 'SmartBazars, online grocery Delhi, grocery delivery Patna, Bihar grocery',
            'metaDescription' => 'Order groceries, fruits, vegetables and daily essentials online from SmartBazars.',
            'allowedCountries' => ['IN'],
            'enableCountryValidation' => false,
        ]);
        $this->mergeSetting('payment', ['cod' => true, 'walletPayment' => true]);
        // decide the subscription feature so the admin panel doesn't stop at the 'choose subscription' screen
        $this->mergeSetting('subscription', ['enableSubscription' => false]);
    }

    // ------------------------------------------------------------------ zones
    protected function zones(): array
    {
        $poly = json_decode(file_get_contents($this->dir . '/zones.json'), true);
        $mk = fn($pts) => array_map(fn($p) => ['lat' => $p[0], 'lng' => $p[1]], $pts);
        $common = [
            'rush_delivery_enabled' => true, 'rush_delivery_time_per_km' => 2, 'rush_delivery_charges' => 49,
            'delivery_time_per_km' => 3, 'regular_delivery_charges' => 25, 'free_delivery_amount' => 299,
            'distance_based_delivery_charges' => 0, 'per_store_drop_off_fee' => 10, 'handling_charges' => 5,
            'delivery_boy_base_fee' => 30, 'delivery_boy_per_store_pickup_fee' => 10,
            'delivery_boy_distance_based_fee' => 5, 'delivery_boy_per_order_incentive' => 5,
            'buffer_time' => 15, 'status' => 'active',
        ];
        // match by name: the model builds the slug itself (matching by slug duplicated zones on re-run)
        $delhi = DeliveryZone::updateOrCreate(['name' => 'Delhi NCR'], $common + [ 'center_latitude' => 28.6315, 'center_longitude' => 77.2167,
            'radius_km' => 120, 'boundary_json' => $mk($poly['NCR']),
        ]);
        $bihar = DeliveryZone::updateOrCreate(['name' => 'Bihar (All Districts)'], $common + [ 'center_latitude' => 25.8600, 'center_longitude' => 85.7800,
            'radius_km' => 320, 'boundary_json' => $mk($poly['BIHAR']),
        ]);
        return ['delhi' => $delhi, 'bihar' => $bihar];
    }

    // ------------------------------------------------------------------ users
    protected function user(string $email, array $attrs): User
    {
        $u = User::withTrashed()->where('email', $email)->first();
        if ($u) {
            if ($u->trashed()) $u->restore();
            return $u;
        }
        return User::create($attrs + ['email' => $email, 'status' => 'active']);
    }

    protected function admin(): User
    {
        $admin = User::where('access_panel', 'admin')->orderBy('id')->first()
            ?? $this->user('admin@smartbazars.in', [
                'name' => 'SmartBazars Admin', 'mobile' => '9000000001', 'password' => 'Admin@123', 'access_panel' => 'admin',
            ]);
        if (!$admin->hasRole('Super Admin')) {
            $admin->assignRole('Super Admin');
        }
        return $admin;
    }

    protected function agents(User $admin): array
    {
        $net = app(NetworkService::class);
        $mk = function ($email, $name, $mobile, ?NetworkMember $parent) use ($net, $admin) {
            $u = $this->user($email, ['name' => $name, 'mobile' => $mobile, 'password' => 'Agent@123', 'access_panel' => 'partner']);
            return NetworkMember::where('user_id', $u->id)->first()
                ?? $net->createMember($u, NetworkMember::TYPE_AGENT, $parent, null, $parent ? 'agent' : 'admin', $parent?->id ?? $admin->id);
        };
        $top = $mk('agent@smartbazars.in', 'Rakesh Verma', '9000000011', null);            // directly under admin
        $delhi = $mk('agent.delhi@smartbazars.in', 'Sunil Kumar', '9000000012', $top);        // under Rakesh
        $bihar = $mk('agent.bihar@smartbazars.in', 'Amit Raj', '9000000013', $top);           // under Rakesh
        $mk('agent.sub@smartbazars.in', 'Pooja Singh', '9000000014', $bihar);                 // 3rd level
        return [$delhi, $bihar];
    }

    protected function vendors(array $zones, NetworkMember $agentDelhi, NetworkMember $agentBihar): array
    {
        $defs = [
            'delhi' => [
                'email' => 'vendor.delhi@smartbazars.in', 'owner' => 'Rajesh Sharma', 'mobile' => '9000000021',
                'store' => 'Sharma Fresh Mart', 'address' => 'Shop 14, Central Market, Lajpat Nagar II', 'city' => 'New Delhi',
                'landmark' => 'Near Lajpat Nagar Metro Station', 'state' => 'Delhi', 'zip' => '110024',
                'lat' => 28.5677, 'lng' => 77.2433, 'agent' => $agentDelhi, 'zone' => $zones['delhi'],
                'gst' => '07ABCPS1234F1Z5', 'bank' => 'HDFC Bank', 'ifsc' => 'HDFC0000123', 'acc' => '50100234567812',
                'img' => 'delhi-store',
            ],
            'bihar' => [
                'email' => 'vendor.patna@smartbazars.in', 'owner' => 'Manoj Prasad', 'mobile' => '9000000022',
                'store' => 'Patliputra Kirana Bhandar', 'address' => '12, Boring Road Chauraha', 'city' => 'Patna',
                'landmark' => 'Opp. A.N. College', 'state' => 'Bihar', 'zip' => '800001',
                'lat' => 25.6100, 'lng' => 85.1167, 'agent' => $agentBihar, 'zone' => $zones['bihar'],
                'gst' => '10AAKPP5678K1Z2', 'bank' => 'State Bank of India', 'ifsc' => 'SBIN0000152', 'acc' => '38912345678',
                'img' => 'patna-store',
            ],
        ];
        $stores = [];
        foreach ($defs as $key => $d) {
            $u = $this->user($d['email'], ['name' => $d['owner'], 'mobile' => $d['mobile'], 'password' => 'Vendor@123', 'access_panel' => 'seller']);
            if (!$u->hasRole('seller')) $u->assignRole('seller');
            $seller = Seller::updateOrCreate(['user_id' => $u->id], [
                'agent_member_id' => $d['agent']->id, 'address' => $d['address'], 'city' => $d['city'], 'landmark' => $d['landmark'],
                'state' => $d['state'], 'zipcode' => $d['zip'], 'country' => 'India', 'country_code' => '91',
                'latitude' => $d['lat'], 'longitude' => $d['lng'],
                'verification_status' => 'approved', 'visibility_status' => 'visible',
                'metadata' => ['store_name' => $d['store'], 'onboarded_via' => 'agent'],
            ]);
            $store = Store::where('seller_id', $seller->id)->first() ?? Store::create([
                'seller_id' => $seller->id, 'name' => $d['store'], 'address' => $d['address'], 'city' => $d['city'],
                'landmark' => $d['landmark'], 'state' => $d['state'], 'zipcode' => $d['zip'], 'country' => 'India',
                'country_code' => '91', 'latitude' => $d['lat'], 'longitude' => $d['lng'],
                'contact_email' => $d['email'], 'contact_number' => $d['mobile'],
                'description' => "{$d['store']} — trusted kirana & fresh produce store in {$d['city']}, now delivering with SmartBazars.",
                'timing' => null,
                'tax_name' => 'GSTIN', 'tax_number' => $d['gst'], 'bank_name' => $d['bank'], 'bank_branch_code' => $d['ifsc'],
                'account_holder_name' => $d['owner'], 'account_number' => $d['acc'], 'routing_number' => $d['ifsc'],
                'bank_account_type' => 'savings', 'currency_code' => 'INR',
                'max_delivery_distance' => 1000, 'order_preparation_time' => 15,
                'promotional_text' => 'Free delivery on orders above ₹299',
                'about_us' => "Serving families in {$d['city']} since 2009.",
                'return_replacement_policy' => 'Fresh produce can be returned at the doorstep. Packaged items within 2 days if unopened.',
                'refund_policy' => 'Refunds are credited to your SmartBazars wallet within 24 hours.',
                'terms_and_conditions' => 'Prices are inclusive of all taxes. Images are for representation.',
                'delivery_policy' => 'Delivery across the zone within the promised slot.',
                'verification_status' => 'approved', 'visibility_status' => 'visible',
                'fulfillment_type' => 'hyperlocal', 'status' => 'online',
            ]);
            $store->zones()->syncWithoutDetaching([$d['zone']->id]);
            $this->media($store, "misc/{$d['img']}-logo.jpg", SpatieMediaCollectionName::STORE_LOGO());
            $this->media($store, "misc/{$d['img']}-banner.jpg", SpatieMediaCollectionName::STORE_BANNER());
            $stores[$key] = $store;
        }
        return $stores;
    }

    protected function deliveryBoys(array $zones): void
    {
        $rows = [
            ['rider.delhi@smartbazars.in', 'Vikas Yadav', '9000000031', 'delhi', 'Kalkaji, New Delhi', 'DL01 AB 4521', 'DL-0120190012345'],
            ['rider.patna@smartbazars.in', 'Ravi Paswan', '9000000032', 'bihar', 'Kankarbagh, Patna', 'BR01 CK 7788', 'BR-0120200056789'],
        ];
        foreach ($rows as [$email, $name, $mobile, $zone, $addr, $vehicle, $dl]) {
            $u = $this->user($email, ['name' => $name, 'mobile' => $mobile, 'password' => 'Rider@123', 'access_panel' => 'web']);
            DeliveryBoy::updateOrCreate(['user_id' => $u->id], [
                'delivery_zone_id' => $zones[$zone]->id, 'status' => 'active', 'full_name' => $name, 'address' => $addr,
                'driver_license_number' => $dl, 'vehicle_type' => 'bike', 'vehicle_registration' => $vehicle,
                'verification_status' => 'verified',
            ]);
            Wallet::firstOrCreate(['user_id' => $u->id], ['balance' => 0, 'currency_code' => 'INR']);
        }
    }

    protected function workers(array $stores): array
    {
        $net = app(NetworkService::class);
        $out = [];
        foreach ([
            'delhi' => ['worker.delhi@smartbazars.in', 'Neha Gupta', '9000000041'],
            'bihar' => ['worker.patna@smartbazars.in', 'Rohit Kumar', '9000000042'],
        ] as $key => [$email, $name, $mobile]) {
            $u = $this->user($email, ['name' => $name, 'mobile' => $mobile, 'password' => 'Worker@123', 'access_panel' => 'partner']);
            $out[$key] = NetworkMember::where('user_id', $u->id)->first()
                ?? $net->createMember($u, NetworkMember::TYPE_WORKER, null, $stores[$key]->seller_id, 'seller', $stores[$key]->seller_id);
        }
        return $out;
    }

    protected function customers(array $workers): void
    {
        $rows = [
            ['customer.delhi@smartbazars.in', 'Priya Kumari', '9000000051', 'delhi',
                ['B-42, Second Floor, Lajpat Nagar IV', 'Near Moolchand Hospital', 'New Delhi', 'Delhi', '110024', 28.5660, 77.2440]],
            ['customer.noida@smartbazars.in', 'Arjun Mehta', '9000000052', 'delhi',
                ['Tower C-1203, Sector 62', 'Near Fortis Hospital', 'Noida', 'Uttar Pradesh', '201309', 28.6270, 77.3640]],
            ['customer.patna@smartbazars.in', 'Sneha Sinha', '9000000053', 'bihar',
                ['Flat 3B, Shanti Apartment, Boring Canal Road', 'Near Rajapur Pul', 'Patna', 'Bihar', '800001', 25.6140, 85.1210]],
            ['customer.gaya@smartbazars.in', 'Rahul Mishra', '9000000054', 'bihar',
                ['House 17, A.P. Colony', 'Near Gaya College', 'Gaya', 'Bihar', '823001', 24.7960, 85.0080]],
        ];
        foreach ($rows as [$email, $name, $mobile, $zone, $a]) {
            $u = $this->user($email, [
                'name' => $name, 'mobile' => $mobile, 'password' => 'Customer@123', 'access_panel' => 'web',
                'referral_code' => strtoupper(substr(md5($email), 0, 8)),
            ]);
            $u->forceFill([
                'referred_by_worker_id' => $u->referred_by_worker_id ?: $workers[$zone]->id,
                'coin_balance' => max((int)$u->coin_balance, 50),
            ])->save();
            $w = Wallet::firstOrCreate(['user_id' => $u->id], ['balance' => 1000, 'currency_code' => 'INR']);
            if ((float)$w->balance < 1000) {
                $w->update(['balance' => 1000]);
            }
            Address::firstOrCreate(['user_id' => $u->id, 'address_line1' => $a[0]], [
                'address_line2' => '', 'landmark' => $a[1], 'city' => $a[2], 'state' => $a[3], 'zipcode' => $a[4],
                'mobile' => $mobile, 'address_type' => 'home', 'country' => 'India', 'country_code' => '91',
                'latitude' => $a[5], 'longitude' => $a[6],
            ]);
        }
    }

    // ------------------------------------------------------------------ catalogue
    protected function categories(): array
    {
        $out = [];
        foreach ($this->catalog['categories'] as $i => $c) {
            $cat = Category::where('title', $c['title'])->whereNull('parent_id')->first();
            if (!$cat) {
                $cat = Category::create([
                    'title' => $c['title'], 'description' => $c['desc'], 'status' => 'active', 'requires_approval' => false,
                    'commission' => $c['commission'], 'sort_order' => $i + 1, 'is_home_category' => true,
                    'background_type' => 'color', 'background_color' => $c['color'], 'font_color' => $c['accent'],
                    'metadata' => ['seo_title' => $c['title'] . ' | SmartBazars', 'seo_description' => $c['desc'], 'seo_keywords' => $c['title']],
                ]);
            }
            $this->media($cat, "categories/{$c['key']}.jpg", SpatieMediaCollectionName::CATEGORY_IMAGE());
            $this->media($cat, "categories/{$c['key']}-banner.jpg", SpatieMediaCollectionName::CATEGORY_BANNER());
            $this->media($cat, "categories/{$c['key']}-icon.png", SpatieMediaCollectionName::CATEGORY_ICON());
            $this->media($cat, "categories/{$c['key']}-icon.png", SpatieMediaCollectionName::CATEGORY_ACTIVE_ICON());
            $out[$c['key']] = $cat;
        }
        return $out;
    }

    protected function brands(): array
    {
        $out = [];
        foreach (array_unique(array_filter(array_column($this->catalog['products'], 2))) as $name) {
            $b = Brand::where('title', $name)->first() ?? Brand::create([
                'title' => $name, 'description' => "$name products at the best price on SmartBazars", 'status' => 'active', 'scope_type' => 'global',
            ]);
            $this->media($b, 'brands/' . \Illuminate\Support\Str::slug($name) . '.png', 'brand');
            $out[$name] = $b;
        }
        return $out;
    }

    protected function products(array $cats, array $brands, array $stores): void
    {
        foreach ($this->catalog['products'] as $i => $p) {
            [$catKey, $title, $brand, , $unit, $mrp, $sp, $indicator, $coins, $desc] = $p;
            // Vendors split the catalogue: Delhi store gets even rows, Patna store odd rows,
            // and daily essentials (every 5th) are stocked in both stores.
            $owner = $i % 2 === 0 ? 'delhi' : 'bihar';
            $sellIn = $i % 5 === 0 ? ['delhi', 'bihar'] : [$owner];
            $store = $stores[$owner];

            $product = Product::where('seller_id', $store->seller_id)->where('title', $title)->first();
            if (!$product) {
                $product = Product::create([
                    'seller_id' => $store->seller_id,
                    'category_id' => $cats[$catKey]->id,
                    'brand_id' => $brand ? $brands[$brand]->id : null,
                    'title' => $title,
                    'type' => 'simple',
                    'base_prep_time' => 10,
                    'short_description' => "$title — $unit",
                    'description' => "<p>$desc</p><ul><li>Pack size: $unit</li><li>Delivered fresh by SmartBazars</li><li>Easy doorstep returns</li></ul>",
                    'indicator' => $indicator,
                    'image_fit' => 'cover',
                    'minimum_order_quantity' => 1,
                    'quantity_step_size' => 1,
                    'total_allowed_quantity' => 10,
                    'is_returnable' => in_array($catKey, ['personal-care', 'home-care', 'staples']) ? '1' : '0',
                    'returnable_days' => in_array($catKey, ['personal-care', 'home-care', 'staples']) ? 3 : null,
                    'is_cancelable' => '1',
                    'cancelable_till' => 'preparing',
                    'featured' => $i % 4 === 0 ? '1' : '0',
                    'requires_otp' => '0',
                    'video_type' => null,
                    'made_in' => 'India',
                    'tags' => json_encode([$cats[$catKey]->title, $brand ?: 'Fresh', 'SmartBazars']),
                    'is_inclusive_tax' => '1',
                ]);
                $product->forceFill(['status' => 'active', 'verification_status' => 'approved', 'cashback_coins' => $coins])->save();

                $variant = ProductVariant::create([
                    'uuid' => (string)\Illuminate\Support\Str::uuid(),
                    'product_id' => $product->id,
                    'title' => $title,
                    'weight' => 1, 'height' => 1, 'breadth' => 1, 'length' => 1,
                    'barcode' => '890' . str_pad((string)(1000000 + $i * 7919), 10, '0', STR_PAD_LEFT),
                    'availability' => 1,
                    'visibility' => 'published',
                    'is_default' => true,
                ]);
            } else {
                $product->forceFill(['cashback_coins' => $coins])->save();
                $variant = $product->variants()->first();
            }

            foreach ($sellIn as $k) {
                StoreProductVariant::firstOrCreate(
                    ['product_variant_id' => $variant->id, 'store_id' => $stores[$k]->id],
                    ['sku' => 'SB-' . strtoupper($k[0]) . '-' . str_pad((string)($i + 1), 4, '0', STR_PAD_LEFT),
                        'price' => $mrp, 'special_price' => $sp, 'cost' => round($sp * 0.82, 2), 'stock' => rand(40, 200)]
                );
            }

            $base = sprintf('products/%02d-%s', $i + 1, \Illuminate\Support\Str::slug($title));
            $this->media($product, "$base.jpg", SpatieMediaCollectionName::PRODUCT_MAIN_IMAGE());
            $this->media($product, "$base-2.jpg", SpatieMediaCollectionName::PRODUCT_ADDITIONAL_IMAGE());
        }
    }

    protected function welcomeKit(): void
    {
        $kit = WelcomeKit::firstOrCreate(['name' => 'SmartBazars Welcome Kit'], [
            'description' => 'Your starter pack of daily essentials worth more than ₹300 — plus your SmartBazars User Card for member benefits.',
            'price' => 250, 'is_active' => true,
        ]);
        if (!$kit->image) {
            $dest = 'welcome-kits/welcome-kit-demo.jpg';
            \Illuminate\Support\Facades\Storage::disk('public')->put($dest, file_get_contents($this->dir . '/images/misc/welcome-kit.jpg'));
            $kit->update(['image' => $dest]);
        }
        if (!$kit->items()->exists()) {
            $items = ['Tata Salt Iodised' => 1, 'Madhur Pure Sugar' => 1, 'Tata Sampann Toor Dal' => 1, 'Parle-G Gold Biscuits' => 1, 'Vim Dishwash Bar' => 1];
            $n = 0;
            foreach ($items as $title => $qty) {
                $p = Product::where('title', $title)->first();
                $kit->items()->create(['product_id' => $p?->id, 'item_name' => $title, 'quantity' => $qty, 'sort' => ++$n]);
            }
        }
        WelcomeKit::where('id', '!=', $kit->id)->update(['is_active' => false]);
        $kit->update(['is_active' => true]);
    }

    protected function banners(array $cats): void
    {
        $rows = [
            ['Fresh groceries in minutes', 'home-1', 'category', $cats['fruits-veg']->id],
            ['Earn cashback coins', 'home-2', 'category', $cats['staples']->id],
            ['Get your SmartBazars User Card', 'home-3', 'custom', null],
        ];
        foreach ($rows as $i => [$title, $img, $type, $catId]) {
            $b = Banner::where('title', $title)->first() ?? Banner::create([
                'type' => $type, 'scope_type' => 'global', 'title' => $title,
                'custom_url' => $type === 'custom' ? '/welcome-kit' : null,
                'category_id' => $catId, 'position' => $i === 0 ? 'top' : 'carousel',
                'visibility_status' => 'published', 'display_order' => $i + 1,
            ]);
            $this->media($b, "misc/banner-$img.jpg", SpatieMediaCollectionName::BANNER_IMAGE());
        }
    }

    protected function featuredSections(array $cats): void
    {
        $rows = [
            ['Featured Picks', 'featured', 'Hand-picked best sellers from your neighbourhood stores'],
            ['Newly Added', 'newly_added', 'Fresh arrivals on SmartBazars'],
            ['Best Sellers', 'best_seller', 'What everyone is buying this week'],
        ];
        foreach ($rows as $i => [$title, $type, $desc]) {
            $fs = FeaturedSection::where('title', $title)->first() ?? FeaturedSection::create([
                'title' => $title, 'short_description' => $desc, 'style' => 'without_background',
                'section_type' => $type, 'sort_order' => $i + 1, 'status' => 'active', 'scope_type' => 'global',
                'text_color' => '#1B5E20',
            ]);
            if (method_exists($fs, 'categories')) {
                $fs->categories()->syncWithoutDetaching(array_map(fn($c) => $c->id, array_values($cats)));
            }
        }
    }

    // ------------------------------------------------------------------ helpers
    protected function media($model, string $rel, string $collection): void
    {
        $path = $this->dir . '/images/' . $rel;
        if (!is_file($path) || $model->getMedia($collection)->isNotEmpty()) {
            return;
        }
        try {
            $model->addMedia($path)->preservingOriginal()->toMediaCollection($collection);
        } catch (\Throwable $e) {
            $this->command?->warn("Image skipped ($rel): " . $e->getMessage());
        }
    }
}
