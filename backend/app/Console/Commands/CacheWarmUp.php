<?php

namespace App\Console\Commands;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Services\CacheService;
use Illuminate\Console\Command;

class CacheWarmUp extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'cache:warmup {--force : Force cache refresh}';

    /**
     * The console command description.
     */
    protected $description = 'Warm up application cache with frequently accessed data';

    /**
     * Execute the console command.
     */
    public function handle(CacheService $cacheService): int
    {
        $this->info('Starting cache warm-up...');

        if ($this->option('force')) {
            $this->warn('Clearing existing caches...');
            $cacheService->clearAllCaches();
        }

        // Cache all categories
        $this->info('Caching categories...');
        $categories = Category::all()->toArray();
        $cacheService->cacheAllCategories($categories);
        $this->info('Cached ' . count($categories) . ' categories');

        // Cache all brands
        $this->info('Caching brands...');
        $brands = Brand::all()->toArray();
        $cacheService->cacheAllBrands($brands);
        $this->info('Cached ' . count($brands) . ' brands');

        // Cache filter options
        $this->info('Caching filter options...');
        $filterOptions = [
            'scales' => Product::distinct()->pluck('scale')->filter()->values()->toArray(),
            'materials' => Product::distinct()->pluck('material')->filter()->values()->toArray(),
            'categories' => $categories,
            'brands' => $brands,
        ];
        $cacheService->cacheFilterOptions($filterOptions);
        $this->info('Cached filter options');

        // Cache top products
        $this->info('Caching popular products...');
        $topProducts = Product::where('status', 'active')
            ->orderBy('created_at', 'desc')
            ->limit(100)
            ->get();
        
        $cachedCount = 0;
        foreach ($topProducts as $product) {
            $cacheService->cacheProduct($product->id, $product->toArray());
            $cachedCount++;
        }
        $this->info('Cached ' . $cachedCount . ' popular products');

        $this->info('Cache warm-up completed successfully!');

        return Command::SUCCESS;
    }
}
