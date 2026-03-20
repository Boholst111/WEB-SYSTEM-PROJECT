<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;

class Brand extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'slug',
        'description',
        'logo_url',
        'website_url',
        'country_of_origin',
        'status',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        //
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array
     */
    protected $appends = ['products_count'];

    /**
     * Get the products for the brand.
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    /**
     * Scope for active brands.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope for brands with products.
     */
    public function scopeWithProducts(Builder $query): Builder
    {
        return $query->whereHas('products');
    }

    /**
     * Get products count for the brand.
     */
    public function getProductsCountAttribute(): int
    {
        // If active_products_count is set (from API), use that instead
        if (isset($this->attributes['active_products_count'])) {
            return $this->attributes['active_products_count'];
        }
        
        return $this->products()->count();
    }

    /**
     * Get active products count for the brand.
     */
    public function getActiveProductsCountAttribute(): int
    {
        return $this->products()->active()->count();
    }
}