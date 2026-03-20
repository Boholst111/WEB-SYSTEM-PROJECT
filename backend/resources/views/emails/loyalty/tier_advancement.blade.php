@extends('emails.layout')

@section('title', 'Loyalty Tier Advancement')

@section('content')
    <h1>Congratulations! 🎊</h1>
    
    <p>Hi {{ $user->first_name }},</p>
    
    <p>We're excited to inform you that you've been upgraded to <strong>{{ ucfirst($new_tier) }} Tier</strong> in our Diecast Credits loyalty program!</p>
    
    <div class="order-details">
        <h2>Your New Benefits</h2>
        <ul style="padding-left: 20px;">
            @foreach($benefits as $benefit)
                <li style="margin: 10px 0;">{{ $benefit }}</li>
            @endforeach
        </ul>
    </div>
    
    <div class="order-details">
        <h2>Your Loyalty Status</h2>
        <table>
            <tr>
                <td class="label">Previous Tier:</td>
                <td>{{ ucfirst($old_tier) }}</td>
            </tr>
            <tr>
                <td class="label">New Tier:</td>
                <td><strong>{{ ucfirst($new_tier) }}</strong></td>
            </tr>
            <tr>
                <td class="label">Current Credits:</td>
                <td>₱{{ number_format($user->loyalty_credits, 2) }}</td>
            </tr>
            <tr>
                <td class="label">Total Spent:</td>
                <td>₱{{ number_format($user->total_spent, 2) }}</td>
            </tr>
        </table>
    </div>
    
    <p style="text-align: center;">
        <a href="{{ config('app.url') }}/loyalty" class="button">View Your Loyalty Dashboard</a>
    </p>
    
    <p>Thank you for being a valued member of the Diecast Empire community. We look forward to continuing to serve your collecting needs!</p>
@endsection
