<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\Wishlist;
use App\Models\User;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;

class WishlistModelTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_has_correct_fillable_attributes()
    {
        $fillable = [
            'user_id',
            'product_id',
        ];

        $wishlistItem = new Wishlist();
        $this->assertEquals($fillable, $wishlistItem->getFillable());
    }

    /** @test */
    public function it_belongs_to_user()
    {
        $user = User::factory()->create();
        $wishlistItem = Wishlist::factory()->create(['user_id' => $user->id]);

        $this->assertInstanceOf(User::class, $wishlistItem->user);
        $this->assertEquals($user->id, $wishlistItem->user->id);
    }

    /** @test */
    public function it_belongs_to_product()
    {
        $product = Product::factory()->create();
        $wishlistItem = Wishlist::factory()->create(['product_id' => $product->id]);

        $this->assertInstanceOf(Product::class, $wishlistItem->product);
        $this->assertEquals($product->id, $wishlistItem->product->id);
    }

    /** @test */
    public function it_creates_wishlist_item_with_user_and_product()
    {
        $user = User::factory()->create();
        $product = Product::factory()->create();

        $wishlistItem = Wishlist::factory()->create([
            'user_id' => $user->id,
            'product_id' => $product->id,
        ]);

        $this->assertEquals($user->id, $wishlistItem->user_id);
        $this->assertEquals($product->id, $wishlistItem->product_id);
        $this->assertInstanceOf(User::class, $wishlistItem->user);
        $this->assertInstanceOf(Product::class, $wishlistItem->product);
    }

    /** @test */
    public function it_has_timestamps()
    {
        $wishlistItem = Wishlist::factory()->create();

        $this->assertNotNull($wishlistItem->created_at);
        $this->assertNotNull($wishlistItem->updated_at);
        $this->assertInstanceOf(\Carbon\Carbon::class, $wishlistItem->created_at);
        $this->assertInstanceOf(\Carbon\Carbon::class, $wishlistItem->updated_at);
    }

    /** @test */
    public function it_can_be_deleted()
    {
        $wishlistItem = Wishlist::factory()->create();
        $id = $wishlistItem->id;

        $wishlistItem->delete();

        $this->assertDatabaseMissing('wishlists', ['id' => $id]);
    }

    /** @test */
    public function multiple_users_can_wishlist_same_product()
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $product = Product::factory()->create();

        $wishlistItem1 = Wishlist::factory()->create([
            'user_id' => $user1->id,
            'product_id' => $product->id,
        ]);

        $wishlistItem2 = Wishlist::factory()->create([
            'user_id' => $user2->id,
            'product_id' => $product->id,
        ]);

        $this->assertNotEquals($wishlistItem1->id, $wishlistItem2->id);
        $this->assertEquals($product->id, $wishlistItem1->product_id);
        $this->assertEquals($product->id, $wishlistItem2->product_id);
    }

    /** @test */
    public function user_can_wishlist_multiple_products()
    {
        $user = User::factory()->create();
        $product1 = Product::factory()->create();
        $product2 = Product::factory()->create();

        $wishlistItem1 = Wishlist::factory()->create([
            'user_id' => $user->id,
            'product_id' => $product1->id,
        ]);

        $wishlistItem2 = Wishlist::factory()->create([
            'user_id' => $user->id,
            'product_id' => $product2->id,
        ]);

        $this->assertNotEquals($wishlistItem1->id, $wishlistItem2->id);
        $this->assertEquals($user->id, $wishlistItem1->user_id);
        $this->assertEquals($user->id, $wishlistItem2->user_id);
    }
}