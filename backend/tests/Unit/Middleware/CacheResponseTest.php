<?php

namespace Tests\Unit\Middleware;

use App\Http\Middleware\CacheResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class CacheResponseTest extends TestCase
{
    protected CacheResponse $middleware;

    protected function setUp(): void
    {
        parent::setUp();
        $this->middleware = new CacheResponse();
        Cache::flush();
    }

    public function test_caches_get_requests(): void
    {
        $request = Request::create('/api/products', 'GET');
        
        $response = $this->middleware->handle($request, function ($req) {
            return new Response('Test Content', 200);
        }, 3600);

        $this->assertEquals('MISS', $response->headers->get('X-Cache'));
        $this->assertEquals('Test Content', $response->getContent());

        // Second request should hit cache
        $response2 = $this->middleware->handle($request, function ($req) {
            return new Response('Different Content', 200);
        }, 3600);

        $this->assertEquals('HIT', $response2->headers->get('X-Cache'));
        $this->assertEquals('Test Content', $response2->getContent());
    }

    public function test_does_not_cache_post_requests(): void
    {
        $request = Request::create('/api/products', 'POST');
        
        $response = $this->middleware->handle($request, function ($req) {
            return new Response('Test Content', 200);
        }, 3600);

        $this->assertNull($response->headers->get('X-Cache'));
    }

    public function test_does_not_cache_authenticated_requests(): void
    {
        $user = new \App\Models\User();
        $user->id = 1;
        $user->email = 'test@example.com';
        
        $request = Request::create('/api/products', 'GET');
        $request->setUserResolver(fn() => $user);
        
        $response = $this->middleware->handle($request, function ($req) {
            return new Response('Test Content', 200);
        }, 3600);

        $this->assertNull($response->headers->get('X-Cache'));
    }

    public function test_does_not_cache_error_responses(): void
    {
        $request = Request::create('/api/products', 'GET');
        
        $response = $this->middleware->handle($request, function ($req) {
            return new Response('Error', 500);
        }, 3600);

        $this->assertEquals('MISS', $response->headers->get('X-Cache'));

        // Second request should not hit cache
        $response2 = $this->middleware->handle($request, function ($req) {
            return new Response('Different Error', 500);
        }, 3600);

        $this->assertEquals('MISS', $response2->headers->get('X-Cache'));
    }

    public function test_different_query_strings_create_different_cache_keys(): void
    {
        $request1 = Request::create('/api/products?page=1', 'GET');
        $request2 = Request::create('/api/products?page=2', 'GET');
        
        $response1 = $this->middleware->handle($request1, function ($req) {
            return new Response('Page 1', 200);
        }, 3600);

        $response2 = $this->middleware->handle($request2, function ($req) {
            return new Response('Page 2', 200);
        }, 3600);

        $this->assertEquals('Page 1', $response1->getContent());
        $this->assertEquals('Page 2', $response2->getContent());
    }
}
