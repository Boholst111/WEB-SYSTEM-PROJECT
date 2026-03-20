@extends('emails.layout')

@section('title', 'Payment Reminder')

@section('content')
    <h1>Payment Reminder ⏰</h1>
    
    <p>Hi {{ $user->first_name }},</p>
    
    <p>This is a friendly reminder that your pre-order payment is due soon.</p>
    
    <div class="order-details">
        <h2>Pre-order Details</h2>
        <table>
            <tr>
                <td class="label">Pre-order Number:</td>
                <td>#{{ $preorder->preorder_number }}</td>
            </tr>
            <tr>
                <td class="label">Product:</td>
                <td><strong>{{ $product->name }}</strong></td>
            </tr>
            <tr>
                <td class="label">Remaining Balance:</td>
                <td><strong>₱{{ number_format($preorder->remaining_amount, 2) }}</strong></td>
            </tr>
            <tr>
                <td class="label">Payment Due Date:</td>
                <td><strong>{{ $preorder->full_payment_due_date->format('F d, Y') }}</strong></td>
            </tr>
            @if($days_until_due !== null)
            <tr>
                <td class="label">Days Remaining:</td>
                <td><strong>{{ $days_until_due }} day(s)</strong></td>
            </tr>
            @endif
        </table>
    </div>
    
    <p style="text-align: center;">
        <a href="{{ config('app.url') }}/preorders/{{ $preorder->id }}/complete-payment" class="button">Pay Now</a>
    </p>
    
    <p>Don't miss out on your reserved item! Complete your payment before the due date to secure your order.</p>
    
    <p>If you have any questions or need assistance, please contact our customer support team.</p>
@endsection
