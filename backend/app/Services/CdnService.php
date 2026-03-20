<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;

class CdnService
{
    /**
     * Get CDN URL for an asset
     */
    public function getAssetUrl(string $path): string
    {
        if (!config('cdn.enabled')) {
            return asset($path);
        }

        $cdnUrl = rtrim(config('cdn.url'), '/');
        $assetsPath = config('cdn.assets_path');
        
        return "{$cdnUrl}/{$assetsPath}/{$path}";
    }

    /**
     * Get CDN URL for a product image
     */
    public function getImageUrl(string $path): string
    {
        if (!config('cdn.enabled')) {
            return Storage::url($path);
        }

        $cdnUrl = rtrim(config('cdn.url'), '/');
        $imagesPath = config('cdn.images_path');
        
        return "{$cdnUrl}/{$imagesPath}/{$path}";
    }

    /**
     * Get optimized image URL with transformations
     */
    public function getOptimizedImageUrl(string $path, array $options = []): string
    {
        $url = $this->getImageUrl($path);

        if (!config('cdn.image_optimization.enabled')) {
            return $url;
        }

        // Add query parameters for image optimization
        $params = [];
        
        if (isset($options['width'])) {
            $params['w'] = $options['width'];
        }
        
        if (isset($options['height'])) {
            $params['h'] = $options['height'];
        }
        
        if (isset($options['quality'])) {
            $params['q'] = $options['quality'];
        } else {
            $params['q'] = config('cdn.image_optimization.quality');
        }
        
        if (isset($options['format'])) {
            $params['f'] = $options['format'];
        }

        if (!empty($params)) {
            $url .= '?' . http_build_query($params);
        }

        return $url;
    }

    /**
     * Get cache control header for asset type
     */
    public function getCacheControlHeader(string $assetType): string
    {
        $cacheControl = config('cdn.cache_control');
        
        return $cacheControl[$assetType] ?? 'public, max-age=3600';
    }

    /**
     * Check if asset should be served from CDN
     */
    public function shouldUseCdn(string $path): bool
    {
        if (!config('cdn.enabled')) {
            return false;
        }

        $extension = pathinfo($path, PATHINFO_EXTENSION);
        $allowedTypes = config('cdn.asset_types');

        return in_array(strtolower($extension), $allowedTypes);
    }
}
