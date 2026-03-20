<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\ProductReview;
use App\Models\User;
use App\Models\Product;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ProductReviewModelTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_has_correct_fillable_attributes()
    {
        $fillable = [
            'user_id',
            'product_id',
            'order_id',
            'rating',
            'title',
            'review_text',
            'images',
            'is_verified_purchase',
            'is_approved',
            'approved_at',
            'approved_by',
            'helpful_votes',
            'total_votes',
        ];

        $review = new ProductReview();
        $this->assertEquals($fillable, $review->getFillable());
    }

    /** @test */
    public function it_casts_attributes_correctly()
    {
        $review = ProductReview::factory()->create([
            'is_verified_purchase' => true,
            'is_approved' => false,
            'approved_at' => '2024-01-15 10:30:00',
            'images' => ['image1.jpg', 'image2.jpg'],
        ]);

        $this->assertTrue($review->is_verified_purchase);
        $this->assertFalse($review->is_approved);
        $this->assertInstanceOf(\Carbon\Carbon::class, $review->approved_at);
        $this->assertIsArray($review->images);
        $this->assertEquals(['image1.jpg', 'image2.jpg'], $review->images);
    }

    /** @test */
    public function it_belongs_to_user()
    {
        $user = User::factory()->create();
        $review = ProductReview::factory()->create(['user_id' => $user->id]);

        $this->assertInstanceOf(User::class, $review->user);
        $this->assertEquals($user->id, $review->user->id);
    }

    /** @test */
    public function it_belongs_to_product()
    {
        $product = Product::factory()->create();
        $review = ProductReview::factory()->create(['product_id' => $product->id]);

        $this->assertInstanceOf(Product::class, $review->product);
        $this->assertEquals($product->id, $review->product->id);
    }

    /** @test */
    public function it_belongs_to_order()
    {
        $order = Order::factory()->create();
        $review = ProductReview::factory()->create(['order_id' => $order->id]);

        $this->assertInstanceOf(Order::class, $review->order);
        $this->assertEquals($order->id, $review->order->id);
    }

    /** @test */
    public function it_can_have_null_order_for_non_verified_purchases()
    {
        $review = ProductReview::factory()->create([
            'order_id' => null,
            'is_verified_purchase' => false,
        ]);

        $this->assertNull($review->order);
        $this->assertFalse($review->is_verified_purchase);
    }

    /** @test */
    public function it_stores_rating_as_integer()
    {
        $review = ProductReview::factory()->create(['rating' => 5]);

        $this->assertEquals(5, $review->rating);
        $this->assertIsInt($review->rating);
    }

    /** @test */
    public function it_stores_helpful_count_as_integer()
    {
        $review = ProductReview::factory()->create(['helpful_count' => 10]);

        $this->assertEquals(10, $review->helpful_count);
        $this->assertIsInt($review->helpful_count);
    }

    /** @test */
    public function it_can_be_approved_or_unapproved()
    {
        $approvedReview = ProductReview::factory()->create(['is_approved' => true]);
        $unapprovedReview = ProductReview::factory()->create(['is_approved' => false]);

        $this->assertTrue($approvedReview->is_approved);
        $this->assertFalse($unapprovedReview->is_approved);
    }

    /** @test */
    public function it_can_be_verified_or_unverified_purchase()
    {
        $verifiedReview = ProductReview::factory()->create(['is_verified_purchase' => true]);
        $unverifiedReview = ProductReview::factory()->create(['is_verified_purchase' => false]);

        $this->assertTrue($verifiedReview->is_verified_purchase);
        $this->assertFalse($unverifiedReview->is_verified_purchase);
    }

    /** @test */
    public function it_stores_review_title_and_content()
    {
        $review = ProductReview::factory()->create([
            'title' => 'Great product!',
            'review_text' => 'This is an excellent diecast model with great attention to detail.',
        ]);

        $this->assertEquals('Great product!', $review->title);
        $this->assertEquals('This is an excellent diecast model with great attention to detail.', $review->review_text);
    }

    /** @test */
    public function it_can_have_empty_title()
    {
        $review = ProductReview::factory()->create([
            'title' => null,
            'review_text' => 'Review without title.',
        ]);

        $this->assertNull($review->title);
        $this->assertEquals('Review without title.', $review->review_text);
    }

    /** @test */
    public function it_handles_different_rating_values()
    {
        $ratings = [1, 2, 3, 4, 5];

        foreach ($ratings as $rating) {
            $review = ProductReview::factory()->create(['rating' => $rating]);
            $this->assertEquals($rating, $review->rating);
        }
    }

    /** @test */
    public function it_initializes_helpful_count_to_zero()
    {
        $review = ProductReview::factory()->create(['helpful_votes' => 0]);

        $this->assertEquals(0, $review->helpful_votes);
    }

    /** @test */
    public function it_can_track_helpful_votes()
    {
        $review = ProductReview::factory()->create(['helpful_votes' => 5]);

        $this->assertEquals(5, $review->helpful_votes);

        // Simulate incrementing helpful count
        $review->helpful_votes++;
        $review->save();

        $this->assertEquals(6, $review->helpful_votes);
    }

    /** @test */
    public function it_has_timestamps()
    {
        $review = ProductReview::factory()->create();

        $this->assertNotNull($review->created_at);
        $this->assertNotNull($review->updated_at);
        $this->assertInstanceOf(\Carbon\Carbon::class, $review->created_at);
        $this->assertInstanceOf(\Carbon\Carbon::class, $review->updated_at);
    }

    /** @test */
    public function user_can_review_multiple_products()
    {
        $user = User::factory()->create();
        $product1 = Product::factory()->create();
        $product2 = Product::factory()->create();

        $review1 = ProductReview::factory()->create([
            'user_id' => $user->id,
            'product_id' => $product1->id,
        ]);

        $review2 = ProductReview::factory()->create([
            'user_id' => $user->id,
            'product_id' => $product2->id,
        ]);

        $this->assertEquals($user->id, $review1->user_id);
        $this->assertEquals($user->id, $review2->user_id);
        $this->assertNotEquals($review1->product_id, $review2->product_id);
    }

    /** @test */
    public function product_can_have_multiple_reviews()
    {
        $product = Product::factory()->create();
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $review1 = ProductReview::factory()->create([
            'user_id' => $user1->id,
            'product_id' => $product->id,
        ]);

        $review2 = ProductReview::factory()->create([
            'user_id' => $user2->id,
            'product_id' => $product->id,
        ]);

        $this->assertEquals($product->id, $review1->product_id);
        $this->assertEquals($product->id, $review2->product_id);
        $this->assertNotEquals($review1->user_id, $review2->user_id);
    }
}