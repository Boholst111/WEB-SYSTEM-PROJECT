<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\User;
use App\Models\Order;
use App\Models\PreOrder;
use App\Models\Product;
use App\Models\NotificationPreference;
use App\Models\NotificationTemplate;
use App\Models\NotificationLog;
use App\Services\NotificationService;
use App\Services\SmsService;
use App\Mail\OrderConfirmed;
use App\Mail\OrderShipped;
use App\Mail\PreOrderArrival;
use App\Mail\PreOrderPaymentReminder;
use App\Mail\LoyaltyTierAdvancement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Mockery;

class NotificationSystemTest extends TestCase
{
    use RefreshDatabase;

    protected NotificationService $notificationService;
    protected $smsServiceMock;
    protected User $user;
    protected Order $order;
    protected PreOrder $preorder;
    protected Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        // Create mock SMS service
        $this->smsServiceMock = Mockery::mock(SmsService::class);
        $this->notificationService = new NotificationService($this->smsServiceMock);

        // Create test data
        $this->user = User::factory()->create([
            'email' => 'test@example.com',
            'phone' => '09171234567',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'loyalty_tier' => 'bronze',
            'loyalty_credits' => 100.00,
            'total_spent' => 5000.00,
        ]);

        $this->product = Product::factory()->create([
            'name' => 'Hot Wheels 1:64 Porsche 911',
            'sku' => 'HW-911-001',
        ]);

        $this->order = Order::factory()->create([
            'user_id' => $this->user->id,
            'order_number' => 'ORD-2024-001',
            'status' => 'pending',
            'total_amount' => 1500.00,
            'payment_method' => 'gcash',
            'shipping_address' => [
                'name' => 'John Doe',
                'address_line1' => '123 Main St',
                'city' => 'Manila',
                'province' => 'Metro Manila',
                'postal_code' => '1000',
                'phone' => '09171234567',
            ],
        ]);

        $this->preorder = PreOrder::factory()->create([
            'user_id' => $this->user->id,
            'product_id' => $this->product->id,
            'preorder_number' => 'PRE-2024-001',
            'quantity' => 1,
            'deposit_amount' => 300.00,
            'remaining_amount' => 700.00,
            'status' => 'deposit_paid',
        ]);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_sends_order_confirmation_email()
    {
        Mail::fake();

        $this->notificationService->sendOrderStatusUpdate($this->order, 'pending', 'confirmed');

        Mail::assertQueued(OrderConfirmed::class, function ($mail) {
            return $mail->order->id === $this->order->id;
        });
    }

    /** @test */
    public function it_sends_order_shipped_notification_with_email_and_sms()
    {
        Mail::fake();
        
        $this->order->update([
            'status' => 'shipped',
            'tracking_number' => 'TRACK123456',
        ]);

        $this->smsServiceMock
            ->shouldReceive('send')
            ->once()
            ->with($this->user->phone, Mockery::type('string'))
            ->andReturn(true);

        $this->notificationService->sendOrderStatusUpdate($this->order, 'processing', 'shipped');

        Mail::assertQueued(OrderShipped::class);
    }

    /** @test */
    public function it_sends_preorder_arrival_notification()
    {
        Mail::fake();

        $this->smsServiceMock
            ->shouldReceive('send')
            ->once()
            ->andReturn(true);

        $this->notificationService->sendPreOrderArrivalNotification($this->preorder);

        Mail::assertQueued(PreOrderArrival::class, function ($mail) {
            return $mail->preorder->id === $this->preorder->id;
        });
    }

    /** @test */
    public function it_sends_preorder_payment_reminder()
    {
        Mail::fake();

        $this->preorder->update([
            'full_payment_due_date' => now()->addDays(3),
        ]);

        $this->smsServiceMock
            ->shouldReceive('send')
            ->once()
            ->andReturn(true);

        $this->notificationService->sendPreOrderPaymentReminder($this->preorder);

        Mail::assertQueued(PreOrderPaymentReminder::class);
    }

    /** @test */
    public function it_sends_loyalty_tier_advancement_notification()
    {
        Mail::fake();

        $this->notificationService->sendLoyaltyTierAdvancement($this->user, 'bronze', 'silver');

        Mail::assertQueued(LoyaltyTierAdvancement::class, function ($mail) {
            return $mail->user->id === $this->user->id
                && $mail->oldTier === 'bronze'
                && $mail->newTier === 'silver';
        });
    }

    /** @test */
    public function it_respects_user_notification_preferences_for_email()
    {
        Mail::fake();

        // Create preferences that disable order updates
        $this->user->preferences = ['allow_order_updates' => false];
        $this->user->save();

        // Reload the order with fresh user data
        $this->order = $this->order->fresh();

        $this->notificationService->sendOrderStatusUpdate($this->order, 'pending', 'confirmed');

        // Email should not be sent due to preferences
        Mail::assertNothingQueued();
    }

    /** @test */
    public function it_respects_user_notification_preferences_for_sms()
    {
        Mail::fake();
        
        $this->user->preferences = ['allow_order_updates' => false];
        $this->user->save();

        $this->order->update([
            'status' => 'shipped',
            'tracking_number' => 'TRACK123',
        ]);

        // Reload order with fresh user
        $this->order = $this->order->fresh();

        // SMS should not be sent
        $this->smsServiceMock
            ->shouldNotReceive('send');

        $this->notificationService->sendOrderStatusUpdate($this->order, 'processing', 'shipped');

        // Add assertion to avoid risky test warning
        $this->assertTrue(true);
    }

    /** @test */
    public function it_sends_bulk_notifications_to_multiple_users()
    {
        $users = User::factory()->count(5)->create();
        $userIds = $users->pluck('id')->toArray();

        Mail::fake();

        $this->smsServiceMock
            ->shouldReceive('send')
            ->times(0); // No SMS for bulk email-only notifications

        $results = $this->notificationService->sendBulkNotification(
            $userIds,
            'Test Subject',
            'Test Message',
            ['email' => true, 'sms' => false]
        );

        $this->assertEquals(5, $results['summary']['total']);
        $this->assertEquals(5, $results['summary']['successful']);
        $this->assertEquals(0, $results['summary']['failed']);
    }

    /** @test */
    public function notification_preference_model_has_correct_defaults()
    {
        $defaults = NotificationPreference::defaults();

        $this->assertFalse($defaults['allow_email_marketing']);
        $this->assertFalse($defaults['allow_sms_marketing']);
        $this->assertTrue($defaults['allow_order_updates']);
        $this->assertTrue($defaults['allow_preorder_notifications']);
        $this->assertTrue($defaults['allow_loyalty_notifications']);
        $this->assertTrue($defaults['allow_security_alerts']);
    }

    /** @test */
    public function notification_template_can_render_with_variables()
    {
        $template = NotificationTemplate::create([
            'name' => 'Test Template',
            'type' => 'test_notification',
            'subject' => 'Hello {{name}}',
            'email_body' => 'Your order {{order_number}} is ready',
            'sms_body' => 'Order {{order_number}} ready',
            'variables' => ['name', 'order_number'],
            'is_active' => true,
        ]);

        $rendered = $template->render([
            'name' => 'John',
            'order_number' => 'ORD-123',
        ]);

        $this->assertEquals('Hello John', $rendered['subject']);
        $this->assertEquals('Your order ORD-123 is ready', $rendered['email_body']);
        $this->assertEquals('Order ORD-123 ready', $rendered['sms_body']);
    }

    /** @test */
    public function notification_template_validates_required_variables()
    {
        $template = NotificationTemplate::create([
            'name' => 'Test Template',
            'type' => 'test_validation',
            'subject' => 'Test',
            'email_body' => 'Test',
            'variables' => ['name', 'email'],
            'is_active' => true,
        ]);

        $this->assertTrue($template->validateData(['name' => 'John', 'email' => 'john@example.com']));
        $this->assertFalse($template->validateData(['name' => 'John']));
        $this->assertFalse($template->validateData([]));
    }

    /** @test */
    public function notification_log_can_track_sent_notifications()
    {
        $log = NotificationLog::create([
            'user_id' => $this->user->id,
            'type' => 'order_confirmed',
            'channel' => 'email',
            'recipient' => $this->user->email,
            'subject' => 'Order Confirmed',
            'message' => 'Your order has been confirmed',
            'status' => 'pending',
        ]);

        $log->markAsSent();

        $this->assertEquals('sent', $log->fresh()->status);
        $this->assertNotNull($log->fresh()->sent_at);
    }

    /** @test */
    public function notification_log_can_track_failed_notifications()
    {
        $log = NotificationLog::create([
            'user_id' => $this->user->id,
            'type' => 'order_confirmed',
            'channel' => 'email',
            'recipient' => $this->user->email,
            'status' => 'pending',
        ]);

        $log->markAsFailed('SMTP connection failed');

        $this->assertEquals('failed', $log->fresh()->status);
        $this->assertEquals('SMTP connection failed', $log->fresh()->error_message);
    }

    /** @test */
    public function notification_log_provides_statistics()
    {
        // Create some test logs
        NotificationLog::create([
            'user_id' => $this->user->id,
            'type' => 'test',
            'channel' => 'email',
            'recipient' => 'test@example.com',
            'status' => 'sent',
            'sent_at' => now(),
        ]);

        NotificationLog::create([
            'user_id' => $this->user->id,
            'type' => 'test',
            'channel' => 'email',
            'recipient' => 'test@example.com',
            'status' => 'sent',
            'sent_at' => now(),
        ]);

        NotificationLog::create([
            'user_id' => $this->user->id,
            'type' => 'test',
            'channel' => 'email',
            'recipient' => 'test@example.com',
            'status' => 'failed',
        ]);

        $stats = NotificationLog::getStats('day');

        $this->assertEquals(3, $stats['total']);
        $this->assertEquals(2, $stats['sent']);
        $this->assertEquals(1, $stats['failed']);
        $this->assertEquals(66.67, $stats['success_rate']);
    }

    /** @test */
    public function sms_service_formats_philippine_phone_numbers_correctly()
    {
        $smsService = new SmsService();

        // Test various formats
        $this->assertTrue($smsService->validatePhoneNumber('09171234567'));
        $this->assertTrue($smsService->validatePhoneNumber('639171234567'));
        $this->assertTrue($smsService->validatePhoneNumber('9171234567'));
        $this->assertTrue($smsService->validatePhoneNumber('+639171234567'));
    }

    /** @test */
    public function it_does_not_send_sms_when_phone_is_missing()
    {
        $userWithoutPhone = User::factory()->create([
            'phone' => null,
        ]);

        $order = Order::factory()->create([
            'user_id' => $userWithoutPhone->id,
            'status' => 'shipped',
            'tracking_number' => 'TRACK123',
        ]);

        $this->smsServiceMock
            ->shouldNotReceive('send');

        $this->notificationService->sendOrderStatusUpdate($order, 'processing', 'shipped');

        // Should complete without errors
        $this->assertTrue(true);
    }

    /** @test */
    public function it_handles_notification_service_errors_gracefully()
    {
        Mail::fake();
        Mail::shouldReceive('to')->andThrow(new \Exception('Mail server error'));

        // Should not throw exception
        $this->notificationService->sendOrderStatusUpdate($this->order, 'pending', 'confirmed');

        // Test passes if no exception is thrown
        $this->assertTrue(true);
    }

    /** @test */
    public function notification_preferences_can_be_created_and_updated()
    {
        $preferences = NotificationPreference::create([
            'user_id' => $this->user->id,
            'allow_email_marketing' => true,
            'allow_sms_marketing' => false,
            'allow_order_updates' => true,
        ]);

        $this->assertTrue($preferences->allow_email_marketing);
        $this->assertFalse($preferences->allow_sms_marketing);

        $preferences->update(['allow_email_marketing' => false]);

        $this->assertFalse($preferences->fresh()->allow_email_marketing);
    }

    /** @test */
    public function it_only_sends_sms_for_important_status_changes()
    {
        Mail::fake();

        // Confirmed status should not trigger SMS
        $this->smsServiceMock
            ->shouldNotReceive('send');

        $this->notificationService->sendOrderStatusUpdate($this->order, 'pending', 'confirmed');

        // Shipped status should trigger SMS
        $this->order->update([
            'status' => 'shipped',
            'tracking_number' => 'TRACK123',
        ]);

        $this->smsServiceMock
            ->shouldReceive('send')
            ->once()
            ->andReturn(true);

        $this->notificationService->sendOrderStatusUpdate($this->order, 'confirmed', 'shipped');

        // Add assertion to avoid risky test warning
        $this->assertTrue(true);
    }
}
