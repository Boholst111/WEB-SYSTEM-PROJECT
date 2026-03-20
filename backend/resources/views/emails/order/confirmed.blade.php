@extends('emails.layout')

@section('title', 'Order Confirmed')

@section('content')
    <h1>Order Confirmed!</h1>
    
    <p>Hi {{ $user->first_name }},</p>
    
    <p>Thank you for your order! We've received your order and it's being prepared for shipment.</p>
    
    <div class="order-details">
        <h2>Order Details</h2>
        <table>
            <tr>
                <td class="label">Order Number:</td>
                <td>#{{ $order->order_number }}</td>
            </tr>
            <tr>
                <td class="label">Order Date:</td>
                <td>{{ $order->created_at->format('F d, Y') }}</td>
            </tr>
            <tr>
                <td class="label">Total Amount:</td>
                <td>₱{{ number_format($order->total_amount, 2) }}</td>
            </tr>
            <tr>
                <td class="label">Payment Method:</td>
                <td>{{ ucfirst($order->payment_method) }}</td>
            </tr>
        </table>
    </div>
    
    <div class="order-details">
        <h2>Items Ordered</h2>
        @foreach($items as $item)
            <div style="padding: 10px 0; border-bottom: 1px solid #eee;">
                <strong>{{ $item->product_name }}</strong><br>
                Quantity: {{ $item->quantity }} × ₱{{ number_format($item->unit_price, 2) }}
                = ₱{{ number_format($item->subtotal, 2) }}
            </div>
        @endforeach
    </div>
    
    <div class="order-details">
        <h2>Shipping Address</h2>
        <p>
            {{ $order->shipping_address['name'] ?? $user->first_name . ' ' . $user->last_name }}<br>
            {{ $order->shipping_address['address_line1'] }}<br>
            @if(isset($order->shipping_address['address_line2']))
                {{ $order->shipping_address['address_line2'] }}<br>
            @endif
            {{ $order->shipping_address['city'] }}, {{ $order->shipping_address['province'] }} {{ $order->shipping_address['postal_code'] }}<br>
            {{ $order->shipping_address['phone'] }}
        </p>
    </div>
    
    <p style="text-align: center;">
        <a href="{{ config('app.url') }}/orders/{{ $order->id }}" class="button">View Order Details</a>
    </p>
    
    <p>We'll send you another email when your order ships.</p>
@endsection
