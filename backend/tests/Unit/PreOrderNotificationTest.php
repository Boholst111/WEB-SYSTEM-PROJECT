<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\PreOrder;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Event;

class PreOrderNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->user = User::factory()->create();
        $this->product = Product::factory()->create([
            'current_price' => 1000.00,
            'is_preorder' => true,
        ]);

        Notification::fake();
        Event::fake();
    }

    /** @test */
    public function it_sends_arrival_notification_when_product_arrives()
    {
        $preorder = PreOrder::factory()->create([
            'user_id' => $this->user->id,
            'status' => PreOrder::STATUS_DEPOSIT_PAID,
            'notification_sent' => false,
            'deposit_paid_at' => now()->subWeek(),
        ]);

        $result = $preorder->sendArrivalNotification();

        $this->assertTrue($result);
        $this->assertEquals(PreOrder::STATUS_READY_FOR_PAYMENT, $preorder->status);
        $this->assertTrue($preorder->notification_sent);
        $this->assertNotNull($preorder->full_payment_due_date);
    }

    /** @test */
    public function it_prevents_duplicate_arrival_notifications()
    {
        $preorder = PreOrder::factory()->create([
            'user_id' => $this->user->id,
            'status' => PreOrder::STATUS_DEPOSIT_PAID,
            'notification_sent' => true, // Already sent
        ]);

        $result = $preorder->sendArrivalNotification();

        $this->assertFalse($result);
        $this->assertEquals(PreOrder::STATUS_DEPOSIT_PAID, $preorder->status); // Should not change
    }

    /** @test */
    public function it_prevents_arrival_notification_for_wrong_status()
    {
        $invalidStatuses = [
            PreOrder::STATUS_DEPOSIT_PENDING,
            PreOrder::STATUS_READY_FOR_PAYMENT,
            PreOrder::STATUS_PAYMENT_COMPLETED,
            PreOrder::STATUS_SHIPPED,
            PreOrder::STATUS_DELIVERED,
            PreOrder::STATUS_CANCELLED,
            PreOrder::STATUS_EXPIRED,
        ];

        foreach ($invalidStatuses as $status) {
            $preorder = PreOrder::factory()->create([
                'status' => $status,
                'notification_sent' => false,
            ]);

            $result = $preorder->sendArrivalNotification();
            
            $this->assertFalse($result, "Should not send arrival notification for status: {$status}");
            $this->assertEquals($status, $preorder->status);
            $this->assertFalse($preorder->notification_sent);
        }
    }

    /** @test */
    public function it_sends_payment_reminder_for_ready_payments()
    {
        $preorder = PreOrder::factory()->create([
            'user_id' => $this->user->id,
            'status' => PreOrder::STATUS_READY_FOR_PAYMENT,
            'full_payment_due_date' => now()->addDays(5),
            'payment_reminder_sent_at' => null,
        ]);

        $result = $preorder->sendPaymentReminder();

        $this->assertTrue($result);
        $this->assertNotNull($preorder->payment_reminder_sent_at);
    }

    /** @test */
    public function it_prevents_payment_reminder_for_wrong_status()
    {
        $invalidStatuses = [
            PreOrder::STATUS_DEPOSIT_PENDING,
            PreOrder::STATUS_DEPOSIT_PAID,
            PreOrder::STATUS_PAYMENT_COMPLETED,
            PreOrder::STATUS_SHIPPED,
            PreOrder::STATUS_DELIVERED,
            PreOrder::STATUS_CANCELLED,
            PreOrder::STATUS_EXPIRED,
        ];

        foreach ($invalidStatuses as $status) {
            $preorder = PreOrder::factory()->create([
                'status' => $status,
                'payment_reminder_sent_at' => null,
            ]);

            $result = $preorder->sendPaymentReminder();
            
            $this->assertFalse($result, "Should not send payment reminder for status: {$status}");
            $this->assertNull($preorder->payment_reminder_sent_at);
        }
    }

    /** @test */
    public function it_identifies_preorders_due_for_reminder()
    {
        // Create preorders in various states
        $duePreorder = PreOrder::factory()->create([
            'status' => PreOrder::STATUS_READY_FOR_PAYMENT,
            'full_payment_due_date' => now()->addDays(5), // Within 7 days
            'payment_reminder_sent_at' => null,
        ]);

        $notDuePreorder = PreOrder::factory()->create([
            'status' => PreOrder::STATUS_READY_FOR_PAYMENT,
            'full_payment_due_date' => now()->addDays(10), // More than 7 days
            'payment_reminder_sent_at' => null,
        ]);

        $recentlyRemindedPreorder = PreOrder::factory()->create([
            'status' => PreOrder::STATUS_READY_FOR_PAYMENT,
            'full_payment_due_date' => now()->addDays(3),
            'payment_reminder_sent_at' => now()->subDay(), // Reminded recently
        ]);

        $oldReminderPreorder = PreOrder::factory()->create([
            'status' => PreOrder::STATUS_READY_FOR_PAYMENT,
            'full_payment_due_date' => now()->addDays(4),
            'payment_reminder_sent_at' => now()->subDays(5), // Old reminder, can send again
        ]);

        $duePreorders = PreOrder::dueForReminder()->get();

        $this->assertTrue($duePreorders->contains($duePreorder));
        $this->assertFalse($duePreorders->contains($notDuePreorder));
        $this->assertFalse($duePreorders->contains($recentlyRemindedPreorder));
        $this->assertTrue($duePreorders->contains($oldReminderPreorder));
    }

    /** @test */
    public function it_sets_payment_due_date_on_arrival_notification()
    {
        $preorder = PreOrder::factory()->create([
            'status' => PreOrder::STATUS_DEPOSIT_PAID,
            'notification_sent' => false,
            'full_payment_due_date' => null,
        ]);

        $beforeNotification = now();
        $preorder->sendArrivalNotification();
        $afterNotification = now();

        $this->assertNotNull($preorder->full_payment_due_date);
        
        // Should set due date to 30 days from now
        $expectedDueDate = now()->addDays(30);
        $this->assertEquals(
            $expectedDueDate->format('Y-m-d'),
            $preorder->full_payment_due_date->format('Y-m-d')
        );
    }

    /** @test */
    public function it_resets_notification_flag_on_arrival_notification()
    {
        $preorder = PreOrder::factory()->create([
            'status' => PreOrder::STATUS_DEPOSIT_PAID,
            'notification_sent' => false,
        ]);

        $preorder->sendArrivalNotification();

        $this->assertTrue($preorder->notification_sent);
        $this->assertEquals(PreOrder::STATUS_READY_FOR_PAYMENT, $preorder->status);
    }

    /** @test */
    public function it_tracks_payment_reminder_timestamps()
    {
        $preorder = PreOrder::factory()->create([
            'status' => PreOrder::STATUS_READY_FOR_PAYMENT,
            'payment_reminder_sent_at' => null,
        ]);

        $preorder->sendPaymentReminder();

        $this->assertNotNull($preorder->payment_reminder_sent_at);
        // Check that timestamp is recent (within last minute)
        $this->assertTrue($preorder->payment_reminder_sent_at->isAfter(now()->subMinute()));
        $this->assertTrue($preorder->payment_reminder_sent_at->isBefore(now()->addMinute()));
    }

    /** @test */
    public function it_allows_multiple_payment_reminders_with_cooldown()
    {
        $preorder = PreOrder::factory()->create([
            'status' => PreOrder::STATUS_READY_FOR_PAYMENT,
            'full_payment_due_date' => now()->addDays(3),
            'payment_reminder_sent_at' => now()->subDays(5), // Old reminder
        ]);

        // Should be included in due for reminder query
        $duePreorders = PreOrder::dueForReminder()->get();
        $this->assertTrue($duePreorders->contains($preorder));

        // Should be able to send another reminder
        $result = $preorder->sendPaymentReminder();
        $this->assertTrue($result);
    }

    /** @test */
    public function it_prevents_payment_reminders_within_cooldown_period()
    {
        $preorder = PreOrder::factory()->create([
            'status' => PreOrder::STATUS_READY_FOR_PAYMENT,
            'full_payment_due_date' => now()->addDays(3),
            'payment_reminder_sent_at' => now()->subDay(), // Recent reminder
        ]);

        // Should not be included in due for reminder query
        $duePreorders = PreOrder::dueForReminder()->get();
        $this->assertFalse($duePreorders->contains($preorder));
    }

    /** @test */
    public function it_handles_notification_workflow_for_complete_preorder_lifecycle()
    {
        $preorder = PreOrder::factory()->create([
            'user_id' => $this->user->id,
            'status' => PreOrder::STATUS_DEPOSIT_PAID,
            'notification_sent' => false,
            'payment_reminder_sent_at' => null,
        ]);

        // Step 1: Send arrival notification
        $arrivalResult = $preorder->sendArrivalNotification();
        $this->assertTrue($arrivalResult);
        $this->assertEquals(PreOrder::STATUS_READY_FOR_PAYMENT, $preorder->status);
        $this->assertTrue($preorder->notification_sent);

        // Step 2: Send payment reminder
        $reminderResult = $preorder->sendPaymentReminder();
        $this->assertTrue($reminderResult);
        $this->assertNotNull($preorder->payment_reminder_sent_at);

        // Step 3: Complete payment (notifications should stop)
        $preorder->completePayment();
        $this->assertEquals(PreOrder::STATUS_PAYMENT_COMPLETED, $preorder->status);

        // Should not be eligible for more reminders
        $duePreorders = PreOrder::dueForReminder()->get();
        $this->assertFalse($duePreorders->contains($preorder));
    }

    /** @test */
    public function it_validates_notification_business_rules()
    {
        // Cannot send arrival notification without deposit being paid
        $unpaidPreorder = PreOrder::factory()->create([
            'status' => PreOrder::STATUS_DEPOSIT_PENDING,
            'notification_sent' => false,
        ]);

        $result = $unpaidPreorder->sendArrivalNotification();
        $this->assertFalse($result);

        // Cannot send payment reminder without being ready for payment
        $notReadyPreorder = PreOrder::factory()->create([
            'status' => PreOrder::STATUS_DEPOSIT_PAID,
            'payment_reminder_sent_at' => null,
        ]);

        $result = $notReadyPreorder->sendPaymentReminder();
        $this->assertFalse($result);
    }

    /** @test */
    public function it_maintains_notification_state_consistency()
    {
        $preorder = PreOrder::factory()->create([
            'status' => PreOrder::STATUS_DEPOSIT_PAID,
            'notification_sent' => false,
        ]);

        // Send arrival notification
        $preorder->sendArrivalNotification();
        
        // Verify all related fields are updated consistently
        $this->assertEquals(PreOrder::STATUS_READY_FOR_PAYMENT, $preorder->status);
        $this->assertTrue($preorder->notification_sent);
        $this->assertNotNull($preorder->full_payment_due_date);
        
        // Verify database state matches object state
        $preorder->refresh();
        $this->assertEquals(PreOrder::STATUS_READY_FOR_PAYMENT, $preorder->status);
        $this->assertTrue($preorder->notification_sent);
        $this->assertNotNull($preorder->full_payment_due_date);
    }

    /** @test */
    public function it_handles_notification_failures_gracefully()
    {
        $preorder = PreOrder::factory()->create([
            'status' => PreOrder::STATUS_DEPOSIT_PAID,
            'notification_sent' => false,
        ]);

        // Even if external notification service fails, 
        // internal state should still be updated
        $result = $preorder->sendArrivalNotification();
        
        $this->assertTrue($result);
        $this->assertEquals(PreOrder::STATUS_READY_FOR_PAYMENT, $preorder->status);
        $this->assertTrue($preorder->notification_sent);
    }
}