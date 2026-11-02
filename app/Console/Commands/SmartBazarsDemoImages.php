<?php

namespace App\Console\Commands;

use App\Enums\SpatieMediaCollectionName;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Give every product / category that has NO image a good-looking demo image.
 *   php artisan smartbazars:demo-images          (only fills missing images)
 *   php artisan smartbazars:demo-images --force  (replace existing images too)
 * Matching: exact demo product title -> its image; otherwise the image of a demo product
 * from the same category; categories match by title.
 */
class SmartBazarsDemoImages extends Command
{
    protected $signature = 'smartbazars:demo-images {--force}';
    protected $description = 'Attach SmartBazars demo images to products and categories without images';

    public function handle(): int
    {
        $dir = database_path('seeders/demo');
        $catalog = json_decode(file_get_contents($dir . '/catalog.json'), true);
        $img = fn($rel) => is_file($dir . '/images/' . $rel) ? $dir . '/images/' . $rel : null;

        // demo lookup tables
        $byTitle = [];
        $byCatTitle = [];
        foreach ($catalog['products'] as $i => $p) {
            $base = sprintf('products/%02d-%s', $i + 1, Str::slug($p[1]));
            $byTitle[mb_strtolower($p[1])] = $base;
            $catTitle = collect($catalog['categories'])->firstWhere('key', $p[0])['title'] ?? '';
            $byCatTitle[mb_strtolower($catTitle)][] = $base;
        }
        $catKey = collect($catalog['categories'])->mapWithKeys(fn($c) => [mb_strtolower($c['title']) => $c['key']]);

        $n = 0;
        foreach (Category::all() as $cat) {
            $key = $catKey[mb_strtolower($cat->title)] ?? null;
            if (!$key) continue;
            foreach (['image' => "categories/$key.jpg", 'banner' => "categories/$key-banner.jpg",
                         SpatieMediaCollectionName::CATEGORY_ICON() => "categories/$key-icon.png"] as $col => $rel) {
                $n += $this->put($cat, $col, $img($rel));
            }
        }
        foreach (Product::with('category')->get() as $p) {
            $base = $byTitle[mb_strtolower($p->title)] ?? null;
            if (!$base) {
                $list = $byCatTitle[mb_strtolower($p->category->title ?? '')] ?? [];
                $base = $list ? $list[$p->id % count($list)] : null;
            }
            if (!$base) continue;
            $n += $this->put($p, SpatieMediaCollectionName::PRODUCT_MAIN_IMAGE(), $img("$base.jpg"));
        }
        $this->info("Images attached: $n");
        return self::SUCCESS;
    }

    protected function put($model, string $collection, ?string $path): int
    {
        if (!$path) return 0;
        if ($model->getMedia($collection)->isNotEmpty()) {
            if (!$this->option('force')) return 0;
            $model->clearMediaCollection($collection);
        }
        try {
            $model->addMedia($path)->preservingOriginal()->toMediaCollection($collection);
            return 1;
        } catch (\Throwable $e) {
            $this->warn($e->getMessage());
            return 0;
        }
    }
}
