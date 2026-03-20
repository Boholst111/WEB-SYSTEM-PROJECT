<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Category;
use App\Models\Brand;
use App\Models\Product;
use Illuminate\Support\Facades\DB;

echo "=== SEEDING SAMPLE DATA ===\n\n";

try {
    DB::beginTransaction();
    
    // Create categories
    echo "Creating categories...\n";
    $categories = [
        ['name' => 'Die-cast Cars', 'slug' => 'die-cast-cars', 'is_active' => true],
        ['name' => 'Model Kits', 'slug' => 'model-kits', 'is_active' => true],
        ['name' => 'Action Figures', 'slug' => 'action-figures', 'is_active' => true],
    ];
    
    foreach ($categories as $cat) {
        Category::firstOrCreate(['slug' => $cat['slug']], $cat);
    }
    
    // Create brands
    echo "Creating brands...\n";
    $brands = [
        ['name' => 'Hot Wheels', 'slug' => 'hot-wheels', 'is_active' => true],
        ['name' => 'Matchbox', 'slug' => 'matchbox', 'is_active' => true],
        ['name' => 'Tomica', 'slug' => 'tomica', 'is_active' => true],
    ];
    
    foreach ($brands as $brand) {
        Brand::firstOrCreate(['slug' => $brand['slug']], $brand);
    }
    
    // Create products
    echo "Creating products...\n";
    $category = Category::first();
    $brand = Brand::first();
    
    if ($category && $brand) {
        for ($i = 1; $i <= 10; $i++) {
            Product::firstOrCreate(
                ['sku' => 'PROD-' . str_pad($i, 4, '0', STR_PAD_LEFT)],
                [
                    'name' => "Sample Product {$i}",
                    'description' => "This is a sample product for testing",
                    'brand_id' => $brand->id,
                    'category_id' => $category->id,
                    'scale' => '1:64',
                    'material' => 'Die-cast Metal',
                    'features' => json_encode(['Opening doors', 'Detailed interior']),
                    'is_chase_variant' => false,
                    'base_price' => 100.00 + ($i * 10),
                    'current_price' => 100.00 + ($i * 10),
                    'stock_quantity' => 50,
                    'is_preorder' => false,
                    'status' => 'active',
                    'images' => json_encode([]),
                    'specifications' => json_encode([]),
                ]
            );
        }
    }
    
    DB::commit();
    
    echo "\n✅ Sample data created successfully!\n";
    echo "Categories: " . Category::count() . "\n";
    echo "Brands: " . Brand::count() . "\n";
    echo "Products: " . Product::count() . "\n";
    
} catch (\Exception $e) {
    DB::rollBack();
    echo "\n❌ Error: " . $e->getMessage() . "\n";
}
