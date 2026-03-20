@extends('emails.layout')

@section('title', 'Pre-order Arrived')

@section('content')
    <h1>Your Pre-order Has Arrived! 🎉</h1>
    
    <p>Hi {{ $user->first_name }},</p>
    
    <p>Exciting news! The item you pre-ordered has arrived at our warehouse and is ready for you.</p>
    
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
                <td class="label">Quantity:</td>
                <td>{{ $preorder->quantity }}</td>
            </tr>
            <tr>
                <td class="label">Deposit Paid:</td>
                <td>₱{{ number_format($preorder->deposit_amount, 2) }}</td>
            </tr>
            <tr>
                <td class="label">Remaining Balance:</td>
                <td><strong>₱{{ number_format($preorder->remaining_amount, 2) }}</strong></td>
            </tr>
            @if($preorder->full_payment_due_date)
            <tr>
                <td class="label">Payment Due Date:</td>
                <td>{{ $preorder->full_payment_due_date->format('F d, Y') }}</td>
            </tr>
            @endif
        </table>
    </div>
    
    <p style="text-align: center;">
        <a href="{{ config('app.url') }}/preorders/{{ $preorder->id }}/complete-payment" class="button">Complete Payment Now</a>
    </p>
    
    <p>Please complete your payment to secure your item. Once payment is confirmed, we'll ship your order right away!</p>
    
    <p><strong>Note:</strong> If payment is not received by the due date, your pre-order may be cancelled and your deposit refunded.</p>
@endsection
