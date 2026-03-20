<?php

namespace App\Observers;

use App\Models\Brand;
use App\Services\CacheService;

class BrandObserver
{
    protected CacheService $cacheService;

    public function __construct(CacheService $cacheService)
    {
        $this->cacheService = $cacheService;
    }

    /**
     * Handle the Brand "updated" event.
     */
    public function updated(Brand $brand): void
    {
        $this->cacheService->invalidateBrand($brand->id);
    }

    /**
     * Handle the Brand "deleted" event.
     */
    public function deleted(Brand $brand): void
    {
        $this->cacheService->invalidateBrand($brand->id);
    }

    /**
     * Handle the Brand "restored" event.
     */
    public function restored(Brand $brand): void
    {
        $this->cacheService->invalidateBrand($brand->id);
    }
}
