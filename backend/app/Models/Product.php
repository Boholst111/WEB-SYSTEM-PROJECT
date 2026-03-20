<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;

class Product extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'sku',
        'name',
        'description',
        'brand_id',
        'category_id',
        'scale',
        'material',
        'features',
        'is_chase_variant',
        'base_price',
        'current_price',
        'stock_quantity',
        'is_preorder',
        'preorder_date',
        'estimated_arrival_date',
        'status',
        'images',
        'specifications',
        'weight',
        'dimensions',
        'minimum_age',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'features' => 'array',
        'images' => 'array',
        'specifications' => 'array',
        'dimensions' => 'array',
        'is_chase_variant' => 'boolean',
        'is_preorder' => 'boolean',
        'base_price' => 'decimal:2',
        'current_price' => 'decimal:2',
        'weight' => 'decimal:2',
        'preorder_date' => 'date',
        'estimated_arrival_date' => 'date',
    ];

    /**
     * Get the brand that owns the product.
     */
    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    /**
     * Get the category that owns the product.
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Get the order items for the product.
     */
    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * Get the pre-orders for the product.
     */
    public function preorders(): HasMany
    {
        return $this->hasMany(PreOrder::class);
    }

    /**
     * Get the shopping cart items for the product.
     */
    public function cartItems(): HasMany
    {
        return $this->hasMany(ShoppingCart::class);
    }

    /**
     * Get the wishlist items for the product.
     */
    public function wishlistItems(): HasMany
    {
        return $this->hasMany(Wishlist::class);
    }

    /**
     * Get the reviews for the product.
     */
    public function reviews(): HasMany
    {
        return $this->hasMany(ProductReview::class);
    }

    /**
     * Get the inventory movements for the product.
     */
    public function inventoryMovements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class);
    }

    /**
     * Scope for active products.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope for in-stock products.
     */
    public function scopeInStock(Builder $query): Builder
    {
        return $query->where('stock_quantity', '>', 0);
    }

    /**
     * Scope for pre-order products.
     */
    public function scopePreOrder(Builder $query): Builder
    {
        return $query->where('is_preorder', true);
    }

    /**
     * Scope for chase variants.
     */
    public function scopeChaseVariant(Builder $query): Builder
    {
        return $query->where('is_chase_variant', true);
    }

    /**
     * Scope for filtering by scale.
     */
    public function scopeByScale(Builder $query, string $scale): Builder
    {
        return $query->where('scale', $scale);
    }

    /**
     * Scope for filtering by material.
     */
    public function scopeByMaterial(Builder $query, string $material): Builder
    {
        return $query->where('material', $material);
    }

    /**
     * Scope for filtering by brand.
     */
    public function scopeByBrand(Builder $query, int $brandId): Builder
    {
        return $query->where('brand_id', $brandId);
    }

    /**
     * Scope for filtering by category.
     */
    public function scopeByCategory(Builder $query, int $categoryId): Builder
    {
        return $query->where('category_id', $categoryId);
    }

    /**
     * Scope for filtering by features.
     */
    public function scopeByFeatures(Builder $query, array $features): Builder
    {
        foreach ($features as $feature) {
            $query->whereJsonContains('features', $feature);
        }
        return $query;
    }

    /**
     * Scope for price range filtering.
     */
    public function scopeByPriceRange(Builder $query, float $minPrice = null, float $maxPrice = null): Builder
    {
        if ($minPrice !== null) {
            $query->where('current_price', '>=', $minPrice);
        }
        
        if ($maxPrice !== null) {
            $query->where('current_price', '<=', $maxPrice);
        }
        
        return $query;
    }

    /**
     * Scope for full-text search.
     */
    public function scopeSearch(Builder $query, string $searchTerm): Builder
    {
        // Use LIKE search for SQLite compatibility in tests
        return $query->where(function ($q) use ($searchTerm) {
            $q->where('name', 'like', '%' . $searchTerm . '%')
              ->orWhere('description', 'like', '%' . $searchTerm . '%');
        });
    }

    /**
     * Scope for complex filtering.
     */
    public function scopeFilter(Builder $query, array $filters): Builder
    {
        if (isset($filters['scale'])) {
            $query->byScale($filters['scale']);
        }

        if (isset($filters['material'])) {
            $query->byMaterial($filters['material']);
        }

        if (isset($filters['brand_id'])) {
            $query->byBrand($filters['brand_id']);
        }

        if (isset($filters['category_id'])) {
            $query->byCategory($filters['category_id']);
        }

        if (isset($filters['features']) && is_array($filters['features'])) {
            $query->byFeatures($filters['features']);
        }

        if (isset($filters['min_price']) || isset($filters['max_price'])) {
            $query->byPriceRange($filters['min_price'] ?? null, $filters['max_price'] ?? null);
        }

        if (isset($filters['is_chase_variant']) && $filters['is_chase_variant']) {
            $query->chaseVariant();
        }

        if (isset($filters['is_preorder']) && $filters['is_preorder']) {
            $query->preOrder();
        }

        if (isset($filters['in_stock']) && $filters['in_stock']) {
            $query->inStock();
        }

        return $query;
    }

    /**
     * Check if product is available for purchase.
     */
    public function isAvailable(): bool
    {
        return $this->status === 'active' && 
               ($this->stock_quantity > 0 || $this->is_preorder);
    }

    /**
     * Check if product is low in stock.
     */
    public function isLowStock(int $threshold = 5): bool
    {
        return !$this->is_preorder && 
               $this->stock_quantity > 0 && 
               $this->stock_quantity <= $threshold;
    }

    /**
     * Get the main product image.
     */
    public function getMainImageAttribute(): ?string
    {
        $images = $this->images ?? [];
        return $images[0] ?? null;
    }

    /**
     * Get formatted price with currency.
     */
    public function getFormattedPriceAttribute(): string
    {
        return '₱' . number_format($this->current_price, 2);
    }

    /**
     * Get discount percentage if on sale.
     */
    public function getDiscountPercentageAttribute(): ?float
    {
        if ($this->base_price > $this->current_price) {
            return round((($this->base_price - $this->current_price) / $this->base_price) * 100, 2);
        }
        return null;
    }

    /**
     * Check if product is on sale.
     */
    public function isOnSale(): bool
    {
        return $this->current_price < $this->base_price;
    }

    /**
     * Get average rating from reviews.
     */
    public function getAverageRatingAttribute(): float
    {
        return $this->reviews()->avg('rating') ?? 0;
    }

    /**
     * Get total review count.
     */
    public function getReviewCountAttribute(): int
    {
        return $this->reviews()->count();
    }

    /**
     * Update stock quantity.
     */
    public function updateStock(int $quantity, string $type = 'sale'): bool
    {
        if ($type === 'sale' && $this->stock_quantity < $quantity) {
            return false; // Insufficient stock
        }

        switch ($type) {
            case 'sale':
                $newQuantity = $this->stock_quantity - $quantity;
                break;
            case 'restock':
            case 'return':
                $newQuantity = $this->stock_quantity + $quantity;
                break;
            default:
                return false; // Invalid type
        }

        $this->stock_quantity = max(0, $newQuantity);
        
        return $this->save();
    }

    /**
     * Reserve stock for order.
     */
    public function reserveStock(int $quantity): bool
    {
        if ($this->is_preorder || $this->stock_quantity >= $quantity) {
            if (!$this->is_preorder) {
                $this->stock_quantity -= $quantity;
                return $this->save();
            }
            return true; // Pre-orders don't need stock reservation
        }
        return false;
    }

    /**
     * Release reserved stock.
     */
    public function releaseStock(int $quantity): bool
    {
        if (!$this->is_preorder) {
            $this->stock_quantity += $quantity;
            return $this->save();
        }
        return true;
    }
}