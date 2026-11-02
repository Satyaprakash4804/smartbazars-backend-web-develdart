<?php

namespace App\Console\Commands;

use App\Enums\SpatieMediaCollectionName;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Replace the demo (illustrated) images with REAL photos.
 *
 *   php artisan smartbazars:real-images                 # uses Openverse (free, no key, CC-licensed photos)
 *   php artisan smartbazars:real-images --source=pexels # uses Pexels (free key: PEXELS_API_KEY in .env)
 *   php artisan smartbazars:real-images --only=categories|products --force
 *
 * Photos are generic photos of the item type (tomatoes, basmati rice, butter...), not brand pack shots.
 * Credits are written to storage/app/public/image-credits.csv (CC BY photos need attribution).
 */
class SmartBazarsRealImages extends Command
{
    protected $signature = 'smartbazars:real-images
        {--source=auto : auto | pexels | openverse}
        {--only=all : all | products | categories}
        {--force : replace even images that were already replaced}';

    protected $description = 'Download real photos for SmartBazars demo products and categories';

    /** product title => photo search query */
    protected array $productQueries = [
        'Fresh Tomato (Desi)' => 'fresh red tomatoes',
        'Onion (Nashik)' => 'red onions',
        'Potato (Aloo)' => 'potatoes',
        'Banana Robusta' => 'bunch of bananas',
        'Shimla Apple' => 'red apples',
        'Alphonso Mango' => 'alphonso mango',
        'Fresh Palak (Spinach)' => 'fresh spinach leaves',
        'Green Chilli' => 'green chillies',
        'Fresh Carrot (Gajar)' => 'fresh carrots',
        'Lemon (Nimbu)' => 'lemons',
        'Amul Taaza Toned Milk' => 'milk glass bottle',
        'Amul Pasteurised Butter' => 'butter block',
        'Fresh Malai Paneer' => 'paneer cubes',
        'Farm Fresh White Eggs' => 'white eggs carton',
        'Britannia Brown Bread' => 'sliced brown bread',
        'Mother Dairy Classic Dahi' => 'yogurt curd bowl',
        'Aashirvaad Whole Wheat Atta' => 'whole wheat flour',
        'India Gate Basmati Rice Classic' => 'basmati rice',
        'Tata Sampann Toor Dal' => 'toor dal lentils',
        'Chana Dal (Premium)' => 'chana dal split chickpeas',
        'Tata Salt Iodised' => 'salt bowl',
        'Madhur Pure Sugar' => 'white sugar',
        'Fortune Kachi Ghani Mustard Oil' => 'mustard oil bottle',
        'Amul Pure Ghee' => 'ghee jar',
        'MDH Garam Masala' => 'garam masala spices',
        'Everest Turmeric Powder' => 'turmeric powder',
        'Dabur Honey' => 'honey jar',
        "Haldiram's Aloo Bhujia" => 'bhujia namkeen',
        "Lay's Classic Salted Chips" => 'potato chips',
        'Maggi 2-Minute Masala Noodles' => 'instant noodles bowl',
        'Kurkure Masala Munch' => 'corn puffs snack',
        'Act II Butter Popcorn' => 'butter popcorn',
        'Roasted Salted Peanuts' => 'roasted peanuts',
        'Parle-G Gold Biscuits' => 'glucose biscuits',
        'Britannia Good Day Cashew' => 'cashew cookies',
        'Cadbury Dairy Milk Silk' => 'milk chocolate bar',
        "Haldiram's Rasgulla Tin" => 'rasgulla sweets',
        'Coca-Cola Soft Drink' => 'cola soft drink glass',
        'Real Mixed Fruit Juice' => 'mixed fruit juice glass',
        'Bisleri Packaged Drinking Water' => 'bottled drinking water',
        'Tender Coconut Water' => 'tender coconut water',
        'Tata Tea Gold' => 'black tea leaves',
        'Nescafe Classic Instant Coffee' => 'instant coffee cup',
        'Wagh Bakri Masala Chai' => 'masala chai cup',
        'Dove Cream Beauty Bathing Bar' => 'white soap bar',
        'Colgate Strong Teeth Toothpaste' => 'toothpaste toothbrush',
        'Head & Shoulders Anti-Dandruff Shampoo' => 'shampoo bottle',
        'Surf Excel Easy Wash Detergent' => 'laundry detergent powder',
        'Vim Dishwash Bar' => 'dish washing sponge soap',
        'Harpic Toilet Cleaner' => 'cleaning supplies bottle',
    ];

    protected array $categoryQueries = [
        'Fruits & Vegetables' => 'fresh fruits and vegetables',
        'Dairy, Bread & Eggs' => 'milk bread eggs breakfast',
        'Atta, Rice & Dal' => 'rice lentils grains',
        'Oil, Ghee & Masala' => 'indian spices',
        'Snacks & Namkeen' => 'indian snacks namkeen',
        'Biscuits & Chocolates' => 'biscuits and chocolates',
        'Cold Drinks & Juices' => 'fruit juices drinks',
        'Tea & Coffee' => 'cup of tea and coffee',
        'Personal Care' => 'personal care products',
        'Home & Cleaning' => 'household cleaning supplies',
    ];

    protected string $source;
    protected array $credits = [];
    protected array $used = [];

    public function handle(): int
    {
        $key = config('services.pexels.key');
        $this->source = match ($this->option('source')) {
            'pexels' => 'pexels',
            'openverse' => 'openverse',
            default => $key ? 'pexels' : 'openverse',
        };
        if ($this->source === 'pexels' && !$key) {
            $this->error('Set PEXELS_API_KEY in .env (free key: https://www.pexels.com/api/) or use --source=openverse');
            return self::FAILURE;
        }
        $this->info("Downloading real photos from " . ucfirst($this->source) . ' ...');

        $only = $this->option('only');
        if (in_array($only, ['all', 'categories'])) {
            foreach ($this->categoryQueries as $title => $q) {
                $cat = Category::where('title', $title)->first();
                if (!$cat) continue;
                $this->replace($cat, $q, 'image', "Category: $title", 800, 800);
                $this->replace($cat, $q, 'banner', "Category banner: $title", 1200, 400);
            }
        }
        if (in_array($only, ['all', 'products'])) {
            foreach ($this->productQueries as $title => $q) {
                foreach (Product::where('title', $title)->get() as $p) {
                    $this->replace($p, $q, SpatieMediaCollectionName::PRODUCT_MAIN_IMAGE(), "Product: $title", 800, 800);
                }
            }
        }

        $this->writeCredits();
        $this->info('Done. Credits saved to storage/app/public/image-credits.csv');
        return self::SUCCESS;
    }

    protected function replace($model, string $query, string $collection, string $label, int $w, int $h): void
    {
        $current = $model->getFirstMedia($collection);
        if ($current && $current->getCustomProperty('real_photo') && !$this->option('force')) {
            $this->line("  skip  $label (already real photo)");
            return;
        }
        try {
            $photo = $this->find($query, $w >= $h * 2);
            if (!$photo) {
                $this->warn("  none  $label — no photo found for \"$query\"");
                return;
            }
            $tmp = $this->download($photo['url'], $w, $h);
            if (!$tmp) {
                $this->warn("  fail  $label — download failed");
                return;
            }
            $model->clearMediaCollection($collection);
            $model->addMedia($tmp)
                ->usingFileName(Str::slug($label) . '.jpg')
                ->withCustomProperties(['real_photo' => true, 'credit' => $photo['credit'], 'license' => $photo['license'], 'source' => $photo['page']])
                ->toMediaCollection($collection);
            $this->credits[] = [$label, $photo['credit'], $photo['license'], $photo['page']];
            $this->line("  <info>ok</info>    $label ← {$photo['credit']}");
        } catch (\Throwable $e) {
            $this->warn("  error $label — " . $e->getMessage());
        }
        usleep($this->source === 'openverse' ? 3_500_000 : 400_000); // stay inside free rate limits
    }

    /** @return array{url:string,credit:string,license:string,page:string}|null */
    protected function find(string $query, bool $wide): ?array
    {
        if ($this->source === 'pexels') {
            $res = Http::withHeaders(['Authorization' => config('services.pexels.key')])->timeout(30)
                ->get('https://api.pexels.com/v1/search', [
                    'query' => $query, 'per_page' => 10, 'orientation' => $wide ? 'landscape' : 'square',
                ]);
            foreach ($res->json('photos') ?? [] as $p) {
                if (isset($this->used[$p['id']])) continue;
                $this->used[$p['id']] = true;
                return [
                    'url' => $p['src']['large'] ?? $p['src']['original'],
                    'credit' => 'Photo by ' . ($p['photographer'] ?? 'Pexels') . ' on Pexels',
                    'license' => 'Pexels License',
                    'page' => $p['url'] ?? '',
                ];
            }
            return null;
        }

        // Openverse: only licences that allow commercial use; photographs only
        $res = Http::withHeaders(['User-Agent' => 'SmartBazars-demo-seeder/1.0'])->timeout(30)
            ->get('https://api.openverse.org/v1/images/', [
                'q' => $query,
                'license' => 'cc0,pdm,by,by-sa',
                'category' => 'photograph',
                'aspect_ratio' => $wide ? 'wide' : 'square',
                'page_size' => 20,
                'mature' => 'false',
            ]);
        $results = $res->json('results') ?? [];
        if (!$results && !$wide) {
            // retry without aspect filter
            $results = Http::withHeaders(['User-Agent' => 'SmartBazars-demo-seeder/1.0'])->timeout(30)
                ->get('https://api.openverse.org/v1/images/', [
                    'q' => $query, 'license' => 'cc0,pdm,by,by-sa', 'category' => 'photograph', 'page_size' => 20,
                ])->json('results') ?? [];
        }
        foreach ($results as $r) {
            if (isset($this->used[$r['id']]) || empty($r['url'])) continue;
            if (($r['width'] ?? 1000) < 500) continue; // skip tiny images
            $this->used[$r['id']] = true;
            $lic = strtoupper($r['license'] ?? '') . ' ' . ($r['license_version'] ?? '');
            return [
                'url' => $r['url'],
                'credit' => '"' . Str::limit($r['title'] ?? 'Photo', 60) . '" by ' . ($r['creator'] ?? 'unknown') . ' (' . ($r['provider'] ?? 'openverse') . ')',
                'license' => trim($lic),
                'page' => $r['foreign_landing_url'] ?? '',
            ];
        }
        return null;
    }

    /** Download, centre-crop and resize to $w x $h (if GD is available). Returns temp file path. */
    protected function download(string $url, int $w, int $h): ?string
    {
        $res = Http::withHeaders(['User-Agent' => 'SmartBazars-demo-seeder/1.0 (+https://smartbazars.in)'])
            ->timeout(60)->withOptions(['allow_redirects' => true])->get($url);
        if (!$res->ok() || strlen($res->body()) < 5000) {
            return null;
        }
        $tmp = tempnam(sys_get_temp_dir(), 'sbimg') . '.jpg';
        if (!function_exists('imagecreatefromstring')) {
            file_put_contents($tmp, $res->body());
            return $tmp;
        }
        $src = @imagecreatefromstring($res->body());
        if (!$src) {
            return null;
        }
        $sw = imagesx($src);
        $sh = imagesy($src);
        $ratio = $w / $h;
        if ($sw / $sh > $ratio) { $cw = (int)($sh * $ratio); $ch = $sh; }
        else { $cw = $sw; $ch = (int)($sw / $ratio); }
        $dst = imagecreatetruecolor($w, $h);
        imagecopyresampled($dst, $src, 0, 0, (int)(($sw - $cw) / 2), (int)(($sh - $ch) / 2), $w, $h, $cw, $ch);
        imagejpeg($dst, $tmp, 85);
        imagedestroy($src);
        imagedestroy($dst);
        return $tmp;
    }

    protected function writeCredits(): void
    {
        if (!$this->credits) return;
        $dir = storage_path('app/public');
        if (!is_dir($dir)) @mkdir($dir, 0775, true);
        $isNew = !file_exists($dir . '/image-credits.csv');
        $fh = fopen($dir . '/image-credits.csv', 'a');
        if ($isNew) {
            fputcsv($fh, ['item', 'credit', 'license', 'source']);
        }
        foreach ($this->credits as $row) fputcsv($fh, $row);
        fclose($fh);
    }
}
