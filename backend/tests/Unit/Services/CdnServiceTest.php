<?php

namespace Tests\Unit\Services;

use App\Services\CdnService;
use Tests\TestCase;

class CdnServiceTest extends TestCase
{
    protected CdnService $cdnService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cdnService = new CdnService();
    }

    public function test_returns_local_asset_url_when_cdn_disabled(): void
    {
        config(['cdn.enabled' => false]);

        $url = $this->cdnService->getAssetUrl('css/app.css');

        $this->assertStringContainsString('css/app.css', $url);
        $this->assertStringNotContainsString('cdn', $url);
    }

    public function test_returns_cdn_asset_url_when_cdn_enabled(): void
    {
        config([
            'cdn.enabled' => true,
            'cdn.url' => 'https://cdn.example.com',
            'cdn.assets_path' => 'assets',
        ]);

        $url = $this->cdnService->getAssetUrl('css/app.css');

        $this->assertEquals('https://cdn.example.com/assets/css/app.css', $url);
    }

    public function test_returns_local_image_url_when_cdn_disabled(): void
    {
        config(['cdn.enabled' => false]);

        // Mock Storage facade
        \Illuminate\Support\Facades\Storage::shouldReceive('url')
            ->once()
            ->with('products/test.jpg')
            ->andReturn('/storage/products/test.jpg');

        $url = $this->cdnService->getImageUrl('products/test.jpg');

        $this->assertEquals('/storage/products/test.jpg', $url);
    }

    public function test_returns_cdn_image_url_when_cdn_enabled(): void
    {
        config([
            'cdn.enabled' => true,
            'cdn.url' => 'https://cdn.example.com',
            'cdn.images_path' => 'images',
        ]);

        $url = $this->cdnService->getImageUrl('products/test.jpg');

        $this->assertEquals('https://cdn.example.com/images/products/test.jpg', $url);
    }

    public function test_returns_optimized_image_url_with_parameters(): void
    {
        config([
            'cdn.enabled' => true,
            'cdn.url' => 'https://cdn.example.com',
            'cdn.images_path' => 'images',
            'cdn.image_optimization.enabled' => true,
            'cdn.image_optimization.quality' => 85,
        ]);

        $url = $this->cdnService->getOptimizedImageUrl('products/test.jpg', [
            'width' => 800,
            'height' => 600,
        ]);

        $this->assertStringContainsString('https://cdn.example.com/images/products/test.jpg', $url);
        $this->assertStringContainsString('w=800', $url);
        $this->assertStringContainsString('h=600', $url);
        $this->assertStringContainsString('q=85', $url);
    }

    public function test_returns_cache_control_header_for_images(): void
    {
        config([
            'cdn.cache_control.images' => 'public, max-age=31536000, immutable',
        ]);

        $header = $this->cdnService->getCacheControlHeader('images');

        $this->assertEquals('public, max-age=31536000, immutable', $header);
    }

    public function test_returns_default_cache_control_for_unknown_type(): void
    {
        $header = $this->cdnService->getCacheControlHeader('unknown');

        $this->assertEquals('public, max-age=3600', $header);
    }

    public function test_should_use_cdn_returns_false_when_disabled(): void
    {
        config(['cdn.enabled' => false]);

        $result = $this->cdnService->shouldUseCdn('image.jpg');

        $this->assertFalse($result);
    }

    public function test_should_use_cdn_returns_true_for_allowed_types(): void
    {
        config([
            'cdn.enabled' => true,
            'cdn.asset_types' => ['jpg', 'png', 'css', 'js'],
        ]);

        $this->assertTrue($this->cdnService->shouldUseCdn('image.jpg'));
        $this->assertTrue($this->cdnService->shouldUseCdn('style.css'));
        $this->assertTrue($this->cdnService->shouldUseCdn('script.js'));
    }

    public function test_should_use_cdn_returns_false_for_disallowed_types(): void
    {
        config([
            'cdn.enabled' => true,
            'cdn.asset_types' => ['jpg', 'png'],
        ]);

        $this->assertFalse($this->cdnService->shouldUseCdn('document.pdf'));
        $this->assertFalse($this->cdnService->shouldUseCdn('video.mp4'));
    }
}
