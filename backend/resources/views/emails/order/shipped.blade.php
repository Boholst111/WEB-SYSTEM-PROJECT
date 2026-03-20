@extends('emails.layout')

@section('title', 'Order Shipped')

@section('content')
    <h1>Your Order Has Shipped! 📦</h1>
    
    <p>Hi {{ $user->first_name }},</p>
    
    <p>Great news! Your order has been shipped and is on its way to you.</p>
    
    <div class="order-details">
        <h2>Shipping Information</h2>
        <table>
            <tr>
                <td class="label">Order Number:</td>
                <td>#{{ $order->order_number }}</td>
            </tr>
            <tr>
                <td class="label">Tracking Number:</td>
                <td><strong>{{ $tracking_number }}</strong></td>
            </tr>
            <tr>
                <td class="label">Courier:</td>
                <td>{{ $order->courier_service ?? 'Standard Shipping' }}</td>
            </tr>
            @if($order->estimated_delivery)
            <tr>
                <td class="label">Estimated Delivery:</td>
                <td>{{ $order->estimated_delivery->format('F d, Y') }}</td>
            </tr>
            @endif
        </table>
    </div>
    
    <p style="text-align: center;">
        <a href="{{ config('app.url') }}/orders/{{ $order->id }}/track" class="button">Track Your Order</a>
    </p>
    
    <p>You can use the tracking number above to monitor your shipment's progress.</p>
    
    <p>If you have any questions about your order, please don't hesitate to contact us.</p>
@endsection
