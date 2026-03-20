<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\Payment;
use App\Models\Order;
use App\Models\PreOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;

class PaymentModelTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_has_correct_fillable_attributes()
    {
        $fillable = [
            'order_id',
            'preorder_id',
            'payment_method',
            'gateway',
            'gateway_transaction_id',
            'amount',
            'currency',
            'status',
            'gateway_response',
            'processed_at',
            'failed_at',
            'failure_reason',
        ];

        $payment = new Payment();
        $this->assertEquals($fillable, $payment->getFillable());
    }

    /** @test */
    public function it_casts_attributes_correctly()
    {
        $payment = Payment::factory()->create([
            'amount' => '123.45',
            'gateway_response' => ['transaction_id' => 'TXN123', 'status' => 'success'],
            'processed_at' => '2024-01-15 10:30:00',
            'failed_at' => '2024-01-15 10:35:00',
        ]);

        $this->assertEquals(123.45, $payment->amount);
        $this->assertIsArray($payment->gateway_response);
        $this->assertEquals('TXN123', $payment->gateway_response['transaction_id']);
        $this->assertInstanceOf(\Carbon\Carbon::class, $payment->processed_at);
        $this->assertInstanceOf(\Carbon\Carbon::class, $payment->failed_at);
    }

    /** @test */
    public function it_belongs_to_order()
    {
        $order = Order::factory()->create();
        $payment = Payment::factory()->create(['order_id' => $order->id]);

        $this->assertInstanceOf(Order::class, $payment->order);
        $this->assertEquals($order->id, $payment->order->id);
    }

    /** @test */
    public function it_belongs_to_preorder()
    {
        $preorder = PreOrder::factory()->create();
        $payment = Payment::factory()->create(['preorder_id' => $preorder->id]);

        $this->assertInstanceOf(PreOrder::class, $payment->preorder);
        $this->assertEquals($preorder->id, $payment->preorder->id);
    }

    /** @test */
    public function it_checks_if_payment_is_completed()
    {
        $completedPayment = Payment::factory()->create(['status' => Payment::STATUS_COMPLETED]);
        $pendingPayment = Payment::factory()->create(['status' => Payment::STATUS_PENDING]);

        $this->assertTrue($completedPayment->isCompleted());
        $this->assertFalse($pendingPayment->isCompleted());
    }

    /** @test */
    public function it_checks_if_payment_failed()
    {
        $failedPayment = Payment::factory()->create(['status' => Payment::STATUS_FAILED]);
        $completedPayment = Payment::factory()->create(['status' => Payment::STATUS_COMPLETED]);

        $this->assertTrue($failedPayment->isFailed());
        $this->assertFalse($completedPayment->isFailed());
    }

    /** @test */
    public function it_gets_formatted_amount()
    {
        $payment = Payment::factory()->create(['amount' => 1234.56]);
        
        $this->assertEquals('₱1,234.56', $payment->getFormattedAmountAttribute());
    }

    /** @test */
    public function it_handles_null_relationships()
    {
        $payment = Payment::factory()->create([
            'order_id' => null,
            'preorder_id' => null,
        ]);

        $this->assertNull($payment->order);
        $this->assertNull($payment->preorder);
    }

    /** @test */
    public function it_can_be_associated_with_order_only()
    {
        $order = Order::factory()->create();
        $payment = Payment::factory()->create([
            'order_id' => $order->id,
            'preorder_id' => null,
        ]);

        $this->assertInstanceOf(Order::class, $payment->order);
        $this->assertNull($payment->preorder);
    }

    /** @test */
    public function it_can_be_associated_with_preorder_only()
    {
        $preorder = PreOrder::factory()->create();
        $payment = Payment::factory()->create([
            'order_id' => null,
            'preorder_id' => $preorder->id,
        ]);

        $this->assertNull($payment->order);
        $this->assertInstanceOf(PreOrder::class, $payment->preorder);
    }

    /** @test */
    public function it_stores_gateway_response_as_array()
    {
        $gatewayResponse = [
            'transaction_id' => 'TXN123456',
            'status' => 'success',
            'reference_number' => 'REF789',
            'gateway_fee' => 5.50,
        ];

        $payment = Payment::factory()->create([
            'gateway_response' => $gatewayResponse,
        ]);

        $this->assertIsArray($payment->gateway_response);
        $this->assertEquals('TXN123456', $payment->gateway_response['transaction_id']);
        $this->assertEquals('success', $payment->gateway_response['status']);
        $this->assertEquals('REF789', $payment->gateway_response['reference_number']);
        $this->assertEquals(5.50, $payment->gateway_response['gateway_fee']);
    }

    /** @test */
    public function it_handles_different_payment_statuses()
    {
        $statuses = [
            Payment::STATUS_PENDING,
            Payment::STATUS_PROCESSING,
            Payment::STATUS_COMPLETED,
            Payment::STATUS_FAILED,
            Payment::STATUS_CANCELLED,
            Payment::STATUS_REFUNDED,
        ];

        foreach ($statuses as $status) {
            $payment = Payment::factory()->create(['status' => $status]);
            $this->assertEquals($status, $payment->status);
        }
    }

    /** @test */
    public function it_handles_different_payment_methods()
    {
        $methods = ['gcash', 'maya', 'bank_transfer', 'credit_card'];

        foreach ($methods as $method) {
            $payment = Payment::factory()->create(['payment_method' => $method]);
            $this->assertEquals($method, $payment->payment_method);
        }
    }

    /** @test */
    public function it_handles_different_gateways()
    {
        $gateways = ['gcash', 'maya', 'paymongo', 'xendit'];

        foreach ($gateways as $gateway) {
            $payment = Payment::factory()->create(['gateway' => $gateway]);
            $this->assertEquals($gateway, $payment->gateway);
        }
    }
}